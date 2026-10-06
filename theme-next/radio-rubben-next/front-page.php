<?php
get_header();
if ( 'posts' === get_option( 'show_on_front' ) ) {
    get_template_part( 'template-parts/content/archive' );
} elseif ( post_password_required() ) {
    while ( have_posts() ) { the_post(); the_content(); }
} elseif ( 'content' === rr_theme_mod( 'rr_front_layout', 'classic' ) ) {
    echo '<div class="rr-wrap rr-section rr-content">';
    while ( have_posts() ) { the_post(); the_content(); wp_link_pages(); }
    echo '</div>';
} elseif ( rr_home_universes_enabled() ) {
    get_template_part( 'template-parts/home/universes' );
} else {
    foreach ( array( 'hero', 'member', 'latest', 'feed' ) as $part ) { get_template_part( 'template-parts/home/' . $part ); }
    do_action( 'rr_theme_home_after_news' );
    // Existing player plugin owns visibility, data and rendering.
    do_action( 'rrpw_homepage' );
    if ( function_exists( 'rr_weather_card' ) ) { rr_weather_card(); }
    get_template_part( 'template-parts/home/about' );
    rr_theme_sponsors( 'home-bottom' );
    // Preserve editor content if it differs from the former theme's legacy hero markup.
    do_action( 'rr_theme_home_content' );
}
get_footer();
