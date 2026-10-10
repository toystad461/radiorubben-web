<?php
namespace RadioRubben\Fotballrobot {
    final class FactStore {public static function save($id,$payload){$GLOBALS['saves']++;return $payload;}}
}
namespace {
    const MINUTE_IN_SECONDS=60;const DAY_IN_SECONDS=86400;
    $saves=0;$n=0;$options=[];$html=file_get_contents(__DIR__.'/fixtures/match.html');
    function check($v,$why){$GLOBALS['n']++;if(!$v)throw new RuntimeException($why);}
    function rejects($f){$before=$GLOBALS['saves'];try{$f();}catch(RuntimeException $e){check($GLOBALS['saves']===$before,'Conflicting facts never saved');return;}throw new RuntimeException('Must reject');}
    function get_option($k,$d=false){return $GLOBALS['options'][$k]??$d;}
    function get_transient(...$a){return false;}
    function set_transient(...$a){}
    function add_action(...$a){}
    function wp_safe_remote_get($url,$args){return ['body'=>str_contains($url,'/kamp/')?$GLOBALS['html']:file_get_contents(__DIR__.'/fixtures/'.(str_contains($url,'30365')?'bremnes':'viggo').'.html')];}
    function is_wp_error($v){return false;}
    function wp_remote_retrieve_response_code($r){return 200;}
    function wp_remote_retrieve_body($r){return $r['body'];}
    require __DIR__.'/../includes/facts.php';require __DIR__.'/../includes/match-jobs.php';require __DIR__.'/../includes/match-followup.php';require __DIR__.'/../includes/robot.php';
    use RadioRubben\Fotballrobot\Robot;use RadioRubben\Fotballrobot\Facts;
    $m=Facts::match($html,8985501);$m['finished_confirmed']=true;
    $options['rr_poll_test_8985501_vipps_v3_75']=['running'=>true,'finished'=>false,'closed'=>false];$speaker=$options;
    $f=Robot::refresh(8985501,false,$m);
    check($f['finished_confirmed']&&$f['match']['score']===[2,2],'Real refresh uses source confirmation without finished dashboard');
    check($options===$speaker,'Real refresh does not mutate dashboard or vote status');
    $changed=$m;$changed['score']=[7,1];rejects(fn()=>Robot::refresh(8985501,false,$changed));
    $changed=$m;$changed['away']['id']=99;rejects(fn()=>Robot::refresh(8985501,false,$changed));
    $changed=$m;$changed['competition']['id']=99;rejects(fn()=>Robot::refresh(8985501,false,$changed));
    $changed=$m;$changed['finished_confirmed']=false;rejects(fn()=>Robot::refresh(8985501,false,$changed));
    $html=preg_replace('/(<div class="endResult">)2 - 2(<\/div>)/','${1}0 - 0${2}',$html,1);
    $m=Facts::match($html,8985501);$m['finished_confirmed']=true;
    check($m['score']===[0,0]&&Robot::refresh(8985501,false,$m)['finished_confirmed'],'Real parser and refresh accept explicitly finished 0-0');
    $f=Robot::refresh(8985501);check(!$f['finished_confirmed'],'Visible score alone never implies source completion');
    echo "OK: $n actual source/HTML consistency and independent-status controls\n";
}
