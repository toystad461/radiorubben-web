<?php get_header(); ?>
<?php while(have_posts()): the_post(); ?>
<section class="rr-subhero rr-generic-hero"><div class="rr-wrap"><p class="rr-eyebrow">RADIO RUBBEN</p><h1><?php if (is_page(736)) echo 'Min Rubben'; else the_title(); ?></h1><?php if(has_excerpt()): ?><p><?php echo esc_html(get_the_excerpt()); ?></p><?php endif; ?></div></section>
<section class="rr-section rr-wrap"><div class="rr-content rr-page-content"><?php if (is_page(736)) rr_member_dashboard(); ?><?php the_content(); ?><?php if (is_page(736)) { get_template_part('template-parts/member-profile'); get_template_part('template-parts/member-support'); get_template_part('template-parts/member-account-actions'); } ?></div></section>
<?php endwhile; ?>
<?php get_footer(); ?>
