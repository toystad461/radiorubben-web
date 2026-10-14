<?php
declare(strict_types=1);

function rrni_key(array $a): string { return 'rrni-'.hash('sha256', RRNI_PACKAGE.'|'.$a['asset_id'].'|'.$a['sha256']); }
function rrni_marker(array $a): string { return 'rr-news-images:'.RRNI_PACKAGE.':'.$a['asset_id'].':'.$a['sha256']; }
function rrni_media_id(array $a): int {
    global $wpdb;
    // Identity is in the INSERT's own columns, so recovery works even before meta is written.
    $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND post_content_filtered=%s", rrni_marker($a)));
    if (count($ids) > 1) throw new RuntimeException('Flere mediefiler har samme importidentitet: '.$a['asset_id']);
    if ($ids && get_post_status((int)$ids[0]) === 'trash') throw new RuntimeException('Mediefilen ligger i papirkurven. Gjenopprett og kontroller: '.$a['asset_id']);
    return (int)($ids[0] ?? 0);
}
function rrni_import(string $zipPath): array {
    global $wpdb;
    $files = rrni_read_package($zipPath);
    $uploads = wp_upload_dir();
    if (!empty($uploads['error'])) throw new RuntimeException('Medielageret er utilgjengelig.');
    $dir = $uploads['basedir'].'/rr-news-images/'.RRNI_PACKAGE;
    if (!wp_mkdir_p($dir)) throw new RuntimeException('Bildemappen kunne ikke opprettes.');
    $lock=fopen($dir.'/.import.lock','c');
    if (!$lock || !flock($lock, LOCK_EX|LOCK_NB)) { if($lock)fclose($lock); throw new RuntimeException('En annen bildeimport pågår. Prøv igjen senere.'); }
    try {
        foreach (rrni_assets() as $assetId => $a) {
            $id = rrni_media_id($a);
            $complete = $id ? get_post_meta($id, '_rrni_complete', true) : '';
            if ($complete !== '') { rrni_media($a); continue; } // Never repair or overwrite an editor's completed asset.
            if (!$id) {
                $id = wp_insert_attachment(['post_title'=>$a['asset_id'], 'post_name'=>rrni_key($a), 'post_status'=>'inherit',
                    'post_mime_type'=>'image/png', 'post_excerpt'=>$a['caption'], 'post_content_filtered'=>rrni_marker($a),
                    'post_content'=>'KI-illustrasjon. '.$a['subject']], false, 0, true);
                if (is_wp_error($id)) throw new RuntimeException('Medieidentitet kunne ikke lagres.');
                do_action('rrni_import_checkpoint', 'attachment', $assetId, $id);
            }
            $file = $dir.'/'.$a['asset_id'].'-'.$a['sha256'].'.png';
            if (is_file($file)) rrni_check_bytes((string)file_get_contents($file), $a);
            else {
                $temp = tempnam($dir, '.import-');
                try {
                    if (!$temp || file_put_contents($temp, $files[$assetId]) !== strlen($files[$assetId]) || !rename($temp, $file)) throw new RuntimeException('Bildet kunne ikke lagres.');
                    chmod($file, 0644);
                } finally { if ($temp && is_file($temp)) unlink($temp); }
            }
            update_attached_file($id, $file);
            update_post_meta($id, '_rrni_asset', ['package'=>RRNI_PACKAGE, 'asset_id'=>$assetId, 'sha256'=>$a['sha256']]);
            update_post_meta($id, '_wp_attachment_image_alt', $a['alt_text']);
            wp_update_attachment_metadata($id, ['width'=>$a['width_px'], 'height'=>$a['height_px'], 'file'=>_wp_relative_upload_path($file), 'filesize'=>$a['file_size_bytes'], 'sizes'=>[]]);
            $record = rrni_mp_record(['origin'=>'ai_generated','generator'=>rrni_manifest()['provenance']['generator'],
                'producedOn'=>rrni_manifest()['created_date'],'reference'=>rrni_manifest()['provenance']['rights_note'], 'description'=>$a['subject']],
                [_wp_relative_upload_path($file)=>$a['sha256']], [], 0, false);
            update_post_meta($id, '_rrni_provenance', $record); // Import is never editorial approval.
            do_action('rrni_import_checkpoint', 'metadata', $assetId, $id);
            update_post_meta($id, '_rrni_complete', RRNI_MANIFEST_SHA);
            rrni_media($a);
        }
        update_option('rrni_package_receipt', ['package'=>RRNI_PACKAGE, 'zip_sha256'=>hash_file('sha256', $zipPath), 'manifest_sha256'=>RRNI_MANIFEST_SHA,
            'archive_path'=>realpath($zipPath), 'media_directory'=>$dir, 'imported_at'=>gmdate('c'), 'onedrive_status'=>'not_uploaded'], false);
        return rrni_catalog();
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}
function rrni_media(array $a): array {
    $id = rrni_media_id($a);
    if (!$id || get_post_meta($id, '_rrni_complete', true) !== RRNI_MANIFEST_SHA) throw new RuntimeException('Opplasting mangler: '.$a['asset_id']);
    $file = get_attached_file($id, true); $base = realpath(wp_get_upload_dir()['basedir']); $real = $file ? realpath($file) : false;
    if (!$real || !$base || !str_starts_with($real, $base.DIRECTORY_SEPARATOR) || is_link($file)) throw new RuntimeException('Mediefilen mangler: '.$a['asset_id']);
    rrni_check_bytes((string)file_get_contents($real), $a);
    $identity=get_post_meta($id,'_rrni_asset',true);$metadata=wp_get_attachment_metadata($id);
    if($identity!==['package'=>RRNI_PACKAGE,'asset_id'=>$a['asset_id'],'sha256'=>$a['sha256']]
        ||($metadata['width']??0)!==$a['width_px']||($metadata['height']??0)!==$a['height_px'])throw new RuntimeException('Mediets registerkobling eller dimensjoner er endret: '.$a['asset_id']);
    $provenance=get_post_meta($id,'_rrni_provenance',true);
    if(($provenance['origin']??'')!=='ai_generated'||($provenance['generator']??'')!==rrni_manifest()['provenance']['generator'])throw new RuntimeException('Bildets KI-opphav mangler eller er endret: '.$a['asset_id']);
    if ((string)get_post_field('post_excerpt', $id) !== $a['caption'] || get_post_meta($id, '_wp_attachment_image_alt', true) !== $a['alt_text']) throw new RuntimeException('Bildetekst eller alternativ tekst er endret: '.$a['asset_id']);
    $url = wp_get_attachment_url($id); // Authoritative WordPress lookup, never a fabricated upload URL.
    if (!is_string($url) || !rrni_url_valid($url)) throw new RuntimeException('WordPress returnerte en ugyldig medieadresse.');
    return ['media_id'=>$id, 'url'=>$url, 'sha256'=>$a['sha256'], 'width_px'=>$a['width_px'], 'height_px'=>$a['height_px']];
}
function rrni_catalog(): array {
    $result = ['package'=>RRNI_PACKAGE, 'manifest_sha256'=>RRNI_MANIFEST_SHA, 'assets'=>[]];
    foreach (rrni_assets() as $id => $a) $result['assets'][$id] = rrni_media($a);
    return $result;
}
function rrni_asset_for_media(int $id): ?array {
    $meta = get_post_meta($id, '_rrni_asset', true);
    if (!is_array($meta) || ($meta['package'] ?? '') !== RRNI_PACKAGE) return null;
    return rrni_assets()[$meta['asset_id'] ?? ''] ?? null;
}
