<?php
// Actual, unchanged Site Functions handlers with in-memory WordPress and HTTP stubs.
const ABSPATH=__DIR__.'/'; const ARRAY_A='ARRAY_A';
// Format-valid, deliberately fake credentials consumed only by the transport stub below.
const RRFR_FOTBALLDATA_CID='1';const RRFR_FOTBALLDATA_CWD='00000000-0000-0000-0000-000000000000';
$case=$argv[1]??'facts';$options=[];$calls=0;$admin=$case!=='unauthorized';$nonce=$case!=='nonce';
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
function wp_safe_remote_get($url,$args){
    if(parse_url($url,PHP_URL_HOST)==='api.fotballdata.no' && parse_url($url,PHP_URL_PATH)==='/v1/matches/900000002/people'){
        $GLOBALS['calls']++;
        $m=['MatchId'=>900000002,'HomeTeamId'=>30365,'AwayTeamId'=>999001,'HomeTeamClubId'=>827,'AwayTeamClubId'=>999003,
            'HomeTeamName'=>'TEST Bremnes','AwayTeamName'=>'TEST Motstander','MatchStartDate'=>'/Date(1792324800000-0000)/',
            'StadiumName'=>'TEST Stadion','TournamentName'=>'TEST Turnering','Cancelled'=>false,'Postponed'=>false,'Interrupted'=>false];
        foreach(['HomeTeamPlayers'=>'Hjemme','AwayTeamPlayers'=>'Borte'] as $field=>$side){
            $m[$field]=[];for($i=1;$i<=13;$i++)$m[$field][]=['PersonInfoHidden'=>false,'PlayerShirtNumber'=>$i,'PersonId'=>900000100+$i+($side==='Borte'?100:0),
                'FirstName'=>'TEST '.$side,'SurName'=>'Spiller'.$i,'Position'=>$i<=11?'Midtbane':'Reserve'];
        }
        return ['status'=>200,'body'=>json_encode($m)];
    }
    if(!in_array($url,['https://www.fotball.no/fotballdata/kamp/?fiksId=900000001','https://www.fotball.no/fotballdata/kamp/?fiksId=900000002'],true))throw new RuntimeException('Unexpected network intent');
    $GLOBALS['calls']++;return ['status'=>200,'body'=>$GLOBALS['html']];
}
class MemoryDB {public $options='test_options';function esc_like($x){return $x;}function prepare(...$args){return $args[0];}function get_results(...$args){return [];}function get_col(...$args){return [];}}
$wpdb=new MemoryDB;
$html=file_get_contents(__DIR__.'/synthetic-match.html');
$rr_poll_definitions_only=true;require __DIR__.'/site-functions/bremnes-poll-match.php';
$rr_poll_definitions_only=false;
$match=rr_poll_parse_nff($html,900000001);
check(!is_wp_error($match),'Synthetic match parses');
check(count($match['starters'])===11 && count($match['bench'])===2 && count($match['away_starters'])===11,'Both lineups parsed');
if($case==='facts') {
    require __DIR__.'/../source/wordpress/wp-content/plugins/radio-rubben-fotballrobot/includes/facts.php';
    require __DIR__.'/../source/wordpress/wp-content/plugins/radio-rubben-fotballrobot/includes/lineups.php';
    require __DIR__.'/site-functions/bremnes-poll-rules.php';
    require __DIR__.'/site-functions/bremnes-speaker-events.php';
    $f=RadioRubben\Fotballrobot\Facts::match($html,900000001);
    $l=RadioRubben\Fotballrobot\Lineups::parse($html,$f);
    check($f['score']===[2,1] && count($f['events'])===3,'Robot facts parse synthetic goals');
    check($l['starters']===$match['starters'] && $l['bench']===$match['bench'],'Poll and robot agree');
    $state=['opened'=>true,'running'=>true,'period'=>1,'elapsed'=>600,'started'=>time(),'entered'=>[],'events'=>[],'finished'=>false];
    $next=rr_speaker_event($match,$state,'sub',12,1);
    check(!is_wp_error($next),'Real substitution function');
    $eligible=rr_poll_allowed_players($match,$next);
    check(isset($eligible[1],$eligible[12]) && !isset($eligible[13]),'Substituted-off starter retained; unused bench excluded');
    check(rr_poll_parse_match_id('https://evil.test/?fiksId=900000001')===0,'Foreign match URL rejected');
    $away=str_replace('fiksId=30365','fiksId=999001',$html);
    check(is_wp_error(rr_poll_parse_nff($away,900000001)),'Characterize home-only import restriction');
    check(!rr_poll_is_closed(['period'=>2,'elapsed'=>5099,'running'=>false,'closed'=>false,'started'=>0]),'Open before 85 minutes');
    check(rr_poll_is_closed(['period'=>2,'elapsed'=>5100,'running'=>false,'closed'=>false,'started'=>0]),'Known limitation: closes at 85 minutes');
    check($calls===0,'No real transport');echo "OK: facts, both lineups, substitutions; limitations HOME_ONLY and CUTOFF_85 confirmed\n";exit;
}
$key='rr_poll_test_900000001_vipps_v3_75';
$state=['session'=>'synthetic','period'=>0,'elapsed'=>0,'started'=>0,'running'=>false,'opened'=>false,'closed'=>false,'entered'=>[],'entered_away'=>[],'eligible_players'=>[],'events'=>[],'finished'=>false,'poll_award'=>null];
if($case==='missing')$match['starters']=[];
if($case==='closed')$state['closed']=true;
if(in_array($case,['vote','duplicate','bench-vote'],true))$state=array_replace($state,['period'=>1,'opened'=>true,'running'=>true,'started'=>time()]);
$options=['rr_poll_selected_match'=>900000001,'rr_poll_match_900000001'=>$match,$key=>$state,'rr_poll_nff_900000001_source'=>'manual'];
$_GET=['rr_match'=>'900000001','rr_poll_api'=>'1'];
if($admin)$_GET['rr_poll_control']='1';
$_SERVER['REQUEST_METHOD']='POST';$_POST=['action'=>'start','nonce'=>'synthetic'];
if($case==='register')$_POST=['rr_select_match'=>'1','rr_match_input'=>'900000002','rr_match_nonce'=>'synthetic'];
if(in_array($case,['vote','duplicate','bench-vote'],true)){
    $voter=hash_hmac('sha256','wp-admin:9001',wp_salt('auth'));
    $token=hash_hmac('sha256',$voter.'|900000001|synthetic|synthetic-session',wp_salt('nonce'));
    $_POST=['action'=>'vote','player'=>$case==='bench-vote'?13:1,'token'=>$token];
    if($case==='duplicate')$options[$key.'_vote_synthetic_'.$voter]=1;
}
try { require __DIR__.'/site-functions/bremnes-poll-test.php'; throw new RuntimeException('No handler response'); }
catch(TestResponse $r) {
    $expected=match($case){'missing','closed','duplicate','bench-vote'=>409,'unauthorized','nonce'=>403,'register'=>303,default=>200};
    check($r->status===$expected,'Status '.$r->status.' expected '.$expected.' '.json_encode($r->payload));
    if($case==='start')check($options[$key]['period']===1&&$options[$key]['opened']&&count($options[$key]['eligible_players'])===11,'Real start opens voting');
    if(in_array($case,['missing','closed','unauthorized','nonce'],true))check($options[$key]===$state,'Rejected start preserves state');
    if($case==='register')check($options['rr_poll_selected_match']===900000002&&count($options['rr_poll_match_900000002']['starters'])===11&&$calls===1,'Real registration saves fixture');
    if($case==='vote')check($options[$key.'_vote_synthetic_'.$voter]===1,'One vote stored in memory');
    if($case!=='register')check($calls===0,'No HTTP needed');
}
echo "OK: actual poll handler $case with synthetic in-memory data\n";
