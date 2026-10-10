<?php
declare(strict_types=1);
/** Selective code release: never loads WordPress or writes editorial data. */
function rr86Deploy(string $root,string $stage,string $backup,string $action): void {
    // The provider's documented /run/webroots entry itself is a symlink.
    // Resolve that trusted entry once; reject all links below the real root.
    $root=realpath($root)?:throw new RuntimeException('Webroot missing');
    if(!in_array($action,['check','install','after','rollback'],true))throw new RuntimeException('Invalid action');
    $manifest=$stage.'/scripts/pr86-release.json';
    $m=json_decode(file_get_contents($manifest),true,64,JSON_THROW_ON_ERROR);
    $prefix='wp-content/plugins/radio-rubben-fotballrobot/';
    $allowed=array_map(fn($p)=>$prefix.$p,['includes/match-followup.php','includes/match-jobs.php','includes/match-work.php','includes/newsroom.php','includes/publication-gate.php','includes/report.php','includes/review-desk.php','includes/robot.php','includes/writer.php','radio-rubben-fotballrobot.php']);
    $keys=array_keys($m['changed']);sort($keys);$scope=$allowed;sort($scope);
    if($keys!==$scope||array_keys($m['before'])!==array_keys($m['after']))throw new RuntimeException('Release scope mismatch');
    $safe=static function(string $base,string $p):string {
        if(str_contains($p,'..')||str_contains($p,'\\')||str_starts_with($p,'/'))throw new RuntimeException('Unsafe path');
        $at=$base;if(is_link($at))throw new RuntimeException('Symlink');
        foreach(explode('/',$p)as$part){$at.='/'.$part;if(is_link($at))throw new RuntimeException('Symlink: '.$p);}return $at;
    };
    foreach(['before','after']as$set)foreach($m[$set]as$p=>$h){
        if(!str_starts_with($p,$prefix)||($h!==null&&!preg_match('/^[a-f0-9]{64}$/D',$h)))throw new RuntimeException('Invalid manifest');
        $safe($root,$p);
        if(($m['before'][$p]!==$m['after'][$p])!==in_array($p,$allowed,true))throw new RuntimeException('Unexpected change');
    }
    $hash=static fn($p)=>is_file($p)?hash_file('sha256',$p):null;
    $verify=static function(array $set)use($root,$safe,$hash):void{foreach($set as$p=>$h){$file=$safe($root,$p);if(($h===null&&file_exists($file))||$hash($file)!==$h)throw new RuntimeException('Runtime drift: '.$p);}};
    $write=static function(string $p,string $bytes,int $permissions):void {
        $tmp=tempnam(dirname($p),'.rr86-');if($tmp===false)throw new RuntimeException('Temporary file failed');
        try{if(file_put_contents($tmp,$bytes)!==strlen($bytes)||!chmod($tmp,$permissions)||!rename($tmp,$p))throw new RuntimeException('Atomic replacement failed');}finally{if(is_file($tmp))unlink($tmp);}
        if(function_exists('opcache_invalidate'))opcache_invalidate($p,true);
    };
    $restore=static function()use($backup,$m,$allowed,$root,$safe,$hash,$write):void {
        $receipt=json_decode(file_get_contents($backup.'/receipt.json'),true,64,JSON_THROW_ON_ERROR);
        if($receipt['manifest']!==$m)throw new RuntimeException('Wrong backup receipt');
        // Validate every owned target and backup before restoring any file.
        foreach($allowed as$p){$current=$hash($safe($root,$p));if($current!==$m['before'][$p]&&$current!==$m['after'][$p])throw new RuntimeException('Concurrent edit blocks rollback');if($m['before'][$p]!==null&&$hash($safe($backup,'code/'.$p))!==$m['before'][$p])throw new RuntimeException('Backup mismatch');}
        foreach(array_reverse($allowed)as$p){$target=$safe($root,$p);if($hash($target)===$m['before'][$p])continue;if($m['before'][$p]===null){if(!unlink($target))throw new RuntimeException('Remove failed');}else $write($target,file_get_contents($safe($backup,'code/'.$p)),$receipt['modes'][$p]);}
    };
    if($action==='rollback'){$restore();$verify($m['before']);return;}
    if($action==='after'){$verify($m['after']);return;}
    $verify($m['before']);
    foreach($allowed as$p)if($hash($safe($stage,'runtime/'.$p))!==$m['after'][$p])throw new RuntimeException('Package mismatch: '.$p);
    if($action==='check')return;
    if(file_exists($backup)||!mkdir($backup,0700,true))throw new RuntimeException('Backup must be new');
    $modes=[];
    foreach($allowed as$p)if($m['before'][$p]!==null){$from=$safe($root,$p);$to=$safe($backup,'code/'.$p);if(!is_dir(dirname($to)))mkdir(dirname($to),0700,true);$modes[$p]=fileperms($from)&0777;if(!copy($from,$to)||!chmod($to,0600)||$hash($to)!==$m['before'][$p])throw new RuntimeException('Backup failed');}
    $receipt=json_encode(['manifest'=>$m,'modes'=>$modes],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    if(file_put_contents($backup.'/receipt.json',$receipt)!==strlen($receipt)||!chmod($backup.'/receipt.json',0600))throw new RuntimeException('Receipt failed');
    $verify($m['before']);
    try{foreach($allowed as$p){$bytes=file_get_contents($safe($stage,'runtime/'.$p));if(hash('sha256',$bytes)!==$m['after'][$p])throw new RuntimeException('Staging changed');$write($safe($root,$p),$bytes,$modes[$p]??0644);}$verify($m['after']);}catch(Throwable $e){$restore();throw $e;}
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    umask(0077);
    try{rr86Deploy('/run/webroots/r1417157',$argv[1]??'',$argv[2]??'',$argv[3]??'');echo 'PR86_'.strtoupper($argv[3])."_OK\n";}
    catch(Throwable $e){fwrite(STDERR,'PR86 blocked: '.$e->getMessage()."\n");exit(1);}
}
