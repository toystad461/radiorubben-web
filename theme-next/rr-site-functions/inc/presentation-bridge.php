<?php
defined( 'ABSPATH' ) || exit;
/** Resolve known FIKS club logos locally; never fetch during a page request.
 * Stored match snapshots remain untouched. Unknown URLs retain their existing behavior.
 */
function rr_site_club_logo_url( $url ) {
    if ( ! is_string( $url ) ) { return ''; }
    $parts = wp_parse_url( $url );
    if ( ! is_array( $parts ) ) { return $url; }
    $host = strtolower( $parts['host'] ?? '' );
    $path = $parts['path'] ?? '';
    $pattern = 'images.fotball.no' === $host ? '~^/clublogos/([1-9][0-9]*)\.png$~' : ( 'logo.fotballdata.no' === $host ? '~^/logos/([1-9][0-9]*)\.jpg$~' : '' );
    if ( $pattern && preg_match( $pattern, $path, $matches ) ) {
        $file = 'assets/club-logos/' . $matches[1] . '.jpg';
        if ( is_file( RR_SITE_DIR . $file ) ) { return RR_SITE_URL . $file; }
    }
    // Legacy fixtures used an absolute upload URL for Bremnes instead of its FIKS logo.
    if ( in_array( $host, array( 'radiorubben.no', 'www.radiorubben.no', '127.0.0.1', 'localhost' ), true ) && '/wp-content/uploads/2026/09/Bremnes-laglogo.png' === $path ) {
        return RR_SITE_URL . 'assets/club-logos/827.jpg';
    }
    return $url;
}
add_filter( 'rr_theme_match_data', function ( $data ) {
    if ( is_array( $data ) ) {
        foreach ( array( 'home_logo', 'away_logo' ) as $key ) {
            if ( isset( $data[$key] ) ) { $data[$key] = rr_site_club_logo_url( $data[$key] ); }
        }
    }
    return $data;
}, 30 );

/** Explicit application template allowlist, independent of active theme paths. */
function rr_site_template( $name ) {
    if ( in_array( $name, array( 'member-profile', 'member-support', 'member-account-actions' ), true ) ) {
        require RR_SITE_DIR . 'template-parts/' . $name . '.php';
    }
}
// Compatibility for application views with unrelated themes, without redefining Next's helpers.
if ( ! function_exists( 'rr_one_logo_url' ) ) {
    function rr_one_logo_url() {
        $id = get_theme_mod( 'custom_logo' );
        if ( ! $id ) { $mods = get_option( 'theme_mods_radio-rubben-wordpress-v1', array() ); $id = $mods['custom_logo'] ?? 0; }
        return $id ? ( wp_get_attachment_image_url( (int) $id, 'full' ) ?: '' ) : '';
    }
}
add_filter( 'template_include', function ( $template ) {
    // Keep the existing member URL/ID and all account controls, not a new replacement page.
    if ( is_page( 736 ) && ! post_password_required() ) { return RR_SITE_DIR . 'templates/member-page.php'; }
    return $template;
}, 30 );
add_filter( 'rr_theme_match_data', function ( $data, $context ) {
    if ( $data ) { return $data; } // An explicitly configured provider has precedence.
    $active = rr_poll_active_vote();
    if ( $active ) {
        $selected = rr_poll_selected_match_data();
        return array_merge( $selected, $active, array( 'status' => 'live', 'cta' => 'Følg kampen og stem →' ) );
    }
    $next = rr_poll_next_match_data();
    if ( ! $next ) { return array(); }
    if ( 'header' === $context ) {
        $mods = get_option( 'theme_mods_radio-rubben-wordpress-v1', array() );
        $days = max( 0, min( 60, (int) ( $mods['rr_next_match_banner_days'] ?? 7 ) ) );
        if ( ! $days || strtotime( $next['kickoff'] ) > time() + $days * DAY_IN_SECONDS ) { return array(); }
    }
    $next['status'] = 'scheduled';
    return $next;
}, 20, 2 );
add_action( 'rr_theme_home_after_news', function () {
    if ( ! shortcode_exists( 'rubben_rss_cards' ) ) { return; }
    if ( function_exists( 'rr_theme_feed_items' ) && rr_theme_feed_items() ) { return; }
    // Reuse RR_News' renderer and cache. No new fetching, scheduling or duplication here.
    require RR_SITE_DIR . 'templates/feed.php';
} );
