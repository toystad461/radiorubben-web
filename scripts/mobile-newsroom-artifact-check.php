<?php
$m=json_decode(file_get_contents(__DIR__.'/mobile-newsroom-release.json'),true,64,JSON_THROW_ON_ERROR);
$p=__DIR__.'/'.$m['studio_package'];if(hash_file('sha256',$p)!==$m['studio_package_sha256'])throw new RuntimeException('Artifact mismatch');
$a=json_decode(file_get_contents($p),true,64,JSON_THROW_ON_ERROR);if($a['source_commit']!==$m['studio_commit'])throw new RuntimeException('Wrong Studio commit');
foreach($a['files']as$p=>$content){if(!isset($m['after'][$p])||hash('sha256',$content)!==$m['after'][$p])throw new RuntimeException('File mismatch');$to='/tmp/studio-mobile-check/'.$p;@mkdir(dirname($to),0700,true);file_put_contents($to,$content);}
