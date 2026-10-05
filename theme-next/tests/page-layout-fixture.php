<?php
/** Pure fixture, no WP bootstrap/database/network. Real templates + WP core block parser. */
define( 'ABSPATH', __DIR__ );
$parser_dir = getenv( 'RR_WP_INCLUDES' );
if ( ! $parser_dir || ! is_file( $parser_dir . '/class-wp-block-parser.php' ) ) { throw new RuntimeException( 'Set RR_WP_INCLUDES to WordPress wp-includes (parser only).' ); }
require_once $parser_dir . '/class-wp-block-parser.php';
$fixture = array( 'layout' => getenv( 'RR_LAYOUT' ) ?: 'football', 'protected' => false, 'post' => false, 'excerpt' => true, 'content_calls' => 0, 'loop' => 0 );
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $v ) { return esc_html( $v ); } function esc_url( $v ) { return esc_html( $v ); }
function __( $v, $domain = '' ) { return $v; } function esc_html_e( $v, $d = '' ) { echo esc_html( $v ); } function esc_attr_e( $v, $d = '' ) { echo esc_attr( $v ); } function esc_attr__( $v, $d = '' ) { return esc_attr( $v ); }
function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
function parse_blocks( $s ) { return ( new WP_Block_Parser() )->parse( $s ); }
function have_posts() { return $GLOBALS['fixture']['loop'] < 1; } function the_post() { $GLOBALS['fixture']['loop']++; }
function post_password_required() { return $GLOBALS['fixture']['protected']; }
function has_excerpt() { return $GLOBALS['fixture']['excerpt']; } function get_the_excerpt() { return 'Samlet på én side — eksempelinnhold for lokal layoutkontroll.'; }
function get_the_title() { return 'Radio Rubben: tett på lokalfotballen'; } function the_title() { echo esc_html( get_the_title() ); }
function get_the_content() { return $GLOBALS['fixture']['content']; }
function the_content() { $GLOBALS['fixture']['content_calls']++; echo post_password_required() ? '<form class="post-password-form"><label>Passord <input type="password"></label></form>' : str_replace( '[rr_weekly_competition]', '<p data-fixture-module="quiz">Simulert pluginvisning. Ingen ekte quiz eller innsending.</p>', get_the_content() ); }
function wp_link_pages( $a = array() ) { if ( ! empty( $GLOBALS['multipage'] ) ) { echo '<nav class="rr-page-links">Side 1 · Side 2</nav>'; } }
function comments_open() { return false; } function get_comments_number() { return 0; } function comments_template() {}
function get_template_part( $p, $name = null, $args = array() ) { require __DIR__ . '/../radio-rubben-next/' . $p . '.php'; }
function get_header() { echo '<main id="content" tabindex="-1">'; } function get_footer() { echo '</main>'; }
function post_class( $s ) { echo 'class="' . esc_attr( $s ) . '"'; }
function get_the_category() { return array(); } function has_post_thumbnail() { return true; }
function the_post_thumbnail( $size ) { echo '<img alt="Eksempelbilde" src="/theme-next/radio-rubben-next/assets/images/football.webp">'; }
function get_post_thumbnail_id() { return 1; } function wp_get_attachment_caption( $id ) { return 'Illustrasjon fra eksisterende profil'; }
function wp_kses_post( $s ) { return $s; } function is_singular( $s ) { return $GLOBALS['fixture']['post']; }
function the_tags( ...$args ) { echo '<div class="rr-tags">Fotball</div>'; } function previous_post_link( ...$args ) { echo 'Forrige'; } function next_post_link( ...$args ) { echo 'Neste'; }
function rr_theme_component( $name ) { echo '<div data-fixture-component="' . esc_attr( $name ) . '">' . esc_html( $name ) . '</div>'; }
function rr_theme_sponsors( $s ) {} function get_permalink() { return 'https://example.invalid/artikkel/'; }
function wp_json_encode( $s, $opts = 0 ) { return json_encode( $s, $opts ); }
require __DIR__ . '/../radio-rubben-next/inc/page-layouts.php';
function fixture_pattern( $name ) { ob_start(); require __DIR__ . '/../radio-rubben-next/patterns/' . $name . '.php'; return ob_get_clean(); }
$fixture['content'] = fixture_pattern( 'application' === $fixture['layout'] ? 'combined-dashboard' : 'combined-football' );
