<?php
declare(strict_types=1);
$root='/run/webroots/r1417157/studio-private/app';
$paths=['news-script.php','case-workflow.php','newsroom.php','board.php','source-identity.php','web-publish.php','news-publication.php','newsroom-worker.php'];
$out=['files'=>[]];
foreach($paths as $path){
    if(!is_file($root.'/'.$path)||is_link($root.'/'.$path))throw new RuntimeException('Missing or linked dependency.');
    $out['files'][$path]=hash_file('sha256',$root.'/'.$path);
}
require_once $root.'/newsroom.php';
$board=studio_board_read();
$out['automatic_enabled']=studio_newsroom_settings($board)['enabled'];
$out['active_items']=count(studio_board_active($board));
$out['rss_productions']=['both'=>0,'web_only'=>0,'radio_only'=>0,'empty'=>0];
foreach(studio_board_active($board) as $item)if(studio_web_is_news($item)){
    $web=!empty($item['web']['body']);$radio=trim((string)($item['script']??''))!=='';
    $key=$web?($radio?'both':'web_only'):($radio?'radio_only':'empty');
    $out['rss_productions'][$key]++;
}
echo json_encode($out,JSON_UNESCAPED_SLASHES)."\nRSS_STUDIO_PREFLIGHT_OK\n";
