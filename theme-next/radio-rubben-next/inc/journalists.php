<?php
defined( 'ABSPATH' ) || exit;
/** Public presentation only. Profiles can be supplied by an independent plugin. */
function rr_theme_journalists() {
    $profiles = apply_filters( 'rr_journalist_profiles', array(
        'rr-fotball' => array( 'name' => 'Fotballredaksjonen', 'role' => 'Digital fotballjournalist', 'description' => 'Fotball, lokale lag og Bremnes IL.', 'type' => 'digital' ),
        'rr-lokal' => array( 'name' => 'Lokalredaksjonen', 'role' => 'Digital lokaljournalist', 'description' => 'Bømlo, lokale nyheter og lokalsamfunnet.', 'type' => 'digital' ),
    ) );
    $valid = array();
    if ( ! is_array( $profiles ) ) { return $valid; }
    foreach ( $profiles as $id => $profile ) {
        if ( ! is_string( $id ) || ! preg_match( '/^rr-[a-z0-9]+(?:-[a-z0-9]+)*$/D', $id ) || ! is_array( $profile ) ) { continue; }
        $valid[ $id ] = array( 'id' => $id );
        foreach ( array( 'name', 'role', 'description', 'avatar_url', 'url', 'type' ) as $key ) { $valid[ $id ][ $key ] = rr_theme_text( $profile[ $key ] ?? '' ); }
        if ( '' === $valid[ $id ]['name'] ) { $valid[ $id ]['name'] = $id; }
    }
    return $valid;
}
function rr_theme_journalist_id( $post_id ) {
    $stored = get_post_meta( $post_id, '_rr_journalist_id', true );
    $id = rr_theme_text( $stored );
    // Existing robot's explicit marker, never inferred from a category or headline.
    if ( '' === $id && get_post_meta( $post_id, '_rrfr_ai_match', true ) ) { $id = 'rr-fotball'; }
    return rr_theme_text( apply_filters( 'rr_theme_journalist_id', $id, $post_id ) );
}
function rr_theme_byline( $post_id = 0 ) {
    $post_id = $post_id ?: get_the_ID();
    $id = rr_theme_journalist_id( $post_id );
    $profiles = rr_theme_journalists();
    if ( $id && isset( $profiles[ $id ] ) ) { return $profiles[ $id ]; }
    if ( $id ) { return array( 'id' => $id, 'name' => $id, 'role' => __( 'Redaksjonell identitet', 'radio-rubben-next' ), 'type' => 'unknown', 'url' => '', 'avatar_url' => '' ); }
    $author = (int) get_post_field( 'post_author', $post_id );
    return array( 'id' => '', 'name' => get_the_author_meta( 'display_name', $author ), 'role' => '', 'type' => 'human', 'url' => get_author_posts_url( $author ), 'avatar_url' => '' );
}
