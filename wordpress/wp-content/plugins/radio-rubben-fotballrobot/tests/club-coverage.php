<?php
namespace RadioRubben\Fotballrobot;
require __DIR__.'/../includes/fotballdata.php';
require __DIR__.'/../includes/club-coverage.php';
require __DIR__.'/../includes/club-automation.php';
$count=0;
function check($v,$label){global $count;if(!$v)throw new \RuntimeException($label);$count++;}
function rejects($fn,$label){try{$fn();}catch(\RuntimeException $e){check(true,$label);return;}throw new \RuntimeException($label);}
function team($id,$name){return ['TeamId'=>$id,'TeamName'=>'Bremnes '.$name,'ClubId'=>827];}
$teams=ClubCoverage::teams(['ClubId'=>827,'Teams'=>[team(1,'G12 1'),team(2,'G13-1'),team(3,'J13-1'),team(4,'J12 1'),team(5,'J17-1'),team(6,'J17-1'),team(7,'Menn Senior A'),team(8,'Kvinner 1'),team(9,'Ukjent')]]);
check(array_keys($teams)===[2,3,5,6,7,8],'G/J boundary, seniors and duplicate names by ID');
rejects(fn()=>ClubCoverage::teams(['ClubId'=>781,'Teams'=>[team(2,'G13')]]),'wrong club');
rejects(fn()=>ClubCoverage::teams(['ClubId'=>827,'Teams'=>[team(2,'G13'),team(2,'J13')]]),'conflicting identities');
rejects(fn()=>ClubCoverage::teams(['ClubId'=>827,'Teams'=>[]]),'empty feed is not no teams');
$raw=['MatchId'=>100,'HomeTeamId'=>2,'HomeTeamName'=>'Bremnes','HomeTeamClubId'=>827,'AwayTeamId'=>20,'AwayTeamName'=>'Motstander','AwayTeamClubId'=>88,'HomeTeamGoals'=>2,'AwayTeamGoals'=>1,'TournamentId'=>77,'TournamentName'=>'G13 2. divisjon','StadiumName'=>'Testbanen','MatchStartDate'=>'/Date(1790618400000-0000)/','Cancelled'=>false,'Postponed'=>false,'Interrupted'=>false,'WalkOverHome'=>false,'WalkOverAway'=>false,'WalkOverBoth'=>false,'FinalResultApprovedByDistrict'=>true,'FinalResultApprovedByReferee'=>false,'HomeTeamContactPersonEmail'=>'private@example.invalid'];
$now=strtotime('2026-09-29T07:00:00+02:00');
$parse=fn($r)=>ClubCoverage::matches(['ClubId'=>827,'Matches'=>$r],$teams,$now);
$m=$parse([$raw])[100];
check($m['finished_confirmed']&&$m['score']===[2,1],'explicit final result');
check(!str_contains(json_encode($m),'private@example.invalid'),'no contact fields');
check($m['home']['registered_name']==='Bremnes G13-1','age class label retained');
check(count($parse([$raw,$raw]))===1,'duplicate matches deduplicated');
rejects(fn()=>$parse([$raw,['AwayTeamGoals'=>9]+$raw]),'conflicting duplicate');
rejects(fn()=>$parse([['HomeTeamClubId'=>781]+$raw]),'club identity mismatch');
$missing=$raw;unset($missing['Cancelled']);rejects(fn()=>$parse([$missing]),'unknown status is not scheduled');
foreach(['Cancelled','Postponed','Interrupted','WalkOverHome','WalkOverAway','WalkOverBoth'] as $flag){$r=$parse([[$flag=>true]+$raw])[100];check(!$r['finished_confirmed']&&$r['score']===null,$flag.' blocks report');}
check(!$parse([['FinalResultApprovedByDistrict'=>false]+$raw])[100]['finished_confirmed'],'no guessed final status');
check($parse([['FinalResultApprovedByDistrict'=>false,'FinalResultApprovedByReferee'=>true]+$raw])[100]['finished_confirmed'],'referee approval');
check(!$parse([['MatchStartDate'=>'/Date(4102444800000-0000)/']+$raw])[100]['finished_confirmed'],'future never finished');
check($parse([['HomeTeamId'=>1]+$raw])===[],'G12 excluded');
check(count($parse([['HomeTeamId'=>20,'HomeTeamClubId'=>88,'AwayTeamId'=>3,'AwayTeamClubId'=>827]+$raw]))===1,'away J13 included');
foreach(['2026-10-24T19:00:00+02:00'=>'2026-10-25T18:00:00+01:00','2027-03-27T19:00:00+01:00'=>'2027-03-28T18:00:00+02:00','2026-10-04T17:59:59+02:00'=>'2026-10-04T18:00:00+02:00','2026-10-04T18:00:00+02:00'=>'2026-10-11T18:00:00+02:00'] as $from=>$to)check(ClubCoverage::nextSunday(strtotime($from))===strtotime($to),'local Sunday including DST '.$from);
$week=ClubCoverage::weekForSunday(strtotime('2026-10-04T18:00:00+02:00'));
check($week['key']==='2026-10-05'&&$week['end']==='2026-10-12T00:00:00+02:00','next Monday to exclusive next Monday');
$next=['kickoff'=>$week['start'],'finished_confirmed'=>false,'score'=>null]+$m;
$last=['id'=>101,'kickoff'=>'2026-10-11T23:59:59+02:00']+$next;
check(count(ClubCoverage::weekMatches([$next,$last,['kickoff'=>$week['end']]+$next,['postponed'=>true]+$next],$week))===2,'week boundaries and postponed filter');
// Isolated WordPress doubles: no network, paid AI or publication.
$options=[];$posts=[];$meta=[];$queue=[];$seq=1;$builds=0;$user=0;
function get_current_user_id(){return $GLOBALS['user'];}
function wp_set_current_user($id){$GLOBALS['user']=$id;}
function user_can($id,$cap){return $id===7;}
function get_option($k,$default=false){global $options;return $options[$k]??$default;}
function update_option($k,$v,...$unused){global $options;$options[$k]=$v;return true;}
function add_option($k,$v,...$unused){global $options;if(array_key_exists($k,$options))return false;$options[$k]=$v;return true;}
function delete_option($k){global $options;unset($options[$k]);}
function wp_next_scheduled($hook,$args=[]){global $queue;return $queue[$hook.json_encode($args)]??false;}
function wp_schedule_event($time,$schedule,$hook){return wp_schedule_single_event($time,$hook);}
function wp_schedule_single_event($time,$hook,$args=[]){global $queue;$queue[$hook.json_encode($args)]=$time;return true;}
function wp_clear_scheduled_hook($hook,$args=[]){global $queue;unset($queue[$hook.json_encode($args)]);}
function get_posts($q){global $posts,$meta;$out=[];foreach($posts as $id=>$p){if(!array_key_exists($q['meta_key'],$meta[$id]??[]))continue;if(isset($q['meta_value'])&&(string)$meta[$id][$q['meta_key']]!==(string)$q['meta_value'])continue;if(!in_array($p->post_status,$q['post_status'],true))continue;$out[]=($q['fields']??'')==='ids'?$id:$p;}return array_slice($out,0,$q['numberposts']);}
function wp_insert_post($data,$error=false){global $seq,$posts,$meta;$id=$seq++;$posts[$id]=(object)(['ID'=>$id,'post_content'=>'']+$data);$meta[$id]=$data['meta_input']??[];return $id;}
function wp_update_post($data,$error=false){global $posts,$meta;$id=$data['ID'];foreach($data as $k=>$v){if($k==='meta_input')$meta[$id]=array_merge($meta[$id],$v);else $posts[$id]->$k=$v;}return $id;}
function get_post($id){global $posts;return clone $posts[$id];}
function update_post_meta($id,$k,$v){global $meta;$meta[$id][$k]=$v;}
function get_post_meta($id,$k,$single=true){global $meta;return $meta[$id][$k]??'';}
function is_wp_error($v){return false;}
function get_term_by($field,$value,$taxonomy){return (object)['term_id'=>['sport'=>16,'fotball'=>17,'bremnes-il'=>60][$value]];}
function wp_json_encode($v){return json_encode($v);}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES);}
function esc_url($v){return esc_html($v);}
function wp_date($fmt,$time,$tz=null){return (new \DateTimeImmutable('@'.$time))->setTimezone($tz??new \DateTimeZone('Europe/Oslo'))->format($fmt);}
$builder=function()use(&$builds){$builds++;return ['post_title'=>'Faktabasert artikkel','post_content'=>'<p>Kontrollert</p>'];};
$id=ClubAutomation::create('match:100',['match'=>$m],$builder);
check($posts[$id]->post_status==='draft'&&$meta[$id]['_rrfr_club_status']==='review','draft only and review state');
check($posts[$id]->post_category===[16,17,60],'sports category hierarchy');
check(ClubAutomation::create('match:100',[],$builder)===$id&&$builds===1,'rerun does not rewrite or pay twice');
$posts[$id]->post_status='publish';check(ClubAutomation::create('match:100',[],$builder)===$id&&$builds===1,'published articles preserved');
$posts[$id]->post_status='trash';check(ClubAutomation::existing('match:100')===$id,'trash prevents duplicate');
rejects(fn()=>ClubAutomation::create('match:101',[],function(){throw new \RuntimeException('Synthetic AI failure');}),'failed generation');
$failed=ClubAutomation::existing('match:101');check($meta[$failed]['_rrfr_club_status']==='failed','failure visible and reserved');
ClubAutomation::create('match:101',[],$builder);check($builds===1,'failure never causes paid auto retry');
rejects(fn()=>ClubAutomation::create('match:102',[],function(){global $posts;$id=ClubAutomation::existing('match:102');$posts[$id]->post_content='Human edit';return ['post_content'=>'Overwrite','post_title'=>'No'];}),'concurrent human edit');
check($posts[ClubAutomation::existing('match:102')]->post_content==='Human edit','human edit preserved');
$article=ClubAutomation::weeklyArticle([$next,$last],$week,'2026-10-04T16:00:00Z');
check(str_contains($article['post_content'],'Bremnes G13-1')&&str_contains($article['post_content'],'Testbanen'),'weekly exact names and venue');
check(!str_contains($article['post_content'],'2–1'),'weekly does not invent results');
check(str_contains(ClubAutomation::weeklyArticle([],$week,'2026-10-04T16:00:00Z')['post_excerpt'],'ingen registrerte'),'successful empty week');
ClubAutomation::register();check(!$queue,'inactive schedules nothing');
update_option('rrfr_club_enabled_at',time());ClubAutomation::register();$q=$queue;ClubAutomation::register();check(count($q)===2&&$queue===$q,'schedule registration idempotent');
check(ClubAutomation::schedules([])['rrfr_halfhour']['interval']===1800,'half-hour interval');
ClubAutomation::stop();check(!$queue,'deactivation cleanup');

