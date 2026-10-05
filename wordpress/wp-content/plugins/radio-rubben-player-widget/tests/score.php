<?php
require __DIR__.'/bootstrap.php';
use RadioRubben\PlayerWidget\Live;
use RadioRubben\PlayerWidget\Service;
$count=0;
function check($ok,$why){global $count;$count++;if(!$ok)throw new RuntimeException($why);}
$m=['id'=>9071080,'kickoff'=>'2026-10-05T20:00:00+02:00','home'=>['id'=>53929,'name'=>'Åkra/Kopervik/Vedavåg'],'away'=>['id'=>210681,'name'=>'Haugesund 2']];
// Minimal contract observed on NFF match 9071080, 5 October 2026. No inferred total.
$html='<html><head><title>Åkra/Kopervik/Vedavåg - Haugesund 2 - 05.10.2026 20:00</title><meta property="og:url" content="https://www.fotball.no/fotballdata/kamp/?fiksId=9071080"></head><body><div class="a_matchCard"><div class="result"><div class="halfTime">(0 - 3)</div></div><div class="teamName"><a href="/fotballdata/lag/hjem/?fiksId=53929">Home</a></div><div class="teamName"><a href="/fotballdata/lag/hjem/?fiksId=210681">Away</a></div></div><div data-tab="kamphendelser"><div class="timelineEventLine"><div class="timelineMinute">35</div><div class="timelineEventContent">Spillemål</div></div></div></body></html>';
$start=strtotime($m['kickoff']);
check(Live::status($html,$m,[],$start+3600)['score']===['home'=>0,'away'=>3,'kind'=>'halftime'],'Halftime is explicitly labelled, not current score');
$current=str_replace('<div class="halfTime">','<div class="endResult">1 - 4</div><div class="halfTime">',$html);
check(Live::status($current,$m,[],$start+3600)['score']===['home'=>1,'away'=>4,'kind'=>'current'],'Current score wins over halftime; home first');
check(Live::status(str_replace('1 - 4','0 - 0',$current),$m,[],$start+3600)['score']['away']===0,'Zero is valid when explicit');
check(Live::status(str_replace('1 - 4','1 - ?',$current),$m,[],$start+3600)['score']===null,'Malformed current score is not replaced with halftime');
check(Live::status(str_replace('fiksId=9071080','fiksId=9071081',$html),$m,[],$start+3600)['score']===null,'Wrong match ID fails closed');
$empty=str_replace('<div class="halfTime">(0 - 3)</div>','',$html);
check(Live::status($empty.'<div class="a_matchCard"><div class="result"><div class="endResult">9 - 8</div></div></div>',$m,[],$start+3600)['score']===null,'Historical match result ignored');
check(Live::status($current,$m,[],$start-60)['score']===null,'Future kickoff never shows a score');
check(Live::status(str_replace('Spillemål','Kampen er slutt',$current),$m,[],$start+6000)['score']['kind']==='final','Final requires explicit final whistle');
check(Live::status(str_replace('Spillemål','Kampen er avlyst',$current),$m,[],$start+6000)['score']===null,'Cancelled match suppresses score');
try{Live::status($html,array_replace($m,['home'=>['id'=>1,'name'=>'Other']]),[],$start+3600);check(false,'Wrong home ID accepted');}catch(RuntimeException $e){check(true,'Wrong home ID rejected');}
$now=time();$m['kickoff']=gmdate(DATE_ATOM,$now-3600);
$posts=[1037];$options['rrfr_player_1037']=['name'=>'Anna','fiks_id'=>3909852,'enabled'=>true,'group'=>'bomlo-away','note'=>'PRIVATE','snapshot'=>['clubs'=>[711=>['name'=>'Haugesund']],'stats'=>[['year'=>(int)wp_date('Y'),'team_id'=>210681,'team'=>'Haugesund 2']]]];
$options[Service::SETTINGS]=['revision'=>1,'enabled'=>true,'players'=>[]];
$options[Service::CACHE]=['teams'=>[210681=>['club_id'=>711,'checked_at'=>$now,'matches'=>[9071080=>$m]]],'matches'=>[9071080=>['match'=>$m,'phase'=>'live','score'=>['home'=>0,'away'=>3,'kind'=>'halftime'],'lineup_checked_at'=>$now,'errors'=>['PRIVATE']]]];
$r=Live::publicMatches();check(count($r)===1 && $r[0]['score']['away']===3,'Public selected fixture includes score');
check(!str_contains(json_encode($r),'PRIVATE'),'No notes or internal errors leak');
$options[Service::CACHE]['matches'][9071080]['lineup_checked_at']=$now-151;
check(Live::publicMatches()[0]['score']===null,'Stale score hidden');
$options[Service::CACHE]['matches'][9071080]['lineup_checked_at']=0;
check(Live::publicMatches()[0]['score']===null,'Fetch failure hides score');
$options[Service::CACHE]['matches'][9071080]['match']['kickoff']=gmdate(DATE_ATOM,$now);
check(Live::publicMatches()===[],'Rescheduling invalidates old score');
$options[Service::SETTINGS]['enabled']=false;check(Live::publicMatches()===[],'Disabled widget does not expose fixtures');
echo "$count score checks passed\n";
