<?php
namespace RadioRubben\Fotballrobot;

/** Research proposes identities. Only a nonce-protected editor decision starts following. */
final class PlayerCandidates {
    const OPTION='rrfr_player_candidates';
    const CLUBS=['Bremnes','Moster','Finnås','Bømlo','Rubbestadneset'];
    public static function all(): array {return get_option(self::OPTION,[]);}
    public static function url(): string {return admin_url('admin.php?page=rrfr-player-candidates');}
    public static function followed(int $fiks): int {
        foreach(Players::ids() as $id)if(Players::state((int)$id)['fiks_id']===$fiks)return (int)$id;
        return 0;
    }
    private static function locked(callable $fn) {
        $key='rrfr_candidate_lock';
        if(!add_option($key,time(),'','no'))throw new \RuntimeException('Kandidatlisten behandles allerede. Last siden på nytt før neste forsøk.');
        try{return $fn();}finally{delete_option($key);}
    }
    private static function put(array $all): void {
        if(!update_option(self::OPTION,$all,false)&&get_option(self::OPTION)!==$all)throw new \RuntimeException('Kandidatlisten kunne ikke lagres. Les tilbake før nytt forsøk.');
    }
    private static function text($value,int $limit): string {
        if(!is_string($value)||trim($value)===''||mb_strlen($value)>$limit||$value!==strip_tags($value))throw new \RuntimeException('Oppgi kort, ren tekst i alle dokumentasjonsfeltene.');
        return trim($value);
    }
    public static function localClub(string $club): bool {
        foreach(self::CLUBS as $name)if(preg_match('/(?:^|\s)'.preg_quote($name,'/').'(?:$|\s)/iu',$club))return true;
        return false;
    }
    public static function ingest(array $input): array {
        if(!Robot::allowed())throw new \RuntimeException('Ingen tilgang.');
        $fiks=PlayerFacts::id((string)($input['fiks_id']??''));
        return self::locked(static function()use($input,$fiks){
            $all=self::all();
            // A repeated discovery never resets rejection, postponement or a human decision.
            if(isset($all[$fiks]))return ['created'=>false,'candidate'=>$all[$fiks],'review_url'=>self::url()];
            if($pid=self::followed($fiks))return ['created'=>false,'already_followed'=>true,'player_id'=>$pid,'fiks_id'=>$fiks];
            if(count($all)>=2000)throw new \RuntimeException('Kandidatlisten må arkiveres før flere forslag lagres.');
            $name=self::text($input['name']??null,120);
            $former=self::text($input['former_club']??null,80);
            $current=self::text($input['current_club']??null,100);
            if(!self::localClub($former)||self::localClub($current))throw new \RuntimeException('Forslaget må ha dokumentert tidligere Bømlo-klubb og spille utenfor Bømlo nå.');
            $year=(int)wp_date('Y');$seasons=$input['former_seasons']??[];
            if(!is_array($seasons)||!array_is_list($seasons)||!$seasons||count($seasons)>21)throw new \RuntimeException('Oppgi dokumenterte sesonger i Bømlo-klubben.');
            foreach($seasons as $season)if(!is_int($season)||$season<$year-20||$season>$year)throw new \RuntimeException('Sesongene må ligge innenfor søkevinduet på 20 år.');
            $active=$input['active_season']??null;
            if(!is_int($active)||$active<$year-1||$active>$year)throw new \RuntimeException('Aktivitet må dokumenteres i inneværende eller foregående sesong.');
            $checked=PlayerMonitor::checked($input['checked_at']??null);
            $sources=$input['sources']??[];$proof=[];$kinds=[];
            if(!is_array($sources)||!array_is_list($sources)||count($sources)<3||count($sources)>6)throw new \RuntimeException('Dokumenter lokal historikk, nåværende klubb og aktivitet med kildelenker.');
            foreach($sources as $s){
                if(!is_array($s)||($s['public_read']??false)!==true||!in_array($s['kind']??'', ['history','current','activity'],true))throw new \RuntimeException('Hver kilde må være lest og identiteten kontrollert.');
                $proof[]=['kind'=>$s['kind'],'url'=>PlayerMonitor::url((string)($s['url']??'')),'fact'=>self::text($s['fact']??null,500)];$kinds[$s['kind']]=true;
            }
            if(count($kinds)!==3)throw new \RuntimeException('Historikk, nåværende klubb og aktivitet må alle dokumenteres.');
            $all[$fiks]=['fiks_id'=>$fiks,'name'=>$name,'former_club'=>$former,'former_seasons'=>array_values(array_unique($seasons)),
                'current_club'=>$current,'current_level'=>self::text($input['current_level']??null,120),'active_season'=>$active,
                'history_note'=>self::text($input['history_note']??null,700),'sources'=>$proof,'checked_at'=>$checked,
                'received_at'=>gmdate(DATE_ATOM),'added_by'=>get_current_user_id(),'status'=>'pending','version'=>1,'player_id'=>null,'decisions'=>[]];
            self::put($all);
            return ['created'=>true,'candidate'=>$all[$fiks],'review_url'=>self::url(),'following'=>'manual_approval_required'];
        });
    }
    public static function decide(int $fiks,int $version,string $operation): array {
        if(!Robot::allowed())throw new \RuntimeException('Ingen tilgang.');
        return self::locked(static function()use($fiks,$version,$operation){
            $all=self::all();$s=$all[$fiks]??null;
            if(!$s||$version!==$s['version'])throw new \RuntimeException('Forslaget er endret. Last siden på nytt.');
            if(!in_array($operation,['approve','reject','later','reopen'],true))throw new \RuntimeException('Ukjent handling.');
            if($s['status']==='approved')throw new \RuntimeException('Spilleren er allerede godkjent. Endre oppfølgingen under Spillere jeg følger.');
            if($operation==='approve'){
                if(!in_array($s['status'],['pending','later'],true))throw new \RuntimeException('Åpne det avviste forslaget igjen før godkjenning.');
                $pid=self::followed($fiks);
                if(!$pid){
                    // Stored historic seasons create a baseline, never transfer news.
                    $pid=Players::save(['name'=>$s['name'],'fiks'=>(string)$fiks,'note'=>$s['history_note'],'club_note'=>$s['current_club'],'group'=>'bomlo-away','enabled'=>true,'watch'=>array_keys(Players::KINDS)]);
                }
                $s['player_id']=$pid;
            }
            $s['status']=['approve'=>'approved','reject'=>'rejected','later'=>'later','reopen'=>'pending'][$operation];
            $s['version']++;$s['decisions'][]=['operation'=>$operation,'by'=>get_current_user_id(),'at'=>gmdate(DATE_ATOM)];
            $all[$fiks]=$s;self::put($all);return $s;
        });
    }
    public static function status(): array {
        return ['version'=>1,'history_years'=>20,'from_season'=>(int)wp_date('Y')-20,'to_season'=>(int)wp_date('Y'),
            'coverage'=>'partial','coverage_note'=>'Enkeltfunn er kontrollert. Hele 20-årsperioden er ikke ferdig kartlagt.',
            'local_clubs'=>self::CLUBS,'following'=>'manual_approval_required','review_url'=>self::url(),'candidates'=>array_values(self::all())];
    }
    public static function routes(): void {
        register_rest_route('rr-fotballrobot/v1','/player-candidates',[
            ['methods'=>'GET','permission_callback'=>[Robot::class,'allowed'],'callback'=>static fn()=>Robot::response(static fn()=>self::status())],
            ['methods'=>'POST','permission_callback'=>[Robot::class,'allowed'],'callback'=>static fn($r)=>Robot::response(static fn()=>self::ingest((array)$r->get_json_params()))]
        ]);
    }
    public static function action(): void {
        if(!Robot::allowed())wp_die('Ingen tilgang.',403);
        check_admin_referer('rrfr_candidate_action');
        try{self::decide(absint($_POST['fiks_id']??0),absint($_POST['version']??0),sanitize_key($_POST['operation']??''));$message='Valget er lagret.';}
        catch(\Throwable $e){$message=$e->getMessage();}
        set_transient('rrfr_candidates_notice_'.get_current_user_id(),$message,120);
        wp_safe_redirect(self::url());exit;
    }
    public static function menu(): void {add_submenu_page('rr-fotballrobot','Spillere ute – kandidater','Spillere ute – kandidater','manage_options','rrfr-player-candidates',[self::class,'page']);}
    public static function page(): void {require __DIR__.'/player-candidates-page.php';}
}
add_action('admin_menu',[PlayerCandidates::class,'menu']);
add_action('rest_api_init',[PlayerCandidates::class,'routes']);
add_action('admin_post_rrfr_candidate_action',[PlayerCandidates::class,'action']);
