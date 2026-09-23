<?php
if (!defined('ABSPATH')) exit;
function rr_quiz_enabled($kind) {
    return (bool)get_option($kind === 'live' ? 'rr_quiz_live_enabled' : 'rr_quiz_weekly_enabled', true);
}
add_action('init', function() {
    foreach (['weekly','live'] as $kind) {
        register_setting('rr_quiz_controls', 'rr_quiz_'.$kind.'_enabled', [
            'type'=>'boolean', 'default'=>true, 'sanitize_callback'=>function($value){ return rest_sanitize_boolean($value) ? 1 : 0; },
            'show_in_rest'=>true,
        ]);
    }
});
add_action('admin_menu',function(){
    add_menu_page('Quiz-kontrollpanel','Quiz-kontroll','manage_options','rr-quiz-controls','rr_quiz_controls_page','dashicons-welcome-learn-more',59);
});
function rr_quiz_controls_page() {
    if (!current_user_can('manage_options')) return;
    echo '<div class="wrap"><h1>Quiz-kontrollpanel</h1><p>Slå quizene av og på hver for seg. Resultater og spørsmål beholdes.</p>';
    settings_errors();
    echo '<form method="post" action="'.esc_url(admin_url('options.php')).'">';
    settings_fields('rr_quiz_controls');
    echo '<table class="form-table" role="presentation">';
    foreach (['weekly'=>'Ukens Rubben-quiz','live'=>'LIVE-quizen'] as $kind=>$label) {
        $key='rr_quiz_'.$kind.'_enabled';
        echo '<tr><th scope="row">'.esc_html($label).'</th><td><input type="hidden" name="'.esc_attr($key).'" value="0"><label><input type="checkbox" name="'.esc_attr($key).'" value="1" '.checked(rr_quiz_enabled($kind),true,false).'> På – tilgjengelig for spillerne</label></td></tr>';
    }
    echo '</table><p>Fjern avkryssingen og lagre for å slå av. Quizen skjules fra quizsiden, og nye spillhandlinger avvises også på serveren.</p><p><strong>Pågående runder:</strong> Av/på-knappen setter ikke klokken på pause. Vent gjerne til pågående runder er ferdige før du slår av Ukens quiz.</p><p>Ukens quiz bytter mandag kl. 00.00 norsk tid. Klargjøring av nye spørsmål fortsetter selv om visningen er slått av. LIVE-rundene styres fortsatt fra det eksisterende LIVE-quizpanelet.</p>';
    submit_button('Lagre quizinnstillinger');
    echo '</form><p><a href="'.esc_url(home_url('/quiz/')).'">Åpne quizsiden</a></p></div>';
}
// These switches gate play on the server, including forms from cached pages.
add_filter('rest_pre_dispatch',function($result,$server,$request){
    if (strpos($request->get_route(),'/rubben-quiz/v1/')===0 && !rr_quiz_enabled('live')) {
        return new WP_Error('rr_quiz_disabled','LIVE-quizen er slått av for øyeblikket. Kom gjerne tilbake senere.',['status'=>503]);
    }
    return $result;
},10,3);
add_filter('pre_do_shortcode_tag',function($return,$tag){
    if ($tag==='rubben_live_quiz' && !rr_quiz_enabled('live')) return '';
    return $return;
},10,2);
// Remove the LIVE section's blocks, including its introduction, when disabled.
add_filter('the_content', function($content) {
    if (!is_page(496) || !in_the_loop() || !is_main_query() || rr_quiz_enabled('live')) return $content;
    $blocks = parse_blocks($content); $start = null; $end = null;
    foreach ($blocks as $i => $block) {
        if ($block['blockName'] === 'core/heading' && strpos($block['innerHTML'], 'id="rr-live-quiz"') !== false) $start = $i;
        if ($start !== null && $block['blockName'] === 'core/shortcode' && strpos($block['innerHTML'], '[rubben_live_quiz') !== false) { $end = $i; break; }
    }
    if ($start !== null && $end !== null) array_splice($blocks, $start, $end - $start + 1);
    return serialize_blocks($blocks);
}, 7);
add_filter('the_content', function($content) {
    if (!is_page(496) || !in_the_loop() || !is_main_query()) return $content;
    if (!rr_quiz_enabled('weekly') && !rr_quiz_enabled('live')) return '<p>Ingen quiz er åpen akkurat nå. Kom gjerne tilbake senere.</p>';
    if (!rr_quiz_enabled('weekly') || !rr_quiz_enabled('live')) $content = preg_replace('/<nav class="rrq-modes"[^>]*>.*?<\\/nav>/s', '', $content);
    return $content;
}, 12);
function rr_quiz_vipps_return($session) {
    // VippsSession implements ArrayAccess; it is not a PHP array.
    if (!is_array($session) && !($session instanceof ArrayAccess)) return '';
    $referer = $session['referer'] ?? '';
    if (!is_string($referer)) return '';
    $origin = wp_parse_url($referer); $target = wp_parse_url(home_url('/quiz/'));
    if (!is_array($origin) || !isset($origin['host'], $origin['path'])
        || strtolower($origin['host']) !== strtolower($target['host'])
        || rtrim($origin['path'], '/') !== rtrim($target['path'], '/')) return '';
    parse_str($origin['query'] ?? '', $args);
    $return = home_url('/quiz/');
    if (isset($args['wpvibe_preview']) && is_string($args['wpvibe_preview'])) {
        $return = add_query_arg('wpvibe_preview',sanitize_text_field($args['wpvibe_preview']),$return);
    }
    return $return.'#rr-weekly';
}
function rr_quiz_vipps_origin($session) {
    return rr_quiz_vipps_return($session) !== '';
}
add_action('continue_with_vipps_before_wordpress_login_redirect', function($user, $session) {
    $target = rr_quiz_vipps_return($session);
    if (!$target) return;
    add_filter('login_redirect', function($redirect, $requested, $logged_in_user) use ($target) {
        return $logged_in_user instanceof WP_User ? $target : $redirect;
    }, 1000, 3);
}, 10, 2);
add_filter('continue_with_vipps_wordpress_confirm_redirect', function($redirect, $user_id, $session) {
    return rr_quiz_vipps_return($session) ?: $redirect;
}, 1000, 3);
function rr_quiz_clear_cache() {
    clean_post_cache(496);
    $front_page = (int)get_option('page_on_front');
    if ($front_page) clean_post_cache($front_page);
    do_action('litespeed_purge_url', home_url('/'));
    if (function_exists('WP_Optimize') && method_exists(WP_Optimize(), 'get_page_cache')) {
        $cache = WP_Optimize()->get_page_cache();
        if (is_object($cache) && method_exists($cache, 'purge')) $cache->purge();
    }
    do_action('litespeed_purge_url', home_url('/quiz/'));
}
add_action('rest_api_init', function() {
    register_rest_route('rubben-weekly/v1', '/refresh', [
        'methods'=>'POST',
        'permission_callback'=>function(){ return current_user_can('manage_options'); },
        'callback'=>function(){ rr_quiz_clear_cache(); return ['refreshed'=>true]; }
    ]);
});
add_action('update_option_rr_quiz_weekly_enabled','rr_quiz_clear_cache',10,0);
add_action('update_option_rr_quiz_live_enabled','rr_quiz_clear_cache',10,0);
add_action('add_option_rr_quiz_weekly_enabled','rr_quiz_clear_cache',10,0);
add_action('add_option_rr_quiz_live_enabled','rr_quiz_clear_cache',10,0);
