<?php
use RadioRubben\Fotballrobot\ReviewDigest;
use RadioRubben\Fotballrobot\PlayerReview;
use RadioRubben\Fotballrobot\ReviewDesk;
if(get_option('home')!=='https://www.radiorubben.no'||!current_user_can('manage_options'))throw new RuntimeException('Wrong site or actor.');
require_once ABSPATH.'studio-private/app/newsroom.php';
require_once ABSPATH.'studio-private/app/newsroom-wordpress.php';
if(studio_newsroom_wp()['version']!=='newsroom-1')throw new RuntimeException('Bridge not ready.');
$before=studio_board_read();$itemsHash=hash('sha256',serialize($before['items']));
// Previously notified articles are not emailed again just because the software changed.
$state=get_option(ReviewDigest::OPTION,[]);$state['seen']??=[];
foreach(ReviewDesk::items()as$p){$s=get_post_meta($p->ID,PlayerReview::META,true);if(is_array($s)&&($s['mail']??'')==='accepted')$state['seen'][]='wp:'.$p->ID.':'.PlayerReview::hash($p);}
$state['seen']=array_values(array_unique($state['seen']));update_option(ReviewDigest::OPTION,$state,false);
studio_board_change(static function(&$b){$b['newsroomSettings']=['enabled'=>true,'activatedAt'=>gmdate('c'),'dailyLimit'=>8];});
if(hash('sha256',serialize(studio_board_read()['items']))!==$itemsHash)throw new RuntimeException('Existing articles unexpectedly changed.');
update_option('rrfr_newsroom_enabled',true,false);ReviewDigest::register();
echo wp_json_encode(['automatic_preparation'=>true,'daily_limit'=>8,'prepare_next'=>wp_next_scheduled('rrfr_newsroom_prepare'),'digest_next'=>wp_next_scheduled('rrfr_newsroom_digest'),'existing_articles'=>'unchanged','publication'=>'manual'],JSON_UNESCAPED_SLASHES)."\nNEWSROOM_ACTIVATED\n";
