<?php get_header(); ?>
<section class="rr-404"><div class="rr-wrap"><p class="rr-eyebrow">404</p><h1>Den siden fant vi ikke.</h1><p>Det kan hende lenken er gammel, eller at innholdet har flyttet på seg.</p><div class="rr-actions"><a class="rr-btn" href="<?php echo esc_url(home_url('/')); ?>">Til forsiden</a><a class="rr-btn-outline" href="<?php echo esc_url(rr_one_get_first_existing_url(['lytt','pages'],'/lytt/')); ?>">Lytt til Radio Rubben</a></div><?php get_search_form(); ?></div></section>
<?php get_footer(); ?>
