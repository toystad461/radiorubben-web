<?php $configured = (bool)trim((string)rr_theme_mod('rr_stream_url','')); ?>
<?php if (!$configured): ?>
<div class="rr-mini-player rr-mini-unavailable" role="status" aria-label="Sendestatus">
 <span class="rr-pause-dot" aria-hidden="true"></span><strong>Radio Rubben</strong><span>Sendingene er på pause</span>
</div>
<?php else: ?>
<div class="rr-mini-player" role="region" aria-label="Radio Rubben avspiller">
 <div class="rr-mini-meta"><small class="rr-player-message" role="status">Trykk play for å lytte.</small><strong><?php echo esc_html(rr_theme_mod('rr_now_artist','Radio Rubben') . ' – ' . rr_theme_mod('rr_now_title','Kjente låter. Nye opplevelser.')); ?></strong></div>
 <span class="rr-broadcast <?php echo rr_theme_mod('rr_live_status',false) ? 'is-live' : ''; ?>"><?php echo rr_theme_mod('rr_live_status',false) ? 'LIVE' : 'AUTOMATIKK'; ?></span>
 <button class="rr-mini-play rr-play-toggle" aria-label="Spill Radio Rubben">▶</button>
</div>
<?php endif; ?>
<audio id="rr-audio" preload="none"></audio>
