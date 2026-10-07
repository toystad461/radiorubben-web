<?php
defined( 'ABSPATH' ) || exit;
$match = rr_theme_match_data( 'header' );
if ( empty( $match['home'] ) || empty( $match['away'] ) ) { return; }
$url = esc_url( rr_theme_text( $match['url'] ?? '' ) );
$live = 'live' === ( $match['status'] ?? '' );
$kickoff = rr_theme_text( $match['kickoff'] ?? '' );
$ts = strtotime( $kickoff );
?>
<div class="rr-next-match-bar<?php echo $live ? ' is-live' : ''; ?>">
<div class="rr-wrap rr-next-match-inner">
<div class="rr-next-club"><span class="rr-next-side-label">HJEMME</span><strong><?php echo esc_html( rr_theme_text( $match['home'] ) ); ?></strong></div>
<div class="rr-next-center"><span class="rr-next-eyebrow"><?php echo esc_html( $live ? __( 'KAMPEN ER I GANG', 'radio-rubben-next' ) : __( 'NESTE KAMP', 'radio-rubben-next' ) ); ?></span>
<?php if ( $live && isset( $match['score']['home'], $match['score']['away'] ) ) : ?><strong><?php echo esc_html( rr_theme_text( $match['score']['home'] ) . '–' . rr_theme_text( $match['score']['away'] ) ); ?></strong>
<?php elseif ( $ts ) : ?><strong class="rr-next-countdown" data-kickoff="<?php echo esc_attr( wp_date( 'c', $ts ) ); ?>"><?php echo esc_html( wp_date( 'd.m · H:i', $ts ) ); ?></strong><span><?php echo esc_html( wp_date( 'd.m · H:i', $ts ) ); ?></span><?php endif; ?>
<?php if ( $url ) : ?><a class="rr-match-link" href="<?php echo $url; // Already escaped above. ?>"><?php echo esc_html( rr_theme_text( $match['cta'] ?? __( 'Ta turen på kamp ↗', 'radio-rubben-next' ) ) ); ?></a><?php endif; ?>
</div><div class="rr-next-club"><span class="rr-next-side-label">BORTE</span><strong><?php echo esc_html( rr_theme_text( $match['away'] ) ); ?></strong></div>
</div></div>
