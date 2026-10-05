<?php
if (!defined('ABSPATH') || !is_user_logged_in()) return;
$rr_profile_user = wp_get_current_user();
$rr_profile_phone = get_user_meta($rr_profile_user->ID, '_vipps_phone', true);
$rr_profile_birthday = get_user_meta($rr_profile_user->ID, '_rr_vipps_birthday_md', true);
$rr_profile_birthday_label = 'Ikke mottatt fra Vipps';
if (is_string($rr_profile_birthday) && preg_match('/^([0-9]{2})-([0-9]{2})$/', $rr_profile_birthday, $rr_parts)
    && checkdate((int)$rr_parts[1], (int)$rr_parts[2], 2000)) {
    $rr_profile_birthday_label = $rr_parts[2].'.'.$rr_parts[1].' (dag og måned)';
}
$rr_profile_fields = [
    'Fornavn' => $rr_profile_user->first_name,
    'Etternavn' => $rr_profile_user->last_name,
    'Visningsnavn' => $rr_profile_user->display_name,
    'E-postadresse' => $rr_profile_user->user_email,
    'Mobilnummer fra Vipps' => is_scalar($rr_profile_phone) ? (string)$rr_profile_phone : '',
    'Bursdag fra Vipps' => $rr_profile_birthday_label,
];
?>
<details class="rr-member-profile" id="rr-my-information">
  <summary>Opplysninger du deler med Radio Rubben</summary>
  <p>Her ser du profilopplysningene som er lagret på kontoen din. Navn og e-post kan komme fra Vipps eller fra oppretting og redigering av kontoen.</p>
  <dl>
    <?php foreach ($rr_profile_fields as $rr_label => $rr_value): ?>
    <div><dt><?php echo esc_html($rr_label); ?></dt><dd><?php echo esc_html(trim((string)$rr_value) !== '' ? $rr_value : 'Ikke registrert'); ?></dd></div>
    <?php endforeach; ?>
  </dl>
  <p>Oversikten vises bare når du er innlogget på din egen konto. Bursdagsfunksjonen lagrer dag og måned, ikke fødselsår. Bursdagshilsener på e-post er ikke aktivert.</p>
  <p>Quizsvar, resultater, ønskelåter og hilsener registreres når du bruker funksjonene her. Resultatet på topplisten vises bare dersom du har valgt å delta offentlig. Vipps-innloggingen bruker også en teknisk ID for å kjenne igjen kontoen din.</p>
  <p>Dette er en profiloversikt, ikke et fullstendig datauttrekk. <a href="<?php echo esc_url(home_url('/kontakt/')); ?>">Kontakt oss for innsyn, retting eller sletting</a>.</p>
</details>
