<?php
namespace RadioRubben\Fotballrobot;
require __DIR__.'/../includes/fotballdata.php';
$count=0;
function check($ok,$label) { global $count; if(!$ok) throw new \RuntimeException($label); $count++; }
function rejects(callable $fn,$label) { try {$fn();} catch(\RuntimeException $e) {check(true,$label); return;} throw new \RuntimeException($label); }
$raw=['MatchId'=>101,'HomeTeamId'=>30365,'HomeTeamName'=>'Bremnes','AwayTeamId'=>19046,'AwayTeamName'=>'Viggo',
'HomeTeamGoals'=>2,'AwayTeamGoals'=>2,'MatchStartDate'=>'/Date(1700000000000-0000)/',
'TournamentId'=>205982,'StadiumName'=>'ScaleAQ Stadion','FinalResultApprovedByDistrict'=>true];
$r=Fotballdata::matchRow($raw);
check($r['score']===[2,2],'score');
check($r['home']['id']===30365,'identity');
check(strtotime($r['kickoff'])===1700000000,'UTC timestamp is not shifted twice');
check(!isset($r['Persons']),'allowlist');
foreach(['Cancelled','Postponed','Interrupted'] as $flag) check(Fotballdata::matchRow([$flag=>true]+$raw)['score']===null,$flag);
check(Fotballdata::matchRow(['MatchStartDate'=>'/Date(4102444800000+0100)/']+$raw)['score']===null,'future zero is unknown');
check(Fotballdata::historyRows(['TeamId'=>30365,'Matches'=>[$raw]],30365)[0]['score']===[2,2],'approved history');
check(Fotballdata::historyRows(['TeamId'=>30365,'Matches'=>[['FinalResultApprovedByDistrict'=>false]+$raw]],30365)[0]['score']===null,'unapproved history');
rejects(fn()=>Fotballdata::historyRows(['TeamId'=>48835,'Matches'=>[$raw]],30365),'wrong team');
rejects(fn()=>Fotballdata::historyRows(['TeamId'=>123,'Matches'=>[$raw]],123),'unrelated match');
rejects(fn()=>Fotballdata::historyRows(['TeamId'=>30365,'Matches'=>[$raw,['HomeTeamGoals'=>3]+$raw]],30365),'conflicting duplicate');
rejects(fn()=>Fotballdata::date('yesterday'),'invalid date');
rejects(fn()=>Fotballdata::matchRow(['MatchId'=>'101']+$raw),'typed identity');
rejects(fn()=>Fotballdata::matchRow(['HomeTeamGoals'=>-1]+$raw),'negative score');
rejects(fn()=>Fotballdata::matchRow(['Cancelled'=>'false']+$raw),'unknown status');
$m=$r+['competition'=>['id'=>205982]];
Fotballdata::assertMatch($raw,$m); check(true,'matching independent sources');
rejects(fn()=>Fotballdata::assertMatch(['AwayTeamGoals'=>1]+$raw,$m),'source conflict');
check(!Fotballdata::enabled(),'opt in only');
// Mock HTTP: never make external requests or include a real credential.
define('RRFR_FOTBALLDATA_CID','1');
define('RRFR_FOTBALLDATA_CWD','00000000-0000-0000-0000-000000000001');
$transport='success'; $calls=0;
function wp_safe_remote_get($url,$args) {
    global $transport,$calls,$raw;
    $calls++;
    check(str_starts_with($url,'https://api.fotballdata.no/v1/'),'fixed HTTPS host');
    check($args['redirection']===0,'no redirects');
    if($transport==='throw') throw new \RuntimeException($url);
    if($transport==='denied') return ['status'=>403,'body'=>'credential details'];
    if($transport==='invalid') return ['status'=>200,'body'=>'{invalid'];
    return ['status'=>200,'body'=>json_encode(['TeamId'=>30365,'Matches'=>[$raw],'Persons'=>[['Email'=>'private@example.invalid']]])];
}
function is_wp_error($r) {return false;}
function wp_remote_retrieve_response_code($r) {return $r['status'];}
function wp_remote_retrieve_body($r) {return $r['body'];}
$history=Fotballdata::history(30365);
check(count($history['rows'])===1,'HTTP normalized');
check(!str_contains(json_encode($history),'private@example.invalid'),'contact omitted');
check(!str_contains(json_encode($history),'cwd'),'credentials omitted');
foreach(['throw','denied','invalid'] as $mode) {
    $transport=$mode;
    try {Fotballdata::history(30365); throw new \LogicException('Expected failure');}
    catch(\RuntimeException $e) {check(!str_contains($e->getMessage(),RRFR_FOTBALLDATA_CWD),'safe errors');}
}
$before=$calls; rejects(fn()=>Fotballdata::history(-1),'invalid outbound id'); check($calls===$before,'no invalid HTTP');
echo 'OK: '.$count." Fotballdata checks\n";
