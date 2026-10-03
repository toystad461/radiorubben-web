<?php
namespace RadioRubben\Fotballrobot;

/** One durable, staged job per newly archived match. Never publishes. */
final class MatchJobs {
    const CONFIG='rrfr_match_jobs';
    const HOOK='rrfr_match_job';
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
    public static function enqueue(int $id,$archive): void {
        $config=get_option(self::CONFIG,[]);
        if(empty($config['enabled'])||!self::eligible($id,$archive)||(int)($archive['saved_at']??0)<(int)$config['since'])return;
        $job=['status'=>'queued','phase'=>'prepare','owner'=>(int)$config['owner'],'created_at'=>time(),'updated_at'=>time()];
        if(!add_option('rrfr_match_job_'.$id,$job,'',false))return;
        self::schedule($id,'prepare',120);
    }
    private static function schedule(int $id,string $phase,int $delay=10): void {
        if(wp_next_scheduled(self::HOOK,[$id,$phase]))return;
        $r=wp_schedule_single_event(time()+$delay,self::HOOK,[$id,$phase],true);
        if(is_wp_error($r)||!$r){$s=self::state($id);$s['status']='error';$s['error']='Kunne ikke planlegge neste steg. Start referatet manuelt.';self::save($id,$s);}
    }
    public static function active(int $id): bool {return in_array(self::state($id)['status']??'', ['queued','running'],true);}
    public static function assertManual(int $id): void {if(self::active($id))throw new \RuntimeException('Automatisk referat er allerede i kø eller under arbeid. Se status på robotsiden.');}
    public static function existing(int $id) {
        $p=get_posts(['post_type'=>'post','post_status'=>['draft','pending','publish','private','future','trash'],'meta_key'=>'_rrfr_ai_match','meta_value'=>$id,'numberposts'=>1]);return $p[0]??null;
    }
    public static function run(int $id,string $phase): void {
        $s=self::state($id);if(($s['status']??'')!=='queued'||($s['phase']??'')!==$phase)return;
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
            $archive=get_option('rr_match_archive_'.$id,[]);
            if(!self::eligible($id,$archive))throw new \RuntimeException('Bekreftet kampslutt mangler i arkivet.');
            if($existing=self::existing($id)) {
                if($existing->post_status==='trash')throw new \RuntimeException('Referatet ligger i papirkurven. Gjenopprett det ved behov.');
                $s['status']='done';$s['post_id']=$existing->ID;self::save($id,$s);return;
            }
            if($phase==='prepare') {
                // Avoid a cached in-play score immediately after the final whistle.
                delete_transient('rrfr_source_match_'.$id);
                $f=Robot::refresh($id);
                if(!$f['finished_confirmed'])throw new \RuntimeException('Kampslutt er ikke bekreftet.');
                $s['fact_hash']=$f['fact_hash'];$s['angle']=$f['angles'][0]['id']??'result';$next='write';
            } elseif($phase==='write') {
                $r=Writer::generate($id,$s['fact_hash'],$s['angle']);
                $s['review_token']=$r['review_token']??'';$next='review';
                if($s['review_token']){
                    $review=get_transient('rrfr_review_'.$s['review_token']);
                    if($review)set_transient('rrfr_review_'.$s['review_token'],$review,DAY_IN_SECONDS);
                }
                if(!$s['review_token']&&!self::existing($id))throw new \RuntimeException('Skrivingen ga ikke et utkast til kontroll.');
            } elseif($phase==='review') {
                Writer::review($s['review_token']);
                $p=self::existing($id);if(!$p)throw new \RuntimeException('Kunne ikke bekrefte lagret referat.');
                $s['status']='done';$s['post_id']=$p->ID;unset($s['review_token']);self::save($id,$s);return;
            } else throw new \RuntimeException('Ukjent skrivesteg.');
            $s['status']='queued';$s['phase']=$next;self::save($id,$s);self::schedule($id,$next);
        } catch(\Throwable $e) {
            $s['status']='error';$s['error']=$e->getMessage();self::save($id,$s);
        } finally {wp_set_current_user($previous);delete_option($lock);}
    }
    public static function action(): void {
        if(!Robot::allowed())wp_die('Ingen tilgang.',403);
        check_admin_referer('rrfr_match_jobs');
        $op=(string)($_POST['operation']??'');$id=absint($_POST['match_id']??0);
        if($op==='enable')update_option(self::CONFIG,['enabled'=>true,'since'=>time(),'owner'=>get_current_user_id()],false);
        elseif($op==='disable'){$c=get_option(self::CONFIG,[]);$c['enabled']=false;update_option(self::CONFIG,$c,false);}
        wp_safe_redirect(admin_url('admin.php?page=rr-fotballrobot&match_id='.$id.'#automatisk-referat'));exit;
    }
    public static function panel(int $id): void {
        $c=get_option(self::CONFIG,[]);$s=self::state($id);$enabled=!empty($c['enabled']);
        echo '<section id="automatisk-referat" class="rrfr-card"><h2>Automatisk referat etter kampslutt</h2><p class="rrfr-badge">'.($enabled?'Aktivert for nye kamper':'Ikke aktivert').'</p><p>Når bekreftet kampslutt lagres i kamparkivet, hentes ferske kampdata. Fotballroboten velger en dokumentert vinkel, skriver med godkjente læringseksempler og faktakontrollerer teksten. Ett utkast per kamp, klart til gjennomlesing. Ingen automatisk publisering.</p><p>Første steg planlegges to minutter etter arkivering. WordPress kjører jobben ved nettstedstrafikk; nøyaktig leveringstid kan variere. En ny artikkel bruker to AI-kall via eksisterende tilkobling. Tidligere arkiverte kamper startes ikke på nytt.</p>';
        if($s){
            $labels=['queued'=>'Venter på neste steg','running'=>'Under arbeid','done'=>'Referatet er klart','error'=>'Stoppet – trenger kontroll'];
            echo '<p><strong>Status for valgt kamp: '.esc_html($labels[$s['status']]??$s['status']).'</strong></p>';
            if($s['status']==='running'&&time()-(int)$s['updated_at']>900)echo '<p>Arbeidet kan ha blitt avbrutt. Ingen automatisk ny skriving startes. Administrator må kontrollere jobben.</p>';
            if(!empty($s['error']))echo '<p role="status">'.esc_html($s['error']).'</p>';
            if(!empty($s['post_id']))echo '<p><a href="'.esc_url(get_edit_post_link($s['post_id'],'raw')).'">Åpne referatet for gjennomlesing</a></p>';
        }elseif($p=self::existing($id)){echo '<p>Denne kampen har allerede et referat og får ikke et nytt automatisk.</p>';}
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('rrfr_match_jobs');
        echo '<input type="hidden" name="action" value="rrfr_match_jobs"><input type="hidden" name="match_id" value="'.esc_attr($id).'"><input type="hidden" name="operation" value="'.($enabled?'disable':'enable').'"><button>'.($enabled?'Stopp automatisk kampreferat':'Aktiver for nye kampslutt').'</button></form></section>';
    }
}
add_action('added_option',[MatchJobs::class,'added'],10,2);
add_action('updated_option',[MatchJobs::class,'changed'],10,3);
add_action(MatchJobs::HOOK,[MatchJobs::class,'run'],10,2);
add_action('admin_post_rrfr_match_jobs',[MatchJobs::class,'action']);
