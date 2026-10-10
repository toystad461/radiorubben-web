<?php
namespace RadioRubben\Fotballrobot {
    function time(){return $GLOBALS['now'];}
    class Robot {public static function allowed(){return true;} public static function refresh($id,$confirmed=false){$GLOBALS['refreshes']++;return $GLOBALS['facts'];}}
    class PublicationGate {public static function current(...$args){return false;}}
}
namespace {
    $now=2000000000;$options=[];$scheduled=[];$posts=[];$refreshes=0;$n=0;
    function add_action(...$a){}
    function get_option($k,$d=false){return $GLOBALS['options'][$k]??$d;}
    function add_option($k,$v,...$a){if(array_key_exists($k,$GLOBALS['options']))return false;$GLOBALS['options'][$k]=$v;return true;}
    function update_option($k,$v,...$a){$GLOBALS['options'][$k]=$v;}
    function delete_option($k){unset($GLOBALS['options'][$k]);}
    function wp_next_scheduled($h,$a){return $GLOBALS['scheduled'][json_encode($a)]??false;}
    function wp_schedule_single_event($time,$h,$a,...$rest){$GLOBALS['scheduled'][json_encode($a)]=$time;return true;}
    function is_wp_error($r){return false;}
    function get_current_user_id(){return 1;}
    function get_posts($q){foreach($GLOBALS['posts'] as $p)if(isset($q['name'])&&$p->post_name===$q['name'])return [$p];return [];}
    function get_edit_post_link($id,...$a){return 'edit/'.$id;}
    function check($v,$why){$GLOBALS['n']++;if(!$v)throw new RuntimeException($why);}
    function rejects($f){try{$f();}catch(RuntimeException $e){check(true,'rejected');return;}check(false,'Must reject');}
    require __DIR__.'/../includes/match-jobs.php';
    use RadioRubben\Fotballrobot\MatchJobs as J;
    $options[J::CONFIG]=['enabled'=>true,'owner'=>1,'since'=>1900000000];
    $archive=['match'=>['id'=>123,'home_id'=>30365,'away_id'=>19012],'state'=>['finished'=>true],'saved_at'=>$now];
    J::enqueue(123,$archive);$first=J::state(123);
    check($first['due_at']===$now+3600&&$scheduled['[123,"prepare"]']===$now+3600,'Exactly one hour from confirmation');
    $now+=120;J::enqueue(123,$archive);check(J::state(123)===$first,'Repeated archive does not reset timer');
    unset($scheduled['[123,"prepare"]']);J::run(123,'prepare');
    check($refreshes===0&&$scheduled['[123,"prepare"]']===$first['due_at'],'Old two-minute event cannot start early');
    $unconfirmed=$archive;$unconfirmed['state']['finished']=false;$unconfirmed['match']['id']=124;J::enqueue(124,$unconfirmed);
    check(J::state(124)===[],'No inference from clock or kickoff');
    $facts=['finished_confirmed'=>true,'match'=>['id'=>125,'home'=>['id'=>19012],'away'=>['id'=>30365],'score'=>[1,3],'kickoff'=>'2033-05-17T10:00:00+02:00','source'=>'https://www.fotball.no/fotballdata/kamp/?fiksId=125']];
    rejects(fn()=>J::observe(125,false,$facts['match']['source']));
    rejects(fn()=>J::observe(125,true,'https://example.test/'));
    J::observe(125,true,$facts['match']['source']);$due=J::state(125)['due_at'];
    check($due===$now+3600&&J::confirmed(125,$facts['match']),'Away observation is bound to official identities and score');
    $m=$facts['match'];$m['score']=[1,4];check(!J::confirmed(125,$m),'Changed result invalidates confirmation');
    $now+=60;J::observe(125,true,$facts['match']['source']);check(J::state(125)['due_at']===$due,'Observer retry does not delay or duplicate');
    $posts[]=(object)['ID'=>8,'post_name'=>'rubben-kamp-126','post_status'=>'publish'];
    $before=$refreshes;J::observe(126,true,'https://www.fotball.no/fotballdata/kamp/?fiksId=126');
    check($before===$refreshes&&J::state(126)===[],'Legacy publication blocks duplicate before fetch or model');
    check(!isset(J::publicState(125)['owner']),'Public state does not reveal owner or token');
    echo "OK: $n timing and duplicate controls\n";
}
