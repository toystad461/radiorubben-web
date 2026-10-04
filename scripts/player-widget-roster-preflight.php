<?php
// Populate only missing approved profiles through the owning plugin's refresh method.
use RadioRubben\Fotballrobot\Players;
if (!class_exists(Players::class) || !method_exists(Players::class,'refresh')) throw new RuntimeException('Player source plugin unavailable');
$approved=get_option('rrfr_player_candidates',[]);
$refreshed=[];
foreach ($approved as $candidate) {
    if (($candidate['status']??'')!=='approved') continue;
    $id=(int)($candidate['player_id']??0);if (!$id) continue;
    $state=Players::state($id);
    if (empty($state['enabled']) || (int)$state['fiks_id']!==(int)$candidate['fiks_id'] || !empty($state['snapshot'])) continue;
    $next=Players::refresh($id);
    if (empty($next['snapshot']['clubs']) || empty($next['snapshot']['stats'])) throw new RuntimeException('Missing verified profile data for '.$id);
    $refreshed[]=$id;
}
echo wp_json_encode(['approved_profiles_refreshed'=>$refreshed])."\n";
