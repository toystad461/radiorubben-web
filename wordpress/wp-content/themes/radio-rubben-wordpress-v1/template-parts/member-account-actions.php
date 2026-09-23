<?php
if (!defined('ABSPATH') || !is_user_logged_in()) return;
$rr_account_user = wp_get_current_user();
?>
<section class="rr-account-actions" aria-labelledby="rr-account-actions-title">
  <h2 id="rr-account-actions-title">Kontoen din</h2>
  <div class="rr-account-button-row">
  <details class="rr-member-fold rr-delete-profile">
    <summary>Slett meg</summary>
    <div class="rr-delete-body">
    <?php if (rr_member_can_self_delete($rr_account_user)): ?>
      <p><strong>Dette kan ikke angres.</strong> Vi sletter lytterkontoen, profilopplysninger, lagret bursdag og Vipps-kobling, ukens quizdata fra alle uker, låtduellstemmer, egne kommentarer og innsendte ønskelåter og hilsener knyttet til kontoen.</p>
      <p>Du blir logget ut etter slettingen. Dette sletter ikke Vipps-kontoen din eller betalingshistorikken hos Vipps. Eventuelle bidrag i LIVE-quizen uten kontotilknytning må håndteres separat. Sikkerhetskopier og tekniske logger følger egne sletterutiner.</p>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="rrm-form">
        <input type="hidden" name="action" value="rr_member_delete">
        <?php wp_nonce_field('rr_member_delete_'.$rr_account_user->ID, 'rr_delete_nonce'); ?>
        <label class="rr-delete-check"><input type="checkbox" name="rr_delete_confirm" value="yes" required> Jeg vil slette profilen og opplysningene som er beskrevet over.</label>
        <label for="rr-delete-word">Skriv SLETT for å bekrefte</label>
        <input id="rr-delete-word" name="rr_delete_word" type="text" required pattern="SLETT" autocomplete="off" spellcheck="false" maxlength="5">
        <button type="submit" class="rr-delete-submit">Slett profilen min permanent</button>
      </form>
    <?php else: ?>
      <p>Selvbetjent sletting gjelder vanlige lytterkontoer. Denne kontoen har en administrativ eller redaksjonell rolle og må håndteres av en administrator, slik at tilgang og innhold på nettsiden blir ivaretatt.</p>
    <?php endif; ?>
      <p><a href="<?php echo esc_url(home_url('/kontakt/')); ?>">Kontakt oss om sletting eller innsyn</a></p>
    </div>
  </details>
  <a class="rr-member-logout" href="<?php echo esc_url(wp_logout_url(home_url('/min-side/'))); ?>">Logg ut</a>
  </div>
</section>
