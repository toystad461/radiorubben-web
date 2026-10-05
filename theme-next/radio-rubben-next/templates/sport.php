<?php
/** Template Name: Sport / Fotball / Bremnes */
get_header(); while ( have_posts() ) : the_post(); ?>
<section class="rr-subhero"><div class="rr-wrap"><p class="rr-eyebrow">SPORT · FOTBALL · BREMNES</p><h1><?php the_title(); ?></h1><?php rr_theme_category_nav(); ?></div></section>
<section class="rr-section rr-wrap"><div class="rr-content"><?php the_content(); wp_link_pages(); ?></div><?php if ( ! post_password_required() ) { rr_theme_component( 'match-card', rr_theme_match_data( 'sport' ) ); rr_theme_sponsors( 'sport' ); } ?></section>
<?php endwhile; get_footer(); ?>
