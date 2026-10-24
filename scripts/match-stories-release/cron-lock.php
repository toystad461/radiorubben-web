<?php
// Temporary core cron mutex only. No schedules/settings/jobs are deleted or disabled.
function rrSportCronLock(string $file,string $action):void {
    if($action==='acquire'){
        if(file_exists($file)||get_transient('doing_cron')!==false)throw new RuntimeException('WordPress cron active; retry after it finishes');
        $token=sprintf('%.22F',microtime(true)+180);
        if(file_put_contents($file,$token,LOCK_EX)!==strlen($token)||!chmod($file,0600))throw new RuntimeException('Cron lock receipt failed');
        if(!set_transient('doing_cron',$token,240)||get_transient('doing_cron')!==$token)throw new RuntimeException('Cron mutex failed');
    }elseif($action==='release'&&is_file($file)){
        $token=file_get_contents($file);$current=get_transient('doing_cron');
        if($current!==false&&$current!==$token)throw new RuntimeException('Foreign cron lock retained');
        if($current===$token)delete_transient('doing_cron');
        unlink($file);
    }else throw new RuntimeException('Invalid cron lock action');
}
if(defined('WP_CLI')&&WP_CLI&&getenv('RR_SPORT_CRON_ACTION')){
    if(get_option('home')!=='https://www.radiorubben.no')throw new RuntimeException('Wrong site');
    rrSportCronLock(getenv('RR_SPORT_STAGE').'/cron-lock-owner',getenv('RR_SPORT_CRON_ACTION'));
    echo 'SPORT_CORE_CRON_'.strtoupper(getenv('RR_SPORT_CRON_ACTION')).PHP_EOL;
}
