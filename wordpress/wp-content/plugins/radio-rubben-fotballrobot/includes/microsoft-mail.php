<?php
namespace RadioRubben\Fotballrobot;

/** Dedicated Graph transport: never intercepts other WordPress mail. */
final class MicrosoftMail {
    const OPTION='rrfr_microsoft_mail';
    const SCOPE='https://graph.microsoft.com/Mail.Send.Shared offline_access';
    public static function url(): string {return admin_url('admin.php?page=rrfr-microsoft-mail');}
    public static function callback(): string {return admin_url('admin-post.php?action=rrfr_microsoft_callback');}
    public static function menu(): void {add_submenu_page('rr-fotballrobot','Microsoft 365 e-post','Microsoft 365 e-post','manage_options','rrfr-microsoft-mail',[self::class,'page']);}
    public static function config(): array { $v=get_option(self::OPTION,[]);return is_array($v)?$v:[]; }
    public static function seal(array $v): string {
        $iv=random_bytes(12);$tag='';
        $cipher=openssl_encrypt(wp_json_encode($v),'aes-256-gcm',hash('sha256',wp_salt('auth').'rrfr-microsoft-mail',true),OPENSSL_RAW_DATA,$iv,$tag);
        if($cipher===false)throw new \RuntimeException('Kryptering feilet.');
        return base64_encode($iv.$tag.$cipher);
    }
    public static function unseal(string $v): array {
        $raw=base64_decode($v,true);if($raw===false||strlen($raw)<29)throw new \RuntimeException('Tilkoblingen kan ikke dekrypteres. Lagre oppsettet og koble til på nytt.');
        $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',hash('sha256',wp_salt('auth').'rrfr-microsoft-mail',true),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));
        $a=$plain===false?null:json_decode($plain,true);
        if(!is_array($a))throw new \RuntimeException('Tilkoblingen kan ikke dekrypteres. Lagre oppsettet og koble til på nytt.');return $a;
    }
    private static function save(array $c): void {
        update_option(self::OPTION,$c,false);
        if(self::config()!==$c)throw new \RuntimeException('Kunne ikke lagre e-postoppsettet.');
    }
    public static function locked(callable $fn) {
        if(!add_option('rrfr_microsoft_lock',time(),'','no'))throw new \RuntimeException('E-posttilkoblingen er opptatt. Ved avbrudd må administrator kontrollere låsen.');
        try{return $fn();}finally{delete_option('rrfr_microsoft_lock');}
    }
    public static function configure(string $tenant,string $client,string $secret): void {
        foreach([$tenant,$client] as $id)if(!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',$id))throw new \RuntimeException('Leietaker-ID og program-ID må være gyldige ID-er fra Microsoft.');
        if(strlen($secret)>4096||($secret!==''&&preg_match('/[\x00-\x1f]/',$secret)))throw new \RuntimeException('Ugyldig klienthemmelighet.');
        self::locked(static function()use($tenant,$client,$secret){
            $old=self::config();
            if($secret===''){
                if(($old['tenant']??'')!==$tenant||($old['client']??'')!==$client||empty($old['secret']))throw new \RuntimeException('Legg inn klienthemmelighet for denne appen.');
                $stored=$old['secret'];
            }else{$stored=self::seal(['secret'=>$secret]);}
            // Every save invalidates pending OAuth attempts and existing tokens.
            self::save(['tenant'=>$tenant,'client'=>$client,'secret'=>$stored,'generation'=>bin2hex(random_bytes(16))]);
        });
    }
    private static function requireConfig(array $c): void {
        if(empty($c['tenant'])||empty($c['client'])||empty($c['secret']))throw new \RuntimeException('Lagre Microsoft-oppsettet først.');
        if(strpos(self::callback(),'https://')!==0)throw new \RuntimeException('Microsoft-tilkoblingen krever HTTPS.');
    }
    public static function begin(): string {
        return self::locked(static function(){
            $c=self::config();self::requireConfig($c);
            $state=bin2hex(random_bytes(32));$verifier=bin2hex(random_bytes(32));
            $c['oauth']=self::seal(['state'=>hash('sha256',$state),'verifier'=>$verifier,'user'=>get_current_user_id(),'session'=>hash('sha256',wp_get_session_token()),'expires'=>time()+600,'generation'=>$c['generation']]);self::save($c);
            $challenge=rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'=');
            return 'https://login.microsoftonline.com/'.$c['tenant'].'/oauth2/v2.0/authorize?'.http_build_query(['client_id'=>$c['client'],'response_type'=>'code','redirect_uri'=>self::callback(),'response_mode'=>'query','scope'=>self::SCOPE,'state'=>$state,'code_challenge'=>$challenge,'code_challenge_method'=>'S256','prompt'=>'select_account'], '', '&', PHP_QUERY_RFC3986);
        });
    }
    private static function token(array $c,array $grant): array {
        $secret=self::unseal($c['secret'])['secret'];
        $r=wp_remote_post('https://login.microsoftonline.com/'.$c['tenant'].'/oauth2/v2.0/token',['timeout'=>25,'redirection'=>0,'body'=>array_merge(['client_id'=>$c['client'],'client_secret'=>$secret,'scope'=>self::SCOPE],$grant)]);
        if(is_wp_error($r))throw new \RuntimeException('Kunne ikke nå Microsoft. Prøv tilkoblingen igjen senere.');
        $d=json_decode(wp_remote_retrieve_body($r),true);
        if(wp_remote_retrieve_response_code($r)!==200||!is_array($d))throw new \RuntimeException('Microsoft avviste tilkoblingen. Kontroller app, hemmelighet og samtykke, og koble til på nytt.');
        $scopes=preg_split('/\s+/',strtolower((string)($d['scope']??'')));
        if(!in_array('mail.send.shared',$scopes,true)&&!in_array('https://graph.microsoft.com/mail.send.shared',$scopes,true))throw new \RuntimeException('Microsoft ga ikke nødvendig Mail.Send.Shared-tilgang.');
        if(empty($d['access_token'])||strtolower($d['token_type']??'')!=='bearer'||(int)($d['expires_in']??0)<120)throw new \RuntimeException('Microsoft returnerte en ugyldig tilgang.');
        return ['access'=>$d['access_token'],'refresh'=>$d['refresh_token']??($grant['refresh_token']??''),'expires'=>time()+(int)$d['expires_in']];
    }
    public static function complete(string $state,string $code,bool $denied=false): void {
        self::locked(static function()use($state,$code,$denied){
            $c=self::config();self::requireConfig($c);
            if(empty($c['oauth']))throw new \RuntimeException('Tilkoblingsforsøket finnes ikke eller er allerede brukt.');
            $o=self::unseal($c['oauth']);
            if($state===''||!hash_equals($o['state'],hash('sha256',$state))||$o['user']!==get_current_user_id()||!hash_equals($o['session'],hash('sha256',wp_get_session_token()))||$o['expires']<time()||$o['generation']!==$c['generation'])throw new \RuntimeException('Tilkoblingsforsøket er utløpt eller tilhører en annen innlogging. Start på nytt.');
            unset($c['oauth']);self::save($c);
            if($denied||$code==='')throw new \RuntimeException('Microsoft-tilkoblingen ble ikke godkjent.');
            $tokens=self::token($c,['grant_type'=>'authorization_code','code'=>$code,'redirect_uri'=>self::callback(),'code_verifier'=>$o['verifier']]);
            if($tokens['refresh']==='')throw new \RuntimeException('Microsoft ga ikke tilgang til automatisk fornyelse.');
            $c['tokens']=self::seal($tokens);$c['connected_at']=gmdate(DATE_ATOM);unset($c['last']);self::save($c);
        });
    }
    public static function disconnect(): void {self::locked(static function(){ $c=self::config();unset($c['tokens'],$c['oauth'],$c['connected_at']);$c['generation']=bin2hex(random_bytes(16));self::save($c); });}
    private static function access(): string {
        return self::locked(static function(){
            $c=self::config();self::requireConfig($c);
            if(empty($c['tokens']))throw new \RuntimeException('Microsoft 365 er ikke tilkoblet. Åpne Fotballrobot → Microsoft 365 e-post.');
            $t=self::unseal($c['tokens']);
            if($t['expires']<time()+90){
                $t=self::token($c,['grant_type'=>'refresh_token','refresh_token'=>$t['refresh']]);
                $c['tokens']=self::seal($t);self::save($c);
            }return $t['access'];
        });
    }
    /** Fixed destination and sender; only explicit review mail uses this method. No automatic send retry. */
    public static function send(string $subject,string $body): bool {
        $access=self::access();
        $message=['subject'=>preg_replace('/[\r\n]+/',' ',$subject),'body'=>['contentType'=>'Text','content'=>$body],'from'=>['emailAddress'=>['address'=>PlayerReview::FROM,'name'=>'Fotballroboten']],'toRecipients'=>[['emailAddress'=>['address'=>PlayerReview::TO]]]];
        $r=wp_remote_post('https://graph.microsoft.com/v1.0/me/sendMail',['timeout'=>25,'redirection'=>0,'headers'=>['Authorization'=>'Bearer '.$access,'Content-Type'=>'application/json'],'body'=>wp_json_encode(['message'=>$message,'saveToSentItems'=>true])]);
        if(is_wp_error($r))throw new \RuntimeException('Uavklart sending: Microsoft svarte ikke. Kontroller innboksen og Sendt før du prøver igjen.');
        $status=wp_remote_retrieve_response_code($r);
        if($status===202)return true;
        if($status===403)throw new \RuntimeException('Microsoft avviste sendingen. Kontroller Mail.Send.Shared og «Send som» for kontoen du koblet til.');
        if($status===401)throw new \RuntimeException('Microsoft-innloggingen er ikke gyldig. Koble til på nytt.');
        if($status===429)throw new \RuntimeException('Microsoft begrenser utsendingen. Vent før du prøver igjen.');
        throw new \RuntimeException('Microsoft bekreftet ikke sendingen (HTTP '.(int)$status.'). Kontroller innboksen og Sendt før nytt forsøk.');
    }
    public static function allowed(): void {if(!current_user_can('manage_options'))wp_die('Ingen tilgang.',403);}
    public static function action(): void {
        self::allowed();check_admin_referer('rrfr_microsoft_mail');
        $op=sanitize_key($_POST['operation']??'');
        try{
            if($op==='save'){self::configure(trim((string)wp_unslash($_POST['tenant']??'')),trim((string)wp_unslash($_POST['client']??'')),trim((string)wp_unslash($_POST['secret']??'')));$notice='Oppsett lagret. Koble til Microsoft 365.';}
            elseif($op==='connect'){$url=self::begin();wp_redirect($url,303,'Fotballrobot');exit;}
            elseif($op==='disconnect'){self::disconnect();$notice='Lokale tilgangstokener er fjernet. Appens samtykke kan også trekkes tilbake i Microsoft Entra.';}
            elseif($op==='test'){self::send('[TEST] Fotballrobotens Microsoft 365-tilkobling','Dette er en tilkoblingstest fra Fotballroboten. Ingen artikkel er publisert. Åpne artikkelforslagene i WordPress: '.PlayerReview::url());$notice='Microsoft har akseptert testmeldingen. Bekreft at den kommer fram i innboksen.';}
            else throw new \RuntimeException('Ukjent handling.');
        }catch(\Throwable $e){$notice=$e->getMessage();}
        set_transient('rrfr_ms_notice_'.get_current_user_id(),$notice,300);wp_safe_redirect(self::url());exit;
    }
    public static function receive(): void {
        self::allowed();nocache_headers();header('Referrer-Policy: no-referrer');
        try{self::complete((string)wp_unslash($_GET['state']??''),(string)wp_unslash($_GET['code']??''),isset($_GET['error']));$notice='Microsoft 365 er tilkoblet. Send en test for å kontrollere avsenderrettigheten.';}
        catch(\Throwable $e){$notice=$e->getMessage();}
        set_transient('rrfr_ms_notice_'.get_current_user_id(),$notice,300);wp_safe_redirect(self::url());exit;
    }
    public static function page(): void {self::allowed();require __DIR__.'/microsoft-mail-page.php';}
}
add_action('admin_menu',[MicrosoftMail::class,'menu'],11);
add_action('admin_post_rrfr_microsoft_mail',[MicrosoftMail::class,'action']);
add_action('admin_post_rrfr_microsoft_callback',[MicrosoftMail::class,'receive']);
