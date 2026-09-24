</main>
<footer class="rr-footer">
  <div class="rr-wrap rr-footer-grid">
    <div class="rr-footer-brand">
      <img class="rr-footer-logo" src="<?php echo esc_url(rr_one_logo_url()); ?>" alt="Radio Rubben">
      <p>Ingen valg. Bare god radio.</p>
      <p class="rr-footer-values">LOKAL <b>•</b> INKLUDERENDE <b>•</b> VERDIG <b>•</b> ENGASJERENDE</p>
    </div>
    <div class="rr-footer-links">
      <a href="<?php echo esc_url(rr_one_get_first_existing_url(['nyheter'], '/nyheter/')); ?>">Aktuelt</a>
      <a href="<?php echo esc_url(rr_one_get_first_existing_url(['kontakt','contact-us'], '/kontakt/')); ?>">Kontakt</a>
      <a href="<?php echo esc_url(rr_one_get_first_existing_url(['personvern','privacy-policy'], '/personvern/')); ?>">Personvern</a>
      <a href="<?php echo esc_url(home_url('/vilkar/')); ?>">Vilkår</a>
      <span>© <?php echo esc_html(date('Y')); ?> Radio Rubben AS</span>
    </div>
  </div>
</footer>
<?php $configured = (bool)trim((string)get_theme_mod('rr_stream_url','')); ?>
<?php if (!$configured): ?>
<div class="rr-mini-player rr-mini-unavailable" role="status" aria-label="Sendestatus">
 <span class="rr-pause-dot" aria-hidden="true"></span><strong>Radio Rubben</strong><span>Sendingene er på pause</span>
</div>
<?php else: ?>
<div class="rr-mini-player" role="region" aria-label="Radio Rubben avspiller">
 <div class="rr-mini-meta"><small class="rr-player-message" role="status">Trykk play for å lytte.</small><strong><?php echo esc_html(get_theme_mod('rr_now_artist','Radio Rubben') . ' – ' . get_theme_mod('rr_now_title','Kjente låter. Nye opplevelser.')); ?></strong></div>
 <span class="rr-broadcast <?php echo get_theme_mod('rr_live_status',false) ? 'is-live' : ''; ?>"><?php echo get_theme_mod('rr_live_status',false) ? 'LIVE' : 'AUTOMATIKK'; ?></span>
 <button class="rr-mini-play rr-play-toggle" aria-label="Spill Radio Rubben">▶</button>
</div>
<?php endif; ?>
<audio id="rr-audio" preload="none"></audio>
<?php wp_footer(); ?>
</body>
</html>
