<?php
// Cooperate with MatchFollowup's existing option lock; never remove another owner.
function rrSportJobLock(string $file,string $action):void {
    global $wpdb;$key='rrfr_match_followup_lock';
    if($action==='acquire'){
        if(file_exists($file))throw new RuntimeException('Job-lock receipt exists');
        $stamp=(string)time().'.'.bin2hex(random_bytes(8));
        if(file_put_contents($file,$stamp,LOCK_EX)!==strlen($stamp)||!chmod($file,0600))throw new RuntimeException('Lock receipt failed');
        if(!add_option($key,$stamp,'','no')){unlink($file);throw new RuntimeException('Kampoppfølging kjører allerede; vent til den er ferdig');}
    }elseif($action==='release'&&is_file($file)){
        $stamp=file_get_contents($file);
        $deleted=$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s",$key,$stamp));
        wp_cache_delete($key,'options');wp_cache_delete('notoptions','options');
        if($deleted!==1)throw new RuntimeException('Job lock owner changed; no foreign lock removed');
        unlink($file);
    }else throw new RuntimeException('Invalid job-lock action');
}
if(defined('WP_CLI')&&WP_CLI&&getenv('RR_SPORT_LOCK_ACTION')){
    if(get_option('home')!=='https://www.radiorubben.no')throw new RuntimeException('Wrong site');
    rrSportJobLock(getenv('RR_SPORT_STAGE').'/job-lock-owner',getenv('RR_SPORT_LOCK_ACTION'));
    echo 'SPORT_FOLLOWUP_LOCK_'.strtoupper(getenv('RR_SPORT_LOCK_ACTION')).PHP_EOL;
}
