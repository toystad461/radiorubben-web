<?php
defined( 'ABSPATH' ) || exit;
$name = rr_theme_text( get_post_meta( get_the_ID(), '_rr_source_name', true ) );
$url = esc_url( rr_theme_text( get_post_meta( get_the_ID(), '_rr_source_url', true ) ) );
$editor = rr_theme_text( get_post_meta( get_the_ID(), '_rr_editor_name', true ) );
if ( ! $name && ! $url && ! $editor ) { return; }
?>
<aside class="rr-source rr-panel" aria-label="<?php esc_attr_e( 'Kilde og redigering', 'radio-rubben-next' ); ?>">
<?php if ( $name || $url ) : ?><p><strong><?php esc_html_e( 'Kilde:', 'radio-rubben-next' ); ?></strong> <?php if ( $url ) : ?><a href="<?php echo $url; // Escaped above. ?>" rel="external noopener"><?php echo esc_html( $name ?: __( 'Originalkilden', 'radio-rubben-next' ) ); ?></a><?php else : echo esc_html( $name ); endif; ?></p><?php endif; ?>
<?php if ( $editor ) : ?><p><?php esc_html_e( 'Redigert av:', 'radio-rubben-next' ); ?> <?php echo esc_html( $editor ); ?></p><?php endif; ?>
</aside>
