<?php
declare(strict_types=1);
$root='/run/webroots/r1417157/studio-private/app';
$paths=['news-script.php','case-workflow.php','newsroom.php','board.php','source-identity.php','web-publish.php','news-publication.php','newsroom-worker.php'];
$out=['files'=>[],'appRealpath'=>realpath($root),'appIsLink'=>is_link($root),'php'=>PHP_VERSION];
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
$testPath=getenv('HOME').'/.radiorubben-deploy/rss-studio/fb89950ddd7b68152c95fa52345cf799288026c1/live-test-board.json';
if(is_file($testPath)){
 $test=studio_board_read($testPath);$item=studio_board_active($test)[0];
 $check=$item['sourceCheck']??[];
 $out['isolatedRadioReview']=['jobState'=>$item['newsroom']['state']??'missing','jobError'=>$item['newsroom']['error']??null,'status'=>$check['status']??'missing','issues'=>$check['issues']??[],
  'fingerprintMatches'=>hash_equals((string)($check['fingerprint']??''),studio_news_fingerprint($item,(string)$item['script'])),
  'unsupported'=>array_values(array_map(static fn($s)=>['verdict'=>$s['verdict'],'reason'=>$s['reason']],array_filter($check['segments']??[],static fn($s)=>$s['verdict']!=='supported')))];
}
echo json_encode($out,JSON_UNESCAPED_SLASHES)."\nRSS_STUDIO_PREFLIGHT_OK\n";
