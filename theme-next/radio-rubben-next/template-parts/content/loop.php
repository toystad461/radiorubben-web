<?php defined( 'ABSPATH' ) || exit; ?>
<?php if ( have_posts() ) : ?><div class="rr-post-grid"><?php while ( have_posts() ) : the_post(); get_template_part( 'template-parts/content/card' ); endwhile; ?></div><?php rr_theme_pagination(); ?>
<?php else : ?><div class="rr-empty"><h2><?php esc_html_e( 'Ingen innlegg funnet.', 'radio-rubben-next' ); ?></h2><p><?php esc_html_e( 'Prøv et annet søk eller gå tilbake til forsiden.', 'radio-rubben-next' ); ?></p><?php get_search_form(); ?></div><?php endif; ?>
