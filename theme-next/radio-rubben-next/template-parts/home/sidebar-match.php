<?php
defined( 'ABSPATH' ) || exit;
$home = rr_theme_text( $args['home'] ?? '' );
$away = rr_theme_text( $args['away'] ?? '' );
if ( ! $home || ! $away ) { return; }
$status = rr_theme_text( $args['status'] ?? 'scheduled' );
$labels = array( 'live' => 'KAMPEN ER I GANG', 'finished' => 'KAMP SLUTT', 'scheduled' => 'NESTE KAMP', 'postponed' => 'KAMPEN ER UTSATT' );
$kickoff = strtotime( rr_theme_text( $args['kickoff'] ?? '' ) );
$zone = new DateTimeZone( 'Europe/Oslo' );
$url = rr_theme_text( $args['url'] ?? '' );
$logos = array();
foreach ( array( 'home', 'away' ) as $side ) {
    $value = $args[ $side . '_logo' ] ?? $args[ 'rr_' . $side . '_logo' ] ?? '';
    $src = is_numeric( $value ) ? wp_get_attachment_image_url( absint( $value ), 'thumbnail' ) : ( is_string( $value ) ? esc_url_raw( $value, array( 'https', 'http' ) ) : '' );
    $logos[ $side ] = $src ?: '';
}
?>
<section class="rr-sidebar-match rr-panel<?php echo 'live' === $status ? ' is-live' : ''; ?>" aria-label="<?php echo esc_attr( $home . ' mot ' . $away ); ?>">
<p class="rr-eyebrow"><?php echo esc_html( $labels[ $status ] ?? 'Kampstatus ikke bekreftet' ); ?></p>
<h2 class="rr-sidebar-fixture">
<?php foreach ( array( 'home' => $home, 'away' => $away ) as $side => $team ) : ?>
<span class="rr-sidebar-team">
<?php if ( $logos[ $side ] ) : ?><img class="rr-sidebar-team-logo" src="<?php echo esc_url( $logos[ $side ] ); ?>" alt="" width="44" height="44" loading="lazy" decoding="async" referrerpolicy="no-referrer"><?php endif; ?>
<small><?php echo 'home' === $side ? 'HJEMME' : 'BORTE'; ?></small>
<strong><?php echo esc_html( preg_replace( '/\s+(\d+)$/u', "\u{00A0}$1", $team ) ); ?></strong>
</span>
<?php endforeach; ?>
</h2>
<?php if ( in_array( $status, array( 'live', 'finished' ), true ) && isset( $args['score']['home'], $args['score']['away'] ) ) : ?>
<p class="rr-sidebar-score" aria-label="Resultat"><?php echo esc_html( rr_theme_text( $args['score']['home'] ) . '–' . rr_theme_text( $args['score']['away'] ) ); ?></p>
<?php endif; ?>
<?php if ( $kickoff ) : ?><time datetime="<?php echo esc_attr( wp_date( 'c', $kickoff, $zone ) ); ?>"><?php echo esc_html( wp_date( 'j. M', $kickoff, $zone ) . ' · kl. ' . wp_date( 'H:i', $kickoff, $zone ) ); ?></time><?php endif; ?>
<?php if ( ! empty( $args['venue'] ) ) : ?><p class="rr-sidebar-venue"><?php echo esc_html( rr_theme_text( $args['venue'] ) ); ?></p><?php endif; ?>
<?php if ( $url ) : ?><a class="rr-sidebar-match-link" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( rr_theme_text( $args['cta'] ?? 'Se kampinfo →' ) ); ?></a><?php endif; ?>
</section>
