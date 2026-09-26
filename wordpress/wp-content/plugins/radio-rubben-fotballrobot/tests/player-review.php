<?php
namespace RadioRubben\Fotballrobot {
 class Robot {static function allowed(){return $GLOBALS['allowed'];}}
 class Writer {static function playerArticle($f,$c,$p){$GLOBALS['writes']++;if($GLOBALS['write_fail'])throw new \RuntimeException('Provider failed');return ['title'=>'Tiril med mål for Brann','lead'=>'Et kontrollert sammendrag.','paragraphs'=>['Registrerte opplysninger.'],'checks'=>[['claim'=>'Test','support'=>'facts']]];}}
}
namespace {
require __DIR__.'/../includes/player-review.php';
use RadioRubben\Fotballrobot\PlayerReview as R;
$options=[];$posts=[];$meta=[];$next=1;$mail=[];$writes=0;$write_fail=false;$mail_ok=true;$allowed=true;$publish=true;$count=0;
function check($v,$why){global $count;$count++;if(!$v)throw new RuntimeException($why);}
function rejects($fn,$why){try{$fn();}catch(Throwable $e){check(true,$why);return;}check(false,$why);}
function add_action(...$a){}function add_filter(...$a){}function get_option($k,$d=false){return $GLOBALS['options'][$k]??$d;}
function add_option($k,$v,...$a){if(isset($GLOBALS['options'][$k]))return false;$GLOBALS['options'][$k]=$v;return true;}
function delete_option($k){unset($GLOBALS['options'][$k]);}
function update_post_meta($id,$k,$v){$GLOBALS['meta'][$id][$k]=$v;}
function get_post_meta($id,$k,...$a){return $GLOBALS['meta'][$id][$k]??'';}
function get_post($id){return isset($GLOBALS['posts'][$id])?clone $GLOBALS['posts'][$id]:null;}
function wp_insert_post($v,...$a){global $next;$id=$next++;$GLOBALS['posts'][$id]=(object)(['ID'=>$id,'post_excerpt'=>'','post_content'=>'']+$v);foreach($v['meta_input']??[] as $k=>$m)update_post_meta($id,$k,$m);return $id;}
function wp_update_post($v,...$a){$v=R::guardTest($v+['post_status'=>get_post($v['ID'])->post_status],$v);foreach($v as $k=>$val)$GLOBALS['posts'][$v['ID']]->$k=$val;return $v['ID'];}
function get_posts($q){return array_values(array_filter($GLOBALS['posts'],fn($p)=>get_post_meta($p->ID,$q['meta_key'])===$q['meta_value']));}
function is_wp_error($r){return false;}function esc_html($v){return htmlspecialchars((string)$v);}function esc_url($v){return htmlspecialchars($v);}
function admin_url($p){return 'https://example.test/wp-admin/'.$p;}function wp_mail($to,$subject,$body,$headers){$GLOBALS['mail'][]=compact('to','subject','body','headers');return $GLOBALS['mail_ok'];}
function current_user_can($cap,...$a){return $GLOBALS['allowed']&&($cap!=='publish_posts'||$GLOBALS['publish']);}
function get_current_user_id(){return 7;}function sanitize_textarea_field($s){return trim(strip_tags($s));}
$f=['source'=>'https://www.fotball.no/fotballdata/person/profil/?fiksId=3942773','fetched_at'=>'2026-09-26T00:00:00Z'];
$id=R::create('test:1',$f,true);check($writes===1,'One write');check(count($mail)===1,'One notification');
check($mail[0]['to']===R::TO&&str_contains(implode(' ',$mail[0]['headers']),R::FROM),'Explicit mail addresses');
check(str_contains($mail[0]['subject'],'[TEST]'),'Test subject');check(!str_contains($mail[0]['body'],'operation=approve'),'Mail link is read-only');
check(R::create('test:1',$f,true)===$id&&$writes===1&&count($mail)===1,'Creation idempotent');R::notify($id);check(count($mail)===1,'Notification idempotent');
$s=R::state($id);$h=R::hash(get_post($id));
$allowed=false;rejects(fn()=>R::decide($id,$s['version'],$h,'approve',''),'Unauthorized');$allowed=true;
rejects(fn()=>R::decide($id,99,$h,'approve',''),'Stale version');
rejects(fn()=>R::decide($id,$s['version'],'bad','approve',''),'Stale content');
rejects(fn()=>R::decide($id,$s['version'],$h,'revise',''),'Revision needs comment');
R::decide($id,$s['version'],$h,'approve','Ser bra ut');check(get_post($id)->post_status==='draft'&&R::state($id)['status']==='test_approved','Test cannot publish');
wp_update_post(['ID'=>$id,'post_status'=>'publish']);check(get_post($id)->post_status==='draft','Direct editor publication blocked for test');
wp_update_post(['ID'=>$id,'post_status'=>'future']);check(get_post($id)->post_status==='draft','Scheduled test publication blocked');
rejects(fn()=>R::decide($id,$s['version'],$h,'approve',''),'Replay rejected');
$s=R::state($id);R::decide($id,$s['version'],R::hash(get_post($id)),'resubmit','');check(count($mail)===2,'New review email');
$s=R::state($id);R::decide($id,$s['version'],R::hash(get_post($id)),'revise','Kort ned teksten');check($writes===2&&count($mail)===3,'Revision writes and notifies');check(R::state($id)['status']==='pending','Revision needs fresh approval');
$s=R::state($id);R::decide($id,$s['version'],R::hash(get_post($id)),'reject','Ikke aktuell');check(get_post($id)->post_status==='draft'&&R::state($id)['status']==='rejected','Reject remains draft');
check(end(R::state($id)['history'])['comment']==='Ikke aktuell','Comment retained');
$id2=R::create('live:1',$f,false);$s=R::state($id2);$publish=false;rejects(fn()=>R::decide($id2,$s['version'],R::hash(get_post($id2)),'approve',''),'Publish capability required');$publish=true;
R::decide($id2,$s['version'],R::hash(get_post($id2)),'approve','');check(get_post($id2)->post_status==='publish','Real approval publishes once');rejects(fn()=>R::decide($id2,$s['version'],R::hash(get_post($id2)),'approve',''),'Published replay rejected');
$write_fail=true;$id3=R::create('fail:1',$f,false);check(R::state($id3)['status']==='failed','Failure visible');$n=$writes;R::create('fail:1',$f,false);check($writes===$n,'No automatic paid retry');
$s=R::state($id3);rejects(fn()=>R::decide($id3,$s['version'],R::hash(get_post($id3)),'resubmit',''),'No blank failed text sent for approval');
$write_fail=false;$mail_ok=false;$id4=R::create('mail:fail',$f,true);check(R::state($id4)['mail']==='failed','Mail failure visible');$s=R::state($id4);$mail_ok=true;R::decide($id4,$s['version'],R::hash(get_post($id4)),'mail','');check(R::state($id4)['mail']==='accepted','Explicit mail retry');
$options['rrfr_review_lock_'.$id4]=time();rejects(fn()=>R::decide($id4,R::state($id4)['version'],R::hash(get_post($id4)),'approve',''),'Concurrent actions blocked');
echo "$count approval checks passed; no real mail, AI calls or publication\n";
}
