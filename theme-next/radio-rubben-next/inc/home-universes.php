<?php
defined( 'ABSPATH' ) || exit;

/** Optional presentation only: no imports, writes, cron or publishing. */
function rr_home_universes_enabled() {
    return 'universes' === rr_theme_mod( 'rr_front_layout', 'classic' );
}
function rr_home_universes_active() {
    return is_front_page() && rr_home_universes_enabled() && ! post_password_required() && 'posts' !== get_option( 'show_on_front' );
}

/** Explicit live signals expire. Unknown / stale signals never promote a section. */
function rr_home_signal_live( $signal, $now ) {
    return is_array( $signal ) && true === ( $signal['live'] ?? false )
        && is_numeric( $signal['observed_at'] ?? null ) && is_numeric( $signal['expires_at'] ?? null )
        && $signal['observed_at'] <= $now && $signal['observed_at'] >= $now - 600
        && $signal['expires_at'] > $now && $signal['expires_at'] <= $signal['observed_at'] + 600;
}

/** One auditable precedence: live match, live radio, approved major story, radio. */
function rr_home_focus( $radio, $sport, $major, $now ) {
    if ( rr_home_signal_live( $sport, $now ) ) { return 'sport'; }
    if ( rr_home_signal_live( $radio, $now ) && ! empty( $radio['stream_url'] ) ) { return 'radio'; }
    if ( $major ) { return 'news'; }
    return 'radio';
}

function rr_home_radio_data() {
    $settings = get_option( 'rr_radio_co_settings', array() );
    $settings = is_array( $settings ) ? $settings : array();
    $stream = rr_theme_text( rr_theme_mod( 'rr_stream_url', '' ) );
    $data = array(
        'stream_url' => esc_url_raw( $stream ?: ( $settings['stream_url'] ?? '' ), array( 'https', 'http' ) ),
        'artist' => rr_theme_text( rr_theme_mod( 'rr_now_artist', '' ) ),
        'title' => rr_theme_text( rr_theme_mod( 'rr_now_title', '' ) ),
        'next_program' => trim( rr_theme_text( rr_theme_mod( 'rr_next_artist', '' ) ) . ' ' . rr_theme_text( rr_theme_mod( 'rr_next_title', '' ) ) ),
        'live' => false,
    );
    // Read the existing plugin cache; page rendering never requests its upstream API.
    $cache = get_transient( 'rr_radio_co_status_data' );
    if ( is_array( $cache ) && is_array( $cache['current_track'] ?? null ) ) {
        $data['title'] = rr_theme_text( $cache['current_track']['title'] ?? $data['title'] );
        $data['artist'] = ''; // Radio.co title already contains artist; do not invent a split.
    }
    // Provider may supply fresh live/programme metadata; boolean theme mods have no timestamp.
    $provided = apply_filters( 'rr_home_radio_data', $data );
    $data = is_array( $provided ) ? array_merge( $data, $provided ) : $data;
    foreach ( array( 'artist', 'title', 'next_program' ) as $key ) { $data[$key] = rr_theme_text( $data[$key] ); }
    $data['stream_url'] = esc_url_raw( rr_theme_text( $data['stream_url'] ), array( 'https', 'http' ) );
    return $data;
}

/** Published, public articles only. Sport descendants are separated from local news. */
function rr_home_article_query( $sport = false ) {
    $category = get_category_by_slug( 'sport' );
    $ids = $category ? array_merge( array( $category->term_id ), get_term_children( $category->term_id, 'category' ) ) : array();
    if ( is_wp_error( $ids ) ) { $ids = $category ? array( $category->term_id ) : array(); }
    $args = array( 'post_type' => 'post', 'post_status' => 'publish', 'has_password' => false,
        'posts_per_page' => $sport ? 3 : 4, 'ignore_sticky_posts' => true, 'no_found_rows' => true );
    if ( $sport && ! $ids ) { $args['post__in'] = array( 0 ); }
    if ( $ids ) { $args[ $sport ? 'category__in' : 'category__not_in' ] = array_map( 'absint', $ids ); }
    return new WP_Query( $args );
}

