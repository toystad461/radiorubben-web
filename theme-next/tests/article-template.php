<?php
/** Isolated integration checks for PR #107 with the unchanged template from #110. */
define('ABSPATH', __DIR__);
function post_class($class) { echo 'class="' . $class . '"'; }
function get_the_category() { return []; }
function get_the_title() { return 'Prøve – Bømlo &amp; øyene'; }
function the_title() { echo get_the_title(); }
function get_permalink() { return 'https://www.radiorubben.no/test-artikkel/'; }
function wp_strip_all_tags($text) { return strip_tags($text); }
function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function esc_url($text) { return esc_attr($text); }
function esc_html($text) { return esc_attr($text); }
function esc_attr__($text, $domain) { return esc_attr($text); }
function esc_attr_e($text, $domain) { echo esc_attr($text); }
function wp_kses_post($text) { return $text; }
function rr_theme_component($name) { echo '<span data-component="' . $name . '"></span>'; }
function get_template_part($name) { require __DIR__ . '/../radio-rubben-next/' . $name . '.php'; }
function has_post_thumbnail() { return true; }
function post_password_required() { return $GLOBALS['protected']; }
function the_post_thumbnail($size) { echo '<img src="/test-image.jpg" alt="Illustrasjonsfoto">'; }
function get_post_thumbnail_id() { return 1; }
function wp_get_attachment_caption($id) { return '<a href="https://example.org/credit">Fotograf og lisens</a>'; }
function is_singular($type) { return $GLOBALS['post_type'] === $type; }
function the_content() { echo $GLOBALS['content']; }
function wp_link_pages($args) { echo '<nav class="pagination-test">2</nav>'; }
function the_tags(...$args) {}
function previous_post_link(...$args) {}
function next_post_link(...$args) {}
function rr_theme_sponsors($placement) {}
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
$footer = 'Teksten er utarbeidet med kunstig intelligens på grunnlag av Bremnes ILs arrangementsinformasjon og godkjent av redaksjonen. Redaksjonelt ansvar: Radio Rubben ved Thomas Magne Sellevold-Øystad.';
$source = '<p>Kilde: <a href="https://example.org/source">Primærkilde</a></p>';
$fixtures = [
    ['legacy-top', '<div class="rubben-ai-notice"><strong>KI-generert artikkel</strong></div><p>Ingress æøå.</p>' . $source, true],
    ['block-footer', '<!-- wp:paragraph --><p class="wp-block-paragraph"><strong>Ingress æøå.</strong></p><!-- /wp:paragraph -->' . $source . '<p><small>' . $footer . '</small></p>', true],
    ['quote-first', '<blockquote><p>Et sitat.</p></blockquote>' . $source, false],
    ['figure-first', '<figure><img src="/inline.jpg"><figcaption>Innholdsbilde</figcaption></figure>' . $source, false],
    ['unknown-footer', '<p>Ingress.</p>' . $source . '<p><small>Annen redaksjonell tekst skal bevares.</small></p>', true],
    ['protected', '<p>Passordskjema.</p>', false],
    ['page', '<p>Vanlig side.</p>' . $source, false],
];
foreach ($fixtures as [$name, $content, $lead]) {
    $protected = $name === 'protected';
    $post_type = $name === 'page' ? 'page' : 'post';
    $before = hash('sha256', $content);
    ob_start(); require __DIR__ . '/../radio-rubben-next/template-parts/content/article.php'; $html = ob_get_clean();
    check(hash('sha256', $content) === $before, "$name: stored content mutated");
    check(substr_count($html, 'class="rr-share"') === 1, "$name: sharing partial missing/duplicated");
    check(str_contains($html, 'facebook.addEventListener'), "$name: new sharing script missing");
    check(str_contains($html, 'pagination-test'), "$name: pagination lost");
    $notice = substr_count($html, 'class="rr-editorial-notice"');
    check($notice === (($protected || $post_type === 'page') ? 0 : 1), "$name: notice count");
    if (!$protected) {
        check(str_contains($html, $source), "$name: source lost");
        check(str_contains($html, 'https://example.org/credit'), "$name: image credit lost");
        check(str_contains($html, '/test-image.jpg'), "$name: hero lost");
    } else {
        check(!str_contains($html, '/test-image.jpg'), "$name: protected image exposed");
        check(!str_contains($html, 'data-component="source"'), "$name: protected sources exposed");
    }
    if ($notice) {
        check(!str_contains($html, 'rubben-ai-notice'), "$name: legacy box retained");
        check(!str_contains($html, $footer), "$name: legacy footer retained");
        preg_match('~<div class="rr-content rr-article-content">(.*)</div>~s', $html, $match);
        $body = $match[1];
        $position = strpos($body, '<aside class="rr-editorial-notice"');
        check($lead ? strpos($body, '</p>') < $position : $position === 0, "$name: notice position");
    }
    if ($name === 'unknown-footer') check(str_contains($html, 'Annen redaksjonell tekst skal bevares.'), 'Unknown footer removed');
    echo "PASS $name\n";
}
echo "PASS 7 article/sharing integration scenarios\n";
