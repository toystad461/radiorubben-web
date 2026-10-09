<?php
defined('ABSPATH') || exit;
// Run before either the public match page or dashboard chooses its match.
add_action('template_redirect',static function() {
    if (($_SERVER['REQUEST_METHOD']??'GET')!=='GET' || isset($_GET['rr_poll_api'])) return;
    $path=trim((string)wp_parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH),'/');
    if (!in_array($path,['dagenskamp','kamp','dashboard','dashboard_test'],true) && strpos($path,'dashboard/')!==0) return;
    $rr_poll_definitions_only=true;
    require __DIR__.'/bremnes-poll-match.php';
    nocache_headers();
    rr_poll_schedule_rollover();
},-20);
