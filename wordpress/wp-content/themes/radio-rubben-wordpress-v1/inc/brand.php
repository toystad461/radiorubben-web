<?php
/** Approved profile assets, without changing stored media or site settings. */
defined( 'ABSPATH' ) || exit;

function rr_brand_uses_profile_logos() {
    return (bool) ( function_exists( 'rr_theme_mod' )
        ? rr_theme_mod( 'rr_use_profile_logos', true )
        : get_theme_mod( 'rr_use_profile_logos', true ) );
}

function rr_brand_asset_url( $file ) {
    return get_template_directory_uri() . '/assets/brand/2026-09-30/' . $file;
}

function rr_brand_logo_url( $variant = 'compact' ) {
    $files = array(
        'compact' => 'SVG/07-Uten-verdilinje-hvit.svg',
        'horizontal' => 'SVG/11-Liggende-transparent.svg',
    );
    return rr_brand_asset_url( $files[ $variant ] ?? $files['compact'] );
}

function rr_brand_header_logo() {
    if ( ! rr_brand_uses_profile_logos() ) {
        $url = function_exists( 'rr_theme_logo_url' ) ? rr_theme_logo_url() : rr_one_logo_url();
        echo '<img src="' . esc_url( $url ) . '" alt="Radio Rubben">';
        return;
    }
    echo '<picture class="rr-profile-logo">';
    echo '<source media="(min-width: 1101px)" srcset="' . esc_url( rr_brand_logo_url( 'horizontal' ) ) . '" width="2600" height="510">';
    echo '<img src="' . esc_url( rr_brand_logo_url() ) . '" width="1700" height="670" alt="Radio Rubben" decoding="async">';
    echo '</picture>';
}

/** WordPress emits the actual icon links, including favicon and Apple touch icon. */
function rr_brand_site_icon_url( $url, $size, $blog_id ) {
    if ( ! rr_brand_uses_profile_logos() || ( $blog_id && (int) $blog_id !== get_current_blog_id() ) ) {
        return $url;
    }
    foreach ( array( 16, 32, 48, 180, 192, 512 ) as $available ) {
        if ( (int) $size <= $available ) {
            return rr_brand_asset_url( 'Ikoner/ikon-' . $available . '.png' );
        }
    }
    return rr_brand_asset_url( 'Ikoner/ikon-512.png' );
}
add_filter( 'get_site_icon_url', 'rr_brand_site_icon_url', 10, 3 );

function rr_brand_customize( $manager ) {
    $manager->add_setting( 'rr_use_profile_logos', array(
        'default' => true,
        'sanitize_callback' => 'rest_sanitize_boolean',
    ) );
    $manager->add_control( 'rr_use_profile_logos', array(
        'label' => 'Bruk Radio Rubbens logopakke fra 30.09.2026',
        'description' => 'Tilpassede logoer og nettstedikon beholdes lagret. Slå av for å bruke dem igjen.',
        'section' => 'title_tagline',
        'type' => 'checkbox',
    ) );
}
add_action( 'customize_register', 'rr_brand_customize' );
