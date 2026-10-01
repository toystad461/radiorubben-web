<?php
// Run without WordPress: exercise the existing callback with Vipps session data.
define('ABSPATH', __DIR__);
$hooks = [];
function add_filter($name, $callback, $priority = 10, $accepted = 1) {
    $GLOBALS['hooks'][$name][$priority][] = [$callback, $accepted];
}
function add_action($name, $callback, $priority = 10, $accepted = 1) { add_filter($name, $callback, $priority, $accepted); }
function apply_filters($name, $value, ...$args) {
    $callbacks = $GLOBALS['hooks'][$name] ?? []; ksort($callbacks);
    foreach ($callbacks as $group) foreach ($group as [$callback, $accepted]) {
        $value = $callback(...array_slice([$value, ...$args], 0, $accepted));
    }
    return $value;
}
function do_action($name, ...$args) {
    $callbacks = $GLOBALS['hooks'][$name] ?? []; ksort($callbacks);
    foreach ($callbacks as $group) foreach ($group as [$callback, $accepted]) $callback(...array_slice($args, 0, $accepted));
}
function home_url($path) { return 'https://www.radiorubben.no' . $path; }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function sanitize_text_field($value) { return strip_tags($value); }
function add_query_arg($name, $value, $url) { return $url . '?' . http_build_query([$name => $value]); }
class WP_User {}
require __DIR__ . '/../../wordpress/wp-content/themes/radio-rubben-wordpress-v1/inc/quiz-controls.php';
$count = 0;
function expect_same($actual, $expected, $label) {
    if ($actual !== $expected) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    $GLOBALS['count']++;
}
$quiz = home_url('/quiz/#rr-weekly');
$profile = home_url('/wp-admin/profile.php');
expect_same(rr_quiz_vipps_return(new ArrayObject(['referer' => $quiz])), $quiz, 'Vipps ArrayAccess session with explicit return');
expect_same(rr_quiz_vipps_return(['referer' => home_url('/quiz/')]), $quiz, 'Existing sessions remain supported');
expect_same(rr_quiz_vipps_return(['referer' => false]), '', 'Missing referrer reproduces profile fallback');
expect_same(rr_quiz_vipps_return(['referer' => home_url('/min-side/')]), '', 'Profile entry is unaffected');
expect_same(rr_quiz_vipps_return(['referer' => home_url('/dagenskamp/')]), '', 'Voting entry is unaffected');
expect_same(rr_quiz_vipps_return(['referer' => 'https://attacker.invalid/quiz/']), '', 'External target rejected');
expect_same(rr_quiz_vipps_return(['referer' => ['bad']]), '', 'Malformed target rejected');
expect_same(rr_quiz_vipps_return(new stdClass()), '', 'Invalid session ignored');
expect_same(rr_quiz_vipps_return(['referer' => home_url('/quiz/?redirect_to=https://attacker.invalid')]), $quiz, 'Arbitrary redirect parameter is not forwarded');
expect_same(rr_quiz_vipps_return(['referer' => home_url('/quiz/?wpvibe_preview=test-token')]), home_url('/quiz/?wpvibe_preview=test-token#rr-weekly'), 'Preview retained');
$user = new WP_User();
do_action('continue_with_vipps_before_wordpress_login_redirect', $user, new ArrayObject(['referer' => $quiz]));
expect_same(apply_filters('login_redirect', $profile, $profile, $user), $quiz, 'Successful login returns to quiz');
expect_same(apply_filters('login_redirect', $profile, $profile, new stdClass()), $profile, 'Failed login not treated as success');
expect_same(apply_filters('continue_with_vipps_wordpress_confirm_redirect', $profile, 123, new ArrayObject(['referer' => $quiz])), $quiz, 'Confirmed account returns to quiz');
echo "$count quiz return checks passed.\n";
