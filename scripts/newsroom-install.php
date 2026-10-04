<?php
declare(strict_types=1);
// One-off transport artifact. Studio source authority remains the pinned Studio commit.
$stage=$argv[1]??'';$backup=$argv[2]??'';$mode=$argv[3]??'install';$root='/run/webroots/r1417157';
if(!is_dir($stage)||!is_dir($backup)||!in_array($mode,['install','rollback'],true))throw new RuntimeException('Invalid release paths.');
$m=json_decode(file_get_contents($stage.'/scripts/newsroom-release.json'),true,64,JSON_THROW_ON_ERROR);
$allowed=['studio-private/app/news-script.php','studio-private/app/web-publish.php','studio-private/app/case-workflow.php','studio-private/app/newsroom.php','studio-private/app/newsroom-wordpress.php','studio-private/app/newsroom-worker.php','studio-public/newsdesk.php','studio-public/assets/newsroom.css','studio-public/assets/newsroom.js'];
foreach(['includes/player-review.php','includes/player-monitor.php','includes/review-desk.php','includes/review-digest.php','includes/newsroom.php','radio-rubben-fotballrobot.php']as$p)$allowed[]='wp-content/plugins/radio-rubben-fotballrobot/'.$p;
$paths=array_keys($m['after']);sort($paths);sort($allowed);if($paths!==$allowed||array_diff(array_keys($m['before']),$allowed))throw new RuntimeException('Release scope mismatch.');
$write=static function(string $path,string $content):void{$tmp=tempnam(dirname($path),'.newsroom-');if(file_put_contents($tmp,$content)===false||!chmod($tmp,0644)||!rename($tmp,$path))throw new RuntimeException('Atomic code replacement failed.');};
if($mode==='rollback'){
    foreach($allowed as$p){if(isset($m['before'][$p]))$write($root.'/'.$p,file_get_contents($backup.'/code/'.$p));elseif(is_file($root.'/'.$p))unlink($root.'/'.$p);}
    echo "NEWSROOM_CODE_RESTORED\n";exit;
}
$package=$stage.'/scripts/'.$m['studio_package'];if(!hash_equals($m['studio_package_sha256'],hash_file('sha256',$package)))throw new RuntimeException('Studio artifact changed.');
$s=json_decode(file_get_contents($package),true,64,JSON_THROW_ON_ERROR);if($s['source_commit']!==$m['studio_commit']||$s['source_repository']!=='toystad461/radiorubben-studio')throw new RuntimeException('Wrong Studio source.');
$content=[];
foreach($allowed as$p){
    $live=$root.'/'.$p;if(is_link($live)||is_link(dirname($live)))throw new RuntimeException('Symlink not allowed.');
    if(isset($m['before'][$p])){if(!is_file($live)||!hash_equals($m['before'][$p],hash_file('sha256',$live)))throw new RuntimeException('Active code drift: '.$p);}
    elseif(file_exists($live))throw new RuntimeException('New file already exists: '.$p);
    $content[$p]=$s['files'][$p]??file_get_contents($stage.'/runtime/'.$p);
    if(!hash_equals($m['after'][$p],hash('sha256',$content[$p])))throw new RuntimeException('Target bytes differ: '.$p);
}
foreach($m['before']as$p=>$hash){$to=$backup.'/code/'.$p;if(!is_dir(dirname($to)))mkdir(dirname($to),0700,true);if(!copy($root.'/'.$p,$to)||hash_file('sha256',$to)!==$hash)throw new RuntimeException('Backup failed.');}
copy($stage.'/scripts/newsroom-release.json',$backup.'/manifest.json');
// Dependencies before callers; web controller and plugin entry last.
usort($allowed,static function($a,$b)use($m){$rank=static fn($p)=>in_array($p,['studio-public/newsdesk.php','wp-content/plugins/radio-rubben-fotballrobot/radio-rubben-fotballrobot.php'],true)?2:(isset($m['before'][$p])?1:0);return $rank($a)<=>$rank($b);});
try{foreach($allowed as$p)$write($root.'/'.$p,$content[$p]);foreach($m['after']as$p=>$hash)if(!hash_equals($hash,hash_file('sha256',$root.'/'.$p)))throw new RuntimeException('Post-install mismatch.');}
catch(Throwable $e){foreach($allowed as$p){if(isset($m['before'][$p]))$write($root.'/'.$p,file_get_contents($backup.'/code/'.$p));elseif(is_file($root.'/'.$p))unlink($root.'/'.$p);}throw $e;}
echo json_encode(['studio_commit'=>$m['studio_commit'],'wordpress_runtime_commit'=>$m['wordpress_runtime_commit'],'after'=>$m['after']],JSON_UNESCAPED_SLASHES)."\nNEWSROOM_CODE_INSTALLED\n";
