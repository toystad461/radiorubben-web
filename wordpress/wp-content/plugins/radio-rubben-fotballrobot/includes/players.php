<?php
namespace RadioRubben\Fotballrobot;

final class Players {
    public const KINDS=['match'=>'Ny kamp','lineup'=>'Startellever / tropp','goals'=>'Mål','cards'=>'Kort','statistics'=>'Sesongstatistikk','club'=>'Klubbendring'];
    public static function register(): void {
        register_post_type('rr_robot_player',['label'=>'Spillere jeg følger','public'=>false,'show_ui'=>false,'show_in_rest'=>false,'rewrite'=>false,'supports'=>['title']]);
        if(!wp_next_scheduled('rrfr_players_tick')) wp_schedule_event(time()+300,'hourly','rrfr_players_tick');
        if(!wp_next_scheduled('rrfr_profiles_tick')) wp_schedule_event(time()+60,'rrfr_five_minutes','rrfr_profiles_tick');
    }
    public static function stop(): void { wp_clear_scheduled_hook('rrfr_players_tick'); wp_clear_scheduled_hook('rrfr_profiles_tick'); }
    public static function schedules(array $s): array { $s['rrfr_five_minutes']=['interval'=>300,'display'=>'Spillerprofiler hvert femte minutt'];return $s; }
    public static function menu(): void { add_submenu_page('rr-fotballrobot','Spillere jeg følger','Spillere jeg følger','manage_options','rr-fotballrobot-players',[self::class,'page']); }
    public static function ids(): array { return get_posts(['post_type'=>'rr_robot_player','post_status'=>'private','numberposts'=>-1,'fields'=>'ids','orderby'=>'ID','order'=>'ASC']); }
    public static function state(int $id): array {
        if(get_post_type($id)!=='rr_robot_player') throw new \RuntimeException('Spillerprofilen finnes ikke.');
        $s=get_option('rrfr_player_'.$id);
        if(!is_array($s)) throw new \RuntimeException('Spillerprofilen mangler lagrede data.');
        return $s;
    }
    private static function put(int $id,array $s): void {
        if(!update_option('rrfr_player_'.$id,$s,false) && get_option('rrfr_player_'.$id)!==$s) throw new \RuntimeException('Spillerdata kunne ikke lagres.');
    }
    private static function locked(string $key,callable $fn) {
        // Fail closed after a hard process interruption: no overlapping writes or duplicate drafts.
        if(!add_option('rrfr_player_lock_'.$key,time(),'','no')) throw new \RuntimeException('Denne spilleren behandles allerede. En avbrutt jobb må kontrolleres av administrator.');
        try { return $fn(); } finally { delete_option('rrfr_player_lock_'.$key); }
    }
    public static function save(array $input): int {
        return self::locked('profiles',static function() use($input) {
            $id=absint($input['id']??0); $fiks=PlayerFacts::id((string)($input['fiks']??'')); $name=sanitize_text_field($input['name']??'');
            if($name==='') throw new \RuntimeException('Skriv inn spillerens navn.');
            foreach(self::ids() as $other) if((int)$other!==$id && self::state((int)$other)['fiks_id']===$fiks) throw new \RuntimeException('Denne FIKS-ID-en følges allerede.');
            $apply=static function() use($id,$fiks,$name,$input) {
                $s=$id?self::state($id):['version'=>1,'snapshot'=>null,'events'=>[],'revision'=>0,'last_checked'=>null,'error'=>null];
                if($id && $s['fiks_id']!==$fiks) throw new \RuntimeException('FIKS-ID kan ikke endres på en lagret profil. Legg til en ny spiller.');
                $s['name']=$name; $s['fiks_id']=$fiks; $s['note']=sanitize_textarea_field($input['note']??'');
                $s['club_note']=sanitize_text_field($input['club_note']??'');
                $s['group']=($input['group']??'')==='bomlo-away'?'bomlo-away':'';
                $s['enabled']=!empty($input['enabled']);
                $s['watch']=array_values(array_intersect(array_keys(self::KINDS),is_array($input['watch']??null)?$input['watch']:[]));
                $pid=$id;
                if(!$pid) { $pid=wp_insert_post(['post_type'=>'rr_robot_player','post_status'=>'private','post_title'=>$name],true); if(is_wp_error($pid)) throw new \RuntimeException($pid->get_error_message()); }
                self::put((int)$pid,$s); return (int)$pid;
            };
            return $id?self::locked((string)$id,$apply):$apply();
        });
    }
    private static function fetch(string $path,array $body=[]): string {
        $key='rrfr_player_source_'.hash('sha256',$path.serialize($body)); $cached=get_transient($key);
        if(is_string($cached)) return $cached;
        $args=['timeout'=>15,'redirection'=>0,'limit_response_size'=>2500000,'headers'=>['Accept'=>'text/html']];
        if($body) {$args['method']='POST';$args['body']=$body;}
        $r=wp_safe_remote_request('https://www.fotball.no'.$path,$args);
        if(is_wp_error($r)||wp_remote_retrieve_response_code($r)!==200) throw new \RuntimeException('Fotball.no kunne ikke hentes. Tidligere data beholdes.');
        $html=wp_remote_retrieve_body($r);
        if(!$html || strlen($html)>=2500000) throw new \RuntimeException('Kilden er tom eller avkortet.');
        set_transient($key,$html,10*MINUTE_IN_SECONDS); return $html;
    }
    public static function collect(array $s): array {
        $id=$s['fiks_id']; $p=PlayerFacts::profile(self::fetch('/fotballdata/person/profil/?fiksId='.$id),$id);
        $p['matches']=$s['snapshot']['matches']??[]; $p['warnings']=[];
        // Only inspect this season. Past seasons remain visible in the profile statistics.
        $year=(int)wp_date('Y'); $candidates=[];
        foreach($p['stats'] as $row) if($row['year']===$year) {
            $html=self::fetch('/PersonPage/GetMatches',['fiksId'=>$id,'teamId'=>$row['team_id'],'statType'=>'any','seasonId'=>$row['season_id'],'isNationalStats'=>'False','showTournament'=>'true','showYear'=>'true']);
            foreach(PlayerFacts::matches($html) as $mid=>$m) {
                $p['matches'][$mid]=$p['matches'][$mid]??($m+['role'=>null,'events'=>[],'source'=>'https://www.fotball.no/fotballdata/kamp/?fiksId='.$mid]);
                $candidates[$mid]=0;
            }
            // Upcoming team matches are candidates, never evidence that this player is selected.
            try {
                $history=Facts::history(self::fetch('/fotballdata/lag/hjem/?fiksId='.$row['team_id']),$row['team_id']);
                foreach($history as $m) if(strtotime($m['kickoff'])>=time()-30*DAY_IN_SECONDS && strtotime($m['kickoff'])<=time()+7*DAY_IN_SECONDS) $candidates[$m['id']]=strtotime($m['kickoff']);
            } catch(\Throwable $e) { $p['warnings'][]='Kommende kamper: '.$e->getMessage(); }
        }
        // Round-robin detail retrieval, max six pages per run. All profile match IDs are retained.
        $checked=$s['snapshot']['detail_checked']??[];
        uksort($candidates,static fn($a,$b)=>(($checked[$a]??0)<=>($checked[$b]??0)) ?: ($b<=>$a));
        foreach(array_slice(array_keys($candidates),0,6) as $mid) {
            try {
                $matchHtml=self::fetch('/fotballdata/kamp/?fiksId='.$mid);
                $detail=PlayerFacts::participation($matchHtml,(int)$mid,$id);
                if($detail) $p['matches'][$mid]=self::newsContext($matchHtml,$detail,$p['matches'][$mid]??[]);
                $checked[$mid]=time();
            } catch(\Throwable $e) { $checked[$mid]=time(); $p['warnings'][]='Kamp '.$mid.': '.$e->getMessage(); }
        }
        $p['detail_checked']=$checked; $p['detail_backlog']=max(0,count($candidates)-6);
        ksort($p['matches']); $p['source']=PlayerFacts::url($id); $p['fetched_at']=gmdate(DATE_ATOM);
        return $p;
    }
    /** Add verified match facts from the same response; never infer finish from score/time. */
    private static function newsContext(string $html,array $detail,array $previous=[],?bool $finished=null): array {
        try {
            $match=Facts::match($html,(int)$detail['id']);
            if($match['kickoff']!==$detail['kickoff'] || $match['source']!==$detail['source'])return $detail;
            $old=$previous['news_context']??[];
            if($finished===null)$finished=($old['id']??0)===$match['id']&&($old['kickoff']??null)===$match['kickoff']&&($old['finished']??false)===true;
            $detail['news_context']=array_intersect_key($match,array_flip(['id','home','away','kickoff','competition','score','source']));
            $detail['news_context']+=['finished'=>$finished,'checked_at'=>gmdate(DATE_ATOM)];
        }catch(\Throwable $e){/* Existing player observations remain useful without article context. */}
        return $detail;
    }
    public static function refresh(int $id): array {
        return self::locked((string)$id,static function() use($id) {
            $s=self::state($id);
            try {
                $p=self::collect($s);
                $previous=$s['comparison']??$s['snapshot'];
                $comparison=$p;
                if($previous) {
                    if($comparison['clubs']===null) $comparison['clubs']=$previous['clubs'];
                    $comparison['stats']=$previous['stats'];
                    foreach($p['stats'] as $key=>$row) {
                        foreach(['appearances','goals','yellow','red'] as $field) if($row[$field]===null) $row[$field]=$previous['stats'][$key][$field]??null;
                        $comparison['stats'][$key]=$row;
                    }
                } else $s['baseline_matches']=array_fill_keys(array_keys($p['matches']),true);
                $changes=PlayerFacts::diff($previous,$comparison); $s['revision']++;
                foreach($changes as $change) if(in_array($change['kind'],$s['watch'],true)) {
                    // Detail enrichment of a baseline match is not a new historical event.
                    if(in_array($change['kind'],['lineup','goals','cards'],true)) {
                        $mid=(int)explode(':',$change['key'])[0];
                        if(isset($s['baseline_matches'][$mid]) && isset($s['snapshot']['matches'][$mid]) && !isset($s['snapshot']['matches'][$mid]['kickoff'])) continue;
                    }
                    $parts=explode(':',$change['key']); $statKey=implode(':',array_slice($parts,0,2));
                    $stat=$p['stats'][$statKey]??($p['stats'][$change['key']]??null);
                    $match=$p['matches'][(int)$parts[0]]??null;
                    $context=$stat?$stat['year'].' · '.$stat['team'].(isset($parts[2])?' · '.(['goals'=>'Mål','yellow'=>'Gule kort','red'=>'Røde kort'][$parts[2]]??''):''):($match['label']??'Registrert klubbtilknytning');
                    $key=hash('sha256',$s['revision'].json_encode($change));
                    $s['events'][$key]=$change+['id'=>$key,'status'=>'new','detected_at'=>$p['fetched_at'],'source'=>$match['source']??$p['source'],'context'=>$context,'player_name'=>$s['name'],'fiks_id'=>$s['fiks_id'],'draft_id'=>null];
                }
                $s['comparison']=$comparison; $s['snapshot']=$p; $s['error']=null; $s['last_checked']=$p['fetched_at']; self::put($id,$s);
                return $s;
            } catch(\Throwable $e) { $s['error']=$e->getMessage(); $s['last_checked']=gmdate(DATE_ATOM); self::put($id,$s); throw $e; }
        });
    }
    public static function tick(): void {
        // Post-match statistics first, otherwise a light six-hour profile rotation.
        $ids=self::ids(); usort($ids,static fn($a,$b)=>((int)(bool)get_option('rrfr_profile_due_'.$b,0)<=>(int)(bool)get_option('rrfr_profile_due_'.$a,0))?:strcmp(self::state((int)$a)['last_checked']??'',self::state((int)$b)['last_checked']??''));
        foreach($ids as $id) {
            $s=self::state((int)$id); $due=get_option('rrfr_profile_due_'.$id,0);
            if(!$s['enabled'] || (!$due && strtotime($s['last_checked']??'')>time()-21600)) continue;
            if($due) delete_transient('rrfr_player_source_'.hash('sha256','/fotballdata/person/profil/?fiksId='.$s['fiks_id'].serialize([])));
            try {self::refresh((int)$id);delete_option('rrfr_profile_due_'.$id);} catch(\Throwable $e) {} break;
        }
    }
    public static function observeMatch(int $match,string $html,array $people,bool $finished): void {
        set_transient('rrfr_player_source_'.hash('sha256','/fotballdata/kamp/?fiksId='.$match.serialize([])),$html,60);
        foreach(self::ids() as $id) {
            $s=self::state((int)$id); if(!$s['enabled'] || !in_array($s['fiks_id'],$people,true) || !$s['snapshot']) continue;
            $detail=PlayerFacts::participation($html,$match,$s['fiks_id']); if(!$detail) continue;
            $detail=self::newsContext($html,$detail,[],$finished);
            self::locked((string)$id,static function() use($id,$match,$detail,$finished) {
                $s=self::state((int)$id); $before=$s['snapshot']; $after=$before; $after['matches'][$match]=$detail;
                foreach(PlayerFacts::diff($before,$after) as $change) if(in_array($change['kind'],$s['watch'],true)) {
                    $key=hash('sha256','match-observation:'.$match.json_encode($change));
                    if(isset($s['events'][$key])) continue;
                    $s['events'][$key]=$change+['id'=>$key,'match_id'=>$match,'status'=>'observing','detected_at'=>gmdate(DATE_ATOM),'source'=>$detail['source'],'context'=>$detail['label'],'player_name'=>$s['name'],'fiks_id'=>$s['fiks_id'],'draft_id'=>null];
                }
                // One completed-match packet, not an article proposal for every minute.
                if($finished) foreach($s['events'] as &$event) if(($event['match_id']??0)===$match && $event['status']==='observing') { $event['status']='new'; $event['detected_at']=gmdate(DATE_ATOM); } unset($event);
                $s['snapshot']['matches'][$match]=$detail;
                if(isset($s['comparison'])) $s['comparison']['matches'][$match]=$detail;
                $s['snapshot']['detail_checked'][$match]=time();
                $s['revision']++; self::put((int)$id,$s);
                if($finished && !get_option('rrfr_match_profile_queued_'.$id.'_'.$match,0)) {
                    update_option('rrfr_profile_due_'.$id,time(),false);
                    update_option('rrfr_match_profile_queued_'.$id.'_'.$match,time(),false);
                }
            });
        }
    }
    public static function eventAction(int $id,string $key,string $action): ?string {
        return self::locked((string)$id,static function() use($id,$key,$action) {
            $s=self::state($id); $e=$s['events'][$key]??null;
            if(!$e) throw new \RuntimeException('Hendelsen finnes ikke.');
            if($action==='ignore') {$s['events'][$key]['status']='ignored';self::put($id,$s);return null;}
            if($action!=='draft') throw new \RuntimeException('Ukjent hendelseshandling.');
            // Also recover a draft created before an interrupted state write.
            $found=get_posts(['post_type'=>'post','post_status'=>['draft','pending','publish','private','future','trash'],'meta_key'=>'_rrfr_player_event','meta_value'=>$id.':'.$key,'numberposts'=>1]);
            if($found) {
                if($found[0]->post_status==='trash') throw new \RuntimeException('Utkastet ligger i papirkurven. Gjenopprett det i WordPress.');
                $pid=$found[0]->ID;
            } else {
                $body='<p>Arbeidsutkast: '.esc_html($e['player_name']).' – '.esc_html(self::KINDS[$e['kind']]).'. Kontroller endringen før publisering.</p>';
                $body.='<p>'.esc_html($e['context']??'').'</p><h2>Dokumentert endring</h2><p>Før:</p><pre>'.esc_html(self::describe($e['before'])).'</pre><p>Etter:</p><pre>'.esc_html(self::describe($e['after'])).'</pre>';
                $body.='<p><a href="'.esc_url($e['source']).'">Kilde: Fotball.no</a> · Hentet '.esc_html($e['detected_at']).'</p>';
                $pid=wp_insert_post(['post_type'=>'post','post_status'=>'draft','post_title'=>$e['player_name'].' – '.self::KINDS[$e['kind']],'post_content'=>$body,'meta_input'=>['_rrfr_player_event'=>$id.':'.$key,'_rrfr_player_fact_snapshot'=>$e]],true);
                if(is_wp_error($pid)) throw new \RuntimeException($pid->get_error_message());
            }
            $s['events'][$key]['draft_id']=(int)$pid; $s['events'][$key]['status']='draft'; self::put($id,$s); return get_edit_post_link($pid,'raw');
        });
    }
    public static function routes(): void {
        register_rest_route('rr-fotballrobot/v1','/players',['methods'=>'GET','permission_callback'=>[Robot::class,'allowed'],'callback'=>static fn()=>Robot::response(static fn()=>array_map(static fn($id)=>['id'=>(int)$id]+self::state((int)$id),self::ids()))]);
        register_rest_route('rr-fotballrobot/v1','/players/(?P<id>[0-9]+)',['methods'=>'GET','permission_callback'=>[Robot::class,'allowed'],'callback'=>static fn($r)=>Robot::response(static fn()=>self::state((int)$r['id']))]);
    }
    public static function action(): void {
        if(!Robot::allowed()) wp_die('Ingen tilgang.',403);
        check_admin_referer('rrfr_player_action'); $id=absint($_POST['id']??0);
        try {
            $op=sanitize_key($_POST['operation']??'');
            if($op==='save') {$id=self::save(wp_unslash($_POST));$message='Spillerprofilen er lagret.';}
            elseif($op==='refresh') {self::refresh($id);$message='Spillerdata er kontrollert.';}
            elseif(in_array($op,['ignore','draft'],true)) { $url=self::eventAction($id,sanitize_key($_POST['event']??''),$op); if($url){wp_safe_redirect($url);exit;} $message='Hendelsen er ignorert.'; }
            else throw new \RuntimeException('Ukjent handling.');
        } catch(\Throwable $e) {$message=$e->getMessage();}
        set_transient('rrfr_players_notice_'.get_current_user_id(),$message,120);
        wp_safe_redirect(admin_url('admin.php?page=rr-fotballrobot-players&player='.$id)); exit;
    }
    private static function fields(string $op,int $id=0,string $event=''): void {
        wp_nonce_field('rrfr_player_action');
        foreach(['action'=>'rrfr_player_action','operation'=>$op,'id'=>$id,'event'=>$event] as $k=>$v) echo '<input type="hidden" name="'.esc_attr($k).'" value="'.esc_attr($v).'">';
    }
    public static function describe($value): string {
        if($value===null) return 'Ikke tilgjengelig';
        $labels=['year'=>'Sesong','team'=>'Lag','appearances'=>'Kamper','goals'=>'Mål','yellow'=>'Gule kort','red'=>'Røde kort','name'=>'Navn','label'=>'Kamp','kickoff'=>'Kampstart','role'=>'Tropp','minute'=>'Minutt','type'=>'Hendelse','events'=>'Kamphendelser'];
        $roles=['starter'=>'Startellever','bench'=>'Innbytter','withdrawn'=>'Strøket'];
        if(!is_array($value)) return $roles[(string)$value]??(string)$value;
        $lines=[];
        foreach($value as $key=>$v) {
            if(in_array($key,['id','team_id','season_id','source','order','side'],true)) continue;
            $lines[]=(isset($labels[$key])?$labels[$key].': ':'').self::describe($v);
        }
        return implode("\n",$lines) ?: 'Ingen registrerte opplysninger';
    }
    public static function page(): void { require __DIR__.'/players-page.php'; }
}
