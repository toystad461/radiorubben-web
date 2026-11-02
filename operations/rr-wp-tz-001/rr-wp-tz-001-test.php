<?php
namespace Google\Site_Kit\Core\Email_Reporting {
    class Email_Reporting_Scheduler {
        public function schedule_initiator_once($callback) { return $callback(); }
    }
}
namespace {
    define('ABSPATH', __DIR__);
    $filters = array();
    function add_filter($name, $callback) { $GLOBALS['filters'][$name] = $callback; }
    function get_option($name, $fallback = false) { return $GLOBALS['saved']; }
    function wp_get_scheduled_event($hook, $args, $timestamp) {
        return $GLOBALS['exists'] ? (object) array('args' => $args) : false;
    }
    require __DIR__ . '/rr-wp-tz-001-guard.php';
    $t = time() + 3600;
    $hook = 'googlesitekit_email_reporting_initiator';
    $saved = array(array('timestamp' => $t, 'args' => array('weekly', $t)));
    $exists = true;
    $scheduler = new \Google\Site_Kit\Core\Email_Reporting\Email_Reporting_Scheduler();
    $unschedule = $filters['pre_unschedule_event'];
    $schedule = $filters['pre_schedule_event'];
    $event = (object) array('hook' => $hook, 'args' => array('weekly', $t + 3600));
    $checks = array();
    $checks['automatic_move_blocked'] = $scheduler->schedule_initiator_once(fn() => $unschedule(null, $t, $hook, array('weekly', $t))) === false;
    $checks['duplicate_blocked'] = $scheduler->schedule_initiator_once(fn() => $schedule(null, $event)) === false;
    $checks['manual_cancel_allowed'] = $unschedule(null, $t, $hook, array('weekly', $t)) === null;
    $checks['manual_schedule_allowed'] = $schedule(null, $event) === null;
    $checks['other_hook_allowed'] = $scheduler->schedule_initiator_once(fn() => $unschedule(null, $t, 'other_hook', array('weekly', $t))) === null;
    $checks['other_filter_preserved'] = $scheduler->schedule_initiator_once(fn() => $unschedule(true, $t, $hook, array('weekly', $t))) === true;
    $exists = false;
    $checks['missing_original_allows_schedule'] = $scheduler->schedule_initiator_once(fn() => $schedule(null, $event)) === null;
    $exists = true;
    $saved[0]['timestamp'] = time() - 1;
    $checks['expired_guard_allows_schedule'] = $scheduler->schedule_initiator_once(fn() => $schedule(null, $event)) === null;
    $checks['due_event_can_run'] = $scheduler->schedule_initiator_once(fn() => $unschedule(null, $t, $hook, array('weekly', $t))) === null;
    echo json_encode($checks, JSON_PRETTY_PRINT) . PHP_EOL;
    exit(in_array(false, $checks, true) ? 1 : 0);
}

