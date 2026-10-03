<?php
// Read-only before/after comparison of existing player data and editorial work.
use RadioRubben\Fotballrobot\Players;
use RadioRubben\Fotballrobot\PlayerReview;
$data=[];foreach(Players::ids() as $id)$data['profiles'][$id]=Players::state((int)$id);
foreach(get_posts(['post_type'=>'post','post_status'=>['draft','pending','publish','private','future','trash'],'meta_key'=>PlayerReview::META,'numberposts'=>-1,'orderby'=>'ID','order'=>'ASC']) as $p)$data['reviews'][$p->ID]=[(array)$p,get_post_meta($p->ID,PlayerReview::META,true),get_post_meta($p->ID,'_thumbnail_id',true)];
$data['enabled_at']=get_option('rrfr_player_review_enabled_at',0);
echo hash('sha256',serialize($data));
