<?php
namespace RadioRubben\Fotballrobot;

/** Administration of the VPS collector. This class never runs research or a writer. */
final class SourceCollection {
    const VERSION='1.0.0';
    const CAP='rrfr_collect_sources';
    const OPTION='rrfr_source_collection_contact';
    public static function allowed(): bool {
        return Robot::allowed() || current_user_can(self::CAP);
    }
    public static function url(): string {return admin_url('admin.php?page=rrfr-source-collection');}
    public static function menu(): void {
        add_submenu_page('rr-fotballrobot','Spilleroppfølging – kildeinnsamling','Kildeinnsamling på VPS','manage_options','rrfr-source-collection',[self::class,'page']);
    }
    public static function config(): array {
        $url=defined('RRFR_COLLECTION_URL')?RRFR_COLLECTION_URL:'';
        $secret=defined('RRFR_COLLECTION_CONTROL_SECRET')?RRFR_COLLECTION_CONTROL_SECRET:'';
        if(!is_string($url)||!preg_match('~^https://[^/?#@]+/rrfr-source-collection/v1$~D',$url)||!wp_http_validate_url($url)||!is_string($secret)||strlen($secret)<32)throw new \RuntimeException('VPS-tilkobling mangler gyldig serverkonfigurasjon.');
        return [$url,$secret];
    }
    public static function remote(string $op,string $requestId=''): array {
        [$base,$secret]=self::config();
        $method=$op==='status'?'GET':'POST';$path='/rrfr-source-collection/v1/'.($op==='status'?'status':'control');
        $body=$method==='GET'?'':wp_json_encode(['operation'=>$op,'request_id'=>$requestId]);
        $at=(string)time();$nonce=wp_generate_uuid4();
        $signature=hash_hmac('sha256',$method."\n".$path."\n".$at."\n".$nonce."\n".hash('sha256',$body),$secret);
        $response=wp_safe_remote_request($base.'/'.($op==='status'?'status':'control'),[
            'method'=>$method,'timeout'=>3,'redirection'=>0,'limit_response_size'=>100000,
            'headers'=>['Content-Type'=>'application/json','X-RR-Time'=>$at,'X-RR-Nonce'=>$nonce,'X-RR-Signature'=>$signature],
            'body'=>$body,
        ]);
        if(is_wp_error($response))throw new \RuntimeException('VPS svarer ikke. En start kan være mottatt; kontroller status før nytt forsøk.');
        $code=wp_remote_retrieve_response_code($response);
        $data=json_decode(wp_remote_retrieve_body($response),true);
        if($code!==200||!is_array($data)||($data['engine']??'')!=='rrfr-source-collection'||($data['version']??'')!==self::VERSION)throw new \RuntimeException('VPS-tilkobling avvist eller feil tjenesteversjon (HTTP '.(int)$code.').');
        // Store only a bounded status document, never credentials or raw responses.
        $clean=self::clean($data);$clean['contact_at']=gmdate(DATE_ATOM);
        update_option(self::OPTION,$clean,false);
        return $clean;
    }
    public static function clean(array $input): array {
        $out=['engine'=>'rrfr-source-collection','version'=>self::VERSION];
        foreach(['state','last_start','last_success','next_run','worker_seen','run_id','request_id','request_state','mode'] as $k)$out[$k]=sanitize_text_field(substr((string)($input[$k]??''),0,100));
        $out['counts']=[];foreach(['sources','candidates','duplicates','omitted','errors'] as $k)$out['counts'][$k]=max(0,(int)($input['counts'][$k]??0));
        $out['events']=[];
        foreach(array_slice(is_array($input['events']??null)?$input['events']:[],0,50) as $event){
            if(!is_array($event))continue;
            $out['events'][]=['at'=>sanitize_text_field(substr((string)($event['at']??''),0,40)),
                'kind'=>sanitize_key($event['kind']??''),'player_id'=>absint($event['player_id']??0),
                'message'=>sanitize_text_field(substr((string)($event['message']??''),0,400))];
        }
        return $out;
    }
    public static function status(): array {
        try{return self::remote('status')+['reachable'=>true];}
        catch(\Throwable $e){return ['state'=>'unavailable','reachable'=>false,'error'=>$e->getMessage(),'last_contact'=>get_option(self::OPTION,[])];}
    }
    public static function history(int $id,int $page): array {
        $player=Players::state($id);
        // All post statuses are included. Do not retry or mutate rejected/failed drafts.
        // Scan the bounded whole archive, since short names/aliases can miss a name search.
        $posts=get_posts(['post_type'=>'post','post_status'=>['draft','pending','publish','private','future','trash'],
            'posts_per_page'=>100,'offset'=>max(0,$page-1)*100,'orderby'=>'ID','order'=>'ASC','suppress_filters'=>false]);
        $out=[];
        $terms=array_filter(preg_split('/[\s-]+/u',$player['name']),static fn($t)=>mb_strlen($t)>=4);
        foreach($posts as $p){
            $content=wp_strip_all_tags($p->post_title.' '.$p->post_content);
            $matches=0;foreach($terms as $term)if(mb_stripos($content,$term)!==false)$matches++;
            if(!$matches)continue;
            $out[]=['id'=>(int)$p->ID,'status'=>$p->post_status,'title'=>$p->post_title,
            'content'=>wp_strip_all_tags($p->post_content),'date_gmt'=>$p->post_date_gmt];
        }
        return ['player_id'=>$id,'fiks_id'=>$player['fiks_id'],'posts'=>$out,'next_page'=>count($posts)===100?$page+1:null];
    }
    public static function routes(): void {
        register_rest_route('rr-fotballrobot/v1','/source-collection',['methods'=>'GET','permission_callback'=>[self::class,'allowed'],
            'callback'=>static fn()=>Robot::response(static fn()=>['version'=>self::VERSION,'contact'=>get_option(self::OPTION,[]),'admin_url'=>self::url()])]);
        register_rest_route('rr-fotballrobot/v1','/players/(?P<id>[0-9]+)/source-history',['methods'=>'GET','permission_callback'=>[self::class,'allowed'],
            'callback'=>static fn($r)=>Robot::response(static fn()=>self::history((int)$r['id'],max(1,(int)$r->get_param('page'))))]);
    }
    public static function action(): void {
        if(!Robot::allowed())wp_die('Ingen tilgang.',403);
        check_admin_referer('rrfr_source_collection');
        $op=sanitize_key($_POST['operation']??'');$request=sanitize_text_field($_POST['request_id']??'');
        try {
            if(!in_array($op,['start','pause','resume'],true)||!preg_match('/^[a-f0-9-]{36}$/D',$request))throw new \RuntimeException('Ugyldig handling.');
            $s=self::remote($op,$request);
            $message=$op==='start'?'VPS har bekreftet forespørselen: '.($s['request_state']??'ukjent').'. Ingen artikkel er publisert.':($op==='pause'?'Kildeinnsamlingen er pauset.':'Kildeinnsamlingen er gjenopptatt.');
            if($op==='start'&&$s['state']==='paused')$message='Kildeinnsamlingen er pauset. Startforespørselen er ikke kølagt.';
        } catch(\Throwable $e){$message=$e->getMessage();}
        set_transient('rrfr_collection_notice_'.get_current_user_id(),$message,120);
        wp_safe_redirect(self::url());exit;
    }
    private static function date(string $date): string {
        $t=strtotime($date);return $t?wp_date('d.m.Y H:i:s',$t,new \DateTimeZone('Europe/Oslo')):'Ikke bekreftet';
    }
    public static function page(): void {
        if(!Robot::allowed())wp_die('Ingen tilgang.',403);
        $live=self::status();$s=$live['reachable']?$live:($live['last_contact']??[]);
        echo '<div class="wrap"><h1>Spilleroppfølging – kildeinnsamling</h1>';
        $notice=get_transient('rrfr_collection_notice_'.get_current_user_id());
        if($notice)echo '<div class="notice notice-info"><p>'.esc_html($notice).'</p></div>';
        if(!$live['reachable'])echo '<div class="notice notice-error"><p>'.esc_html($live['error']).' Tidligere opplysninger beholdes.</p></div>';
        $states=['active'=>'Aktiv','running'=>'Kjører','paused'=>'Pauset','unavailable'=>'Utilgjengelig','failed'=>'Feil – se logg','partial'=>'Delvis kontrollert'];
        echo '<p><strong>'.esc_html($states[$live['state']]??$live['state']).'</strong> · Modus: '.esc_html(($s['mode']??'')==='deliver'?'Innlegging i kildeinnboks':'Test uten innlegging').'</p>';
        echo '<table class="widefat striped"><tbody>';
        foreach(['contact_at'=>'Siste kontakt med VPS','worker_seen'=>'Siste livstegn fra arbeidsprosessen','last_start'=>'Siste start','last_success'=>'Siste vellykkede kjøring','next_run'=>'Neste planlagte kjøring'] as $k=>$label)echo '<tr><th>'.esc_html($label).'</th><td>'.esc_html(self::date((string)($s[$k]??''))).'</td></tr>';
        echo '</tbody></table><p>Alle tider er norsk tid (Europe/Oslo). Kilde lagt i kø, utkast opprettet og publisert er separate tilstander. Thomas sluttgodkjenner teksten i WordPress.</p>';
        foreach(['start'=>'Start én innsamlingsrunde','pause'=>'Pause kildeinnsamlingen','resume'=>'Gjenoppta kildeinnsamlingen'] as $op=>$label){
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin:0 12px 12px 0">';
            wp_nonce_field('rrfr_source_collection');
            echo '<input type="hidden" name="action" value="rrfr_source_collection"><input type="hidden" name="operation" value="'.esc_attr($op).'"><input type="hidden" name="request_id" value="'.esc_attr(wp_generate_uuid4()).'"><button class="button">'.esc_html($label).'</button></form>';
        }
        echo '<h2>Aktive spillerprofiler</h2><table class="widefat striped"><thead><tr><th>Spiller</th><th>WP-ID / FIKS-ID</th><th>Siste registrerte klubbtilknytning</th><th>Kontrollgrunnlag</th></tr></thead><tbody>';
        foreach(Players::ids() as $id){$p=Players::state((int)$id);if(!$p['enabled'])continue;
            $clubs=array_column($p['snapshot']['clubs']??[],'name');
            echo '<tr><td>'.esc_html($p['name']).'</td><td>'.(int)$id.' / '.(int)$p['fiks_id'].'</td><td>'.esc_html($clubs?implode(', ',$clubs):'Ikke bekreftet').'</td><td>'.esc_html(self::date((string)($p['snapshot']['fetched_at']??''))).($p['error']?' – kildefeil':'').'</td></tr>';
        }
        echo '</tbody></table><h2>Siste kjøring og logg</h2><p>';
        foreach(['sources'=>'Kilder bekreftet lagret','candidates'=>'Kandidater bekreftet lagret','duplicates'=>'Duplikater','omitted'=>'Utelatte treff','errors'=>'Feil'] as $k=>$label)echo esc_html($label).': '.(int)($s['counts'][$k]??0).' · ';
        echo '</p><table class="widefat striped"><thead><tr><th>Tid</th><th>Type</th><th>Spiller-ID</th><th>Begrunnelse</th></tr></thead><tbody>';
        foreach($s['events']??[] as $e)echo '<tr><td>'.esc_html(self::date($e['at'])).'</td><td>'.esc_html($e['kind']).'</td><td>'.(int)$e['player_id'].'</td><td>'.esc_html($e['message']).'</td></tr>';
        echo '</tbody></table><p>De siste 50 logghendelsene vises. Full kjøringslogg og sjekkpunkter oppbevares på VPS.</p>';
        $pending=count(array_filter(PlayerCandidates::all(),static fn($c)=>$c['status']==='pending'));
        echo '<p><a href="'.esc_url(PlayerCandidates::url()).'">Kandidater til manuell godkjenning ('.$pending.')</a> · <a href="'.esc_url(admin_url('admin.php?page=rr-fotballrobot-players')).'">Kildeinnboks og spillerprofiler</a> · <a href="'.esc_url(PlayerReview::url()).'">Artikler til godkjenning</a></p></div>';
    }
}
add_action('admin_menu',[SourceCollection::class,'menu']);
add_action('rest_api_init',[SourceCollection::class,'routes']);
add_action('admin_post_rrfr_source_collection',[SourceCollection::class,'action']);