/** Major-story promotion requires an editor-selected, fresh public news post. */
function rr_home_major_story() {
    $id = absint( rr_theme_mod( 'rr_home_major_story_id', 0 ) );
    $deadline = rr_theme_text( rr_theme_mod( 'rr_home_major_story_until', '' ) );
    $until = ctype_digit( $deadline ) ? (int) $deadline : 0;
    if ( ! $until && $deadline ) {
        $date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $deadline, wp_timezone() );
        if ( $date && $date->format( 'Y-m-d\TH:i' ) === $deadline ) { $until = $date->getTimestamp(); }
    }
    if ( ! $id || $until <= time() ) { return null; }
    $post = get_post( $id );
    if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status || $post->post_password || ! is_post_publicly_viewable( $post ) ) { return null; }
    $sport = get_category_by_slug( 'sport' );
    if ( $sport ) {
        $children = get_term_children( $sport->term_id, 'category' );
        $ids = array_merge( array( $sport->term_id ), is_wp_error( $children ) ? array() : $children );
        if ( has_category( $ids, $post ) ) { return null; }
    }
    return $post;
}

function rr_home_sport_data() {
    $data = array( 'match' => rr_theme_match_data( 'sport' ), 'live' => false, 'last_result' => '', 'upcoming' => array(), 'rrlive_url' => '' );
    // A voting-window flag from the compatibility bridge is not authoritative live status.
    if ( is_array( $data['match'] ) && 'live' === ( $data['match']['status'] ?? '' ) ) { $data['match']['status'] = 'unknown'; }
    $match_posts = array();
    foreach ( array( 'live' => 'DESC', 'finished' => 'DESC', 'scheduled' => 'ASC' ) as $status => $order ) {
        $conditions = array( array( 'key' => 'rr_status', 'value' => $status ) );
        if ( 'scheduled' === $status ) { $conditions[] = array( 'key' => 'rr_kickoff', 'value' => gmdate( 'Y-m-d', time() - 86400 ), 'compare' => '>=' ); }
        $matches = new WP_Query( array( 'post_type' => 'rr_match', 'post_status' => 'publish', 'has_password' => false,
            'posts_per_page' => 'scheduled' === $status ? 15 : 5, 'no_found_rows' => true,
            'meta_key' => 'rr_kickoff', 'orderby' => 'meta_value', 'order' => $order,
            'meta_query' => $conditions ) );
        $match_posts = array_merge( $match_posts, $matches->posts );
    }
    $upcoming = array(); $latest_result = 0;
    foreach ( $match_posts as $post ) {
        $m = rr_theme_rrlive_data( $post->ID );
        $kickoff = strtotime( $m['rr_kickoff'] ?? '' );
        $synced = strtotime( $m['rr_last_synced'] ?? '' );
        $home = rr_theme_text( $m['rr_home_team'] ?? '' ); $away = rr_theme_text( $m['rr_away_team'] ?? '' );
        if ( ! $kickoff || ! $home || ! $away ) { continue; }
        if ( ! $data['rrlive_url'] ) { $data['rrlive_url'] = get_permalink( $post ); }
        $signal = array( 'live' => 'live' === ( $m['rr_status'] ?? '' ), 'observed_at' => $synced ?: 0, 'expires_at' => ( $synced ?: 0 ) + 180 );
        $card = array( 'home' => $home, 'away' => $away, 'kickoff' => $m['rr_kickoff'], 'venue' => $m['rr_venue'] ?? '',
            'url' => get_permalink( $post ), 'score' => array( 'home' => $m['rr_score_home'] ?? '', 'away' => $m['rr_score_away'] ?? '' ) );
        if ( ! $data['live'] && rr_home_signal_live( $signal, time() ) ) {
            $data = array_merge( $data, $signal ); $data['rrlive_url'] = get_permalink( $post ); $data['match'] = array_merge( $card, array( 'status' => 'live', 'cta' => 'Følg kampen i RR Live →' ) );
        }
        if ( 'finished' === ( $m['rr_status'] ?? '' ) && $kickoff > $latest_result && ctype_digit( (string) ( $m['rr_score_home'] ?? '' ) ) && ctype_digit( (string) ( $m['rr_score_away'] ?? '' ) ) ) {
            $latest_result = $kickoff;
            $data['last_result'] = $home . ' ' . $m['rr_score_home'] . '–' . $m['rr_score_away'] . ' ' . $away;
        }
        if ( 'scheduled' === ( $m['rr_status'] ?? '' ) && $kickoff > time() ) {
            $upcoming[] = array( 'time' => $kickoff, 'text' => $home . ' – ' . $away . ' · ' . wp_date( 'j. F H:i', $kickoff ), 'card' => $card );
        }
    }
    usort( $upcoming, static function ( $a, $b ) { return $a['time'] <=> $b['time']; } );
    $data['upcoming'] = array_column( array_slice( $upcoming, 0, 4 ), 'text' );
    if ( ! $data['live'] && $upcoming ) { $data['match'] = array_merge( $upcoming[0]['card'], array( 'status' => 'scheduled' ) ); }
    // Never equate open voting or a passed kickoff with an actual live match.
    $provided = apply_filters( 'rr_home_sport_data', $data );
    $data = is_array( $provided ) ? array_merge( $data, $provided ) : $data;
    $data['match'] = is_array( $data['match'] ) ? $data['match'] : array();
    $data['last_result'] = rr_theme_text( $data['last_result'] );
    $data['rrlive_url'] = esc_url_raw( rr_theme_text( $data['rrlive_url'] ), array( 'https', 'http' ) );
    $data['upcoming'] = is_array( $data['upcoming'] ) ? array_map( 'rr_theme_text', array_slice( $data['upcoming'], 0, 4 ) ) : array();
    if ( 'live' === ( $data['match']['status'] ?? '' ) && ! rr_home_signal_live( $data, time() ) ) { $data['match']['status'] = 'unknown'; }
    return $data;
}

