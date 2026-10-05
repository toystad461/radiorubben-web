<?php
/**
 * Plugin Name: Radio Rubben Site Functions
 * Description: Theme-independent migration bridge for existing match, speaker, member, quiz, weather and RRLive functions. Keep existing service plugins active.
 * Version: 1.0.0-rc.3
 * Requires at least: 6.6
 * Requires PHP: 8.2
 * License: GPL-2.0-or-later
 * Text Domain: rr-site-functions
 */
defined( 'ABSPATH' ) || exit;
define( 'RR_SITE_VERSION', '1.0.0-rc.3' );
define( 'RR_SITE_DIR', plugin_dir_path( __FILE__ ) );
define( 'RR_SITE_URL', plugin_dir_url( __FILE__ ) );

/** Delay ownership until the active theme has declared its functions. */
function rr_site_boot() {
    if ( isset( $GLOBALS['rr_site_state'] ) ) { return; }
    if ( 'radio-rubben-wordpress-v1' === get_template() ) {
        $GLOBALS['rr_site_state'] = 'legacy-theme-owns-functions';
        return; // Including a legacy child theme: no duplicate hooks, jobs or declarations.
    }
    foreach ( array( 'rr_weather_card', 'rr_quiz_enabled', 'rrwq_week', 'rr_member_dashboard', 'rr_poll_active_vote', 'rrlive_register_match_type' ) as $function ) {
        if ( function_exists( $function ) ) {
            $GLOBALS['rr_site_state'] = 'conflict';
            add_action( 'admin_notices', 'rr_site_conflict_notice' );
            return; // Fail closed instead of half-loading a second copy of a motor.
        }
    }
    $GLOBALS['rr_site_state'] = 'active';
    require_once RR_SITE_DIR . 'inc/presentation-bridge.php';
    foreach ( array( 'weather', 'quiz-controls', 'weekly-quiz', 'member-hub', 'bremnes-direkte-test', 'match-rollover', 'dashboard-prototype' ) as $module ) {
        require_once RR_SITE_DIR . 'inc/' . $module . '.php';
    }
    require_once RR_SITE_DIR . 'rrlive-data.php';
}
add_action( 'after_setup_theme', 'rr_site_boot', 20 );
function rr_site_conflict_notice() {
    if ( current_user_can( 'activate_plugins' ) ) {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'Radio Rubben Site Functions: another theme or plugin already owns legacy functions. Migration bridge was not loaded. Resolve the duplicate before continuing.', 'rr-site-functions' ) . '</p></div>';
    }
}
// No activation migration, automatic data reset, cron registration, or uninstall deletion.
