<?php
// Offline regression checks: no accounts, votes or network requests on the live site.
define('ABSPATH',__DIR__.'/'); define('MINUTE_IN_SECONDS',60);
set_error_handler(static function($severity,$message) { throw new Exception($message); });
$rr_control=false;
$hooks=[]; $options=[]; $transients=[]; $scheduled=[];
function add_action($name,$callback,$priority=10,$args=1) { global $hooks; $hooks[$name][]=$callback; }
function add_filter(...$args) {}
function home_url($path='/') { return 'https://www.radiorubben.no'.$path; }
function wp_parse_url($url,$part=-1) { return parse_url($url,$part); }
function add_query_arg($name,$value,$url) { return $url.'?'.$name.'='.$value; }
function absint($n) { return abs((int)$n); }
function sanitize_text_field($s) { return trim(strip_tags($s)); }
function get_option($key,$fallback=false) { global $options; return $options[$key]??$fallback; }
function get_transient($key) { global $transients; return $transients[$key]??false; }
function set_transient($key,$value,$ttl) { global $transients; $transients[$key]=$value; }
function update_option($key,$value,$autoload=false) { global $options; $options[$key]=$value; }
function wp_next_scheduled($name,$args=[]) { global $scheduled; return isset($scheduled[$name.json_encode($args)]) ? 1 : false; }
function wp_schedule_single_event($time,$name,$args=[]) { global $scheduled; $scheduled[$name.json_encode($args)]=[$time,$name,$args]; }
function wp_safe_remote_get(...$args) { if (!empty($GLOBALS['background'])) return new WP_Error; throw new Exception('Public rendering must not fetch external data'); }
class WP_Error { function get_error_message() { return 'External source unavailable'; } }
function is_wp_error($value) { return $value instanceof WP_Error; }
class WP_User { public $ID=42; public $user_email='verified@example.org'; }
class VippsLogin {
    public $account=[null,null]; public $mapped=[];
    static function instance() { static $login; return $login??=$login=new self; }
    function get_vipps_account($id) { return $this->account; }
    function map_phone_to_user($phone,$sub,$user) { $this->mapped[]=[$phone,$sub,$user->ID]; }
}
function check($ok,$message) { if (!$ok) throw new Exception($message); echo 'PASS '.$message.PHP_EOL; }
require __DIR__.'/../rr-site-functions/inc/bremnes-direkte-test.php';
require __DIR__.'/../rr-site-functions/inc/bremnes-speaker-welcome.php';
$callback=$hooks['continue_with_vipps_after_create_wordpress_user'][0];
$user=new WP_User;
$session=['referer'=>home_url('/dagenskamp/').'?rr_match=8984418','userinfo'=>[
    'email_verified'=>true,'email'=>$user->user_email,'phone_number'=>'+4712345678','sub'=>'verified-sub']];
$callback($user,new ArrayObject($session));
check(VippsLogin::instance()->mapped===[['+4712345678','verified-sub',42]],'new user gets verified Vipps mapping before first login');
VippsLogin::instance()->mapped=[];
foreach (['unverified','wrong-email','external','unrelated','missing-sub'] as $case) {
    $bad=$session;
    if ($case==='unverified') $bad['userinfo']['email_verified']=false;
    if ($case==='wrong-email') $bad['userinfo']['email']='other@example.org';
    if ($case==='external') $bad['referer']='https://example.org/dagenskamp/';
    if ($case==='unrelated') $bad['referer']=home_url('/nyheter/');
    if ($case==='missing-sub') unset($bad['userinfo']['sub']);
    $callback($user,new ArrayObject($bad));
}
check(!VippsLogin::instance()->mapped,'invalid identity and unrelated sessions cannot create mappings');
VippsLogin::instance()->account=['phone','existing-sub']; $callback($user,new ArrayObject($session));
check(!VippsLogin::instance()->mapped,'existing mapping is preserved');
$match=['id'=>8984418,'home'=>'Bremnes','away'=>'Testlag','kickoff'=>'2026-10-10T15:00:00+02:00'];
$intro=rr_poll_public_welcome($match);
check(!$intro['verified_table'],'cold cache renders immediately without external requests');
check(count($scheduled)===1,'cold cache schedules one background refresh');
rr_poll_public_welcome($match);
check(count($scheduled)===1,'repeated requests do not schedule duplicate refreshes');
$options['rr_poll_public_intro_last_8984418']=['table'=>[],'scorer'=>[],'fetched'=>123];
check(rr_poll_public_welcome($match)['fetched']===123,'stale data retains original fetch timestamp');
$transients['rr_poll_public_intro_8984418']=['table'=>[],'scorer'=>[],'fetched'=>456];
check(rr_poll_public_welcome($match)['fetched']===456,'warm cache uses current snapshot');
rr_poll_schedule_rollover(); rr_poll_schedule_rollover();
check(count($scheduled)===2,'rollover is deferred and deduplicated');
$options['rr_poll_match_8984418']=$match; $background=true;
rr_poll_refresh_public_intro(8984418);
check($transients['rr_poll_public_intro_8984418']['fetched']===123,'background fetch failure preserves stale verified data');
