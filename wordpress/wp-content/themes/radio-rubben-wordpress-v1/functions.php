<?php
if (!defined('ABSPATH')) exit;

define('RR_ONE_VERSION', '1.3.6');
require_once get_template_directory() . '/inc/brand.php';
require_once get_template_directory() . '/inc/weather.php';
require_once get_template_directory() . '/inc/quiz-controls.php';
require_once get_template_directory() . '/inc/weekly-quiz.php';
require_once get_template_directory() . '/inc/member-hub.php';
require_once get_template_directory() . '/inc/bremnes-direkte-test.php';

function rr_one_setup() {
    load_theme_textdomain('radio-rubben-one', get_template_directory() . '/languages');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('custom-logo', [
        'height' => 300,
        'width' => 900,
        'flex-height' => true,
        'flex-width' => true,
    ]);
    add_theme_support('html5', ['search-form','comment-form','comment-list','gallery','caption','style','script']);
    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');
    add_theme_support('editor-styles');
    add_image_size('rr-card', 720, 405, true);
    add_image_size('rr-hero', 1600, 900, true);
    register_nav_menus([
        'primary' => __('Hovedmeny', 'radio-rubben-one'),
        'footer' => __('Footermeny', 'radio-rubben-one'),
    ]);
}
add_action('after_setup_theme', 'rr_one_setup');

function rr_one_assets() {
    wp_enqueue_style('rr-one-style', get_stylesheet_uri(), [], RR_ONE_VERSION);
    wp_enqueue_style('rr-one-theme', get_template_directory_uri() . '/assets/css/theme.css', [], RR_ONE_VERSION);
    wp_enqueue_style('rr-one-design', get_template_directory_uri() . '/assets/css/design-v13.css', ['rr-one-theme'], RR_ONE_VERSION);
    if (is_page(910)) {
        wp_add_inline_style('rr-one-design', '.page-id-910 #content{position:relative;isolation:isolate;background:#0b0d11}.page-id-910 #content::before{content:"";position:absolute;inset:0;z-index:0;background:url("https://www.radiorubben.no/wp-content/uploads/2026/09/Radio-Rubben-%E2%80%93-Fotball.png") center top/cover no-repeat;opacity:.1;pointer-events:none}.page-id-910 #content>.rr-poll{position:relative;z-index:1}');
    }
    if (is_front_page()) wp_enqueue_style('rr-home-tidy', get_template_directory_uri() . '/assets/css/home-tidy.css', ['rr-one-design'], '1.0.0');
    if (is_singular('rr_match') || is_page('rrlive')) wp_enqueue_style('rrlive-match', get_template_directory_uri() . '/assets/css/rrlive.css', ['rr-one-design'], '0.2.4');
    wp_enqueue_style('rr-mobile-shell', get_template_directory_uri() . '/assets/css/mobile-shell.css', ['rr-one-design','rr-member-hub'], '1.0.0');
    wp_enqueue_script('rr-one-theme', get_template_directory_uri() . '/assets/js/theme.js', [], RR_ONE_VERSION, true);
    wp_localize_script('rr-one-theme', 'RR_ONE', [
        'streamUrl' => esc_url_raw(get_theme_mod('rr_stream_url', '')),
        'live' => (bool) get_theme_mod('rr_live_status', false),
        'noStreamText' => __('Sendingen er ikke tilgjengelig akkurat nå. Prøv igjen senere.', 'radio-rubben-one'),
    ]);
}
add_action('wp_enqueue_scripts', 'rr_one_assets');
// Load after all existing shell styles, with its own cache version.
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('rr-brand-profile', get_template_directory_uri() . '/assets/css/brand-profile.css', ['rr-one-design', 'rr-mobile-shell'], '2026.10.01.1');
}, 30);

