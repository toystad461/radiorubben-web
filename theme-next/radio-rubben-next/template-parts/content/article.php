<?php defined( 'ABSPATH' ) || exit; ?>
<article <?php post_class( 'rr-single-article' ); ?>>
<section class="rr-subhero rr-article-hero"><div class="rr-wrap"><p class="rr-eyebrow"><?php $cats = get_the_category(); echo esc_html( $cats ? $cats[0]->name : 'FRA RADIO RUBBEN' ); ?></p><h1><?php the_title(); ?></h1><?php rr_theme_component( 'byline' ); get_template_part( 'template-parts/article-share' ); ?></div></section>
<?php if ( has_post_thumbnail() && ! post_password_required() ) : ?><div class="rr-wrap rr-single-image"><figure><?php the_post_thumbnail( 'rr-hero' ); ?><?php $caption = wp_get_attachment_caption( get_post_thumbnail_id() ); if ( $caption ) : ?><figcaption><?php echo wp_kses_post( $caption ); ?></figcaption><?php endif; ?></figure></div><?php endif; ?>
<section class="rr-section rr-wrap"><div class="rr-content rr-article-content"><?php 
// Keep the shared notice in the presentation layer; stored editorial text is untouched.
if ( is_singular( 'post' ) && ! post_password_required() ) {
	ob_start();
	the_content();
	$rr_article_html = ob_get_clean();
	// Remove only the legacy notice container and the two known standalone footers.
	$rr_article_html = preg_replace(
		'~<div\b[^>]*\bclass\s*=\s*(["\'])[^"\']*\brubben-ai-notice\b[^"\']*\1[^>]*>.*?</div>\s*~is',
		'', $rr_article_html
	);
	$rr_legacy_footers = array(
		'Teksten er utarbeidet med kunstig intelligens på grunnlag av Bremnes ILs arrangementsinformasjon og godkjent av redaksjonen. Redaksjonelt ansvar: Radio Rubben ved Thomas Magne Sellevold-Øystad.',
		'Teksten er utarbeidet med kunstig intelligens fra de oppgitte kildene. Redaksjonelt ansvar: Radio Rubben ved Thomas Magne Sellevold-Øystad.',
	);
	foreach ( $rr_legacy_footers as $rr_legacy_footer ) {
		$rr_article_html = preg_replace(
			'~<p\b[^>]*>\s*<small>\s*' . preg_quote( $rr_legacy_footer, '~' ) . '\s*</small>\s*</p>\s*~u',
			'', $rr_article_html
		);
	}
	$rr_notice = '<aside class="rr-editorial-notice" aria-label="Om artikkelen" style="font-size:clamp(.875rem,2.5vw,1rem);line-height:1.6;padding:16px 20px;margin:24px 0;border:1px solid #626570;border-radius:14px;background:#1b1e25;color:#e5e7eb;overflow-wrap:anywhere;">'
		. '<strong style="display:block;margin-bottom:6px;">Utarbeidet med kunstig intelligens</strong>'
		. '<p style="margin:0 0 8px;font:inherit;color:inherit;">Denne artikkelen er utarbeidet med hjelp av kunstig intelligens, basert på kildene som er oppgitt i saken. Radio Rubben har det redaksjonelle ansvaret.</p>'
		. '<p style="margin:0;font:inherit;color:inherit;"><strong>Redaksjonelt ansvarlig:</strong> Thomas Magne Sellevold-Øystad.</p></aside>';
	// Place after an opening lead paragraph; otherwise before the content.
	// Anchoring avoids inserting the notice inside a quote, list, figure or embed.
	if ( preg_match( '~\A(?:\s|<!--.*?-->)*<p\b[^>]*>.*?</p>~is', $rr_article_html, $rr_lead ) ) {
		$rr_article_html = substr_replace( $rr_article_html, $rr_notice, strlen( $rr_lead[0] ), 0 );
	} else {
		$rr_article_html = $rr_notice . $rr_article_html;
	}
	echo $rr_article_html; // WordPress-rendered content, as returned by the_content().
} else {
	the_content();
}
 wp_link_pages( array( 'before' => '<nav class="rr-page-links" aria-label="' . esc_attr__( 'Artikkelsider', 'radio-rubben-next' ) . '">', 'after' => '</nav>' ) ); ?>
<?php if ( ! post_password_required() ) { rr_theme_component( 'source' ); rr_theme_component( 'journalist' ); } ?>
</div><?php if ( is_singular( 'post' ) ) : the_tags( '<div class="rr-tags">', ' ', '</div>' ); ?>
<nav class="rr-post-nav" aria-label="<?php esc_attr_e( 'Innleggsnavigasjon', 'radio-rubben-next' ); ?>"><div><?php previous_post_link( '%link', '← %title' ); ?></div><div><?php next_post_link( '%link', '%title →' ); ?></div></nav>
<?php endif; rr_theme_sponsors( 'article-bottom' ); ?></section></article>
