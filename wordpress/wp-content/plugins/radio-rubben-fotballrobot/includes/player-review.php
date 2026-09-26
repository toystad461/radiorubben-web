<?php
namespace RadioRubben\Fotballrobot;

/** Durable, human-approved publication. Links only open the authenticated review page. */
final class PlayerReview {
    const TO='thomas.sellevold-oystad@radiorubben.no';
    const FROM='fotballrobot@radiorubben.no';
    const META='_rrfr_player_review';
    public static function menu(): void {add_submenu_page('rr-fotballrobot','Artikler til godkjenning','Artikler til godkjenning','manage_options','rrfr-player-review',[self::class,'page']);}
    public static function url(int $id=0): string {return admin_url('admin.php?page=rrfr-player-review'.($id?'&post_id='.$id:''));}
    public static function hash($p): string {return hash('sha256',$p->post_title."\n".$p->post_content."\n".$p->post_excerpt);}
    public static function state(int $id): array { $s=get_post_meta($id,self::META,true); if(!is_array($s)||!$s)throw new \RuntimeException('Ukjent artikkelforslag.');return $s;}
    private static function put(int $id,array $s): void {update_post_meta($id,self::META,$s);if(get_post_meta($id,self::META,true)!==$s)throw new \RuntimeException('Kunne ikke lagre godkjenningsstatus.');}
    private static function lock(string $key,callable $fn) {
        $lock='rrfr_review_lock_'.$key;if(!add_option($lock,time(),'','no'))throw new \RuntimeException('Forslaget behandles allerede. En avbrutt jobb må kontrolleres før låsen fjernes.');
        try{return $fn();}finally{delete_option($lock);}
    }
    public static function headers(): array {return ['Content-Type: text/plain; charset=UTF-8','From: Fotballroboten <'.self::FROM.'>'];}
    public static function notify(int $id): void {
        self::lock('mail_'.$id,static function() use($id){
            $s=self::state($id);$p=get_post($id);
            if($s['status']!=='pending'||in_array($s['mail']??'', ['accepted','sending'],true))return;
            $s['mail']='sending';self::put($id,$s);
            $subject=($s['test']?'[TEST] ':'').'Fotballroboten: '.$p->post_title;
            $body=($s['test']?"TEST – ingen publisering, også når du velger ja.\n\n":'').$p->post_title."\n\n".$p->post_excerpt."\n\nLes hele forslaget og velg ja, nei eller be om endringer med kommentar:\n".self::url($id)."\n\nDu må logge inn i WordPress. Lenken publiserer ingenting. Kommentarer skrives på godkjenningssiden; svar på denne e-posten behandles ikke automatisk.\n\nFotballroboten · Radio Rubben";
            try {$ok=wp_mail(self::TO,preg_replace('/[\r\n]+/',' ',$subject),$body,self::headers());$s['mail']=$ok?'accepted':'failed';}catch(\Throwable $e){$s['mail']='failed';}
            $s['mail_at']=gmdate(DATE_ATOM);self::put($id,$s);
        });
    }
    public static function create(string $key,array $facts,bool $test=false): int {
        return self::lock('create_'.hash('sha256',$key),static function()use($key,$facts,$test){
            $found=get_posts(['post_type'=>'post','post_status'=>['draft','pending','publish','private','future','trash'],'meta_key'=>'_rrfr_review_key','meta_value'=>$key,'numberposts'=>1]);
            if($found)return (int)$found[0]->ID;
            // Reserve before paid generation. Failed or interrupted jobs are never retried automatically.
            $id=wp_insert_post(['post_type'=>'post','post_status'=>'draft','post_title'=>($test?'[TEST] ':'').'Spillerforslag klargjøres','meta_input'=>['_rrfr_review_key'=>$key,self::META=>['test'=>$test,'status'=>'writing','version'=>0,'mail'=>'none','facts'=>$facts,'history'=>[]]]],true);
            if(is_wp_error($id))throw new \RuntimeException($id->get_error_message());
            self::write((int)$id,'');return (int)$id;
        });
    }
    private static function write(int $id,string $comment): void {
        $s=self::state($id);$p=get_post($id);$before=self::hash($p);
        try {
            $a=Writer::playerArticle($s['facts'],$comment,$s['article']??null);
            // Never overwrite a human edit made while the request was running.
            if(self::hash(get_post($id))!==$before||get_post($id)->post_status!=='draft')throw new \RuntimeException('Artikkelen ble endret under skriving. Teksten din er beholdt.');
            $body='<p><small>'.($s['test']?'TEST – skal ikke publiseres. ':'').'Utkast fra Fotballroboten, basert på offentlige opplysninger fra Fotball.no.</small></p>';
            foreach(array_merge([$a['lead']],$a['paragraphs']) as $ptext)$body.='<p>'.esc_html($ptext).'</p>';
            $body.='<p><small>Kilde: <a href="'.esc_url($s['facts']['source']).'">Fotball.no</a> · Hentet '.esc_html($s['facts']['fetched_at']).'</small></p>';
            $r=wp_update_post(['ID'=>$id,'post_title'=>($s['test']?'[TEST] ':'').$a['title'],'post_content'=>$body,'post_excerpt'=>$a['lead'],'post_category'=>[17]],true);
            if(is_wp_error($r))throw new \RuntimeException($r->get_error_message());
            $s['article']=$a;$s['status']='pending';$s['version']++;$s['hash']=self::hash(get_post($id));$s['mail']='none';unset($s['error']);self::put($id,$s);
        }catch(\Throwable $e){$s['status']='failed';$s['error']=$e->getMessage();self::put($id,$s);return;}
        self::notify($id);
    }
    public static function test(int $player): int {
        if(!Robot::allowed())throw new \RuntimeException('Ingen tilgang.');
        $s=Players::state($player);$p=$s['snapshot'];if(!$p)throw new \RuntimeException('Hent spillerdata først.');
        $facts=['type'=>'test_profile','name'=>$p['name'],'clubs'=>$p['clubs'],'stats'=>array_values(array_filter($p['stats'],static fn($r)=>$r['year']===(int)wp_date('Y'))),'source'=>$p['source'],'fetched_at'=>$p['fetched_at']];
        return self::create('test:'.$player.':'.get_current_user_id(),$facts,true);
    }
    public static function tick(): void {
        $since=get_option('rrfr_player_review_enabled_at',0);if(!$since)return;
        // One proposal per run, grouping observations from the same player revision.
        foreach(Players::ids() as $id){$s=Players::state((int)$id);if(!$s['enabled'])continue;$groups=[];
            foreach($s['events'] as $e)if($e['status']==='new'&&strtotime($e['detected_at'])>=$since)$groups[$e['detected_at']][]=$e;
            foreach($groups as $at=>$events){
                $key='events:'.$id.':'.$at;
                $found=get_posts(['post_type'=>'post','post_status'=>['draft','pending','publish','private','future','trash'],'meta_key'=>'_rrfr_review_key','meta_value'=>$key,'numberposts'=>1]);if($found)continue;
                try{self::create($key,['type'=>'events','events'=>$events,'source'=>PlayerFacts::url($s['fiks_id']),'fetched_at'=>$at]);}catch(\Throwable $e){update_option('rrfr_review_queue_error',$e->getMessage(),false);}return;
            }
        }
    }
    public static function decide(int $id,int $version,string $hash,string $op,string $comment): void {
        if(!Robot::allowed()||!current_user_can('edit_post',$id))throw new \RuntimeException('Ingen tilgang.');
        self::lock((string)$id,static function()use($id,$version,$hash,$op,$comment){
            $s=self::state($id);$p=get_post($id);
            if(!$p||$p->post_type!=='post'||$p->post_status!=='draft')throw new \RuntimeException('Forslaget er ikke et redigerbart utkast.');
            if($version!==$s['version']||!hash_equals(self::hash($p),$hash))throw new \RuntimeException('Teksten eller statusen er endret. Last siden på nytt.');
            $comment=sanitize_textarea_field($comment);if(mb_strlen($comment)>2000)throw new \RuntimeException('Kommentaren kan være høyst 2000 tegn.');
            if($op==='mail') {if($s['status']!=='pending')throw new \RuntimeException('Ingen tekst venter på godkjenning.');$s['mail']='none';self::put($id,$s);self::notify($id);return;}
            if(!in_array($op,['approve','reject','revise','resubmit','retry'],true))throw new \RuntimeException('Ukjent valg.');
            if(in_array($op,['approve','reject','revise'],true)&&$s['status']!=='pending')throw new \RuntimeException('Forslaget er allerede behandlet.');
            if($op==='approve'&&!current_user_can('publish_posts'))throw new \RuntimeException('Du mangler publiseringsrettighet.');
            if($op==='revise'&&mb_strlen($comment)<5)throw new \RuntimeException('Beskriv endringen du ønsker.');
            if($op==='retry'&&$s['status']!=='failed')throw new \RuntimeException('Bare feilede skrivejobber kan prøves på nytt.');
            if($op==='resubmit'&&empty($s['article']))throw new \RuntimeException('Forslaget mangler en kontrollert tekst.');
            $s['history'][]=['action'=>$op,'comment'=>$comment,'user'=>get_current_user_id(),'at'=>gmdate(DATE_ATOM),'version'=>$s['version'],'hash'=>$hash];
            $s['version']++;
            if($op==='approve'){
                $s['status']=$s['test']?'test_approved':'publishing';self::put($id,$s);
                if(!$s['test']){$r=wp_update_post(['ID'=>$id,'post_status'=>'publish'],true);if(is_wp_error($r)||get_post($id)->post_status!=='publish')throw new \RuntimeException('Publisering ble ikke bekreftet. Kontroller innlegget før nytt forsøk.');$s['status']='published';self::put($id,$s);}
            }elseif($op==='reject'){$s['status']='rejected';self::put($id,$s);}
            elseif(in_array($op,['revise','retry'],true)){$s['status']='writing';self::put($id,$s);self::write($id,$comment);}
            else {if(!in_array($s['status'],['failed','rejected','test_approved'],true))throw new \RuntimeException('Dette forslaget kan ikke sendes på nytt nå.');$s['status']='pending';$s['hash']=$hash;$s['mail']='none';self::put($id,$s);self::notify($id);}
        });
    }
    public static function action(): void {
        if(!Robot::allowed())wp_die('Ingen tilgang.',403);check_admin_referer('rrfr_player_review');$id=absint($_POST['post_id']??0);
        try{
            $op=sanitize_key($_POST['operation']??'');
            if($op==='test')$id=self::test(absint($_POST['player_id']??0));
            elseif($op==='enable'){update_option('rrfr_player_review_enabled_at',time(),false);}
            elseif($op==='disable'){delete_option('rrfr_player_review_enabled_at');}
            else self::decide($id,absint($_POST['version']??0),(string)wp_unslash($_POST['hash']??''),$op,(string)wp_unslash($_POST['comment']??''));
            $message='Handlingen er lagret. Se status nedenfor.';
        }catch(\Throwable $e){$message=$e->getMessage();}
        set_transient('rrfr_review_notice_'.get_current_user_id(),$message,120);wp_safe_redirect(self::url($id));exit;
    }
    public static function fields(string $op,int $id=0): void {
        wp_nonce_field('rrfr_player_review');foreach(['action'=>'rrfr_player_review','operation'=>$op,'post_id'=>$id] as $k=>$v)echo '<input type="hidden" name="'.esc_attr($k).'" value="'.esc_attr($v).'">';
    }
    public static function page(): void {require __DIR__.'/player-review-page.php';}
    public static function guardTest(array $data,array $postarr): array {
        $id=(int)($postarr['ID']??0);$s=$id?get_post_meta($id,self::META,true):null;
        if(is_array($s)&&!empty($s['test'])&&in_array($data['post_status'],['publish','future','private'],true))$data['post_status']='draft';
        return $data;
    }
}
add_action('admin_menu',[PlayerReview::class,'menu'],11);
add_action('admin_post_rrfr_player_review',[PlayerReview::class,'action']);
add_action('rrfr_players_tick',[PlayerReview::class,'tick'],20);
add_filter('wp_insert_post_data',[PlayerReview::class,'guardTest'],10,2);
