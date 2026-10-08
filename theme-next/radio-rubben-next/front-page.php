<?php
// Only the classic public homepage receives this presentation change.
$rr_news_priority = 'posts' !== get_option( 'show_on_front' ) && ! post_password_required()
    && 'content' !== rr_theme_mod( 'rr_front_layout', 'classic' ) && ! rr_home_universes_enabled();
if ( $rr_news_priority ) {
    wp_enqueue_style( 'rr-next-news-priority', get_theme_file_uri( '/assets/css/news-priority.css' ), array( 'rr-next-home' ), '2026.10.07.1' );
}
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
    echo '<div class="rr-news-first">';
    get_template_part( 'template-parts/home/hero' );
    echo '<div class="rr-wrap rr-home-columns"><div class="rr-home-main">';
    get_template_part( 'template-parts/home/news-priority' );
    echo '</div>';
    get_template_part( 'template-parts/home/sidebar' );
    echo '</div>';
    get_template_part( 'template-parts/home/news-priority', null, array( 'sport' => true ) );
    // Existing player plugin owns visibility, data and rendering.
    do_action( 'rrpw_homepage' );
    get_template_part( 'template-parts/home/feed' );
    do_action( 'rr_theme_home_after_news' );
    // Weather now belongs to the sidebar.
    get_template_part( 'template-parts/home/about' );
    rr_theme_sponsors( 'home-bottom' );
    // Preserve editor content and all existing downstream integrations.
    do_action( 'rr_theme_home_content' );
    echo '</div>';
}
get_footer();