function rr_one_customize($wp_customize) {
    $wp_customize->add_section('rr_radio', [
        'title' => __('Radio Rubben – radio og kontakt', 'radio-rubben-one'),
        'priority' => 30,
    ]);

    $fields = [
        'rr_stream_url' => ['Strøm-URL', 'url', ''],
        'rr_now_artist' => ['Fallback: artist', 'text', 'Radio Rubben'],
        'rr_now_title' => ['Fallback: låt', 'text', 'Kjente låter. Nye opplevelser.'],
        'rr_next_artist' => ['Fallback: neste artist', 'text', ''],
        'rr_next_title' => ['Fallback: neste låt', 'text', ''],
        'rr_email' => ['E-post', 'email', 'post@radiorubben.no'],
        'rr_phone' => ['Telefon', 'text', '+47 934 44 954'],
        'rr_address' => ['Adresse', 'text', ''],
        'rr_orgnr' => ['Organisasjonsnummer', 'text', ''],
        'rr_facebook' => ['Facebook-URL', 'url', ''],
        'rr_instagram' => ['Instagram-URL', 'url', ''],
        'rr_tiktok' => ['TikTok-URL', 'url', ''],
        'rr_cf7_shortcode' => ['Contact Form 7 shortcode', 'text', '[contact-form-7 id="c62b2ec" title="Kontaktskjema 1"]'],
    ];
    foreach ($fields as $id => $f) {
        [$label,$type,$default] = $f;
        $sanitize = 'sanitize_text_field';
        if ($type === 'url') $sanitize = 'esc_url_raw';
        if ($type === 'email') $sanitize = 'sanitize_email';
        $wp_customize->add_setting($id, ['default'=>$default, 'sanitize_callback'=>$sanitize]);
        $wp_customize->add_control($id, ['label'=>$label, 'section'=>'rr_radio', 'type'=>$type]);
    }

    $wp_customize->add_section('rr_match_banner', [
        'title' => __('Kampstripe i toppen', 'radio-rubben-one'),
        'priority' => 31,
    ]);
    $wp_customize->add_setting('rr_next_match_banner_days', [
        'default' => 7,
        'sanitize_callback' => static function($value) { return max(0,min(60,(int)$value)); },
    ]);
    $wp_customize->add_control('rr_next_match_banner_days', [
        'label' => __('Vis neste kamp innen antall dager', 'radio-rubben-one'),
        'description' => __('Standard er 7 dager. Bruk 0 for å skjule stripen. Pågående avstemning vises fortsatt.', 'radio-rubben-one'),
        'section' => 'rr_match_banner',
        'type' => 'number',
        'input_attrs' => ['min'=>0,'max'=>60,'step'=>1],
    ]);

    $wp_customize->add_setting('rr_live_status', ['default'=>false, 'sanitize_callback'=>'rest_sanitize_boolean']);
    $wp_customize->add_control('rr_live_status', [
        'label' => __('Vis LIVE NÅ', 'radio-rubben-one'),
        'section' => 'rr_radio',
        'type' => 'checkbox',
        'description' => __('Slå bare på når sendingen faktisk er direkte.', 'radio-rubben-one'),
    ]);
}
add_action('customize_register', 'rr_one_customize');

function rr_one_logo_url() {
    if (rr_brand_uses_profile_logos()) return rr_brand_logo_url();
    $custom_logo_id = get_theme_mod('custom_logo');
    if ($custom_logo_id) {
        $src = wp_get_attachment_image_src($custom_logo_id, 'full');
        if ($src) return $src[0];
    }
    return get_template_directory_uri() . '/assets/images/radio-rubben-logo.png';
}

function rr_one_fallback_icon() {
    if (!has_site_icon()) {
        $icon = get_template_directory_uri() . '/assets/images/site-icon.png';
        echo '<link rel="icon" href="' . esc_url($icon) . '" sizes="512x512">' . "\n";
        echo '<link rel="apple-touch-icon" href="' . esc_url($icon) . '">' . "\n";
    }
}
add_action('wp_head', 'rr_one_fallback_icon', 5);

function rr_one_create_page($title, $slug, $content = '') {
    $existing = get_page_by_path($slug);
    if ($existing) return (int)$existing->ID;
    return wp_insert_post([
        'post_title' => $title,
        'post_name' => $slug,
        'post_content' => $content,
        'post_status' => 'publish',
        'post_type' => 'page',
    ]);
}

function rr_one_get_first_existing_url($slugs, $fallback = '/') {
    foreach ((array)$slugs as $slug) {
        $p = get_page_by_path($slug);
        if ($p) return get_permalink($p);
    }
    return home_url($fallback);
}

function rr_one_build_primary_menu() {
    $menu_name = 'Radio Rubben – Hovedmeny v1.2';
    $menu = wp_get_nav_menu_object($menu_name);
    $menu_id = $menu ? (int)$menu->term_id : wp_create_nav_menu($menu_name);
    if (is_wp_error($menu_id)) return;

    if (!$menu || !wp_get_nav_menu_items($menu_id)) {
        $items = [
            ['Hjem', home_url('/')],
            ['Lytt', rr_one_get_first_existing_url(['lytt','pages'], '/lytt/')],
            ['Reimagined', rr_one_get_first_existing_url(['reimagined'], '/reimagined/')],
            ['På Radio Rubben', rr_one_get_first_existing_url(['pa-radio-rubben','pages-2'], '/pa-radio-rubben/')],
            ['Aktuelt', rr_one_get_first_existing_url(['nyheter'], '/nyheter/')],
            ['Om', rr_one_get_first_existing_url(['om-radio-rubben','about-us'], '/om-radio-rubben/')],
            ['Samarbeid', rr_one_get_first_existing_url(['samarbeid','sponsor'], '/samarbeid/')],
            ['Kontakt', rr_one_get_first_existing_url(['kontakt','contact-us'], '/kontakt/')],
        ];
        foreach ($items as [$label,$url]) {
            wp_update_nav_menu_item($menu_id, 0, [
                'menu-item-title' => $label,
                'menu-item-url' => $url,
                'menu-item-status' => 'publish',
            ]);
        }
    }
    $locations = get_theme_mod('nav_menu_locations', []);
    $locations['primary'] = $menu_id;
    set_theme_mod('nav_menu_locations', $locations);
}

