<?php
// Execute only via authenticated, real WP-CLI; all private snapshots stay outside the webroot.
use RadioRubben\Fotballrobot\ClubCoverage;
use RadioRubben\Fotballrobot\ClubAutomation;
use RadioRubben\Fotballrobot\Writer;
use RadioRubben\Fotballrobot\PublicationGate;
use RadioRubben\Fotballrobot\Newsroom;
$mode=$args[0]??'';$file=$args[1]??'';$stage=$args[2]??'';
if(!defined('WP_CLI')||!WP_CLI||!current_user_can('manage_options')||!current_user_can('publish_posts'))throw new RuntimeException('Authenticated administrator required.');
function pr30_assert($ok,$message){if(!$ok)throw new RuntimeException($message);}
function pr30_content(){global $wpdb;return hash('sha256',wp_json_encode($wpdb->get_results("SELECT ID,post_status,post_title,post_content,post_excerpt FROM {$wpdb->posts} WHERE post_type='post' ORDER BY ID",ARRAY_A)));}
function pr30_options(){global $wpdb;$out=[];foreach($wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'rrfr\\_club\\_%'")as$k)$out[$k]=get_option($k);return $out;}
if($mode==='before') {
    pr30_assert(get_option('home')==='https://www.radiorubben.no','Unexpected site.');
    pr30_assert(Writer::key()!=='','Current AI configuration unavailable.');
    pr30_assert(!get_option('rrfr_club_enabled_at',0),'Club automation already active; reconcile first.');
    require_once $stage.'/runtime/wp-content/plugins/radio-rubben-fotballrobot/includes/fotballdata.php';
    require_once $stage.'/runtime/wp-content/plugins/radio-rubben-fotballrobot/includes/club-coverage.php';
    $feed=ClubCoverage::collect();
    $snapshot=['content'=>pr30_content(),'club'=>pr30_options(),'match_jobs'=>get_option('rrfr_match_jobs'),
        'teams'=>count($feed['teams']),'matches'=>count($feed['matches'])];
    pr30_assert(file_put_contents($file,wp_json_encode($snapshot,JSON_THROW_ON_ERROR))!==false,'Could not save private state.');
    echo wp_json_encode(['api'=>'verified','eligible_teams'=>$snapshot['teams'],'eligible_matches'=>$snapshot['matches']])."\nPR30_API_PREFLIGHT_OK\n";return;
}
$s=json_decode(file_get_contents($file),true,64,JSON_THROW_ON_ERROR);
if($mode==='rollback') {
    // Remove only this feature's schedules/options. Other cron and editorial state remain untouched.
    foreach(_get_cron_array()?:[]as$timestamp=>$hooks)foreach($hooks as$hook=>$events)if(str_starts_with($hook,'rrfr_club_'))foreach($events as$event)wp_unschedule_event($timestamp,$hook,$event['args']);
    foreach(array_keys(pr30_options())as$k)if(!array_key_exists($k,$s['club']))delete_option($k);
    foreach($s['club']as$k=>$v)update_option($k,$v,false);
    echo "PR30_OPTIONS_RESTORED\n";return;
}
pr30_assert($mode==='after','Unknown postflight mode.');
pr30_assert(pr30_content()===$s['content'],'Article content changed during release; review before continuing.');
pr30_assert(get_option('rrfr_match_jobs')===$s['match_jobs'],'Senior automation changed.');
foreach(['rrfr_club_tick','rrfr_club_match','rrfr_club_weekly']as$hook)pr30_assert(has_action($hook)!==false,'Missing club callback.');
pr30_assert(PublicationGate::managed(0,['_rrfr_club_key'=>'week:readback']),'Weekly publication guard missing.');
pr30_assert(PublicationGate::guard(['post_type'=>'post','post_status'=>'publish'],['meta_input'=>['_rrfr_club_key'=>'week:readback']])['post_status']==='draft','New weekly article bypasses gate.');
$news=Newsroom::response();pr30_assert(is_array($news),'Newsroom readback failed.');
ClubAutomation::enable();
$weekly=wp_next_scheduled('rrfr_club_weekly');$tick=wp_next_scheduled('rrfr_club_tick');
pr30_assert(ClubAutomation::active()&&$tick&&$weekly,'New schedules missing.');
pr30_assert($weekly===ClubCoverage::nextSunday(time()),'Wrong weekly schedule.');
pr30_assert(get_option('rrfr_match_jobs')===$s['match_jobs']&&pr30_content()===$s['content'],'Existing content or automation changed.');
echo wp_json_encode(['version'=>'0.10.5','enabled'=>true,'owner'=>get_option('rrfr_club_owner'),
    'weekly_local'=>wp_date('Y-m-d H:i:s T',$weekly,new DateTimeZone('Europe/Oslo')),
    'next_week'=>ClubCoverage::weekForSunday($weekly),'result_wait_seconds'=>ClubAutomation::RESULT_WAIT,
    'eligible_teams'=>$s['teams'],'eligible_matches'=>$s['matches'],'newsroom_readback'=>true,'articles_unchanged'=>true],JSON_UNESCAPED_SLASHES)."\nPR30_RUNTIME_OK\n";
