<?php
defined( 'ABSPATH' ) || exit;
// Legacy public presentation helpers. No former application files are loaded.
if ( ! function_exists( 'rr_one_logo_url' ) ) { function rr_one_logo_url() { return rr_theme_logo_url(); } }
if ( ! function_exists( 'rr_one_get_first_existing_url' ) ) { function rr_one_get_first_existing_url( $slugs, $fallback = '/' ) { return rr_theme_page_url( $slugs, $fallback ); } }
if ( ! function_exists( 'rr_one_post_meta' ) ) { function rr_one_post_meta() { rr_theme_component( 'byline' ); } }
if ( ! function_exists( 'rr_one_pagination' ) ) { function rr_one_pagination() { rr_theme_pagination(); } }
if ( ! function_exists( 'rr_one_contact_form' ) ) {
    function rr_one_contact_form() {
        $default = get_option( 'theme_mods_radio-rubben-wordpress-v1' ) ? '[contact-form-7 id="c62b2ec" title="Kontaktskjema 1"]' : '';
        $shortcode = rr_theme_text( rr_theme_mod( 'rr_cf7_shortcode', $default ) );
        if ( shortcode_exists( 'contact-form-7' ) && preg_match( '/^\[contact-form-7\s[^\[\]]+\]$/D', $shortcode ) ) { return do_shortcode( $shortcode ); }
        $email = sanitize_email( rr_theme_mod( 'rr_email', '' ) );
        return $email ? '<p><a href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a></p>' : '';
    }
}
add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'switch_themes' ) ) { return; }
    if ( get_option( 'theme_mods_radio-rubben-wordpress-v1' ) && ! function_exists( 'rr_poll_active_vote' ) ) {
        echo '<div class="notice notice-warning"><p>' . esc_html__( 'Radio Rubben Next: tidligere kampfunksjoner er ikke lastet. Les theme/docs/DEPLOY.md. Denne kandidaten skal testes på staging før produksjonsbytte.', 'radio-rubben-next' ) . '</p></div>';
    }
} );
