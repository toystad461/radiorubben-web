<?php
namespace RadioRubben\Fotballrobot;

/** Source status is independent of the speaker clock, poll and editorial decision. */
final class MatchFollowup {
    const HOOK='rrfr_match_followup';
    const WINDOW=7*DAY_IN_SECONDS;
    const STALE=1800;
    public static function schedules(array $s): array {
        $s['rrfr_match_five_minutes']=['interval'=>300,'display'=>'Kampoppfølging hvert femte minutt'];return $s;
    }
    public static function register(): void {
        if(empty(get_option(MatchJobs::CONFIG,[])['enabled']))return;
        if(!wp_next_scheduled(self::HOOK))wp_schedule_event(time()+60,'rrfr_match_five_minutes',self::HOOK);
    }
    public static function stop(): void {wp_clear_scheduled_hook(self::HOOK);}
    /** Compare-and-delete: never remove another process's replacement lock. */
    public static function release(string $key,int $stamp): bool {
        global $wpdb;
        $deleted=$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s",$key,(string)$stamp));
        wp_cache_delete($key,'options');wp_cache_delete('notoptions','options');return $deleted===1;
    }
    public static function tick(): void {
        $config=get_option(MatchJobs::CONFIG,[]);if(empty($config['enabled']))return;
        $key='rrfr_match_followup_lock';$stamp=(int)get_option($key,0);
        if($stamp&&$stamp<time()-self::STALE)self::release($key,$stamp);
        $stamp=time();if(!add_option($key,$stamp,'',false))return;
        $previous=get_current_user_id();
        try {
            wp_set_current_user((int)$config['owner']);
            if(!Robot::allowed())throw new \RuntimeException('Kampoppfølgingen mangler redaktørtilgang.');
            // Recovery also runs when the source is unavailable.
            foreach(self::jobIds() as $id)MatchJobs::recover($id);
            $failure=get_option('rrfr_match_followup_error',[]);
            if(time()<(int)($failure['retry_at']??0))return;
            $feed=ClubCoverage::collect();self::ingest($feed,$config);
            update_option('rrfr_match_followup_last',['at'=>time(),'count'=>count($feed['matches'])],false);
            delete_option('rrfr_match_followup_error');
        } catch(\Throwable $e) {
            $old=get_option('rrfr_match_followup_error',[]);
            $attempts=(int)($old['attempts']??0)+1;
            update_option('rrfr_match_followup_error',['at'=>time(),'attempts'=>$attempts,'retry_at'=>time()+min(3600,300*(2**min($attempts-1,4))),'message'=>$e->getMessage()],false);
        } finally {wp_set_current_user($previous);self::release($key,$stamp);}
    }
    /** Bounded pagination covers jobs older than the discovery window after an outage. */
    public static function jobIds(): array {
        global $wpdb;
        $after=(string)get_option('rrfr_match_followup_cursor','');
        $keys=$wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name>%s ORDER BY option_name LIMIT 100",$wpdb->esc_like('rrfr_match_job_').'%', $after));
        update_option('rrfr_match_followup_cursor',count($keys)===100?end($keys):'',false);
        return array_values(array_filter(array_map(static fn($k)=>preg_match('/^rrfr_match_job_([1-9][0-9]*)$/D',$k,$m)?(int)$m[1]:0,$keys)));
    }
    public static function ingest(array $feed,array $config): void {
        $tracked=[];
        foreach($feed['matches'] as $id=>$m) {
            $kickoff=strtotime($m['kickoff']);
            $senior=(bool)array_intersect([30365,48835],[$m['home']['id'],$m['away']['id']]);
            $youth=false;foreach(['home','away'] as $side)if(isset($feed['teams'][$m[$side]['id']]['age_class']))$youth=true;
            $since=$senior?(int)$config['since']:(int)get_option('rrfr_club_enabled_at',0);
            if((!$senior&&!$youth)||!$since||$kickoff<$since||$kickoff<time()-self::WINDOW||$kickoff>time())continue;
            $s=MatchJobs::state((int)$id);
            $reason=$m['cancelled']?'Avlyst':($m['postponed']?'Utsatt':($m['interrupted']?'Avbrutt':($m['walkover']?'Walkover – krever redaksjonell vurdering':($m['finished_confirmed']?'Kildens sluttresultat er bekreftet':'Venter på uttrykkelig sluttbekreftelse fra Fotballdata; ny kontroll om fem minutter.'))));
            $tracked[$id]=['match_id'=>(int)$id,'match'=>($m['home']['registered_name']??$m['home']['name']).' – '.($m['away']['registered_name']??$m['away']['name']),'kickoff'=>$m['kickoff'],
                'source_status'=>$m['finished_confirmed']?'finished':(ClubCoverage::blocked($m)?'exception':'waiting'), 'last_source_check'=>time(),'reason'=>$reason];
            if(!$senior) {
                // Preserve PR30's sole youth writer and its one-hour stable-result rule.
                $seen=get_option('rrfr_club_seen',[])[$id]??[];
                $tracked[$id]['due_at']=!empty($seen['first_seen'])?$seen['first_seen']+ClubAutomation::RESULT_WAIT:null;
                $tracked[$id]['status']=$m['finished_confirmed']?'queued':'waiting';
                if($p=ClubAutomation::existing('match:'.$id)) {
                    $tracked[$id]['post_id']=$p;$tracked[$id]['review_url']=PlayerReview::url($p);$tracked[$id]['post_status']=get_post($p)->post_status;
                    $tracked[$id]['quality_passed']=PublicationGate::current($p,get_post($p));
                    $tracked[$id]['status']=$tracked[$id]['quality_passed']?'done':'blocked';
                    $tracked[$id]['error']=(string)get_post_meta($p,'_rrfr_club_error',true);
                }
                if($error=get_option('rrfr_club_error_match_'.$id,[])){$tracked[$id]['status']='error';$tracked[$id]['error']=$error['message'];}
                continue;
            }
            if($m['finished_confirmed'])MatchJobs::sourceConfirmed((int)$id,$m);
            else MatchJobs::waitForSource((int)$id,$reason);
        }
        update_option('rrfr_match_followup_matches',$tracked,false);
    }
    /** Recheck fresh source data before preparing or continuing an automatic article. */
    public static function verify(int $id): array {
        $feed=ClubCoverage::collect();$m=$feed['matches'][$id]??null;
        if(!$m||!$m['finished_confirmed'])throw new \RuntimeException('Kilden bekrefter ikke ferdig kamp. Ny kildekontroll kreves.');
        MatchJobs::sourceConfirmed($id,$m,true);
        return $m;
    }
    public static function same(array $a,array $b): bool {
        return $a['id']===$b['id']&&$a['home']['id']===$b['home']['id']&&$a['away']['id']===$b['away']['id']
            &&$a['competition']['id']===$b['competition']['id']&&strtotime($a['kickoff'])===strtotime($b['kickoff'])&&$a['score']===$b['score'];
    }
    public static function rows(): array {
        $rows=get_option('rrfr_match_followup_matches',[]);
        foreach(self::readJobIds() as $id)$rows[$id]=($rows[$id]??['match_id'=>$id,'match'=>'Kamp '.$id])+MatchJobs::publicState($id);
        foreach($rows as $id=>$row)$rows[$id]=array_merge($row,MatchJobs::publicState((int)$id));
        return array_values($rows);
    }
    private static function readJobIds(): array {
        global $wpdb;
        $keys=$wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id DESC LIMIT 100",$wpdb->esc_like('rrfr_match_job_').'%'));
        return array_values(array_filter(array_map(static fn($k)=>preg_match('/^rrfr_match_job_([1-9][0-9]*)$/D',$k,$m)?(int)$m[1]:0,$keys)));
    }
    public static function panel(): void {
        echo '<section class="rrfr-card"><h2>Kampoppfølging</h2><p>Kildens sluttstatus styrer referatet. Speaker og avstemning styres separat. Manuell sluttgodkjenning kreves.</p>';
        $last=get_option('rrfr_match_followup_last',[]);
        if(!$last||time()-(int)$last['at']>900)echo '<p role="alert">Periodisk kampkontroll mangler eller er forsinket. Kontroller serverens scheduler.</p>';
        $runner=get_option('rrfr_match_followup_runner',[]);
        echo '<p>'.esc_html($runner?'Siste kjøring uten nettsidebesøk: '.wp_date('d.m.Y H:i',$runner['at']):'Ingen bekreftet serverkjøring uten nettsidebesøk registrert.').'</p>';
        if($e=get_option('rrfr_match_followup_error',[]))echo '<p role="alert">'.esc_html($e['message'].' · '.$e['attempts'].' mislykkede kildekontroller').'</p>';
        echo '<style>@media(max-width:600px){.rrfr-match-progress thead{display:none}.rrfr-match-progress,.rrfr-match-progress tbody,.rrfr-match-progress tr,.rrfr-match-progress td{display:block;width:auto}.rrfr-match-progress tr{padding:12px 0;border-bottom:1px solid #c3c4c7}.rrfr-match-progress td{padding:6px 12px;overflow-wrap:anywhere}.rrfr-match-progress td:before{content:attr(data-label);display:block;font-weight:600;margin-bottom:4px}}</style><div style="overflow:auto"><table class="widefat rrfr-match-progress"><thead><tr><th>Kamp</th><th>Sluttstatus / siste kildekontroll</th><th>Planlagt behandling</th><th>Jobbstatus / blokkering</th><th>Utkast</th></tr></thead><tbody>';
        foreach(self::rows() as $r) {
            $labels=['waiting'=>'Venter på sluttbekreftelse','queued'=>'I kø','running'=>'Under arbeid','error'=>'Feilet','blocked'=>'Blokkert – trenger kontroll','done'=>'Klar til godkjenning'];
            $status=$labels[$r['status']??'waiting']??'Trenger kontroll';
            if(($r['post_status']??'')==='publish')$status='Publisert';
            if(($r['status']??'')==='done'&&empty($r['quality_passed'])&&($r['post_status']??'')!=='publish')$status='Utkast – trenger kontroll';
            $phases=['prepare'=>'Klargjør kampgrunnlag','write'=>'Skriver referat','review'=>'Faktakontroll og språkvask'];
            echo '<tr><td data-label="Kamp">'.esc_html($r['match'].' ('.$r['match_id'].')').'</td><td data-label="Sluttstatus / siste kildekontroll">'.esc_html(($r['reason']??$r['source_status']??'Sluttstatus må kontrolleres').' · '.(!empty($r['last_source_check'])?wp_date('d.m.Y H:i',$r['last_source_check']):'Ingen kildekontroll registrert')).'</td><td data-label="Planlagt behandling">'.esc_html(!empty($r['due_at'])?wp_date('d.m.Y H:i',$r['due_at']):'Venter').'</td><td data-label="Jobbstatus / blokkering">'.esc_html($status.' · '.($phases[$r['phase']??'']??'').' '.($r['error']??'')).'</td><td data-label="Utkast">';
            if(!empty($r['review_url']))echo '<a href="'.esc_url($r['review_url']).'">Les utkast</a>';
            echo '</td></tr>';
        }
        echo '</tbody></table></div></section>';
    }
}
