<?php
namespace RadioRubben\Fotballrobot {
 class MicrosoftMail {static function url(){return 'https://example.test/mail-settings';}static function send($subject,$body){return \wp_mail(PlayerReview::TO,$subject,$body,PlayerReview::headers());}}
 class Players {static function ids(){return array_keys($GLOBALS['profiles']??[]);}static function state($id){if(!isset($GLOBALS['profiles'][$id]))throw new \RuntimeException('Unknown player');return $GLOBALS['profiles'][$id];}}
 class PlayerFacts {static function url($id){return 'https://www.fotball.no/fotballdata/person/profil/?fiksId='.$id;}}
 class Robot {static function allowed(){return $GLOBALS['allowed'];} static function latest($id){return $GLOBALS['matchFacts'];}}
 class Report {static function extras(...$args){return [];}}
 class Writer {static function playerArticle($f,$c,$p){$GLOBALS['writes']++;if($GLOBALS['write_fail'])throw new \RuntimeException('Provider failed');return ['_quality'=>['rulesVersion'=>'1.0.0','factsHash'=>EditorialQuality::hash($f),'publishable'=>true,'languageStatus'=>'completed','findings'=>[]],'title'=>'Tiril med mål for Brann','lead'=>'Et kontrollert sammendrag.','paragraphs'=>['Registrerte opplysninger.'],'checks'=>[['claim'=>'Test','support'=>'facts']]];}}
}
namespace {
require __DIR__.'/../includes/player-review.php';
use RadioRubben\Fotballrobot\PlayerReview as R;
$options=[];$posts=[];$meta=[];$next=1;$mail=[];$writes=0;$write_fail=false;$mail_ok=true;$allowed=true;$publish=true;$count=0;
function check($v,$why){global $count;$count++;if(!$v)throw new RuntimeException($why);}
function rejects($fn,$why){try{$fn();}catch(Throwable $e){check(true,$why);return;}check(false,$why);}
function register_rest_route($ns,$path,$route){$GLOBALS['routes'][$path]=$route;}function wp_next_scheduled($hook){return time()+100;}
function update_option($k,$v,...$args){$GLOBALS['options'][$k]=$v;return true;}
function add_action(...$a){}function add_filter(...$a){}function get_option($k,$d=false){return $GLOBALS['options'][$k]??$d;}
function add_option($k,$v,...$a){if(isset($GLOBALS['options'][$k]))return false;$GLOBALS['options'][$k]=$v;return true;}
function delete_option($k){unset($GLOBALS['options'][$k]);}
function update_post_meta($id,$k,$v){$GLOBALS['meta'][$id][$k]=$v;}
function get_post_meta($id,$k,...$a){return $GLOBALS['meta'][$id][$k]??'';}
function wp_attachment_is_image($id){return !in_array($id,$GLOBALS['missingImages']??[],true);}
function set_post_thumbnail($id,$media){update_post_meta($id,'_thumbnail_id',$media);return true;}
function get_post($id){return isset($GLOBALS['posts'][$id])?clone $GLOBALS['posts'][$id]:null;}
function wp_insert_post($v,...$a){global $next;$id=$next++;$GLOBALS['posts'][$id]=(object)(['ID'=>$id]+$v+['post_excerpt'=>'','post_content'=>'']);foreach($v['meta_input']??[] as $k=>$m)update_post_meta($id,$k,$m);return $id;}
function wp_update_post($v,...$a){if(!empty($GLOBALS['beforeUpdate'])){$f=$GLOBALS['beforeUpdate'];unset($GLOBALS['beforeUpdate']);$f($v['ID']);}$v=RadioRubben\Fotballrobot\PublicationGate::guard(R::guardTest($v+(array)get_post($v['ID']),$v),$v);foreach($v as $k=>$val)$GLOBALS['posts'][$v['ID']]->$k=$val;return $v['ID'];}
function get_posts($q){return array_values(array_filter($GLOBALS['posts'],fn($p)=>!array_key_exists('meta_value',$q)||get_post_meta($p->ID,$q['meta_key'])===$q['meta_value']));}
function is_wp_error($r){return false;}function esc_html($v){return htmlspecialchars((string)$v);}function esc_url($v){return htmlspecialchars($v);}
function wp_unslash($v){return $v;}function set_transient(...$a){}
function absint($v){return abs((int)$v);}function get_transient($k){return false;}function delete_transient($k){}
function esc_attr($v){return esc_html($v);}function wp_nonce_field($v){}function wp_kses_post($v){return $v;}
function get_edit_post_link($id,...$a){return admin_url('post.php?post='.$id);}function wp_die($m){throw new RuntimeException($m);}
function is_email($v){return filter_var($v,FILTER_VALIDATE_EMAIL);}
function admin_url($p){return 'https://example.test/wp-admin/'.$p;}function wp_mail($to,$subject,$body,$headers){$GLOBALS['mail'][]=compact('to','subject','body','headers');if(!empty($GLOBALS['mail_throw']))throw new RuntimeException('Transport exception');return $GLOBALS['mail_ok'];}
function current_user_can($cap,...$a){return $GLOBALS['allowed']&&($cap!=='publish_posts'||$GLOBALS['publish']);}
function get_current_user_id(){return 7;}function sanitize_textarea_field($s){return trim(strip_tags($s));}
function get_permalink($id){return 'https://example.test/article/'.$id;}
function wp_get_attachment_image($id,...$args){return '<img alt="Illustrasjon" width="1200" height="675" src="data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="675"><rect width="1200" height="675" fill="#223b36"/><circle cx="600" cy="337" r="130" fill="none" stroke="#99b2a3" stroke-width="8"/></svg>').'">';}
function wp_get_attachment_caption($id){return 'Illustrasjon. Viser ikke den aktuelle kampen.';}
function wp_create_nonce($action){return 'mock-nonce';}function rest_url($path){return 'https://example.test/wp-json/'.$path;}
function wp_json_encode($v,$flags=0){return json_encode($v,$flags);}
use RadioRubben\Fotballrobot\ReviewDesk as D;
use RadioRubben\Fotballrobot\PublicationGate as G;
use RadioRubben\Fotballrobot\EditorialQuality as Q;
function bindQuality($id){$f=G::facts($id);update_post_meta($id,G::META,G::bind(['rulesVersion'=>Q::RULES_VERSION,'factsHash'=>Q::hash(Q::packet($f)),'publishable'=>true,'languageStatus'=>'completed','findings'=>[]],(array)get_post($id)));}
function token($id){return D::token($id,D::model($id));}
function renderArticle($id){ob_start();D::article($id,D::model($id));return ob_get_clean();}
function fixture($name,$html){$dir=getenv('RRFR_RENDER_DIR');if(!$dir)return;if(!is_dir($dir))mkdir($dir,0700,true);$style=file_get_contents(__DIR__.'/../admin.css');ob_start();require __DIR__.'/../includes/review-desk-style.php';$extra=ob_get_clean();file_put_contents($dir.'/'.$name.'.html','<!doctype html><html lang="nb"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;background:#f0f0f1;padding:16px;font-family:system-ui}@media(max-width:600px){body{padding:8px}}'.$style.'</style>'.$extra.'<body><main class="rrfr rrfr-desk">'.$html.'</main></body></html>');}

