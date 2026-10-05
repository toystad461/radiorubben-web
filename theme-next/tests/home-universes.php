<?php
/** Offline behavioural checks. No WordPress database, network or sending. */
define( 'ABSPATH', __DIR__ );
$mods = $filters = $posts = array();
function rr_theme_mod( $key, $default = false ) { global $mods; return $mods[$key] ?? $default; }
function rr_theme_text( $v ) { return is_scalar( $v ) ? (string) $v : ''; }
function add_action( ...$args ) {}
function apply_filters( $key, $data ) { global $filters; return $filters[$key] ?? $data; }
function esc_url_raw( $url, $protocols = array() ) { return preg_match( '~^https?://~', $url ) ? $url : ''; }
function get_option( $key, $default = array() ) { global $test_options; return $test_options[$key] ?? $default; }
function wp_timezone() { return new DateTimeZone( 'Europe/Oslo' ); }
function is_front_page() { global $test_front; return $test_front ?? true; }
function post_password_required() { global $test_password; return $test_password ?? false; }
function wp_date( $format, $stamp ) { return date( $format, $stamp ); }
function get_permalink( $post ) { return 'https://example.invalid/match/' . $post->ID; }
function rr_theme_rrlive_data( $id ) { global $test_matches; return $test_matches[$id]; }
function get_transient( $key ) { return false; }
function get_category_by_slug( $slug ) { return (object) array( 'term_id' => 8 ); }
function get_term_children( $id, $taxonomy ) { return array( 9 ); }
function is_wp_error( $v ) { return false; }
function absint( $v ) { return abs( (int) $v ); }
function get_post( $id ) { global $posts; return $posts[$id] ?? null; }
function is_post_publicly_viewable( $p ) { return 'publish' === $p->post_status; }
function has_category( $ids, $p ) { return in_array( $p->category ?? 1, $ids, true ); }
function rr_theme_match_data( $context ) { return array( 'status' => 'live' ); }
class WP_Query { public $args; public $posts = array(); function __construct( $args ) { global $test_matches; $this->args = $args; if ( 'rr_match' === $args['post_type'] ) { foreach ( (array) $test_matches as $id => $match ) { if ( $match['rr_status'] === $args['meta_query'][0]['value'] ) { $this->posts[] = (object) array( 'ID' => $id ); } } } } }
require __DIR__ . '/../radio-rubben-next/inc/home-universes.php';
$count = 0;
function check( $condition, $message ) { global $count; if ( ! $condition ) { throw new Exception( $message ); } $count++; }
$now = 10000;
$live = array( 'live' => true, 'observed_at' => 9990, 'expires_at' => 10200, 'stream_url' => 'https://example.invalid/listen' );
check( 'radio' === rr_home_focus( array(), array(), false, $now ), 'Radio default' );
check( 'news' === rr_home_focus( array(), array(), true, $now ), 'Major story promotion' );
check( 'radio' === rr_home_focus( $live, array(), true, $now ), 'Live radio before news' );
check( 'sport' === rr_home_focus( $live, $live, true, $now ), 'Live match before radio' );
check( ! rr_home_signal_live( array( 'live' => true ), $now ), 'Untimed boolean rejected' );
foreach ( array( array( 'observed_at' => 10001 ), array( 'observed_at' => 9300 ), array( 'expires_at' => 10000 ), array( 'expires_at' => 11000 ), array( 'live' => 'true' ) ) as $bad ) {
    check( ! rr_home_signal_live( array_merge( $live, $bad ), $now ), 'Stale, future or invalid signal rejected' );
}
check( 'news' === rr_home_focus( array_merge( $live, array( 'stream_url' => '' ) ), array(), true, $now ), 'No stream cannot promote radio live' );
check( ! rr_home_sport_data()['live'], 'Open vote is not live evidence' );
$mods['rr_stream_url'] = 'javascript:alert(1)';
check( '' === rr_home_radio_data()['stream_url'], 'Reject unsafe stream URL' );
$filters['rr_home_radio_data'] = array( 'stream_url' => 'data:text/html,test', 'title' => array() );
check( '' === rr_home_radio_data()['stream_url'] && '' === rr_home_radio_data()['title'], 'Normalize provider data' );
$news = rr_home_article_query()->args;
$sports = rr_home_article_query( true )->args;
check( 'publish' === $news['post_status'] && false === $news['has_password'], 'Public approved posts only' );
check( array( 8, 9 ) === $news['category__not_in'] && array( 8, 9 ) === $sports['category__in'], 'Disjoint news and sport descendants' );
check( null === rr_home_major_story(), 'No implicit sticky promotion' );
$mods['rr_home_major_story_id'] = 12;
$mods['rr_home_major_story_until'] = time() + 300;
$posts[12] = (object) array( 'ID' => 12, 'post_type' => 'post', 'post_status' => 'draft', 'post_password' => '', 'category' => 1 );
check( null === rr_home_major_story(), 'Draft cannot dominate' );
$posts[12]->post_status = 'publish';
$posts[12]->post_password = 'private';
check( null === rr_home_major_story(), 'Protected post cannot dominate' );
$posts[12]->post_password = '';
$posts[12]->category = 9;
check( null === rr_home_major_story(), 'Sport descendant cannot dominate news' );
$posts[12]->category = 1;
check( 12 === rr_home_major_story()->ID, 'Approved local story can dominate' );
$mods['rr_home_major_story_until'] = time() - 1;
check( null === rr_home_major_story(), 'Expired editorial promotion' );
check( ! rr_home_universes_enabled(), 'Classic remains default' );
$mods['rr_front_layout'] = 'universes';
check( rr_home_universes_enabled(), 'Explicit staging opt-in' );
check( rr_home_universes_active(), 'Opt-in static front page active' );
$test_options['show_on_front'] = 'posts';
check( ! rr_home_universes_active(), 'Posts archive preserves player and header' );
$test_options['show_on_front'] = 'page'; $test_password = true;
check( ! rr_home_universes_active(), 'Protected front page preserves header' );
$test_password = false; $test_front = false;
check( ! rr_home_universes_active(), 'Other pages unchanged' ); $test_front = true;
$base_match = array( 'rr_home_team' => 'Bremnes', 'rr_away_team' => 'Example', 'rr_kickoff' => date( 'c', time()+3600 ), 'rr_last_synced' => date( 'c' ), 'rr_status' => 'live', 'rr_score_home' => '0', 'rr_score_away' => '0' );
$test_matches = array( 30 => $base_match );
check( rr_home_sport_data()['live'] && 'live' === rr_home_sport_data()['match']['status'], 'Fresh RRLive adapter promotes match' );
$test_matches[30]['rr_last_synced'] = date( 'c', time()-700 );
check( ! rr_home_sport_data()['live'], 'Stale RRLive record cannot promote match' );
$test_matches[30]['rr_status'] = 'finished';
check( 'Bremnes 0–0 Example' === rr_home_sport_data()['last_result'], 'Confirmed zero score is preserved' );
$test_matches[30]['rr_score_away'] = '';
check( '' === rr_home_sport_data()['last_result'], 'Missing result is not invented as zero' );
$test_matches[30] = array_merge( $base_match, array( 'rr_status' => 'scheduled' ) );
$test_matches[31] = array_merge( $base_match, array( 'rr_status' => 'scheduled', 'rr_kickoff' => date( 'c', time()+1800 ) ) );
$scheduled = rr_home_sport_data();
check( 2 === count( $scheduled['upcoming'] ) && 'https://example.invalid/match/31' === $scheduled['match']['url'], 'Nearest fixture first with future fixtures' );
$test_matches[31]['rr_kickoff'] = date( 'c', time()-1800 );
check( 'https://example.invalid/match/30' === rr_home_sport_data()['match']['url'], 'Passed kickoff never becomes live' );
$mods['rr_home_major_story_until'] = ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( '+1 hour' )->format( 'Y-m-d\TH:i' );
check( 12 === rr_home_major_story()->ID, 'Local editorial expiry follows site timezone' );
$mods['rr_home_major_story_until'] = '2026-02-31T12:00';
check( null === rr_home_major_story(), 'Invalid calendar deadline rejected' );
$filters['rr_home_sport_data'] = array( 'match' => (object) array(), 'rrlive_url' => 'javascript:alert(1)', 'last_result' => array(), 'upcoming' => 'bad' );
$invalid = rr_home_sport_data();
check( array() === $invalid['match'] && '' === $invalid['rrlive_url'] && '' === $invalid['last_result'] && array() === $invalid['upcoming'], 'Malformed provider data is safe' );
$filters['rr_home_sport_data'] = array( 'live' => true, 'match' => array( 'status' => 'live' ) );
check( 'unknown' === rr_home_sport_data()['match']['status'], 'Untimed provider cannot label card live' );
echo "$count home universe checks passed\n";
