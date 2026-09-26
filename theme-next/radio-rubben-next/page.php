<?php get_header(); while ( have_posts() ) : the_post(); ?>
<section class="rr-subhero rr-generic-hero"><div class="rr-wrap"><p class="rr-eyebrow">RADIO RUBBEN</p><h1><?php the_title(); ?></h1><?php if ( has_excerpt() && ! post_password_required() ) : ?><p><?php echo esc_html( get_the_excerpt() ); ?></p><?php endif; ?></div></section>
<section class="rr-section rr-wrap"><div class="rr-content rr-page-content"><?php the_content(); wp_link_pages(); ?></div></section>
<?php if ( comments_open() || get_comments_number() ) { comments_template(); } endwhile; get_footer(); ?>
