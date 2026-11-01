<?php
// Isolated real adapter -> coverage -> follow-up -> existing CLI runner. No network or WordPress writes.
namespace RadioRubben\Fotballrobot;
const DAY_IN_SECONDS=86400;
define('ABSPATH',__DIR__);
function time(){return $GLOBALS['now'];}
function get_option($k,$default=false){return $GLOBALS['options'][$k]??$default;}
function update_option($k,$v,...$rest){$GLOBALS['options'][$k]=$v;return true;}
function add_option($k,$v,...$rest){if(isset($GLOBALS['options'][$k]))return false;return update_option($k,$v);}
function delete_option($k){unset($GLOBALS['options'][$k]);}
function get_current_user_id(){return $GLOBALS['user'];}
function wp_set_current_user($id){$GLOBALS['user']=$id;}
function wp_cache_delete(...$args){}
function _get_cron_array(){return [];}
function wp_safe_remote_get($url,$args){
    $GLOBALS['requests']++;
    $r=array_shift($GLOBALS['responses']);
    if($r===null)throw new \RuntimeException('Unexpected request: private-url');
    if($r instanceof \Throwable)throw $r;
    return $r;
}
final class TransportError {function get_error_message(){return 'cURL error 28: private-url timed out';}}
function is_wp_error($r){return $r instanceof TransportError;}
function wp_remote_retrieve_response_code($r){return $r['code'];}
function wp_remote_retrieve_body($r){return $r['body'];}
function wp_remote_retrieve_header($r,$key){return $r[$key]??'';}
final class Robot {static function allowed(){return get_current_user_id()===7;}}
final class MatchJobs {
    const CONFIG='rrfr_match_jobs';
    static function state($id){return [];}
    static function recover($id){}
    static function sourceConfirmed($id,$m){$GLOBALS['confirmed'][$id]=$m;}
    static function waitForSource($id,$reason){}
}
final class DB {
    public $options='wp_options';public $args;
    function prepare($sql,...$args){$this->args=$args;return $sql;}
    function esc_like($s){return $s;}
    function get_col($sql){return [];}
    function query($sql){[$key,$stamp]=$this->args;if((string)get_option($key)!==$stamp)return 0;delete_option($key);return 1;}
}
$wpdb=new DB;
$root=dirname(__DIR__,2).'/wordpress/wp-content/plugins/radio-rubben-fotballrobot/includes/';
require $root.'fotballdata.php';require $root.'club-coverage.php';require $root.'match-followup.php';
function check($ok,$why){if(!$ok)throw new \RuntimeException($why);}
function response($data,$code=200){return ['code'=>$code,'body'=>json_encode($data)];}
function resetCase(){
    $GLOBALS['now']=1793520000;$GLOBALS['user']=0;$GLOBALS['requests']=0;$GLOBALS['confirmed']=[];
    $GLOBALS['options']=['rrfr_match_jobs'=>['enabled'=>true,'owner'=>7,'since'=>1793000000],
        'rr_fd_cid'=>'310','rr_fd_cwd'=>'00000000-0000-0000-0000-000000000000'];
    $GLOBALS['responses']=[];
}
function teams(){return response(['ClubId'=>827,'Teams'=>[['ClubId'=>827,'TeamId'=>30365,'TeamName'=>'Bremnes Menn Senior A']]]);}
function run(){ob_start();try{require dirname(__DIR__).'/fotballrobot-cron.php';return ob_get_contents();}finally{ob_end_clean();}}
function expectFailure($category,$status=0){
    $before=['verified'=>'keep'];update_option('rrfr_match_followup_matches',$before);
    try{run();throw new \LogicException('Expected runner failure');}catch(\RuntimeException $e){check(str_contains($e->getMessage(),'Kildefeil'),'Runner exposes failure');}
    $error=get_option('rrfr_match_followup_error');
    check($error['category']===$category&&$error['http_status']===$status,'Error classification');
    check($error['attempts']===1&&get_option('rrfr_match_followup_matches')===$before,'Preserve verified state');
    check(!str_contains(json_encode($error),'private-url')&&!str_contains(json_encode($error),'00000000'),'No secrets');
    $requests=$GLOBALS['requests'];
    try{run();}catch(\RuntimeException $e){}
    check($requests===$GLOBALS['requests']&&get_option('rrfr_match_followup_error')['attempts']===1,'Backoff has no request or new attempt');
    check($GLOBALS['user']===0&&!get_option('rrfr_match_followup_lock'),'Identity and lock restored');
}
$cases=[];
$cases['empty matches and recovery clear failure without work']=function(){
    update_option('rrfr_match_followup_error',['attempts'=>33,'retry_at'=>time()-1]);
    $GLOBALS['responses']=[teams(),response(['ClubId'=>827,'Matches'=>[]])];
    check(str_contains(run(),'source_status=no_work'),'Empty matches succeed');
    check(!get_option('rrfr_match_followup_error')&&!$GLOBALS['confirmed'],'No failure or draft work');
    check(get_option('rrfr_match_followup_last')['eligible']===0,'Empty receipt');
};
$cases['empty eligible team set skips match request']=function(){
    foreach([[],[['ClubId'=>827,'TeamId'=>44,'TeamName'=>'Bremnes G12']]] as $rows){
        resetCase();$GLOBALS['responses']=[response(['ClubId'=>827,'Teams'=>$rows])];
        check(str_contains(run(),'source_status=no_work')&&$GLOBALS['requests']===1,'Valid team rules avoid match request');
    }
};
$cases['data then unchanged then out-of-window']=function(){
    $raw=['MatchId'=>8985501,'HomeTeamId'=>30365,'HomeTeamName'=>'Bremnes','HomeTeamClubId'=>827,'AwayTeamId'=>99,'AwayTeamName'=>'Other','AwayTeamClubId'=>814,'HomeTeamGoals'=>0,'AwayTeamGoals'=>0,'TournamentId'=>77,'TournamentName'=>'5. divisjon','StadiumName'=>'Test','MatchStartDate'=>'/Date(1793400000000)/','Cancelled'=>false,'Postponed'=>false,'Interrupted'=>false,'WalkOverHome'=>false,'WalkOverAway'=>false,'WalkOverBoth'=>false,'FinalResultApprovedByDistrict'=>true,'FinalResultApprovedByReferee'=>false];
    $GLOBALS['responses']=[teams(),response(['ClubId'=>827,'Matches'=>[$raw]])];
    check(str_contains(run(),'source_status=success')&&count($GLOBALS['confirmed'])===1,'Valid confirmed 0-0 processed');
    $GLOBALS['now']+=300;$GLOBALS['responses']=[teams(),response(['ClubId'=>827,'Matches'=>[$raw]])];
    check(str_contains(run(),'source_status=no_work')&&get_option('rrfr_match_followup_last')['reason']==='unchanged','Unchanged source succeeds');
    $raw['MatchStartDate']='/Date(1794400000000)/';$GLOBALS['responses']=[teams(),response(['ClubId'=>827,'Matches'=>[$raw]])];
    check(str_contains(run(),'source_status=no_work')&&get_option('rrfr_match_followup_last')['eligible']===0,'Future match needs no followup');
};
foreach([401=>'authentication',403=>'access',429=>'rate_limit',500=>'service',502=>'service',503=>'service',404=>'http',204=>'http'] as $code=>$category){
    $cases['HTTP '.$code]=function()use($code,$category){$GLOBALS['responses']=[response([], $code)];expectFailure($category,$code);};
}
$cases['Retry-After honored']=function(){
    $GLOBALS['responses']=[['code'=>429,'body'=>'private-url','retry-after'=>'7200']];expectFailure('rate_limit',429);
    check(get_option('rrfr_match_followup_error')['retry_at']===time()+7200,'Server retry delay');
};
$cases['timeout']=function(){$GLOBALS['responses']=[new TransportError];expectFailure('timeout');};
$cases['transport exception redacted']=function(){$GLOBALS['responses']=[new \RuntimeException('private-url')];expectFailure('network');};
foreach([''=>'response_body','<html>private-url'=>'invalid_json','[]'=>'schema','{}'=>'schema','{"ClubId":827,"Teams":null}'=>'schema','{"ClubId":827,"Teams":{}}'=>'schema','{"ClubId":999,"Teams":[]}'=>'schema'] as $body=>$category){
    $cases['invalid payload '.strlen($body).' '.$body]=function()use($body,$category){$GLOBALS['responses']=[['code'=>200,'body'=>$body]];expectFailure($category);};
}
$cases['valid teams cannot hide match endpoint failure']=function(){
    $GLOBALS['responses']=[teams(),response([],503)];expectFailure('service',503);
};
$failed=0;
foreach($cases as $name=>$test){resetCase();try{$test();echo 'PASS: '.$name."\n";}catch(\Throwable $e){$failed++;echo 'FAIL: '.$name.' '.$e->getMessage()."\n";}}
echo count($cases).' cases; failures='.$failed."\n";exit($failed?1:0);
