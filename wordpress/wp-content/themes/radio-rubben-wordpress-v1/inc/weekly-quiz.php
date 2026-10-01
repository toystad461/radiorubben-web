<?php
/** Radio Rubben weekly competition. Server is authoritative for time and scoring. */
if (!defined('ABSPATH')) exit;
function rrwq_week($timestamp = null) {
    $now = (new DateTimeImmutable('@' . ($timestamp ?? time())))->setTimezone(new DateTimeZone('Europe/Oslo'));
    $start = $now->modify('monday this week')->setTime(0, 0);
    return ['id'=>$start->format('Y-m-d'), 'end'=>$start->modify('+1 week')->getTimestamp(), 'label'=>$start->format('d.m.Y')];
}
function rrwq_pack($week) {
    static $refresh = null;
    if ($refresh === null) $refresh = require __DIR__ . '/weekly-quiz-refresh.php';
    if (isset($refresh[$week])) return $refresh[$week];
    $saved = get_option('rrwq_pack_' . $week, false);
    if ($saved !== false) return $saved;
    $seeds = require __DIR__ . '/weekly-quiz-seeds.php';
    return $seeds[$week] ?? null;
}
function rrwq_revision($week) { return max(1, (int)(rrwq_pack($week)['revision'] ?? 1)); }
function rrwq_key($kind, $week, $uid) {
    $revision = rrwq_revision($week);
    return 'rrwq_' . $kind . '_' . $week . '_' . ($revision > 1 ? 'r' . $revision . '_' : '') . (int)$uid;
}
function rrwq_board_key($week, $revision = null) {
    $revision = $revision ?? rrwq_revision($week);
    return 'rrwq_board_' . $week . ($revision > 1 ? '_r' . $revision : '');
}
function rrwq_error($message, $status = 400) { wp_send_json_error(['message'=>$message], $status); }
function rrwq_board($week) {
    $cache = get_transient(rrwq_board_key($week));
    if ($cache !== false) return $cache;
    global $wpdb;
    $prefix = substr(rrwq_key('result', $week, 0), 0, -1);
    $rows = $wpdb->get_results($wpdb->prepare("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like($prefix) . '%'));
    $board = [];
    foreach ($rows as $row) {
        $suffix = substr($row->option_name, strlen($prefix));
        if (!ctype_digit($suffix)) continue;
        $uid = (int)$suffix;
        $r = maybe_unserialize($row->option_value);
        if (!is_array($r) || empty($r['public']) || get_option(rrwq_key('hide', $week, $uid)) || !get_userdata($uid)) continue;
        $board[] = ['name'=>$r['name'], 'score'=>$r['score'], 'seconds'=>$r['seconds'], 'finished'=>$r['finished']];
    }
    usort($board, function($a, $b) { return ($b['score'] <=> $a['score']) ?: (($a['seconds'] <=> $b['seconds']) ?: ($a['finished'] <=> $b['finished'])); });
    $board = array_slice($board, 0, 10);
    foreach ($board as &$row) unset($row['finished']);
    unset($row);
    set_transient(rrwq_board_key($week), $board, 60);
    return $board;
}
function rrwq_state($week, $pack, $uid) {
    $verified = $uid && !get_user_meta($uid, '_rrm_pending', true);
    $data = ['week'=>$week['id'], 'label'=>$week['label'], 'ends'=>$week['end'], 'now'=>time(), 'ready'=>(bool)$pack, 'loggedIn'=>(bool)$uid, 'verified'=>(bool)$verified, 'board'=>rrwq_board($week['id']), 'nonce'=>$uid ? wp_create_nonce('rrwq_play') : ''];
    $data['revision'] = rrwq_revision($week['id']);
    $data['storageKey'] = $uid ? substr(wp_hash($uid . ':' . $week['id'] . ':' . $data['revision']), 0, 20) : '';
    if (!$pack) return $data;
    $data['title'] = $pack['title'];
    $attempt = $uid ? get_option(rrwq_key('attempt', $week['id'], $uid)) : false;
    $result = $uid ? get_option(rrwq_key('result', $week['id'], $uid)) : false;
    if ($result) {
        $data['result'] = ['score'=>$result['score'], 'seconds'=>$result['seconds'], 'public'=>!empty($result['public']) && !get_option(rrwq_key('hide', $week['id'], $uid)), 'answers'=>$result['answers']];
        $data['review'] = $pack['questions'];
    } elseif ($attempt && $verified) {
        $data['started'] = $attempt['started'];
        $data['questions'] = array_map(function($q) { return ['q'=>$q['q'], 'a'=>$q['a']]; }, $pack['questions']);
    }
    return $data;
}
function rrwq_ajax() {
    nocache_headers();
    if (!rr_quiz_enabled('weekly')) rrwq_error('Ukens quiz er slått av for øyeblikket. Kom gjerne tilbake senere.', 503);
    header('Cache-Control: private, no-store, max-age=0');
    $op = sanitize_key(wp_unslash($_POST['op'] ?? 'state'));
    $week = rrwq_week(); $uid = get_current_user_id(); $pack = rrwq_pack($week['id']);
    if ($op === 'state') wp_send_json_success(rrwq_state($week, $pack, $uid));
    if (!$uid) rrwq_error('Logg inn via Min side for å spille.', 401);
    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'rrwq_play')) rrwq_error('Økten må oppdateres. Last siden på nytt; klokken fortsetter.', 403);
    if (get_user_meta($uid, '_rrm_pending', true)) rrwq_error('Bekreft e-posten din før du spiller.', 403);
    if (sanitize_text_field(wp_unslash($_POST['week'] ?? '')) !== $week['id']) rrwq_error('En ny quizuke har startet. Last siden på nytt.', 409);
    if (!$pack) rrwq_error('Ukens spørsmål er ikke klare ennå.', 409);
    if ((string)($_POST['revision'] ?? '1') !== (string)rrwq_revision($week['id'])) rrwq_error('Quizen er nullstilt med nye spørsmål. Last siden på nytt og start den nye runden.', 409);
    $attempt_key = rrwq_key('attempt', $week['id'], $uid);
    $result_key = rrwq_key('result', $week['id'], $uid);
    if ($op === 'start') {
        // Freeze this week's pack before any question is served. add_option is atomic.
        add_option('rrwq_pack_' . $week['id'], $pack, '', false);
        $pack = rrwq_pack($week['id']);
        $public = ($_POST['public'] ?? '') === '1';
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $name = preg_split('/\s+/u', trim($name))[0] ?? '';
        if ($public && (!preg_match('/^[\p{L}\p{M}][\p{L}\p{M}\x{2019}\x{0027}-]{0,39}$/u', $name))) rrwq_error('Skriv bare fornavnet ditt for å delta på topplisten.');
        add_option($attempt_key, ['started'=>time(), 'public'=>$public, 'name'=>$public ? $name : ''], '', false);
    } elseif ($op === 'submit') {
        $attempt = get_option($attempt_key);
        if (!$attempt) rrwq_error('Trykk Start quizen først.', 409);
        if (!get_option($result_key)) {
            $answers = json_decode(wp_unslash($_POST['answers'] ?? ''), true);
            if (!is_array($answers) || array_keys($answers) !== range(0,19)) rrwq_error('Svar på alle 20 spørsmål før du leverer.');
            $score = 0;
            foreach ($answers as $i=>$answer) {
                if (!is_int($answer) || $answer < 0 || $answer > 3) rrwq_error('Et svar mangler eller er ugyldig.');
                if ($answer === $pack['questions'][$i]['correct']) $score++;
            }
            $finished = time();
            if ($finished >= $week['end']) rrwq_error('Uken er avsluttet. Last siden på nytt.', 409);
            add_option($result_key, ['score'=>$score, 'seconds'=>max(0,$finished-$attempt['started']), 'finished'=>$finished, 'public'=>$attempt['public'], 'name'=>$attempt['name'], 'answers'=>$answers], '', false);
            delete_transient(rrwq_board_key($week['id']));
        }
    } elseif ($op === 'hide') {
        add_option(rrwq_key('hide', $week['id'], $uid), 1, '', false);
        delete_transient(rrwq_board_key($week['id']));
    } else rrwq_error('Ukjent handling.');
    wp_send_json_success(rrwq_state($week, $pack, $uid));
}
add_action('wp_ajax_rrwq', 'rrwq_ajax');
add_action('wp_ajax_nopriv_rrwq', 'rrwq_ajax');