$clock=strtotime('2026-09-29T12:00:00+02:00');
function time(){global $clock;return $clock??\time();}
define('RRFR_FOTBALLDATA_CID','1');
define('RRFR_FOTBALLDATA_CWD','00000000-0000-0000-0000-000000000001');
$apiMode='ok';$httpCalls=0;
$apiTeams=['ClubId'=>827,'Teams'=>[team(2,'G13-1'),team(3,'J13-1'),team(7,'Menn Senior A')]];
$raw['MatchId']=500;$raw['MatchStartDate']='/Date('.(($clock-3600)*1000).'-0000)/';
$apiMatches=['ClubId'=>827,'Matches'=>[$raw,['MatchId'=>501,'HomeTeamId'=>7]+$raw]];
function wp_safe_remote_get($url,$args){global $apiMode,$httpCalls,$apiTeams,$apiMatches;$httpCalls++;if($apiMode==='failure')throw new \RuntimeException('transport secret must not escape');return ['body'=>json_encode(str_contains($url,'/teams?')?$apiTeams:$apiMatches),'code'=>200];}
function wp_remote_retrieve_response_code($r){return $r['code'];}
function wp_remote_retrieve_body($r){return $r['body'];}
class Writer {
    const DEFAULT_FEATURED_MEDIA=812;
    public static function qualityReview($a,$f){return ['article'=>$a,'publishable'=>true];}
    public static function body($a,$f){return '<p>'.esc_html($a['lead']).'</p><p>'.esc_html(implode(' ',$a['paragraphs'])).'</p>';}
    public static function clubArticle($f){$a=['title'=>'Kontrollert resultat','lead'=>'Bremnes vant 2–1.','paragraphs'=>['Kampen ble spilt på Testbanen.'],'checks'=>[['claim'=>'2–1','support'=>'match.score']]];$a['_quality']=self::qualityReview($a,$f);return $a;}
}
class PublicationGate {public static function hash($p){$p=(array)$p;return hash('sha256',json_encode(array_map(static fn($k)=>$p[$k]??'', ['post_title','post_content','post_excerpt'])));}const META='_rrfr_quality_review';public static function bind($q,$p){return $q+['postHash'=>hash('sha256',json_encode($p))];}}

