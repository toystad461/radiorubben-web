<?php
defined('ABSPATH') || exit;

// A random journey reference is diagnostic only; never an identity or permission.
function rr_poll_flow_id($value) {
    return is_string($value) && preg_match('/^[a-f0-9]{32}$/D',$value) ? $value : '';
}
function rr_poll_canonical_vote_url($request_host,$request_uri) {
    $home=wp_parse_url(home_url('/'));
    $canonical=strtolower($home['host']??'');
    $alias=str_starts_with($canonical,'www.')?substr($canonical,4):'www.'.$canonical;
    if (!is_string($request_host) || strtolower($request_host)!==$alias || !is_string($request_uri)) return '';
    $path=wp_parse_url($request_uri,PHP_URL_PATH);
    if (rtrim($path??'','/')!==rtrim(wp_parse_url(home_url('/dagenskamp/'),PHP_URL_PATH),'/')) return '';
    parse_str(wp_parse_url($request_uri,PHP_URL_QUERY)??'', $query);
    if (isset($query['rr_poll_api']) || isset($query['rr_poll_control'])) return '';
    $target=home_url('/dagenskamp/');
    foreach (['rr_match','rr_archive_page'] as $key) {
        if (isset($query[$key]) && is_string($query[$key]) && preg_match('/^[1-9][0-9]{0,8}$/D',$query[$key])) $target=add_query_arg($key,$query[$key],$target);
        elseif (isset($query[$key])) return ''; // Preserve the existing invalid-input response.
    }
    $flow=rr_poll_flow_id($query['rr_flow']??'');
    if ($flow) $target=add_query_arg('rr_flow',$flow,$target);
    if (($query['rr_login']??'')==='retry') $target=add_query_arg('rr_login','retry',$target);
    // Do not move a preview/access token between hosts.
    if (isset($query['wpvibe_preview'])) return '';
    return $target;
}
function rr_poll_journey_version() {
    static $version;
    if (!$version) {
        $parts=[];
        foreach (['bremnes-poll-journey.php','bremnes-direkte-test.php','bremnes-poll-match.php','bremnes-poll-test.php','bremnes-poll-performance.php'] as $file) {
            $parts[]=hash_file('sha256',__DIR__.'/'.$file);
        }
        $version=substr(hash('sha256',implode('|',$parts)),0,16);
    }
    return $version;
}
function rr_poll_journey_log($event,$match_id,$flow='') {
    // Opt in during a time-limited rehearsal; use the host's existing private log/retention.
    if (!defined('RR_POLL_DIAGNOSTICS') || RR_POLL_DIAGNOSTICS!==true) return;
    if (!in_array($event,['login_start','login_return','login_error','vote_recorded','vote_existing','vote_error'],true)) return;
    error_log('RR_POLL '.wp_json_encode(['event'=>$event,'match'=>(int)$match_id,
        'version'=>rr_poll_journey_version(),'flow'=>rr_poll_flow_id($flow)]));
}
function rr_poll_journey_log_url($event,$url) {
    parse_str(wp_parse_url($url,PHP_URL_QUERY)??'', $args);
    rr_poll_journey_log($event,(int)($args['rr_match']??0),$args['rr_flow']??'');
}
function rr_poll_login_problem($url,$message) {
    wp_die(esc_html($message).'<p><a href="'.esc_url($url.'#poll-live').'">Tilbake til avstemmingen</a></p>',
        'Vipps-innlogging',['response'=>400]);
}

// add_option() can upsert after two concurrent cache misses. Keep a collision
// a no-op, preserving the existing match/session/voter key and stored ballot.
function rr_poll_insert_once($key,$value) {
    global $wpdb;
    $result=$wpdb->query($wpdb->prepare(
        "INSERT INTO `{$wpdb->options}` (option_name,option_value,autoload) VALUES (%s,%s,'no') ON DUPLICATE KEY UPDATE option_name=VALUES(option_name)",
        $key,(string)$value));
    wp_cache_delete($key,'options');
    wp_cache_delete('notoptions','options');
    return $result!==false;
}
function rr_poll_saved_vote($key) {
    global $wpdb;
    // Bypass request-local negative caches after a concurrent insert.
    $value=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM `{$wpdb->options}` WHERE option_name=%s",$key));
    return $value===null ? null : (int)$value;
}
