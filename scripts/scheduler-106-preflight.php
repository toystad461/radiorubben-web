<?php
if (!defined('ABSPATH')) throw new RuntimeException('WordPress required');
$expected=json_decode('{"plugins/radio-rubben-fotballrobot/includes/club-automation.php":"0c52b337decc2086b6f021c0aed717386748ad7b","plugins/radio-rubben-fotballrobot/includes/robot.php":"404cd7570e74e05f630d35fe2bb6b194710b8e8b","plugins/radio-rubben-fotballrobot/includes/weekly-coverage.php":"88517ed9d857be0873f77f8b2406c1f1331c1445","plugins/radio-rubben-fotballrobot/radio-rubben-fotballrobot.php":"067133ada0eb75c23604d4319faeb3b015b33e43"}',true);
foreach($expected as $relative=>$sha) {
 $file=WP_CONTENT_DIR.'/'.$relative;
 $bytes=file_get_contents($file);
 $actual=sha1('blob '.strlen($bytes)."\0".$bytes);
 echo $relative.' sha256='.hash('sha256',$bytes).' git='.$actual."\n";
 if(!hash_equals($sha,$actual)) throw new RuntimeException('Installed source mismatch: '.$relative);
}
if(!has_action('rrfr_club_results')) throw new RuntimeException('Results callback missing');
if(empty(get_option('rrfr_match_jobs',[])['enabled']) || !(int)get_option('rrfr_club_enabled_at',0)) throw new RuntimeException('Automation disabled');
$old=getenv('HOME').'/.radiorubben-deploy/fotballrobot-scheduler/f31b4765f6ec8020d97eb6779e37b344002cb16d/run.php';
$bytes=file_get_contents($old);
if(sha1('blob '.strlen($bytes)."\0".$bytes)!=='c38aa5b8dbe137e8436b3c7b8f39f3eecac94d60')throw new RuntimeException('Old runner mismatch');
$backup=getenv('HOME').'/.radiorubben-deploy/fotballrobot-scheduler/backup-before-106-'.gmdate('YmdHis').'.php';
if(file_exists($backup)||file_put_contents($backup,$bytes)!==strlen($bytes))throw new RuntimeException('Backup failed');
chmod($backup,0600);
if(hash_file('sha256',$backup)!==hash('sha256',$bytes))throw new RuntimeException('Backup verification failed');
echo 'OLD_RUNNER_BACKUP_OK sha256='.hash('sha256',$bytes)."\n";
echo 'HEARTBEAT '.json_encode(get_option('rrfr_match_followup_runner',[]))."\n";
echo "PREFLIGHT_OK: installed sources, callback, activation and runner backup\n";
