<?php get_header();
$email=rr_theme_mod('rr_email','post@radiorubben.no');
$phone=rr_theme_mod('rr_phone','+47 934 44 954');
$address=trim((string)rr_theme_mod('rr_address',''));
$orgnr=trim((string)rr_theme_mod('rr_orgnr',''));
?>
<section class="rr-subhero"><div class="rr-wrap"><p class="rr-eyebrow">KONTAKT RADIO RUBBEN</p><h1>Har du noe på hjertet?</h1><p>Har du spørsmål, et tips, musikk vi bør høre, en idé til samarbeid – eller bare noe du vil fortelle oss? Da vil vi gjerne høre fra deg.</p><div class="rr-actions"><a class="rr-btn" href="mailto:<?php echo esc_attr($email); ?>">Send e-post</a><a class="rr-btn-outline" href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $phone)); ?>">Ring Radio Rubben</a></div></div></section>
<section class="rr-section rr-wrap"><div class="rr-contact-grid <?php echo $address ? '' : 'rr-two'; ?>">
<?php if($address): ?><article><small>ADRESSE</small><h3>Radio Rubben AS</h3><p><?php echo nl2br(esc_html($address)); ?></p><?php if($orgnr): ?><p class="rr-small">Org.nr. <?php echo esc_html($orgnr); ?></p><?php endif; ?></article><?php endif; ?>
<article><small>E-POST</small><h3><a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></h3></article>
<article><small>TELEFON</small><h3><a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $phone)); ?>"><?php echo esc_html($phone); ?></a></h3><?php if(!$address && $orgnr): ?><p class="rr-small">Org.nr. <?php echo esc_html($orgnr); ?></p><?php endif; ?></article>
</div></section>
<section class="rr-section rr-alt"><div class="rr-wrap rr-contact-form-grid"><div><p class="rr-eyebrow">SEND OSS EN MELDING</p><h2>Vi hører gjerne fra deg.</h2><p class="rr-large-copy">Bruk skjemaet til spørsmål, samarbeid, musikk eller tips. Vi svarer så snart vi har anledning.</p></div><div class="rr-form-box"><?php echo rr_one_contact_form(); ?><p class="rr-form-note">Opplysningene du sender inn brukes for å behandle og besvare henvendelsen. Se personvernerklæringen for mer informasjon.</p></div></div></section>
<section class="rr-section rr-wrap"><p class="rr-eyebrow">MUSIKK</p><h2>Har du musikk vi bør høre?</h2><p class="rr-large-copy">Lokal tilhørighet kan åpne døren – musikken avgjør hva som skjer videre. Er du artist fra Bømlo, Sunnhordland eller Vestlandet, eller mener du ganske enkelt at du har en låt vi bør høre? Send den til oss.</p><a class="rr-btn" href="mailto:<?php echo esc_attr($email); ?>?subject=Musikk%20til%20Radio%20Rubben">Send musikk til Radio Rubben</a></section>
<?php get_footer(); ?>
