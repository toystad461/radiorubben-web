<?php
namespace RadioRubben\Fotballrobot;

/** One durable, staged job per newly archived match. Never publishes. */
final class MatchJobs {
    const CONFIG='rrfr_match_jobs';
    const HOOK='rrfr_match_job';
    const DELAY=3600;
    const MAX_RETRIES=6;
    public static function sourceConfirmed(int $id,array $m,bool $locked=false): void {
        if(($m['id']??0)!==$id)throw new \RuntimeException('Kamp-ID og kilde er ikke enige.');
        $key='rrfr_match_job_lock_'.$id;
        if(!$locked&&!add_option($key,time(),'',false))return;
        try{self::sourceConfirmedLocked($id,$m);}finally{if(!$locked)delete_option($key);}
    }
    private static function sourceConfirmedLocked(int $id,array $m): void {
        $config=get_option(self::CONFIG,[]);
        if(empty($config['enabled'])||!$m['finished_confirmed']||strtotime($m['kickoff'])<(int)$config['since'])return;
        $key='rrfr_match_source_'.$id;$old=get_option($key,[]);$s=self::state($id);
        $same=$old&&MatchFollowup::same($old['match'],$m);
        // The configured adapter has no documented final-whistle timestamp. Never infer one.
        $at=$same?(int)$old['confirmed_at']:time();
        update_option($key,['match'=>$m,'confirmed_at'=>$at,'checked_at'=>time(),'provider'=>'Fotballdata'],false);
        if(self::existing($id))return;
        if(!$s) {
            $s=['status'=>'queued','phase'=>'prepare','owner'=>(int)$config['owner'],'confirmed_at'=>$at,'due_at'=>$at+self::DELAY,'created_at'=>time(),'updated_at'=>time()];
            if(add_option('rrfr_match_job_'.$id,$s,'',false))self::schedule($id,'prepare',self::DELAY);
            return;
        }
        if(!$same&&$old) {
            if(MatchWork::forMatch($id)) {$s['status']='blocked';$s['error']='Kildedata er endret etter skriving. Lagret tekst beholdes; redaksjonell kontroll kreves.';self::save($id,$s);return;}
            $s['phase']='prepare';$s['confirmed_at']=$at;$s['due_at']=$at+self::DELAY;
        }
        if(($s['status']??'')==='waiting'){$s['status']='queued';unset($s['error']);}
        $s['last_source_check']=time();$s['source_status']='finished';self::save($id,$s);
        if($s['status']==='queued')self::schedule($id,$s['phase'],max(10,$s['due_at']-time()));
    }
    public static function waitForSource(int $id,string $reason): void {
        $key='rrfr_match_job_lock_'.$id;if(!add_option($key,time(),'',false))return;
        try {
            $source=get_option('rrfr_match_source_'.$id,[]);
            if($source){$source['revoked']=true;$source['checked_at']=time();update_option('rrfr_match_source_'.$id,$source,false);}
            $s=self::state($id);if(!$s||in_array($s['status'],['done','blocked','running'],true))return;
            $s['status']='waiting';$s['error']=$reason;$s['last_source_check']=time();$s['source_status']='waiting';self::save($id,$s);
        } finally{delete_option($key);}
    }
    public static function recover(int $id): void {
        $s=self::state($id);if(!$s)return;
        $lock='rrfr_match_job_lock_'.$id;$stamp=(int)get_option($lock,0);
        if($stamp&&$stamp>=time()-MatchFollowup::STALE)return;
        if($p=self::existing($id)) {
            $s['post_id']=$p->ID;$s['status']=$p->post_status==='publish'||PublicationGate::current($p->ID,$p)?'done':'blocked';
            if($p->post_status==='trash')$s['error']='Referatet ligger i papirkurven. Gjenopprett ved behov.';
            self::save($id,$s);if($stamp)MatchFollowup::release($lock,$stamp);return;
        }
        if(($s['status']??'')==='running'||$stamp) {
            $work=MatchWork::forMatch($id);
            if(in_array($s['phase'],['write','review'],true)&&(!$work||!empty($work['in_flight']))) {
                $s['status']='blocked';$s['error']='AI-behandling avbrutt med ukjent utfall. Ingen automatisk gjentakelse. Kontroller siste sikre steg.';self::save($id,$s);return;
            }
            if($stamp&&!MatchFollowup::release($lock,$stamp))return;
            if($work) {
                $aiLock=(int)get_option('rrfr_ai_lock_'.$id,0);
                if($aiLock&&$aiLock<time()-MatchFollowup::STALE)MatchFollowup::release('rrfr_ai_lock_'.$id,$aiLock);
                $s['review_token']=$work['token'];$s['phase']='review';
            }
            $s['status']='queued';self::save($id,$s);
        }
        if(($s['status']??'')==='error'&&($s['phase']??'prepare')!=='prepare'&&empty($s['safe_retry'])) {
            $s['status']='blocked';$s['error']='Eldre avbrutt skrivesteg har ukjent utfall. Lagret arbeid må kontrolleres.';self::save($id,$s);return;
        }
        if(($s['status']??'')==='error'&&(int)($s['attempts']??0)<self::MAX_RETRIES&&time()>=(int)($s['retry_at']??0)) {$s['status']='queued';self::save($id,$s);}
        if(($s['status']??'')==='queued')self::schedule($id,$s['phase'],max(10,(int)($s['retry_at']??$s['due_at'])-time()));
    }
    public static function state(int $id): array { $s=get_option('rrfr_match_job_'.$id,[]);return is_array($s)?$s:[]; }
    private static function save(int $id,array $s): void {
        $s['updated_at']=time();update_option('rrfr_match_job_'.$id,$s,false);
        if(self::state($id)!==$s)throw new \RuntimeException('Jobbstatus kunne ikke lagres.');
    }
    public static function added($key,$value): void {self::changed($key,false,$value);}
    public static function changed($key,$old,$value): void {
        if(!preg_match('/^rr_match_archive_([1-9][0-9]{0,8})$/D',(string)$key)||!empty($old['state']['finished']))return;
        $id=(int)substr($key,17);self::enqueue($id,$value);
    }
    public static function eligible(int $id,$a): bool {
        return is_array($a)&&!empty($a['state']['finished'])&&(int)($a['match']['id']??0)===$id
            && (in_array((int)($a['match']['home_id']??0),[30365,48835],true)||in_array((int)($a['match']['away_id']??0),[30365,48835],true));
    }
    public static function confirmed(int $id,array $m): bool {
        $o=get_option('rrfr_finish_observation_'.$id,[]);
        return is_array($o)&&($o['match_id']??0)===$id&&($o['score']??null)===$m['score']
            &&($o['home_id']??0)===$m['home']['id']&&($o['away_id']??0)===$m['away']['id']
            &&($o['kickoff']??'')===$m['kickoff']&&($o['source_url']??'')===$m['source'];
    }
    public static function publicState(int $id): array {
        $s=self::state($id);
        $out=array_intersect_key($s,array_flip(['status','phase','confirmed_at','due_at','created_at','updated_at','post_id','error','last_source_check','source_status','attempts','retry_at']));
        if($p=self::existing($id)) {
            $out['post_id']=$p->ID;$out['post_status']=$p->post_status;
            $out['edit_url']=get_edit_post_link($p->ID,'raw');
            $out['review_url']=admin_url('admin.php?page=rrfr-player-review&post_id='.(int)$p->ID);
            $out['quality_passed']=PublicationGate::current($p->ID,$p);
        }
        return $out+['match_id'=>$id,'delay_seconds'=>self::DELAY];
    }
    /** Authenticated observer must independently verify full time; no inferred end timestamp. */
    public static function observe(int $id,bool $confirmed,string $source): array {
        if(!Robot::allowed()||!$confirmed||$source!=='https://www.fotball.no/fotballdata/kamp/?fiksId='.$id)
            throw new \RuntimeException('Uavhengig bekreftet kampslutt og konkret NFF-kilde kreves.');
        $config=get_option(self::CONFIG,[]);
        if(empty($config['enabled']))throw new \RuntimeException('Automatisk referat er ikke aktivert.');
        if(self::existing($id)||self::state($id))return self::publicState($id);
        $f=Robot::refresh($id,true);$m=$f['match'];
        if(!$f['finished_confirmed']||strtotime($m['kickoff'])<(int)$config['since'])
            throw new \RuntimeException('Kampen er ikke ferdig eller er eldre enn oppstarten.');
        $observation=['match_id'=>$id,'home_id'=>$m['home']['id'],'away_id'=>$m['away']['id'],
            'score'=>$m['score'],'kickoff'=>$m['kickoff'],'source_url'=>$source,'observed_at'=>time(),'observed_by'=>get_current_user_id()];
        add_option('rrfr_finish_observation_'.$id,$observation,'',false);
        if(!self::confirmed($id,$m))throw new \RuntimeException('Kampsluttbekreftelsen avviker. Kontroller kampen.');
        $o=get_option('rrfr_finish_observation_'.$id);
        $job=['status'=>'queued','phase'=>'prepare','owner'=>(int)$config['owner'],
            'confirmed_at'=>$o['observed_at'],'due_at'=>$o['observed_at']+self::DELAY,'created_at'=>time(),'updated_at'=>time()];
        if(add_option('rrfr_match_job_'.$id,$job,'',false))self::schedule($id,'prepare',max(0,$job['due_at']-time()));
        return self::publicState($id);
    }
    public static function enqueue(int $id,$archive): void {
        $config=get_option(self::CONFIG,[]);
        if(empty($config['enabled'])||!self::eligible($id,$archive)||(int)($archive['saved_at']??0)<(int)$config['since'])return;
        $confirmed=time();
        $job=['status'=>'queued','phase'=>'prepare','owner'=>(int)$config['owner'],'confirmed_at'=>$confirmed,'due_at'=>$confirmed+self::DELAY,'created_at'=>time(),'updated_at'=>time()];
        if(self::existing($id)||!add_option('rrfr_match_job_'.$id,$job,'',false))return;
        self::schedule($id,'prepare',self::DELAY);
    }
    private static function schedule(int $id,string $phase,int $delay=10): void {
        if(wp_next_scheduled(self::HOOK,[$id,$phase]))return;
        $r=wp_schedule_single_event(time()+$delay,self::HOOK,[$id,$phase],true);
        if(is_wp_error($r)||!$r){$s=self::state($id);$s['status']='error';$s['error']='Kunne ikke planlegge neste steg. Start referatet manuelt.';self::save($id,$s);}
    }
    public static function active(int $id): bool {return in_array(self::state($id)['status']??'', ['queued','running','waiting','blocked','error'],true);}
    public static function retry(int $id): void {
        if(!Robot::allowed())throw new \RuntimeException('Ingen tilgang.');
        $s=self::state($id);$work=MatchWork::forMatch($id);
        if(!$s||!in_array($s['status'],['waiting','error','blocked'],true)||self::existing($id)||get_option('rrfr_match_job_lock_'.$id)
            ||get_option('rrfr_ai_lock_'.$id)||!empty($work['in_flight']))throw new \RuntimeException('Jobben eller et uavklart AI-kall må kontrolleres før gjenopptakelse.');
        if(($s['phase']??'prepare')!=='prepare'&&!$work&&empty($s['safe_retry']))throw new \RuntimeException('Uavklart eldre skrivesteg kan ikke gjentas automatisk.');
        $s['status']='queued';$s['attempts']=0;unset($s['error'],$s['retry_at']);
        $s['phase']=$work?'review':'prepare';if($work)$s['review_token']=$work['token'];
        self::save($id,$s);self::schedule($id,$s['phase'],max(10,$s['due_at']-time()));
    }
    public static function assertManual(int $id): void {if(self::active($id))throw new \RuntimeException('Automatisk referat er allerede i kø eller under arbeid. Se status på robotsiden.');}
    public static function existing(int $id) {
        $q=['post_type'=>'post','post_status'=>['draft','pending','publish','private','future','trash'],'numberposts'=>1];
        $p=get_posts($q+['meta_key'=>'_rrfr_ai_match','meta_value'=>$id]);
        if(!$p)$p=get_posts($q+['name'=>'rubben-kamp-'.$id]);
        if(!$p)$p=get_posts($q+['name'=>'rubben-kamp-'.$id.'__trashed']);
        return $p[0]??null;
    }
    public static function run(int $id,string $phase): void {
        $s=self::state($id);if(($s['status']??'')!=='queued'||($s['phase']??'')!==$phase)return;
        $due=(int)($s['due_at']??((int)$s['created_at']+self::DELAY));
        $due=max($due,(int)($s['retry_at']??0));
        if(time()<$due){self::schedule($id,$phase,$due-time());return;}
        // This lock stays behind after a hard timeout; never repeat a potentially paid call.
        $lock='rrfr_match_job_lock_'.$id;
        if(!add_option($lock,time(),'',false))return;
        $previous=get_current_user_id();
        try {
            wp_cache_delete('rrfr_match_job_'.$id,'options');
            $s=self::state($id);
            if(($s['status']??'')!=='queued'||($s['phase']??'')!==$phase)return;
            $s['status']='running';self::save($id,$s);
            $config=get_option(self::CONFIG,[]);
            if(empty($config['enabled']))throw new \RuntimeException('Automatisk kampreferat er stoppet.');
            wp_set_current_user($s['owner']);
            if(!Robot::allowed())throw new \RuntimeException('Administratorens skrivetilgang mangler.');
            if($existing=self::existing($id)) {
                if($existing->post_status==='trash')throw new \RuntimeException('Referatet ligger i papirkurven. Gjenopprett det ved behov.');
                $s['status']=$existing->post_status==='publish'||PublicationGate::current($existing->ID,$existing)?'done':'blocked';$s['post_id']=$existing->ID;self::save($id,$s);return;
            }
            $source=MatchFollowup::verify($id);$updated=self::state($id);
            if($updated['status']==='blocked')return;
            $s=array_merge($s,$updated);
            if(time()<(int)$s['due_at']){$s['status']='queued';self::save($id,$s);self::schedule($id,$s['phase'],$s['due_at']-time());return;}
            if($phase!=='prepare'&&!MatchFollowup::same($source,Robot::latest($id)['match']))throw new \RuntimeException('Kildegrunnlaget er endret. Lagret tekst beholdes.');
            if($phase==='prepare') {
                // Avoid a cached in-play score immediately after the final whistle.
                delete_transient('rrfr_source_match_'.$id);
                $f=Robot::refresh($id,false,$source);
                if(!$f['finished_confirmed'])throw new \RuntimeException('Kampslutt er ikke bekreftet.');
                $s['fact_hash']=$f['fact_hash'];$s['angle']=$f['angles'][0]['id']??'result';$next='write';
            } elseif($phase==='write') {
                $r=Writer::generate($id,$s['fact_hash'],$s['angle'],false,true);
                $s['review_token']=$r['review_token']??'';$next='review';
                if($s['review_token']){
                    $review=get_transient('rrfr_review_'.$s['review_token']);
                    if($review)set_transient('rrfr_review_'.$s['review_token'],$review,DAY_IN_SECONDS);
                }
                if(!$s['review_token']&&!self::existing($id))throw new \RuntimeException('Skrivingen ga ikke et utkast til kontroll.');
            } elseif($phase==='review') {
                $r=Writer::review($s['review_token']);
                if(!empty($r['review_token'])) {
                    $s['status']='queued';self::save($id,$s);self::schedule($id,'review');return;
                }
                $p=self::existing($id);if(!$p)throw new \RuntimeException('Kunne ikke bekrefte lagret referat.');
                $s['status']=PublicationGate::current($p->ID,$p)?'done':'blocked';$s['post_id']=$p->ID;unset($s['review_token']);self::save($id,$s);return;
            } else throw new \RuntimeException('Ukjent skrivesteg.');
            $s['status']='queued';$s['phase']=$next;unset($s['error'],$s['retry_at']);self::save($id,$s);self::schedule($id,$next);
        } catch(\Throwable $e) {
            $s['attempts']=(int)($s['attempts']??0)+1;$s['error']=$e->getMessage();
            $work=MatchWork::forMatch($id);
            $safe=$phase==='prepare'||empty($work['in_flight']);
            $s['safe_retry']=$safe;
            $s['status']=$safe?'error':'blocked';
            $s['retry_at']=time()+min(3600,300*(2**min($s['attempts']-1,4)));self::save($id,$s);
            if($safe&&$s['attempts']<self::MAX_RETRIES)self::schedule($id,$phase,$s['retry_at']-time());
        } finally {wp_set_current_user($previous);delete_option($lock);}
    }
    public static function action(): void {
        if(!Robot::allowed())wp_die('Ingen tilgang.',403);
        check_admin_referer('rrfr_match_jobs');
        $op=(string)($_POST['operation']??'');$id=absint($_POST['match_id']??0);
        if($op==='enable')update_option(self::CONFIG,['enabled'=>true,'since'=>time(),'owner'=>get_current_user_id()],false);
        elseif($op==='disable'){$c=get_option(self::CONFIG,[]);$c['enabled']=false;update_option(self::CONFIG,$c,false);}
        elseif($op==='retry')try{self::retry($id);}catch(\Throwable $e){set_transient('rrfr_notice_'.get_current_user_id(),$e->getMessage(),120);}
        wp_safe_redirect(admin_url('admin.php?page=rr-fotballrobot&match_id='.$id.'#automatisk-referat'));exit;
    }
    public static function panel(int $id): void {
        $c=get_option(self::CONFIG,[]);$s=self::state($id);$enabled=!empty($c['enabled']);
        echo '<section id="automatisk-referat" class="rrfr-card"><h2>Automatisk referat etter kampslutt</h2><p class="rrfr-badge">'.($enabled?'Aktivert for nye kamper':'Ikke aktivert').'</p><p>Registrerte kamper kontrolleres automatisk mot Fotballdata, uavhengig av speaker og avstemning. Ved bekreftet kampslutt hentes ferske kampdata. Fotballroboten velger en dokumentert vinkel, skriver med godkjente læringseksempler og faktakontrollerer teksten. Ett utkast per kamp, klart til gjennomlesing. Ingen automatisk publisering.</p><p>Første steg planlegges én time etter registrert, bekreftet kampslutt. Mangler kilden et sluttidspunkt, regnes timen fra første bekreftelse. Avspark og siste kamphendelse brukes aldri som sluttid. En separat serverkjøring må være aktivert for behandling uten nettsidebesøk. Se samlet kampoppfølging i godkjenningsflaten. En ny artikkel bruker tre til fire AI-kall via eksisterende tilkobling, fordelt på separate steg. Eksisterende artikler overskrives ikke.</p>';
        if($s){
            $labels=['waiting'=>'Venter på sluttbekreftelse','queued'=>'I kø','running'=>'Under arbeid','done'=>'Referatet er klart','blocked'=>'Blokkert – trenger kontroll','error'=>'Feilet – trenger kontroll'];
            echo '<p><strong>Status for valgt kamp: '.esc_html($labels[$s['status']]??$s['status']).'</strong></p>';
            if($s['status']==='running'&&time()-(int)$s['updated_at']>900)echo '<p>Arbeidet kan ha blitt avbrutt. Ingen automatisk ny skriving startes. Administrator må kontrollere jobben.</p>';
            if(!empty($s['due_at']))echo '<p>Tidligste start: '.esc_html(wp_date('d.m.Y H:i',$s['due_at'],new \DateTimeZone('Europe/Oslo'))).'</p>';
            if(!empty($s['error']))echo '<p role="status">'.esc_html($s['error']).'</p>';
            if(!empty($s['post_id']))echo '<p><a href="'.esc_url(PlayerReview::url((int)$s['post_id'])).'">Les og godkjenn referatet</a></p>';
        }elseif($p=self::existing($id)){echo '<p>Denne kampen har allerede et referat og får ikke et nytt automatisk.</p>';}
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('rrfr_match_jobs');
        echo '<input type="hidden" name="action" value="rrfr_match_jobs"><input type="hidden" name="match_id" value="'.esc_attr($id).'"><input type="hidden" name="operation" value="'.($enabled?'disable':'enable').'"><button>'.($enabled?'Stopp automatisk kampreferat':'Aktiver for nye kampslutt').'</button></form></section>';
        if(in_array($s['status']??'',['waiting','error','blocked'],true)) {
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('rrfr_match_jobs');
            echo '<input type="hidden" name="action" value="rrfr_match_jobs"><input type="hidden" name="operation" value="retry"><input type="hidden" name="match_id" value="'.esc_attr($id).'"><button>Gjenoppta siste sikre steg</button><p>Uavklarte AI-kall og eksisterende artikler gjentas ikke.</p></form>';
        }
    }
}
add_action('added_option',[MatchJobs::class,'added'],10,2);
add_action('updated_option',[MatchJobs::class,'changed'],10,3);
add_action(MatchJobs::HOOK,[MatchJobs::class,'run'],10,2);
add_action('admin_post_rrfr_match_jobs',[MatchJobs::class,'action']);
