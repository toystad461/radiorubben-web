<?php
defined( 'ABSPATH' ) || exit;
$profiles = rr_theme_journalists();
$id = rr_theme_text( $args['id'] ?? rr_theme_journalist_id( get_the_ID() ) );
if ( ! isset( $profiles[ $id ] ) ) { return; }
$p = $profiles[ $id ];
?>
<aside class="rr-journalist rr-panel" aria-label="<?php esc_attr_e( 'Om journalisten', 'radio-rubben-next' ); ?>">
<?php if ( $p['avatar_url'] ) : ?><img src="<?php echo esc_url( $p['avatar_url'] ); ?>" alt="" width="64" height="64" loading="lazy"><?php else : ?><span class="rr-journalist-mark" aria-hidden="true">RR</span><?php endif; ?>
<div><p class="rr-eyebrow"><?php echo esc_html( $p['role'] ); ?></p><h2><?php if ( $p['url'] ) : ?><a href="<?php echo esc_url( $p['url'] ); ?>"><?php echo esc_html( $p['name'] ); ?></a><?php else : ?><?php echo esc_html( $p['name'] ); ?><?php endif; ?></h2><p><?php echo esc_html( $p['description'] ); ?></p><small><?php echo esc_html( $id ); ?></small></div>
</aside>
