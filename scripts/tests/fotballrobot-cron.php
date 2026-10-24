<?php
// Isolated dispatch contract: no WordPress bootstrap, database, API or article writes.
namespace RadioRubben\Fotballrobot;

define('ABSPATH', __DIR__);
final class MatchJobs { public const CONFIG = 'rrfr_match_jobs'; }
final class MatchFollowup {
    public static function tick(): void { $GLOBALS['fixture']['ticks']++; }
}
function time(): int { return $GLOBALS['fixture']['now']; }
function get_option($name, $default = false) { return $GLOBALS['fixture']['options'][$name] ?? $default; }
function update_option($name, $value, $autoload = null): bool {
    $GLOBALS['fixture']['options'][$name] = $value;
    return true;
}
function _get_cron_array(): array { return $GLOBALS['fixture']['cron']; }
function has_action($hook) { return isset($GLOBALS['fixture']['handlers'][$hook]); }
function wp_reschedule_event($at, $schedule, $hook, $args): bool {
    $GLOBALS['fixture']['rescheduled'][] = [$at, $schedule, $hook, $args];
    queue(time() + 300, $hook, $args, $schedule);
    return true;
}
function wp_unschedule_event($at, $hook, $args, $wpError = false) {
    if ($GLOBALS['fixture']['reservation_failure']) return false;
    unset($GLOBALS['fixture']['cron'][$at][$hook][md5(serialize($args))]);
    return true;
}
function do_action_ref_array($hook, $args): void {
    $GLOBALS['fixture']['calls'][] = [$hook, $args];
    ($GLOBALS['fixture']['handlers'][$hook])(...$args);
}
function resetFixture(): void {
    $GLOBALS['fixture'] = [
        'now' => 1800000000, 'cron' => [], 'options' => [MatchJobs::CONFIG => ['enabled' => 1]],
        'handlers' => [], 'calls' => [], 'ticks' => 0, 'rescheduled' => [], 'reservation_failure' => false,
    ];
    foreach (['rrfr_match_job', 'rrfr_club_tick', 'rrfr_club_match', 'rrfr_club_weekly', 'rrfr_club_results'] as $hook) {
        $GLOBALS['fixture']['handlers'][$hook] = static function (): void {};
    }
}
function queue(int $at, string $hook, array $args = [], $schedule = false): void {
    $GLOBALS['fixture']['cron'][$at][$hook][md5(serialize($args))] = ['schedule' => $schedule, 'args' => $args];
    ksort($GLOBALS['fixture']['cron']);
}
function queued(int $at, string $hook, array $args = []): bool {
    return isset($GLOBALS['fixture']['cron'][$at][$hook][md5(serialize($args))]);
}
function check(bool $condition, string $message): void {
    if (!$condition) throw new \RuntimeException($message);
}
function runScheduler(?string $expectedError = null): string {
    ob_start();
    $caught = null;
    try { (static function (): void { require getenv('RRFR_TEST_RUNNER') ?: dirname(__DIR__) . '/fotballrobot-cron.php'; })(); }
    catch (\Throwable $e) { $caught = $e; }
    finally { $output = ob_get_clean(); }
    if ($expectedError !== null) {
        check($caught !== null && str_contains($caught->getMessage(), $expectedError), 'Expected error: ' . $expectedError);
    } elseif ($caught !== null) { throw $caught; }
    return $output;
}

