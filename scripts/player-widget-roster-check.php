<?php
use RadioRubben\PlayerWidget\Service;
use RadioRubben\PlayerWidget\View;
if (RadioRubben\PlayerWidget\VERSION!=='1.2.0') throw new RuntimeException('Wrong version');
$before=hash('sha256',serialize(Service::settings()));
$roster=Service::selected(Service::settings(),Service::profiles());
$expected=[1011,1012,1013,1014,1037,1081,1082,1083,1084,1085,1086,1087];
if (array_diff($expected,array_keys($roster))) throw new RuntimeException('Missing approved players');
$teamIds=[];foreach ($roster as $p)foreach ($p['selected_teams'] as $id)$teamIds[$id]=true;
$deadline=microtime(true)+150;
for ($i=0;$i<count($teamIds)+8 && microtime(true)<$deadline;$i++) {
    Service::tick();
    $cache=Service::cache();
    $pending=array_diff_key($teamIds,$cache['teams']);
    $missingMedia=false;
    foreach($roster as $p){$c=Service::cards($p['fiks_id'],1)[0]??null;if($c && !isset($cache['matches'][$c['match']['id']]))$missingMedia=true;}
    if (!$pending && !$missingMedia) break;
}
$html=View::shortcode();
preg_match_all('/data-player-id="(\d+)"/',$html,$ids);
if (array_diff($expected,array_map('intval',$ids[1]))) throw new RuntimeException('Player missing from compact output');
preg_match_all('/data-kickoff="(\d+)"/',$html,$times);$order=array_map(static fn($v)=>(int)$v?:PHP_INT_MAX,$times[1]);$sorted=$order;sort($sorted);
if ($sorted!==$order) throw new RuntimeException('Fixtures not sorted');
if (preg_match('/Tropp uavklart|Ingen kommende kamp|Ingen kamp de neste|Kampdata uavklart/',$html)) throw new RuntimeException('Removed notices returned');
if ($before!==hash('sha256',serialize(Service::settings()))) throw new RuntimeException('Manual selections changed');
$coverage=[];$cache=Service::cache();
foreach ($roster as $id=>$p) {
 $teams=[];foreach($p['selected_teams'] as $tid){$t=$cache['teams'][$tid]??null;if($t && empty($t['error']) && in_array($t['club_id'],$p['clubs'],true))$teams[$tid]=$t['name'];}
 $c=Service::cards($p['fiks_id'],1)[0]??null;
 $coverage[]=['player'=>$p['name'],'teams'=>$teams,'next_match'=>$c['match']??null];
}
echo wp_json_encode(['version'=>RadioRubben\PlayerWidget\VERSION,'players'=>count($ids[1]),'next_refresh'=>wp_next_scheduled('rrpw_refresh'),'coverage'=>$coverage],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
