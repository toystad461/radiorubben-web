<?php
$universe_player = rr_home_universes_active();
$home_radio = $universe_player ? rr_home_radio_data() : array();
$configured = $universe_player ? (bool) $home_radio['stream_url'] : (bool)trim((string)rr_theme_mod('rr_stream_url',''));
$player_live = $universe_player ? rr_home_signal_live( $home_radio, time() ) : rr_theme_mod('rr_live_status',false);
$player_track = $universe_player ? trim( $home_radio['artist'] . ' ' . $home_radio['title'] ) : rr_theme_mod('rr_now_artist','Radio Rubben') . ' – ' . rr_theme_mod('rr_now_title','Kjente låter. Nye opplevelser.');
?>
<?php if (!$configured): ?>
<div class="rr-mini-player rr-mini-unavailable" role="status" aria-label="Sendestatus">
 <span class="rr-pause-dot" aria-hidden="true"></span><strong>Radio Rubben</strong><span>Sendingene er på pause</span>
</div>
<?php else: ?>
<div class="rr-mini-player" role="region" aria-label="Radio Rubben avspiller">
 <div class="rr-mini-meta"><small class="rr-player-message" role="status">Trykk play for å lytte.</small><strong><?php echo esc_html( $player_track ?: 'Radio Rubben' ); ?></strong></div>
 <?php if ( $universe_player ) : ?><span class="rr-u-mini-label">Hør live</span><?php endif; ?>
 <span class="rr-broadcast <?php echo $player_live ? 'is-live' : ''; ?>"><?php echo $player_live ? 'LIVE' : 'AUTOMATIKK'; ?></span>
 <button class="rr-mini-play rr-play-toggle" aria-label="Spill Radio Rubben">▶</button>
</div>
<?php endif; ?>
<audio id="rr-audio" preload="none"></audio>
