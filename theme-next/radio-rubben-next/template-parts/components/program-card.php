<?php defined( 'ABSPATH' ) || exit; if ( empty( $args['title'] ) ) { return; } ?>
<article class="rr-program-card rr-panel"><p class="rr-eyebrow"><?php echo esc_html( rr_theme_text( $args['schedule'] ?? '' ) ); ?></p><h2><?php echo esc_html( rr_theme_text( $args['title'] ) ); ?></h2><p><?php echo esc_html( rr_theme_text( $args['description'] ?? '' ) ); ?></p>
<?php if ( ! empty( $args['url'] ) ) : ?><a href="<?php echo esc_url( rr_theme_text( $args['url'] ) ); ?>"><?php esc_html_e( 'Om programmet →', 'radio-rubben-next' ); ?></a><?php endif; ?></article>