add_action( 'customize_register', function ( $manager ) {
    $setting = $manager->get_setting( 'rr_front_layout' );
    if ( $setting ) { $setting->sanitize_callback = static function ( $value ) {
        return in_array( $value, array( 'classic', 'content', 'universes' ), true ) ? $value : 'classic';
    }; }
    $control = $manager->get_control( 'rr_front_layout' );
    if ( $control ) { $control->choices['universes'] = 'Tre univers – stagingkandidat'; }
    foreach ( array( 'rr_home_major_story_id' => 'Stor lokal sak: publisert innlegg-ID' ) as $key => $label ) {
        $manager->add_setting( $key, array( 'default' => 0, 'sanitize_callback' => 'absint' ) );
        $manager->add_control( $key, array( 'label' => $label, 'type' => 'number', 'section' => 'rr_presentation' ) );
    }
    $manager->add_setting( 'rr_home_major_story_until', array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
    $manager->add_control( 'rr_home_major_story_until', array( 'label' => 'Løft saken fram til', 'description' => 'Dato og klokkeslett følger nettstedets tidssone.', 'type' => 'datetime-local', 'section' => 'rr_presentation' ) );
}, 20 );
add_action( 'wp_enqueue_scripts', function () {
    if ( ! rr_home_universes_active() ) { return; }
    wp_enqueue_style( 'rr-next-universes', get_theme_file_uri( '/assets/css/home-universes.css' ), array( 'rr-next-home' ), '2026.10.05.1' );
    // Reuse the single existing audio element and its play/pause/error handling.
    $radio = rr_home_radio_data();
    wp_localize_script( 'rr-next-ui', 'RR_ONE', array( 'streamUrl' => $radio['stream_url'] ) );
} );
