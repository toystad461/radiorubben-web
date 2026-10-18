<?php
namespace RadioRubben\FootballTest {
    function gethostname(){return ($GLOBALS['case']==='wrong-host')?'production.example':'vps-5363103';}
    function realpath($p){return ($GLOBALS['case']==='wrong-root')?'/production':'/srv/radiorubben-test/wordpress';}
}
namespace {
    $case=$argv[1]??'valid';$hooks=[];
    define('ABSPATH','/srv/radiorubben-test/wordpress/');
    define('DB_NAME',$case==='wrong-db'?'production':'rr_test_wp');
    define('DB_USER','rr-wordpress');define('DB_HOST','localhost:/run/mysqld/mysqld.sock');
    define('WP_HOME',$case==='wrong-url'?'https://www.radiorubben.no':'https://5.249.252.78/wordpress');
    define('WP_SITEURL',WP_HOME);define('DISABLE_WP_CRON',$case!=='cron-on');define('WP_HTTP_BLOCK_EXTERNAL',true);
    function wp_get_environment_type(){return $GLOBALS['case']==='production'?'production':'staging';}
    function add_filter($name,$fn,...$unused){$GLOBALS['hooks'][$name]=$fn;}
    class WP_Error {function __construct(public $code,public $message,public $data=[]){} }
    require dirname(__DIR__).'/guard.php';
    function check($b,$why){if(!$b)throw new \RuntimeException($why);}
    check($case==='valid','Invalid environment unexpectedly continued');
    foreach(['https://www.radiorubben.no','https://api.openai.com','http://127.0.0.1','https://5.249.252.78/wordpress'] as $url)
        check($hooks['pre_http_request'](false,[],$url) instanceof WP_Error,'HTTP must be blocked');
    check($hooks['pre_wp_mail']()===false,'Mail blocked');
    foreach(['pre_schedule_event','pre_reschedule_event','pre_spawn_cron'] as $key)check($hooks[$key]()===false,'Cron blocked');
    check($hooks['pre_option_cron']()===[],'No runnable cron');
    check($hooks['pre_update_option_cron'](['new'],['old'])===['old'],'Existing cron preserved');
    foreach(['publish','future'] as $status){
        check($hooks['wp_insert_post_data'](['post_type'=>'post','post_status'=>$status])['post_status']==='draft','No article publishing');
        check($hooks['rest_pre_insert_post']((object)['post_status'=>$status]) instanceof WP_Error,'REST publication rejected');
    }
    check($hooks['wp_insert_post_data'](['post_type'=>'post','post_status'=>'draft'])['post_status']==='draft','Draft remains draft');
    echo "OK: test guard rejects HTTP/mail/cron/publishing and preserves drafts\n";
}
