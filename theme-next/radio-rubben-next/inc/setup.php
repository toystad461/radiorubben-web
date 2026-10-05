<?php
defined( 'ABSPATH' ) || exit;
function rr_theme_setup() {
    load_theme_textdomain( 'radio-rubben-next', get_template_directory() . '/languages' );
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'automatic-feed-links' );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'align-wide' );
    add_theme_support( 'editor-styles' );
    add_theme_support( 'wp-block-styles' );
    add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
    add_theme_support( 'custom-logo', array( 'height' => 300, 'width' => 900, 'flex-height' => true, 'flex-width' => true ) );
    add_image_size( 'rr-card', 720, 405, true );
    add_image_size( 'rr-hero', 1600, 900, false );
    register_nav_menus( array( 'primary' => __( 'Hovedmeny', 'radio-rubben-next' ), 'footer' => __( 'Footermeny', 'radio-rubben-next' ) ) );
    add_editor_style( array( 'assets/css/editor.css', 'assets/css/components.css', 'assets/css/page-layouts.css' ) );
}
add_action( 'after_setup_theme', 'rr_theme_setup' );
function rr_theme_assets() {
    $previous = array();
    foreach ( array( 'theme', 'design-v13', 'member-hub', 'mobile-shell', 'components', 'brand-profile', 'page-layouts' ) as $style ) {
        $handle = 'rr-next-' . $style;
        wp_enqueue_style( $handle, get_theme_file_uri( '/assets/css/' . $style . '.css' ), $previous, 'page-layouts' === $style ? '2026.10.05.1' : ( 'brand-profile' === $style ? '2026.10.01.1' : RR_THEME_VERSION ) );
        $previous = array( $handle );
    }
    if ( is_front_page() ) {
        wp_enqueue_style( 'rr-next-home', get_theme_file_uri( '/assets/css/home-tidy.css' ), $previous, RR_THEME_VERSION );
    }
    if ( is_singular( 'rr_match' ) || is_page( 'rrlive' ) ) {
        wp_enqueue_style( 'rr-next-live', get_theme_file_uri( '/assets/css/rrlive.css' ), $previous, RR_THEME_VERSION );
    }
    if ( is_child_theme() ) {
        wp_enqueue_style( 'rr-next-child', get_stylesheet_uri(), $previous, wp_get_theme()->get( 'Version' ) );
    }
    wp_enqueue_script( 'rr-next-ui', get_theme_file_uri( '/assets/js/ui.js' ), array(), RR_THEME_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
    wp_localize_script( 'rr-next-ui', 'RR_ONE', array( 'streamUrl' => esc_url_raw( rr_theme_mod( 'rr_stream_url', '' ), array( 'https', 'http' ) ) ) );
    if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
        wp_enqueue_script( 'comment-reply' );
    }
}
add_action( 'wp_enqueue_scripts', 'rr_theme_assets' );
add_filter( 'body_class', function ( $classes ) {
    if ( is_front_page() ) { $classes[] = 'rr-front'; }
    if ( is_singular( 'post' ) ) { $classes[] = 'rr-single-post'; }
    $classes[] = 'rr-next';
    return $classes;
} );
add_action( 'init', function () {
    register_block_pattern_category( 'radio-rubben', array( 'label' => __( 'Radio Rubben', 'radio-rubben-next' ) ) );
    register_block_style( 'core/group', array( 'name' => 'rr-panel', 'label' => __( 'Rubben-kort', 'radio-rubben-next' ) ) );
} );
function rr_theme_customize( $manager ) {
    $manager->add_section( 'rr_presentation', array( 'title' => __( 'Radio Rubben – presentasjon', 'radio-rubben-next' ) ) );
    foreach ( array( 'rr_stream_url' => array( 'Strøm-URL', 'url', 'esc_url_raw' ), 'rr_now_artist' => array( 'Artist / program', 'text', 'sanitize_text_field' ), 'rr_now_title' => array( 'Låt / undertittel', 'text', 'sanitize_text_field' ), 'rr_email' => array( 'Kontaktadresse', 'email', 'sanitize_email' ), 'rr_phone' => array( 'Telefon', 'text', 'sanitize_text_field' ), 'rr_address' => array( 'Adresse', 'text', 'sanitize_text_field' ), 'rr_orgnr' => array( 'Organisasjonsnummer', 'text', 'sanitize_text_field' ), 'rr_cf7_shortcode' => array( 'Contact Form 7-kortkode', 'text', 'sanitize_text_field' ) ) as $key => $field ) {
        $manager->add_setting( $key, array( 'default' => rr_theme_mod( $key, '' ), 'sanitize_callback' => $field[2] ) );
        $manager->add_control( $key, array( 'label' => $field[0], 'type' => $field[1], 'section' => 'rr_presentation' ) );
    }
    $manager->add_setting( 'rr_live_status', array( 'default' => rr_theme_mod( 'rr_live_status', false ), 'sanitize_callback' => 'rest_sanitize_boolean' ) );
    $manager->add_control( 'rr_live_status', array( 'label' => __( 'Sendingen er direkte', 'radio-rubben-next' ), 'type' => 'checkbox', 'section' => 'rr_presentation' ) );
    $manager->add_setting( 'rr_front_layout', array( 'default' => 'classic', 'sanitize_callback' => static function ( $value ) { return in_array( $value, array( 'classic', 'content' ), true ) ? $value : 'classic'; } ) );
    $manager->add_control( 'rr_front_layout', array( 'label' => __( 'Forsidelayout', 'radio-rubben-next' ), 'type' => 'select', 'choices' => array( 'classic' => 'Dagens Radio Rubben-layout', 'content' => 'Sideinnhold / blokkmønstre' ), 'section' => 'rr_presentation' ) );
    $manager->add_setting( 'rr_portrait_id', array( 'default' => rr_theme_mod( 'rr_portrait_id', 0 ), 'sanitize_callback' => 'absint' ) );
    $manager->add_control( new WP_Customize_Media_Control( $manager, 'rr_portrait_id', array( 'label' => __( 'Portrett på forsiden', 'radio-rubben-next' ), 'section' => 'rr_presentation', 'mime_type' => 'image' ) ) );
}
add_action( 'customize_register', 'rr_theme_customize' );
