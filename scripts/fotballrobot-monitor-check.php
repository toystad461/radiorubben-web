<?php
use RadioRubben\Fotballrobot\PlayerMonitor;
use RadioRubben\Fotballrobot\Players;
use RadioRubben\Fotballrobot\Writer;
use RadioRubben\Fotballrobot\PlayerReview;
$status=PlayerMonitor::status();
if($status['version']!=='0.9.5'||!$status['automatic_proposals']||!$status['next_check']||$status['publication']!=='manual_approval_required')throw new RuntimeException('Monitor configuration mismatch');
$expected=[3942773,3909887,3584397,3646624,3909852];$actual=array_column($status['players'],'fiks_id');sort($expected);sort($actual);if($actual!==$expected)throw new RuntimeException('Unexpected monitored players');
if(Writer::DEFAULT_FEATURED_MEDIA!==812)throw new RuntimeException('Match image changed');
$routes=rest_get_server()->get_routes();foreach(['/rr-fotballrobot/v1/player-monitor','/rr-fotballrobot/v1/players/(?P<id>[0-9]+)/sources'] as $path)if(!isset($routes[$path]))throw new RuntimeException('Monitor route missing');
foreach($status['players'] as $p)if(!$p['enabled'])throw new RuntimeException('Existing watch was paused');
echo "Verified native player monitor, five preserved FIKS profiles, private source routes and manual approval. No write or mail test.\n";
