<?php
/** Template Name: Radio / Program */
get_header(); while ( have_posts() ) : the_post(); ?>
<section class="rr-subhero"><div class="rr-wrap"><p class="rr-eyebrow">PÅ RADIO RUBBEN</p><h1><?php the_title(); ?></h1></div></section><section class="rr-wrap rr-section"><div class="rr-content"><?php the_content(); wp_link_pages(); ?></div><?php if ( ! post_password_required() ) { rr_theme_sponsors( 'radio' ); } ?></section>
<?php endwhile; get_footer(); ?>
