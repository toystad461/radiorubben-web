<?php
// Read-only comparison; output only a digest, never draft text or private configuration.
use RadioRubben\Fotballrobot\Players;
use RadioRubben\Fotballrobot\PlayerReview;
$data=[];foreach(Players::ids() as $id)$data['profiles'][$id]=Players::state((int)$id);
foreach(get_posts(['post_type'=>'post','post_status'=>['draft','pending','publish','private','future','trash'],'numberposts'=>-1,'orderby'=>'ID','order'=>'ASC',
    'meta_query'=>['relation'=>'OR',['key'=>PlayerReview::META,'compare'=>'EXISTS'],['key'=>'_rrfr_ai_match','compare'=>'EXISTS'],['key'=>'_rrfr_trial_match','compare'=>'EXISTS']]]) as $p)
    $data['articles'][$p->ID]=[(array)$p,get_post_meta($p->ID)];
$data['enabled_at']=get_option('rrfr_player_review_enabled_at',0);
$data['match_config']=get_option('rrfr_match_jobs',[]);
$data['candidates']=get_option('rrfr_player_candidates',[]);
foreach(Players::ids() as $id)$data['sources'][$id]=get_option('rrfr_player_sources_'.$id,[]);
echo hash('sha256',serialize($data));
