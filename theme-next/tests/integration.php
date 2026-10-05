<?php
// Only run against an explicitly disposable local installation.
if ( ! defined('WP_CLI') || ! WP_CLI || wp_get_environment_type() !== 'local' || getenv('RR_DISPOSABLE_TEST_DB') !== '1' ) {
    throw new RuntimeException('Requires WP-CLI, WP_ENVIRONMENT_TYPE=local and RR_DISPOSABLE_TEST_DB=1.');
}

global $checks, $failures; $checks=0;$failures=[];
function check($condition,$message){global $checks,$failures;$checks++;if(!$condition)$failures[]=$message;}
function render_rr($name,$args=[]){ob_start();rr_theme_component($name,$args);return ob_get_clean();}
check(wp_get_theme()->get('Version')==='2.0.0-rc.1','Theme version');
foreach(['title-tag','post-thumbnails','automatic-feed-links','responsive-embeds','align-wide','editor-styles'] as $support)check(current_theme_supports($support),'Theme support '.$support);
check(count(wp_get_theme()->get_page_templates())>=6,'Assignable templates registered');
foreach(['article-intro','sport-hub','match-shell','feed-source','sponsor','program','workspace'] as $p){$pattern=WP_Block_Patterns_Registry::get_instance()->get_registered('radio-rubben-next/'.$p);check(!empty($pattern['content']),'Pattern '.$p);if($pattern)check(count(parse_blocks($pattern['content']))>0,'Pattern parsing '.$p);}
$profiles=rr_theme_journalists();check(isset($profiles['rr-fotball'],$profiles['rr-lokal']),'Two digital identities');
$post=wp_insert_post(['post_title'=>'QA Security','post_status'=>'draft','post_content'=>'QA','post_type'=>'post']);
update_post_meta($post,'_rr_journalist_id','rr-fotball');check(rr_theme_byline($post)['id']==='rr-fotball','Explicit journalist ID');
update_post_meta($post,'_rr_journalist_id','rr-new-role');check(rr_theme_byline($post)['type']==='unknown','Unknown identity not mislabelled as human');
update_post_meta($post,'_rr_journalist_id','');update_post_meta($post,'_rrfr_ai_match',8985491);check(rr_theme_journalist_id($post)==='rr-fotball','Legacy robot marker');
delete_post_meta($post,'_rrfr_ai_match');check(rr_theme_byline($post)['type']==='human','Ordinary authors preserved');
check(rr_editorial_sanitize_id('rr-lokal')==='rr-lokal','Identity syntax accepted');check(rr_editorial_sanitize_id('../../evil')==='','Invalid identity rejected');check(rr_editorial_sanitize_id(['rr-lokal'])==='','Array identity rejected');
foreach(['match-card'=>['home'=>'<script>alert(1)</script>','away'=>'A','url'=>'javascript:alert(1)','status'=>'live','score'=>['home'=>0,'away'=>0]],'feed-card'=>['title'=>'<img src=x onerror=alert(1)>','url'=>'javascript:alert(1)'],'sponsor'=>['name'=>'<script>alert(1)</script>','url'=>'javascript:alert(1)'],'program-card'=>['title'=>'<script>alert(1)</script>','url'=>'javascript:alert(1)']] as $component=>$data){$html=render_rr($component,$data);check(!str_contains($html,'<script>'),'Escaping '.$component);check(!str_contains($html,'javascript:'),'URL escaping '.$component);}
check(str_contains(render_rr('match-card',['home'=>'Bremnes','away'=>'Viggo','status'=>'finished','score'=>['home'=>0,'away'=>0]]),'0–0'),'Score zero preserved');
check(!str_contains(render_rr('match-card',['home'=>'Bremnes','away'=>'Viggo','status'=>'scheduled']),'0–0'),'Missing score not invented');
check(render_rr('../../wp-config')==='','Component path allowlist');
check(render_rr('match-card')==='','Absent match omits card');
$data=rr_theme_rrlive_data($post);check(isset($data['rr_competition'],$data['rr_radio_url']),'Empty RRLive normalized');
update_post_meta($post,'rr_events',[['type'=>'goal','player'=>'A'],null,'bad']);$data=rr_theme_rrlive_data($post);check(count($data['rr_events'])===1&&isset($data['rr_events'][0]['note']),'Malformed event rows filtered');
$before=get_post($post)->to_array();switch_theme('twentytwentyfive');switch_theme('radio-rubben-next');check(get_post($post)->to_array()===$before,'Theme switching does not alter posts');
// REST permissions, persistence and sanitization against real WordPress core.
$author=wp_insert_user(['user_login'=>'qa-author-'.time(),'user_pass'=>wp_generate_password(32),'role'=>'author']);
$other=wp_insert_user(['user_login'=>'qa-other-'.time(),'user_pass'=>wp_generate_password(32),'role'=>'author']);
wp_update_post(['ID'=>$post,'post_author'=>$author]);
foreach([0=>$post,$other=>$post] as $uid=>$pid){wp_set_current_user($uid);$request=new WP_REST_Request('POST','/wp/v2/posts/'.$pid);$request->set_param('meta',['_rr_journalist_id'=>'rr-lokal']);$response=rest_do_request($request);check($response->get_status()>=400,'Unauthorized REST denied uid '.$uid);}
wp_set_current_user($author);$request=new WP_REST_Request('POST','/wp/v2/posts/'.$post);$request->set_param('meta',['_rr_journalist_id'=>'rr-lokal','_rr_source_name'=>'<b>Kommunen</b>','_rr_source_url'=>'javascript:alert(1)']);$response=rest_do_request($request);check($response->get_status()===200,'Authorized author can update own metadata');check(get_post_meta($post,'_rr_journalist_id',true)==='rr-lokal','REST ID persisted');check(get_post_meta($post,'_rr_source_name',true)==='Kommunen','REST text sanitized');check(get_post_meta($post,'_rr_source_url',true)==='','REST unsafe URL sanitized');check(get_post_status($post)==='draft','Metadata write does not publish');
// Legacy settings fallback is read-only and an explicitly empty new value wins.
$legacy=get_option('theme_mods_radio-rubben-wordpress-v1',null);update_option('theme_mods_radio-rubben-wordpress-v1',['rr_now_title'=>'Legacy title']);remove_theme_mod('rr_now_title');check(rr_theme_mod('rr_now_title')==='Legacy title','Read-only settings fallback');set_theme_mod('rr_now_title','');check(rr_theme_mod('rr_now_title')==='','Explicit empty override respected');remove_theme_mod('rr_now_title');if($legacy===null)delete_option('theme_mods_radio-rubben-wordpress-v1');else update_option('theme_mods_radio-rubben-wordpress-v1',$legacy);
wp_set_current_user(1);wp_delete_post($post,true);require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($author);wp_delete_user($other);
echo wp_json_encode(['checks'=>$checks,'failures'=>$failures,'wordpress'=>get_bloginfo('version'),'php'=>PHP_VERSION],JSON_PRETTY_PRINT)."\n";
if($failures)exit(1);
