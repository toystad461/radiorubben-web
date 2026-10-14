<?php
require __DIR__.'/../includes/facts.php';
require __DIR__.'/../includes/lineups.php';
require __DIR__.'/../includes/club-coverage.php';
require __DIR__.'/../includes/shared-rules.php';
use RadioRubben\Fotballrobot\ClubCoverage as C;
use RadioRubben\Fotballrobot\Facts;
use RadioRubben\Fotballrobot\SharedRules;
function check($v,$label){if(!$v)throw new RuntimeException($label);echo "OK $label\n";}
function rejects($fn,$label){try{$fn();}catch(RuntimeException $e){check(true,$label);return;}throw new RuntimeException($label);}
$html=file_get_contents(__DIR__.'/fixtures/match.html');
$m=Facts::match($html,8985491);
$m+=['finished_confirmed'=>true,'cancelled'=>false,'postponed'=>false,'interrupted'=>false,'walkover'=>false];
$m['home']['registered_name']='Bremnes G19-1';
$facts=C::normalizeDetails($m,$html,'2026-10-14T12:00:00Z');
check($facts['match']['half_time']===[0,2] && count($facts['match']['events'])>0,'details and half time retained');
check($facts['detail_check']['events_complete'],'goals reconcile with score');
check($facts['match']['home']['registered_name']==='Bremnes G19-1','youth identity retained');
check(C::sameFeed($facts['match'],array_replace($m,['events'=>[]])),'simple feed does not erase detailed events');
rejects(fn()=>C::normalizeDetails(array_replace($m,['score'=>[9,0]]),$html,'now'),'score conflict blocked');
rejects(fn()=>C::normalizeDetails(array_replace($m,['finished_confirmed'=>false]),$html,'now'),'unconfirmed end blocked');
rejects(fn()=>C::normalizeDetails(array_replace($m,['cancelled'=>true]),$html,'now'),'cancelled match blocked');
$r=['id'=>'rule-1','revision'=>2,'text'=>'Bevar alle forbehold i kilden.','scope'=>'shared','status'=>'approved'];
check(SharedRules::fromBoard(['editorialRules'=>[$r]])[0]['version']===2,'shared rule identity and version retained');
foreach(['pending','disabled','rejected'] as $status)check(SharedRules::fromBoard(['editorialRules'=>[array_replace($r,['status'=>$status])]])===[],'unapproved rule excluded '.$status);
check(SharedRules::fromBoard(['editorialRules'=>[array_replace($r,['scope'=>'program'])]])===[],'program-specific rules not copied to football');
rejects(fn()=>SharedRules::fromBoard(['editorialRules'=>[array_replace($r,['text'=>'<b>Ikke en gyldig regel</b>'])]]),'invalid shared rule rejected');

echo "Club detail and shared rule tests passed\n";
