<?php
/** Approved image-only release. Never creates, edits or publishes news posts. */
declare(strict_types=1);
echo "RRNI_RELEASE_ENTERED\n";
function rrni_release_assert(bool $ok, string $why): void { if (!$ok) { fwrite(STDERR,'RRNI_GUARD: '.$why."\n"); throw new RuntimeException($why); } }
function rrni_release_posts(): array {
    global $wpdb;
    $rows=$wpdb->get_results("SELECT ID,post_status,post_title,post_excerpt,post_content,post_modified_gmt FROM {$wpdb->posts} WHERE post_type='post' ORDER BY ID",ARRAY_A);
    $meta=$wpdb->get_results("SELECT m.post_id,m.meta_key,m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID=m.post_id WHERE p.post_type='post' AND m.meta_key IN ('_thumbnail_id','_rrni_selection','_rrni_post_approval') ORDER BY m.meta_id",ARRAY_A);
    return ['count'=>count($rows),'sha256'=>hash('sha256',serialize([$rows,$meta]))];
}
function rrni_release_write(string $path, string $bytes, int $mode=0600): void {
    $tmp=tempnam(dirname($path),'.rrni-');
    try {
        rrni_release_assert($tmp!==false && file_put_contents($tmp,$bytes)===strlen($bytes) && chmod($tmp,$mode) && rename($tmp,$path),'Atomic release write failed');
    } finally { if($tmp&&is_file($tmp))unlink($tmp); }
}
rrni_release_assert(defined('WP_CLI')&&WP_CLI,'WP CLI required');
$operation=$args[0]??'';
rrni_release_assert(in_array($operation,['install','verify'],true),'Unknown operation');
$root='/customers/9/3/1/cptk37ymg/webroots/r1417157';
rrni_release_assert(realpath(ABSPATH)===$root && get_option('home')==='https://www.radiorubben.no','Unexpected production root/site');
rrni_release_assert(get_stylesheet()==='radio-rubben-next','Active theme changed; inspect before release');
$stage=__DIR__;
$stageRoot=getenv('HOME').'/.radiorubben-deploy/news-images';
rrni_release_assert(preg_match('/^[0-9]+-[0-9]+$/D',basename($stage))===1
    &&dirname($stage)===realpath($stageRoot)
    &&!is_link($stageRoot.'/'.basename($stage)),'Private stage required');
