<?php
if (!defined('ABSPATH')) exit;

function rr_member_can_self_delete($user) {
    return $user instanceof WP_User && $user->exists()
        && !is_multisite()
        && !user_can($user, 'manage_options')
        && !user_can($user, 'edit_posts')
        && !user_can($user, 'delete_users')
        && !user_can($user, 'edit_theme_options')
        && !is_super_admin($user->ID);
}

add_action('admin_post_rr_member_delete', function () {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !is_user_logged_in()) {
        wp_die('Du må være innlogget og bruke skjemaet på Min Rubben.', '', ['response'=>403]);
    }
    $user = wp_get_current_user();
    $uid = (int)$user->ID; // Never accept an account ID from the submitted form.
    check_admin_referer('rr_member_delete_'.$uid, 'rr_delete_nonce');
    if (!rr_member_can_self_delete($user)) {
        wp_die('Denne kontoen må håndteres av en administrator.', '', ['response'=>403]);
    }
    $confirmation = isset($_POST['rr_delete_word']) && is_string($_POST['rr_delete_word']) ? trim(wp_unslash($_POST['rr_delete_word'])) : '';
    if ($confirmation !== 'SLETT' || ($_POST['rr_delete_confirm'] ?? '') !== 'yes') {
        wp_die('Sletting er ikke bekreftet. Gå tilbake, kryss av og skriv SLETT.', '', ['response'=>400]);
    }
    global $wpdb;
    // Do not erase editorial content or media via a listener-account workflow.
    $other_content = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_author=%d AND post_type NOT IN ('rrm_innsending','revision') LIMIT 1", $uid
    ));
    if ($wpdb->last_error || $other_content) {
        wp_die('Kontoen har innhold som må gjennomgås av oss før sletting. Kontakt Radio Rubben.', '', ['response'=>409]);
    }
    if (!function_exists('rrwq_erase_user')) {
        wp_die('Slettingen er midlertidig utilgjengelig. Ingen opplysninger er slettet.', '', ['response'=>503]);
    }
    $fail = function () {
        wp_die('Slettingen ble ikke fullført. Noen opplysninger kan allerede være fjernet. Kontakt Radio Rubben for å fullføre slettingen.', '', ['response'=>500]);
    };
    $submissions = $wpdb->get_col($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_author=%d AND post_type='rrm_innsending'", $uid
    ));
    if ($wpdb->last_error) $fail();
    foreach ($submissions as $id) if (!wp_delete_post((int)$id, true)) $fail();
    $comments = $wpdb->get_col($wpdb->prepare(
        "SELECT comment_ID FROM {$wpdb->comments} WHERE user_id=%d", $uid
    ));
    if ($wpdb->last_error) $fail();
    foreach ($comments as $id) if (!wp_delete_comment((int)$id, true)) $fail();
    foreach (['rrm_votes'=>'user_id', 'vipps_login_users'=>'userid'] as $suffix=>$column) {
        $table = $wpdb->prefix.$suffix;
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        if ($wpdb->last_error) $fail();
        if ($exists === $table && $wpdb->delete($table, [$column=>$uid], ['%d']) === false) $fail();
    }
    rrwq_erase_user($uid);
    if ($wpdb->last_error) $fail();
    require_once ABSPATH.'wp-admin/includes/user.php';
    // Core removes the account, all user meta and session tokens; deletion hooks also run.
    if (!wp_delete_user($uid)) $fail();
    wp_logout();
    nocache_headers();
    wp_safe_redirect(add_query_arg('rr_profile_deleted','1',home_url('/min-side/')));
    exit;
});
