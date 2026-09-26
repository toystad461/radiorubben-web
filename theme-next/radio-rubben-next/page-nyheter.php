<?php get_header(); ?>
<section class="rr-subhero"><div class="rr-wrap"><p class="rr-eyebrow">AKTUELT</p><h1>Fra Radio Rubben.</h1><p>Artikler, ledertekster og lokale saker fra Radio Rubben.</p></div></section>
<section class="rr-section rr-wrap"><?php $q = new WP_Query( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => get_option( 'posts_per_page' ), 'paged' => max( 1, get_query_var( 'paged' ), get_query_var( 'page' ) ) ) ); ?>
<div class="rr-post-grid"><?php while ( $q->have_posts() ) { $q->the_post(); get_template_part( 'template-parts/content/card' ); } ?></div><?php rr_theme_pagination( $q ); wp_reset_postdata(); ?></section><?php get_footer(); ?>
