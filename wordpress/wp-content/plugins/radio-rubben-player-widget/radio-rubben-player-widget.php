<?php
/**
 * Plugin Name: Radio Rubben – spillerkamper
 * Description: Kampkort for lokale spillere med kontrollerte MyGame-lenker og separat troppsstatus.
 * Version: 1.3.0
 * Requires PHP: 8.0
 * Author: Radio Rubben
 */
namespace RadioRubben\PlayerWidget;
if (!defined('ABSPATH')) exit;
const VERSION = '1.3.0';
const FILE = __FILE__;
require_once __DIR__.'/includes/sources.php';
require_once __DIR__.'/includes/service.php';
require_once __DIR__.'/includes/live.php';
require_once __DIR__.'/includes/view.php';
require_once __DIR__.'/includes/compact.php';
require_once __DIR__.'/includes/admin.php';
require_once __DIR__.'/includes/widget.php';
add_filter('cron_schedules', [Service::class, 'schedules']);
add_action('init', [Service::class, 'init']);
add_action('rest_api_init', [Live::class, 'routes']);
add_action('rrpw_refresh', [Service::class, 'tick']);
add_action('admin_menu', [Admin::class, 'menu']);
add_action('admin_post_rrpw_save', [Admin::class, 'save']);
add_action('admin_post_rrpw_refresh', [Admin::class, 'refresh']);
add_action('widgets_init', static function() { register_widget(Widget::class); });
add_action('wp_enqueue_scripts', [View::class, 'assets']);
add_action('template_redirect', [View::class, 'cachePolicy']);
add_action('rrpw_homepage', [View::class, 'homepage']);
add_action('admin_enqueue_scripts', static function($hook) { if ($hook === 'settings_page_rr-player-widget') View::assets(); });
register_deactivation_hook(__FILE__, [Service::class, 'stop']);
