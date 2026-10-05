<?php
/** One content pipeline for the three page frames. Editorial reuses the article view. */
defined( 'ABSPATH' ) || exit;
$layout = $args['layout'] ?? 'standard';
if ( ! in_array( $layout, array( 'standard', 'application', 'football' ), true ) ) { $layout = 'standard'; }
while ( have_posts() ) : the_post(); ?>
<article class="rr-page-frame rr-page-frame--<?php echo esc_attr( $layout ); ?>">
<?php get_template_part( 'template-parts/page/heading' ); ?>
<?php get_template_part( 'template-parts/page/sections' ); ?>
<div class="rr-content rr-page-body">
<?php the_content(); wp_link_pages( array( 'before' => '<nav class="rr-page-links" aria-label="' . esc_attr__( 'Innholdssider', 'radio-rubben-next' ) . '">', 'after' => '</nav>' ) ); ?>
</div>
<?php if ( comments_open() || get_comments_number() ) { comments_template(); } ?>
</article>
<?php endwhile; ?>
