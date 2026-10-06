<footer class="rr-footer">
  <div class="rr-wrap rr-footer-grid">
    <div class="rr-footer-brand">
      <img class="rr-footer-logo" src="<?php echo esc_url(rr_one_logo_url()); ?>" alt="Radio Rubben">
      <p class="rr-footer-values">LOKAL <b>•</b> INKLUDERENDE <b>•</b> VERDIG <b>•</b> ENGASJERENDE</p>
    </div>
    <div class="rr-footer-links">
      <a href="<?php echo esc_url(rr_one_get_first_existing_url(['nyheter'], '/nyheter/')); ?>">Aktuelt</a>
      <a href="<?php echo esc_url(rr_one_get_first_existing_url(['kontakt','contact-us'], '/kontakt/')); ?>">Kontakt</a>
      <a href="<?php echo esc_url(rr_one_get_first_existing_url(['personvern','privacy-policy'], '/personvern/')); ?>">Personvern</a>
      <a href="<?php echo esc_url(home_url('/vilkar/')); ?>">Vilkår</a><span>© <?php echo esc_html(wp_date('Y')); ?> Radio Rubben AS</span>
    </div>
  </div>
</footer>
<?php rr_theme_menu( 'footer' ); ?>
