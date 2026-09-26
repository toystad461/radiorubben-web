<?php
/**
 * Plugin Name: Radio Rubben Editorial Contract
 * Description: Vedvarende, tilgangskontrollert journalist- og kildemetadata for WordPress og senere VPS. Ingen AI-motor.
 * Version: 1.0.0-rc.1
 * Requires at least: 6.6
 * Requires PHP: 8.2
 * License: GPL-2.0-or-later
 * Text Domain: rr-editorial-contract
 */
defined( 'ABSPATH' ) || exit;
/** A stable public identity, never a WordPress login, capability or API credential. */
function rr_editorial_sanitize_id( $value ) {
    return is_string( $value ) && preg_match( '/^rr-[a-z0-9]+(?:-[a-z0-9]+)*$/D', $value ) ? $value : '';
}
function rr_editorial_can_edit_meta( $allowed, $key, $post_id ) {
    return current_user_can( 'edit_post', $post_id );
}
add_action( 'init', function () {
    foreach ( array( 'post', 'page' ) as $type ) {
        foreach ( array(
            '_rr_journalist_id' => 'rr_editorial_sanitize_id',
            '_rr_source_name' => 'sanitize_text_field',
            '_rr_source_url' => 'esc_url_raw',
            '_rr_editor_name' => 'sanitize_text_field',
        ) as $key => $sanitize ) {
            register_post_meta( $type, $key, array(
                'type' => 'string', 'single' => true, 'default' => '',
                'sanitize_callback' => $sanitize,
                'auth_callback' => 'rr_editorial_can_edit_meta',
                'show_in_rest' => array( 'schema' => array( 'type' => 'string', 'context' => array( 'view', 'edit' ) ) ),
                'revisions_enabled' => true,
            ) );
        }
    }
} );
add_action( 'add_meta_boxes', function () {
    foreach ( array( 'post', 'page' ) as $type ) {
        add_meta_box( 'rr-editorial', __( 'Radio Rubben – byline og kilde', 'rr-editorial-contract' ), 'rr_editorial_metabox', $type, 'side' );
    }
} );
function rr_editorial_metabox( $post ) {
    wp_nonce_field( 'rr_editorial_save', 'rr_editorial_nonce' );
    $fields = array( '_rr_journalist_id' => 'Journalist-ID (rr-fotball / rr-lokal)', '_rr_source_name' => 'Kildenavn', '_rr_source_url' => 'Kilde-URL', '_rr_editor_name' => 'Redigert av (bare når bekreftet)' );
    foreach ( $fields as $key => $label ) {
        echo '<p><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label><input class="widefat" type="' . ( '_rr_source_url' === $key ? 'url' : 'text' ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( get_post_meta( $post->ID, $key, true ) ) . '"></p>';
    }
    echo '<p>' . esc_html__( 'Tom journalist-ID bruker vanlig WordPress-forfatter. Ingen publisering eller AI-generering startes her.', 'rr-editorial-contract' ) . '</p>';
}
add_action( 'save_post', function ( $post_id ) {
    if ( ! in_array( get_post_type( $post_id ), array( 'post', 'page' ), true ) ) { return; }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
    if ( ! isset( $_POST['rr_editorial_nonce'] ) || ! is_string( $_POST['rr_editorial_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rr_editorial_nonce'] ) ), 'rr_editorial_save' ) ) { return; }
    foreach ( array( '_rr_journalist_id', '_rr_source_name', '_rr_source_url', '_rr_editor_name' ) as $key ) {
        if ( isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ) { update_post_meta( $post_id, $key, wp_unslash( $_POST[ $key ] ) ); }
    }
} );
