<?php
namespace RadioRubben\Fotballrobot;

/** One reading desk; all publication still requires an authenticated, explicit POST. */
final class ReviewDesk {
    const META='_rrfr_editor_decision';
    public static function model(int $id): array {
        $p=get_post($id);
        if(!Robot::allowed()||!current_user_can('edit_post',$id)||!$p||$p->post_type!=='post'||$p->post_status==='trash'||!PublicationGate::managed($id))
            throw new \RuntimeException('Dette forslaget er ikke tilgjengelig for godkjenning.');
        $player=get_post_meta($id,PlayerReview::META,true);
        $isPlayer=is_array($player)&&!empty($player);
        $s=$isPlayer?$player:get_post_meta($id,self::META,true);
        if(!is_array($s))$s=[];
        $status=$s['status']??'pending';
        if($p->post_status==='publish')$status='published';
        $test=!empty($s['test'])||get_post_meta($id,'_rrfr_test_only',true)||get_post_meta($id,'_rrfr_trial_match',true);
        $quality=PublicationGate::current($id,$p);
        return ['post'=>$p,'state'=>$s,'player'=>$isPlayer,'status'=>$status,'test'=>(bool)$test,'quality'=>$quality,
            'pending'=>$status==='pending'&&$p->post_status==='draft',
            'can_approve'=>$status==='pending'&&$p->post_status==='draft'&&$quality&&current_user_can('publish_posts')&&(!$test||$isPlayer)];
    }
    public static function token(int $id,array $m): string {
        $p=$m['post'];
        return hash('sha256',serialize([PlayerReview::hash($p),$p->post_status,$p->post_modified_gmt??'',
            (int)($m['state']['version']??0),$m['status'],get_post_meta($id,'_thumbnail_id',true)]));
    }
    private static function save(int $id,array $s): void {
        update_post_meta($id,self::META,$s);
        if(get_post_meta($id,self::META,true)!==$s)throw new \RuntimeException('Kunne ikke lagre avgjørelsen.');
    }
    public static function decide(int $id,string $token,string $op,string $comment='',string $editorFacts=''): void {
        $m=self::model($id);
        if(!hash_equals(self::token($id,$m),$token))throw new \RuntimeException('Forslaget er endret siden du åpnet det. Les den nyeste teksten før du velger på nytt.');
        if($m['player']) {
            PlayerReview::decide($id,(int)$m['state']['version'],PlayerReview::hash($m['post']),$op,$comment,$editorFacts);
            return;
        }
        $lock='rrfr_review_lock_'.$id;
        if(!add_option($lock,time(),'','no'))throw new \RuntimeException('Forslaget behandles allerede. Last siden på nytt før du prøver igjen.');
        try {
            $m=self::model($id);$p=$m['post'];$s=$m['state'];
            if(!hash_equals(self::token($id,$m),$token)||$p->post_status!=='draft')throw new \RuntimeException('Forslaget er endret eller allerede behandlet. Last siden på nytt.');
            if(!in_array($op,['approve','reject','resubmit'],true))throw new \RuntimeException('Ukjent valg for kampomtalen.');
            if($op==='resubmit'&&$m['status']!=='rejected')throw new \RuntimeException('Bare avviste kampomtaler kan åpnes igjen.');
            if($op!=='resubmit'&&!$m['pending'])throw new \RuntimeException('Forslaget er allerede behandlet.');
            if($op==='approve'&&!$m['can_approve'])throw new \RuntimeException('Publisering krever et ordinært utkast, publiseringsrettighet og godkjent kontroll av den lagrede teksten.');
            $comment=sanitize_textarea_field($comment);
            if(mb_strlen($comment)>2000)throw new \RuntimeException('Kommentaren kan være høyst 2000 tegn.');
            $s['history'][]=['action'=>$op,'comment'=>$comment,'user'=>get_current_user_id(),'at'=>gmdate(DATE_ATOM),
                'hash'=>PlayerReview::hash($p),'rulesVersion'=>EditorialQuality::RULES_VERSION];
            $s['version']=(int)($s['version']??0)+1;
            $s['hash']=PlayerReview::hash($p);
            $s['status']=$op==='approve'?'publishing':($op==='reject'?'rejected':'pending');
            self::save($id,$s);
            if($op==='approve') {
                // PublicationGate independently checks quality and facts again in wp_insert_post_data.
                $r=wp_update_post(['ID'=>$id,'post_status'=>'publish'],true);
                if(is_wp_error($r)||get_post($id)->post_status!=='publish')throw new \RuntimeException('Publisering ble ikke bekreftet. Kontroller innlegget før nytt forsøk.');
                $s['status']='published';self::save($id,$s);
            }
        } finally {delete_option($lock);}
    }
    public static function action(): void {
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!Robot::allowed())wp_die('Ingen tilgang.',403);
        check_admin_referer('rrfr_review_desk');$id=absint($_POST['post_id']??0);
        try {
            self::decide($id,(string)wp_unslash($_POST['token']??''),sanitize_key($_POST['operation']??''),(string)wp_unslash($_POST['comment']??''),(string)wp_unslash($_POST['editorial_facts']??''));
            $m=self::model($id);
            $message=$m['status']==='published'?'Artikkelen er publisert.':($m['status']==='rejected'?'Forslaget er avvist. Teksten er beholdt som utkast.':'Valget er lagret. Se oppdatert status nedenfor.');
        } catch(\Throwable $e) {$message=$e->getMessage();}
        set_transient('rrfr_review_notice_'.get_current_user_id(),$message,120);
        wp_safe_redirect(PlayerReview::url($id));exit;
    }
    public static function items(): array {
        return get_posts(['post_type'=>'post','post_status'=>['draft','pending'],'numberposts'=>100,'orderby'=>'date','order'=>'ASC',
            'meta_query'=>['relation'=>'OR',['key'=>PlayerReview::META,'compare'=>'EXISTS'],['key'=>'_rrfr_ai_match','compare'=>'EXISTS'],['key'=>'_rrfr_trial_match','compare'=>'EXISTS']]]);
    }
    private static function fields(int $id,array $m): void {
        wp_nonce_field('rrfr_review_desk');
        foreach(['action'=>'rrfr_review_desk','post_id'=>$id,'token'=>self::token($id,$m)] as $key=>$value)
            echo '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($value).'">';
    }
    public static function label(array $m): string {
        if($m['test'])return 'Test – publiseres ikke';
        if($m['pending'])return $m['quality']?'Klar for gjennomlesing':'Trenger kontroll';
        return ['rejected'=>'Avvist','published'=>'Publisert','failed'=>'Skriving stoppet','writing'=>'Skriving pågår eller må kontrolleres',
            'publishing'=>'Publisering må kontrolleres','test_approved'=>'Test godkjent'][''.$m['status']]??'Trenger oppfølging';
    }
    public static function page(): void {
        if(!Robot::allowed())wp_die('Ingen tilgang.');
        $id=absint($_GET['post_id']??0);$items=[];
        foreach(self::items() as $p)try{$items[$p->ID]=self::model((int)$p->ID);}catch(\Throwable $e){}
        $pending=array_filter($items,static fn($m)=>$m['pending']&&!$m['test']);
        $ready=array_filter($pending,static fn($m)=>$m['quality']);
        echo '<div class="wrap rrfr rrfr-desk">';require __DIR__.'/review-desk-style.php';
        echo '<header><p class="rrfr-eyebrow">FOTBALLROBOTEN · REDAKSJON</p><h1>Les og godkjenn</h1><p>Les artikkelen. Publiser når du er fornøyd, eller be om endringer.</p></header>';
        $notice=get_transient('rrfr_review_notice_'.get_current_user_id());delete_transient('rrfr_review_notice_'.get_current_user_id());
        if($notice)echo '<p class="rrfr-notice" role="status">'.esc_html($notice).'</p>';
        if($id) {
            echo '<p><a href="'.esc_url(PlayerReview::url()).'">← Alle forslag</a></p>';
            try {self::article($id,self::model($id),$ready);}catch(\Throwable $e){echo '<p class="rrfr-notice">'.esc_html($e->getMessage()).'</p>';}
        } else {
            echo '<section class="rrfr-card"><h2>'.count($ready).' klare for gjennomlesing</h2><p>Kampomtaler og spillersaker er samlet her. Ingenting publiseres før du velger «Godkjenn og publiser».</p>';
            self::listing($ready);
            if(!$ready)echo '<p>Ingen ferdigkontrollerte forslag venter akkurat nå.</p>';
            echo '</section>';
            $other=array_diff_key($items,$ready);
            if($other){echo '<details class="rrfr-card"><summary>Trenger oppfølging, avviste og tester ('.count($other).')</summary>';self::listing($other);echo '</details>';}
        }
        self::settings();echo '</div>';
    }
    private static function listing(array $items): void {
        echo '<ul class="rrfr-review-list">';
        foreach($items as $id=>$m)echo '<li><a href="'.esc_url(PlayerReview::url((int)$id)).'"><span class="rrfr-muted">'.($m['player']?'Spillersak':'Kampomtale').' · '.esc_html(self::label($m)).'</span><strong>'.esc_html($m['post']->post_title).'</strong><span>Les forslaget →</span></a></li>';
        echo '</ul>';
    }
    public static function article(int $id,array $m,array $ready=[]): void {
        $p=$m['post'];$s=$m['state'];$quality=get_post_meta($id,PublicationGate::META,true);
        echo '<section class="rrfr-card rrfr-reading"><p class="rrfr-badge">'.esc_html(self::label($m)).'</p><h2 class="rrfr-story-title">'.esc_html($p->post_title).'</h2>';
        if($m['status']==='published')echo '<p><a href="'.esc_url(get_permalink($id)).'">Se den publiserte artikkelen →</a></p>';
        if(!$m['quality']&&$p->post_status==='draft') {
            echo '<div class="rrfr-notice"><strong>Teksten må kontrolleres før den kan publiseres.</strong><p>Du kan lese og redigere forslaget nå.</p>';
            if($m['pending'])echo '<a href="#rrfr-control">Se kontroll og neste steg ↓</a>';
            echo '</div>';
        }
        if(!empty($s['error']))echo '<p class="rrfr-notice">'.esc_html($s['error']).'</p>';
        $image=(int)get_post_meta($id,'_thumbnail_id',true);
        if($image) {
            echo '<figure class="rrfr-review-image">'.wp_get_attachment_image($image,'large',false,['loading'=>'lazy']);
            $caption=wp_get_attachment_caption($image);if($caption)echo '<figcaption>'.wp_kses_post($caption).'</figcaption>';echo '</figure>';
        }
        // Display the stored text and its sources, without running shortcodes, embeds or filters.
        echo '<article class="rrfr-review-prose" aria-label="Lagret artikkeltekst">'.wp_kses_post($p->post_content).'</article>';
        if($m['pending']) {
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="rrfr-decision" aria-label="Din sluttgodkjenning">';self::fields($id,$m);
            echo '<p>'.($m['test']?'Dette er en test. Artikkelen blir ikke publisert.':'Knappen publiserer den lagrede teksten du nettopp har lest.').'</p><div class="rrfr-row">';
            echo '<button class="rrfr-primary" name="operation" value="approve"'.($m['can_approve']?'':' disabled').'>'.($m['test']?'Godkjenn test':'Godkjenn og publiser').'</button>';
            echo '<a class="rrfr-secondary" href="'.esc_url(get_edit_post_link($id,'raw')).'">Rediger selv</a></div>';
            if(!$m['can_approve'])echo '<p class="rrfr-muted">'.esc_html(!current_user_can('publish_posts')?'Du mangler publiseringsrettighet.':($m['test']&&!$m['player']?'Prøveutkast kan ikke publiseres.':'Publisering er låst til kontrollen er godkjent.')).'</p>';
            echo '<details class="rrfr-changes"><summary>Be om endringer eller avvis</summary><label for="rrfr-comment">Hva vil du endre?</label><textarea id="rrfr-comment" name="comment" rows="3" maxlength="2000" placeholder="For eksempel: Kort ned ingressen og gjør Bremnes-vinkelen tydeligere."></textarea><p class="rrfr-muted">Kommentaren er intern og blir ikke en del av artikkelen.</p><div class="rrfr-row">';
            if($m['player'])echo '</div><label for="rrfr-editorial-facts">Egne opplysninger til saken</label><textarea id="rrfr-editorial-facts" name="editorial_facts" rows="3" maxlength="2000" placeholder="Opplysninger du kjenner og vil stå som kilde til."></textarea><p class="rrfr-muted">Opplysningene lagres med deg som kilde og brukes i den nye fakta- og språkkontrollen. Vanlige endringsønsker skrives i feltet over.</p><div class="rrfr-row"><button name="operation" value="revise">Send til omskriving</button>';
            else echo '<a class="rrfr-secondary" href="'.esc_url(get_edit_post_link($id,'raw')).'">Åpne teksten for endring</a>';
            echo '<button name="operation" value="reject">Avvis forslaget</button></div>';
            if($m['player'])echo '<p class="rrfr-muted">Omskriving bruker AI og krever en ny sluttgodkjenning.</p>';
            else echo '<p class="rrfr-muted">Kampomtaler redigeres manuelt. Kommentaren lagres hvis du avviser.</p>';
            echo '</details></form>';
        }
        if($p->post_status==='draft'&&!$m['quality']&&$m['pending']) {
            echo '<details id="rrfr-control" class="rrfr-control"><summary>Kontroll og neste steg</summary>';
            self::quality($id,$p,is_array($quality)?$quality:[]);
            echo '</details>';
        }
        $next=array_diff_key($ready,[$id=>true]);
        if($next){$nextId=(int)array_key_first($next);echo '<p class="rrfr-next"><a href="'.esc_url(PlayerReview::url($nextId)).'">Les neste forslag →</a></p>';}
        echo '<details class="rrfr-review-details"><summary>Historikk og flere valg</summary><p><a href="'.esc_url(get_edit_post_link($id,'raw')).'">Åpne i WordPress</a> · <a href="'.esc_url(PlayerReview::url($id)).'">Hent siste lagrede tekst</a></p>';
        echo '<p>Kontroll: '.esc_html($quality['rulesVersion']??'Ikke kontrollert').'</p>';
        if($m['player']) {
            $mailLabels=['none'=>'Ikke sendt','sending'=>'Utsending må kontrolleres','accepted'=>'Levert til e-postsystemet','failed'=>'Sending feilet'];
            echo '<p>E-post: '.esc_html($mailLabels[$s['mail']??'none']??'Ukjent').'</p>';
            if(!empty($s['mail_error']))echo '<p>'.esc_html($s['mail_error']).'</p>';
        }
        $ops=[];
        if($p->post_status==='draft') {
            if($m['player']&&$m['pending'])$ops['mail']='Send e-post på nytt';
            if($m['status']==='rejected'||($m['player']&&$m['status']==='test_approved'))$ops['resubmit']='Åpne for ny gjennomlesing';
            if($m['player']&&$m['status']==='failed')$ops['retry']='Prøv AI-skriving på nytt';
        }
        if($ops){echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';self::fields($id,$m);foreach($ops as $op=>$label)echo '<button name="operation" value="'.esc_attr($op).'">'.esc_html($label).'</button> ';echo '</form>';}
        $names=['approve'=>'Godkjent','reject'=>'Avvist','revise'=>'Bedt om endringer','resubmit'=>'Åpnet igjen','retry'=>'Nytt skriveforsøk'];
        foreach(array_reverse($s['history']??[]) as $h)echo '<p>'.esc_html(($names[$h['action']]??$h['action']).' · '.$h['at'].(!empty($h['comment'])?' · '.$h['comment']:'').(!empty($h['editorial_facts'])?' · Egne opplysninger fra '.$h['editorial_facts']['source_name'].': '.$h['editorial_facts']['text']:'')).'</p>';
        echo '</details></section>';
    }
    private static function quality(int $id,$post,array $quality): void {
        foreach($quality['findings']??[] as $finding)echo '<p>'.esc_html($finding).'</p>';
        if(!empty($quality['suggestion'])) {
            echo '<details><summary>Forslag fra språkvask</summary>';
            foreach(array_merge([$quality['suggestion']['title'],$quality['suggestion']['lead']],$quality['suggestion']['paragraphs']) as $text)echo '<p>'.esc_html($text).'</p>';
            echo '</details>';
        }
        echo '<p>Etter redigering må du lagre som utkast. Denne AI-kontrollen leser lagret tekst og endrer den ikke.</p><button type="button" id="rrfr-desk-check">Kontroller lagret tekst</button><p id="rrfr-desk-check-status" role="status" aria-live="polite"></p>';
        $config=wp_json_encode(['url'=>rest_url('rr-fotballrobot/v1/quality/'.$id),'nonce'=>wp_create_nonce('wp_rest'),'hash'=>PublicationGate::hash($post)],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
        echo '<script>document.getElementById("rrfr-desk-check").addEventListener("click",async function(){const c='.$config.';this.disabled=true;const s=document.getElementById("rrfr-desk-check-status");s.textContent="Kontrollerer språk og fakta …";try{const r=await fetch(c.url,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","X-WP-Nonce":c.nonce},body:JSON.stringify({hash:c.hash})});const d=await r.json();if(r.ok){window.location.reload();return}s.textContent=d.message||"Kontrollen feilet. Last siden på nytt før nytt forsøk."}catch(e){s.textContent="Kontrollen ble avbrutt. Last siden på nytt før nytt forsøk."}});</script>';
    }
    private static function settings(): void {
        echo '<details class="rrfr-card rrfr-settings"><summary>Innstillinger og testforslag</summary><p><a href="'.esc_url(MicrosoftMail::url()).'">E-postoppsett og tilkobling</a></p><p>Varslene lenker til denne innloggede siden. Svar på e-post behandles ikke automatisk.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        $enabled=(bool)get_option('rrfr_player_review_enabled_at',0);PlayerReview::fields($enabled?'disable':'enable');
        echo '<p>Automatiske spillerforslag: <strong>'.($enabled?'Aktivert':'Ikke aktivert').'</strong></p><button>'.($enabled?'Stopp automatiske forslag':'Aktiver nye forslag').'</button></form>';
        echo '<h3>Test med en spiller</h3><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';PlayerReview::fields('test');echo '<label>Spiller <select name="player_id">';
        foreach(Players::ids() as $pid){$state=Players::state((int)$pid);echo '<option value="'.esc_attr($pid).'">'.esc_html($state['name']).'</option>';}
        echo '</select></label> <button>Lag og send testforslag</button><p>Bruker AI. Testforslaget kan aldri publiseres.</p></form></details>';
    }
}
add_action('admin_post_rrfr_review_desk',[ReviewDesk::class,'action']);
