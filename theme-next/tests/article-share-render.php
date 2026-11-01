<?php
/** Isolated template fixtures: no WordPress database, network or real posts. */
define('ABSPATH', __DIR__);
function get_permalink() { return $GLOBALS['fixture']['url']; }
function get_the_title() { return $GLOBALS['fixture']['title']; }
function wp_strip_all_tags($text) { return strip_tags($text); }
function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function esc_url($text) { return esc_attr($text); }

$fixtures = [
    ['url' => 'https://www.radiorubben.no/test-artikkel/', 'title' => 'Bømlo – mål &amp; musikk'],
    ['url' => 'https://www.radiorubben.no/test/?a=1&b=%C3%B8#del', 'title' => '<b>«Nyheter»</b> &quot;æøå&quot; &amp; &#039;sitat&#039;'],
];
foreach ($fixtures as &$fixture) {
    $GLOBALS['fixture'] = $fixture;
    ob_start();
    require __DIR__ . '/../radio-rubben-next/template-parts/article-share.php';
    $fixture['html'] = ob_get_clean();
}
unset($fixture);
echo json_encode($fixtures, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
