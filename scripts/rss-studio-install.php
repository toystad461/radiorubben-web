<?php
declare(strict_types=1);
$stage=$argv[1]??'';$backup=$argv[2]??'';$mode=$argv[3]??'install';
$root='/run/webroots/r1417157/studio-private/app';
if(realpath($root)!==$root||!is_dir($stage)||!is_dir($backup)||!in_array($mode,['install','rollback'],true))throw new RuntimeException('Invalid deployment paths');
$m=json_decode(file_get_contents($stage.'/scripts/rss-studio-release.json'),true,64,JSON_THROW_ON_ERROR);
$a=json_decode(file_get_contents($stage.'/scripts/release-artifacts/rss-studio.json'),true,64,JSON_THROW_ON_ERROR);
$allowed=['case-workflow.php','news-script.php','newsroom.php'];$keys=array_keys($m['after']);sort($keys);
if($keys!==$allowed||$a['source_commit']!==$m['source_commit'])throw new RuntimeException('Scope mismatch');
$write=static function($name,$bytes)use($root){
 $tmp=tempnam($root,'.rss-release-');
 if(file_put_contents($tmp,$bytes)===false||!chmod($tmp,0644)||!rename($tmp,$root.'/'.$name))throw new RuntimeException('Code replacement failed');
};
$lock=fopen(dirname($root).'/config/sending-board.json.newsroom.lock','c');
if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Newsroom worker busy; release stopped');
try{
 if($mode==='rollback'){
  foreach($allowed as $name){
   $bytes=file_get_contents($backup.'/'.$name);
   if(hash('sha256',$bytes)!==$m['before'][$name])throw new RuntimeException('Backup mismatch');
   $write($name,$bytes);
  }
  echo "RSS_CODE_RESTORED\n";exit;
 }
 foreach($m['before'] as $name=>$hash){
  if(!preg_match('/^[a-z-]+\.php$/D',$name)||is_link($root.'/'.$name)||!hash_equals($hash,hash_file('sha256',$root.'/'.$name)))throw new RuntimeException('Active code drift: '.$name);
 }
 $content=[];
 foreach($allowed as $name){
  $bytes=$a['files']['studio-private/app/'.$name]['content'];
  if(hash('sha256',$bytes)!==$m['after'][$name])throw new RuntimeException('Artifact mismatch');
  $content[$name]=$bytes;
  if(!copy($root.'/'.$name,$backup.'/'.$name)||hash_file('sha256',$backup.'/'.$name)!==$m['before'][$name])throw new RuntimeException('Backup failed');
 }
 copy($stage.'/scripts/rss-studio-release.json',$backup.'/manifest.json');
 try{
  foreach(['news-script.php','case-workflow.php','newsroom.php'] as $name)$write($name,$content[$name]);
  foreach($m['after'] as $name=>$hash)if(hash_file('sha256',$root.'/'.$name)!==$hash)throw new RuntimeException('Postflight mismatch');
 }catch(Throwable $e){foreach($allowed as $name)$write($name,file_get_contents($backup.'/'.$name));throw $e;}
 echo json_encode(['source'=>$m['source_commit'],'after'=>$m['after']],JSON_UNESCAPED_SLASHES)."\nRSS_CODE_INSTALLED\n";
}finally{flock($lock,LOCK_UN);fclose($lock);}
