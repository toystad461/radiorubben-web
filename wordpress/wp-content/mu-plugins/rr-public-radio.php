<?php
/** Plugin Name: Radio Rubben public now/next
 * Version: 1.0.5
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
    wp_enqueue_script('rr-public-radio', plugins_url('rr-public-radio.js', __FILE__), array('rr-next-ui'), '1.0.5', true);
    if (function_exists('rr_one_logo_url')) {
        wp_localize_script('rr-public-radio', 'RR_PUBLIC_RADIO', array('logoUrl' => esc_url_raw(rr_one_logo_url())));
    }
}, 30);
add_action('wp_head', static function () {
    if (!rr_public_radio_enabled()) { return; }
    echo '<style id="rr-public-radio-cover">
.rr-radio-card .rr-radio-cover{display:block;position:relative;width:min(100%,220px);height:auto;min-height:0;aspect-ratio:1/1;margin:16px auto;padding:0;box-sizing:border-box;background:#08090c;overflow:hidden}
.rr-radio-card .rr-radio-cover img{position:absolute;inset:0;display:block;width:100%;height:100%;max-width:none;max-height:none;margin:0;padding:16px;box-sizing:border-box;object-fit:contain}
.rr-mini-player .rr-mini-cover{display:block;width:48px;height:48px;aspect-ratio:1/1;flex:0 0 48px;padding:4px;box-sizing:border-box;object-fit:contain;border-radius:6px;background:#08090c}
.rr-mini-player .rr-mini-meta{min-width:0}
@media(max-width:720px){.rr-news-first .rr-radio-card .rr-radio-cover{display:block;width:min(100%,180px);height:auto;min-height:0;aspect-ratio:1/1;margin:12px auto;padding:0}.rr-mini-player .rr-u-mini-label{display:none}.rr-mini-player .rr-mini-cover{width:44px;height:44px;flex-basis:44px}.rr-front:has(.rr-universes) .rr-mini-player{left:14px;right:14px;width:auto;max-width:none}.rr-mini-player .rr-broadcast{display:none}}
</style>';
}, 99);
