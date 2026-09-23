<?php
if (!defined('ABSPATH')) exit;
function rr_speaker_team($match,$side='home') {
    return $side==='away' ? ['roster'=>$match['away_roster']??[],'starters'=>$match['away_starters']??[],'bench'=>$match['away_bench']??[]] : $match;
}
function rr_speaker_on_pitch($match,$state,$side='home') {
    $team=rr_speaker_team($match,$side);
    $active=($side==='away' && empty($team['starters']) && !empty($team['roster']))
        ? array_fill_keys(array_keys($team['roster']),true)
        : array_fill_keys($team['starters']??[],true);
    foreach (($state[$side==='away'?'entered_away':'entered']??[]) as $no=>$time) $active[(int)$no]=true;
    foreach (($state['events']??[]) as $event) {
        if (($event['side']??'home')!==$side) continue;
        if ($event['type']==='sub') unset($active[$event['out']]);
        if ($event['type']==='red' || !empty($event['dismissed'])) unset($active[$event['player']]);
    }
    return array_intersect_key($team['roster'],$active);
}
function rr_speaker_event($match,$state,$type,$player,$out=0,$side='home') {
    if (!in_array($side,['home','away'],true)) return new WP_Error('team','Velg et lag.');
    if (empty($state['opened']) || !empty($state['finished'])) return new WP_Error('status','Start kampen først. Hendelser kan ikke registreres etter kampslutt.');
    if (!in_array($type,['goal','yellow','red','sub'],true)) return new WP_Error('type','Ukjent hendelse.');
    $team=rr_speaker_team($match,$side);
    $lineup_ready=!empty($team['starters']) && count($team['starters'])<=11;
    if (!$lineup_ready && !($side==='away' && $type!=='sub' && !empty($team['roster']))) return new WP_Error('lineup','Startoppstillingen for laget mangler eller er ugyldig.');
    $key=$side==='away'?'entered_away':'entered';
    $active=rr_speaker_on_pitch($match,$state,$side);
    $event=['type'=>$type,'side'=>$side,'player'=>$player,'seconds'=>rr_poll_elapsed($state),'period'=>$state['period']];
    if ($type==='sub') {
        if (!isset($active[$out]) || !in_array($player,$team['bench']??[],true) || isset($state[$key][$player])) return new WP_Error('player','Velg en spiller på banen og en ubrukt innbytter.');
        $event['out']=$out;
        $state[$key][$player]=$event['seconds'];
        if ($side==='home') {
            if (!isset($state['eligible_players']) || !is_array($state['eligible_players'])) $state['eligible_players']=[];
            $state['eligible_players'][(int)$player]=(string)($team['roster'][(int)$player]??'');
        }
    } else {
        if (empty($state['running'])) return new WP_Error('status','Start omgangen før du registrerer mål eller kort.');
        if (!isset($active[$player])) return new WP_Error('player','Velg en spiller som er på banen.');
        if ($type==='yellow') {
            $yellow=0;
            foreach (($state['events']??[]) as $previous) if (($previous['side']??'home')===$side && $previous['type']==='yellow' && $previous['player']===$player) $yellow++;
            if ($yellow>=1) $event['dismissed']=true;
        }
    }
    $state['events'][]=$event;
    return $state;
}
function rr_speaker_score($state) {
    $score=['home'=>0,'away'=>0];
    foreach (($state['events']??[]) as $event) {
        if ($event['type']==='goal') $score[($event['side']??'home')==='away'?'away':'home']++;
    }
    return $score;
}
