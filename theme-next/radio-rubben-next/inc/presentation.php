<?php
defined( 'ABSPATH' ) || exit;
/** Read-only fallback; theme activation never copies or changes production settings. */
function rr_theme_mod( $key, $default = false ) {
    $current = get_theme_mods();
    if ( is_array( $current ) && array_key_exists( $key, $current ) ) { return get_theme_mod( $key ); }
    if ( is_child_theme() ) {
        $parent_mods = get_option( 'theme_mods_' . get_template(), array() );
        if ( is_array( $parent_mods ) && array_key_exists( $key, $parent_mods ) ) { return $parent_mods[ $key ]; }
    }
    $legacy = get_option( 'theme_mods_radio-rubben-wordpress-v1', array() );
    if ( 'rr_portrait_id' === $key && $legacy && wp_attachment_is_image( 760 ) ) { return 760; }
    return is_array( $legacy ) && array_key_exists( $key, $legacy ) ? $legacy[ $key ] : $default;
}
function rr_theme_text( $value ) { return is_scalar( $value ) ? (string) $value : ''; }
function rr_theme_member_label() {
    return function_exists( 'rr_member_entry_label' ) ? rr_member_entry_label() : ( is_user_logged_in() ? __( 'Åpne Min Rubben', 'radio-rubben-next' ) : __( 'Logg inn på Min Rubben', 'radio-rubben-next' ) );
}
function rr_theme_logo_url() {
    if ( rr_brand_uses_profile_logos() ) { return rr_brand_logo_url(); }
    $src = wp_get_attachment_image_url( absint( rr_theme_mod( 'custom_logo', 0 ) ), 'full' );
    return $src ?: get_theme_file_uri( '/assets/images/radio-rubben-logo.webp' );
}
function rr_theme_page_url( $slugs, $fallback = '/' ) {
    foreach ( (array) $slugs as $slug ) {
        $page = get_page_by_path( $slug );
        if ( $page && 'publish' === $page->post_status ) { return get_permalink( $page ); }
    }
    return home_url( $fallback );
}
function rr_theme_fallback_menu() {
    $items = array( '/' => 'Hjem', '/lytt/' => 'Lytt', '/reimagined/' => 'Reimagined', '/pa-radio-rubben/' => 'På Radio Rubben', '/nyheter/' => 'Aktuelt', '/sport/' => 'Sport', '/om-radio-rubben/' => 'Om', '/samarbeid/' => 'Samarbeid', '/kontakt/' => 'Kontakt' );
    echo '<ul>';
    foreach ( $items as $path => $label ) { echo '<li><a href="' . esc_url( home_url( $path ) ) . '">' . esc_html( $label ) . '</a></li>'; }
    echo '</ul>';
}
function rr_theme_menu( $location ) {
    $locations = (array) rr_theme_mod( 'nav_menu_locations', array() );
    wp_nav_menu( array( 'theme_location' => $location, 'menu' => absint( $locations[ $location ] ?? 0 ), 'container' => false, 'fallback_cb' => 'primary' === $location ? 'rr_theme_fallback_menu' : false, 'items_wrap' => '<ul>%3$s</ul>' ) );
}
/** Components are selected from a fixed allowlist, never from request input. */
function rr_theme_component( $name, $data = array() ) {
    if ( ! in_array( $name, array( 'match-card', 'feed-card', 'sponsor', 'program-card', 'journalist', 'byline', 'source', 'radio-card', 'live-card' ), true ) ) { return; }
    get_template_part( 'template-parts/components/' . $name, null, is_array( $data ) ? $data : array() );
}
function rr_theme_match_data( $context = 'card' ) {
    $data = array();
    if ( 'header' === $context ) {
        $vote = function_exists( 'rr_poll_active_vote' ) ? rr_poll_active_vote() : array();
        if ( is_array( $vote ) && $vote ) { $data = array_merge( $vote, array( 'status' => 'live', 'label' => 'KAMPEN ER I GANG', 'cta' => 'Stem på Dagens Bremnesing' ) ); }
        elseif ( function_exists( 'rr_poll_next_match_header' ) ) { $data = rr_poll_next_match_header(); }
    }
    $data = apply_filters( 'rr_theme_match_data', is_array( $data ) ? $data : array(), $context, get_queried_object_id() );
    return is_array( $data ) ? $data : array();
}
function rr_theme_feed_items() {
    $items = apply_filters( 'rr_theme_feed_items', array(), 'bomlo-kommune' );
    return is_array( $items ) ? array_slice( $items, 0, 6 ) : array();
}
function rr_theme_sponsors( $slot ) {
    $items = apply_filters( 'rr_theme_sponsors', array(), $slot );
    if ( ! is_array( $items ) ) { return; }
    foreach ( array_slice( $items, 0, 8 ) as $item ) { if ( is_array( $item ) ) { rr_theme_component( 'sponsor', $item ); } }
}
function rr_theme_pagination( $query = null ) {
    $query = $query ?: $GLOBALS['wp_query'];
    $links = paginate_links( array( 'total' => $query->max_num_pages, 'current' => max( 1, get_query_var( 'paged' ), get_query_var( 'page' ) ), 'mid_size' => 1, 'prev_text' => __( '← Nyere', 'radio-rubben-next' ), 'next_text' => __( 'Eldre →', 'radio-rubben-next' ), 'type' => 'list' ) );
    if ( $links ) { echo '<nav class="rr-pagination" aria-label="' . esc_attr__( 'Sideinndeling', 'radio-rubben-next' ) . '">' . wp_kses_post( $links ) . '</nav>'; }
}
function rr_theme_category_nav() {
    $terms = get_terms( array( 'taxonomy' => 'category', 'slug' => array( 'sport', 'fotball', 'bremnes-il', 'herrer', 'damer', 'leder' ), 'hide_empty' => false ) );
    if ( is_wp_error( $terms ) || ! $terms ) { return; }
    $ordered = array_flip( array( 'sport', 'fotball', 'bremnes-il', 'herrer', 'damer', 'leder' ) );
    usort( $terms, static function ( $a, $b ) use ( $ordered ) { return $ordered[ $a->slug ] <=> $ordered[ $b->slug ]; } );
    echo '<nav class="rr-taxonomy-nav" aria-label="' . esc_attr__( 'Sport og lederartikler', 'radio-rubben-next' ) . '">';
    foreach ( $terms as $term ) {
        $url = get_term_link( $term );
        if ( ! is_wp_error( $url ) ) { echo '<a href="' . esc_url( $url ) . '"' . ( is_category( $term->term_id ) ? ' aria-current="page"' : '' ) . '>' . esc_html( $term->name ) . '</a>'; }
    }
    echo '</nav>';
}

