<?php defined( 'ABSPATH' ) || exit; if ( empty( $args['name'] ) ) { return; } ?>
<aside class="rr-sponsor rr-panel" aria-label="<?php esc_attr_e( 'Annonse / samarbeidspartner', 'radio-rubben-next' ); ?>"><p class="rr-eyebrow">ANNONSE · SAMARBEIDSPARTNER</p>
<?php if ( ! empty( $args['image_id'] ) ) { echo wp_get_attachment_image( absint( $args['image_id'] ), 'medium', false, array( 'loading' => 'lazy', 'alt' => rr_theme_text( $args['name'] ) ) ); } ?>
<?php if ( ! empty( $args['url'] ) ) : ?><a href="<?php echo esc_url( rr_theme_text( $args['url'] ) ); ?>" rel="sponsored noopener"><?php echo esc_html( rr_theme_text( $args['name'] ) ); ?></a><?php else : ?><strong><?php echo esc_html( rr_theme_text( $args['name'] ) ); ?></strong><?php endif; ?>
<?php if ( ! empty( $args['text'] ) ) : ?><p><?php echo esc_html( rr_theme_text( $args['text'] ) ); ?></p><?php endif; ?></aside>
