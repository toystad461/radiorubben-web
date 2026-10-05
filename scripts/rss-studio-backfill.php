<?php
declare(strict_types=1);
// Bounded completion of unapproved legacy cases; no mail, approval or publication.
$stage=$argv[1]??'';if(!is_dir($stage))throw new RuntimeException('Missing private stage');
$root='/run/webroots/r1417157/studio-private/app';
require_once $root.'/config.php';require_once $root.'/newsroom.php';require_once $root.'/producer.php';
$path=studio_board_path();$lock=fopen($path.'.newsroom.lock','c');
if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Newsroom busy; no backfill performed');
try{
 $config=load_config();$candidates=[];
 foreach(studio_board_active(studio_board_read($path)) as $item)
  if(studio_web_is_news($item)&&!empty($item['web']['body'])&&trim((string)($item['script']??''))===''&&empty($item['web']['approvedHash'])&&empty($item['web']['delivery'])&&($item['newsroom']['state']??'')!=='working')$candidates[]=$item;
 if(count($candidates)>2)throw new RuntimeException('Backfill exceeded reviewed two-case bound');
 if(file_exists($stage.'/active-board-before-backfill.json'))throw new RuntimeException('Backfill already attempted');
 if(!copy($path,$stage.'/active-board-before-backfill.json'))throw new RuntimeException('Board backup failed');
 chmod($stage.'/active-board-before-backfill.json',0600);
 $results=[];$user=['role'=>'producer','name'=>'Robåt – kontrollert RSS-utfylling'];
 foreach($candidates as $before){
  $web=studio_web_text($before['web']);$ok=true;
  try{studio_newsroom_prepare($before['id'],$before['revision'],$user,$config,'',$path,null,null,true);}catch(Throwable $e){$ok=false;}
  $after=studio_case_get($before['id'],$path);
  $row=['id'=>$before['id'],'completed'=>$ok,'sameSourceIdentity'=>$before['originId']===$after['originId']&&$before['sourceUrl']===$after['sourceUrl'],
   'webTextPreserved'=>$web===studio_web_text($after['web']),'radioGenerated'=>trim((string)($after['script']??''))!=='',
   'sameOriginalSnapshot'=>($after['sourceCheck']['source']??null)===($after['web']['check']['source']??null),
   'radioChecked'=>studio_news_check_current($after),'webChecked'=>studio_web_checked($after),
   'unapproved'=>empty($after['web']['approvedHash'])&&!$after['verified'],'noDelivery'=>empty($after['web']['delivery'])];
  $results[]=$row;
  if(!$row['webTextPreserved']||!$row['sameSourceIdentity']||!$row['unapproved']||!$row['noDelivery'])throw new RuntimeException('Backfill invariant requires manual investigation');
 }
 file_put_contents($stage.'/backfill-result.json',json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));chmod($stage.'/backfill-result.json',0600);
 echo json_encode(['legacyBackfill'=>$results],JSON_UNESCAPED_SLASHES)."\n";
 foreach($results as $row)if(!$row['completed']||!$row['radioGenerated']||!$row['sameOriginalSnapshot'])exit(2);
 echo "RSS_LEGACY_BACKFILL_OK\n";
}finally{flock($lock,LOCK_UN);fclose($lock);}
