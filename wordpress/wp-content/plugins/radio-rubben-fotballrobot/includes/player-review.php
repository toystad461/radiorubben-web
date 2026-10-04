<?php
namespace RadioRubben\Fotballrobot;
require_once __DIR__.'/publication-gate.php';
require_once __DIR__.'/editorial-notice.php';
require_once __DIR__.'/player-monitor.php';
require_once __DIR__.'/player-news-filter.php';
require_once __DIR__.'/review-desk.php';
require_once __DIR__.'/review-digest.php';

/** Durable, human-approved publication. Links only open the authenticated review page. */
final class PlayerReview {
    const TO='thomas.sellevold-oystad@radiorubben.no';
    const FROM='fotballrobot@radiorubben.no';
    const META='_rrfr_player_review';
    const DEFAULT_FEATURED_MEDIA=813; // Radio Rubben Fotball, without a club logo.
    public static function ensureImage(int $id): void {
        // Respect an editor-selected image. Never replace it during a rewrite.
        $selected=(int)get_post_meta($id,'_thumbnail_id',true);
        if($selected) {
            if(!wp_attachment_is_image($selected))throw new \RuntimeException('Hovedbildet er ikke tilgjengelig. Velg et nytt bilde før godkjenning.');
            return;
        }
        if(!wp_attachment_is_image(self::DEFAULT_FEATURED_MEDIA)||!set_post_thumbnail($id,self::DEFAULT_FEATURED_MEDIA))
            throw new \RuntimeException('Fotballbildet mangler. Velg et hovedbilde før godkjenning.');
    }
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
        self::lock('mail_'.$id,static fn()=>ReviewDigest::enqueue($id));
    }
    public static function create(string $key,array $facts,bool $test=false,bool $notify=true): int {
        return self::lock('create_'.hash('sha256',$key),static function()use($key,$facts,$test,$notify){
            $found=get_posts(['post_type'=>'post','post_status'=>['draft','pending','publish','private','future','trash'],'meta_key'=>'_rrfr_review_key','meta_value'=>$key,'numberposts'=>1]);
            if($found)return (int)$found[0]->ID;
            // Reserve before paid generation. Failed or interrupted jobs are never retried automatically.
            $id=wp_insert_post(['post_type'=>'post','post_status'=>'draft','post_title'=>($test?'[TEST] ':'').'Spillerforslag klargjøres','meta_input'=>['_rrfr_review_key'=>$key,self::META=>['test'=>$test,'notify'=>$notify,'status'=>'writing','version'=>0,'mail'=>'none','facts'=>$facts,'history'=>[]]]],true);
            if(is_wp_error($id))throw new \RuntimeException($id->get_error_message());
            self::write((int)$id,'');return (int)$id;
        });
    }
    private static function write(int $id,string $comment): void {
        $s=self::state($id);$p=get_post($id);$before=self::hash($p);
        update_post_meta($id,PublicationGate::META,['rulesVersion'=>EditorialQuality::RULES_VERSION,'publishable'=>false,'findings'=>['Ny skrive- og kvalitetskontroll er ikke fullført.']]);
        try {
            self::ensureImage($id);
            $a=Writer::playerArticle($s['facts'],$comment,$s['article']??null);
            // Never overwrite a human edit made while the request was running.
            if(self::hash(get_post($id))!==$before||get_post($id)->post_status!=='draft')throw new \RuntimeException('Artikkelen ble endret under skriving. Teksten din er beholdt.');
            $body=self::body($a,$s['facts'],$s['test']);
            $r=wp_update_post(['ID'=>$id,'post_title'=>($s['test']?'[TEST] ':'').$a['title'],'post_content'=>$body,'post_excerpt'=>$a['lead'],'post_category'=>[17]],true);
            if(is_wp_error($r))throw new \RuntimeException($r->get_error_message());
            update_post_meta($id,PublicationGate::META,PublicationGate::bind($a['_quality'],['post_title'=>($s['test']?'[TEST] ':'').$a['title'],'post_content'=>$body,'post_excerpt'=>$a['lead']]));
            unset($a['_quality']);
            $s['article']=$a;$s['status']='pending';$s['version']++;$s['hash']=self::hash(get_post($id));$s['mail']='none';unset($s['error']);self::put($id,$s);
        }catch(\Throwable $e){$s['status']='failed';$s['error']=$e->getMessage();self::put($id,$s);return;}
        if($s['notify']??true) self::notify($id);
    }
    public static function body(array $article,array $facts,bool $test=false): string {
        $block=static fn($html)=>"<!-- wp:paragraph -->\n<p>".$html."</p>\n<!-- /wp:paragraph -->\n";
        $body=EditorialNotice::BLOCK."\n";
        if($test)$body.=$block('<strong>TEST – skal ikke publiseres.</strong>');
        foreach(InlineSources::paragraphs($article,$facts) as $html)$body.=$block($html);
        if($video=MediaSources::videoBlock($facts))$body.=$block($video);
        $sources=InlineSources::urls($facts);
        $links=[];
        foreach(array_unique($sources) as $url)$links[]='<a href="'.esc_url($url).'">'.esc_html(preg_replace('/^www\./','',parse_url($url,PHP_URL_HOST)??'')).'</a>';
        return $body.$block('<small style="font-size:13px;line-height:1.5;">Kilder: '.implode(', ',$links).'</small>');
    }
    public static function test(int $player): int {
        if(!Robot::allowed())throw new \RuntimeException('Ingen tilgang.');
        $s=Players::state($player);$p=$s['snapshot'];if(!$p)throw new \RuntimeException('Hent spillerdata først.');
        $facts=['type'=>'test_profile','name'=>$p['name'],'clubs'=>$p['clubs'],'stats'=>array_values(array_filter($p['stats'],static fn($r)=>$r['year']===(int)wp_date('Y'))),'source'=>$p['source'],'fetched_at'=>$p['fetched_at']];
        return self::create('test:'.$player.':'.get_current_user_id(),$facts,true);
    }
    public static function tick(): void {
        $since=get_option('rrfr_player_review_enabled_at',0);if(!$since)return;
        // One proposal per run across both sources; oldest unhandled observation first.
        $candidates=PlayerMonitor::candidates();
        foreach(Players::ids() as $id){$s=Players::state((int)$id);if(!$s['enabled'])continue;
            foreach(self::newsSelection((int)$id,$s,(int)$since)['candidates'] as $candidate)$candidates[]=$candidate;
        }
        usort($candidates,static fn($a,$b)=>(strtotime($a['at'])<=>strtotime($b['at']))?:strcmp($a['key'],$b['key']));
        if(!$candidates)return;
        $next=$candidates[0];
        try{self::create($next['key'],$next['facts']);delete_option('rrfr_review_queue_error');}
        catch(\Throwable $e){update_option('rrfr_review_queue_error',$e->getMessage(),false);}
    }
    /** Read-only queue inspection; preserve old drafts, decisions and raw observations. */
    public static function newsSelection(int $id,array $state,int $since): array {
        $events=[];$handled=[];
        foreach($state['events'] as $event){
            $at=(string)($event['detected_at']??'');
            if(($event['status']??'')!=='new'||strtotime($at)<$since)continue;
            if(!array_key_exists($at,$handled))$handled[$at]=(bool)PlayerMonitor::review('events:'.$id.':'.$at);
            if(!$handled[$at])$events[]=$event;
        }
        $selection=PlayerNewsFilter::select($state,$events);$candidates=[];
        foreach($selection['proposals'] as $mid=>$proposal){
            $key='match-news:'.$state['fiks_id'].':'.$mid;
            if(!PlayerMonitor::review($key))$candidates[]=['key'=>$key]+$proposal;
        }
        unset($selection['proposals']);$selection['candidates']=$candidates;
        return $selection;
    }
    public static function decide(int $id,int $version,string $hash,string $op,string $comment,string $editorFacts=''): void {
        if(!Robot::allowed()||!current_user_can('edit_post',$id))throw new \RuntimeException('Ingen tilgang.');
        self::lock((string)$id,static function()use($id,$version,$hash,$op,$comment,$editorFacts){
            $s=self::state($id);$p=get_post($id);
            if(!$p||$p->post_type!=='post'||$p->post_status!=='draft')throw new \RuntimeException('Forslaget er ikke et redigerbart utkast.');
            if($version!==$s['version']||!hash_equals(self::hash($p),$hash))throw new \RuntimeException('Teksten eller statusen er endret. Last siden på nytt.');
            $comment=sanitize_textarea_field($comment);if(mb_strlen($comment)>2000)throw new \RuntimeException('Kommentaren kan være høyst 2000 tegn.');
            $editorFacts=trim($editorFacts);
            if($editorFacts!=='' && ($op!=='revise' || $editorFacts!==strip_tags($editorFacts) || mb_strlen($editorFacts)>2000)) throw new \RuntimeException('Egne opplysninger må være ren tekst på høyst 2000 tegn og sendes til omskriving.');
            if($op==='mail') {if($s['status']!=='pending')throw new \RuntimeException('Ingen tekst venter på godkjenning.');$s['mail']='none';self::put($id,$s);self::notify($id);return;}
            if(!in_array($op,['approve','reject','revise','resubmit','retry'],true))throw new \RuntimeException('Ukjent valg.');
            if(in_array($op,['approve','reject','revise'],true)&&$s['status']!=='pending')throw new \RuntimeException('Forslaget er allerede behandlet.');
            if($op==='approve'&&!current_user_can('publish_posts'))throw new \RuntimeException('Du mangler publiseringsrettighet.');
            if($op==='revise'&&mb_strlen($comment)<5&&mb_strlen($editorFacts)<5)throw new \RuntimeException('Beskriv endringen eller opplysningene du ønsker å legge til.');
            if($op==='retry'&&$s['status']!=='failed')throw new \RuntimeException('Bare feilede skrivejobber kan prøves på nytt.');
            if($op==='resubmit'&&empty($s['article']))throw new \RuntimeException('Forslaget mangler en kontrollert tekst.');
            if($op==='approve'&&!PublicationGate::current($id,$p)) throw new \RuntimeException('Språk- og kvalitetskontroll mangler eller er utdatert. Kontroller lagret tekst i WordPress først.');
            $s['history'][]=['action'=>$op,'comment'=>$comment,'user'=>get_current_user_id(),'at'=>gmdate(DATE_ATOM),'version'=>$s['version'],'hash'=>$hash,'rulesVersion'=>EditorialQuality::RULES_VERSION];
            if($editorFacts!=='') {
                $note=['text'=>$editorFacts,'source_kind'=>'editor_confirmed','source_name'=>(string)wp_get_current_user()->display_name,'user'=>get_current_user_id(),'recorded_at'=>gmdate(DATE_ATOM)];
                $s['facts']['editorial_facts'][]=$note;
                $s['history'][array_key_last($s['history'])]['editorial_facts']=$note;
                // New source facts invalidate the old review. They never grant publication approval.
                update_post_meta($id,PublicationGate::META,['rulesVersion'=>EditorialQuality::RULES_VERSION,'publishable'=>false,'findings'=>['Nye redaksjonelle opplysninger må kontrolleres sammen med den omskrevne teksten.']]);
            }
            $s['version']++;
            if($op==='approve'){
                $s['hash']=$hash;$s['status']=$s['test']?'test_approved':'publishing';self::put($id,$s);
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
