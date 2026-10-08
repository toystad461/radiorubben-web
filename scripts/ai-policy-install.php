<?php
declare(strict_types=1);
/** One-file policy rollout. Historical PR30 receipts remain immutable. */
function rrPolicyDeploy(string $root,string $stage,string $backup,string $mode): void {
    $path='wp-content/plugins/radio-rubben-fotballrobot/includes/publication-gate.php';
    if(!in_array($mode,['check','install','after','rollback'],true))throw new RuntimeException('Invalid mode');
    $m=json_decode(file_get_contents($stage.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
    if(($m['path']??'')!==$path || !preg_match('/^[a-f0-9]{40}$/D',$m['source_commit']??''))throw new RuntimeException('Invalid scope');
    foreach(['before','after'] as $set)foreach($m[$set] as $p=>$h){
        if(!str_starts_with($p,'wp-content/plugins/radio-rubben-fotballrobot/')||str_contains($p,'..')||str_contains($p,'\\')||!preg_match('/^[a-f0-9]{64}$/D',$h))throw new RuntimeException('Invalid manifest');
        $at=$root;foreach(explode('/',$p)as$part){$at.='/'.$part;if(is_link($at))throw new RuntimeException('Symlink rejected');}
    }
    if(array_keys($m['before'])!==array_keys($m['after']))throw new RuntimeException('Scope changed');
    $diff=[];foreach($m['before'] as $p=>$h)if($h!==$m['after'][$p])$diff[]=$p;
    if($diff!==[$path])throw new RuntimeException('Only publication gate may change');
    $hash=static fn(string $p):?string=>is_file($p)&&!is_link($p)?hash_file('sha256',$p):null;
    $verify=static function(array $expected)use($root,$hash):void{foreach($expected as $p=>$h)if($hash($root.'/'.$p)!==$h)throw new RuntimeException('Runtime drift: '.$p);};
    $target=$root.'/'.$path;
    $write=static function(string $bytes,int $mode)use($target):void{
        $tmp=tempnam(dirname($target),'.rr-policy-');if($tmp===false)throw new RuntimeException('Temporary file failed');
        try{if(file_put_contents($tmp,$bytes)!==strlen($bytes)||!chmod($tmp,$mode)||!rename($tmp,$target))throw new RuntimeException('Atomic replacement failed');}
        finally{if(is_file($tmp))unlink($tmp);}
        if(function_exists('opcache_invalidate'))opcache_invalidate($target,true);
    };
    if($mode==='after'){$verify($m['after']);return;}
    if($mode==='rollback'){
        $receipt=json_decode(file_get_contents($backup.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
        if($receipt['release']!==$m||$hash($backup.'/publication-gate.php')!==$m['before'][$path])throw new RuntimeException('Invalid backup');
        if($hash($target)===$m['before'][$path])return;
        if($hash($target)!==$m['after'][$path])throw new RuntimeException('Concurrent edit blocks rollback');
        $write(file_get_contents($backup.'/publication-gate.php'),$receipt['mode']);return;
    }
    $verify($m['before']);
    if($hash($stage.'/publication-gate.php')!==$m['after'][$path])throw new RuntimeException('Package mismatch');
    if($mode==='check')return;
    if(file_exists($backup)||!mkdir($backup,0700))throw new RuntimeException('Backup must be new');
    $modeBits=fileperms($target)&0777;
    if(!copy($target,$backup.'/publication-gate.php')||!chmod($backup.'/publication-gate.php',0600)||$hash($backup.'/publication-gate.php')!==$m['before'][$path])throw new RuntimeException('Backup failed');
    $receipt=json_encode(['release'=>$m,'mode'=>$modeBits],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    if(file_put_contents($backup.'/manifest.json',$receipt)!==strlen($receipt)||!chmod($backup.'/manifest.json',0600))throw new RuntimeException('Backup manifest failed');
    $verify($m['before']);
    $bytes=file_get_contents($stage.'/publication-gate.php');
    if(hash('sha256',$bytes)!==$m['after'][$path])throw new RuntimeException('Staging changed');
    $write($bytes,$modeBits);
    try{$verify($m['after']);}catch(Throwable $e){
        if($hash($target)===$m['after'][$path])$write(file_get_contents($backup.'/publication-gate.php'),$modeBits);
        throw $e;
    }
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    umask(0077);
    rrPolicyDeploy('/run/webroots/r1417157',$argv[1]??'',$argv[2]??'',$argv[3]??'');
    echo "AI_POLICY_".strtoupper($argv[3])."_OK\n";
}
