<?php
/**
 * Plugin Name: Radio Rubben Nyhetsbilder
 * Description: Versjonert bildepakke, kontrollert import og hel bildevisning for nye Studio-utkast.
 * Version: 1.0.0
 * Requires PHP: 8.2
 */
defined('ABSPATH') || exit;
require_once __DIR__.'/contract.php';
// Reuse PR75's pure provenance contract, without activating its site-wide gates.
require_once __DIR__.'/lib/media-policy.php';
require_once __DIR__.'/import.php';
require_once __DIR__.'/publication.php';

add_action('rest_api_init', static function(): void {
    register_rest_route('rr-news-images/v1', '/catalog', [
        'methods'=>'GET', 'permission_callback'=>static fn()=>current_user_can('edit_posts'),
        'callback'=>static function() { try { return rrni_catalog(); } catch (Throwable $e) { return new WP_Error('rrni_missing', $e->getMessage(), ['status'=>409]); } }
    ]);
});
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('rr-news-images import', static function($args): void {
        try { WP_CLI::line(wp_json_encode(rrni_import((string)($args[0] ?? '')), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)); }
        catch (Throwable $e) { WP_CLI::error($e->getMessage()); }
    });
    WP_CLI::add_command('rr-news-images verify', static function(): void {
        try { WP_CLI::line(wp_json_encode(rrni_catalog(), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)); }
        catch (Throwable $e) { WP_CLI::error($e->getMessage()); }
    });
}
