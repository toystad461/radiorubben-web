<?php defined( 'ABSPATH' ) || exit; if ( empty( $args['title'] ) || empty( $args['url'] ) ) { return; } ?>
<article class="rr-feed-card rr-panel"><p class="rr-eyebrow"><?php echo esc_html( rr_theme_text( $args['source_name'] ?? 'Bømlo kommune' ) ); ?></p><h3><a href="<?php echo esc_url( rr_theme_text( $args['url'] ) ); ?>" rel="external noopener"><?php echo esc_html( rr_theme_text( $args['title'] ) ); ?></a></h3>
<?php $stamp = strtotime( rr_theme_text( $args['published_at'] ?? '' ) ); if ( $stamp ) : ?><time datetime="<?php echo esc_attr( wp_date( 'c', $stamp ) ); ?>"><?php echo esc_html( wp_date( 'j. F Y H:i', $stamp ) ); ?></time><?php endif; ?>
<?php if ( ! empty( $args['excerpt'] ) ) : ?><p><?php echo esc_html( rr_theme_text( $args['excerpt'] ) ); ?></p><?php endif; ?></article>