$tests = [];
$tests['due weekly results dispatch once, without a page visit'] = static function (): void {
    queue(time() - 10, 'rrfr_club_results');
    $output = runScheduler();
    check($GLOBALS['fixture']['calls'] === [['rrfr_club_results', []]], 'Weekly results were not dispatched exactly once');
    check(!queued(time() - 10, 'rrfr_club_results'), 'Completed event was not removed');
    check(str_contains($output, 'events=1'), 'Missing receipt');
    runScheduler();
    check(count($GLOBALS['fixture']['calls']) === 1, 'Removed event was dispatched again');
};
$tests['future results and unrelated due hooks remain untouched'] = static function (): void {
    queue(time() + 60, 'rrfr_club_results');
    queue(time() - 60, 'wp_scheduled_delete');
    $before = _get_cron_array();
    runScheduler();
    check(_get_cron_array() === $before && !$GLOBALS['fixture']['calls'], 'Out-of-scope job changed');
};
$tests['existing football hooks keep arguments and recurrence'] = static function (): void {
    foreach (['rrfr_match_job', 'rrfr_club_match', 'rrfr_club_weekly'] as $hook) queue(time() - 60, $hook, [42]);
    queue(time() - 60, 'rrfr_club_tick', [], 'rrfr_halfhour');
    runScheduler();
    check($GLOBALS['fixture']['calls'] === [
        ['rrfr_match_job', [42]], ['rrfr_club_match', [42]], ['rrfr_club_weekly', [42]], ['rrfr_club_tick', []],
    ], 'Existing football dispatch changed');
    check($GLOBALS['fixture']['rescheduled'] === [[time() - 60, 'rrfr_halfhour', 'rrfr_club_tick', []]], 'Recurrence changed');
    check(queued(time() + 300, 'rrfr_club_tick'), 'Next recurring event lost');
};
$tests['missing installed callback preserves queued event and fails visibly'] = static function (): void {
    foreach ([false, 'rrfr_halfhour'] as $schedule) {
        resetFixture();
        queue(time() - 60, 'rrfr_club_results', [], $schedule);
        unset($GLOBALS['fixture']['handlers']['rrfr_club_results']);
        $before = _get_cron_array();
        runScheduler('mangler registrert behandler');
        check(_get_cron_array() === $before, 'Unhandled event was removed or rescheduled');
        check(!$GLOBALS['fixture']['calls'] && !$GLOBALS['fixture']['rescheduled'], 'Unhandled callback ran');
        check(get_option('rrfr_match_followup_runner') === false, 'False success heartbeat');
    }
};
$tests['disabled automation does not touch jobs'] = static function (): void {
    queue(time() - 60, 'rrfr_club_results');
    $GLOBALS['fixture']['options'][MatchJobs::CONFIG]['enabled'] = 0;
    $before = _get_cron_array();
    runScheduler('deaktivert');
    check(_get_cron_array() === $before && $GLOBALS['fixture']['ticks'] === 0, 'Disabled automation ran');
};
$tests['failed reservation never invokes callback'] = static function (): void {
    queue(time() - 60, 'rrfr_club_results');
    $GLOBALS['fixture']['reservation_failure'] = true;
    runScheduler('kunne ikke reserveres');
    check(queued(time() - 60, 'rrfr_club_results') && !$GLOBALS['fixture']['calls'], 'Unreserved job ran');
};
$tests['twelve-job cap leaves excess jobs queued'] = static function (): void {
    for ($i = 0; $i < 14; $i++) queue(time() - 60, 'rrfr_club_match', [$i]);
    runScheduler();
    check(count($GLOBALS['fixture']['calls']) === 12, 'Job cap ignored');
    check(queued(time() - 60, 'rrfr_club_match', [12]) && queued(time() - 60, 'rrfr_club_match', [13]), 'Excess jobs lost');
};
$tests['time budget leaves next job queued'] = static function (): void {
    queue(time() - 60, 'rrfr_club_match', [1]);
    queue(time() - 60, 'rrfr_club_results');
    $due = time() - 60;
    $GLOBALS['fixture']['handlers']['rrfr_club_match'] = static function (): void { $GLOBALS['fixture']['now'] += 360; };
    runScheduler();
    check(count($GLOBALS['fixture']['calls']) === 1 && queued($due, 'rrfr_club_results'), 'Deadline ignored');
};
$tests['heartbeat counts dispatched events, source errors suppress OK receipt'] = static function (): void {
    runScheduler();
    check(get_option('rrfr_match_followup_runner') === ['at' => time(), 'transport' => 'host-cli', 'events' => 0], 'Heartbeat contract changed');
    $GLOBALS['fixture']['options']['rrfr_match_followup_error'] = ['message' => 'synthetic error'];
    check(runScheduler('Kildefeil') === '', 'Source error reported as OK');
};

$failures = 0;
foreach ($tests as $label => $test) {
    resetFixture();
    try { $test(); echo 'PASS: ' . $label . "\n"; }
    catch (\Throwable $e) { $failures++; fwrite(STDERR, 'FAIL: ' . $label . ': ' . $e->getMessage() . "\n"); }
}
echo count($tests) . ' cases; failures=' . $failures . "\n";
exit($failures ? 1 : 0);
