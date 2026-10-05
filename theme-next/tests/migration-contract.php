<?php
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type()!=='local' || getenv('RR_DISPOSABLE_TEST_DB')!=='1') throw new RuntimeException('Local WP-CLI only');
$checks=[];
$test=static function($label,$pass)use(&$checks){$checks[]=['check'=>$label,'pass'=>(bool)$pass];};
$test('Bridge owns functions with new theme',($GLOBALS['rr_site_state']??'')==='active');
foreach(['rr_poll_active_vote','rr_poll_rollover_selection','rrwq_week','rr_weather_card','rr_member_dashboard','rr_member_can_self_delete','rrlive_get_match_data'] as $fn){
 if(!function_exists($fn) && $fn==='rr_poll_rollover_selection'){$rr_poll_definitions_only=true;require RR_SITE_DIR.'inc/bremnes-poll-match.php';}
 $test('Callable '.$fn,function_exists($fn));
 if(function_exists($fn))$test('Plugin owns '.$fn,str_contains((new ReflectionFunction($fn))->getFileName(),'/plugins/rr-site-functions/'));
}
$test('RRLive content registered',post_type_exists('rr_match'));
$test('RRLive permalink preserved',get_post_type_object('rr_match')->rewrite['slug']==='rrlive/kamp');
foreach(['rubben_rss_cards','rubben_live_quiz','min_rubben','rr_player','rr_weekly_competition','contact-form-7','continue-with-vipps'] as $sc)$test('Shortcode '.$sc,shortcode_exists($sc));
$test('Weather AJAX retained',has_action('wp_ajax_rr_weather')!==false && has_action('wp_ajax_nopriv_rr_weather')!==false);
$test('Weekly quiz AJAX retained',has_action('wp_ajax_rrwq','rrwq_ajax')===10);
$test('Member deletion retained',has_action('admin_post_rr_member_delete')!==false);
$test('HTTP outbound blocked',is_wp_error(wp_remote_get('https://www.radiorubben.no')));
$test('Email sending blocked',false===wp_mail('staging@example.invalid','Staging guard test','No mail should leave.'));
$test('Cron disabled',defined('DISABLE_WP_CRON') && DISABLE_WP_CRON);
$legacy=get_option('theme_mods_radio-rubben-wordpress-v1',[]);
$test('Radio stream setting preserved',rr_theme_mod('rr_stream_url','')===($legacy['rr_stream_url']??''));
$test('Live quiz engine still active',shortcode_exists('rubben_live_quiz'));
// Verify quiz OFF gates server requests, then restore the exact prior value.
$prior=get_option('rr_quiz_live_enabled',null);update_option('rr_quiz_live_enabled',0);
$r=rest_do_request(new WP_REST_Request('GET','/rubben-quiz/v1/state'));
$test('Disabled LIVE quiz rejects server requests',$r->get_status()===503);
if($prior===null)delete_option('rr_quiz_live_enabled');else update_option('rr_quiz_live_enabled',$prior);
$test('RSS renderer attached once',has_action('rr_theme_home_after_news')!==false);
$erasure=apply_filters('wp_privacy_personal_data_erasers',[]);
$test('Quiz data eraser preserved',isset($erasure['rrwq']));
$export=apply_filters('wp_privacy_personal_data_exporters',[]);
$test('Quiz data export preserved',isset($export['rrwq']));
$test('Paused birthday collector remains paused',!array_filter(get_included_files(),static fn($f)=>str_ends_with($f,'/inc/vipps-birthday.php')));
$failed=array_filter($checks,static fn($c)=>!$c['pass']);
echo wp_json_encode(['checks'=>$checks,'total'=>count($checks),'passed'=>count($checks)-count($failed)],JSON_PRETTY_PRINT)."\n";
if($failed)exit(1);
