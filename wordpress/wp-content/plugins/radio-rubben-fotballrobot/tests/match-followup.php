<?php
namespace RadioRubben\Fotballrobot {
    function time(){return $GLOBALS['now'];}
    final class Robot {
        public static function allowed(){return get_current_user_id()===7;}
        public static function refresh($id,$manual=false,$source=null){
            if(!$source||!$source['finished_confirmed'])throw new \RuntimeException('No confirmed source');
            return $GLOBALS['facts'][$id]=['match'=>$source,'fact_hash'=>'h'.$id,'finished_confirmed'=>true,'angles'=>[['id'=>'result']]];
        }
        public static function latest($id){return $GLOBALS['facts'][$id];}
    }
    final class PublicationGate {public static function current($id,$post){return $post->quality??false;}}
    final class Writer {
        public static function generate($id,$hash,$angle,$test=false,$durable=false){
            $GLOBALS['calls']++;$token=str_pad((string)$id,40,'x');
            MatchWork::begin($id,$token,['user'=>7,'facts'=>Robot::latest($id)]);
            MatchWork::save($token,['user'=>7,'article'=>['saved'=>'text'],'in_flight'=>false]);
            return ['review_token'=>$token];
        }
        public static function review($token){
            $work=MatchWork::load($token);$id=(int)rtrim($token,'x');
            $GLOBALS['calls']++;
            if(empty($work['reviewed'])){$work['reviewed']=true;MatchWork::save($token,$work);return ['review_token'=>$token];}
            $GLOBALS['posts'][$id]=(object)['ID'=>$id,'post_type'=>'post','post_name'=>'rubben-kamp-'.$id,'post_status'=>'draft','quality'=>true,'post_content'=>'Saved draft'];
            return ['id'=>$id];
        }
    }
}
namespace {
    const DAY_IN_SECONDS=86400;const MINUTE_IN_SECONDS=60;const ABSPATH=__DIR__.'/';
    $now=1791614288;$options=[];$events=[];$posts=[];$facts=[];$calls=0;$user=0;$n=0;$fetches=0;
    function check($v,$why){$GLOBALS['n']++;if(!$v)throw new RuntimeException($why);}
    function add_action(...$a){}
    function get_option($k,$d=false){return $GLOBALS['options'][$k]??$d;}
    function add_option($k,$v,...$a){if(array_key_exists($k,$GLOBALS['options']))return false;$GLOBALS['options'][$k]=$v;return true;}
    function update_option($k,$v,...$a){$GLOBALS['options'][$k]=$v;return true;}
    function delete_option($k){unset($GLOBALS['options'][$k]);}
    function wp_cache_delete(...$a){}
    function get_current_user_id(){return $GLOBALS['user'];}
    function wp_set_current_user($id){$GLOBALS['user']=$id;}
    function wp_next_scheduled($hook,$args=[]){foreach($GLOBALS['events'] as $at=>$hooks)foreach($hooks[$hook]??[] as $event)if($event['args']===$args)return $at;return false;}
    function wp_schedule_single_event($at,$hook,$args=[],...$rest){$GLOBALS['events'][$at][$hook][serialize($args)]=['args'=>$args,'schedule'=>false];return true;}
    function wp_schedule_event($at,$schedule,$hook){$GLOBALS['events'][$at][$hook]['a:0:{}']=['args'=>[],'schedule'=>$schedule];return true;}
    function wp_clear_scheduled_hook($hook,$args=[]){foreach($GLOBALS['events'] as &$hooks)unset($hooks[$hook][serialize($args)]);}
    function wp_unschedule_event($at,$hook,$args,...$rest){unset($GLOBALS['events'][$at][$hook][serialize($args)]);return true;}
    function wp_reschedule_event($at,$schedule,$hook,$args){return wp_schedule_event($GLOBALS['now']+1800,$schedule,$hook);}
    function _get_cron_array(){ksort($GLOBALS['events']);return $GLOBALS['events'];}
    function do_action_ref_array($hook,$args){if($hook==='rrfr_match_job')RadioRubben\Fotballrobot\MatchJobs::run(...$args);}
    function get_posts($q){$out=[];foreach($GLOBALS['posts'] as $id=>$p){if(($q['meta_key']??'')==='_rrfr_ai_match'&&$id===(int)($q['meta_value']??0))$out[]=$p;elseif(isset($q['name'])&&$p->post_name===$q['name'])$out[]=$p;}return $out;}
    function get_edit_post_link($id,...$a){return 'edit/'.$id;}
    function admin_url($p){return 'https://example.test/'.$p;}
    function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
    function esc_url($s){return esc_html($s);}
    function wp_date($fmt,$at){return (new DateTimeImmutable('@'.$at))->setTimezone(new DateTimeZone('Europe/Oslo'))->format($fmt);}
    function delete_transient(...$a){}
    function get_transient(...$a){return false;}
    function is_wp_error($r){return false;}
    function wp_remote_retrieve_response_code($r){return $r['code'];}
    function wp_remote_retrieve_body($r){return $r['body'];}
    function wp_safe_remote_get($url,$args){
        $GLOBALS['fetches']++;if(!empty($GLOBALS['source_failure']))throw new RuntimeException('Synthetic transport');
        return ['code'=>200,'body'=>json_encode(str_contains($url,'/teams?')?$GLOBALS['team_feed']:$GLOBALS['match_feed'])];
    }
    class DB {
        public $options='wp_options';public $prepared;
        function esc_like($s){return $s;}
        function prepare($query,...$args){$this->prepared=[$query,$args];return $query;}
        function get_col($q){$keys=array_values(array_filter(array_keys($GLOBALS['options']),fn($k)=>preg_match('/^rrfr_match_job_[0-9]+$/',$k)));sort($keys);$after=$this->prepared[1][1]??'';return array_slice(array_values(array_filter($keys,fn($k)=>$k>$after)),0,100);}
        function query($q){[$key,$value]=$this->prepared[1];if((string)get_option($key,'')!==$value)return 0;delete_option($key);return 1;}
    }
    $wpdb=new DB;
    require __DIR__.'/../includes/fotballdata.php';require __DIR__.'/../includes/club-coverage.php';
    require __DIR__.'/../includes/match-work.php';require __DIR__.'/../includes/match-jobs.php';require __DIR__.'/../includes/match-followup.php';
    use RadioRubben\Fotballrobot\MatchJobs as J;use RadioRubben\Fotballrobot\MatchFollowup as F;use RadioRubben\Fotballrobot\MatchWork as W;
    $options['rr_fd_cid']='310';$options['rr_fd_cwd']='00000000-0000-0000-0000-000000000000';
    $options[J::CONFIG]=['enabled'=>true,'owner'=>7,'since'=>1790441712];
    $team_feed=['ClubId'=>827,'Teams'=>[['ClubId'=>827,'TeamId'=>30365,'TeamName'=>'Bremnes Menn Senior A']]];
    $raw=['MatchId'=>8985501,'HomeTeamId'=>30365,'HomeTeamName'=>'Bremnes','HomeTeamClubId'=>827,'AwayTeamId'=>99,'AwayTeamName'=>'Motstander','AwayTeamClubId'=>814,'HomeTeamGoals'=>7,'AwayTeamGoals'=>1,'TournamentId'=>77,'TournamentName'=>'5. divisjon','StadiumName'=>'Teststadion','MatchStartDate'=>'/Date(1791567000000-0000)/','Cancelled'=>false,'Postponed'=>false,'Interrupted'=>false,'WalkOverHome'=>false,'WalkOverAway'=>false,'WalkOverBoth'=>false,'FinalResultApprovedByDistrict'=>true,'FinalResultApprovedByReferee'=>false];
    $match_feed=['ClubId'=>827,'Matches'=>[$raw]];
    $options['rr_poll_test_8985501_vipps_v3_75']=['finished'=>false,'running'=>true,'closed'=>false,'opened'=>true];$speaker=$options['rr_poll_test_8985501_vipps_v3_75'];
    F::register();$registered=$events;F::register();check($events===$registered,'One periodic registration');
    F::tick();check(J::state(8985501)['due_at']===$now+3600,'Source alone queues 7-1 one hour from first confirmation');
    check($user===0&&$speaker===$options['rr_poll_test_8985501_vipps_v3_75']&&!get_option('rr_match_archive_8985501'),'No user, archive, poll or speaker side effects');
    $due=J::state(8985501)['due_at'];$now+=300;F::tick();check(J::state(8985501)['due_at']===$due,'Repeated checks preserve first confirmed time');
    $user=7;wp_unschedule_event($due,J::HOOK,[8985501,'prepare']);J::run(8985501,'prepare');check($calls===0&&wp_next_scheduled(J::HOOK,[8985501,'prepare'])===$due,'No early preparation');$user=0;
    $events=[];F::tick();check(wp_next_scheduled(J::HOOK,[8985501,'prepare'])===$due,'Lost cron event repaired');
    $now=$due+1;
    // Actual CLI runner runs cron with no logged-in user or HTTP visit.
    ob_start();require dirname(__DIR__,5).'/scripts/fotballrobot-cron.php';$output=ob_get_clean();
    check(str_contains($output,'OK:')&&J::state(8985501)['phase']==='write'&&$user===0,'CLI runs preparation and restores anonymous identity');
    foreach(['write','review','review'] as $phase){$now+=20;wp_unschedule_event($now,J::HOOK,[8985501,$phase]);J::run(8985501,$phase);}
    check(count($posts)===1&&$posts[8985501]->post_status==='draft'&&J::state(8985501)['status']==='done','One complete draft, never automatically published');
    $before=$calls;F::tick();J::run(8985501,'review');check(count($posts)===1&&$calls===$before,'Repeated runs never rewrite or call AI again');
    $posts[8985501]->post_content='Human edit';$posts[8985501]->quality=false;J::recover(8985501);check(J::state(8985501)['status']==='blocked'&&$posts[8985501]->post_content==='Human edit','Human edits survive and require new review');
    $posts[8985501]->post_status='publish';J::recover(8985501);check($posts[8985501]->post_content==='Human edit'&&$calls===$before,'Published text preserved');
    $m=RadioRubben\Fotballrobot\ClubCoverage::collect()['matches'][8985501];$m['id']=20;$m['score']=[0,0];J::sourceConfirmed(20,$m);check(J::state(20)['status']==='queued','Confirmed zero-zero is valid');
    $m['id']=21;$m['finished_confirmed']=false;J::sourceConfirmed(21,$m);check(J::state(21)===[],'No inferred finish from elapsed kickoff or final event');
    foreach(['Cancelled','Postponed','Interrupted'] as $flag){$match_feed['Matches']=[[$flag=>true]+$raw];F::tick();check(get_option('rrfr_match_followup_matches')[8985501]['source_status']==='exception',$flag.' visibly distinct');}
    $match_feed['Matches']=[$raw,['AwayTeamGoals'=>2]+$raw];F::tick();check((bool)get_option('rrfr_match_followup_error')&&$calls===$before,'Contradictory rows stop writing and show source error');
    $fetchBefore=$fetches;F::tick();check($fetches===$fetchBefore,'Source failure backs off');
    delete_option('rrfr_match_followup_error');$match_feed['Matches']=[$raw];
    $s=['status'=>'running','phase'=>'prepare','owner'=>7,'created_at'=>$now-4000,'due_at'=>$now-100,'updated_at'=>$now-2000];
    $options['rrfr_match_job_30']=$s;$options['rrfr_match_job_lock_30']=$now-2000;J::recover(30);check(J::state(30)['status']==='queued'&&!get_option('rrfr_match_job_lock_30'),'Interrupted nonpaid prepare resumes');
    $options['rrfr_match_job_31']=['phase'=>'write']+$s;$options['rrfr_match_job_lock_31']=$now-2000;J::recover(31);check(J::state(31)['status']==='blocked','Uncertain legacy paid call never repeats');
    W::begin(32,str_repeat('z',40),['user'=>7]);W::save(str_repeat('z',40),['user'=>7,'article'=>['saved'=>'checkpoint'],'in_flight'=>false]);
    $options['rrfr_match_job_32']=['phase'=>'write']+$s;$options['rrfr_match_job_lock_32']=$now-2000;J::recover(32);check(J::state(32)['phase']==='review'&&J::state(32)['review_token']===str_repeat('z',40),'Saved generation resumes review instead of regenerating');
    W::begin(33,str_repeat('y',40),['user'=>7]);$options['rrfr_match_job_33']=['phase'=>'review']+$s;J::recover(33);check(J::state(33)['status']==='blocked','In-flight durable AI attempt blocks unknown replay');
    $options['rrfr_match_job_34']=['phase'=>'prepare']+$s;$options['rrfr_match_job_lock_34']=$now;J::recover(34);check(J::state(34)['status']==='running','Live concurrent job lock respected');
    $options['rrfr_match_job_35']=['status'=>'error','phase'=>'prepare','attempts'=>6,'retry_at'=>$now-1]+$s;J::recover(35);check(J::state(35)['status']==='error','Automatic attempts bounded at six');
    $options['rrfr_match_job_39']=['status'=>'error','phase'=>'write','attempts'=>1]+$s;J::recover(39);check(J::state(39)['status']==='blocked','Legacy paid error without checkpoint never retries automatically');
    $options['rrfr_match_job_lock_36']=$now;check(!F::release('rrfr_match_job_lock_36',$now-2000)&&get_option('rrfr_match_job_lock_36')===$now,'Stale owner cannot delete replacement lock');
    $user=7;J::retry(35);check(J::state(35)['status']==='queued'&&J::state(35)['attempts']===0,'Explicit retry resets bounded safe source attempts');
    $user=0;try{J::retry(35);check(false,'Unauthorized retry');}catch(RuntimeException $e){check(true,'Unauthorized retry rejected');}
    $user=7;try{J::retry(33);check(false,'Unknown paid retry');}catch(RuntimeException $e){check(true,'Unknown paid retry rejected');}$user=0;
    $options['rrfr_match_job_37']=['status'=>'waiting','phase'=>'prepare']+$s;
    $options['rrfr_match_job_38']=['status'=>'error','phase'=>'prepare','error'=>'Kontroller datakilden']+$s;
    ob_start();F::panel();$panel=ob_get_clean();
    foreach(['Kampoppfølging','Venter på sluttbekreftelse','I kø','Under arbeid','Feilet','Blokkert','Les utkast','Siste kjøring uten nettsidebesøk'] as $label)check(str_contains($panel,$label),'Progress panel exposes '.$label);
    check(!str_contains($panel,str_repeat('z',40))&&!str_contains($panel,'rr_fd_cwd'),'Panel never exposes source access or private review token');
    $render=getenv('RRFR_RENDER_DIR');
    if($render){if(!is_dir($render))mkdir($render,0700,true);file_put_contents($render.'/followup.html','<!doctype html><html lang="nb"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;padding:8px;font-family:system-ui}table{border-collapse:collapse}td,th{padding:8px;text-align:left}main{max-width:1100px;margin:auto}</style><body><main>'.$panel.'</main></body></html>');}
    echo "OK: $n source, scheduling, recovery and duplicate controls; mocked AI\n";
}
