<?php
/** Read-only production verification; no content, roles, credentials or cron changes. */
if (!defined('WP_CLI') || !WP_CLI) {throw new RuntimeException('WP-CLI required');}
$assert=static function($ok,$message){if(!$ok)throw new RuntimeException($message);};
$assert(home_url()==='https://www.radiorubben.no','Wrong WordPress target');
$plugin='radio-rubben-fotballrobot/radio-rubben-fotballrobot.php';
require_once ABSPATH.'wp-admin/includes/plugin.php';
$assert(is_plugin_active($plugin),'Plugin not active');
$version=get_plugin_data(WP_PLUGIN_DIR.'/'.$plugin,false,false)['Version'];
$assert($version==='0.10.9','Unexpected installed plugin version');
$class='RadioRubben\\Fotballrobot\\SourceCollection';
$assert(class_exists($class),'SourceCollection not loaded');
$admin=get_user_by('login','toystad');
$assert($admin&&user_can($admin,'manage_options')&&user_can($admin,'edit_posts'),'Existing administrator unavailable');
wp_set_current_user($admin->ID);
$response=rest_do_request(new WP_REST_Request('GET','/rr-fotballrobot/v1/source-collection'));
$assert($response->get_status()===200,'Installed REST route unavailable');
$data=$response->get_data();
$assert(($data['version']??'')==='1.0.0','Unexpected collection protocol');
$assert(has_action('admin_menu',[$class,'menu'])!==false,'Admin menu not registered');
ob_start();$class::page();$html=ob_get_clean();
foreach(['Spilleroppfølging – kildeinnsamling','Start én innsamlingsrunde','Pause kildeinnsamlingen','Europe/Oslo','Kildeinnboks og spillerprofiler','Artikler til godkjenning'] as $label)$assert(str_contains($html,$label),'Missing admin page element');
$state=$class::status();
echo 'RRFR_SOURCE_INSTALLED '.wp_json_encode(['plugin_version'=>$version,'collection_version'=>$class::VERSION,'admin_url'=>$class::url(),'state'=>$state['state'],'reachable'=>$state['reachable'],'profile_count'=>count(\RadioRubben\Fotballrobot\Players::ids()),'rendered_admin'=>true,'browser_verified'=>false,'content_written'=>false],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
