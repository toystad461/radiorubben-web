<?php
// Runtime smoke check: no ingestion, following, AI generation, notification or publishing.
use RadioRubben\Fotballrobot\PlayerCandidates;
use RadioRubben\Fotballrobot\PlayerMonitor;
use RadioRubben\Fotballrobot\PlayerReview;
use RadioRubben\Fotballrobot\MediaSources;
use RadioRubben\Fotballrobot\Writer;
use RadioRubben\Fotballrobot\PublicationGate;
$status=PlayerMonitor::status();
if($status['version']!=='0.9.6'||!$status['automatic_proposals']||!$status['next_check']||$status['publication']!=='manual_approval_required')throw new RuntimeException('Monitor is not active with the expected safeguards');
if(Writer::WRITING_PROMPT_VERSION!=='2026-10-04.1')throw new RuntimeException('Wrong writing prompt revision');
if(!has_action('admin_post_rrfr_candidate_action',[PlayerCandidates::class,'action']))throw new RuntimeException('Candidate decision handler absent');
if(!has_filter('wp_insert_post_data',[PublicationGate::class,'guard']))throw new RuntimeException('Publication protection absent');
$users=get_users(['role'=>'administrator','number'=>1,'fields'=>'ID']);if(!$users)throw new RuntimeException('No editor for read-only rendering');wp_set_current_user((int)$users[0]);
$_GET['status']='all';ob_start();PlayerCandidates::page();$html=ob_get_clean();
if(!str_contains($html,'Spillere ute – kandidater')||!str_contains($html,'Kartlegging pågår'))throw new RuntimeException('Candidate page missing');
$url='https://example.org/kontrollert-intervju';
$facts=['type'=>'public_news','source'=>$url,'news'=>['url'=>$url,'video'=>['url'=>$url,'publisher'=>'Kontrollmedium','content_verified'=>true],'supporting_sources'=>[['url'=>'https://example.org/kamp']]]];
$article=['title'=>'Kontroll','lead'=>'En kontrollert testtekst.','paragraphs'=>['Kildene følger saken.'],'checks'=>[]];
$body=PlayerReview::body($article,$facts);
if(!str_contains($body,'Se hele videointervjuet hos Kontrollmedium')||!str_contains($body,'https://example.org/kamp'))throw new RuntimeException('Video or source footer missing');
echo 'Verified v0.9.6 candidate approval, source links, original video link, writing revision and publication gate; no player or article changed.'.PHP_EOL;
