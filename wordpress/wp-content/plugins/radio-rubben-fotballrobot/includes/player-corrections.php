<?php
namespace RadioRubben\Fotballrobot;

/** A separate, reviewed revision. Preparing it never changes the published article. */
final class PlayerCorrections {
    const META='_rrfr_pending_correction';
    private static function live(int $id): array {
        if(!Robot::allowed()||!current_user_can('edit_post',$id))throw new \RuntimeException('Ingen tilgang.');
        $p=get_post($id);$s=PlayerReview::state($id);
        if(!$p||$p->post_type!=='post'||$p->post_status!=='publish'||($s['status']??'')!=='published'||!empty($s['test'])||get_post_meta($id,'_rrfr_test_only',true))
            throw new \RuntimeException('Bare publiserte spillersaker kan få en rettelse.');
        return [$p,$s];
    }
    public static function base(int $id): string {
        [$p,$s]=self::live($id);
        return hash('sha256',serialize([PlayerReview::hash($p),$s,get_post_meta($id,PublicationGate::META,true),get_post_meta($id,'_thumbnail_id',true)]));
    }
    private static function locked(int $id,callable $fn) {
        $key='rrfr_review_lock_'.$id;
        if(!add_option($key,time(),'','no'))throw new \RuntimeException('Saken behandles allerede.');
        try{return $fn();}finally{delete_option($key);}
    }
    private static function put(int $id,array $record): void {
        update_post_meta($id,self::META,$record);
        if(get_post_meta($id,self::META,true)!==$record)throw new \RuntimeException('Kunne ikke lagre rettelsen.');
    }
    public static function prepare(int $id,string $hash,array $article): array {
        return self::locked($id,static function()use($id,$hash,$article){
            [$p,$s]=self::live($id);
            if(!PublicationGate::current($id,$p))throw new \RuntimeException('Den publiserte versjonen trenger ny kontroll først.');
            if(!hash_equals(PlayerReview::hash($p),$hash))throw new \RuntimeException('Saken er endret. Les den på nytt.');
            if((get_post_meta($id,self::META,true)['status']??'')==='pending')throw new \RuntimeException('En rettelse venter allerede på godkjenning.');
            $base=self::base($id);
            $quality=Writer::qualityReview(Writer::validate($article),$s['facts']);
            if(!hash_equals($base,self::base($id)))throw new \RuntimeException('Saken ble endret under kontrollen. Ingen rettelse er lagret.');
            if(($quality['publishable']??false)!==true)throw new \RuntimeException(implode(' ',$quality['findings']??['Ny kontroll kreves.']));
            $a=$quality['article'];$post=['post_title'=>$a['title'],'post_excerpt'=>$a['lead'],'post_content'=>PlayerReview::body($a,$s['facts'])];
            $record=['status'=>'pending','base'=>$base,'article'=>$a,'post'=>$post,'quality'=>PublicationGate::bind($quality,$post),
                'prepared_by'=>get_current_user_id(),'at'=>gmdate(DATE_ATOM)];
            $record['token']=hash('sha256',serialize($record));self::put($id,$record);
            return ['status'=>'pending','review_url'=>PlayerReview::url($id),'article'=>$a];
        });
    }
    /** A requested rewrite replaces only the pending proposal after fresh quality checks. */
    public static function revise(int $id,string $token,string $comment): void {
        self::locked($id,static function()use($id,$token,$comment){
            [$p,$s]=self::live($id);$r=get_post_meta($id,self::META,true);
            if(!current_user_can('publish_posts')||!is_array($r)||($r['status']??'')!=='pending'||!hash_equals($r['token'],$token)||!hash_equals($r['base'],self::base($id)))
                throw new \RuntimeException('Forslaget er endret. Les saken på nytt.');
            $comment=trim(sanitize_textarea_field($comment));
            if($comment===''||mb_strlen($comment)>2000)throw new \RuntimeException('Skriv ønsket endring, inntil 2000 tegn.');
            $a=Writer::playerArticle($s['facts'],$comment,$r['article']);$quality=$a['_quality']??[];unset($a['_quality']);
            if(($quality['publishable']??false)!==true)throw new \RuntimeException('Den nye teksten bestod ikke kontrollen. Forrige forslag er beholdt.');
            if(!hash_equals($r['base'],self::base($id)))throw new \RuntimeException('Saken ble endret under kontrollen. Forslaget er beholdt.');
            $post=['post_title'=>$a['title'],'post_excerpt'=>$a['lead'],'post_content'=>PlayerReview::body($a,$s['facts'])];
            $r['history'][]=['article'=>$r['article'],'token'=>$r['token'],'requested_by'=>get_current_user_id(),'comment'=>$comment,'at'=>gmdate(DATE_ATOM)];
            $r['article']=$a;$r['post']=$post;$r['quality']=PublicationGate::bind($quality,$post);
            unset($r['token']);$r['token']=hash('sha256',serialize($r));self::put($id,$r);
        });
    }
    public static function approve(int $id,string $token): void {
        self::locked($id,static function()use($id,$token){
            [$p,$s]=self::live($id);$r=get_post_meta($id,self::META,true);
            if(!current_user_can('publish_posts')||!is_array($r)||($r['status']??'')!=='pending'||!hash_equals($r['token'],$token)||!hash_equals($r['base'],self::base($id)))
                throw new \RuntimeException('Rettelsen er endret eller du mangler tilgang. Les saken på nytt.');
            $q=$r['quality'];$new=$r['post'];
            if(($q['rulesVersion']??'')!==EditorialQuality::RULES_VERSION||($q['publishable']??false)!==true||($q['languageStatus']??'')!=='completed'||($q['findings']??null)!==[]
                ||!hash_equals($q['factsHash']??'',EditorialQuality::hash(EditorialQuality::packet($s['facts'])))||!hash_equals($q['postHash']??'',PublicationGate::hash($new)))
                throw new \RuntimeException('Rettelsen trenger ny kvalitetskontroll.');
            $oldQuality=get_post_meta($id,PublicationGate::META,true);$next=$s;
            $next['status']='publishing';$next['version']++;$next['hash']=PlayerReview::hash((object)$new);$next['article']=$r['article'];
            $next['history'][]=['action'=>'correct','user'=>get_current_user_id(),'at'=>gmdate(DATE_ATOM),'hash'=>$next['hash'],'previous_hash'=>PlayerReview::hash($p),'rulesVersion'=>EditorialQuality::RULES_VERSION];
            update_post_meta($id,PlayerReview::META,$next);update_post_meta($id,PublicationGate::META,$q);
            try {
                // The normal publication gate still verifies these exact facts and this exact text.
                if(!PublicationGate::canPublish($id,(object)$new))throw new \RuntimeException('Publiseringskontrollen avviste rettelsen.');
                $result=wp_update_post(['ID'=>$id]+$new,true);
                if(is_wp_error($result)||get_post($id)->post_status!=='publish'||PlayerReview::hash(get_post($id))!==$next['hash'])throw new \RuntimeException('Oppdateringen ble ikke bekreftet.');
                $next['status']='published';update_post_meta($id,PlayerReview::META,$next);
                $r['status']='applied';$r['approved_by']=get_current_user_id();self::put($id,$r);
            }catch(\Throwable $e){
                // Retain the original public version and its approval if a write fails.
                update_post_meta($id,PlayerReview::META,$s);update_post_meta($id,PublicationGate::META,$oldQuality);
                wp_update_post(['ID'=>$id,'post_title'=>$p->post_title,'post_excerpt'=>$p->post_excerpt,'post_content'=>$p->post_content,'post_status'=>'publish']);
                throw $e;
            }
        });
    }
    public static function discard(int $id,string $token): void {
        self::locked($id,static function()use($id,$token){self::live($id);$r=get_post_meta($id,self::META,true);
            if(!is_array($r)||($r['status']??'')!=='pending'||!hash_equals($r['token'],$token))throw new \RuntimeException('Forslaget er endret.');
            $r['status']='discarded';$r['discarded_by']=get_current_user_id();self::put($id,$r);
        });
    }
    public static function render(int $id): void {
        $r=get_post_meta($id,self::META,true);if(!is_array($r)||($r['status']??'')!=='pending')return;
        try{$current=hash_equals($r['base'],self::base($id));}catch(\Throwable $e){$current=false;}
        echo '<section class="rrfr-card"><h2>Forslag til forbedret tekst</h2><p>Den publiserte saken beholdes til du godkjenner denne versjonen. Nettadressen og bildet beholdes.</p><h3>'.esc_html($r['post']['post_title']).'</h3><article class="rrfr-review-prose">'.wp_kses_post($r['post']['post_content']).'</article>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('rrfr_player_correction');
        foreach(['action'=>'rrfr_player_correction','post_id'=>$id,'token'=>$r['token']] as $k=>$v)echo '<input type="hidden" name="'.esc_attr($k).'" value="'.esc_attr($v).'">';
        echo '<button class="button button-primary" name="operation" value="approve"'.($current&&current_user_can('publish_posts')?'':' disabled').'>Godkjenn og oppdater saken</button> <button class="button" name="operation" value="discard">Forkast endringsforslaget</button></form>';
        if(!$current)echo '<p>Saken er endret etter at forslaget ble kontrollert. Ny kontroll kreves.</p>';
        echo '</section>';
    }
    public static function action(): void {
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!Robot::allowed())wp_die('Ingen tilgang.',403);
        check_admin_referer('rrfr_player_correction');$id=absint($_POST['post_id']??0);
        try{
            $token=(string)wp_unslash($_POST['token']??'');$op=(string)($_POST['operation']??'');
            if($op==='approve'){self::approve($id,$token);$message='Saken er oppdatert.';}
            elseif($op==='discard'){
                self::discard($id,$token);$message='Endringsforslaget er forkastet. Den publiserte saken er beholdt.';
            }else throw new \RuntimeException('Ukjent valg.');
        }
        catch(\Throwable $e){$message=$e->getMessage();}
        set_transient('rrfr_review_notice_'.get_current_user_id(),$message,120);wp_safe_redirect(PlayerReview::url($id));exit;
    }
    public static function routes(): void {
        register_rest_route('rr-fotballrobot/v1','/player-corrections/(?P<id>[0-9]+)',[
            'methods'=>'POST','permission_callback'=>[Robot::class,'allowed'],
            'callback'=>static fn($r)=>Robot::response(static fn()=>self::prepare((int)$r['id'],(string)$r->get_param('hash'),(array)$r->get_param('article')))
        ]);
    }
}
add_action('rest_api_init',[PlayerCorrections::class,'routes']);
add_action('admin_post_rrfr_player_correction',[PlayerCorrections::class,'action']);
