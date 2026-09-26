<?php
defined( 'ABSPATH' ) || exit;
$artist = rr_theme_mod( 'rr_now_artist', 'Radio Rubben' );
$title = rr_theme_mod( 'rr_now_title', 'Kjente låter. Nye opplevelser.' );
$next_artist = rr_theme_mod( 'rr_next_artist', '' );
$next_title = rr_theme_mod( 'rr_next_title', '' );
$configured = (bool) rr_theme_mod( 'rr_stream_url', '' );
$live = (bool) rr_theme_mod( 'rr_live_status', false );
?>
 <aside class="rr-radio-card" id="lytt" aria-label="Radio Rubben avspiller">
  <div class="rr-radio-top"><strong>RADIO RUBBEN</strong><span class="rr-broadcast <?php echo $configured && $live ? 'is-live' : ''; ?>"><?php echo !$configured ? 'SENDINGENE ER PÅ PAUSE' : ($live ? 'LIVE NÅ' : 'AUTOMATIKK'); ?></span></div>
  <div class="rr-radio-cover"><img src="<?php echo esc_url(rr_one_logo_url()); ?>" alt="Radio Rubben"></div>
  <div class="rr-radio-track"><div><p class="rr-eyebrow">LYTT TIL RUBBEN</p><h2><?php echo $configured ? esc_html($artist) : 'Vi bygger videre.'; ?></h2><?php if($configured): ?><p><?php echo esc_html($title); ?></p><?php endif; ?></div><button class="rr-main-play rr-play-toggle" aria-label="Spill Radio Rubben" <?php disabled(!$configured); ?>><span>▶</span></button></div>
  <p class="rr-player-message" role="status"><?php echo $configured ? 'Trykk play for å lytte.' : 'Sendingen er ikke tilgjengelig akkurat nå. Vi jobber videre med Radio Rubben.'; ?></p>
  <?php if($configured): ?><label class="rr-volume">Volum <input id="rr-volume" type="range" min="0" max="1" value="0.8" step="0.05" aria-label="Volum"></label><?php endif; ?>
  <?php if($configured && ($next_artist || $next_title)): ?><p class="rr-small">Om litt: <?php echo esc_html(trim($next_artist . ' – ' . $next_title, ' –')); ?></p><?php endif; ?>
 </aside>
