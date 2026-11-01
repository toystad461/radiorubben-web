<?php
/** Run only through real WP-CLI; never installed under the web root. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$mode = $args[0] ?? 'snapshot';
if ($mode === 'cache') {
    if (!function_exists('WP_Optimize')) { throw new RuntimeException('Expected WP-Optimize is not loaded'); }
    $cache = WP_Optimize()->get_page_cache();
    if ($cache->is_enabled()) {
        if (!$cache->purge()) { throw new RuntimeException('WP-Optimize purge failed'); }
        echo "WP_OPTIMIZE_CACHE_PURGED\n";
    } else { echo "WP_OPTIMIZE_CACHE_DISABLED\n"; }
    if (!wp_cache_flush()) { throw new RuntimeException('Object cache flush failed'); }
    echo "OBJECT_CACHE_FLUSHED\n";
    return;
}
if ($mode !== 'snapshot') { throw new RuntimeException('Unknown mode'); }
$state = [];
foreach ([1594, 1601, 1576, 1603] as $id) {
    $post = get_post($id, ARRAY_A);
    if (!$post) { throw new RuntimeException('Required record missing'); }
    $meta = get_post_meta($id); ksort($meta);
    $state[$id] = hash('sha256', serialize([$post, $meta]));
}
$state['stylesheet'] = get_option('stylesheet');
$state['home'] = get_option('home');
echo json_encode($state, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
