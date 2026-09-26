<?php
/**
 * Plugin Name: Radio Rubbens Fotballrobot
 * Description: Kontrollert kampgrunnlag, laghistorikk og separate prøveutkast for Radio Rubben.
 * Version: 0.4.0
 * Requires PHP: 8.0
 * Author: Radio Rubben
 */
namespace RadioRubben\Fotballrobot;
if (!defined('ABSPATH')) exit;
require_once __DIR__.'/includes/facts.php';
require_once __DIR__.'/includes/robot.php';
require_once __DIR__.'/includes/writer.php';
require_once __DIR__.'/includes/report.php';
add_action('init', [Robot::class,'register']);
add_action('admin_menu', [Robot::class,'menu']);
add_action('rest_api_init', [Robot::class,'routes']);
add_action('admin_post_rrfr_action', [Robot::class,'action']);
add_action('admin_enqueue_scripts', static function($hook) {
    if($hook==='toplevel_page_rr-fotballrobot') wp_enqueue_style('rrfr-admin',plugins_url('admin.css',__FILE__),[], '0.4.0');
});

require_once __DIR__.'/includes/player-facts.php';
require_once __DIR__.'/includes/players.php';
add_action('init', [Players::class,'register']);
add_action('admin_menu', [Players::class,'menu']);
add_action('rest_api_init', [Players::class,'routes']);
add_action('admin_post_rrfr_player_action', [Players::class,'action']);
add_action('rrfr_players_tick', [Players::class,'tick']);
register_deactivation_hook(__FILE__, [Players::class,'stop']);
add_action('admin_enqueue_scripts', static function($hook) {
    if($hook==='fotballrobot_page_rr-fotballrobot-players') wp_enqueue_style('rrfr-admin',plugins_url('admin.css',__FILE__),[], '0.4.0');
});
