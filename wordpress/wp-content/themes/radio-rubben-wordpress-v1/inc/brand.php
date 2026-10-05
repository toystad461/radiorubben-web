<?php
if (!defined('ABSPATH')) exit;

// Release-owned assets make logo changes reversible through the normal code deploy.
function rr_brand_url($path) {
    return get_template_directory_uri() . '/assets/brand/2026-09/' . $path . '?v=20261005-2';
}

function rr_one_logo_url($variant = 'master') {
    $files = [
        'master' => 'SVG/03-Hovedlogo-transparent-hvit.svg',
        'compact' => 'SVG/07-Uten-verdilinje-hvit.svg',
        'light' => 'SVG/04-Hovedlogo-transparent-svart.svg',
        'watermark' => 'SVG/16-Ikon-hvit-transparent.svg',
    ];
    return rr_brand_url($files[$variant] ?? $files['master']);
}

function rr_brand_icon_url($url, $size) {
    $size = (int) $size;
    $icon = $size <= 16 ? 16 : ($size <= 32 ? 32 : ($size <= 48 ? 48 : ($size <= 180 ? 180 : ($size <= 192 ? 192 : 512))));
    return rr_brand_url('Ikoner/ikon-' . $icon . '.png');
}
add_filter('get_site_icon_url', 'rr_brand_icon_url', 10, 2);

function rr_brand_head() {
    foreach ([32, 192] as $size) {
        echo '<link rel="icon" type="image/png" sizes="' . $size . 'x' . $size . '" href="' . esc_url(rr_brand_icon_url('', $size)) . '">' . "\n";
    }
    echo '<link rel="icon" href="' . esc_url(rr_brand_url('Ikoner/favicon.ico')) . '" sizes="any">' . "\n";
    echo '<link rel="apple-touch-icon" sizes="180x180" href="' . esc_url(rr_brand_icon_url('', 180)) . '">' . "\n";
    echo '<link rel="manifest" href="' . esc_url(rr_brand_url('site.webmanifest')) . '">' . "\n";
    echo '<meta name="theme-color" content="#000000">' . "\n";
}
remove_action('wp_head', 'wp_site_icon', 99);
add_action('wp_head', 'rr_brand_head', 99);

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('rr-brand', get_template_directory_uri() . '/assets/css/brand.css', ['rr-mobile-shell'], '2026.10.05.2');
});

// Only replace the front-page brand card or a positively identified old brand image.
// Article photographs and editorial illustrations retain their own sharing images.
function rr_brand_old_image($url) {
    $path = (string) parse_url((string) $url, PHP_URL_PATH);
    return (bool) preg_match('~/(?:A1067EB6-D53D-4988-876B-5497FE466CD0|(?:cropped-)*RR_Logo_(?:Main_Dark|Icon|Horizontal)|(?:cropped-)*Kopi-av-Originalstorrelse-Logo-Radio-Rubben_-Black_Blue_White)(?:[-0-9x]*)\.(?:png|svg)$~i', $path);
}

function rr_brand_share_image($image) {
    if (is_front_page() || rr_brand_old_image($image['url'] ?? '')) {
        return ['url' => rr_brand_url('PNG/01-Hovedlogo-mork.png'), 'width' => 2400, 'height' => 1073, 'type' => 'image/png', 'alt' => 'Radio Rubben – lokal, inkluderende, verdig, engasjerende'];
    }
    return $image;
}
add_filter('rank_math/opengraph/facebook/image_array', 'rr_brand_share_image');
add_filter('rank_math/opengraph/twitter/image_array', 'rr_brand_share_image');

add_filter('rank_math/json_ld', function ($data) {
    foreach ($data as &$entity) {
        if (!is_array($entity)) continue;
        if (isset($entity['logo']) && is_array($entity['logo']) && ($entity['logo']['@id'] ?? '') === home_url('/#logo')) {
            $entity['logo']['url'] = $entity['logo']['contentUrl'] = rr_brand_url('PNG/12-Profilbilde-mork.png');
            $entity['logo']['width'] = $entity['logo']['height'] = 1080;
        }
        if (is_front_page() && ($entity['@type'] ?? '') === 'ImageObject' && rr_brand_old_image($entity['url'] ?? '')) {
            // Keep @id stable because primaryImageOfPage references it.
            $entity['url'] = rr_brand_url('PNG/01-Hovedlogo-mork.png');
            $entity['width'] = 2400;
            $entity['height'] = 1073;
        }
    }
    unset($entity);
    return $data;
}, 99);
