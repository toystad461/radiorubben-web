<?php
$m=json_decode(file_get_contents(__DIR__.'/rss-studio-release.json'),true,64,JSON_THROW_ON_ERROR);
$a=json_decode(file_get_contents(__DIR__.'/release-artifacts/rss-studio.json'),true,64,JSON_THROW_ON_ERROR);
if($a['source_commit']!==$m['source_commit'])throw new RuntimeException('Source mismatch');
foreach($a['baseline'] as $name=>$bytes)if(hash('sha256',$bytes)!==$m['before'][$name])throw new RuntimeException('Live baseline differs from reviewed Studio source');
@mkdir('/tmp/rss-studio-check',0700,true);
foreach($m['after'] as $name=>$hash){
 $f=$a['files']['studio-private/app/'.$name];$content=$f['content'];
 if(hash('sha256',$content)!==$hash||hash('sha1','blob '.strlen($content)."\0".$content)!==$f['blob'])throw new RuntimeException('Artifact mismatch');
 file_put_contents('/tmp/rss-studio-check/'.$name,$content);
}
echo "RSS_ARTIFACT_VERIFIED\n";
