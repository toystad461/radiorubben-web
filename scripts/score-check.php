<?php
use RadioRubben\PlayerWidget\Live;
use RadioRubben\PlayerWidget\Service;
if(\RadioRubben\PlayerWidget\VERSION!=='1.3.1') WP_CLI::error('Unexpected widget version');
$event=wp_get_scheduled_event('rrpw_refresh');
if(!$event || $event->interval!==60) WP_CLI::error('Minute schedule missing');
// Uses the existing bounded scheduler; no forced parallel or per-player fetch.
Service::tick();
$matches=Live::publicMatches();
foreach($matches as $m) {
    if(isset($m['score']) && (!is_int($m['score']['home']) || !is_int($m['score']['away']))) WP_CLI::error('Invalid score');
}
echo wp_json_encode(['interval'=>$event->interval,'matches'=>$matches],JSON_UNESCAPED_UNICODE)."\n";
echo "SCORE_RELEASE_OK\n";
