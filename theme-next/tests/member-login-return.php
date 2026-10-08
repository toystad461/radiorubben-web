<?php
/** Isolated return routing: no real credentials, sessions, users or quiz attempts. */
define('ABSPATH', __DIR__);
$actions = $filters = [];
function add_action($name, $callback, $priority = 10, $accepted = 1) { $GLOBALS['actions'][$name][] = $callback; }
function add_filter($name, $callback, $priority = 10, $accepted = 1) { $GLOBALS['filters'][$name][] = $callback; }
function home_url($path) { return 'https://www.radiorubben.no' . $path; }
function wp_parse_url($url) { return parse_url($url); }
function sanitize_text_field($value) { return strip_tags($value); }
function add_query_arg($key, $value, $url) { return $url . '?' . rawurlencode($key) . '=' . rawurlencode($value); }
class WP_User {}
require __DIR__ . '/../rr-site-functions/inc/quiz-controls.php';
function same($expected, $actual, $label) {
    if ($expected !== $actual) { throw new RuntimeException($label . ': ' . var_export($actual, true)); }
}
$cases = [
    ['Min side array', ['referer' => home_url('/min-side/')], home_url('/min-side/')],
    ['Min side Vipps ArrayAccess', new ArrayObject(['referer' => home_url('/min-side/')]), home_url('/min-side/')],
    ['Missing trailing slash', ['referer' => home_url('/min-side')], home_url('/min-side/')],
    ['Uppercase host', ['referer' => 'https://WWW.RADIORUBBEN.NO/min-side/'], home_url('/min-side/')],
    ['Ignored hostile query', ['referer' => home_url('/min-side/?redirect_to=https://evil.example/')], home_url('/min-side/')],
    ['Preserve preview token', ['referer' => home_url('/min-side/?wpvibe_preview=test-token')], home_url('/min-side/?wpvibe_preview=test-token')],
    ['Quiz route preserved', ['referer' => home_url('/quiz/')], home_url('/quiz/#rr-weekly')],
    ['Quiz ArrayAccess preserved', new ArrayObject(['referer' => home_url('/quiz/?wpvibe_preview=token')]), home_url('/quiz/?wpvibe_preview=token#rr-weekly')],
    ['Unrelated public page', ['referer' => home_url('/kontakt/')], ''],
    ['Admin login unchanged', ['referer' => home_url('/wp-admin/')], ''],
    ['Unrelated path prefix', ['referer' => home_url('/min-side/other/')], ''],
    ['External host', ['referer' => 'https://evil.example/min-side/'], ''],
    ['Host suffix spoof', ['referer' => 'https://www.radiorubben.no.evil.example/min-side/'], ''],
    ['Relative origin', ['referer' => '/min-side/'], ''],
    ['Missing origin', [], ''],
    ['Invalid origin type', ['referer' => ['bad']], ''],
    ['Invalid session', new stdClass(), ''],
];
$original = home_url('/wp-admin/profile.php');
foreach ($cases as [$label, $session, $expected]) {
    same($expected, rr_quiz_vipps_return($session), $label);
    same($expected !== '', rr_quiz_vipps_origin($session), $label . ' origin');
    $filters['login_redirect'] = [];
    foreach ($actions['continue_with_vipps_before_wordpress_login_redirect'] as $callback) { $callback(new WP_User(), $session); }
    $redirect = $original;
    foreach ($filters['login_redirect'] as $callback) { $redirect = $callback($redirect, '', new WP_User()); }
    same($expected ?: $original, $redirect, $label . ' login hook');
    foreach ($filters['login_redirect'] as $callback) { same($original, $callback($original, '', new stdClass()), $label . ' failed login'); }
    $redirect = $original;
    foreach ($filters['continue_with_vipps_wordpress_confirm_redirect'] as $callback) { $redirect = $callback($redirect, 123, $session); }
    same($expected ?: $original, $redirect, $label . ' confirmation hook');
}
echo 'PASS: ' . count($cases) . " member/quiz return cases, login and confirmation hooks; unrelated/failed logins unchanged\n";
