<?php
// Synthetic identities and temporary tables only. No WordPress or Vipps requests.
define('ABSPATH',__DIR__);
$hooks=[];
function add_action($name,$fn,$priority=10,$argc=1) { $GLOBALS['hooks'][$name][]=$fn; }
function add_filter($name,$fn,$priority=10,$argc=1) { add_action($name,$fn,$priority,$argc); }
function home_url($path='/') { return 'https://www.radiorubben.no'.$path; }
function wp_parse_url($url,$part=-1) { return parse_url($url,$part); }
function absint($v) { return abs((int)$v); }
function sanitize_text_field($v) { return trim(strip_tags($v)); }
function wp_json_encode($v) { return json_encode($v); }
function wp_cache_delete(...$args) { $GLOBALS['invalidated'][]=$args; }
function add_query_arg($key,$value,$url) {
    $p=parse_url($url);parse_str($p['query']??'',$q);$q[$key]=$value;
    return $p['scheme'].'://'.$p['host'].($p['path']??'/').'?'.http_build_query($q).(isset($p['fragment'])?'#'.$p['fragment']:'');
}
class WP_User {}
function verify($ok,$message) { if (!$ok) throw new RuntimeException($message); echo 'PASS '.$message.PHP_EOL; }
require __DIR__.'/../rr-site-functions/inc/bremnes-direkte-test.php';
$flow=str_repeat('a',32);
verify(rr_poll_canonical_vote_url('radiorubben.no','/dagenskamp/?rr_match=99999991')===home_url('/dagenskamp/').'?rr_match=99999991','bare-domain entry canonicalized before cookies and API requests');
verify(rr_poll_canonical_vote_url('www.radiorubben.no','/dagenskamp/')==='' && rr_poll_canonical_vote_url('external.example','/dagenskamp/')==='','no redirect loop or arbitrary Host redirect');
verify(rr_poll_canonical_vote_url('radiorubben.no','/quiz/')==='' && rr_poll_canonical_vote_url('radiorubben.no','/dagenskamp/?wpvibe_preview=private')==='','other pages and preview tokens are not moved');
verify(rr_poll_canonical_vote_url('radiorubben.no','/dagenskamp/?rr_match=bad')==='','invalid match is not silently replaced by current match');
$target=rr_poll_vipps_return(new ArrayObject(['referer'=>home_url('/dagenskamp/').'?rr_match=99999991&rr_flow='.$flow]));
verify($target===home_url('/dagenskamp/').'?rr_match=99999991&rr_flow='.$flow.'#poll-live','ArrayAccess Vipps return keeps match and journey, lands at ballot');
verify(rr_poll_vipps_return(['referer'=>home_url('/dagenskamp/').'?rr_match=99999992'])===home_url('/dagenskamp/').'?rr_match=99999992#poll-live','next match does not inherit old match');
verify(rr_poll_vipps_return(['referer'=>home_url('/dashboard/hendelser/').'?rr_bremnes_test=1&rr_vote=1'])===home_url('/dashboard/'),'dashboard navigation preserved');
foreach (['https://external.example/dagenskamp/','https://www.radiorubben.no.evil.example/dagenskamp/',home_url('/quiz/'),null] as $bad) {
    verify(rr_poll_vipps_return(['referer'=>$bad])==='','unrelated/invalid return not overridden');
}
verify(rr_poll_flow_id(['a'])==='' && rr_poll_flow_id('token-or-email')==='','diagnostic input rejects non-allowlisted data');
$error=$hooks['continue_with_vipps_error_login_redirect'][0];
verify($error('/default','access_denied',new ArrayObject(['referer'=>$target]))===home_url('/dagenskamp/').'?rr_match=99999991&rr_flow='.$flow.'&rr_login=retry#poll-live','cancelled login returns to same ballot with retry notice');
verify($error('/default','missing_state',[])==='/default','missing trusted session leaves provider recovery intact');
verify(strlen(rr_poll_journey_version())===16,'code fingerprint is available without user data');

