<?php
/** Plugin Name: Radio Rubben public now/next
 * Version: 1.0.0
 * Description: Public playback metadata for the approved VPS stream, with no Studio access.
 */
defined('ABSPATH') || exit;
function rr_public_radio_enabled() {
    return function_exists('rr_theme_mod') && rr_theme_mod('rr_stream_url', '') === 'https://5.249.252.78/radio.mp3';
}
// Do not show the old static next-song promise for this source, including before JS loads.
add_action('after_setup_theme', static function () {
    if (!rr_public_radio_enabled()) { return; }
    foreach (array_unique(array(get_stylesheet(), get_template(), 'radio-rubben-wordpress-v1')) as $theme) {
        add_filter('option_theme_mods_' . $theme, static function ($mods) {
            if (!is_array($mods)) { return $mods; }
            $mods['rr_next_artist'] = ''; $mods['rr_next_title'] = ''; return $mods;
        });
    }
}, 99);
add_action('wp_enqueue_scripts', static function () {
    if (!rr_public_radio_enabled()) { return; }
    wp_enqueue_script('rr-public-radio', plugins_url('rr-public-radio.js', __FILE__), array('rr-next-ui'), '1.0.0', true);
}, 30);
