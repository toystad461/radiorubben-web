<?php
// Validate the candidate in one native WP-CLI process. No installed code or article edits.
if(!defined('WP_CLI')||!WP_CLI)throw new RuntimeException('WP-CLI required');
if(class_exists('RadioRubben\\Fotballrobot\\Writer',false))throw new RuntimeException('Skip installed robot');
require dirname(__DIR__).'/wordpress/wp-content/plugins/radio-rubben-fotballrobot/radio-rubben-fotballrobot.php';
use RadioRubben\Fotballrobot\Writer;
use RadioRubben\Fotballrobot\Robot;
use RadioRubben\Fotballrobot\PublicationGate as G;
use RadioRubben\Fotballrobot\EditorialNotice as N;
use RadioRubben\Fotballrobot\MatchJobs;
if(get_option('home')!=='https://www.radiorubben.no')throw new RuntimeException('Wrong site');
add_filter('pre_wp_mail',static fn()=>false);
add_filter('pre_http_request',static fn($pre,$args,$url)=>parse_url($url,PHP_URL_HOST)==='api.openai.com'?$pre:new WP_Error('preflight_no_external_action','Blocked'),1,3);
wp_set_current_user((int)(get_option(MatchJobs::CONFIG,[])['owner']??0));
if(!Robot::allowed())throw new RuntimeException('Job owner not authorized');
$post=get_post(1073);
if(!$post||$post->post_status!=='draft'||(int)get_post_meta(1073,'_rrfr_ai_match',true)!==8984418)throw new RuntimeException('Expected approved draft missing');
$hash=G::hash($post);
if($hash!=='ef3deb807b8126f014a875b9f2904f11341e1e7f48281b7b94d0b79249344dcc')throw new RuntimeException('Draft has changed since user approval; inspect first');
if(N::reviewContent($post->post_content)===$post->post_content)throw new RuntimeException('Canonical visible notice missing');
$result=Writer::recheck(1073,$hash);
echo wp_json_encode($result,JSON_UNESCAPED_UNICODE)."\n";
if(!G::current(1073,get_post(1073))||!G::canPublish(1073,get_post(1073)))throw new RuntimeException('Quality check not approved');
if(get_post_status(1073)!=='draft'||G::hash(get_post(1073))!==$hash)throw new RuntimeException('Article changed');
// Verify the existing test-only barrier without publishing anything.
if(G::canPublish(1071,get_post(1071)))throw new RuntimeException('Test-only barrier failed');
file_put_contents(dirname(__DIR__).'/notice-preflight-ok',$hash."\n");
echo "APPROVED NOTICE PREFLIGHT PASSED; article unchanged and unpublished.\n";
