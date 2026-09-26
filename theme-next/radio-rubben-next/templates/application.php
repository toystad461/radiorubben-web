<?php
/** Template Name: Applikasjonsramme (full bredde) */
get_header(); while ( have_posts() ) : the_post(); ?>
<section class="rr-app-shell rr-wrap"><header class="rr-app-heading"><p class="rr-eyebrow">RADIO RUBBEN</p><h1><?php the_title(); ?></h1></header><div class="rr-app-content"><?php the_content(); ?></div></section>
<?php endwhile; get_footer(); ?>
