<?php
/**
 * Plugin Name: Radio Rubbens Fotballrobot
 * Description: Kontrollert kampgrunnlag, laghistorikk og separate prøveutkast for Radio Rubben.
 * Version: 0.6.0
 * Requires PHP: 8.0
 * Author: Radio Rubben
 */
namespace RadioRubben\Fotballrobot;
if (!defined('ABSPATH')) exit;
require_once __DIR__.'/includes/facts.php';
require_once __DIR__.'/includes/fotballdata.php';
require_once __DIR__.'/includes/club-coverage.php';
require_once __DIR__.'/includes/club-automation.php';
add_filter('cron_schedules',[ClubAutomation::class,'schedules']);
add_action('init',[ClubAutomation::class,'register']);
add_action('admin_menu',[ClubAutomation::class,'menu'],11);
add_action('admin_post_rrfr_club',[ClubAutomation::class,'action']);
add_action('rrfr_club_tick',[ClubAutomation::class,'tick']);
add_action('rrfr_club_match',[ClubAutomation::class,'match']);
add_action('rrfr_club_weekly',[ClubAutomation::class,'weekly']);
register_deactivation_hook(__FILE__,[ClubAutomation::class,'stop']);
require_once __DIR__.'/includes/robot.php';
require_once __DIR__.'/includes/writer.php';
require_once __DIR__.'/includes/report.php';
require_once __DIR__.'/includes/learning.php';
add_action('init', [Robot::class,'register']);
add_action('admin_menu', [Robot::class,'menu']);
add_action('rest_api_init', [Robot::class,'routes']);
add_action('admin_post_rrfr_action', [Robot::class,'action']);
add_action('admin_enqueue_scripts', static function($hook) {
    if($hook==='toplevel_page_rr-fotballrobot'||strpos($hook,'rrfr-learning')!==false) wp_enqueue_style('rrfr-admin',plugins_url('admin.css',__FILE__),[], '0.6.0');
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
    if($hook==='fotballrobot_page_rr-fotballrobot-players') wp_enqueue_style('rrfr-admin',plugins_url('admin.css',__FILE__),[], '0.6.0');
});

require_once __DIR__.'/includes/player-review.php';
add_action('admin_enqueue_scripts', static function($hook) {
    if(strpos($hook,'rrfr-player-review')!==false) wp_enqueue_style('rrfr-admin',plugins_url('admin.css',__FILE__),[], '0.6.0');
});
