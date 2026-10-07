<?php
if (!defined('ABSPATH')) exit;
// Match-specific sponsor settings; no changes to votes or match clock.
$rr_sponsor_key = 'rr_bremnes_sponsor_'.$rr_match_id;
$rr_sponsor_settings = get_option($rr_sponsor_key, ['name'=>'','logo'=>0]);
$rr_sponsor_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rr_save_sponsor'])) {
    if (!$rr_control || !$rr_admin) wp_die('Du har ikke tilgang til kampoppsettet.', '', ['response'=>403]);
    check_admin_referer('rr_sponsor_'.$rr_match_id,'rr_sponsor_nonce');
    $rr_sponsor_name = isset($_POST['rr_sponsor_name']) && is_string($_POST['rr_sponsor_name'])
        ? sanitize_text_field(wp_unslash($_POST['rr_sponsor_name'])) : '';
    if (strlen($rr_sponsor_name)>240) $rr_sponsor_error='Sponsornavnet er for langt. Bruk et kortere navn.';
    $rr_logo_id = !empty($_POST['rr_remove_logo']) ? 0 : absint($rr_sponsor_settings['logo'] ?? 0);
    $rr_has_upload = isset($_FILES['rr_sponsor_logo']['error']) && $_FILES['rr_sponsor_logo']['error'] !== UPLOAD_ERR_NO_FILE;
    if ($rr_has_upload && !$rr_sponsor_error) {
        if (!current_user_can('upload_files')) {
            $rr_sponsor_error='Du mangler tilgang til å laste opp bilder.';
        } elseif ($_FILES['rr_sponsor_logo']['error'] !== UPLOAD_ERR_OK) {
            $rr_sponsor_error='Opplastingen feilet. Prøv igjen med en mindre bildefil.';
        } elseif ((int)$_FILES['rr_sponsor_logo']['size'] > min(wp_max_upload_size(),5*MB_IN_BYTES)) {
            $rr_sponsor_error='Logoen er for stor. Maksimal størrelse er 5 MB eller nettstedets lavere opplastingsgrense.';
        } elseif ($rr_sponsor_name === '') {
            $rr_sponsor_error='Skriv inn sponsornavnet før du laster opp logoen.';
        } else {
            require_once ABSPATH.'wp-admin/includes/file.php';
            require_once ABSPATH.'wp-admin/includes/media.php';
            require_once ABSPATH.'wp-admin/includes/image.php';
            $rr_logo_mimes=['jpg|jpeg|jpe'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'];
            $rr_file_check=wp_check_filetype_and_ext($_FILES['rr_sponsor_logo']['tmp_name'],$_FILES['rr_sponsor_logo']['name'],$rr_logo_mimes);
            if (empty($rr_file_check['type']) || !in_array(wp_get_image_mime($_FILES['rr_sponsor_logo']['tmp_name']),array_values($rr_logo_mimes),true)) {
                $rr_sponsor_error='Velg en gyldig PNG-, JPG- eller WebP-logo.';
            } else {
                $rr_upload = media_handle_upload('rr_sponsor_logo',0,[],['test_form'=>false,'mimes'=>$rr_logo_mimes]);
                if (is_wp_error($rr_upload)) $rr_sponsor_error='Logoen kunne ikke lagres. Prøv igjen.';
                else $rr_logo_id=(int)$rr_upload;
            }
        }
    }
    if (!$rr_sponsor_error) {
        update_option($rr_sponsor_key,['name'=>$rr_sponsor_name,'logo'=>$rr_logo_id],false);
        $return_section=isset($_POST['rr_return_section']) && is_string($_POST['rr_return_section']) ? sanitize_key(wp_unslash($_POST['rr_return_section'])) : 'oppsett';
        if (!in_array($return_section,['','oppsett'],true)) $return_section='oppsett';
        wp_safe_redirect(add_query_arg('rr_sponsor_saved',1,rr_poll_dashboard_url($rr_url,$return_section)),303);
        exit;
    }
}
$rr_match_sponsor = sanitize_text_field($rr_sponsor_settings['name'] ?? '');
$rr_sponsor_logo_id = absint($rr_sponsor_settings['logo'] ?? 0);
function rr_poll_sponsor_form($settings,$error,$url,$match,$section='oppsett') {
?>
<section class="card" id="rr-sponsor-settings">
<h2>Kampsponsor</h2>
<p class="muted"><?php echo esc_html($match['home'].' – '.$match['away']); ?> · FIKS-ID <?php echo (int)$match['id']; ?>. Sponsoren lagres for denne kampen.</p>
<?php if ($error): ?><p role="alert"><?php echo esc_html($error); ?></p><?php endif; ?>
<?php if (isset($_GET['rr_sponsor_saved'])): ?><p role="status">Kampsponsoren er lagret.</p><?php endif; ?>
<form method="post" enctype="multipart/form-data" action="<?php echo esc_url(rr_poll_dashboard_url($url,$section)); ?>">
<input type="hidden" name="rr_return_section" value="<?php echo esc_attr($section); ?>">
<?php wp_nonce_field('rr_sponsor_'.$match['id'],'rr_sponsor_nonce'); ?>
<input type="hidden" name="rr_save_sponsor" value="1">
<p><label for="rr-sponsor-name">Navn på kampsponsor</label><br>
<input style="width:100%;background:#0c1220;color:white" id="rr-sponsor-name" name="rr_sponsor_name" type="text" maxlength="120" value="<?php echo esc_attr($settings['name'] ?? ''); ?>" placeholder="Skriv inn sponsornavn"></p>
<?php if (!empty($settings['logo'])): ?>
<div><?php echo wp_get_attachment_image(absint($settings['logo']),'medium',false,['alt'=>'Nåværende sponsorlogo','style'=>'max-width:220px;max-height:110px;width:auto;height:auto;object-fit:contain;background:white;padding:12px;border-radius:8px']); ?></div>
<p><label><input type="checkbox" name="rr_remove_logo" value="1"> Fjern logo fra denne kampen</label></p>
<?php endif; ?>
<p><label for="rr-sponsor-logo">Last opp logo</label><br>
<input style="max-width:100%" id="rr-sponsor-logo" name="rr_sponsor_logo" type="file" accept="image/png,image/jpeg,image/webp"></p>
<p class="muted">PNG, JPG eller WebP, inntil 5 MB. Ny opplasting erstatter logoen på kampen. Bildefilene beholdes i mediebiblioteket. Tomt sponsornavn skjuler sponsoromtalen og logoen.</p>
<button type="submit">Lagre kampsponsor</button>
</form>
</section>
<?php
}
