<?php defined( 'ABSPATH' ) || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class( rr_theme_mod( 'rr_stream_url', '' ) ? 'rr-stream-configured' : 'rr-stream-unavailable' ); ?>>
<?php wp_body_open(); ?>
<a class="rr-skip-link" href="#content"><?php esc_html_e( 'Hopp til innhold', 'radio-rubben-next' ); ?></a>
<?php if ( wp_is_block_theme() ) { block_template_part( 'header' ); } else { get_template_part( 'template-parts/site/header' ); } ?>
<main id="content" tabindex="-1">
