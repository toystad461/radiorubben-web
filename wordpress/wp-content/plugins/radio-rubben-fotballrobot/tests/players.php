<?php
require __DIR__.'/../includes/facts.php';
require __DIR__.'/../includes/player-facts.php';
require __DIR__.'/../includes/players.php';
use RadioRubben\Fotballrobot\PlayerFacts as F;
use RadioRubben\Fotballrobot\Players as P;
$count=0;
function check($ok,$why){global $count;$count++;if(!$ok)throw new RuntimeException($why);}
function rejects($fn,$why){try{$fn();}catch(Throwable $e){check(true,$why);return;}check(false,$why);}
check(F::id('3942773')===3942773,'Numeric ID');
check(F::id('https://www.fotball.no/fotballdata/person/profil/?fiksId=3942773')===3942773,'Profile URL');
foreach(['0','-1','12345678901','https://evil.test/?fiksId=1','https://www.fotball.no.evil.test/fotballdata/person/profil/?fiksId=1','https://www.fotball.no/fotballdata/kamp/?fiksId=1','https://www.fotball.no/fotballdata/person/profil/?fiksId[]=1','https://foo@www.fotball.no/fotballdata/person/profil/?fiksId=1'] as $bad) rejects(fn()=>F::id($bad),'Invalid input');
$html=file_get_contents(__DIR__.'/fixtures/player-profile.html');
$p=F::profile($html,3942773);$p['matches']=[];
check($p['clubs'][781]['name']==='Sportsklubben Brann','Explicit player club');
check($p['stats']['2026:108426']['goals']===1,'Season stats');
rejects(fn()=>F::profile($html,1),'Identity mismatch');
rejects(fn()=>F::profile('<html>Service unavailable</html>',3942773),'Failure is not empty stats');
$tor=file_get_contents(__DIR__.'/fixtures/torbjorn-profile.html');$t=F::profile($tor,2039307);
check(array_keys($t['clubs'])===[1730],'Player club excludes youth coaching roles');
check($t['stats']['2026:171']['appearances']===25,'Hødd team 171 verified from profile');
check($t['stats']['2008:48947']['appearances']===0 && $t['stats']['2008:48947']['yellow']===1,'Card-only season retains zero appearances');
rejects(fn()=>F::profile(str_replace('data-fiksid="2039307"','data-fiksid="999"',$tor),2039307),'Card-only links cannot bypass person identity');
$conflict=preg_replace('/data-team-id="171"/','data-team-id="999"',$tor,1);
rejects(fn()=>F::profile($conflict,2039307),'Conflicting team identities in same row rejected');
$games=F::matches(file_get_contents(__DIR__.'/fixtures/player-games.html'));
check(count($games)===3,'Match ID deduplication');check(str_contains($games[9225195]['label'],'Viking'),'Match label not overwritten by score');
$match=file_get_contents(__DIR__.'/fixtures/player-match.html');
$m=F::participation($match,8655049,3942773);
check($m['role']==='starter','Starting lineup');
check(count($m['events'])===4,'Only player-linked goals');
check(F::participation($match,8655049,123)===null,'No inferred selection');
check(F::diff(null,$p)===[],'Baseline quiet');check(F::diff($p,$p)===[],'Unchanged quiet');
$n=$p;$n['stats']['2026:108426']['goals']=2;
check(array_column(F::diff($p,$n),'kind')===['statistics','goals'],'Goal delta');
$n=$p;$n['stats']['2026:108426']['yellow']=1;$n['stats']['2026:108426']['red']=1;
check(count(array_filter(F::diff($p,$n),fn($e)=>$e['kind']==='cards'))===2,'Both cards');
$n=$p;$n['clubs']=null;check(F::diff($p,$n)===[],'Missing club not transfer');
$n=$p;$n['clubs']=[999=>['id'=>999,'name'=>'Ny klubb']];check(F::diff($p,$n)[0]['kind']==='club','Transfer');
$n=$p;$n['matches'][8655049]=$m;$delta=F::diff($p,$n);
check(count($delta)===6,'Match lineup four goals');check(F::diff($n,$n)===[],'No duplicate events');
$old=$n;$n['matches'][8655049]['role']='bench';check(F::diff($old,$n)[0]['kind']==='lineup','Lineup update');
$old=$n;$n['stats']['2026:108426']['goals']=0;check(F::diff($old,$n)[1]['after']===0,'Correction stored as change not invented goal');
// Minimal WordPress persistence harness exercises real application methods.
$options=[];$posts=[];$next=1;$transients=[];$fail=false;
const MINUTE_IN_SECONDS=60;const DAY_IN_SECONDS=86400;
function absint($v){return abs((int)$v);}function sanitize_text_field($v){return trim(strip_tags($v));}function sanitize_textarea_field($v){return trim(strip_tags($v));}
function get_option($k,$d=false){global $options;return $options[$k]??$d;}
function add_option($k,$v,...$unused){global $options;if(array_key_exists($k,$options))return false;$options[$k]=$v;return true;}
function update_option($k,$v,...$unused){global $options;$options[$k]=$v;return true;}
function delete_option($k){global $options;unset($options[$k]);}
function get_post_type($id){global $posts;return $posts[$id]->post_type??false;}
function wp_insert_post($v,...$unused){global $posts,$next;$v['ID']=$next;$posts[$next]=(object)$v;return $next++;}
function get_posts($q){global $posts;$result=[];foreach($posts as $p)if($p->post_type===$q['post_type']&&(!isset($q['meta_key'])||($p->meta_input[$q['meta_key']]??null)===$q['meta_value']))$result[]=($q['fields']??'')==='ids'?$p->ID:$p;return $result;}
function is_wp_error($v){return false;}function get_transient($k){global $transients;return $transients[$k]??false;}function set_transient($k,$v,...$unused){global $transients;$transients[$k]=$v;}
function wp_date($f){return '2026';}function wp_remote_retrieve_response_code($r){return $r['status'];}function wp_remote_retrieve_body($r){return $r['body'];}
function wp_safe_remote_request($url,$args){global $html,$fail;if($fail)return ['status'=>503,'body'=>''];if(str_contains($url,'person/profil'))return ['status'=>200,'body'=>$html];if(str_contains($url,'GetMatches'))return ['status'=>200,'body'=>file_get_contents(__DIR__.'/fixtures/player-games.html')];return ['status'=>503,'body'=>''];}
function esc_html($v){return htmlspecialchars((string)$v);}function esc_url($v){return htmlspecialchars($v);}function wp_json_encode($v,...$args){return json_encode($v,...$args);}function get_edit_post_link($id,...$args){return 'https://example.test/edit/'.$id;}
$input=['name'=>'Testspiller','fiks'=>'3942773','watch'=>array_keys(P::KINDS),'enabled'=>1,'group'=>'bomlo-away','note'=>'Bømlo'];
$id=P::save($input);check(P::state($id)['group']==='bomlo-away','Group persisted');
rejects(fn()=>P::save($input),'Unique player');
P::refresh($id);check(P::state($id)['events']===[],'Refresh baseline');
P::refresh($id);check(P::state($id)['events']===[],'Repeat quiet');
$html=str_replace('Sportsklubben Brann','Klubb endret',$html);$transients=[];
P::refresh($id);$state=P::state($id);check(count($state['events'])===1,'Persist club event');
$key=array_key_first($state['events']);P::eventAction($id,$key,'ignore');check(P::state($id)['events'][$key]['status']==='ignored','Ignore retained');
P::refresh($id);check(count(P::state($id)['events'])===1,'Ignore does not recreate');
$url=P::eventAction($id,$key,'draft');check($url===P::eventAction($id,$key,'draft'),'Idempotent draft');
$draft=get_posts(['post_type'=>'post'])[0];check($draft->post_status==='draft','Never published');
$before=P::state($id)['snapshot'];$fail=true;$transients=[];rejects(fn()=>P::refresh($id),'HTTP failure');check(P::state($id)['snapshot']===$before,'Snapshot preserved');check(P::state($id)['error']!==null,'Failure visible');
$options['rrfr_player_lock_'.$id]=time();rejects(fn()=>P::eventAction($id,$key,'draft'),'Concurrent write locked');unset($options['rrfr_player_lock_'.$id]);
rejects(fn()=>P::state(999),'Wrong post type');rejects(fn()=>P::eventAction($id,'missing','draft'),'Unknown event');
$input['id']=$id;$input['fiks']='2';rejects(fn()=>P::save($input),'Immutable identity');
$fail=false;$transients=[];
$before=P::state($id);$clubHtml=$html;
$html=str_replace('class="a_roleCard"','class="unavailable-role"',$html);
P::refresh($id);check(count(P::state($id)['events'])===1,'Missing club does not create event');
$html=str_replace('Klubb endret','Neste klubb',$clubHtml);$transients=[];
P::refresh($id);check(count(P::state($id)['events'])===2,'Club comparison survives unknown interval');
$input['fiks']='3942773';$input['watch']=['goals'];P::save($input);
$html=str_replace('Neste klubb','Tredje klubb',$html);$transients=[];P::refresh($id);
check(count(P::state($id)['events'])===2,'Event preference respected');
require __DIR__.'/../includes/robot.php';
$permissions=[];function current_user_can($cap){global $permissions;return in_array($cap,$permissions,true);}
check(!RadioRubben\Fotballrobot\Robot::allowed(),'Anonymous denied');
$permissions=['edit_posts'];check(!RadioRubben\Fotballrobot\Robot::allowed(),'Editor denied');
$permissions=['manage_options','edit_posts'];check(RadioRubben\Fotballrobot\Robot::allowed(),'Administrator allowed');
$completed=file_get_contents(__DIR__.'/../../radio-rubben-player-widget/tests/fixtures/completed-match.html');
$detail=F::participation($completed,8989882,3942773);
check($detail['events'][7417931]['type']==='Innbytte','Actual Tiril event direction is normalized');
$outgoing=F::participation($completed,8989882,3436482);
check($outgoing['events'][7417931]['type']==='Utbytte','Same actual event has correct outgoing identity');
$options['rrfr_player_'.$id]['watch']=array_keys(P::KINDS);
P::observeMatch(8989882,$completed,[3942773],false);
$news=P::state($id)['snapshot']['matches'][8989882]['news_context']??[];
check($news===[],'Reduced widget fixture without competition cannot become article context');
$observed=array_values(array_filter(P::state($id)['events'],fn($e)=>($e['match_id']??0)===8989882));
check(count($observed)>0 && array_values(array_unique(array_column($observed,'status')))===['observing'],'Live facts do not queue one article each minute');
// Widget fixture omits the tournament navigation. Add a synthetic link for this parser check.
$completed.='<a href="/fotballdata/turnering/hjem/?fiksId=123">Testserie</a>';
$before=P::state($id)['events'];P::observeMatch(8989882,$completed,[3942773],false);
$news=P::state($id)['snapshot']['matches'][8989882]['news_context']??[];
check(($news['finished']??null)===false && ($news['score']??null)===[0,3] && ($news['competition']['id']??null)===123,'Same response supplies match context without inferring the final whistle');
check(P::state($id)['events']===$before,'Repeated match observation is idempotent');
P::observeMatch(8989882,$completed,[3942773],true);
$news=P::state($id)['snapshot']['matches'][8989882]['news_context']??[];
check(($news['finished']??null)===true && $news['home']['id']!==$news['away']['id'] && !empty($news['checked_at']),'Confirmed final whistle supplies complete article context');
$observed=array_values(array_filter(P::state($id)['events'],fn($e)=>($e['match_id']??0)===8989882));
check(array_values(array_unique(array_column($observed,'status')))===['new'] && count(array_unique(array_column($observed,'detected_at')))===1,'Final whistle queues one completed packet');
check(get_option('rrfr_profile_due_'.$id)>0,'Final whistle prioritizes profile statistics');
echo "$count player checks passed\n";