function rr_one_maybe_upgrade() {
    $saved = get_option('rr_one_theme_version', '0');
    if (version_compare($saved, RR_ONE_VERSION, '>=')) return;

    // A visual update must not change existing pages, menus or front-page selection.
    update_option('rr_one_theme_version', RR_ONE_VERSION);
    set_transient('rr_one_updated_notice', 1, DAY_IN_SECONDS);
}
add_action('admin_init', 'rr_one_maybe_upgrade');

function rr_one_admin_notice() {
    if (!get_transient('rr_one_updated_notice') || !current_user_can('manage_options')) return;
    delete_transient('rr_one_updated_notice');
    echo '<div class="notice notice-success is-dismissible"><p><strong>Radio Rubben One v1.3 er aktiv.</strong> Designet er oppdatert. Eksisterende sider og menyer er beholdt. Kontroller strøm-URL, adresse og organisasjonsnummer under <em>Utseende → Tilpass → Radio Rubben – radio og kontakt</em>.</p></div>';
}
add_action('admin_notices', 'rr_one_admin_notice');

function rr_one_contact_form() {
    $shortcode = trim((string)get_theme_mod('rr_cf7_shortcode', '[contact-form-7 id="c62b2ec" title="Kontaktskjema 1"]'));
    if (!$shortcode) return '';
    if (!shortcode_exists('contact-form-7')) {
        $email = get_theme_mod('rr_email','post@radiorubben.no');
        return '<p class="rr-form-unavailable">Kontaktskjemaet er ikke aktivt akkurat nå. Send gjerne e-post til <a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>.</p>';
    }
    return do_shortcode($shortcode);
}

function rr_one_body_classes($classes) {
    if (is_front_page()) $classes[] = 'rr-front';
    if (is_singular('post')) $classes[] = 'rr-single-post';
    return $classes;
}
add_filter('body_class', 'rr_one_body_classes');

function rr_one_fallback_menu() {
    $items = [
        ['Hjem', home_url('/')],
        ['Lytt', rr_one_get_first_existing_url(['lytt','pages'], '/lytt/')],
        ['Reimagined', rr_one_get_first_existing_url(['reimagined'], '/reimagined/')],
        ['På Radio Rubben', rr_one_get_first_existing_url(['pa-radio-rubben','pages-2'], '/pa-radio-rubben/')],
        ['Aktuelt', rr_one_get_first_existing_url(['nyheter'], '/nyheter/')],
        ['Om', rr_one_get_first_existing_url(['om-radio-rubben','about-us'], '/om-radio-rubben/')],
        ['Samarbeid', rr_one_get_first_existing_url(['samarbeid','sponsor'], '/samarbeid/')],
        ['Kontakt', rr_one_get_first_existing_url(['kontakt','contact-us'], '/kontakt/')],
    ];
    echo '<ul>';
    foreach ($items as [$label,$url]) echo '<li><a href="'.esc_url($url).'">'.esc_html($label).'</a></li>';
    echo '</ul>';
}

function rr_one_excerpt_length($length) { return is_admin() ? $length : 28; }
add_filter('excerpt_length', 'rr_one_excerpt_length', 999);
function rr_one_excerpt_more($more) { return '…'; }
add_filter('excerpt_more', 'rr_one_excerpt_more');

function rr_one_post_meta() {
    $cats = get_the_category();
    $cat = $cats ? $cats[0]->name : 'Radio Rubben';
    echo '<div class="rr-post-meta"><span>' . esc_html(get_the_date()) . '</span><span>•</span><span>' . esc_html(get_the_author()) . '</span><span>•</span><span>' . esc_html($cat) . '</span></div>';
}

function rr_one_pagination() {
    $links = paginate_links([
        'mid_size' => 1,
        'prev_text' => '← Nyere',
        'next_text' => 'Eldre →',
        'type' => 'list',
    ]);
    if ($links) echo '<nav class="rr-pagination" aria-label="Sideinndeling">' . $links . '</nav>';
}

/**
 * RRLive match data and REST fields.
 */
require_once get_template_directory() . '/rrlive-data.php';