$f=['source'=>'https://www.fotball.no/fotballdata/person/profil/?fiksId=1'];
$player=R::create('desk:player',$f,false);bindQuality($player);
$writesBefore=$writes;$mailBefore=count($mail);
$m=D::model($player);check($m['can_approve']&&$m['player'],'Existing player draft enters shared desk');
$html=renderArticle($player);check(strpos($html,'Lagret artikkeltekst')<strpos($html,'Godkjenn og publiser'),'Read before approval');
check(str_contains($html,'name="token"')&&!str_contains($html,'value="approve" disabled'),'Ready player exposes protected manual action');
check(str_contains($html,'<details class="rrfr-changes">'),'Changes tucked away');
check($writes===$writesBefore&&count($mail)===$mailBefore,'Reading page never writes or sends');
$allowed=false;rejects(fn()=>D::model($player),'Unauthenticated view refused');rejects(fn()=>D::decide($player,'bad','approve'),'Unauthenticated action refused');$allowed=true;
$publish=false;check(!D::model($player)['can_approve'],'No publish capability hides approval');rejects(fn()=>D::decide($player,token($player),'approve'),'No capability cannot approve');$publish=true;
$stale=token($player);$posts[$player]->post_content.=' Human edit.';
rejects(fn()=>D::decide($player,$stale,'approve'),'Stale player browser refused');
check(!D::model($player)['can_approve'],'Human edit needs fresh quality check');
$html=renderArticle($player);check(str_contains($html,'value="approve" disabled')&&str_contains($html,'rrfr-desk-check'),'Blocked view offers explicit saved-text check');fixture('blocked',$html);
bindQuality($player);$stale=token($player);update_post_meta($player,'_thumbnail_id',812);
rejects(fn()=>D::decide($player,$stale,'approve'),'Changed featured image invalidates page token');
D::decide($player,token($player),'approve');check(get_post($player)->post_status==='publish'&&R::state($player)['status']==='published','Player uses existing approval gate');
rejects(fn()=>D::decide($player,$stale,'approve'),'Player replay refused');

