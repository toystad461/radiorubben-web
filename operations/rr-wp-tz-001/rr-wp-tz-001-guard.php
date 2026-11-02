<?php
/**
 * Plugin Name: Radio Rubben – preserve existing Site Kit report times
 * Description: RR-WP-TZ-001. Temporary protection for the three pre-migration events only.
 * Version: 1.0.0
 */
defined('ABSPATH') || exit;

function rr_wp_tz_001_reconciling() {
    foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 16) as $frame) {
        if (($frame['class'] ?? '') === 'Google\\Site_Kit\\Core\\Email_Reporting\\Email_Reporting_Scheduler'
            && ($frame['function'] ?? '') === 'schedule_initiator_once') {
            return true;
        }
    }
    return false;
}

function rr_wp_tz_001_preserved($hook, $args) {
    if ($hook !== 'googlesitekit_email_reporting_initiator' || !is_array($args)) {
        return null;
    }
    $saved = get_option('rr_wp_tz_001_preserved_events', array());
    foreach ($saved as $event) {
        if ($event['timestamp'] > time() && ($args[0] ?? null) === ($event['args'][0] ?? null)) {
            $existing = wp_get_scheduled_event($hook, $event['args'], $event['timestamp']);
            if ($existing && $existing->args === $event['args']) {
                return $event;
            }
        }
    }
    return null;
}

add_filter('pre_unschedule_event', function ($pre, $timestamp, $hook, $args) {
    if ($pre !== null || !rr_wp_tz_001_reconciling()) {
        return $pre;
    }
    $saved = rr_wp_tz_001_preserved($hook, $args);
    // Only prevent automatic reconciliation; manual cancellation and due cron execution remain available.
    if ($saved && (int) $timestamp === $saved['timestamp'] && $args === $saved['args']) {
        return false;
    }
    return $pre;
}, 10, 4);

add_filter('pre_schedule_event', function ($pre, $event) {
    if ($pre !== null || !rr_wp_tz_001_reconciling()) {
        return $pre;
    }
    // Do not create a second event for a frequency whose original event is still pending.
    if (rr_wp_tz_001_preserved($event->hook, $event->args)) {
        return false;
    }
    return $pre;
}, 10, 2);

