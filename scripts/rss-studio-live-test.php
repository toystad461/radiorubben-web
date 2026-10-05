<?php
declare(strict_types=1);
// Isolated real-source test; never touches the active rundown or WordPress.
$stage=$argv[1]??'';if(!is_dir($stage))throw new RuntimeException('Missing private test stage');
$root='/run/webroots/r1417157/studio-private/app';
require_once $root.'/config.php';require_once $root.'/newsroom.php';
require_once $root.'/producer.php';require_once $root.'/integrations/NewsDesk.php';
$activePath=studio_board_path();$activeBefore=hash_file('sha256',$activePath);
$config=load_config();$spec=newsdesk_sources()['bomlo'];
$body=newsdesk_fetch_rss($spec['feed']);
if($body===null)throw new RuntimeException('Live feed unavailable');
$sources=newsdesk_parse_rss($body,'bomlo',$spec,gmdate('c'));
if(!$sources)throw new RuntimeException('No usable live source');
$matches=array_values(array_filter($sources,static fn($s)=>$s['url']==='https://www.bomlo.kommune.no/aktuelt-og-kunngjeringar/bli-med-i-frivilligheita.20979.aspx'));if(count($matches)!==1)throw new RuntimeException('Pinned real RSS test source missing');$source=$matches[0];$path=$stage.'/live-test-board.json';$user=['role'=>'producer','name'=>'Isolert RSS-test'];
studio_board_add_source($source,$user,$path);
$item=studio_case_source_item(studio_board_active(studio_board_read($path)),$source);
$ok=true;
try{studio_newsroom_prepare($item['id'],$item['revision'],$user,$config,'',$path);}catch(Throwable $e){$ok=false;}
$item=studio_case_get($item['id'],$path);
$both=!empty($item['web']['body'])&&trim((string)($item['script']??''))!=='';
$shared=$both&&($item['sourceCheck']['source']??null)===($item['web']['check']['source']??null);
$reviewComplete=!empty($item['sourceCheck']['segments'])&&!empty($item['web']['check']['segments'])&&in_array($item['sourceCheck']['status']??'',['passed','needs_review'],true)&&in_array($item['web']['check']['status']??'',['passed','needs_review'],true);
$result=['reviewsComplete'=>$reviewComplete,'sourceUrl'=>$source['url'],'sourceId'=>$source['id'],'itemId'=>$item['id'],'completed'=>$ok,
 'radioGenerated'=>trim((string)($item['script']??''))!=='','webGenerated'=>!empty($item['web']['body']),
 'sameOriginalSnapshot'=>$shared,'radioChecked'=>studio_news_check_current($item),'webChecked'=>studio_web_checked($item),
 'webApprovalAbsent'=>empty($item['web']['approvedHash']),'radioUnapproved'=>$item['status']==='draft'&&!$item['verified'],
 'noDelivery'=>!isset($item['web']['delivery']),'activeBoardUntouched'=>hash_equals($activeBefore,hash_file('sha256',$activePath))];
file_put_contents($stage.'/live-test-result.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo json_encode($result,JSON_UNESCAPED_SLASHES)."\n";
if(!$reviewComplete||!$both||!$shared||!$result['webApprovalAbsent']||!$result['radioUnapproved']||!$result['noDelivery'])exit(2);
echo "RSS_REAL_SOURCE_TEST_OK\n";