/** Normalize the existing RRLive read contract without owning its data or routes. */
function rr_theme_rrlive_data( $post_id ) {
    $keys = array( 'rr_fiks_id', 'rr_ntb_id', 'rr_sport', 'rr_competition', 'rr_round', 'rr_kickoff', 'rr_venue', 'rr_home_team', 'rr_away_team', 'rr_home_team_id', 'rr_away_team_id', 'rr_club_name', 'rr_home_logo', 'rr_away_logo', 'rr_status', 'rr_minute', 'rr_score_home', 'rr_score_away', 'rr_data_source', 'rr_stream_provider', 'rr_stream_url', 'rr_stream_requires_subscription', 'rr_radio_live', 'rr_radio_url', 'rr_last_synced' );
    $raw = function_exists( 'rrlive_get_match_data' ) ? rrlive_get_match_data( $post_id ) : array();
    $raw = apply_filters( 'rr_theme_rrlive_data', is_array( $raw ) ? $raw : array(), $post_id );
    $raw = is_array( $raw ) ? $raw : array();
    $data = array();
    foreach ( $keys as $key ) { $data[ $key ] = rr_theme_text( $raw[ $key ] ?? get_post_meta( $post_id, $key, true ) ); }
    foreach ( array( 'rr_events', 'rr_home_lineup', 'rr_away_lineup', 'rr_table' ) as $key ) {
        $rows = $raw[ $key ] ?? get_post_meta( $post_id, $key, true );
        $data[ $key ] = array();
        if ( ! is_array( $rows ) ) { continue; }
        foreach ( array_slice( $rows, 0, 500 ) as $row ) {
            if ( ! is_array( $row ) ) { continue; }
            $clean = array();
            foreach ( $row as $field => $value ) { $clean[ $field ] = rr_theme_text( $value ); }
            $data[ $key ][] = array_merge( array( 'note' => '' ), $clean );
        }
    }
    return $data;
}
