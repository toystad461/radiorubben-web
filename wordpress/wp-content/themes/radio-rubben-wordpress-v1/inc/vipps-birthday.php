<?php
/** Receive consented Vipps birthdate; retain only month/day for birthday greetings. */
if (!defined('ABSPATH')) exit;
add_filter('login_with_vipps_openid_scope', function ($scopes, $action, $session) {
    if (!is_array($scopes)) $scopes = preg_split('/\s+/', trim((string)$scopes));
    $scopes[] = 'birthDate';
    return array_values(array_unique(array_filter($scopes)));
}, 20, 3);

function rr_vipps_birthday_value($info) {
    $date = is_array($info) ? ($info['birthdate'] ?? null) : null;
    if (!is_string($date) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)) return '';
    if (!checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1])) return '';
    return $parts[2].'-'.$parts[3];
}
// Keep the claim in memory until the plugin has actually authenticated this account.
add_filter('continue_with_vipps_allow_login', function ($allowed, $user, $info, $session) {
    if ($allowed && $user instanceof WP_User) {
        $GLOBALS['rr_vipps_birthday_pending'] = ['uid'=>(int)$user->ID, 'birthday'=>rr_vipps_birthday_value($info)];
    }
    return $allowed;
}, 100, 4);
add_action('continue_with_vipps_before_login_redirect', function ($user, $session) {
    $pending = $GLOBALS['rr_vipps_birthday_pending'] ?? null;
    unset($GLOBALS['rr_vipps_birthday_pending']);
    if (!$user instanceof WP_User || get_current_user_id() !== (int)$user->ID) return;
    if (!$pending || $pending['uid'] !== (int)$user->ID || !$pending['birthday']) return;
    update_user_meta($user->ID, '_rr_vipps_birthday_md', $pending['birthday']);
}, 20, 2);
// Explicit account linking has already been verified by the plugin at this hook.
add_action('continue_with_vipps_wordpress_confirm_before_redirect', function ($uid, $info, $session) {
    $birthday = rr_vipps_birthday_value($info);
    if ($birthday && get_current_user_id() === (int)$uid) update_user_meta($uid, '_rr_vipps_birthday_md', $birthday);
}, 20, 3);
