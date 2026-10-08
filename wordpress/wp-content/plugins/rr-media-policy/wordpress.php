<?php
/** Hash every local variant, including originals retained after WordPress edits. */
function rrmp_files(int $id): array {
    if(!wp_attachment_is_image($id))throw new RuntimeException('Velg et bilde fra mediebiblioteket.');
    $uploads=wp_get_upload_dir();$base=realpath($uploads['basedir']);$main=get_attached_file($id,true);
    if(!$base||!is_string($main))throw new RuntimeException('Bildelageret er utilgjengelig.');
    $meta=wp_get_attachment_metadata($id);$paths=[$main];
    foreach($meta['sizes']??[]as$size)$paths[]=dirname($main).'/'.$size['file'];
    if(!empty($meta['original_image']))$paths[]=dirname($main).'/'.$meta['original_image'];
    $files=[];
    foreach(array_unique($paths)as$p){
        $real=realpath($p);
        if(!$real||!str_starts_with($real,$base.DIRECTORY_SEPARATOR)||is_link($p)||!is_file($real)||filesize($real)>50000000)throw new RuntimeException('Bildet eller en variant mangler eller er ugyldig.');
        $files[substr($real,strlen($base)+1)]=hash_file('sha256',$real);
    }
    ksort($files);return $files;
}
function rrmp_get(int $id): array { $r=get_post_meta($id,RRMP_META,true);return is_array($r)?$r:[]; }
function rrmp_presentation(int $id):array {
    $values=['caption'=>(string)get_post_field('post_excerpt',$id),'alt'=>(string)get_post_meta($id,'_wp_attachment_image_alt',true),
        'title'=>(string)get_post_field('post_title',$id),'description'=>(string)get_post_field('post_content',$id)];
    return array_map(static fn($value)=>hash('sha256',$value),$values);
}
function rrmp_valid(int $id):bool {return rrmp_current(rrmp_get($id),rrmp_files($id),rrmp_presentation($id));}
function rrmp_can_review(int $id): bool {return current_user_can('edit_post',$id)&&current_user_can('publish_posts');}
function rrmp_save(int $id,array $input): void {
    if(!rrmp_can_review($id)||!wp_verify_nonce((string)($input['nonce']??''),'rrmp-'.$id))throw new RuntimeException('Du mangler tilgang eller må laste siden på nytt.');
    $before=rrmp_get($id);
    if((int)($input['revision']??-1)!==(int)($before['revision']??0))throw new RuntimeException('Bildets opplysninger er endret. Last siden på nytt.');
    $clean=[];foreach(['origin','generator','producedOn','reference','description','correction']as$key)$clean[$key]=sanitize_text_field(is_string($input[$key]??null)?$input[$key]:'');
    $record=rrmp_record($clean,rrmp_files($id),$before,get_current_user_id(),($input['approve']??'')==='1',rrmp_presentation($id));
    $saved=$before?update_post_meta($id,RRMP_META,$record,$before):add_post_meta($id,RRMP_META,$record,true);
    if(!$saved)throw new RuntimeException('Opplysningene kunne ikke lagres. Last siden på nytt.');
}
add_filter('attachment_fields_to_edit',function(array $fields,$post):array{
    if(!wp_attachment_is_image($post->ID)||!rrmp_can_review($post->ID))return $fields;
    $r=rrmp_get($post->ID);$name='attachments['.$post->ID.'][rrmp]';
    $html='<input type="hidden" name="'.esc_attr($name.'[nonce]').'" value="'.esc_attr(wp_create_nonce('rrmp-'.$post->ID)).'"><input type="hidden" name="'.esc_attr($name.'[revision]').'" value="'.(int)($r['revision']??0).'">';
    $html.='<p><label>Bildeopphav <select name="'.esc_attr($name.'[origin]').'">';
    foreach(['unknown'=>'Uavklart','photo'=>'Fotografi uten generativ AI','illustration'=>'Illustrasjon uten generativ AI','ai_generated'=>'AI-generert illustrasjon','ai_edited'=>'AI-redigert bilde']as$value=>$label)$html.='<option value="'.$value.'"'.selected($r['origin']??'unknown',$value,false).'>'.esc_html($label).'</option>';
    $html.='</select></label></p>';
    foreach(['generator'=>'Generator/modell (skriv Ukjent når dette er avklart som ukjent)','producedOn'=>'Produksjonsdato','reference'=>'Kilde og rettighetsgrunnlag','description'=>'Hva er AI-redigert?','correction'=>'Begrunnelse for rettelse']as$key=>$label)$html.='<p><label>'.esc_html($label).'<input class="widefat" type="'.($key==='producedOn'?'date':'text').'" name="'.esc_attr($name.'['.$key.']').'" value="'.esc_attr($r[$key]??'').'"></label></p>';
    try{$ready=rrmp_valid($post->ID);}catch(Throwable){$ready=false;}
    $html.='<p>'.($ready?'Gjeldende bilde er godkjent.':'Bildet trenger avklaring eller ny godkjenning.').'</p><label><input type="checkbox" name="'.esc_attr($name.'[approve]').'" value="1"> Jeg har kontrollert bildet, opphavet, rettighetene og publikumsmerkingen og godkjenner denne versjonen</label><p>AI-merking følger bildet automatisk. Endret fil eller metadata krever ny godkjenning.</p>';
    $fields['rrmp']=['label'=>'Radio Rubben – bildeopphav','input'=>'html','html'=>$html];return $fields;
},10,2);
add_filter('attachment_fields_to_save',function(array $post,array $attachment):array{
    if(isset($attachment['rrmp'])&&is_array($attachment['rrmp']))try{rrmp_save((int)$post['ID'],wp_unslash($attachment['rrmp']));}catch(Throwable $e){$post['errors']['rrmp']['errors'][]=$e->getMessage();}
    return $post;
},10,2);
/** Public consumers get a read-only contract; approval identities/history remain private. */
add_action('rest_api_init',function():void{
    register_rest_field('attachment','rr_media_policy',['get_callback'=>function(array $post):array{
        $r=rrmp_get((int)$post['id']);try{$valid=rrmp_valid((int)$post['id']);}catch(Throwable){$valid=false;}
        return ['version'=>RRMP_VERSION,'origin'=>$r['origin']??'unknown','label'=>rrmp_public_label($r),'generator'=>$r['generator']??'','producedOn'=>$r['producedOn']??'','approved'=>$valid,'files'=>$r['files']??[]];
    },'schema'=>['type'=>'object','context'=>['view','edit'],'readonly'=>true]]);
});
function rrmp_dom(string $html): array {
    $dom=new DOMDocument();$previous=libxml_use_internal_errors(true);
    try{$dom->loadHTML('<?xml encoding="utf-8" ?><html><body>'.$html.'</body></html>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);}
    finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
    return [$dom,$dom->getElementsByTagName('body')->item(0)];
}
function rrmp_image_id(DOMElement $img): int {
    // The URL is authoritative. A forged wp-image-ID must not label another file.
    $src=$img->getAttribute('src');$id=(int)attachment_url_to_postid($src);
    if(!$id&&preg_match('/\bwp-image-(\d+)\b/',$img->getAttribute('class'),$m)){
        $candidate=(int)$m[1];$urls=[wp_get_attachment_url($candidate)];
        $meta=wp_get_attachment_metadata($candidate);$base=dirname((string)$urls[0]);
        foreach($meta['sizes']??[]as$s)$urls[]=$base.'/'.rawurlencode($s['file']);
        if(in_array($src,$urls,true))$id=$candidate;
    }
    return $id;
}
function rrmp_image_variants_valid(DOMElement $img,int $id):bool {
    if(!$img->hasAttribute('srcset'))return true;
    foreach(explode(',',$img->getAttribute('srcset'))as$entry){
        $url=preg_split('/\s+/',trim($entry))[0]??'';
        $candidate=clone $img;$candidate->setAttribute('src',$url);
        if(rrmp_image_id($candidate)!==$id)return false;
    }
    return true;
}
function rrmp_post_error(int $id,string $content,int $featured): ?string {
    $ids=$featured>0?[$featured]:[];
    [$dom]=$content!==''?rrmp_dom($content):[null];
    if($dom)foreach($dom->getElementsByTagName('img')as$img){$image=rrmp_image_id($img);if(!$image||!rrmp_image_variants_valid($img,$image))return 'Importer alle artikkelbilder til mediebiblioteket og avklar opphavet før publisering.';$ids[]=$image;}
    if($dom&&$dom->getElementsByTagName('picture')->length)return 'Bruk vanlige WordPress-bildeblokker slik at alle bildevarianter kan kontrolleres.';
    // Gallery images are resolved without executing arbitrary shortcodes.
    if(preg_match_all('/\[gallery\b[^\]]*\bids=["\x27]([0-9, ]+)["\x27][^\]]*\]/i',$content,$matches))foreach($matches[1]as$csv)foreach(explode(',',$csv)as$image)$ids[]=(int)$image;
    if(preg_match('/\[gallery\b/i',$content)&&!$matches[1])return 'Velg eksplisitte bilder i galleriet før publisering.';
    foreach(array_unique($ids)as$image){try{if(rrmp_valid($image))continue;}catch(Throwable){}return 'Bilde '.$image.' mangler avklart opphav eller gjeldende menneskelig godkjenning i mediebiblioteket.';}
    return null;
}
function rrmp_rest_gate($prepared,$request){
    if(is_wp_error($prepared))return $prepared;
    $id=(int)($prepared->ID??$request['id']??0);$old=$id?get_post($id):null;
    $status=$prepared->post_status??($old->post_status??'draft');
    if(!in_array($status,['publish','future','private'],true))return $prepared;
    $featured=$request->has_param('featured_media')?(int)$request['featured_media']:($id>0?(int)get_post_thumbnail_id($id):0);
    $error=rrmp_post_error($id,(string)($prepared->post_content??$old->post_content??''),$featured);
    if($error)return new WP_Error('rr_media_policy',$error,['status'=>409]);
    $GLOBALS['rrmp_rest_candidate']=['id'=>$id,'content'=>(string)($prepared->post_content??$old->post_content??''),'featured'=>$featured];
    return $prepared;
}
foreach(['post','page']as$type)add_filter('rest_pre_insert_'.$type,'rrmp_rest_gate',20,2);
add_filter('wp_insert_post_data',function(array $data,array $postarr):array{
    if(!in_array($data['post_type']??'',['post','page'],true)||!in_array($data['post_status']??'',['publish','future','private'],true))return $data;
    $id=(int)($postarr['ID']??0);$featured=(int)($postarr['meta_input']['_thumbnail_id']??$postarr['_thumbnail_id']??($id>0?get_post_thumbnail_id($id):0));
    $candidate=$GLOBALS['rrmp_rest_candidate']??null;
    if($candidate&&$candidate['id']===$id&&$candidate['content']===wp_unslash($data['post_content']??'')){
        $featured=$candidate['featured'];unset($GLOBALS['rrmp_rest_candidate']);
    }
    $error=rrmp_post_error($id,wp_unslash($data['post_content']??''),$featured);
    if($error){$data['post_status']='draft';if(get_current_user_id())set_transient('rrmp_notice_'.get_current_user_id(),$error,120);}
    return $data;
},20,2);
add_action('admin_notices',function():void{$key='rrmp_notice_'.get_current_user_id();$error=get_transient($key);if($error){delete_transient($key);echo '<div class="notice notice-error"><p>'.esc_html($error).' Saken er beholdt som utkast.</p></div>';}});
function rrmp_wrap(string $html,int $id): string {
    $label=rrmp_public_label(rrmp_get($id));if($label==='')return $html;
    return '<span class="rr-ai-image" data-rr-media-label="'.esc_attr($label).'" style="display:inline-block;position:relative;width:100%;height:100%;max-width:100%;vertical-align:middle">'.$html.'<span class="rr-ai-image-label" style="position:absolute;bottom:0;left:0;max-width:100%;box-sizing:border-box;background:#171717;color:#fff;padding:4px 7px;font:600 12px/1.4 sans-serif;white-space:normal">'.esc_html($label).'</span></span>';
}
add_filter('wp_get_attachment_image',function($html,$id){return rrmp_wrap($html,(int)$id);},99,2);
add_filter('wp_get_attachment_caption',function($caption,$id){$label=rrmp_public_label(rrmp_get((int)$id));return $label&&!str_contains($caption,$label)?$label.($caption?' — '.$caption:''):$caption;},20,2);
function rrmp_render_content(string $html): string {
    if(!str_contains(strtolower($html),'<img'))return $html;
    [$dom,$body]=rrmp_dom($html);if(!$body)return $html;
    // Never trust a marker in submitted HTML to suppress the server label.
    $xpath=new DOMXPath($dom);
    foreach(iterator_to_array($xpath->query('//*[@data-rr-media-label]'))as$wrapper){
        foreach(iterator_to_array($wrapper->childNodes)as$child){
            if($child instanceof DOMElement&&str_contains($child->getAttribute('class'),'rr-ai-image-label'))$wrapper->removeChild($child);
            else $wrapper->parentNode->insertBefore($child,$wrapper);
        }
        $wrapper->parentNode->removeChild($wrapper);
    }
    $images=iterator_to_array($dom->getElementsByTagName('img'));
    foreach($images as$img){
        $parent=$img->parentNode;
        $id=rrmp_image_id($img);if(!$id)continue;
        $original=$dom->saveHTML($img);$wrapped=rrmp_wrap($original,$id);if($wrapped===$original)continue;
        [$fragment,$fragmentBody]=rrmp_dom($wrapped);$replacement=$dom->importNode($fragmentBody->firstChild,true);$parent->replaceChild($replacement,$img);
    }
    $out='';foreach($body->childNodes as$child)$out.=$dom->saveHTML($child);return $out;
}
add_filter('the_content','rrmp_render_content',30);
add_filter('the_content_feed','rrmp_render_content',30);

/** Late thumbnail assignments by classic editor, REST, cron or integrations. */
function rrmp_thumbnail_gate($check,$objectId,$key,$value){
    if($key!=='_thumbnail_id'||(int)$value<=0||!in_array(get_post_type($objectId),['post','page'],true)||!in_array(get_post_status($objectId),['publish','future','private'],true))return $check;
    try{if(rrmp_valid((int)$value))return $check;}catch(Throwable){}
    return false;
}
add_filter('add_post_metadata','rrmp_thumbnail_gate',20,4);
add_filter('update_post_metadata','rrmp_thumbnail_gate',20,4);

add_filter('rest_request_after_callbacks',function($response){unset($GLOBALS['rrmp_rest_candidate']);return $response;});
