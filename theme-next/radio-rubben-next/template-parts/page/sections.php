<?php
/** Optional in-page navigation. Password-protected or paginated content is excluded. */
defined( 'ABSPATH' ) || exit;
if ( post_password_required() || ! empty( $GLOBALS['multipage'] ) ) { return; }
$links = rr_theme_section_links( parse_blocks( get_the_content() ) );
if ( count( $links ) < 2 ) { return; }
?>
<nav class="rr-section-nav" aria-label="<?php esc_attr_e( 'På denne siden', 'radio-rubben-next' ); ?>">
<?php foreach ( $links as $anchor => $label ) : ?>
<a href="#<?php echo esc_attr( $anchor ); ?>"><?php echo esc_html( $label ); ?></a>
<?php endforeach; ?>
</nav>
