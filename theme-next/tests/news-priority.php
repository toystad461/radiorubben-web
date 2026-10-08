<?php
// Isolated template contracts with synthetic fixtures; not a WordPress database test.
declare(strict_types=1);
define('ABSPATH', __DIR__);
$root = dirname(__DIR__) . '/radio-rubben-next';
$fixture = [];
$queryArgs = [];
$renderFull = false;
$styles = [];
$mode = 'classic';
class WP_Query {
    public array $posts;
    public function __construct(array $args) { global $queryArgs, $fixture; $queryArgs = $args; $this->posts = $fixture; }
}
function esc_html($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function esc_attr($s) { return esc_html($s); }
function esc_url($s) { return esc_attr($s); }
function get_permalink($p) { return '/fixture/' . (int)$p->ID; }
function get_the_title($p) { return $p->title; }
function get_the_excerpt($p) { return 'Tydelig merket testinnhold for nyhetsforsiden, ikke en faktisk nyhet.'; }
function wp_trim_words($text, $n) { return $text; }
function get_the_date($format, $p) { return $format === DATE_W3C ? '2026-10-07T12:00:00+02:00' : '7. oktober 2026'; }
function get_the_time($format, $p) { return '12:00'; }
function has_post_thumbnail($p) { return !empty($p->image); }
function get_the_post_thumbnail($p, $size, $attrs) { return '<img width="720" height="405" src="data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="720" height="405"><rect width="720" height="405" fill="#262732"/><text x="50" y="220" font-size="44" fill="white">TESTBILDE · NYHETER</text></svg>') . '" alt="">'; }
function rr_theme_page_url($slugs, $fallback) { return $fallback; }
function rr_theme_mod($key, $default) { global $mode; return $key === 'rr_front_layout' ? $mode : $default; }
function get_option($key) { global $mode; return $mode === 'posts' ? 'posts' : 'page'; }
function post_password_required() { global $mode; return $mode === 'password'; }
function rr_home_universes_enabled() { global $mode; return $mode === 'universes'; }
function get_theme_file_uri($path) { return '/theme' . $path; }
function wp_enqueue_style($handle, $src, $deps, $version) { global $styles; $styles[$handle] = [$src, $deps, $version]; }
function get_header() { global $styles; echo '<header data-fixture="header">Radio Rubben</header>'; }
function get_footer() { echo '<footer data-fixture="footer">Radio Rubben</footer>'; }
function have_posts() { return false; }
function the_post() {}
function the_content() {}
function wp_link_pages() {}
function do_action($name) { echo '<div data-hook="' . esc_attr($name) . '"></div>'; }
function rr_weather_card() { echo '<div data-hook="weather"></div>'; }
function rr_theme_sponsors($name) { echo '<div data-hook="sponsors"></div>'; }
function get_template_part($slug, $name = null, $args = []) {
    global $renderFull, $root;
    if ($renderFull && $slug === 'template-parts/home/news-priority') { include $root . '/' . $slug . '.php'; return; }
    if ($renderFull && $slug === 'template-parts/home/hero') {
        echo '<section class="rr-home-intro rr-wrap"><div class="rr-home-copy"><p class="rr-eyebrow">FRA BØMLO. MED MUSIKKGLEDE.</p><h1>Ingen valg.<br>Bare <span>god radio.</span></h1><p>Kjente låter. Nye opplevelser.</p><a class="rr-btn" href="#lytt">Til avspilleren</a></div><aside id="lytt" class="rr-radio-card"><div class="rr-radio-top"><strong>RADIO RUBBEN</strong><span>SENDING PÅ PAUSE</span></div><div class="rr-radio-cover">Profilbilde</div><div class="rr-radio-track"><h2>Vi bygger videre.</h2><button disabled aria-label="Spill Radio Rubben">▶</button></div><p>Sendingen er ikke tilgjengelig akkurat nå.</p></aside></section>'; return;
    }
    echo '<div data-part="' . esc_attr($slug) . '"' . (!empty($args['sport']) ? ' data-sport="true"' : '') . '></div>';
}
function renderNews(bool $sport = false): string {
    global $root;
    $args = ['sport' => $sport]; ob_start(); include $root . '/template-parts/home/news-priority.php'; return ob_get_clean();
}
$fixture = [(object)['ID'=>1,'title'=>'TESTUTKAST: En lang lokal overskrift som må kunne brytes på mobilen','image'=>true],(object)['ID'=>2,'title'=>'TESTUTKAST: En annen aktuell sak','image'=>true],(object)['ID'=>3,'title'=>'TESTUTKAST: Mer fra lokalsamfunnet','image'=>true]];
if (($argv[1] ?? '') === '--render') {
    $renderFull = true;
    echo '<!doctype html><html lang="nb"><head><meta name="viewport" content="width=device-width,initial-scale=1"><meta charset="utf-8"><title>Radio Rubben · syntetisk skjermtest</title><style>';
    foreach (['theme','design-v13','member-hub','mobile-shell','components','brand-profile','page-layouts','home-tidy','news-priority'] as $css) {
        $path = $root . '/assets/css/' . $css . '.css'; if (is_file($path)) echo file_get_contents($path);
    }
    echo '</style></head><body class="rr-next rr-front">'; include $root . '/front-page.php'; echo '</body></html>'; exit;
}
$checks = 0;
function check($ok, $label) { global $checks; if (!$ok) throw new RuntimeException($label); $checks++; }
$html = renderNews();
check($queryArgs['post_type'] === 'post', 'Only normal posts');
check($queryArgs['post_status'] === 'publish' && $queryArgs['has_password'] === false, 'Public published content only');
check($queryArgs['posts_per_page'] === 3 && $queryArgs['ignore_sticky_posts'] === true, 'Bounded chronological selection');
check($queryArgs['orderby'] === ['date'=>'DESC','ID'=>'DESC'], 'Stable newest-first ordering');
check($queryArgs['tax_query'][0]['terms'] === ['latest-updates','lokale_nyheter'], 'News categories explicit');
check($queryArgs['tax_query'][1]['terms'] === ['sport','fotball'] && $queryArgs['tax_query'][1]['operator'] === 'NOT IN', 'Dual-category sports excluded');
check($queryArgs['tax_query'][0]['include_children'] && $queryArgs['tax_query'][1]['include_children'], 'Descendants included and excluded');
check(substr_count($html, '<article ') === 3 && substr_count($html, '<img ') === 1, 'One lead image, two headline cards');
check(str_contains($html, '>Siste nytt</h2>') && str_contains($html, 'Alle nyheter'), 'Clear news heading and archive link');
check(strpos($html, '<h3>') < strpos($html, '<img '), 'Headline before repeated branding image');
check(str_contains($html, '2026-10-07T12:00:00+02:00') && str_contains($html, 'kl. 12:00'), 'Publication datetime and time visible');
$fixture[0]->title = '<script>alert(1)</script>'; $html = renderNews();
check(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'), 'Escaped title');
$fixture[0]->image = false; $html = renderNews(); check(!str_contains($html, '<img '), 'No broken image when absent');
renderNews(true);
check(count($queryArgs['tax_query']) === 2 && $queryArgs['tax_query'][0]['terms'] === ['sport','fotball'], 'Sports use separate category query');
$fixture=[]; $html = renderNews(); check(!str_contains($html, '<article ') && str_contains($html, 'når de er publisert'), 'Empty news stays honest');
foreach (['classic','content','universes','posts','password'] as $mode) {
    $styles=[]; ob_start(); include $root . '/front-page.php'; $page=ob_get_clean();
    if ($mode === 'classic') {
        check(strpos($page, 'home/news-priority') < strpos($page, 'home/member'), 'News precedes member section');
        check(substr_count($page, 'data-hook="rrpw_homepage"') === 1, 'Existing player hook exactly once');
        check(substr_count($page, 'home/hero') === 1 && !str_contains($page, 'home/latest'), 'Existing radio reused and old duplicate removed');
        check(isset($styles['rr-next-news-priority']) && $styles['rr-next-news-priority'][1] === ['rr-next-home'], 'Scoped CSS depends on existing homepage stylesheet');
        check(str_contains($page, 'rr_theme_home_after_news') && str_contains($page, 'rr_theme_home_content') && str_contains($page, 'home/feed'), 'RSS and existing hooks retained');
    } else {
        check(!str_contains($page, 'rr-news-first') && !$styles, 'Other homepage mode preserved: '.$mode);
    }
}
echo "News priority: $checks template contract checks passed; synthetic fixtures, no database writes.\n";
