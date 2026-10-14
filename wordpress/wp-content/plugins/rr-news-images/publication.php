<?php
declare(strict_types=1);

/** Validate the exact case-bound selection at WordPress's existing REST publication boundary. */
function rrni_rest_gate($prepared, $request) {
    if (is_wp_error($prepared)) return $prepared;
    $id = (int)($request['id'] ?? 0);
    $choice = $request['rr_news_image'] ?? null;
    $saved = $id ? get_post_meta($id, '_rrni_selection', true) : [];
    $featuredCandidate=$request->has_param('featured_media')?(int)$request['featured_media']:($id?(int)get_post_thumbnail_id($id):0);
    if ($choice === null && !$saved && !rrni_asset_for_media($featuredCandidate)) return $prepared; // Unrelated posts are outside this rollout.
    if($choice===null && $saved && ($prepared->post_status??get_post_status($id))==='draft')return $prepared; // Draft edits stay possible; Studio verifies before publication.
    try {
        if (!is_array($choice) || !rrni_selection_valid($choice)) throw new RuntimeException('Bildet må velges og kontrolleres i Studio.');
        if ($saved && ($choice['theme_id'] ?? null) !== ($saved['theme_id'] ?? null)) throw new RuntimeException('Bildevalget er allerede levert. Bevar eller avklar WordPress-redaktørens valg.');
        if ($choice['status'] === 'selected') {
            $catalog = rrni_catalog();
            if (rrni_bind($choice, $catalog)['variants'] !== $choice['variants']) throw new RuntimeException('Bildets fil eller mediekobling har endret seg.');
            $featured = $request->has_param('featured_media') ? (int)$request['featured_media'] : ($id ? (int)get_post_thumbnail_id($id) : 0);
            if ($featured !== $choice['variants']['article_landscape']['media_id']) throw new RuntimeException('WordPress-bildet avviker. Bevar redaktørens valg og avklar saken før publisering.');
        } elseif($featuredCandidate>0) throw new RuntimeException('WordPress har et redaksjonelt bilde. Avklar det før et valg uten bilde kan leveres.');
        $status = $prepared->post_status ?? ($id ? get_post_status($id) : 'draft');
        if (in_array($status, ['publish','future','private'], true) && (!current_user_can('publish_posts')
            || ($request['rr_news_image_confirmed'] ?? false) !== true || empty($choice['reviewed_by']) || empty($choice['reviewed_at']))) throw new RuntimeException('Menneskelig bilde- og publiseringsgodkjenning mangler.');
        if(in_array($status,['future','private'],true))throw new RuntimeException('Denne flyten støtter utkast eller godkjent publisering.');
        if($status==='publish')rrni_approve_assets($choice);
        $GLOBALS['rrni_candidate'] = $choice;
        $GLOBALS['rrni_candidate_post']=['id'=>$id,'title'=>$prepared->post_title??get_post_field('post_title',$id),'content'=>$prepared->post_content??get_post_field('post_content',$id),'excerpt'=>$prepared->post_excerpt??get_post_field('post_excerpt',$id)];
    } catch (Throwable $e) { return new WP_Error('rrni_review', $e->getMessage(), ['status'=>409]); }
    return $prepared;
}
add_filter('rest_pre_insert_post', 'rrni_rest_gate', 15, 2);
add_action('rest_after_insert_post', static function($post, $request): void {
    if (!isset($GLOBALS['rrni_candidate'])) return;
    $choice = $GLOBALS['rrni_candidate']; unset($GLOBALS['rrni_candidate']);
    update_post_meta($post->ID, '_rrni_selection', $choice);
    update_post_meta($post->ID, '_rrni_review', ['actor'=>get_current_user_id(), 'at'=>gmdate('c'), 'confirmed'=>($request['rr_news_image_confirmed'] ?? false) === true]);
    update_post_meta($post->ID, '_rrni_post_approval', ($request['rr_news_image_confirmed']??false)===true ? rrni_post_hash($post,(int)get_post_thumbnail_id($post->ID),$choice) : '');
}, 10, 2);
add_filter('rest_request_after_callbacks', static function($response) { unset($GLOBALS['rrni_candidate'],$GLOBALS['rrni_candidate_post']); return $response; });

