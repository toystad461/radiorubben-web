<?php
defined( 'ABSPATH' ) || exit;
$m = $args;
$home = rr_theme_text( $m['home'] ?? '' ); $away = rr_theme_text( $m['away'] ?? '' );
if ( ! $home || ! $away ) { return; }
$status = rr_theme_text( $m['status'] ?? 'scheduled' );
$labels = array( 'live' => 'LIVE', 'finished' => 'SLUTT', 'scheduled' => 'KOMMENDE KAMP', 'postponed' => 'UTSATT' );
$score = isset( $m['score']['home'], $m['score']['away'] ) ? rr_theme_text( $m['score']['home'] ) . '–' . rr_theme_text( $m['score']['away'] ) : '–';
$kickoff = strtotime( rr_theme_text( $m['kickoff'] ?? '' ) );
?>
<article class="rr-match-card rr-panel<?php echo 'live' === $status ? ' is-live' : ''; ?>">
<p class="rr-eyebrow"><?php echo esc_html( $labels[ $status ] ?? __( 'Kampstatus ikke bekreftet', 'radio-rubben-next' ) ); ?></p>
<h2><?php echo esc_html( $home . ' – ' . $away ); ?></h2>
<?php if ( in_array( $status, array( 'live', 'finished' ), true ) ) : ?><p class="rr-score" aria-label="<?php esc_attr_e( 'Resultat', 'radio-rubben-next' ); ?>"><?php echo esc_html( $score ); ?></p><?php endif; ?>
<?php if ( $kickoff ) : ?><time datetime="<?php echo esc_attr( wp_date( 'c', $kickoff ) ); ?>"><?php echo esc_html( wp_date( 'j. F Y · H:i', $kickoff ) ); ?></time><?php endif; ?>
<?php if ( ! empty( $m['venue'] ) ) : ?><p><?php echo esc_html( rr_theme_text( $m['venue'] ) ); ?></p><?php endif; ?>
<?php if ( ! empty( $m['url'] ) ) : ?><a class="rr-text-link" href="<?php echo esc_url( rr_theme_text( $m['url'] ) ); ?>"><?php echo esc_html( rr_theme_text( $m['cta'] ?? __( 'Se kampen →', 'radio-rubben-next' ) ) ); ?></a><?php endif; ?>
</article>
