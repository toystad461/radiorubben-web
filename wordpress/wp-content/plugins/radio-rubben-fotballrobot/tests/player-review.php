<?php
namespace RadioRubben\Fotballrobot {
 class MicrosoftMail {static function send($subject,$body){return \wp_mail(PlayerReview::TO,$subject,$body,PlayerReview::headers());}}
 class Players {static function ids(){return array_keys($GLOBALS['profiles']??[]);}static function state($id){if(!isset($GLOBALS['profiles'][$id]))throw new \RuntimeException('Unknown player');return $GLOBALS['profiles'][$id];}}
 class PlayerFacts {static function url($id){return 'https://www.fotball.no/fotballdata/person/profil/?fiksId='.$id;}}
 class Robot {static function allowed(){return $GLOBALS['allowed'];}}
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
function get_post($id){return isset($GLOBALS['posts'][$id])?clone $GLOBALS['posts'][$id]:null;}
function wp_insert_post($v,...$a){global $next;$id=$next++;$GLOBALS['posts'][$id]=(object)(['ID'=>$id,'post_excerpt'=>'','post_content'=>'']+$v);foreach($v['meta_input']??[] as $k=>$m)update_post_meta($id,$k,$m);return $id;}
function wp_update_post($v,...$a){$v=RadioRubben\Fotballrobot\PublicationGate::guard(R::guardTest($v+(array)get_post($v['ID']),$v),$v);foreach($v as $k=>$val)$GLOBALS['posts'][$v['ID']]->$k=$val;return $v['ID'];}
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
$f=['source'=>'https://www.fotball.no/fotballdata/person/profil/?fiksId=3942773','fetched_at'=>'2026-09-26T00:00:00Z'];
$silent=R::create('silent:1',$f,false,false);
check(get_post($silent)->post_status==='draft' && count($mail)===0,'Explicit silent draft sends no message');
check(R::state($silent)['status']==='pending','Silent draft still needs manual approval');
wp_update_post(['ID'=>$silent,'post_status'=>'publish']);check(get_post($silent)->post_status==='draft','Silent draft retains publication gate');
$writes=0;
putenv('RRFR_REVIEW_FROM_EMAIL=approved-sender@example.org');putenv('RRFR_REVIEW_FROM_APPROVED=1');
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
$old=$GLOBALS['posts'][$id2]->post_content;
wp_update_post(['ID'=>$id2,'post_content'=>$old.' Ny påstand.']);
rejects(fn()=>R::decide($id2,$s['version'],R::hash(get_post($id2)),'approve',''),'Fresh browser hash cannot approve an unreviewed human edit');
wp_update_post(['ID'=>$id2,'post_content'=>$old]);
wp_update_post(['ID'=>$id2,'post_status'=>'publish']);check(get_post($id2)->post_status==='draft','Direct editor publication cannot bypass human approval');
R::decide($id2,$s['version'],R::hash(get_post($id2)),'approve','');check(get_post($id2)->post_status==='publish','Real approval publishes once');rejects(fn()=>R::decide($id2,$s['version'],R::hash(get_post($id2)),'approve',''),'Published replay rejected');
$write_fail=true;$id3=R::create('fail:1',$f,false);check(R::state($id3)['status']==='failed','Failure visible');$n=$writes;R::create('fail:1',$f,false);check($writes===$n,'No automatic paid retry');
$s=R::state($id3);rejects(fn()=>R::decide($id3,$s['version'],R::hash(get_post($id3)),'resubmit',''),'No blank failed text sent for approval');
$write_fail=false;$mail_ok=false;$id4=R::create('mail:fail',$f,true);check(R::state($id4)['mail']==='failed','Mail failure visible');$s=R::state($id4);$mail_ok=true;R::decide($id4,$s['version'],R::hash(get_post($id4)),'mail','');check(R::state($id4)['mail']==='accepted','Explicit mail retry');
$options['rrfr_review_lock_'.$id4]=time();rejects(fn()=>R::decide($id4,R::state($id4)['version'],R::hash(get_post($id4)),'approve',''),'Concurrent actions blocked');
$profiles=[1011=>['name'=>'Tiril Elisabeth Sellevold-Øystad','fiks_id'=>3942773,'enabled'=>true,'events'=>[],'last_checked'=>null,'error'=>null]];
$input=['fiks_id'=>3942773,'url'=>'https://www.brann.no/nyheter/eksempel?utm_source=test#x','title'=>'Et kontrollert testtreff','identity_note'=>'Fullt navn og klubb stemmer med profilen.','facts'=>['Kontrollert offentlig fotballopplysning i egne ord.'],'public_read'=>true,'published_at'=>gmdate(DATE_ATOM,time()-3600),'checked_at'=>gmdate(DATE_ATOM),'event_date'=>null];
$monitor=RadioRubben\Fotballrobot\PlayerMonitor::class;
$allowed=false;rejects(fn()=>$monitor::ingest(1011,$input),'Source ingest requires authorization');$allowed=true;
rejects(fn()=>$monitor::ingest(1011,array_replace($input,['fiks_id'=>4])),'Reject wrong FIKS identity');
rejects(fn()=>$monitor::ingest(1011,array_replace($input,['public_read'=>false])),'Unread source refused');
rejects(fn()=>$monitor::ingest(1011,array_replace($input,['url'=>'http://www.brann.no/news'])),'Insecure source refused');
rejects(fn()=>$monitor::ingest(1011,array_replace($input,['url'=>'https://127.0.0.1/news'])),'Private address refused');
rejects(fn()=>$monitor::ingest(1011,array_replace($input,['facts'=>['<script>alert(1)</script>']])),'HTML facts refused');
rejects(fn()=>$monitor::ingest(1011,array_replace($input,['checked_at'=>gmdate(DATE_ATOM,time()-200000)])),'Stale research refused');
rejects(fn()=>$monitor::ingest(1011,array_replace($input,['event_date'=>'2026-02-30'])),'Invalid event date refused');
$profiles[1011]['enabled']=false;rejects(fn()=>$monitor::ingest(1011,$input),'Paused player refused');$profiles[1011]['enabled']=true;
$n=$writes;$receipt=$monitor::ingest(1011,$input);check($receipt['created']&&$writes===$n,'Intake queues without a model call or publication');
$source=$receipt['source'];check($source['url']==='https://www.brann.no/nyheter/eksempel','Tracking and fragment removed');
$again=$monitor::ingest(1011,array_replace($input,['url'=>'https://www.brann.no/nyheter/eksempel/','facts'=>['Different text']]));
check(!$again['created']&&$again['source']['facts']===$input['facts'],'Canonical retry cannot overwrite evidence');
R::tick();check($writes===$n,'Disabled auto-proposals retain inbox without generation');
$options['rrfr_player_review_enabled_at']=time()-100;
$goal=['id'=>'7','name'=>'Test Spiller','minute'=>'46','type'=>'Spillemål'];$sourceUrl='https://www.fotball.no/fotballdata/kamp/?fiksId=123';$start=gmdate(DATE_ATOM,time()-7200);
$profiles[1011]['snapshot']['matches'][123]=['id'=>123,'source'=>$sourceUrl,'kickoff'=>$start,'events'=>[7=>$goal],'news_context'=>['id'=>123,'source'=>$sourceUrl,'kickoff'=>$start,'home'=>['id'=>1,'name'=>'Hjemme'],'away'=>['id'=>2,'name'=>'Borte'],'competition'=>['id'=>3,'name'=>'Testserie'],'score'=>[1,0],'finished'=>true,'checked_at'=>gmdate(DATE_ATOM)]];
$profiles[1011]['events']=[['kind'=>'goals','key'=>'123:7','id'=>'sample','fiks_id'=>3942773,'before'=>null,'after'=>$goal,'status'=>'new','detected_at'=>gmdate(DATE_ATOM,time()-60),'source'=>$sourceUrl]];
R::tick();check($writes===$n+1,'Only oldest NFF observation handled first');
check(count($monitor::candidates())===1,'News remains queued for next tick');
R::tick();check($writes===$n+2,'News enters same quality and approval flow');
$review=$monitor::review($monitor::key(1011,$source['id']));$newsId=$review['id'];
check($review['post_status']==='draft'&&$review['status']==='pending','News stays draft awaiting approval');
check(str_starts_with(get_post($newsId)->post_content,RadioRubben\Fotballrobot\EditorialNotice::BLOCK),'Player draft starts with canonical AI disclosure');
check(str_contains(get_post($newsId)->post_content,'<!-- wp:paragraph -->')&&str_contains(get_post($newsId)->post_content,'>brann.no</a>'),'Player draft has paragraph blocks and actual source domain');
R::tick();check($writes===$n+2,'Repeat tick never duplicates NFF or news drafts');
$GLOBALS['posts'][$newsId]->post_content='Human edit';$GLOBALS['posts'][$newsId]->post_status='trash';
$again=$monitor::ingest(1011,$input);R::tick();check(!$again['created']&&$writes===$n+2&&get_post($newsId)->post_content==='Human edit'&&get_post($newsId)->post_status==='trash','Retry preserves human edits and trash');
$write_fail=true;$monitor::ingest(1011,array_replace($input,['url'=>'https://www.brann.no/nyheter/second']));R::tick();$write_fail=false;R::tick();check($writes===$n+3,'Failed news generation is not retried automatically');
$status=$monitor::status();check($status['engine']==='radio-rubben-fotballrobot'&&count($status['players'])===1&&count($status['players'][0]['news'])===2,'Combined status retains source receipts and reviews');
$monitor::routes();foreach($routes as $route){foreach(isset($route['methods'])?[$route]:$route as $r)check($r['permission_callback']===[RadioRubben\Fotballrobot\Robot::class,'allowed'],'Every monitor route requires admin access');}
function wp_get_current_user(){return (object)['display_name'=>'Testredaktør'];}
$editId=R::create('editor-facts-test',$f,false,false);$s=R::state($editId);$hash=R::hash(get_post($editId));
$notes='Spilleren har trent regelmessig og fått nye oppgaver på laget.';
$allowed=false;rejects(fn()=>R::decide($editId,$s['version'],$hash,'revise','Ta med bakgrunnen.',$notes),'Unprivileged editor facts rejected');$allowed=true;
rejects(fn()=>R::decide($editId,$s['version']+1,$hash,'revise','Ta med bakgrunnen.',$notes),'Stale facts edit rejected');
rejects(fn()=>R::decide($editId,$s['version'],$hash,'approve','',$notes),'Facts cannot silently accompany approval');
rejects(fn()=>R::decide($editId,$s['version'],$hash,'revise','Ta med bakgrunnen.','<b>Ny opplysning</b>'),'HTML facts rejected');
rejects(fn()=>R::decide($editId,$s['version'],$hash,'revise','Ta med bakgrunnen.',str_repeat('x',2001)),'Oversize facts rejected');
check(!isset(R::state($editId)['facts']['editorial_facts']),'Rejected requests do not alter evidence');
$write_fail=true;$mailBefore=count($mail);R::decide($editId,$s['version'],$hash,'revise','',$notes);$write_fail=false;
$s=R::state($editId);$note=$s['facts']['editorial_facts'][0];
check($note['text']===$notes && $note['user']===7 && $note['source_name']==='Testredaktør','New facts carry exact text and authenticated provenance');
check(end($s['history'])['editorial_facts']===$note,'Editorial evidence recorded in audit history');
check($s['status']==='failed' && !RadioRubben\Fotballrobot\PublicationGate::current($editId,get_post($editId)),'Provider failure preserves facts and invalidates old approval');
check(get_post($editId)->post_status==='draft' && count($mail)===$mailBefore,'No automatic publication or notification');
R::decide($editId,$s['version'],R::hash(get_post($editId)),'retry','Bruk de registrerte opplysningene.');
check(R::state($editId)['facts']['editorial_facts'][0]===$note && count(R::state($editId)['facts']['editorial_facts'])===1,'Retry preserves evidence without duplication');
check(R::state($editId)['status']==='pending' && RadioRubben\Fotballrobot\PublicationGate::current($editId,get_post($editId)),'New evidence and text are quality checked together');
wp_update_post(['ID'=>$editId,'post_status'=>'publish']);check(get_post($editId)->post_status==='draft','Manual approval still required after new facts');
// End-to-end queue regression: ordinary substitutions/renames never reach Writer or mail.
$writesBefore=$writes;$mailBefore=count($mail);$postsBefore=count($posts);
$sub=$profiles[1011]['events'][0];$sub['after']['type']='Utbytte';$sub['key']='123:8';$sub['after']['id']='8';$sub['detected_at']=gmdate(DATE_ATOM);
$profiles[1011]['snapshot']['matches'][123]['events'][8]=$sub['after'];$profiles[1011]['events']=[$sub];
$renamed=$sub;$renamed['before']=$sub['after'];$renamed['before']['type']='Ut: Test Spiller';$profiles[1011]['events'][]=$renamed;
$beforeProfile=serialize($profiles);R::tick();R::tick();
check($writes===$writesBefore&&count($mail)===$mailBefore&&count($posts)===$postsBefore,'Routine and technical changes generate no posts, AI calls or email');
check(serialize($profiles)===$beforeProfile,'Filtered events are preserved, not marked consumed or deleted');
// A later discovery time for an already proposed match cannot duplicate the article.
$againEvent=$sub;$againEvent['key']='123:9';$againEvent['after']['id']='9';$againEvent['after']['type']='Spillemål';
$profiles[1011]['events']=[$againEvent];$profiles[1011]['snapshot']['matches'][123]['events'][9]=$againEvent['after'];R::tick();
check($writes===$writesBefore&&count($mail)===$mailBefore,'Player and match identity prevents second article across observation timestamps');
echo "$count approval checks passed; mocked existing Microsoft mail transport\n";
}
