<?php
/** Public weekly archive; cumulative listing requires an explicit member opt-in. */
if (!defined('ABSPATH')) exit;
function rrwq_history_data() {
    $cached = get_transient('rrwq_public_history_v1');
    if (is_array($cached)) return $cached;
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like('rrwq_result_') . '%'
    ));
    $weeks = $totals = $members = [];
    $current = rrwq_week()['id'];
    foreach ($rows as $row) {
        if (!preg_match('/^rrwq_result_(\d{4}-\d{2}-\d{2})_(?:r\d+_)?([1-9]\d*)$/D', $row->option_name, $m)) continue;
        $week = $m[1]; $uid = (int)$m[2];
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $week, new DateTimeZone('Europe/Oslo'));
        if (!$date || $date->format('Y-m-d') !== $week || $date->format('N') !== '1' || $week > $current) continue;
        if ($row->option_name !== rrwq_key('result', $week, $uid)) continue;
        $result = maybe_unserialize($row->option_value);
        if (!is_array($result) || empty($result['public']) || get_option(rrwq_key('hide', $week, $uid))) continue;
        if (!array_key_exists($uid, $members)) $members[$uid] = (bool)get_userdata($uid);
        if (!$members[$uid] || !is_string($result['name'] ?? null) || trim($result['name']) === '') continue;
        if (!is_int($result['score'] ?? null) || $result['score'] < 0 || $result['score'] > 20
            || !is_int($result['seconds'] ?? null) || $result['seconds'] < 0) continue;
        $public = ['name'=>$result['name'], 'score'=>$result['score'], 'seconds'=>$result['seconds'], 'finished'=>(int)($result['finished'] ?? 0)];
        $weeks[$week][] = $public;
        if (!get_user_meta($uid, '_rrwq_overall_public', true)) continue;
        if (!isset($totals[$uid])) $totals[$uid] = ['name'=>$public['name'], 'score'=>0, 'seconds'=>0, 'weeks'=>0, 'latest'=>''];
        $totals[$uid]['score'] += $public['score'];
        $totals[$uid]['seconds'] += $public['seconds'];
        $totals[$uid]['weeks']++;
        if ($week >= $totals[$uid]['latest']) { $totals[$uid]['name'] = $public['name']; $totals[$uid]['latest'] = $week; }
    }
    krsort($weeks);
    $archive = [];
    foreach ($weeks as $week=>$results) {
        usort($results, function($a,$b) { return ($b['score'] <=> $a['score']) ?: ($a['seconds'] <=> $b['seconds']) ?: ($a['finished'] <=> $b['finished']); });
        $results = array_slice($results, 0, 10);
        foreach ($results as &$result) unset($result['finished']);
        unset($result);
        $archive[] = ['week'=>$week, 'label'=>(new DateTimeImmutable($week))->format('d.m.Y'), 'board'=>$results];
    }
    $totals = array_values($totals);
    usort($totals, function($a,$b) { return ($b['score'] <=> $a['score']) ?: ($a['seconds'] <=> $b['seconds']); });
    $totals = array_slice($totals, 0, 10);
    foreach ($totals as &$total) unset($total['latest']);
    unset($total);
    $data = ['weeks'=>$archive, 'overall'=>$totals];
    set_transient('rrwq_public_history_v1', $data, 120);
    return $data;
}
function rrwq_history_ajax() {
    nocache_headers();
    header('Cache-Control: private, no-store, max-age=0');
    if (!rr_quiz_enabled('weekly')) wp_send_json_error(['message'=>'Quizen er slått av.'], 503);
    $op = sanitize_key(wp_unslash($_POST['op'] ?? 'history'));
    $uid = get_current_user_id();
    if ($op === 'consent') {
        if (!$uid) wp_send_json_error(['message'=>'Logg inn for å velge deltakelse.'], 401);
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'rrwq_play')) wp_send_json_error(['message'=>'Last siden på nytt før du endrer valget.'], 403);
        $value = $_POST['include'] ?? '';
        if (!in_array($value, ['0','1'], true)) wp_send_json_error(['message'=>'Ugyldig valg.'], 400);
        update_user_meta($uid, '_rrwq_overall_public', $value === '1' ? 1 : 0);
        delete_transient('rrwq_public_history_v1');
    } elseif ($op !== 'history') wp_send_json_error(['message'=>'Ukjent handling.'], 400);
    wp_send_json_success(rrwq_history_data() + ['optedIn'=>$uid ? (bool)get_user_meta($uid, '_rrwq_overall_public', true) : false]);
}
add_action('wp_ajax_rrwq_history', 'rrwq_history_ajax');
add_action('wp_ajax_nopriv_rrwq_history', 'rrwq_history_ajax');
function rrwq_history_invalidate($option) {
    if (preg_match('/^rrwq_(?:result|hide|pack)_/', $option)) delete_transient('rrwq_public_history_v1');
}
add_action('added_option', 'rrwq_history_invalidate', 10, 1);
add_action('updated_option', 'rrwq_history_invalidate', 10, 1);
add_action('deleted_option', 'rrwq_history_invalidate', 10, 1);
add_action('deleted_user', function() { delete_transient('rrwq_public_history_v1'); });
