<?php
/** Plugin Name: Radio Rubben — Fotballrobot test guard (separate from original 0.10.8) */
namespace RadioRubben\FootballTest;
if (!defined('ABSPATH')) exit;
function rrfr_test_environment_valid(): bool {
    return gethostname()==='vps-5363103'
        && realpath(ABSPATH)==='/srv/radiorubben-test/wordpress'
        && defined('DB_NAME') && DB_NAME==='rr_test_wp'
        && defined('DB_USER') && DB_USER==='rr-wordpress'
        && defined('DB_HOST') && DB_HOST==='localhost:/run/mysqld/mysqld.sock'
        && defined('WP_HOME') && WP_HOME==='https://5.249.252.78/wordpress'
        && defined('WP_SITEURL') && WP_SITEURL===WP_HOME
        && wp_get_environment_type()==='staging'
        && defined('DISABLE_WP_CRON') && DISABLE_WP_CRON===true
        && defined('WP_HTTP_BLOCK_EXTERNAL') && WP_HTTP_BLOCK_EXTERNAL===true;
}
function rrfr_test_require_environment(): void {
    if (!rrfr_test_environment_valid()) {
        http_response_code(503);exit('Fotballrobot testmiljø: miljøkontrollen feilet.');
    }
}
rrfr_test_require_environment();
// No fixture HTTP exception is installed: all real HTTP remains blocked.
add_filter('pre_http_request',static fn()=>new \WP_Error('rrfr_test_offline','Nettverk er sperret i test.'),PHP_INT_MAX);
add_filter('pre_wp_mail',static fn()=>false,PHP_INT_MAX);
add_filter('pre_schedule_event',static fn()=>false,PHP_INT_MAX);
add_filter('pre_reschedule_event',static fn()=>false,PHP_INT_MAX);
add_filter('pre_spawn_cron',static fn()=>false,PHP_INT_MAX);
add_filter('pre_option_cron',static fn()=>[],PHP_INT_MAX);
add_filter('pre_update_option_cron',static fn($new,$old)=>$old,PHP_INT_MAX,2);
add_filter('wp_insert_post_data',static function($data) {
    if (($data['post_type']??'post')==='post' && in_array($data['post_status']??'',['publish','future'],true)) {
        $data['post_status']='draft';
    }
    return $data;
},PHP_INT_MAX);
add_filter('rest_pre_insert_post',static function($post) {
    return in_array($post->post_status??'',['publish','future'],true)
        ? new \WP_Error('rrfr_test_no_publish','Publisering er sperret i testpakken.',['status'=>403]) : $post;
},PHP_INT_MAX);
add_filter('wp_is_application_passwords_available','__return_false',PHP_INT_MAX);
