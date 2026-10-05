<?php
use RadioRubben\Fotballrobot\Newsroom;
use RadioRubben\Fotballrobot\PlayerCorrections;
use RadioRubben\Fotballrobot\PlayerReview;
use RadioRubben\Fotballrobot\PublicationGate;
$p=get_post(1091);if(!$p||$p->post_status!=='publish')throw new RuntimeException('Published story missing.');
$fingerprint=hash('sha256',serialize([$p->post_title,$p->post_excerpt,$p->post_content,get_post_meta(1091,'_thumbnail_id',true),get_post_meta(1091,PlayerReview::META,true),get_post_meta(1091,PublicationGate::META,true),get_post_meta(1091,PlayerCorrections::META,true)]));
if(($args[0]??'')==='before'){echo json_encode(['post_1091'=>$fingerprint]);return;}
$before=json_decode(file_get_contents($args[1]??''),true,64,JSON_THROW_ON_ERROR);
if(($before['post_1091']??'')!==$fingerprint)throw new RuntimeException('Story changed during release.');
if(!method_exists(PlayerCorrections::class,'revise'))throw new RuntimeException('Missing correction method.');
$r=Newsroom::response();$card=Newsroom::card(1091);
if(!array_key_exists('image',$card)||($card['image']['id']??0)!==813||!array_key_exists('isCorrection',$card))throw new RuntimeException('Incomplete mobile response.');
require_once '/run/webroots/r1417157/studio-private/app/newsroom-view.php';
if(!studio_newsroom_image_url($card['image']['url']))throw new RuntimeException('Featured image URL unavailable.');
echo wp_json_encode(['bridge'=>$r['version'],'cards'=>count($r['items']),'image_id'=>$card['image']['id'],'correction'=>$card['isCorrection'],'public_story_and_pending_proposal'=>'unchanged'])."\nMOBILE_NEWSROOM_RUNTIME_OK\n";
