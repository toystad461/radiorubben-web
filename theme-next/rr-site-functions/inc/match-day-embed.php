<?php
/** Public match/voting view; the existing canonical endpoint owns all actions. */
defined( 'ABSPATH' ) || exit;

function rr_site_prepare_match_day() {
    if ( is_admin() || is_feed() || ! is_singular( 'page' ) || post_password_required() ) { return; }
    $post = get_queried_object();
    if ( ! $post || ! has_shortcode( $post->post_content, 'rr_match_day' ) ) { return; }
    // Set before theme output. Personalized vote/session tokens must never be cached.
    if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
    nocache_headers();
    header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
    header( 'Vary: Cookie', false );
    if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { return; }
    // A content page never becomes an API or speakerboard through query parameters.
    $saved_query = $_GET;
    unset( $_GET['rr_poll_control'], $_GET['rr_poll_api'], $_GET['rr_dashboard_section'] );
    $rr_embedded = true;
    ob_start();
    try {
        require RR_SITE_DIR . 'inc/bremnes-poll-test.php';
        $GLOBALS['rr_site_match_day_view'] = array( 'post' => (int) $post->ID, 'html' => ob_get_contents(), 'used' => false );
    } finally {
        ob_end_clean();
        $_GET = $saved_query;
    }
}
add_action( 'template_redirect', 'rr_site_prepare_match_day', 20 );

function rr_site_match_day_shortcode() {
    $view = &$GLOBALS['rr_site_match_day_view'];
    if ( ! is_array( $view ) || $view['used'] || ! in_the_loop() || ! is_main_query() || get_the_ID() !== $view['post'] || post_password_required() ) { return ''; }
    $view['used'] = true; // Shared renderer has fixed IDs: exactly one instance per response.
    return $view['html'];
}
add_shortcode( 'rr_match_day', 'rr_site_match_day_shortcode' );

/** Delegate player data and markup to its existing plugin, including empty states. */
function rr_site_player_matches_shortcode() {
    if ( ! shortcode_exists( 'rr_spillerkamper' ) ) {
        return '<p class="rr-module-empty">Spilleroversikten er ikke tilgjengelig akkurat nå.</p>';
    }
    $html = do_shortcode( '[rr_spillerkamper]' );
    return trim( $html ) !== '' ? $html : '<p class="rr-module-empty">Ingen spillerkamper er klare for visning ennå.</p>';
}
add_shortcode( 'rr_player_matches', 'rr_site_player_matches_shortcode' );