// Content-only publishing endpoint: administrators can prepare a future weekly pack.
add_action('rest_api_init', function() {
    register_rest_route('rubben-weekly/v1', '/pack', [
        'methods'=>'POST', 'permission_callback'=>function() { return current_user_can('manage_options'); },
        'callback'=>function(WP_REST_Request $request) {
            $p = $request->get_json_params(); $id = $p['week'] ?? '';
            if (!is_string($id) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $id)) return new WP_Error('date','Ugyldig uke.', ['status'=>400]);
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $id, new DateTimeZone('Europe/Oslo'));
            if (!$date || $date->format('Y-m-d') !== $id || $date->format('N') !== '1' || $id <= rrwq_week()['id']) return new WP_Error('date','Velg en framtidig mandag.', ['status'=>400]);
            if (!is_string($p['title'] ?? null) || !is_array($p['questions'] ?? null) || count($p['questions']) !== 20) return new WP_Error('pack','Pakken må ha tittel og 20 spørsmål.', ['status'=>400]);
            $qs=[]; $seen=[];
            foreach (array_values($p['questions']) as $q) {
                if (!is_array($q) || !is_string($q['q'] ?? null) || !is_string($q['why'] ?? null) || !is_string($q['url'] ?? null) || !is_array($q['a'] ?? null) || count($q['a']) !== 4 || !is_int($q['correct'] ?? null) || $q['correct'] < 0 || $q['correct'] > 3) return new WP_Error('question','Ugyldig spørsmål.', ['status'=>400]);
                foreach ($q['a'] as $a) if (!is_string($a) || trim($a) === '') return new WP_Error('answer','Ugyldig svaralternativ.', ['status'=>400]);
                $clean = ['q'=>sanitize_text_field($q['q']), 'a'=>array_values(array_map('sanitize_text_field',$q['a'])), 'correct'=>$q['correct'], 'why'=>sanitize_text_field($q['why']), 'url'=>esc_url_raw($q['url'],['https'])];
                if (!$clean['q'] || !$clean['why'] || !$clean['url'] || count(array_unique($clean['a'])) !== 4 || isset($seen[$clean['q']])) return new WP_Error('question','Spørsmål, alternativer og kilde må være gyldige og unike.', ['status'=>400]);
                $seen[$clean['q']] = true; $qs[]=$clean;
            }
            $value = ['title'=>sanitize_text_field($p['title']), 'questions'=>$qs];
            if (!add_option('rrwq_pack_'.$id,$value,'',false)) return new WP_Error('exists','Uken har allerede en lagret pakke. Den er ikke overskrevet.', ['status'=>409]);
            return ['week'=>$id,'questions'=>20,'saved'=>true];
        }
    ]);
    register_rest_route('rubben-weekly/v1', '/packs', [
        'methods'=>'GET','permission_callback'=>function(){return current_user_can('manage_options');},
        'callback'=>function(){ $out=[]; $start=new DateTimeImmutable(rrwq_week()['id'],new DateTimeZone('Europe/Oslo')); for($i=0;$i<5;$i++){ $id=$start->modify('+'.$i.' weeks')->format('Y-m-d'); $p=rrwq_pack($id); $out[]=['week'=>$id,'ready'=>(bool)$p,'title'=>$p['title']??null,'questions'=>$p['questions']??[]]; } return $out; }
    ]);
});
// Privacy tools remove participation data when a WordPress account is deleted.
function rrwq_erase_user($uid) {
    global $wpdb;
    foreach (['attempt','result','hide'] as $kind) {
        $names=$wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like('rrwq_'.$kind.'_').'%' . $wpdb->esc_like('_'.(int)$uid)));
        foreach($names as $name) { if(preg_match('/^rrwq_(?:attempt|result|hide)_(\d{4}-\d{2}-\d{2})_(?:r(\d+)_)?'.(int)$uid.'$/D',$name,$m)){ delete_option($name); delete_transient(rrwq_board_key($m[1], isset($m[2]) && $m[2] !== '' ? (int)$m[2] : 1)); } }
    }
}
add_action('delete_user','rrwq_erase_user');
add_filter('wp_privacy_personal_data_erasers',function($erasers){$erasers['rrwq']=['eraser_friendly_name'=>'Ukens Rubben-quiz','callback'=>function($email,$page=1){$u=get_user_by('email',$email); if($u)rrwq_erase_user($u->ID);return ['items_removed'=>(bool)$u,'items_retained'=>false,'messages'=>[],'done'=>true];}];return $erasers;});
add_filter('wp_privacy_personal_data_exporters',function($exporters){$exporters['rrwq']=['exporter_friendly_name'=>'Ukens Rubben-quiz','callback'=>function($email,$page=1){global $wpdb;$u=get_user_by('email',$email);$data=[];if($u){$names=$wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",$wpdb->esc_like('rrwq_').'%' . $wpdb->esc_like('_'.$u->ID)));foreach($names as $name){if(preg_match('/^rrwq_(attempt|result|hide)_(\d{4}-\d{2}-\d{2})_(?:r\d+_)?'.(int)$u->ID.'$/D',$name,$m))$data[]=['group_id'=>'rrwq','group_label'=>'Ukens Rubben-quiz','item_id'=>$name,'data'=>[['name'=>'Uke','value'=>$m[2]],['name'=>'Spilldata','value'=>wp_json_encode(get_option($name),JSON_UNESCAPED_UNICODE)]]];}}return ['data'=>$data,'done'=>true];}];return $exporters;});

function rrwq_shortcode() {
    if (!rr_quiz_enabled('weekly')) return '';
    $base = get_template_directory_uri();
    wp_enqueue_style('rrwq',$base.'/assets/css/weekly-quiz.css',[],RR_ONE_VERSION);
    wp_enqueue_script('rrwq',$base.'/assets/js/weekly-quiz.js',[],RR_ONE_VERSION.'-quiz-return-1',true);
    $ajax = admin_url('admin-ajax.php');
    if (isset($_GET['wpvibe_preview'])) $ajax=add_query_arg('wpvibe_preview',sanitize_text_field(wp_unslash($_GET['wpvibe_preview'])),$ajax);
    $return_to = home_url('/quiz/');
    if (isset($_GET['wpvibe_preview']) && is_string($_GET['wpvibe_preview'])) {
        $return_to = add_query_arg('wpvibe_preview',sanitize_text_field(wp_unslash($_GET['wpvibe_preview'])),$return_to);
    }
    $return_to .= '#rr-weekly';
    $login_url = wp_login_url($return_to);
    wp_localize_script('rrwq','rrwqConfig',['ajax'=>$ajax,'login'=>$login_url,'returnTo'=>$return_to,'week'=>rrwq_week()['id']]);
    $vipps_login = shortcode_exists('continue-with-vipps') ? do_shortcode('[continue-with-vipps language="no"]') : '';
    $login_template = '<template id="rrq-login-template"><p>Logg inn med Vipps for å spille. Du kommer tilbake hit etter innlogging. Klokken starter først når du trykker «Start quizen».</p>' . $vipps_login . '<p><a href="' . esc_url($login_url) . '">Logg inn med brukernavn og passord</a></p></template>';
    return '<nav class="rrq-modes" aria-label="Velg quiz"><a href="#rr-weekly"><small>SPILL NÅR DU VIL</small><strong>Ukens Rubben-quiz</strong><span>20 spørsmål · nivå ca. 5/10 · tidtaking</span></a><a href="#rr-live-quiz"><small>SAMMEN PÅ LUFTA</small><strong>LIVE-quizen</strong><span>Delta når studio åpner en runde.</span></a></nav><section id="rr-weekly" class="rrq" aria-labelledby="rrq-title"><p class="rr-eyebrow">20 SPØRSMÅL · NIVÅ CA. 5/10</p><h2 id="rrq-title">Ukens Rubben-quiz</h2><p>Ny quiz mandag kl. 00.00 norsk tid. Flest riktige svar vinner; ved lik poengsum avgjør kortest tid.</p><p>Én tellende runde per konto i ukens gjeldende quiz. Klokken starter når du trykker «Start quizen» og fortsetter hvis du lukker siden. Lever før ukebyttet.</p><div class="rrq-status" role="status">Laster ukens quiz …</div><div class="rrq-play"></div>' . $login_template . '<aside class="rrq-board" aria-label="Ukens topp ti"></aside><noscript>Du må slå på JavaScript for å spille.</noscript></section>';
}
add_shortcode('rr_weekly_competition','rrwq_shortcode');
// Upgrade the old self-contained weekly HTML block while preserving LIVE content.
add_filter('the_content',function($content){
    if (!is_page(496) || !in_the_loop() || !is_main_query()) return $content;
    return preg_replace_callback('/<!-- wp:html -->.*?<!-- \/wp:html -->/s',function($m){return strpos($m[0],'id="rr-weekly"')!==false ? rrwq_shortcode() : $m[0];},$content);
},8);
