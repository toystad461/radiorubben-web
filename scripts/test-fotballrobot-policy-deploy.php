<?php
require __DIR__.'/ai-policy-install.php';
$base=sys_get_temp_dir().'/rr-policy-'.bin2hex(random_bytes(6));mkdir($base,0700);
$p='wp-content/plugins/radio-rubben-fotballrobot/includes/publication-gate.php';
$root=$base.'/root';$stage=$base.'/stage';$backup=$base.'/backup';mkdir(dirname($root.'/'.$p),0700,true);mkdir($stage,0700);
file_put_contents($root.'/'.$p,'before');file_put_contents($stage.'/publication-gate.php','after');
$m=['source_commit'=>str_repeat('a',40),'path'=>$p,'before'=>[$p=>hash('sha256','before')],'after'=>[$p=>hash('sha256','after')]];
file_put_contents($stage.'/manifest.json',json_encode($m));
function rejects(callable $f):void{try{$f();}catch(RuntimeException $e){return;}throw new RuntimeException('Expected rejection');}
try{
 rrPolicyDeploy($root,$stage,$backup,'check');
 file_put_contents($root.'/'.$p,'other');rejects(fn()=>rrPolicyDeploy($root,$stage,$backup,'install'));
 if(file_get_contents($root.'/'.$p)!=='other')throw new RuntimeException('Drift overwritten');
 file_put_contents($root.'/'.$p,'before');
 file_put_contents($stage.'/publication-gate.php','bad package');rejects(fn()=>rrPolicyDeploy($root,$stage,$backup,'install'));
 file_put_contents($stage.'/publication-gate.php','after');
 rrPolicyDeploy($root,$stage,$backup,'install');rrPolicyDeploy($root,$stage,$backup,'after');
 if(file_get_contents($backup.'/publication-gate.php')!=='before')throw new RuntimeException('Backup failed');
 file_put_contents($root.'/'.$p,'concurrent');rejects(fn()=>rrPolicyDeploy($root,$stage,$backup,'rollback'));
 if(file_get_contents($root.'/'.$p)!=='concurrent')throw new RuntimeException('Concurrent edit overwritten');
 file_put_contents($root.'/'.$p,'after');rrPolicyDeploy($root,$stage,$backup,'rollback');
 if(file_get_contents($root.'/'.$p)!=='before')throw new RuntimeException('Rollback failed');
 echo "PASS: drift, package integrity, install, backup, postflight, concurrent rollback and restore\n";
}finally{
 $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
 foreach($it as$f){if($f->isDir())rmdir($f->getPathname());else unlink($f->getPathname());}rmdir($base);
}
