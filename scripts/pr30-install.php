<?php
declare(strict_types=1);
$stage=$argv[1]??'';$backup=$argv[2]??'';$mode=$argv[3]??'';
$root='/run/webroots/r1417157';
if(!is_dir($stage)||!is_dir($backup)||!in_array($mode,['check','install','rollback','after'],true))throw new RuntimeException('Invalid release invocation.');
$m=json_decode(file_get_contents($stage.'/scripts/pr30-release.json'),true,64,JSON_THROW_ON_ERROR);
$prefix='wp-content/plugins/radio-rubben-fotballrobot/';
$allowed=array_map(static fn($p)=>$prefix.$p,['includes/club-coverage.php','includes/fotballdata.php','includes/club-automation.php','includes/writer.php','includes/publication-gate.php','includes/review-desk.php','includes/newsroom.php','radio-rubben-fotballrobot.php']);
$expected=array_keys($m['changed']);sort($expected);$scope=$allowed;sort($scope);
if($expected!==$scope)throw new RuntimeException('Release scope mismatch.');
foreach(array_keys($m['before']+$m['after'])as$p)if(!str_starts_with($p,$prefix)||str_contains($p,'..')||str_contains($p,"\\"))throw new RuntimeException('Unsafe runtime path.');
$hash=static fn($p)=>is_file($p)&&!is_link($p)?hash_file('sha256',$p):null;
$write=static function($p,$bytes):void {
    if(is_link($p)||is_link(dirname($p)))throw new RuntimeException('Symlink forbidden.');
    $tmp=tempnam(dirname($p),'.rrfr-pr30-');
    if($tmp===false||file_put_contents($tmp,$bytes)!==strlen($bytes)||!chmod($tmp,0644)||!rename($tmp,$p))throw new RuntimeException('Atomic replacement failed.');
    if(function_exists('opcache_invalidate'))opcache_invalidate($p,true);
};
if($mode==='rollback') {
    if(!is_file($backup.'/manifest.json'))throw new RuntimeException('Backup manifest missing.');
    foreach(array_reverse($allowed)as$p) {
        $current=$hash($root.'/'.$p);$before=$m['before'][$p]??null;
        if($current===$before)continue;
        if($current!==$m['after'][$p])throw new RuntimeException('Rollback found concurrent code change: '.$p);
        if($before===null) {if(!unlink($root.'/'.$p))throw new RuntimeException('Could not remove owned file.');}
        else {
            if($hash($backup.'/code/'.$p)!==$before)throw new RuntimeException('Backup checksum failed.');
            $write($root.'/'.$p,file_get_contents($backup.'/code/'.$p));
        }
    }
    echo "PR30_CODE_RESTORED\n";exit;
}
if($mode==='after') {
    foreach($m['after']as$p=>$h)if($hash($root.'/'.$p)!==$h)throw new RuntimeException('Postflight hash mismatch: '.$p);
    echo "PR30_ALL_RUNTIME_HASHES_OK\n";exit;
}
foreach($m['before']as$p=>$h) {
    if(is_link(dirname($root.'/'.$p))||$hash($root.'/'.$p)!==$h)throw new RuntimeException('Active runtime drift: '.$p);
}
foreach($allowed as$p) {
    if(!isset($m['before'][$p])&&file_exists($root.'/'.$p))throw new RuntimeException('New runtime file already exists: '.$p);
    if($hash($stage.'/runtime/'.$p)!==$m['after'][$p])throw new RuntimeException('Release package mismatch: '.$p);
}
if($mode==='check'){echo "PR30_BEFORE_AND_PACKAGE_OK\n";exit;}
foreach($allowed as$p)if(isset($m['before'][$p])) {
    $to=$backup.'/code/'.$p;if(!is_dir(dirname($to)))mkdir(dirname($to),0700,true);
    if(!copy($root.'/'.$p,$to)||$hash($to)!==$m['before'][$p])throw new RuntimeException('Backup failed.');
}
if(!copy($stage.'/scripts/pr30-release.json',$backup.'/manifest.json'))throw new RuntimeException('Backup manifest failed.');
$written=[];
try {
    foreach($allowed as$p){$write($root.'/'.$p,file_get_contents($stage.'/runtime/'.$p));$written[]=$p;}
    foreach($m['after']as$p=>$h)if($hash($root.'/'.$p)!==$h)throw new RuntimeException('Post-install mismatch: '.$p);
} catch(Throwable $e) {
    foreach(array_reverse($written)as$p) {
        if($hash($root.'/'.$p)!==$m['after'][$p])throw new RuntimeException('Concurrent code change during rollback.');
        if(isset($m['before'][$p]))$write($root.'/'.$p,file_get_contents($backup.'/code/'.$p));else unlink($root.'/'.$p);
    }
    throw $e;
}
echo "PR30_CODE_INSTALLED\n";
