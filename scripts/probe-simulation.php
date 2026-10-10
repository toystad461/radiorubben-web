<?php
// Host CLI only. Source import and in-memory rehearsal; no option or post writes.
if (PHP_SAPI!=='cli') exit(1);
ini_set('display_errors','0'); ini_set('log_errors','0');
define('WP_USE_THEMES',false); define('DISABLE_WP_CRON',true);
ob_start(); require '/run/webroots/r1417157/wp-load.php'; ob_end_clean();
try {
    $stage=realpath($argv[1]??''); $mode=$argv[2]??'';
    if (!$stage || !in_array($mode,['before','after'],true)) throw new RuntimeException('arguments');
    if ($mode==='after' && (($GLOBALS['rr_site_state']??'')!=='active' || !function_exists('rr_poll_sim_action'))) throw new RuntimeException('module inactive');
    global $wpdb;
    $protected=$wpdb->get_results($wpdb->prepare(
        "SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name IN (%s,%s,%s,%s) OR option_name LIKE %s ORDER BY option_name",
        'rr_poll_selected_match','rr_poll_match_8985501','rr_match_archive_8985501','rr_bremnes_sponsor_8985501',
        $wpdb->esc_like('rr_poll_test_8985501_').'%'),ARRAY_A);
    if ($wpdb->last_error) throw new RuntimeException('database');
    $hash=hash('sha256',serialize($protected));
    if ($mode==='before') file_put_contents($stage.'/protected.sha256',$hash);
    elseif (!hash_equals(trim(file_get_contents($stage.'/protected.sha256')),$hash)) throw new RuntimeException('protected match changed');
    $root='/run/webroots/r1417157/wp-content/plugins/rr-site-functions';
    if (!function_exists('rr_poll_fd_fetch_match')) require $root.'/inc/bremnes-poll-fotballdata.php';
    if (!function_exists('rr_poll_lineup_ready')) require $root.'/inc/bremnes-poll-rules.php';
    if (!function_exists('rr_poll_sim_new')) require $stage.'/candidate/poll-simulation.php';
    $match=rr_poll_fd_fetch_match(8985501,false);
    if (is_wp_error($match) || !rr_poll_lineup_ready($match)) throw new RuntimeException('historical source unavailable');
    $sim=rr_poll_sim_new($match);
    $sim=rr_poll_sim_action($sim,'start',0,1000);
    $sim=rr_poll_sim_action($sim,'vote',(int)$match['starters'][0],1001);
    if (is_wp_error($sim) || array_sum($sim['votes'])!==1) throw new RuntimeException('simulation');
    echo 'SIMULATION_VERIFIED: mode='.$mode.'; match=8985501; starters='.count($match['starters']).'; memory_vote=1; protected_sha256='.$hash."\n";
} catch (Throwable $error) {
    // Never print provider transport details, private roster data or database contents.
    fwrite(STDERR,"SIMULATION_PROBE_FAILED: source, module or protected-state verification failed\n"); exit(1);
}
