<?php
namespace RadioRubben\Fotballrobot;
// Executed by the host scheduler through wp eval-file. No HTTP visit is required.
use RadioRubben\Fotballrobot\MatchFollowup;
use RadioRubben\Fotballrobot\MatchJobs;
if(!defined('ABSPATH')||!class_exists(MatchFollowup::class))throw new \RuntimeException('Fotballrobot 0.10.6 må være installert før scheduler aktiveres.');
if(empty(get_option(MatchJobs::CONFIG,[])['enabled']))throw new \RuntimeException('Automatisk referat er deaktivert.');
MatchFollowup::tick();
$allowed=['rrfr_match_job','rrfr_club_tick','rrfr_club_match','rrfr_club_weekly'];$count=0;$deadline=time()+360;
foreach(_get_cron_array()?:[] as $at=>$hooks) {
    if($at>time())continue;
    foreach($hooks as $hook=>$events) {
        if(!in_array($hook,$allowed,true))continue;
        foreach($events as $event) {
            if($count>=12||time()>=$deadline)break 3; // Leave time for the current model call to finish before the host timeout.
            $args=$event['args'];
            if(!empty($event['schedule']))wp_reschedule_event($at,$event['schedule'],$hook,$args);
            if(wp_unschedule_event($at,$hook,$args,true)!==true)throw new \RuntimeException('Cron-steget kunne ikke reserveres.');
            do_action_ref_array($hook,$args);$count++;
        }
    }
}
update_option('rrfr_match_followup_runner',['at'=>time(),'transport'=>'host-cli','events'=>$count],false);
$error=get_option('rrfr_match_followup_error',[]);
if($error)throw new \RuntimeException('Kildefeil i kampoppfølgingen. Se godkjenningsflaten.');
echo 'OK: Fotballrobot scheduler; events='.$count."\n";
