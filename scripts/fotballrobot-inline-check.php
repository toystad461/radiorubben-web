<?php
// Render in memory only. Never generate, save, mail or publish an article.
use RadioRubben\Fotballrobot\InlineSources;
use RadioRubben\Fotballrobot\Writer;
use RadioRubben\Fotballrobot\EditorialQuality;
use RadioRubben\Fotballrobot\PlayerReview;
if(Writer::WRITING_PROMPT_VERSION!=='2026-10-03.2'||EditorialQuality::RULES_VERSION!=='1.0.0')throw new RuntimeException('Unexpected writing or quality revision');
$url='https://www.fotball.no/fotballdata/kamp/?fiksId=9007464';
$article=['title'=>'Kontroll','lead'=>'Kontroll av kildelenke.','paragraphs'=>['Breivik fikk også gult kort i det 59. minutt.'],'checks'=>[['claim'=>'Kontroll','support'=>'events']],'inline_sources'=>[['paragraph'=>0,'text'=>'gult kort i det 59. minutt','source_url'=>$url]]];
$facts=['source'=>$url];
$html=PlayerReview::body($article,$facts,true);
if(!str_contains($html,'Breivik fikk også <a href="'.$url.'">gult kort i det 59. minutt</a>.')||!str_contains($html,'Kilder:'))throw new RuntimeException('Inline source or footer missing');
if(!in_array('inline_sources',Writer::schema()['required'],true))throw new RuntimeException('Source annotation schema missing');
require __DIR__.'/fotballrobot-monitor-check.php';
echo "Verified inline source rendering, plain excerpt schema and manual approval. No saved article changed.\n";
