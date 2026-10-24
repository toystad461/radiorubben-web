<?php
// Read-only: no WordPress bootstrap, code writes, options, queues or model calls.
$m=json_decode(/* MANIFEST */,true,512,JSON_THROW_ON_ERROR);
$root=realpath('/run/webroots/r1417157');
if($root!=='/customers/9/3/1/cptk37ymg/webroots/r1417157')throw new RuntimeException('Unexpected root');
$phase=($argv[1]??'before')==='after'?'after':'before';
$result=['phase'=>$phase,'release'=>$m['release'],'checked_at_utc'=>gmdate(DATE_ATOM),'php'=>PHP_VERSION,'web_commit'=>$m['web_commit'],'studio_commit'=>$m['studio_commit'],'files'=>[],'ready'=>true];
foreach($m[$phase]as$p=>$expected){
 $at=$root;foreach(explode('/',$p)as$part){$at.='/'.$part;if(is_link($at))throw new RuntimeException('Symlink: '.$p);}
 if(file_exists($at)&&!is_file($at))throw new RuntimeException('Not regular: '.$p);
 $actual=is_file($at)?hash_file('sha256',$at):null;
 $result['files'][$p]=['sha256'=>$actual,'matches_expected'=>$actual===$expected];
 if($actual!==$expected)$result['ready']=false;
}
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
exit($result['ready']?0:2);
