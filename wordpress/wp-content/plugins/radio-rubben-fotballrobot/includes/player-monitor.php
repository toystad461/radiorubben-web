<?php
namespace RadioRubben\Fotballrobot;
require_once __DIR__.'/media-sources.php';

/** Private source inbox. Research collects public facts; this plugin owns drafting and approval. */
final class PlayerMonitor {
    public static function sources(int $id): array {
        Players::state($id);
        return get_option('rrfr_player_sources_'.$id,[]);
    }
    public static function key(int $id,string $sourceId): string {return 'news:'.$id.':'.$sourceId;}
    public static function review(string $key): ?array {
        $posts=get_posts(['post_type'=>'post','post_status'=>['draft','pending','publish','private','future','trash'],'meta_key'=>'_rrfr_review_key','meta_value'=>$key,'numberposts'=>1]);
        if(!$posts)return null;
        $p=$posts[0];$s=get_post_meta($p->ID,PlayerReview::META,true);
        return ['id'=>(int)$p->ID,'title'=>$p->post_title,'post_status'=>$p->post_status,'status'=>$s['status']??'unknown','mail'=>$s['mail']??'none','error'=>$s['error']??null,'review_url'=>PlayerReview::url((int)$p->ID)];
    }
    private static function text($value,int $max): string {
        if(!is_string($value)||trim($value)===''||mb_strlen($value)>$max||$value!==strip_tags($value))throw new \RuntimeException('Kildetekst må være kort ren tekst, uten HTML.');
        return trim($value);
    }
    public static function url(string $url): string {
        $p=parse_url($url);
        if(strlen($url)>2048||!filter_var($url,FILTER_VALIDATE_URL)||!$p||($p['scheme']??'')!=='https'||isset($p['user'])||isset($p['pass'])||isset($p['port'])||!str_contains($p['host']??'','.')||rtrim($p['path']??'','/')===''||filter_var($p['host'],FILTER_VALIDATE_IP)||preg_match('/\.(?:local|localhost|internal|test)$/i',$p['host']))throw new \RuntimeException('Bruk en direkte offentlig HTTPS-kildelenke.');
        $query=[];parse_str($p['query']??'',$query);
        foreach(array_keys($query) as $k)if(preg_match('/^(utm_|fbclid$|gclid$)/i',$k))unset($query[$k]);
        ksort($query);
        return 'https://'.strtolower($p['host']).(rtrim($p['path']??'','/')?:'/').($query?'?'.http_build_query($query,'','&',PHP_QUERY_RFC3986):'');
    }
    private static function timestamp($value): string {
        if(!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/',$value)||($time=strtotime($value))===false||$time>time()+300)throw new \RuntimeException('Oppgi en gyldig dato med tidssone.');
        try{$date=new \DateTimeImmutable($value);$errors=\DateTimeImmutable::getLastErrors();if($errors&&($errors['warning_count']||$errors['error_count']))throw new \RuntimeException('Ugyldig dato.');}catch(\Throwable $e){throw new \RuntimeException('Oppgi en gyldig dato med tidssone.');}
        return gmdate(DATE_ATOM,$time);
    }
    public static function checked($value): string {
        $date=self::timestamp($value);
        if(strtotime($date)<time()-172800)throw new \RuntimeException('Les kilden på nytt før innlegging; kontrollen er eldre enn to døgn.');
        return $date;
    }
    public static function ingest(int $id,array $input): array {
        if(!Robot::allowed())throw new \RuntimeException('Ingen tilgang.');
        $player=Players::state($id);
        if(!$player['enabled']||(int)($input['fiks_id']??0)!==$player['fiks_id'])throw new \RuntimeException('Spilleren er pauset eller FIKS-ID stemmer ikke.');
        if(($input['public_read']??false)!==true)throw new \RuntimeException('Kilden må være lest og kontrollert offentlig. Søketreff alene er ikke nok.');
        $url=self::url((string)($input['url']??''));$key=hash('sha256',$url);
        // Accept retries without replacing source facts or human edits, including trashed drafts.
        $existing=self::sources($id);
        if(isset($existing[$key]))return self::receipt($id,$existing[$key],false);
        $title=self::text($input['title']??null,200);
        $identity=self::text($input['identity_note']??null,400);
        $published=$input['published_at']??null;
        if(is_string($published)&&preg_match('/^\d{4}-\d{2}-\d{2}$/',$published)){if(!strtotime($published)||gmdate('Y-m-d',strtotime($published))!==$published||$published>gmdate('Y-m-d'))throw new \RuntimeException('Ugyldig publiseringsdato.');}
        else $published=self::timestamp($published);
        $checked=self::checked($input['checked_at']??null);
        $facts=MediaSources::facts($input['facts']??null);
        $media=MediaSources::validate($input,$url);
        $eventDate=$input['event_date']??null;
        if($eventDate!==null&&(!is_string($eventDate)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$eventDate)||!strtotime($eventDate)||gmdate('Y-m-d',strtotime($eventDate))!==$eventDate))throw new \RuntimeException('Hendelsesdato må være ÅÅÅÅ-MM-DD, eller null når ukjent.');
        $lock='rrfr_source_lock_'.$id;
        if(!add_option($lock,time(),'','no'))throw new \RuntimeException('Kildeinnboksen behandles allerede. Les tilbake før nytt forsøk.');
        try {
            $all=self::sources($id);
            if(isset($all[$key]))return self::receipt($id,$all[$key],false);
            if(count($all)>=2000)throw new \RuntimeException('Kildeinnboksen må arkiveres manuelt før flere treff lagres.');
            $source=['id'=>$key,'url'=>$url,'title'=>$title,'published_at'=>$published,'checked_at'=>$checked,'event_date'=>$eventDate,'identity_note'=>$identity,'facts'=>$facts,'received_at'=>gmdate(DATE_ATOM),'added_by'=>get_current_user_id()]+$media;
            $all[$key]=$source;
            if(!update_option('rrfr_player_sources_'.$id,$all,false)&&get_option('rrfr_player_sources_'.$id)!==$all)throw new \RuntimeException('Kilden kunne ikke lagres. Les tilbake før nytt forsøk.');
            return self::receipt($id,$source,true);
        }finally{delete_option($lock);}
    }
    private static function receipt(int $id,array $source,bool $created): array {
        return ['created'=>$created,'source'=>$source,'review'=>self::review(self::key($id,$source['id'])),'queued'=>(bool)get_option('rrfr_player_review_enabled_at',0),'publication'=>'manual_approval_required'];
    }
    public static function candidates(): array {
        $out=[];
        foreach(Players::ids() as $id){$s=Players::state((int)$id);if(!$s['enabled'])continue;
            foreach(self::sources((int)$id) as $n){$key=self::key((int)$id,$n['id']);if(self::review($key))continue;
                $out[]=['key'=>$key,'at'=>$n['received_at'],'facts'=>['type'=>'public_news','player_id'=>(int)$id,'fiks_id'=>$s['fiks_id'],'name'=>$s['name'],'news'=>$n,'source'=>$n['url'],'fetched_at'=>$n['checked_at']]];
            }
        }
        return $out;
    }
    public static function status(): array {
        $players=[];
        foreach(Players::ids() as $id){$s=Players::state((int)$id);$news=[];$reviews=[];
            foreach(array_reverse(self::sources((int)$id)) as $n){$review=self::review(self::key((int)$id,$n['id']));$news[]=$n+['review'=>$review];}
            $seen=[];
            foreach($s['events'] as $e){
                $keys=['events:'.$id.':'.$e['detected_at']];
                if(preg_match('/^([1-9][0-9]*):[1-9][0-9]*$/D',(string)($e['key']??''),$match))$keys[]='match-news:'.$s['fiks_id'].':'.$match[1];
                foreach($keys as $key){if(isset($seen[$key]))continue;$seen[$key]=true;$review=self::review($key);if($review)$reviews[$key]=$review;}
            }
            $players[]=['id'=>(int)$id,'fiks_id'=>$s['fiks_id'],'name'=>$s['name'],'enabled'=>$s['enabled'],'last_checked'=>$s['last_checked'],'error'=>$s['error'],'warnings'=>$s['snapshot']['warnings']??[],'news'=>array_slice($news,0,100),'news_total'=>count($news),'reviews'=>array_values($reviews),'news_filter'=>PlayerReview::newsSelection((int)$id,$s,(int)get_option('rrfr_player_review_enabled_at',PHP_INT_MAX))];
        }
        $next=wp_next_scheduled('rrfr_players_tick');
        return ['version'=>'0.9.9','engine'=>'radio-rubben-fotballrobot','features'=>['candidate_approval','verified_video','supporting_sources','player_news_filter'],'automatic_proposals'=>(bool)get_option('rrfr_player_review_enabled_at',0),'publication'=>'manual_approval_required','next_check'=>$next?gmdate(DATE_ATOM,$next):null,'queue_error'=>get_option('rrfr_review_queue_error',null),'players'=>$players];
    }
    public static function routes(): void {
        register_rest_route('rr-fotballrobot/v1','/player-monitor',['methods'=>'GET','permission_callback'=>[Robot::class,'allowed'],'callback'=>static fn()=>Robot::response(static fn()=>self::status())]);
        register_rest_route('rr-fotballrobot/v1','/players/(?P<id>[0-9]+)/sources',[
            ['methods'=>'GET','permission_callback'=>[Robot::class,'allowed'],'callback'=>static fn($r)=>Robot::response(static fn()=>array_values(self::sources((int)$r['id'])))],
            ['methods'=>'POST','permission_callback'=>[Robot::class,'allowed'],'callback'=>static fn($r)=>Robot::response(static fn()=>self::ingest((int)$r['id'],(array)$r->get_json_params()))]
        ]);
    }
    public static function panel(): void {
        $s=self::status();
        echo '<section class="rrfr-card"><h2>Samlet spillerovervåkning</h2><p>NFF-data og kontrollerte avis- og klubbnyheter går til den nye Fotballroboten. Nyhetssøket leverer kilder hit; roboten skriver, faktakontrollerer og sender forslag til din godkjenning.</p><p>Automatiske artikkelforslag: <strong>'.($s['automatic_proposals']?'På':'Av').'</strong>. Publisering krever din sluttgodkjenning. <a href="'.esc_url(PlayerReview::url()).'">Åpne artikler til godkjenning</a>.</p>';
        $filtered=[];foreach($s['players'] as $player)foreach($player['news_filter']['excluded_counts'] as $reason=>$number)$filtered[$reason]=($filtered[$reason]??0)+$number;
        echo '<p><strong>Nyhetsfilter aktivt:</strong> Vanlige bytter og tekniske rettelser beholdes i historikken. Mål og utvisninger kan gi ett forslag per spiller og kamp når kampdato, sluttstatus, lag, turnering og resultat er bekreftet. Kamper eldre enn 48 timer gir ikke automatiske forslag.</p>';
        if($filtered){echo '<details><summary>Hvorfor noen registreringer ikke blir artikkelforslag</summary><ul>';foreach($filtered as $reason=>$number)echo '<li>'.esc_html(PlayerNewsFilter::REASONS[$reason]??$reason).' ('.(int)$number.')</li>';echo '</ul></details>';}
        if(!$s['next_check'])echo '<p class="rrfr-notice">Spillerkontrollen mangler en planlagt kjøring.</p>';
        if($s['queue_error'])echo '<p class="rrfr-notice">'.esc_html($s['queue_error']).'</p>';
        $count=0;
        foreach($s['players'] as $p)foreach(array_slice($p['news'],0,10) as $n){$count++;echo '<article class="rrfr-player"><h3>'.esc_html($p['name'].' · '.$n['title']).'</h3><p><a href="'.esc_url($n['url']).'">'.esc_html(parse_url($n['url'],PHP_URL_HOST)).'</a> · Publisert '.esc_html($n['published_at']).'</p><p>'.esc_html(implode(' ',$n['facts'])).'</p><p>'.($n['review']?'<a href="'.esc_url($n['review']['review_url']).'">Se artikkelforslag</a> · '.esc_html($n['review']['status']):($s['automatic_proposals']?'Venter på robotens skrivekø.':'Lagret kilde. Automatiske forslag er slått av.')).'</p></article>';}
        if(!$count)echo '<p>Ingen avis- eller klubbnyheter i innboksen ennå. NFF-hendelser og eksisterende forslag beholdes.</p>';
        echo '</section>';
    }
}
add_action('rest_api_init',[PlayerMonitor::class,'routes']);
