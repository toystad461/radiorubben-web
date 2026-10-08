<?php defined( 'ABSPATH' ) || exit; ?>
</main>
<?php if ( wp_is_block_theme() ) { block_template_part( 'footer' ); } else { get_template_part( 'template-parts/site/footer' ); } ?>
<?php get_template_part( 'template-parts/site/mini-player' ); ?>
<?php wp_footer(); ?>
</body></html>
