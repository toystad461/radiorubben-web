<?php
// Derived from recovery-0.10.8/qa/poll-flow.php (PR101). Actual handlers, synthetic memory stores.
// Separate process per case; HTTP, email and real WordPress data are unavailable.
const ABSPATH=__DIR__.'/'; const ARRAY_A='ARRAY_A';
// Format-valid, deliberately fake credentials consumed only by the transport stub below.
const RRFR_FOTBALLDATA_CID='1';const RRFR_FOTBALLDATA_CWD='00000000-0000-0000-0000-000000000000';
$case=$argv[1]??'vote';$options=[];$calls=0;$admin=false;$nonce=!in_array($case,['nonce','login-nonce'],true);
class WP_Error {function __construct(public $code,public $message){} function get_error_message(){return $this->message;}}
class TestResponse extends RuntimeException {function __construct(public $payload,public $status=200){parent::__construct('Test response');}}
function check($v,$why){if(!$v)throw new RuntimeException($why);}
function wp_parse_url($s,$c=-1){return parse_url($s,$c);}
function sanitize_text_field($s){return trim(strip_tags($s));}
function sanitize_key($s){return preg_replace('/[^a-z0-9_]/','',$s);}
function wp_unslash($s){return $s;}
function esc_url_raw($s){return $s;}
function esc_html($s){return htmlspecialchars($s);}
function esc_attr($s){return htmlspecialchars($s);}
function esc_url($s){return $s;}
function home_url($p){return 'https://example.test'.$p;}
function add_query_arg(...$args){return 'https://example.test/dagenskamp/?rr_match=900000001';}
function rr_poll_dashboard_url($url,$section=''){return $url;}
function get_option($k,$d=false){return $GLOBALS['options'][$k]??$d;}
function add_option($k,$v,...$unused){if(array_key_exists($k,$GLOBALS['options']))return false;$GLOBALS['options'][$k]=$v;return true;}
function update_option($k,$v,...$unused){$GLOBALS['options'][$k]=$v;return true;}
function delete_option($k){unset($GLOBALS['options'][$k]);}
function get_transient($k){return false;}
function set_transient(...$unused){}
function current_user_can(...$unused){return $GLOBALS['admin'];}
function is_user_logged_in(){return true;}
function get_current_user_id(){return 9001;}
function wp_salt($kind){return 'synthetic-local-only-'.$kind;}
function wp_get_session_token(){return 'synthetic-session';}
function wp_generate_uuid4(){return '00000000-0000-4000-8000-000000000001';}
function wp_create_nonce($key){return 'synthetic-nonce';}
function get_user_meta(...$unused){return 'TEST';}
function wp_verify_nonce(...$unused){return $GLOBALS['nonce'];}
function check_admin_referer(...$unused){if(!$GLOBALS['nonce'])throw new TestResponse([],403);}
function wp_send_json($data,$code=200){throw new TestResponse($data,$code);}
function wp_safe_redirect($url,$code){throw new TestResponse(['redirect'=>$url],$code);}
function wp_die($msg,$title='', $args=[]){throw new TestResponse(['message'=>$msg],$args['response']??500);}
function nocache_headers(){}
function absint($v){return abs((int)$v);}
function wp_date($f,$t){return date($f,$t);}
function is_wp_error($v){return $v instanceof WP_Error;}
function wp_remote_retrieve_response_code($r){return $r['status'];}
function wp_remote_retrieve_body($r){return $r['body'];}
function wp_safe_remote_get(...$args){throw new RuntimeException('Network forbidden');}
class MemoryDB {
 public $options='synthetic_options';
 function esc_like($x){return $x;}
 function prepare($sql,...$args){return [$sql,$args];}
 function get_results(...$args){return [];}
 function get_col(...$args){return [];}
 function get_var($query){return $GLOBALS['options'][$query[1][0]]??null;}
 function query($query){if($GLOBALS['case']==='database-failure')return false;$key=$query[1][0];if(!isset($GLOBALS['options'][$key]))$GLOBALS['options'][$key]=$query[1][1];return 1;}
}
function wp_cache_delete(...$args){}
function wp_json_encode($v){return json_encode($v);}
class VippsLogin {
 static function instance(){return new self;}
 function get_vipps_account($id){return in_array($GLOBALS['case'],['unauthorized','login-nonce','login-unavailable'],true)?['','']:['synthetic-phone','synthetic-sub'];}
}
$wpdb=new MemoryDB;
$match=['id'=>99999991,'home'=>'TEST Bremnes','away'=>'TEST United','home_id'=>30365,'team'=>'TEST','home_logo'=>'','away_logo'=>'','kickoff'=>'2027-01-01T12:00:00+01:00','date_label'=>'TEST','venue'=>'TEST','competition'=>'TEST','roster'=>[1=>'TEST Starter',12=>'TEST Reserve'],'starters'=>[1],'bench'=>[12],'away_roster'=>[],'away_starters'=>[],'away_bench'=>[],'fetched'=>0];
$key='rr_poll_test_99999991_vipps_v3_75';
$state=['session'=>'synthetic','period'=>1,'elapsed'=>0,'started'=>time(),'running'=>false,'opened'=>true,'closed'=>false,'entered'=>[],'entered_away'=>[],'eligible_players'=>[],'events'=>[],'finished'=>false,'poll_award'=>null];
if($case==='before-open')$state['opened']=false;
if(in_array($case,['closed','duplicate-closed'],true))$state['closed']=true;
if($case==='cutoff'){$state['period']=2;$state['elapsed']=5100;}
if($case==='substitute'){$state['entered'][12]=30;$state['eligible_players'][1]='TEST Starter';}
$options=['rr_poll_selected_match'=>99999991,'rr_poll_match_99999991'=>$match,$key=>$state,'rr_poll_nff_99999991_source'=>'manual'];
$_GET=['rr_match'=>'99999991','rr_poll_api'=>'1'];$_SERVER['REQUEST_METHOD']='POST';
$voter=hash_hmac('sha256','vipps:synthetic-sub',wp_salt('auth'));
$token=hash_hmac('sha256',$voter.'|99999991|synthetic|synthetic-session',wp_salt('nonce'));
$_POST=['action'=>'vote','player'=>in_array($case,['bench','substitute','duplicate','duplicate-closed'],true)?12:1,'token'=>$case==='nonce'?'invalid':$token];
if($case==='array-token')$_POST['token']=['invalid'];
$vote_key=$key.'_vote_synthetic_'.$voter;
if(in_array($case,['duplicate','duplicate-closed'],true))$options[$vote_key]=1;
if($case==='other-match')$options[str_replace('99999991','99999992',$vote_key)]=12;
if($case==='status')$_SERVER['REQUEST_METHOD']='GET';
if(str_starts_with($case,'login-')){unset($_GET['rr_poll_api']);$_POST=['rr_vipps_begin'=>'1','rr_vipps_nonce'=>'synthetic'];}
try {require __DIR__.'/../rr-site-functions/inc/bremnes-poll-test.php';throw new RuntimeException('No handler response');}
catch(TestResponse $r){
 if($case==='status'){check($r->payload['match_id']===99999991,'API match binding');check(strlen($r->payload['journey_version'])===16,'API code version');check($r->payload['authenticated'] && $r->payload['eligible'],'API session recognition');check($r->payload['login_nonce']==='synthetic-nonce','Fresh login nonce');check(!$r->payload['voted'],'No inherited vote');echo "PASS actual synthetic handler: $case\n";exit;}
 if(str_starts_with($case,'login-')){check($r->status===($case==='login-existing'?303:400),'Login response');check($case==='login-existing'?str_ends_with($r->payload['redirect'],'#poll-live'):str_contains($r->payload['message'],'Tilbake til avstemmingen'),'Safe ballot recovery');echo "PASS actual synthetic handler: $case\n";exit;}
 $expected=match($case){'before-open','closed','cutoff','bench'=>409,'unauthorized','nonce','array-token'=>403,'database-failure'=>503,default=>200};
 check($r->status===$expected,'Unexpected status for '.$case.': '.$r->status.' '.json_encode($r->payload));
 if($expected===200){check($r->payload['voted']===true,'Server confirms saved ballot');check((int)$options[$vote_key]===($case==='substitute'?12:1),'Original ballot preserved');check((int)$options[$key.'_entrant_synthetic_'.$voter]===9001,'Entrant registered once');}
 else check(!isset($options[$vote_key]),'Rejected request must not store a ballot');
 check($options[$key]===$state,'Vote handler preserves clock, candidate and award state');
}
echo "PASS actual synthetic handler: $case\n";
