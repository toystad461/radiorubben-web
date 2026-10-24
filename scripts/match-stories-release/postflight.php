<?php
if(!defined('WP_CLI')||!WP_CLI||get_option('home')!=='https://www.radiorubben.no')throw new RuntimeException('Wrong context');
use RadioRubben\Fotballrobot\{MatchStories,ReviewDesk,Newsroom,ReviewDigest};
if(!class_exists(MatchStories::class)||!shortcode_exists('rrfr_weekly_results'))throw new RuntimeException('New module missing');
$routes=rest_get_server()->get_routes();
foreach(['weekly-template','weekly-match']as$r)if(!isset($routes['/rr-fotballrobot/v1/'.$r]))throw new RuntimeException('Route missing');
$queue=ReviewDigest::worker('queue');$r=Newsroom::response();
if(!is_int($r['studioQueueReady']??null)||($queue['version']??'')!=='2026-10-04.1')throw new RuntimeException('Newsroom bridge not healthy');
if(!function_exists('rrni_catalog')||count(rrni_catalog()['assets']??[])!==21)throw new RuntimeException('Image catalog invalid');
$p=get_post(1398);if(!$p||$p->post_status!=='publish')throw new RuntimeException('Existing published story changed');
$p=get_post(1467);if(!$p||$p->post_status!=='draft'||(int)get_post_thumbnail_id($p)!==812)throw new RuntimeException('Draft identity changed');
ob_start();ReviewDesk::article(1467,ReviewDesk::model(1467));$html=ob_get_clean();
if(str_contains(strip_tags($html),'.rrfr-week-card{')||!str_contains($html,'Korte kampartikler'))throw new RuntimeException('Review rendering failed');
$archive=do_shortcode('[rrfr_weekly_results]');
if(!str_contains($archive,'id="rr-weekly-results"')||str_contains($archive,get_permalink(1467)))throw new RuntimeException('Archive/draft boundary failed');
$user=get_current_user_id();wp_set_current_user(0);
foreach(['weekly-template','weekly-match']as$route){$response=rest_do_request(new WP_REST_Request('POST','/rr-fotballrobot/v1/'.$route));if($response->get_status()<400)throw new RuntimeException('Anonymous route open');}
wp_set_current_user($user);
if(!function_exists('wpo_cache_flush'))throw new RuntimeException('Expected page-cache API missing');
wpo_cache_flush();
echo "SPORT_RUNTIME_AND_EDITORIAL_BOUNDARIES_OK\n";
