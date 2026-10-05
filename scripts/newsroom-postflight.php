<?php
use RadioRubben\Fotballrobot\Newsroom;
use RadioRubben\Fotballrobot\ReviewDigest;
if(get_option('home')!=='https://www.radiorubben.no'||Newsroom::VERSION!=='newsroom-1')throw new RuntimeException('Wrong site/version.');
$r=Newsroom::response();if($r['version']!=='newsroom-1')throw new RuntimeException('Invalid bridge.');
$q=ReviewDigest::worker('queue');if(($q['version']??'')!=='2026-10-04.1')throw new RuntimeException('Private Studio worker unavailable.');
echo wp_json_encode(['bridge_version'=>$r['version'],'wordpress_cards'=>count($r['items']),'studio_ready'=>count($q['items']),'digest'=>$r['notification'],'worker'=>'ok'],JSON_UNESCAPED_SLASHES)."\nNEWSROOM_RUNTIME_OK\n";
