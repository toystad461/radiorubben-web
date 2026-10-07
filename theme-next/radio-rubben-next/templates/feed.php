<?php
/** Template Name: Lokalt / RSS */
get_header(); while ( have_posts() ) : the_post(); ?>
<section class="rr-subhero"><div class="rr-wrap"><p class="rr-eyebrow">LOKALT NÅ</p><h1><?php the_title(); ?></h1></div></section><section class="rr-wrap rr-section"><div class="rr-content"><?php the_content(); wp_link_pages(); ?></div></section>
<?php endwhile; get_footer(); ?>
