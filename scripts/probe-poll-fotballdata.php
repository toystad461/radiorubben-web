<?php
// Read-only provider schema probe; never output credentials, request URLs or player names.
if (PHP_SAPI !== 'cli') exit(1);
ini_set('display_errors','0');
ini_set('log_errors','0');
define('WP_USE_THEMES',false);
define('DISABLE_WP_CRON',true);
ob_start();
require '/run/webroots/r1417157/wp-load.php';
ob_end_clean();
function rr_probe_shape($value,$depth=0) {
    if ($depth>6) return 'nested';
    if (!is_array($value)) return gettype($value);
    if (array_is_list($value)) return ['count'=>count($value),'item'=>isset($value[0])?rr_probe_shape($value[0],$depth+1):null];
    $out=[];
    foreach($value as $key=>$item) $out[$key]=rr_probe_shape($item,$depth+1);
    return $out;
}
try {
    $cid=get_option('rr_fd_cid',''); $cwd=get_option('rr_fd_cwd','');
    if (!preg_match('/^[1-9][0-9]*$/D',(string)$cid) || !preg_match('/^[a-f0-9-]{36}$/Di',(string)$cwd)) throw new RuntimeException('configuration');
    $match=(int)get_option('rr_poll_selected_match',0);
    if ($match<1) throw new RuntimeException('match');
    $candidate=$argv[1]??'';
    if ($candidate!=='' && !function_exists('rr_poll_fd_parse_match')) require $candidate;
    foreach (['matches/'.$match.'/people','teams/30365/matches'] as $path) {
        $url='https://api.fotballdata.no/v1/'.$path.'?'.http_build_query(['cid'=>$cid,'cwd'=>$cwd,'clubid'=>827,'format'=>'json']);
        $r=wp_safe_remote_get($url,['timeout'=>20,'redirection'=>0,'limit_response_size'=>2500000,'headers'=>['Accept'=>'application/json']]);
        if(is_wp_error($r)) throw new RuntimeException('transport');
        $status=wp_remote_retrieve_response_code($r);
        if($status!==200) { echo 'HTTP status: '.$status."\n"; throw new RuntimeException('provider'); }
        $d=json_decode(wp_remote_retrieve_body($r),true,64,JSON_THROW_ON_ERROR);
        if(!is_array($d)) throw new RuntimeException('schema');
        if ($candidate!=='' && str_starts_with($path,'matches/')) {
            $normalized=rr_poll_fd_parse_match($d,$match);
            if(is_wp_error($normalized)) { echo $normalized->get_error_message()."\n"; throw new RuntimeException('normalization'); }
            $stored=get_option('rr_poll_match_'.$match,[]);
            if(!empty($stored['kickoff']) && strtotime($stored['kickoff'])!==strtotime($normalized['kickoff'])) throw new RuntimeException('kickoff mismatch');
            echo 'NORMALIZED: kickoff matches stored; starters='.count($normalized['starters']).'; bench='.count($normalized['bench']).'; away starters='.count($normalized['away_starters'])."\n";
        }
        echo $path.' '.json_encode(rr_probe_shape($d),JSON_UNESCAPED_SLASHES)."\n";
        // Role labels alone identify the lineup semantics; no person names/IDs.
        $roles=[];
        $walk=function($v)use(&$walk,&$roles) {
            if(!is_array($v))return;
            foreach($v as $key=>$item) {
                if(preg_match('/role|position|reserve|start|playernumber|shirtnumber|jersey/i',(string)$key)&&is_scalar($item))
                    $roles[$key][(string)$item]=true;
                elseif(is_array($item))$walk($item);
            }
        };
        $walk($d);
        echo 'role_fields '.json_encode(array_map('array_keys',$roles),JSON_UNESCAPED_UNICODE)."\n";
    }
    echo "API CHECK: read-only provider requests passed\n";
} catch(Throwable $e) { fwrite(STDERR,"API CHECK failed; no production data changed\n"); exit(1); }
