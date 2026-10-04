<?php
// Run after selective installation. Calls the plugin's own validation and fetch path.
use RadioRubben\PlayerWidget\Service;
if (get_option('home') !== 'https://www.radiorubben.no' || !current_user_can('manage_options')) throw new RuntimeException('Wrong site or user.');
$profiles = Service::profiles();
$expected = [1011=>3942773,1012=>3909887,1013=>3584397,1014=>3646624,1037=>3909852];
$teams = [1011=>'35897',1012=>'20705',1013=>'2',1014=>'2',1037=>'210681'];
$input = ['revision'=>Service::settings()['revision'],'enabled'=>1,'players'=>[]];
foreach ($expected as $id=>$fiks) {
    if (($profiles[$id]['fiks_id'] ?? null) !== $fiks || !$profiles[$id]['enabled']) throw new RuntimeException('Player selection changed.');
    $input['players'][$id] = ['show'=>1,'teams'=>$teams[$id]];
}
Service::save($input);
for ($i=0;$i<4;$i++) Service::tick();
$cache = Service::cache();
foreach ([35897=>781,20705=>1633,2=>1509,210681=>711] as $id=>$club) {
    if (!empty($cache['teams'][$id]['error']) || ($cache['teams'][$id]['club_id']??0)!==$club) throw new RuntimeException('Team verification failed: '.$id);
}
$cards = Service::cards(3942773,6);
$today = array_values(array_filter($cards,static fn($c)=>$c['match']['id']===8989882));
if (!$today || ($today[0]['stream']['url']??'')!=='https://play.tv2.no/gpid/c849b4db-02ae-451f-a507-dd01135f8359') throw new RuntimeException('Today\'s stream was not confirmed.');
if (!wp_next_scheduled('rrpw_refresh')) throw new RuntimeException('Cron missing.');
update_option('rrpw_homepage_enabled',true,false);
echo wp_json_encode(['players'=>count($input['players']),'teams'=>count($cache['teams']),'cards'=>count(Service::cards(0,6)),'today'=>$today[0]['match'],'role'=>$today[0]['players'][3942773]['role'],'next_refresh'=>wp_next_scheduled('rrpw_refresh')], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
