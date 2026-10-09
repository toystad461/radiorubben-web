<?php
defined('ABSPATH') || exit;

// Vipps 1.5.6 passes a null user to map_phone_to_user during registration.
// This hook runs after creation, with the verified provider data still in the session.
add_action('continue_with_vipps_after_create_wordpress_user', static function($user, $session) {
    if (!$user instanceof WP_User || !rr_poll_vipps_return($session)) return;
    $info=$session['userinfo']??null;
    if (!is_array($info) || empty($info['email_verified']) || empty($info['sub'])
        || empty($info['phone_number']) || empty($info['email'])
        || strcasecmp($user->user_email,(string)$info['email'])!==0) return;
    if (!is_callable(['VippsLogin','instance'])) return;
    $login=VippsLogin::instance();
    if (!is_callable([$login,'get_vipps_account']) || !is_callable([$login,'map_phone_to_user'])) return;
    $account=$login->get_vipps_account((int)$user->ID);
    if (!empty($account[1])) return;
    $login->map_phone_to_user(sanitize_text_field($info['phone_number']),sanitize_text_field($info['sub']),$user);
},10,2);

// Match introduction data never needs to block login, voting or page rendering.
function rr_poll_refresh_public_intro($id) {
    $id=absint($id);
    $match=get_option('rr_poll_match_'.$id,[]);
    if (!$id || !is_array($match) || empty($match['home']) || empty($match['away'])) return;
    $rr_poll_definitions_only=true;
    require_once __DIR__.'/bremnes-poll-match.php';
    $rr_control=false;
    require_once __DIR__.'/bremnes-speaker-welcome.php';
    $snapshot=['table'=>[],'scorer'=>[],'fetched'=>time()];
    $info=rr_welcome_match($match);
    if (empty($info['error'])) {
        $snapshot['table']=rr_welcome_table($info);
        $snapshot['scorer']=rr_welcome_top_scorer($info);
        $snapshot['team_id']=(int)($info['ids'][0]??0);
    }
    // Keep the last successful data when the external source is unavailable.
    if (empty($info['error'])) update_option('rr_poll_public_intro_last_'.$id,$snapshot,false);
    else {
        $previous=get_option('rr_poll_public_intro_last_'.$id,[]);
        if (is_array($previous) && $previous) $snapshot=$previous;
    }
    set_transient('rr_poll_public_intro_'.$id,$snapshot,!empty($snapshot['table'])?15*MINUTE_IN_SECONDS:5*MINUTE_IN_SECONDS);
}
add_action('rr_poll_refresh_public_intro','rr_poll_refresh_public_intro');

function rr_poll_schedule_rollover() {
    if (!wp_next_scheduled('rr_poll_background_rollover')) wp_schedule_single_event(time()+1,'rr_poll_background_rollover');
}
add_action('rr_poll_background_rollover',static function() {
    $rr_poll_definitions_only=true;
    require_once __DIR__.'/bremnes-poll-match.php';
    rr_poll_rollover_selection();
});