function rrni_approve_assets(array $choice): void {
    foreach($choice['variants']??[] as $variant){
        $id=$variant['media_id'];$a=rrni_assets()[$variant['asset_id']];
        $files=[_wp_relative_upload_path(get_attached_file($id,true))=>$a['sha256']];
        $input=['origin'=>'ai_generated','generator'=>rrni_manifest()['provenance']['generator'],'producedOn'=>rrni_manifest()['created_date'],
            'reference'=>rrni_manifest()['provenance']['rights_note'],'description'=>$a['subject']];
        $presentation=['caption'=>$a['caption'],'alt'=>$a['alt_text']];
        $before=get_post_meta($id,'_rrni_provenance',true)?:[];
        $record=rrni_mp_record($input,$files,$before,get_current_user_id(),true,$presentation);
        update_post_meta($id,'_rrni_provenance',$record);
        // When PR75 is separately activated, satisfy its existing provenance contract too.
        if(function_exists('rrmp_record')&&function_exists('rrmp_files')){
            update_post_meta($id,RRMP_META,rrmp_record($input,rrmp_files($id),rrmp_get($id),get_current_user_id(),true,rrmp_presentation($id)));
        }
    }
}
function rrni_post_hash($post,int $featured,array $choice): string {
    return hash('sha256',wp_json_encode([$post->post_title,$post->post_excerpt,$post->post_content,$featured,$choice]));
}
add_filter('wp_insert_post_data',static function($data,$postarr){
    if(($data['post_type']??'')!=='post'||!in_array($data['post_status']??'',['publish','future','private'],true))return $data;
    $id=(int)($postarr['ID']??0);$choice=$id?get_post_meta($id,'_rrni_selection',true):[];
    $featured=(int)($postarr['meta_input']['_thumbnail_id']??$postarr['_thumbnail_id']??($id?get_post_thumbnail_id($id):0));
    $candidate=$GLOBALS['rrni_candidate_post']??null;
    if($candidate&&$candidate['id']===$id&&$candidate['title']===wp_unslash($data['post_title'])&&$candidate['content']===wp_unslash($data['post_content'])&&$candidate['excerpt']===wp_unslash($data['post_excerpt']))return $data;
    if(!$choice&&!rrni_asset_for_media($featured))return $data;
    $post=(object)array_map('wp_unslash',$data);
    if(!$choice||rrni_post_hash($post,$featured,$choice)!==get_post_meta($id,'_rrni_post_approval',true))$data['post_status']='draft';
    return $data;
},15,2);
add_action('publish_future_post',static function($id){
    if(get_post_meta($id,'_rrni_selection',true))wp_update_post(['ID'=>$id,'post_status'=>'draft']);
},0);

// Originals only: no WordPress centre crops or cross-format srcset substitutions.
add_filter('intermediate_image_sizes_advanced', static fn($sizes, $meta, $id)=>rrni_asset_for_media((int)$id) ? [] : $sizes, 99, 3);
add_filter('image_downsize', static function($value, $id) {
    $a = rrni_asset_for_media((int)$id); if (!$a) return $value;
    return [wp_get_attachment_url($id), $a['width_px'], $a['height_px'], false];
}, 99, 2);
add_filter('wp_get_attachment_image_attributes', static function($attr, $attachment) {
    $a = rrni_asset_for_media((int)$attachment->ID); if (!$a) return $attr;
    $attr['class'] = ($attr['class'] ?? '').' rrni-whole-image'; $attr['data-rrni'] = $a['asset_id'];
    $attr['class'] .= ' skip-optimization'; $attr['data-skip-lazy'] = '1';
    $attr['alt'] = $a['alt_text']; $attr['srcset'] = ''; $attr['sizes'] = '';
    $attr['style'] = 'object-fit:contain!important;object-position:center!important;max-width:100%;';
    return $attr;
}, 999, 2);
add_filter('wp_get_attachment_image', static function($html, $id) {
    $a = rrni_asset_for_media((int)$id); if (!$a) return $html;
    return '<span class="rrni-image">'.$html.'<span class="rrni-caption">'.esc_html($a['caption']).'</span></span>';
}, 100, 2);
add_action('wp_enqueue_scripts', static function(): void { wp_enqueue_style('rr-news-images', plugins_url('images.css', __FILE__), [], '1.0.0'); });
add_filter('wp_get_attachment_caption', static function($caption, $id) { return rrni_asset_for_media((int)$id) ? RRNI_CAPTION : $caption; }, 99, 2);
// Social link cards keep the full landscape original. Feed/story variants are explicit Studio exports.
add_filter('wpseo_opengraph_image', static function($url) { $id = (int)get_post_thumbnail_id(); return rrni_asset_for_media($id) ? wp_get_attachment_url($id) : $url; });
add_filter('rank_math/opengraph/facebook/image', static function($url) { $id = (int)get_post_thumbnail_id(); return rrni_asset_for_media($id) ? wp_get_attachment_url($id) : $url; });
// Optimole 4.2.16: preserve the approved original, including when it builds a URL before tag replacement.
add_filter('optml_dont_replace_url', static function($skip,$url) {
    return $skip || (is_string($url) && rrni_url_valid($url) && str_contains((string)parse_url($url,PHP_URL_PATH),'/rr-news-images/'.RRNI_PACKAGE.'/'));
}, 99, 2);
