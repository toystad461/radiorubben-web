<?php
require __DIR__.'/bootstrap.php';
use RadioRubben\PlayerWidget\Service as App;
use RadioRubben\PlayerWidget\View;
$checks=0;
function ok($value,$message) { global $checks; $checks++; if (!$value) throw new RuntimeException($message); }
function order(): array { preg_match_all('/data-player-id="(\d+)"/',View::shortcode(),$m);return array_map('intval',$m[1]); }
$now=time();$posts=[1,2,3,4,5,6];
foreach($posts as $id) $options['rrfr_player_'.$id]=['name'=>'Player '.$id,'fiks_id'=>100+$id,'enabled'=>true,'group'=>'','snapshot'=>['clubs'=>[781=>['name'=>'Brann']],'stats'=>[['year'=>(int)wp_date('Y'),'team_id'=>$id,'team'=>'Team '.$id]]]];
$options[App::SETTINGS]=['revision'=>1,'enabled'=>true,'players'=>[1=>['fiks_id'=>101,'teams'=>[10]]]];
$options['rrfr_player_candidates']=[102=>['status'=>'approved','fiks_id'=>102,'player_id'=>2],103=>['status'=>'approved','fiks_id'=>103,'player_id'=>3],104=>['status'=>'pending','fiks_id'=>104,'player_id'=>4],105=>['status'=>'rejected','fiks_id'=>105,'player_id'=>5],106=>['status'=>'approved','fiks_id'=>106,'player_id'=>999]];
$options['rrfr_player_4']['group']='bomlo-away';
$selected=App::selected(App::settings(),App::profiles());
ok(array_keys($selected)===[1,2,3],'Only explicit and approved exact profile identities');
ok($selected[1]['selected_teams']===[10,1],'All registered teams join explicit extras');
$options[App::CACHE]=['teams'=>[],'matches'=>[]];
foreach([1=>10800,2=>3600,10=>7200] as $tid=>$seconds) {
 $mid=1000+$tid;$m=['id'=>$mid,'kickoff'=>gmdate(DATE_ATOM,$now+$seconds),'home'=>['id'=>$tid,'name'=>'Team '.$tid],'away'=>['id'=>999,'name'=>'Opposition'],'venue'=>'Ground','competition'=>'League'];
 $options[App::CACHE]['teams'][$tid]=['id'=>$tid,'club_id'=>781,'name'=>'Team '.$tid,'matches'=>[$mid=>$m],'checked_at'=>$now,'error'=>null];
}
ok(order()===[2,1,3],'Nearest match at left, no fixture at right');
ok(str_contains(View::shortcode(),'Team 10'),'Nearest of multiple registered teams chosen');
ok(!preg_match('/Tropp uavklart|Ingen kommende kamp|Ingen kamp de neste|Kampdata uavklart/',View::shortcode()),'No unwanted unknown or empty notices');
$options[App::CACHE]['teams'][2]['matches'][1002]['kickoff']=gmdate(DATE_ATOM,$now+20*86400);
ok(order()===[1,2,3],'Rescheduled match reorders, match beyond seven days retained');
$options[App::CACHE]['teams'][10]['club_id']=711;
ok(!str_contains(View::shortcode(),'Team 10'),'Previous club excluded even if configured');
$options[App::CACHE]['teams'][1]['matches'][1001]['kickoff']=$options[App::CACHE]['teams'][2]['matches'][1002]['kickoff'];
ok(order()===[1,2,3],'Stable tie order');
$options['rrfr_player_2']['enabled']=false;ok(order()===[1,3],'Paused approved player removed');
$options['rrfr_player_2']['enabled']=true;$posts=[1,3,4,5,6];ok(order()===[1,3],'Deleted approved player removed');
$posts[] = 2;$options['rrfr_player_2']['snapshot']=null;
ok(order()===[1,2,3],'Approved player without snapshot is retained quietly');
$options['rrfr_player_3']['group']='bomlo-away';$options['rrfr_player_candidates'][103]['status']='later';ok(order()===[1,2],'Deferred candidate is not approval even with group label');
$options['rrfr_player_candidates'][103]['status']='approved';$options['rrfr_player_3']['fiks_id']=777;ok(order()===[1,2],'Approval cannot move to another person through group membership');
$options['rrfr_player_3']['group']='';ok(order()===[1,2],'Candidate approval cannot be reused for another person');
$options[App::SETTINGS]['enabled']=false;ok(View::shortcode()==='','Global off remains respected');
echo "$checks roster and sorting checks passed\n";
