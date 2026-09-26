<?php defined( 'ABSPATH' ) || exit; $compact = ! empty( $args['compact'] ); ?>
<article <?php post_class( 'rr-post-card' ); ?>>
<?php if ( has_post_thumbnail() && ! post_password_required() ) : ?><a class="rr-post-thumb" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true"><?php the_post_thumbnail( 'rr-card', array( 'alt' => '', 'loading' => 'lazy' ) ); ?></a><?php endif; ?>
<?php if ( $compact ) : ?><h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3><time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
<?php else : ?><?php rr_theme_component( 'byline' ); ?><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><div class="rr-card-excerpt"><?php the_excerpt(); ?></div><?php endif; ?>
</article>
