<?php
/** Render the real home/component templates with synthetic public fixtures. Not WP staging. */
define( 'ABSPATH', __DIR__ );
date_default_timezone_set( 'Europe/Oslo' );
$scenario = getenv( 'RR_PREVIEW_SCENARIO' ) ?: 'radio';
$mods = array( 'rr_front_layout' => 'universes', 'rr_stream_url' => 'https://example.invalid/listen', 'rr_now_artist' => 'Radio Rubben', 'rr_now_title' => 'Eksempel på musikk og program', 'rr_next_artist' => 'Neste program', 'rr_next_title' => 'Eksempel fra sendeplan' );
if ( 'news' === $scenario ) { $mods['rr_home_major_story_id'] = 1; $mods['rr_home_major_story_until'] = time() + 600; }
$stories = array();
$titles = array( 'Historiene som gjør Bømlo til Bømlo', 'Små øyeblikk. Stor musikkglede.', 'Møt stemmene bak mikrofonen', 'Det beste skjer når vi møtes', 'Heia Bremnes – fra første til siste fløyte', 'Spillerne vi følger, hjemme og ute', 'Din stemme. Dagens Bremnesing.' );
foreach ( $titles as $i => $title ) { $stories[$i+1] = (object) array( 'ID' => $i+1, 'post_type' => 'post', 'post_status' => 'publish', 'post_password' => '', 'category' => $i > 3 ? 8 : 1, 'title' => $title ); }
function rr_theme_mod( $k, $d = false ) { global $mods; return $mods[$k] ?? $d; }
function rr_theme_text( $v ) { return is_scalar( $v ) ? (string) $v : ''; }
function add_action( ...$a ) {} function add_filter( ...$a ) {}
function apply_filters( $key, $data ) { global $scenario; if ( 'rr_home_radio_data' === $key && 'radio' === $scenario ) { return array_merge( $data, array( 'live' => true, 'observed_at' => time(), 'expires_at' => time()+180 ) ); } if ( 'rr_home_sport_data' === $key ) { $data['last_result'] = 'Bremnes 2–1 Eksempellag'; $data['upcoming'] = array( 'Bremnes – Eksempellag · eksempel fra terminlisten', 'Eksempellag – Bremnes · eksempel fra terminlisten' ); if ( 'sport' === $scenario ) { $data = array_merge( $data, array( 'live' => true, 'observed_at' => time(), 'expires_at' => time()+180 ) ); $data['match']['status'] = 'live'; } } return $data; }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); } function esc_attr( $v ) { return esc_html( $v ); } function esc_url( $v ) { return esc_html( $v ); } function esc_attr_e( $v, $domain = '' ) { echo esc_html( $v ); } function __( $v, $domain = '' ) { return $v; }
function esc_url_raw( $v, $protocols = array() ) { return preg_match( '~^https?://~', $v ) ? $v : ''; }
function disabled( $condition ) { if ( $condition ) { echo 'disabled'; } }
function get_option( $key, $default = array() ) { return $default; } function get_transient( $k ) { return false; }
function is_front_page() { return true; } function post_password_required() { return false; } function absint( $v ) { return abs( (int) $v ); } function is_wp_error( $v ) { return false; }
function get_category_by_slug( $s ) { return (object) array( 'term_id' => 8 ); } function get_term_children( $id, $tax ) { return array( 9 ); }
function get_post( $id ) { global $stories; return $stories[$id] ?? null; } function is_post_publicly_viewable( $p ) { return true; } function has_category( $ids, $p ) { return in_array( $p->category, $ids ); }
function get_the_title( $p ) { return $p->title; } function get_the_date( $format, $p ) { return '5. oktober'; } function get_the_excerpt( $p ) { return 'Et eksempel på hvordan en godkjent lokal sak presenteres. Nære historier, tydelige stemmer og plass til det som betyr noe for øya vår.'; } function wp_trim_words( $text, $count ) { return implode( ' ', array_slice( explode( ' ', $text ), 0, $count ) ); }
function has_post_thumbnail( $p ) { return in_array( $p->ID, array( 1, 5, 6, 7 ) ); } function get_the_post_thumbnail( $p, $size ) { $image = $p->category === 8 ? 'football' : 'mosterhamn'; return '<img alt="Illustrasjonsbilde i testvisning" src="/theme-next/radio-rubben-next/assets/images/' . $image . '.webp" loading="lazy">'; }
function get_permalink( $p ) { return '#nyheter'; } function home_url( $path ) { return $path; } function wp_date( $format, $time = null ) { return date( $format, $time ?? time() ); }
function get_template_directory_uri() { return '/theme-next/radio-rubben-next'; } function get_theme_file_uri( $path ) { return get_template_directory_uri().$path; }
function rr_theme_page_url( $slugs, $fallback ) { return $fallback; } function rr_one_get_first_existing_url( $slugs, $fallback ) { return $fallback; } function rr_one_logo_url() { return rr_brand_logo_url(); } function rr_theme_member_label() { return 'Åpne Min Rubben'; }
function rr_theme_menu( $slot ) { if ( 'primary' === $slot ) { echo '<ul><li><a href="#nettradio">Nettradio</a></li><li><a href="#nyheter">Nyheter</a></li><li><a href="#sport">Sport</a></li><li><a href="/kontakt/">Kontakt</a></li></ul>'; } }
function rr_theme_match_data( $ctx ) { if ( 'header' === $ctx ) { return array(); } return array( 'home' => 'Bremnes', 'away' => 'Eksempellag', 'status' => 'scheduled', 'kickoff' => '2026-10-10T15:00:00+02:00', 'venue' => 'Eksempel på kampsted', 'url' => '#sport', 'score' => array( 'home' => 2, 'away' => 1 ) ); }
function rr_theme_sponsors( $slot ) {} function do_action( $slot ) {}
function rr_theme_feed_items() { return array(); }
function rr_theme_component( $name, $args ) { require __DIR__.'/../radio-rubben-next/template-parts/components/'.$name.'.php'; }
function get_template_part( $path ) { if ( 'template-parts/site/match-bar' === $path ) { return; } require __DIR__.'/../radio-rubben-next/'.$path.'.php'; }
class WP_Query { public $posts; function __construct( $args ) { global $stories; $this->posts = 'rr_match' === $args['post_type'] ? array() : array_values( array_filter( $stories, static function ( $post ) use ( $args ) { return isset( $args['category__in'] ) ? 8 === $post->category : 1 === $post->category; } ) ); } }
require __DIR__.'/../radio-rubben-next/inc/brand.php';
require __DIR__.'/../radio-rubben-next/inc/home-universes.php';
?>
<!doctype html><html lang="nb"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Radio Rubben – testforside</title>
<?php foreach ( array( 'theme', 'design-v13', 'member-hub', 'mobile-shell', 'components', 'brand-profile', 'home-universes' ) as $style ) : ?><link rel="stylesheet" href="/theme-next/radio-rubben-next/assets/css/<?php echo $style; ?>.css"><?php endforeach; ?>
<style>.rr-preview-toolbar{position:relative;background:#ffdf85;color:#111;padding:10px 18px;font:12px/1.5 Arial;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}.rr-preview-toolbar a{color:#111;font-weight:bold;margin-left:12px}.rr-preview-toolbar nav{display:flex;flex-wrap:wrap}</style></head><body class="rr-next rr-front">
<div class="rr-preview-toolbar"><span>TESTVISNING · Eksempelinnhold · Ingen ekte sending eller produksjonsendring</span><nav aria-label="Test scenarier"><a href="/preview-radio.html">Radio live</a><a href="/preview-news.html">Stor lokal sak</a><a href="/preview-sport.html">Live kamp</a></nav></div>
<a class="screen-reader-text" href="#main">Hopp til innhold</a>
<?php get_template_part( 'template-parts/site/header' ); ?><main id="main"><?php get_template_part( 'template-parts/home/universes' ); ?></main><?php get_template_part( 'template-parts/site/footer' ); get_template_part( 'template-parts/site/mini-player' ); ?>
<script>window.RR_ONE={streamUrl:'https://example.invalid/listen'};HTMLMediaElement.prototype.play=function(){return Promise.reject(new Error('Testvisning uten lyd'));};</script><script src="/theme-next/radio-rubben-next/assets/js/ui.js"></script>
</body></html>
