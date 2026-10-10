<?php
// Native WP-CLI only. Candidate code runs in this process; installed plugin files are untouched.
if(!defined('WP_CLI')||!WP_CLI)throw new RuntimeException('WP-CLI required');
if(class_exists('RadioRubben\\Fotballrobot\\Writer',false))throw new RuntimeException('Installed plugin must be skipped');
require dirname(__DIR__).'/wordpress/wp-content/plugins/radio-rubben-fotballrobot/radio-rubben-fotballrobot.php';
use RadioRubben\Fotballrobot\Robot;
use RadioRubben\Fotballrobot\Writer;
use RadioRubben\Fotballrobot\PublicationGate;
use RadioRubben\Fotballrobot\MatchJobs;
// No notifications or unrelated HTTP calls during this test.
add_filter('pre_wp_mail',static fn()=>false);
add_filter('pre_http_request',static function($pre,$args,$url){
    return in_array(parse_url($url,PHP_URL_HOST),['www.fotball.no','api.openai.com'],true)?$pre:new WP_Error('preflight_no_external_action','Blocked in preflight');
},1,3);
wp_set_current_user((int)(get_option(MatchJobs::CONFIG,[])['owner']??0));
if(!Robot::allowed())throw new RuntimeException('Existing job owner is not authorized');
Robot::register();
$id=8985496;
if(MatchJobs::active($id))throw new RuntimeException('Match already has active job');
delete_transient('rrfr_source_match_'.$id);
// Full-time result checked independently on the NFF match and result list on 2026-10-03.
$f=Robot::refresh($id,true);
if($f['match']['home']['id']!==19012||$f['match']['away']['id']!==30365||$f['match']['score']!==[1,3]
    ||substr($f['match']['kickoff'],0,10)!=='2026-10-01'||!$f['finished_confirmed'])throw new RuntimeException('Live test facts differ from checked fixture');
$r=Writer::generate($id,$f['fact_hash'],$f['angles'][0]['id'],true);
for($i=0;!empty($r['review_token'])&&$i<3;$i++)$r=Writer::review($r['review_token']);
if(!empty($r['review_token']))throw new RuntimeException('Quality review did not finish');
$posts=get_posts(['post_type'=>'post','post_status'=>['draft','pending','publish','private','future','trash'],'meta_key'=>'_rrfr_ai_match','meta_value'=>$id,'numberposts'=>1]);
$p=$posts[0]??null;
if(!$p||$p->post_status!=='draft'||!get_post_meta($p->ID,'_rrfr_test_only',true))throw new RuntimeException('Separate blocked test draft was not verified');
$q=get_post_meta($p->ID,PublicationGate::META,true);
echo wp_json_encode(['id'=>$p->ID,'title'=>$p->post_title,'status'=>$p->post_status,'edit_url'=>get_edit_post_link($p->ID,'raw'),
    'test_only'=>true,'rules_version'=>$q['rulesVersion']??null,'quality_passed'=>PublicationGate::current($p->ID,$p),'findings'=>$q['findings']??[]],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
if(!PublicationGate::current($p->ID,$p))throw new RuntimeException('Draft retained, but quality gate did not approve');
if(PublicationGate::canPublish($p->ID,$p))throw new RuntimeException('Test publication block failed');
// Exercise the real WordPress save filter. It must retain the draft status without publication.
$guard=PublicationGate::guard(wp_slash(array_merge((array)$p,['post_status'=>'publish'])),['ID'=>$p->ID]);
if($guard['post_status']!=='draft')throw new RuntimeException('Test guard failed');
echo "PREFLIGHT PASSED; installed plugin untouched; test draft cannot publish.\n";