$options=['rrfr_club_owner'=>7];$queue=[];
update_option('rrfr_club_enabled_at',$clock-7200);
ClubAutomation::tick();check(count(get_option('rrfr_club_seen'))===1,'youth queued, senior owned by existing automation');
check(!wp_next_scheduled('rrfr_club_match',[500]),'no draft immediately after observing final');
$clock+=1800;ClubAutomation::tick();check(!wp_next_scheduled('rrfr_club_match',[500]),'no draft after only half an hour');
$clock+=1800;ClubAutomation::tick();check(wp_next_scheduled('rrfr_club_match',[500])!==false,'one hour observation wait');
ClubAutomation::match(500);$pid=ClubAutomation::existing('match:500');check($pid>0&&$meta[$pid]['_rrfr_club_status']==='review','end to end cron draft');
check(get_current_user_id()===0&&$posts[$pid]->post_author===7,'cron owner restored and author recorded');
check($meta[$pid]['_rrfr_editor_decision']['status']==='pending'&&$meta[$pid]['_thumbnail_id']===812&&isset($meta[$pid][PublicationGate::META]['postHash']),'current inbox state, image and bound quality');
$bodyBefore=$posts[$pid]->post_content;$apiMatches['Matches'][0]['AwayTeamGoals']=0;ClubAutomation::tick();
check($posts[$pid]->post_content===$bodyBefore&&$meta[$pid]['_rrfr_fact_snapshot']['match']['score']===[2,0],'new raw facts invalidate review without overwriting text');
$oldCount=count($posts);ClubAutomation::match(500);check(count($posts)===$oldCount,'repeated job is idempotent');
$apiMatches['Matches']=[['MatchId'=>502]+$raw];ClubAutomation::tick();$clock+=1800;$apiMatches['Matches'][0]['AwayTeamGoals']=0;ClubAutomation::tick();check(!wp_next_scheduled('rrfr_club_match',[502]),'result change restarts wait');
$before=get_option('rrfr_club_seen');$apiMode='failure';ClubAutomation::tick();check(get_option('rrfr_club_seen')===$before,'API failure preserves prior state');
check(!str_contains(json_encode(get_option('rrfr_club_error_matches')),'secret'),'transport details redacted');
$clock=strtotime('2026-10-04T18:00:00+02:00');$options=['rrfr_club_owner'=>7];$queue=[];
update_option('rrfr_club_enabled_at',$clock-86400);update_option('rrfr_club_weekly_due',$clock);
ClubAutomation::weekly();check(!ClubAutomation::existing('week:2026-10-05'),'API error is not empty schedule');
check(wp_next_scheduled('rrfr_club_weekly')===$clock+1800,'weekly source failure retries in 30 minutes');
check(get_option('rrfr_club_weekly_due')===$clock,'retry preserves intended week');
$apiMode='ok';$apiMatches['Matches']=[];unset($queue['rrfr_club_weekly[]']);$clock+=1800;ClubAutomation::weekly();
$weeklyId=ClubAutomation::existing('week:2026-10-05');check($weeklyId>0&&$posts[$weeklyId]->post_status==='draft','successful empty week creates draft');
check(get_option('rrfr_club_weekly_due')===strtotime('2026-10-11T18:00:00+02:00'),'next local Sunday scheduled');
$before=$httpCalls;update_option('rrfr_club_enabled_at',0);ClubAutomation::tick();ClubAutomation::weekly();ClubAutomation::match(500);check($httpCalls===$before,'disabled jobs perform no network');
check(get_current_user_id()===0,'weekly restores cron user');
$options['rrfr_club_enabled_at']=$clock;$options['rrfr_club_owner']=99;$before=$httpCalls;ClubAutomation::tick();check($httpCalls===$before&&get_current_user_id()===0,'revoked owner cannot run jobs');
echo 'OK: '.$count." club coverage checks\n";
