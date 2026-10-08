<?php defined( 'ABSPATH' ) || exit; ?>
<section class="rr-home-intro rr-wrap">
<div class="rr-home-copy"><p class="rr-eyebrow">FRA BØMLO. MED MUSIKKGLEDE.</p><h1>Ingen valg.<br>Bare <span>god radio.</span></h1><p>Kjente låter. Nye opplevelser.<br>Du setter på Radio Rubben. Vi ordner resten.</p><a class="rr-btn" href="#lytt">Til avspilleren ↓</a></div>
<div class="rr-home-player">
<?php rr_theme_component( 'radio-card' ); ?>
<p class="rr-member-inline"><a href="<?php echo esc_url( rr_theme_page_url( array( 'min-side' ), '/min-side/' ) ); ?>">Ønsk en låt, send en hilsen eller spill quiz på Min Rubben →</a></p>
</div>
</section>

