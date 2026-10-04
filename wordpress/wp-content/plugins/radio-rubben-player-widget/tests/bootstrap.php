<?php
namespace RadioRubben\PlayerWidget {
    const FILE = __DIR__.'/../radio-rubben-player-widget.php';
    const VERSION = '1.0.0';
}
namespace {
    $options = []; $posts = []; $remote = []; $calls = []; $admin = false; $scheduled = false;
    function get_option($k,$default=false) { global $options; return $options[$k] ?? $default; }
    function update_option($k,$v,...$args) { global $options; $options[$k]=$v; return true; }
    function add_option($k,$v,...$args) { global $options; if (isset($options[$k])) return false; $options[$k]=$v; return true; }
    function delete_option($k) { global $options; unset($options[$k]); }
    function get_posts($args) { global $posts; return $posts; }
    function get_post($id) { global $posts; return in_array($id,$posts,true) ? (object)['post_type'=>'rr_robot_player','post_status'=>'private'] : null; }
    function current_user_can($cap) { global $admin; return $admin; }
    function wp_date($format,$time=null,$tz=null) {
        $text=(new DateTimeImmutable('@'.($time??time())))->setTimezone($tz??new DateTimeZone('Europe/Oslo'))->format($format);
        return strtr($text,['Monday'=>'mandag','Tuesday'=>'tirsdag','Wednesday'=>'onsdag','Thursday'=>'torsdag','Friday'=>'fredag','Saturday'=>'lørdag','Sunday'=>'søndag','January'=>'januar','February'=>'februar','March'=>'mars','April'=>'april','May'=>'mai','June'=>'juni','July'=>'juli','August'=>'august','September'=>'september','October'=>'oktober','November'=>'november','December'=>'desember']);
    }
    function wp_generate_uuid4() { return bin2hex(random_bytes(16)); }
    function wp_safe_remote_get($url,$args) { global $remote,$calls; $calls[]=['url'=>$url,'args'=>$args]; return $remote[$url]??['status'=>503,'body'=>'']; }
    function is_wp_error($r) { return false; }
    function wp_remote_retrieve_response_code($r) { return $r['status']; }
    function wp_remote_retrieve_body($r) { return $r['body']; }
    function add_shortcode(...$args) {}
    function wp_next_scheduled($hook) { global $scheduled; return $scheduled; }
    function wp_schedule_event(...$args) { global $scheduled; $scheduled=true; }
    function wp_clear_scheduled_hook($hook) { global $scheduled; $scheduled=false; }
    function shortcode_atts($defaults,$attrs,...$args) { return array_merge($defaults,array_intersect_key($attrs,$defaults)); }
    function absint($v) { return abs((int)$v); }
    function esc_html($v) { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
    function esc_attr($v) { return esc_html($v); }
    function esc_url($v) { return esc_html($v); }
    function plugins_url($path,$file) { return '../assets/'.basename($path); }
    function wp_unique_id($prefix) { static $id=0; return $prefix.(++$id); }
    function wp_enqueue_style(...$args) {}
    function wp_enqueue_script(...$args) {}
    require __DIR__.'/../includes/sources.php';
    require __DIR__.'/../includes/service.php';
    require __DIR__.'/../includes/view.php';
}
