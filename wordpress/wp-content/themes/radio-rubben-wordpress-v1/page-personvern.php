<?php get_header(); ?>
<section class="rr-subhero"><div class="rr-wrap"><p class="rr-eyebrow">PERSONVERN</p><h1><?php the_title(); ?></h1><p>Slik behandler Radio Rubben opplysninger om deg på nettstedet og i lyttertjenestene.</p></div></section>
<section class="rr-section rr-wrap"><div class="rr-content rr-legal-content">
<?php while (have_posts()) : the_post(); the_content(); endwhile; ?>
</div></section>
<?php get_footer(); ?>
