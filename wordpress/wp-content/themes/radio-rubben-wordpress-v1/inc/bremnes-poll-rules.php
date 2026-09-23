<?php
if (!defined('ABSPATH')) exit;
function rr_poll_elapsed($state,$now=null) {
    $now=$now??time();
    return max(0,(int)$state['elapsed']+(!empty($state['running'])?max(0,$now-(int)$state['started']):0));
}
function rr_poll_is_closed($state,$now=null) {
    return !empty($state['closed']) || ((int)$state['period']===2 && rr_poll_elapsed($state,$now)>=4500);
}
function rr_poll_lineup_ready($match) {
    return !empty($match['starters']) && count($match['starters'])<=11;
}
function rr_poll_allowed_players($match,$state) {
    if (empty($state['opened']) || !rr_poll_lineup_ready($match)) return [];
    $allowed=[];

    // Voting eligibility is cumulative for the whole match:
    // starters are eligible from kickoff, substitutes are added when they enter,
    // and no player is removed again because they leave the pitch.
    foreach (($state['eligible_players']??[]) as $no=>$stored_name) {
        $no=(int)$no;
        $name=is_string($stored_name) && trim($stored_name)!=='' ? trim($stored_name) : ($match['roster'][$no]??'');
        if ($name!=='') $allowed[$no]=$name;
    }
    foreach ($match['starters'] as $no) {
        $no=(int)$no;
        if(isset($match['roster'][$no])) $allowed[$no]=$match['roster'][$no];
    }
    foreach (($state['entered']??[]) as $no=>$minute) {
        $no=(int)$no;
        if (isset($match['roster'][$no])) $allowed[$no]=$match['roster'][$no];
    }

    ksort($allowed);
    return $allowed;
}
