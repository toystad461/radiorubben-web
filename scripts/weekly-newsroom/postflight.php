<?php
/** In-memory reads only. Never run tick(), prepare(), approve() or a notification job. */
if(!defined('WP_CLI')||!WP_CLI||get_option('home')!=='https://www.radiorubben.no')throw new RuntimeException('Wrong context');
use RadioRubben\Fotballrobot\WeeklyCoverage;
use RadioRubben\Fotballrobot\Newsroom;
use RadioRubben\Fotballrobot\ReviewDigest;
if(!class_exists(WeeklyCoverage::class))throw new RuntimeException('Weekly module missing');
$queue=ReviewDigest::worker('queue'); // Bubbles up incompatibility instead of accepting a null queue.
$r=Newsroom::response();
if(!is_int($r['studioQueueReady']??null)||($queue['version']??'')!=='2026-10-04.1')throw new RuntimeException('Newsroom bridge not healthy');
if(!function_exists('rrni_catalog')||count(rrni_catalog()['assets']??[])!==21)throw new RuntimeException('Image catalog invalid');
$p=get_post(1398);if(!$p||$p->post_status!=='publish')throw new RuntimeException('Existing story is not public');
if(!isset(rest_get_server()->get_routes()['/rr-fotballrobot/v1/weekly']))throw new RuntimeException('Weekly endpoint missing');
echo "WEEKLY_NEWSROOM_RUNTIME_OK\n";