$manifest=json_decode(file_get_contents($stage.'/manifest.json'),true,32,JSON_THROW_ON_ERROR);
$allowed=['contract.php','data/01-BILDEREGISTER.json','import.php','publication.php','rr-news-images.php','images.css','lib/media-policy.php'];
rrni_release_assert(array_keys($manifest['files'])===$allowed && preg_match('/^[a-f0-9]{40}$/D',$manifest['commit'])===1,'Unexpected release file set');
foreach($manifest['files']as$file=>$hash)rrni_release_assert(!is_link($stage.'/plugin/'.$file)&&hash_file('sha256',$stage.'/plugin/'.$file)===$hash,'Stage hash mismatch: '.$file);
$work=getenv('HOME').'/.radiorubben-deploy';
$lock=$work.'/lock';
rrni_release_assert(@mkdir($lock,0700),'Another WordPress deployment holds the lock');
$activated=false;
try {
    $before=rrni_release_posts();
    rrni_release_write($stage.'/posts-before.json',json_encode($before,JSON_THROW_ON_ERROR));
    $target=WP_PLUGIN_DIR.'/rr-news-images';
    rrni_release_assert(realpath(WP_PLUGIN_DIR)===$root.'/wp-content/plugins'&&!is_link($target),'Unexpected plugin location');
    $installed=is_dir($target);
    require_once ABSPATH.'wp-admin/includes/plugin.php';
    $wasActive=is_plugin_active('rr-news-images/rr-news-images.php');
    $backup=$work.'/backups/news-images-'.basename($stage);
    rrni_release_assert(!file_exists($backup)&&mkdir($backup,0700,true),'Backup must be new');
    rrni_release_write($backup.'/before.json',json_encode(['installed'=>$installed,'active'=>$wasActive,'posts'=>$before,'manifest'=>$manifest],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT));
    if($installed){
        foreach($manifest['files']as$file=>$hash)rrni_release_assert(is_file($target.'/'.$file)&&!is_link($target.'/'.$file)&&hash_file('sha256',$target.'/'.$file)===$hash,'Existing plugin differs; refusing overwrite: '.$file);
    } else {
        rrni_release_assert($operation==='install','Plugin is not installed');
        // Publish a complete directory atomically; no mixed-version PHP requests.
        $pending=WP_PLUGIN_DIR.'/.rr-news-images-'.basename($stage);
        rrni_release_assert(!file_exists($pending)&&mkdir($pending,0755),'Staging plugin directory exists');
        foreach($manifest['files']as$file=>$hash){
            if(!is_dir(dirname($pending.'/'.$file)))rrni_release_assert(mkdir(dirname($pending.'/'.$file),0755,true),'Cannot create plugin directory');
            rrni_release_write($pending.'/'.$file,file_get_contents($stage.'/plugin/'.$file),0644);
            rrni_release_assert(hash_file('sha256',$pending.'/'.$file)===$hash,'Installed hash mismatch');
        }
        rrni_release_assert(rename($pending,$target),'Cannot install plugin directory');
        // Other active plugins may have primed get_plugins() before this atomic install.
        // Refresh discovery; activate_plugin still performs all normal validation.
        wp_clean_plugins_cache(false);
    }
    require_once $target.'/rr-news-images.php';
    if($operation==='install'){
        $zip=$stage.'/RadioRubben-Nyhetsbilder-v1.2.zip';
        rrni_release_assert(hash_file('sha256',$zip)==='a2d27533d3b9455a96d7c967ed4f87f6aba3827156914a468104bb8b21303adc','Wrong ZIP');
        // Full manifest validation happens before persistent archive/media writes.
        rrni_read_package($zip);
        $archiveDir=$work.'/news-images/archive';
        rrni_release_assert(!is_link($archiveDir),'Archive directory must not be a symlink');
        if(!is_dir($archiveDir))rrni_release_assert(mkdir($archiveDir,0700),'Cannot create archive');
        $archive=$archiveDir.'/RadioRubben-Nyhetsbilder-v1.2.zip';
        if(is_file($archive))rrni_release_assert(!is_link($archive)&&hash_file('sha256',$archive)===hash_file('sha256',$zip),'Existing archive differs');
        else rrni_release_write($archive,file_get_contents($zip));
        $first=rrni_import($archive);$catalog=rrni_import($archive);
        rrni_release_assert($first===$catalog,'Repeated import changed media identities');
        $receipt=get_option('rrni_package_receipt');
        $receipt['onedrive_status']='uploaded';
        $receipt['onedrive_item_id']='012VNUSIGB4FUDOXKHQRAYD3WJSRTTNAJY';
        $receipt['onedrive_url']='https://radiorubben-my.sharepoint.com/personal/thomas_sellevold-oystad_radiorubben_no/Documents/Radio%20Rubben/02%20Profil%20og%20maler/Nyhetsbilder/RadioRubben-Nyhetsbilder-v1.2/RadioRubben-Nyhetsbilder-v1.2.zip';
        $receipt['onedrive_sha256_verified']='2026-10-14';
        update_option('rrni_package_receipt',$receipt,false);
        rrni_release_assert(rrni_release_posts()===$before,'Editorial content changed during import; investigate without restoring content');
        if(!$wasActive){$error=activate_plugin('rr-news-images/rr-news-images.php');rrni_release_assert(!is_wp_error($error),'Plugin activation failed'.(is_wp_error($error)?': '.$error->get_error_code():''));$activated=true;}
    }else{$catalog=rrni_catalog();}
    rrni_release_assert(is_plugin_active('rr-news-images/rr-news-images.php'),'Image plugin is not active');
    rrni_catalog_validate($catalog);
    $unapproved=rrni_bind(['status'=>'selected','package'=>RRNI_PACKAGE,'package_version'=>'1.2','manifest_sha256'=>RRNI_MANIFEST_SHA,'theme_id'=>'bremnes-fotball','variants'=>[],
        'reason'=>'Syntetisk kontroll av menneskelig godkjenning.','mode'=>'automatic','locked'=>false],$catalog);
    $request=new WP_REST_Request('POST','/wp/v2/posts');
    $request->set_body_params(['status'=>'publish','rr_news_image'=>$unapproved,'featured_media'=>$unapproved['variants']['article_landscape']['media_id']]);
    $denied=rrni_rest_gate((object)['post_status'=>'publish','post_title'=>'Syntetisk kontroll','post_content'=>'','post_excerpt'=>''],$request);
    rrni_release_assert(is_wp_error($denied)&&$denied->get_error_code()==='rrni_review','Human approval gate failed');
    global $wpdb;
    $count=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_content_filtered LIKE 'rr-news-images:RadioRubben-Nyhetsbilder-v1.2:%'");
    rrni_release_assert($count===21,'Unexpected image identity count');
    // Exercise the live image pipeline and active theme styles without saving a post.
    $html='<!doctype html><html lang="nb"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    do_action('wp_enqueue_scripts');
    ob_start();wp_print_styles();$html.=ob_get_clean();
    $html.='<link rel="stylesheet" href="images.css"></head><body class="rr-news-first" style="margin:20px;background:#101c28;color:white"><h1>Produksjonskontroll av nyhetsbilder</h1><p>Syntetisk visning – ingen artikkel er opprettet eller publisert.</p><div class="rr-post-grid">';
    foreach(rrni_themes()as$theme=>$asset){$id=$catalog['assets'][$asset['asset_id']]['media_id'];$html.='<article class="rr-post-card"><a class="rr-post-thumb">'.wp_get_attachment_image($id,'rr-card').'</a><h2>'.esc_html($theme).'</h2></article>';}
    $bremnes=$catalog['assets']['rr-bremnes-fotball-liggende-v1']['media_id'];
    $html.='</div><article class="rr-wrap rr-single-image"><h2>Artikkel</h2><figure>'.wp_get_attachment_image($bremnes,'rr-hero').'<figcaption>'.esc_html(wp_get_attachment_caption($bremnes)).'</figcaption></figure></article><a class="rr-priority-image">'.wp_get_attachment_image($bremnes,'rr-card').'</a></body></html>';
    rrni_release_write($stage.'/preview.html',$html);
    rrni_release_write($stage.'/images.css',file_get_contents($target.'/images.css'));
    $after=rrni_release_posts();rrni_release_assert($after===$before,'Editorial content changed during verification');
    $result=['at'=>gmdate('c'),'operation'=>$operation,'commit'=>$manifest['commit'],'plugin_active'=>true,'media_count'=>$count,
        'repeat_import_verified'=>$operation==='install','posts_unchanged'=>$after,'backup'=>$backup,'receipt'=>get_option('rrni_package_receipt'),'catalog'=>$catalog];
    rrni_release_write($stage.'/result.json',json_encode($result,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    echo wp_json_encode(array_diff_key($result,['catalog'=>true]),JSON_UNESCAPED_SLASHES)."\nNEWS_IMAGES_WORDPRESS_VERIFIED\n";
}catch(Throwable $e){
    if($activated)deactivate_plugins('rr-news-images/rr-news-images.php',true);
    throw $e;
}finally{rmdir($lock);}
