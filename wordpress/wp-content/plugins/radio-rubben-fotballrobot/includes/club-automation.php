<?php
namespace RadioRubben\Fotballrobot;

final class ClubAutomation {
    public static function active(): bool { return (int)get_option('rrfr_club_enabled_at',0)>0; }
    public static function schedules(array $s): array { $s['rrfr_halfhour']=['interval'=>1800,'display'=>'Hver halvtime']; return $s; }
    public static function register(): void {
        if (!self::active()) return;
        if (!wp_next_scheduled('rrfr_club_tick')) wp_schedule_event(time()+60,'rrfr_halfhour','rrfr_club_tick');
        if (!wp_next_scheduled('rrfr_club_weekly')) {
            $due=(int)get_option('rrfr_club_weekly_due',0);
            if (!$due) { $due=ClubCoverage::nextSunday(time()); update_option('rrfr_club_weekly_due',$due,false); }
            wp_schedule_single_event(max(time()+1,$due),'rrfr_club_weekly');
        }
    }
    public static function stop(): void {
        foreach (['rrfr_club_tick','rrfr_club_weekly'] as $hook) wp_clear_scheduled_hook($hook);
        // Jobs already queued are inert after disable; remove their known arguments as well.
        foreach (get_option('rrfr_club_seen',[]) as $id=>$unused) wp_clear_scheduled_hook('rrfr_club_match',[(int)$id]);
    }
    public static function error(string $job,\Throwable $e): void {
        update_option('rrfr_club_error_'.$job,['at'=>gmdate(DATE_ATOM),'message'=>$e->getMessage()],false);
    }
    public static function tick(): void {
        if (!self::active() || !add_option('rrfr_club_tick_lock',time(),'','no')) return;
        try {
            $feed=ClubCoverage::collect(); $seen=get_option('rrfr_club_seen',[]); $since=(int)get_option('rrfr_club_enabled_at',0);
            foreach ($feed['matches'] as $id=>$m) {
                // Existing senior automation remains the sole owner of senior match reports.
                $youth=false;
                foreach (['home','away'] as $side) if (isset($feed['teams'][$m[$side]['id']]['age_class'])) $youth=true;
                if (!$youth || strtotime($m['kickoff'])<$since || strtotime($m['kickoff'])>time()) continue;
                if (!$m['finished_confirmed']) { unset($seen[$id]); continue; }
                $hash=hash('sha256',wp_json_encode($m));
                if (($seen[$id]['hash']??'')!==$hash) $seen[$id]=['hash'=>$hash,'first_seen'=>time()];
                if (time()-$seen[$id]['first_seen']>=1800 && !wp_next_scheduled('rrfr_club_match',[(int)$id]) && !self::existing('match:'.$id)) {
                    wp_schedule_single_event(time()+10,'rrfr_club_match',[(int)$id]);
                }
            }
            update_option('rrfr_club_seen',$seen,false);
            update_option('rrfr_club_last',['at'=>$feed['fetched_at'],'teams'=>$feed['teams'],'match_count'=>count($feed['matches'])],false);
            delete_option('rrfr_club_error_matches');
        } catch (\Throwable $e) { self::error('matches',$e); }
        finally { delete_option('rrfr_club_tick_lock'); }
    }
    public static function match(int $id): void {
        if (!self::active()) return;
        try {
            if (self::existing('match:'.$id)) return;
            $feed=ClubCoverage::collect(); $m=$feed['matches'][$id]??null;
            $seen=get_option('rrfr_club_seen',[])[$id]??null;
            if (!$m || !$m['finished_confirmed'] || !$seen || time()-$seen['first_seen']<1800 || strtotime($m['kickoff'])<(int)get_option('rrfr_club_enabled_at',0)) return;
            if (!hash_equals($seen['hash'],hash('sha256',wp_json_encode($m)))) return; // Changed result restarts the wait on next tick.
            $facts=['match'=>$m,'finished_confirmed'=>true,'forms'=>['home'=>null,'away'=>null],'lineups'=>[],
                'warnings'=>['Kampfeed gir resultat, men ikke dokumenterte målscorere, bytter eller kampforløp.'],
                'sources'=>[['url'=>$m['source'],'provider'=>'Fotballdata','fetched_at'=>$feed['fetched_at']]],'created_at'=>$feed['fetched_at']];
            self::create('match:'.$id,$facts,static function() use($facts,$m) {
                $a=Writer::clubArticle($facts); $body='<p><small>Automatisk generert kampoppsummering fra Radio Rubben, basert på Fotballdata.</small></p>';
                foreach (array_merge([$a['lead']],$a['paragraphs']) as $p) $body.='<!-- wp:paragraph --><p>'.esc_html($p).'</p><!-- /wp:paragraph -->';
                $body.='<p><small>Kilde: <a href="'.esc_url($m['source']).'">Kampens registrering hos NFF</a></small></p>';
                return ['post_title'=>$a['title'],'post_excerpt'=>$a['lead'],'post_content'=>$body,'meta_input'=>['_rrfr_ai_checks'=>$a['checks'],'_rrfr_original_article'=>['title'=>$a['title'],'paragraphs'=>array_merge([$a['lead']],$a['paragraphs'])]]];
            });
        } catch (\Throwable $e) { self::error('match_'.$id,$e); }
    }
    public static function existing(string $key): int {
        $query=['post_type'=>'post','post_status'=>['draft','pending','publish','private','future','trash'],'numberposts'=>1,'fields'=>'ids','meta_key'=>'_rrfr_club_key','meta_value'=>$key];
        $found=get_posts($query);
        if (!$found && str_starts_with($key,'match:')) {
            $query['meta_key']='_rrfr_ai_match'; $query['meta_value']=(int)substr($key,6); $found=get_posts($query);
        }
        return $found?(int)$found[0]:0;
    }
    public static function create(string $key,array $facts,callable $build): int {
        $lock=str_starts_with($key,'match:')?'rrfr_ai_lock_'.(int)substr($key,6):'rrfr_club_write_'.hash('sha256',$key);
        if (!add_option($lock,time(),'','no')) throw new \RuntimeException('Artikkelen behandles allerede. Kontroller avbrutte jobber før låsen fjernes.');
        try {
            if ($id=self::existing($key)) return $id;
            $meta=['_rrfr_club_key'=>$key,'_rrfr_club_status'=>'writing','_rrfr_fact_snapshot'=>$facts];
            if (str_starts_with($key,'match:')) $meta['_rrfr_ai_match']=(int)substr($key,6);
            $categories=[];
            foreach (['sport','fotball','bremnes-il'] as $slug) { $term=get_term_by('slug',$slug,'category'); if ($term && !is_wp_error($term)) $categories[]=(int)$term->term_id; }
            $id=wp_insert_post(['post_type'=>'post','post_status'=>'draft','post_title'=>'Fotballroboten – klargjør artikkel','post_category'=>$categories,'meta_input'=>$meta],true);
            if (is_wp_error($id)) throw new \RuntimeException('Kunne ikke reservere utkast.');
            $original=get_post($id); $hash=hash('sha256',$original->post_title."\n".$original->post_content);
            try {
                $article=$build(); $current=get_post($id);
                if ($current->post_status!=='draft' || !hash_equals($hash,hash('sha256',$current->post_title."\n".$current->post_content))) throw new \RuntimeException('Artikkelen ble redigert under skriving. Endringene er beholdt.');
                $result=wp_update_post(['ID'=>$id]+$article,true);
                if (is_wp_error($result)) throw new \RuntimeException('Utkastet kunne ikke lagres.');
                update_post_meta($id,'_rrfr_club_status','review');
            } catch (\Throwable $e) { update_post_meta($id,'_rrfr_club_status','failed'); update_post_meta($id,'_rrfr_club_error',$e->getMessage()); throw $e; }
            return (int)$id;
        } finally { delete_option($lock); }
    }
    public static function weekly(): void {
        if (!self::active() || !add_option('rrfr_club_weekly_lock',time(),'','no')) return;
        $due=(int)get_option('rrfr_club_weekly_due',0);
        try {
            if (!$due || time()<$due) return;
            $week=ClubCoverage::weekForSunday($due);
            if (time()>=strtotime($week['end'])) throw new \RuntimeException('Ukesartikkelen er utløpt; kontroller serverens cron.');
            $key='week:'.$week['key'];
            if (!self::existing($key)) {
                $feed=ClubCoverage::collect(); $matches=ClubCoverage::weekMatches($feed['matches'],$week);
                self::create($key,['week'=>$week,'matches'=>$matches,'fetched_at'=>$feed['fetched_at'],'provider'=>'Fotballdata'],static fn()=>self::weeklyArticle($matches,$week,$feed['fetched_at']));
            }
            delete_option('rrfr_club_error_weekly');
            update_option('rrfr_club_weekly_due',ClubCoverage::nextSunday(time()),false);
        } catch (\Throwable $e) {
            self::error('weekly',$e);
            if ($due && time()>=strtotime(ClubCoverage::weekForSunday($due)['end'])) update_option('rrfr_club_weekly_due',ClubCoverage::nextSunday(time()),false);
        } finally {
            delete_option('rrfr_club_weekly_lock');
            $next=(int)get_option('rrfr_club_weekly_due',0);
            if (!wp_next_scheduled('rrfr_club_weekly')) wp_schedule_single_event(max(time()+1800,$next),'rrfr_club_weekly');
        }
    }
    public static function weeklyArticle(array $matches,array $week,string $fetched): array {
        $tz=new \DateTimeZone('Europe/Oslo');
        $period=wp_date('j. F',strtotime($week['start']),$tz).'–'.wp_date('j. F Y',strtotime($week['end'])-1,$tz);
        $title='Dette er Bremnes-kampene '.$period;
        $lead=$matches?'Bremnes har '.count($matches).' registrerte kamper fra G13/J13 og oppover i perioden '.$period.'. Her er oversikten over hjemme- og bortekampene.':'Det er ingen registrerte Bremnes-kamper fra G13/J13 og oppover i perioden '.$period.' i det kontrollerte kampgrunnlaget.';
        $body='<p>'.esc_html($lead).'</p>';
        foreach ($matches as $m) {
            $home=$m['home']['registered_name']??$m['home']['name']; $away=$m['away']['registered_name']??$m['away']['name'];
            $body.='<h2>'.esc_html($home.' – '.$away).'</h2><p>'.esc_html(wp_date('l j. F \k\l. H:i',strtotime($m['kickoff']),$tz).' · '.$m['venue'].' · '.$m['competition']['name']).' · <a href="'.esc_url($m['source']).'">Kampinformasjon</a></p>';
        }
        $body.='<p><small>Oversikten er automatisk laget av Radio Rubben fra Fotballdata. Oppdatert '.esc_html(wp_date('d.m.Y H:i',strtotime($fetched),$tz)).'. Kampoppsettet kan endres. Avlyste, utsatte, avbrutte og walkover-kamper er utelatt.</small></p>';
        return ['post_title'=>$title,'post_excerpt'=>$lead,'post_content'=>$body];
    }
    public static function menu(): void { add_submenu_page('rr-fotballrobot','Bremnes fra 13 år','Bremnes fra 13 år','manage_options','rrfr-club',[self::class,'page']); }
    public static function action(): void {
        if (!Robot::allowed()) wp_die('Ingen tilgang.',403);
        check_admin_referer('rrfr_club');
        try {
            if (($_POST['operation']??'')==='enable') {
                ClubCoverage::collect(); // Validate actual API access before scheduling.
                if (Writer::key()==='') throw new \RuntimeException('AI-oppsett mangler.');
                if (!self::active()) update_option('rrfr_club_enabled_at',time(),false);
                self::register();
            } elseif (($_POST['operation']??'')==='disable') { update_option('rrfr_club_enabled_at',0,false); self::stop(); }
            else throw new \RuntimeException('Ukjent handling.');
        } catch (\Throwable $e) { self::error('configuration',$e); }
        wp_safe_redirect(admin_url('admin.php?page=rrfr-club')); exit;
    }
    public static function page(): void {
        if (!Robot::allowed()) wp_die('Ingen tilgang.',403);
        echo '<div class="wrap"><h1>Bremnes fra 13 år</h1><p>'.esc_html(self::active()?'Automatikken er aktiv.':'Automatikken er ikke aktivert.').'</p><p>Kampoppsummeringer for G13/J13 og eldre ungdomslag. Seniorreferater beholder eksisterende kjøring. Ukesoversikten inkluderer også seniorlagene.</p><p>Søndag kl. 18.00, Europe/Oslo: én artikkel om kommende mandag–søndag. Alt lagres som utkast til gjennomlesning. Ingen automatisk publisering eller e-post.</p><p>For presis kjøring må serveren utløse WordPress Cron minst hvert minutt. Ved forsinkelse beholdes riktig uke. Ny kontroll ved kildefeil skjer etter 30 minutter.</p>';
        $last=get_option('rrfr_club_last',[]);
        if ($last) echo '<p>Siste vellykkede kontroll: '.esc_html($last['at']).' · '.count($last['teams']).' lag.</p>';
        foreach (['configuration','matches','weekly'] as $job) { $e=get_option('rrfr_club_error_'.$job,[]); if ($e) echo '<p role="alert">'.esc_html($e['message'].' · '.$e['at']).'</p>'; }
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'; wp_nonce_field('rrfr_club');
        echo '<input type="hidden" name="action" value="rrfr_club"><button class="button" name="operation" value="'.(self::active()?'disable':'enable').'">'.(self::active()?'Stans automatikk':'Kontroller tilgang og aktiver').'</button></form><h2>Artikler</h2><ul>';
        foreach (get_posts(['post_type'=>'post','post_status'=>['draft','pending','publish','private','future'],'numberposts'=>30,'meta_key'=>'_rrfr_club_key']) as $p) {
            echo '<li><a href="'.esc_url(get_edit_post_link($p->ID,'raw')).'">'.esc_html($p->post_title).'</a> · '.esc_html(get_post_meta($p->ID,'_rrfr_club_status',true));
            if ($error=get_post_meta($p->ID,'_rrfr_club_error',true)) echo ' · '.esc_html($error);
            echo '</li>';
        }
        echo '</ul></div>';
    }
}
