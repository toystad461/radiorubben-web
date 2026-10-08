<?php
/** Render the real classic homepage with a plugin callback; no database/network. */
$events = array();
$enabled = true;
function get_header() {}
function get_footer() {}
function get_option($key) { return 'page'; }
function post_password_required() { return false; }
function rr_theme_mod($key, $default) { return $default; }
function rr_home_universes_enabled() { return false; }
function get_template_part($part) { global $events; $events[] = $part; if ('template-parts/home/legacy-layout' === $part) require __DIR__ . '/../radio-rubben-next/' . $part . '.php'; }
function rr_theme_sponsors($slot) {}
function rr_weather_card() { global $events; $events[] = 'weather'; }
function do_action($hook) {
    global $events, $enabled;
    $events[] = $hook;
    if ($hook === 'rrpw_homepage' && $enabled) echo '<section id="bomlo-spillere">Existing plugin output</section>';
}
foreach (array(true, false) as $enabled) {
    $events = array();
    ob_start(); require __DIR__ . '/../radio-rubben-next/front-page.php'; $html = ob_get_clean();
    if (substr_count($html, 'id="bomlo-spillere"') !== ($enabled ? 1 : 0)) throw new Exception('Respect plugin visibility and render exactly once');
    if (count(array_keys($events, 'rrpw_homepage', true)) !== 1) throw new Exception('Plugin hook must run once');
    if (!(array_search('rr_theme_home_after_news', $events) < array_search('rrpw_homepage', $events) && array_search('rrpw_homepage', $events) < array_search('weather', $events))) throw new Exception('Preserve placement after news and before weather');
}
echo "PASS: homepage renders plugin once, respects visibility and preserves placement.\n";
