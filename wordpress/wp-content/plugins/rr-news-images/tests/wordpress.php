<?php
/** Run only against a disposable WordPress database: wp eval-file tests/wordpress.php /absolute/package.zip */
if(!defined('RRNI_TEST_ISOLATED')||RRNI_TEST_ISOLATED!==true)throw new RuntimeException('Disposable test installation required.');
function rrni_test($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function rrni_reject($fn,$label){try{$fn();}catch(Throwable){echo "PASS $label\n";return;}throw new RuntimeException($label);}
$zip=$args[0]??getenv('RRNI_TEST_ZIP');if(!$zip)throw new RuntimeException('ZIP required.');
$first=rrni_import($zip);$repeat=rrni_import($zip);
rrni_test($first===$repeat,'second import returns identical real attachment IDs and URLs');
rrni_test(count($first['assets'])===21,'all 21 assets imported');
$a=reset(rrni_assets());$id=rrni_media_id($a);$bytes=file_get_contents(get_attached_file($id,true));
// Deliberate synthetic failure after creating an attachment, before writing metadata.
wp_delete_attachment($id,true);
$fail=static function($phase,$asset){if($phase==='attachment')throw new RuntimeException('Synthetic interrupted import');};
add_action('rrni_import_checkpoint',$fail,10,2);
rrni_reject(fn()=>rrni_import($zip),'interrupted attachment import reported');
remove_action('rrni_import_checkpoint',$fail,10);
$orphan=rrni_media_id($a);rrni_test($orphan>0,'recovery identity exists before metadata');
$recovered=rrni_import($zip);rrni_test($recovered['assets'][$a['asset_id']]['media_id']===$orphan,'retry recovers exact interrupted identity');
global $wpdb;
rrni_test((int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment' AND post_content_filtered LIKE 'rr-news-images:%'")===21,'retry after failure has no duplicates');
$lock=fopen(wp_get_upload_dir()['basedir'].'/rr-news-images/'.RRNI_PACKAGE.'/.import.lock','c');flock($lock,LOCK_EX);
rrni_reject(fn()=>rrni_import($zip),'concurrent importer cannot create duplicates');flock($lock,LOCK_UN);fclose($lock);
$file=get_attached_file($orphan,true);file_put_contents($file,substr($bytes,0,-1).'x');
rrni_reject(fn()=>rrni_catalog(),'changed file rejected by real media lookup');
rrni_reject(fn()=>rrni_import($zip),'retry never overwrites changed completed media');file_put_contents($file,$bytes);
update_post_meta($orphan,'_wp_attachment_image_alt','Changed by editor');rrni_reject(fn()=>rrni_catalog(),'changed alt text requires review');update_post_meta($orphan,'_wp_attachment_image_alt',$a['alt_text']);
$copy=wp_insert_attachment(['post_title'=>'Synthetic duplicate','post_status'=>'inherit','post_content_filtered'=>rrni_marker($a),'post_mime_type'=>'image/png'],false,0,true);
rrni_reject(fn()=>rrni_import($zip),'duplicate existing identities fail closed');wp_delete_attachment($copy,true);
$catalog=rrni_catalog();
$context=['title'=>'Bremnes IL vant fotballkampen','intro'=>'Bremnes IL vant fotballkampen 3–1.','body'=>'Bremnes IL vant fotballkampen 3–1.','category'=>'local','entities'=>[]];
$choice=rrni_bind(rrni_assessment($context,['theme_id'=>'bremnes-fotball','reason'=>'Saken gjelder Bremnes IL-fotball og kampmotivet passer.','evidence'=>[$context['body']],
 'motif_fits'=>true,'confidence'=>0.95,'main_angle'=>'bremnes_football','primary_club'=>'Bremnes IL','bremnes_focus'=>true]),$catalog)+['context_hash'=>rrni_context_hash($context)];
wp_set_current_user(1);
$call=static function($data,$id=0){$r=new WP_REST_Request('POST','/wp/v2/posts'.($id?'/'.$id:''));$r->set_body_params($data);$result=rest_do_request($r);return [$result->get_status(),$result->get_data()];};
$payload=['title'=>$context['title'],'excerpt'=>$context['intro'],'content'=>'<p>Et syntetisk referat.</p><p>Kilde: <a href="https://example.test/original">Originalkilden</a>.</p>',
 'status'=>'publish','featured_media'=>$choice['variants']['article_landscape']['media_id'],'rr_news_image'=>$choice];
[$status]=$call($payload);rrni_test($status===409,'automatic image is not publication approval');
$payload['status']='draft';[$status,$draft]=$call($payload);rrni_test($status===201&&$draft['status']==='draft','new draft receives actual selected featured media');$postId=$draft['id'];
rrni_test(get_post_meta($postId,'_rrni_selection',true)===$choice,'case image metadata saved on draft');
$choice['reviewed_by']='Synthetic editor';$choice['reviewed_at']=gmdate('c');$payload['rr_news_image']=$choice;$payload['rr_news_image_confirmed']=true;$payload['status']='publish';unset($payload['featured_media']);
[$status,$published]=$call($payload,$postId);rrni_test($status===200&&$published['status']==='publish','human approval publishes exact draft');
rrni_test(get_post_meta($choice['variants']['article_landscape']['media_id'],'_rrni_provenance',true)['approval']['actor']===1,'provenance approval records real authenticated actor');
rrni_test(str_contains($published['content']['rendered'],'https://example.test/original'),'source remains independent of theme');
$old=get_post($postId);$selection=get_post_meta($postId,'_rrni_selection',true);
wp_update_post(['ID'=>$postId,'post_status'=>'draft']);set_post_thumbnail($postId,$catalog['assets']['rr-politikk-liggende-v1']['media_id']);
[$status]=$call($payload,$postId);rrni_test($status===409,'changed WordPress editor image is preserved and blocks stale publish');
rrni_test((int)get_post_thumbnail_id($postId)===$catalog['assets']['rr-politikk-liggende-v1']['media_id'],'editor thumbnail not overwritten');
$unrelated=wp_insert_post(['post_title'=>'Unrelated synthetic article','post_content'=>'Existing manual content','post_type'=>'post','post_status'=>'publish']);
rrni_test(get_post_status($unrelated)==='publish','unrelated existing publication flow untouched');
$manual=array_replace(rrni_pending('Redaktøren har vurdert alle motiver og velger ingen illustrasjon.'),['status'=>'manual_none','mode'=>'manual','locked'=>true,'reviewed_by'=>'Synthetic editor','reviewed_at'=>gmdate('c')]);
[$status,$none]=$call(['title'=>'Ingen illustrasjon passer','content'=>'Syntetisk tekst.','status'=>'publish','rr_news_image'=>$manual,'rr_news_image_confirmed'=>true,'featured_media'=>0]);
rrni_test($status===201&&$none['featured_media']===0,'explicit human decision can publish without unsuitable illustration');
// Output from the actual WordPress image pipeline is used by browser QA.
$html='<!doctype html><html lang="nb"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/theme.css"><link rel="stylesheet" href="/images.css"><body style="margin:20px;background:#101c28;color:white"><h1>Isolert WordPress-visning</h1><div class="rr-post-grid">';
foreach(rrni_themes()as$theme=>$asset){$media=$catalog['assets'][$asset['asset_id']]['media_id'];$html.='<article class="rr-post-card"><a class="rr-post-thumb">'.wp_get_attachment_image($media,'thumbnail').'</a><h2>'.esc_html($theme).'</h2></article>';}
$html.='</div><article class="rr-single-image">'.wp_get_attachment_image($choice['variants']['article_landscape']['media_id'],'large').'</article></body></html>';
if($out=getenv('RRNI_TEST_HTML'))file_put_contents($out,$html);
rrni_test(!str_contains($html,'-150x150')&&str_contains($html,'rrni-whole-image')&&str_contains($html,RRNI_CAPTION),'thumbnail and article pipeline use full original with visible KI caption');
echo "WordPress integration passed in disposable installation. No production changes.\n";
