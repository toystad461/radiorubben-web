<?php
declare(strict_types=1);
$root='/run/webroots/r1417157/studio-private/app';
$expected=json_decode('{"news-script.php":"a27233add01715b4be55dd04c998bf134b0d07c2ef42d891d65642f3623c7f5f","case-workflow.php":"f05697f3e45fd5657caaf387390b30fc17d1df11494866a6715a9ca01fcb008c","newsroom.php":"290e495e22fd7ac1b3b3f82d55e7e4dbd1361913f086e9d6814d51df02fec449","board.php":"aefe4af163ede3cb0e6abbb5d925a85b27e940c7e8501130576640258a0a3aea","source-identity.php":"8d9d8c8b8d4b5e865b76edb2a0f94a23284545650b26f7e149ab09a183118a85","web-publish.php":"f4a66a1fd1132c2e2b54287b5ecfdd07af0a6d1a3506385179a8d76b2860a6f1","news-publication.php":"37ab6f88138a53b95c4d0237284597436ff856d680d5a81b86b74784c3354ee7","newsroom-worker.php":"bf65c5511f857c708fd4fea9379ab0b29ba3f990535fd2a13528ac6d93475747"}',true,64,JSON_THROW_ON_ERROR);
if(realpath($root)!=='/customers/9/3/1/cptk37ymg/webroots/r1417157/studio-private/app'||is_link($root))throw new RuntimeException('Unexpected application directory');
foreach($expected as $name=>$hash)if(is_link($root.'/'.$name)||!hash_equals($hash,hash_file('sha256',$root.'/'.$name)))throw new RuntimeException('Application changed since reviewed release');
require_once $root.'/newsroom.php';
$stage=getenv('HOME').'/.radiorubben-deploy/rss-studio/cda6b684d66cb0313f2bd76d13a36802c3a17d8b';
$result=json_decode(file_get_contents($stage.'/live-test-result.json'),true,64,JSON_THROW_ON_ERROR);
foreach(['completed','radioGenerated','webGenerated','sameOriginalSnapshot','webApprovalAbsent','radioUnapproved','noDelivery','activeBoardUntouched'] as $key)if(($result[$key]??null)!==true)throw new RuntimeException('Real-source test did not establish pipeline invariant');
$items=studio_board_active(studio_board_read($stage.'/live-test-board.json'));if(count($items)!==1)throw new RuntimeException('Ambiguous isolated test');
$item=$items[0];$reviews=['radio'=>$item['sourceCheck'],'web'=>$item['web']['check']];$summary=[];
foreach($reviews as $kind=>$check){
 $text=$kind==='radio'?$item['script']:studio_web_text($item['web']);
 if(!in_array($check['status']??'',['passed','needs_review'],true)||!studio_news_original_read($item,$check)
  ||($check['policy']??'')!==STUDIO_NEWS_POLICY||!hash_equals(studio_news_fingerprint($item,$text),(string)($check['fingerprint']??''))||empty($check['segments']))
  throw new RuntimeException('Invalid or incomplete source review');
 $summary[$kind]=['status'=>$check['status'],'issues'=>$check['issues'],'unsupported'=>array_values(array_map(static fn($s)=>['reason'=>$s['reason']],array_filter($check['segments'],static fn($s)=>$s['verdict']!=='supported')))];
}
echo json_encode(['validatedRealSourceReviews'=>$summary],JSON_UNESCAPED_SLASHES)."\nRSS_PIPELINE_INVARIANTS_OK\n";
$argv[1]=$stage;
require $stage.'/scripts/rss-studio-backfill.php';
