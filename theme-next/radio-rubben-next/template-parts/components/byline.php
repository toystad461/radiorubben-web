<?php defined( 'ABSPATH' ) || exit; $byline = rr_theme_byline(); ?>
<div class="rr-post-meta rr-byline">
<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
<span aria-hidden="true">·</span>
<?php if ( ! empty( $byline['url'] ) ) : ?><a rel="author" href="<?php echo esc_url( $byline['url'] ); ?>"><?php echo esc_html( $byline['name'] ); ?></a><?php else : ?><span><?php echo esc_html( $byline['name'] ); ?></span><?php endif; ?>
<?php if ( 'digital' === $byline['type'] ) : ?><span class="rr-digital-label"><?php esc_html_e( 'Digital journalist', 'radio-rubben-next' ); ?></span><?php endif; ?>
</div>
