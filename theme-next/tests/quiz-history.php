<?php
define('ABSPATH', __DIR__);
function add_action(...$args) {}
$cache = false; $uid = 0; $meta = []; $hidden = [];
function get_transient($key) { return $GLOBALS['cache']; }
function set_transient($key,$data,$ttl) { $GLOBALS['cache'] = $data; }
function delete_transient($key) { $GLOBALS['cache'] = false; }
function get_userdata($uid) { return $uid !== 5; }
function get_user_meta($uid,$key,$single) { return $GLOBALS['meta'][$uid] ?? false; }
function update_user_meta($uid,$key,$value) { $GLOBALS['meta'][$uid] = $value; }
function get_option($key) { return $GLOBALS['hidden'][$key] ?? false; }
function maybe_unserialize($value) { return $value; }
function rrwq_week() { return ['id'=>'2026-10-05']; }
function rrwq_key($kind,$week,$uid) { return 'rrwq_'.$kind.'_'.$week.'_'.($week==='2026-09-28'?'r2_':'').$uid; }
function rr_quiz_enabled($kind) { return true; }
function nocache_headers() {}
function sanitize_key($value) { return $value; }
function wp_unslash($value) { return $value; }
function sanitize_text_field($value) { return $value; }
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_verify_nonce($value,$action) { return $value === 'valid'; }
class JsonResponse extends RuntimeException { public $data; public $status; public function __construct($data,$status) { $this->data=$data; $this->status=$status; } }
function wp_send_json_error($data,$status) { throw new JsonResponse($data,$status); }
function wp_send_json_success($data) { throw new JsonResponse($data,200); }
class FakeDatabase {
    public $options = 'wp_options'; public $rows = [];
    function prepare($sql,$value) { return $sql; }
    function esc_like($value) { return $value; }
    function get_results($sql) { return $this->rows; }
}
$wpdb = new FakeDatabase();
require __DIR__ . '/../rr-site-functions/inc/weekly-quiz-history.php';
function expect($condition,$message) { if (!$condition) throw new RuntimeException($message); }
function row($week,$uid,$score,$seconds,$public=true,$revision='') {
    return (object)['option_name'=>'rrwq_result_'.$week.'_'.$revision.$uid,'option_value'=>['name'=>$uid===1||$uid===8?'Ada':'Spiller '.$uid,'score'=>$score,'seconds'=>$seconds,'finished'=>$uid,'public'=>$public]];
}
$meta = [1=>1,3=>1,4=>1,5=>1,6=>1,7=>1,8=>1];
$hidden['rrwq_hide_2026-10-05_4'] = true;
$wpdb->rows = [row('2026-10-05',1,10,20),row('2026-09-28',1,20,40,true,'r2_'),row('2026-10-05',2,20,10),row('2026-10-05',3,20,1,false),row('2026-10-05',4,20,1),row('2026-10-05',5,20,1),row('2026-09-28',6,20,1),row('2026-09-28',6,5,50,true,'r2_'),row('2026-10-12',7,20,1),row('2026-10-05',8,7,10),row('2026-10-06',9,20,1),row('2026-99-99',9,20,1)];
// Ada's current result is outside the weekly top ten, but still counts in her total.
for ($i=10;$i<21;$i++) $wpdb->rows[] = row('2026-10-05',$i,20,30);
$data = rrwq_history_data();
expect(count($data['weeks'])===2,'Only two valid, public weeks');
expect($data['weeks'][0]['week']==='2026-10-05','Newest week first');
expect(count($data['weeks'][0]['board'])===10,'Weekly top ten limit');
expect($data['overall'][0]===['name'=>'Ada','score'=>30,'seconds'=>60,'weeks'=>2],'Totals include results outside weekly top ten');
expect(count($data['overall'])===3,'Private, hidden, deleted, old revisions, future and non-consenting players excluded');
expect($data['overall'][1]['name']==='Ada' && $data['overall'][1]['score']===7,'Identical names remain separate accounts');
expect($data['overall'][2]['score']===5,'Only current revision counts');
expect(!str_contains(json_encode($data),'finished') && !str_contains(json_encode($data),'uid'),'No identity keys or completion timestamps exposed');
$uid=2; $_POST=['op'=>'consent','include'=>'1','nonce'=>'invalid'];
try { rrwq_history_ajax(); } catch (JsonResponse $r) { expect($r->status===403,'Consent needs valid nonce'); }
expect(empty($meta[2]),'Failed consent changes nothing');
$_POST['nonce']='valid';
try { rrwq_history_ajax(); } catch (JsonResponse $r) { expect($r->status===200 && $r->data['optedIn']===true,'Explicit opt-in accepted'); }
expect($meta[2]===1,'Consent stored only for current member');
$_POST['include']='0';
try { rrwq_history_ajax(); } catch (JsonResponse $r) { expect($r->status===200 && $r->data['optedIn']===false,'Revocation accepted'); expect(count($r->data['overall'])===3,'Revocation removes total immediately'); }
$uid=0; $_POST['include']='1';
try { rrwq_history_ajax(); } catch (JsonResponse $r) { expect($r->status===401,'Anonymous consent forbidden'); }
$cache=['stale']; rrwq_history_invalidate('rrwq_hide_2026-10-05_1'); expect($cache===false,'Hiding invalidates archive');
$cache=['stale']; rrwq_history_invalidate('unrelated_option'); expect($cache===['stale'],'Unrelated options ignored');
echo "PASS: archive, cumulative totals, revision/date validation, private/hidden/deleted exclusions, consent, revocation and cache invalidation\n";
