<?php
require __DIR__.'/kickoff-install.php';
$dir=sys_get_temp_dir().'/rr-kickoff-'.bin2hex(random_bytes(6));mkdir($dir,0700);
$root=$dir.'/site';$stage=$dir.'/stage';$backup=$dir.'/backup';$count=0;
function check86(bool $ok,string $why):void{global $count;$count++;if(!$ok)throw new RuntimeException($why);}
function rejects86(callable $f,string $why):void{try{$f();}catch(RuntimeException $e){check86(true,$why);return;}throw new RuntimeException($why);}
function put86(string $p,string $data):void{if(!is_dir(dirname($p)))mkdir(dirname($p),0700,true);file_put_contents($p,$data);}
function clean86(string $p):void{if(is_dir($p)&&!is_link($p)){foreach(scandir($p)as$n)if($n!=='.'&&$n!=='..')clean86($p.'/'.$n);rmdir($p);}else unlink($p);}
try {
    $m=json_decode(file_get_contents(__DIR__.'/kickoff-release.json'),true);
    foreach($m['before']as$p=>$h){
        $old=$h===null?null:'old '.$p;$new=isset($m['changed'][$p])?'new '.$p:$old;
        $m['before'][$p]=$old===null?null:hash('sha256',$old);$m['after'][$p]=hash('sha256',$new);
        if($old!==null)put86($root.'/'.$p,$old);
        if(isset($m['changed'][$p])){$m['changed'][$p]=$m['after'][$p];put86($stage.'/runtime/'.$p,$new);}
    }
    put86($stage.'/scripts/kickoff-release.json',json_encode($m));
    rrKickoffDeploy($root,$stage,$backup,'check');check86(!file_exists($backup),'Check creates no backup');
    $p=array_keys($m['changed'])[0];$existing=array_keys(array_filter($m['changed'],fn($h,$k)=>$m['before'][$k]!==null,ARRAY_FILTER_USE_BOTH))[0];
    put86($root.'/'.$existing,'editor code');rejects86(fn()=>rrKickoffDeploy($root,$stage,$backup,'install'),'Runtime drift blocks install');check86(!file_exists($backup),'Drift precedes backup');put86($root.'/'.$existing,'old '.$existing);
    put86($stage.'/runtime/'.$p,'corrupt');rejects86(fn()=>rrKickoffDeploy($root,$stage,$backup,'check'),'Corrupt package blocked');put86($stage.'/runtime/'.$p,'new '.$p);
    rrKickoffDeploy($root,$stage,$backup,'install');rrKickoffDeploy($root,$stage,$backup,'after');check86(is_file($backup.'/receipt.json'),'Verified backup receipt');
    foreach($m['after']as$file=>$h)check86(hash_file('sha256',$root.'/'.$file)===$h,'Exact installed hash');
    put86($root.'/'.$existing,'concurrent edit');rejects86(fn()=>rrKickoffDeploy($root,$stage,$backup,'rollback'),'Rollback preserves concurrent code');check86(file_get_contents($root.'/'.$existing)==='concurrent edit','Concurrent edit remains');put86($root.'/'.$existing,'new '.$existing);
    rrKickoffDeploy($root,$stage,$backup,'rollback');rrKickoffDeploy($root,$stage,$backup,'check');check86(hash_file('sha256',$root.'/'.$p)===$m['before'][$p],'Original runtime file restored');
    rrKickoffDeploy($root,$stage,$backup,'rollback');check86(true,'Rollback is idempotent');
    rejects86(fn()=>rrKickoffDeploy($root,$stage,$backup,'install'),'Existing backup cannot be replaced');
    if(function_exists('symlink')){
        put86($dir.'/outside-file','old '.$existing);unlink($root.'/'.$existing);
        if(@symlink($dir.'/outside-file',$root.'/'.$existing))rejects86(fn()=>rrKickoffDeploy($root,$stage,$backup,'check'),'Symlink below webroot blocked');
        else put86($root.'/'.$existing,'old '.$existing);
    }
    echo "OK: $count kickoff release controls; no WordPress, AI or production writes\n";
} finally {clean86($dir);}