$matchFacts=['match'=>['id'=>123,'home'=>['name'=>'Bremnes','id'=>30365],'away'=>['name'=>'Eksempel','id'=>2],'score'=>[2,1],'kickoff'=>'2026-10-03T15:00:00+02:00'],'finished_confirmed'=>true];
$match=wp_insert_post(['post_type'=>'post','post_status'=>'draft','post_title'=>'Bremnes vant med kampens siste mål','post_content'=>RadioRubben\Fotballrobot\EditorialNotice::BLOCK.'<p>Bremnes vant 2–1 mot Eksempel lørdag. Dette er konstruert testinnhold for godkjenningssiden.</p><p>Et kontrollert kampreferat med korte avsnitt og kildelenker gjør teksten lett å lese på mobil.</p><p><strong>Bremnes:</strong> Eksempel, Eksempel. <small>(Innbyttere: Eksempel)</small></p><p><small>Kilder: <a href="https://www.fotball.no/fotballdata/kamp/?fiksId=123">fotball.no</a></small></p>','post_excerpt'=>'Testinngress.','meta_input'=>['_rrfr_ai_match'=>123,'_rrfr_fact_snapshot'=>$matchFacts,'_thumbnail_id'=>812]]);
bindQuality($match);$m=D::model($match);check($m['can_approve']&&!$m['player'],'Match draft uses same reading desk');
$html=renderArticle($match);check(str_contains($html,'konstruert testinnhold')&&str_contains($html,'AI-generert artikkel'),'Stored prose and disclosure are rendered');check(str_contains($html,'Illustrasjon. Viser ikke den aktuelle kampen.')&&str_contains($html,'<figure'),'Selected image and caption preserved');fixture('ready',$html);
$token=token($match);$matchFacts['match']['score']=[3,1];
check(!D::model($match)['can_approve'],'Changed facts invalidate match quality');rejects(fn()=>D::decide($match,$token,'approve'),'Changed facts refused at action');
$matchFacts['match']['score']=[2,1];bindQuality($match);
$meta[$match]['_rrfr_test_only']=true;check(!D::model($match)['can_approve'],'Test match never offers publication');rejects(fn()=>D::decide($match,token($match),'approve'),'Test match publication refused');unset($meta[$match]['_rrfr_test_only']);
$meta[$match]['_rrfr_trial_match']=123;rejects(fn()=>D::decide($match,token($match),'approve'),'Legacy trial publication refused');unset($meta[$match]['_rrfr_trial_match']);
$options['rrfr_review_lock_'.$match]=time();rejects(fn()=>D::decide($match,token($match),'approve'),'Concurrent match action refused');unset($options['rrfr_review_lock_'.$match]);
$token=token($match);D::decide($match,$token,'reject','Ikke aktuell nå.');check(get_post($match)->post_status==='draft'&&D::model($match)['status']==='rejected','Reject preserves match draft');
rejects(fn()=>D::decide($match,$token,'approve'),'Reject invalidates prior page');rejects(fn()=>D::decide($match,token($match),'approve'),'Rejected match needs reopening');
D::decide($match,token($match),'resubmit');check(D::model($match)['pending'],'Explicit reopen restores review');
rejects(fn()=>D::decide($match,token($match),'revise','Rewrite'),'No hidden paid match retry');
$beforeUpdate=static function($id){$GLOBALS['posts'][$id]->post_content.=' Samtidig redigering.';bindQuality($id);};
rejects(fn()=>D::decide($match,token($match),'approve'),'Concurrent newly checked text cannot inherit earlier approval');check(get_post($match)->post_status==='draft','Concurrent human edit remains draft and is not overwritten');
$meta[$match][D::META]['status']='pending';
$token=token($match);D::decide($match,$token,'approve');check(get_post($match)->post_status==='publish','Explicit match approval publishes');
check(end($meta[$match][D::META]['history'])['action']==='approve','Match approval audit retained');
rejects(fn()=>D::decide($match,$token,'approve'),'Published match replay refused');
$posts[$match]->post_status='trash';rejects(fn()=>D::model($match),'Trash never reopened by desk');
$test=R::create('desk:test',$f,true);bindQuality($test);D::decide($test,token($test),'approve');check(get_post($test)->post_status==='draft'&&R::state($test)['status']==='test_approved','Player test approval still never publishes');

$normal=wp_insert_post(['post_type'=>'post','post_status'=>'draft','post_title'=>'Human draft']);rejects(fn()=>D::model($normal),'Unmanaged drafts excluded');
$posts[$match]->post_status='draft';$meta[$match][D::META]=[];$posts[$player]->post_status='draft';$meta[$player][R::META]['status']='pending';
$_GET=[];ob_start();D::page();$page=ob_get_clean();check(strpos($page,'klare for gjennomlesing')<strpos($page,'Innstillinger og testforslag'),'Settings follow the queue');
check(str_contains($page,'Kampomtale')&&str_contains($page,'Spillersak'),'Unified queue includes both article types');fixture('queue',$page);
check(!str_contains($page,'operation=approve'),'GET links never approve');
echo "$count review desk checks passed; no network, real AI, email or publication\n";
}
