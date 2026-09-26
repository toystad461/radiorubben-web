<?php get_header(); while ( have_posts() ) : the_post(); ?>
<article <?php post_class( 'rr-single-article' ); ?>>
<section class="rr-subhero rr-article-hero"><div class="rr-wrap"><p class="rr-eyebrow"><?php $cats = get_the_category(); echo esc_html( $cats ? $cats[0]->name : 'FRA RADIO RUBBEN' ); ?></p><h1><?php the_title(); ?></h1><?php rr_theme_component( 'byline' ); get_template_part( 'template-parts/article-share' ); ?></div></section>
<?php if ( has_post_thumbnail() && ! post_password_required() ) : ?><div class="rr-wrap rr-single-image"><figure><?php the_post_thumbnail( 'rr-hero' ); ?><?php $caption = wp_get_attachment_caption( get_post_thumbnail_id() ); if ( $caption ) : ?><figcaption><?php echo wp_kses_post( $caption ); ?></figcaption><?php endif; ?></figure></div><?php endif; ?>
<section class="rr-section rr-wrap"><div class="rr-content rr-article-content"><?php the_content(); wp_link_pages( array( 'before' => '<nav class="rr-page-links" aria-label="' . esc_attr__( 'Artikkelsider', 'radio-rubben-next' ) . '">', 'after' => '</nav>' ) ); ?>
<?php if ( ! post_password_required() ) { rr_theme_component( 'source' ); rr_theme_component( 'journalist' ); } ?>
</div><?php the_tags( '<div class="rr-tags">', ' ', '</div>' ); ?>
<nav class="rr-post-nav" aria-label="<?php esc_attr_e( 'Innleggsnavigasjon', 'radio-rubben-next' ); ?>"><div><?php previous_post_link( '%link', '← %title' ); ?></div><div><?php next_post_link( '%link', '%title →' ); ?></div></nav>
<?php rr_theme_sponsors( 'article-bottom' ); ?></section></article>
<?php if ( comments_open() || get_comments_number() ) { comments_template(); } endwhile; get_footer(); ?>