class PollTestDB {
    public $options; public $fail=false; public $dialect; public $pdo;
    function __construct() {
        $dsn=getenv('RR_POLL_TEST_MYSQL_DSN');
        if ($dsn) {
            if ($dsn!=='mysql:host=127.0.0.1;port=3306;dbname=rr_poll_test') throw new RuntimeException('Only isolated CI database allowed');
            $this->pdo=new PDO($dsn,'root','');$this->dialect='mysql';
        } else { $this->pdo=new PDO('sqlite::memory:');$this->dialect='sqlite'; }
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $this->options='rr_poll_synthetic_'.bin2hex(random_bytes(6));
        $this->pdo->exec("CREATE TABLE `{$this->options}` (option_name VARCHAR(191) PRIMARY KEY, option_value TEXT, autoload VARCHAR(20))");
    }
    function prepare($sql,...$args) {
        foreach ($args as $arg) $sql=preg_replace('/%s/',str_replace('$','\\$',$this->pdo->quote((string)$arg)),$sql,1);
        return $sql;
    }
    function query($sql) {
        if ($this->fail) return false;
        if ($this->dialect==='sqlite') $sql=str_replace('ON DUPLICATE KEY UPDATE option_name=VALUES(option_name)','ON CONFLICT(option_name) DO NOTHING',$sql);
        return $this->pdo->exec($sql);
    }
    function get_var($sql) { $v=$this->pdo->query($sql)->fetchColumn(); return $v===false?null:$v; }
}
$wpdb=new PollTestDB;
try {
    $key='rr_poll_test_99999991_vipps_v3_75_vote_synthetic_voter';
    // Both requests observe a miss before either writes: the failing old race.
    verify(rr_poll_saved_vote($key)===null && rr_poll_saved_vote($key)===null,'both requests can observe no ballot');
    verify(rr_poll_insert_once($key,7) && rr_poll_insert_once($key,12),'duplicate inserts are handled');
    verify(rr_poll_saved_vote($key)===7,'first vote survives second choice after concurrent cache miss');
    verify(rr_poll_insert_once($key,7) && rr_poll_saved_vote($key)===7,'retry after lost response confirms original vote');
    verify((int)$wpdb->get_var("SELECT COUNT(*) FROM `{$wpdb->options}`")===1,'one stored vote only');
    verify(rr_poll_saved_vote(str_replace('99999991','99999992',$key))===null,'new match has no previous ballot');
    verify(rr_poll_saved_vote($key.'_new_session')===null,'new voting session has no previous ballot');
    if ($wpdb->dialect==='mysql') {
        // Reproduce the documented WordPress add_option SQL after two cache misses.
        $old=$key.'_old_race';
        foreach ([7,12] as $choice) $wpdb->query($wpdb->prepare("INSERT INTO `{$wpdb->options}` (option_name,option_value,autoload) VALUES (%s,%s,'no') ON DUPLICATE KEY UPDATE option_name=VALUES(option_name),option_value=VALUES(option_value),autoload=VALUES(autoload)",$old,(string)$choice));
        verify(rr_poll_saved_vote($old)===12,'old WordPress upsert reproduces overwritten first choice (MySQL only)');
    }
    $wpdb->fail=true;verify(!rr_poll_insert_once($key.'_failed',7),'database failure is not reported as a recorded vote');
    verify(in_array([$key,'options'],$invalidated,true) && in_array(['notoptions','options'],$invalidated,true),'positive and negative option caches invalidated');
    echo 'Storage engine: '.$wpdb->dialect.($wpdb->dialect==='sqlite'?' (MySQL conflict clause translated; MySQL CI required)':' (unmodified production SQL)').PHP_EOL;
} finally { $wpdb->pdo->exec("DROP TABLE `{$wpdb->options}`"); }
