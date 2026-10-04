<?php
declare(strict_types=1);
$stage=$argv[1]??'';$backup=$argv[2]??'';$mode=$argv[3]??'';$root='/run/webroots/r1417157';
if(!is_dir($stage)||!is_dir($backup)||!in_array($mode,['install','rollback'],true))throw new RuntimeException('Invalid paths.');
$m=json_decode(file_get_contents($stage.'/scripts/player-story-release.json'),true,32,JSON_THROW_ON_ERROR);
$allowed=array_map(static fn($p)=>'wp-content/plugins/radio-rubben-fotballrobot/'.$p,['includes/player-review.php','includes/writer.php','includes/player-corrections.php','includes/review-desk.php','includes/player-monitor.php','radio-rubben-fotballrobot.php']);
$paths=array_keys($m['after']);sort($paths);$check=$allowed;sort($check);
if($paths!==$check||array_diff(array_keys($m['before']),$allowed))throw new RuntimeException('Wrong release scope.');
$write=static function($p,$body){$tmp=tempnam(dirname($p),'.player-story-');if(file_put_contents($tmp,$body)===false||!chmod($tmp,0644)||!rename($tmp,$p))throw new RuntimeException('Atomic replacement failed.');};
$restore=static function()use($m,$allowed,$root,$backup,$write){foreach($allowed as$p){if(isset($m['before'][$p])){$data=file_get_contents($backup.'/code/'.$p);if(hash('sha256',$data)!==$m['before'][$p])throw new RuntimeException('Backup mismatch.');$write($root.'/'.$p,$data);}elseif(is_file($root.'/'.$p)&&hash_file('sha256',$root.'/'.$p)===$m['after'][$p])unlink($root.'/'.$p);}};
if($mode==='rollback'){$restore();echo "PLAYER_STORY_RESTORED\n";exit;}
foreach($allowed as$p){
    if(is_link($root.'/'.$p)||is_link(dirname($root.'/'.$p)))throw new RuntimeException('Symlink refused.');
    if(isset($m['before'][$p])){if(!is_file($root.'/'.$p)||hash_file('sha256',$root.'/'.$p)!==$m['before'][$p])throw new RuntimeException('Active code changed: '.$p);}
    elseif(file_exists($root.'/'.$p))throw new RuntimeException('New path already exists.');
    if(hash_file('sha256',$stage.'/runtime/'.$p)!==$m['after'][$p])throw new RuntimeException('Package mismatch.');
}
foreach($m['before']as$p=>$hash){$to=$backup.'/code/'.$p;if(!is_dir(dirname($to)))mkdir(dirname($to),0700,true);if(!copy($root.'/'.$p,$to)||hash_file('sha256',$to)!==$hash)throw new RuntimeException('Backup failed.');}
copy($stage.'/scripts/player-story-release.json',$backup.'/manifest.json');
try{foreach($allowed as$p)$write($root.'/'.$p,file_get_contents($stage.'/runtime/'.$p));foreach($m['after']as$p=>$h)if(hash_file('sha256',$root.'/'.$p)!==$h)throw new RuntimeException('Installed hash mismatch.');}
catch(Throwable $e){$restore();throw $e;}
echo "PLAYER_STORY_INSTALLED\n";
