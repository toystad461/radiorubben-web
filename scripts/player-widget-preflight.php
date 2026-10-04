<?php
// Read-only production checks. No player state or published content is modified.
require dirname(__DIR__).'/plugin/includes/sources.php';
use RadioRubben\PlayerWidget\Sources;
$out = ['php'=>PHP_VERSION,'dom'=>class_exists('DOMDocument'),'mbstring'=>function_exists('mb_strtolower'),'home'=>get_option('home'),'theme'=>get_stylesheet(),'timezone'=>wp_timezone_string()];
foreach ([1011,1012,1013,1014,1037] as $id) {
    $s = get_option('rrfr_player_'.$id, []);
    $out['players'][$id] = array_intersect_key($s,array_flip(['name','fiks_id','enabled']));
    $out['players'][$id]['clubs'] = $s['snapshot']['clubs'] ?? [];
}
foreach ([35897,2,20705,210681] as $id) {
    try {
        $html = Sources::fetch('team',$id);
        file_put_contents(dirname(__DIR__).'/team-'.$id.'.html',$html);
        $team = Sources::team($html,$id);
        $team['matches'] = array_filter($team['matches'],static fn($m)=>strtotime($m['kickoff'])>=time()-10800 && strtotime($m['kickoff'])<=time()+604800);
        $out['teams'][$id] = $team;
    } catch (Throwable $e) { $out['teams'][$id] = ['error'=>$e->getMessage()]; }
}
$m = $out['teams'][35897]['matches'][8989882] ?? null;
if ($m) foreach (['stream','match'] as $kind) {
    try {
        $html = Sources::fetch($kind,8989882);
        file_put_contents(dirname(__DIR__).'/'.$kind.'-8989882.html',$html);
        $out[$kind] = $kind==='stream' ? Sources::stream($html,$m) : Sources::lineup($html,$m,[3942773]);
    } catch (Throwable $e) { $out[$kind] = ['error'=>$e->getMessage()]; }
}
foreach (['front-page.php','page.php'] as $f) {
    $file = get_theme_root().'/'.get_stylesheet().'/'.$f;
    $out['theme_files'][$f] = ['sha256'=>hash_file('sha256',$file),'content'=>file_get_contents($file)];
}
$out['cron_disabled'] = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
$out['plugin_already_installed'] = file_exists(WP_PLUGIN_DIR.'/radio-rubben-player-widget/radio-rubben-player-widget.php');
echo wp_json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
