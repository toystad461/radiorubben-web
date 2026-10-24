<?php
namespace RadioRubben\Fotballrobot;

/** Short match drafts reuse the existing reservation, fact and publication boundaries. */
final class MatchStories {
    /** A permanent Sport landing section, containing only public weekly result articles. */
    public static function archive(): string {
        $posts=get_posts(['post_type'=>'post','post_status'=>'publish','has_password'=>false,'numberposts'=>12,
            'orderby'=>'date','order'=>'DESC','meta_query'=>[['key'=>'_rrfr_club_key','value'=>'results:','compare'=>'LIKE']]]);
        $html='<section id="rr-weekly-results" class="rrfr-results-archive" aria-labelledby="rrfr-results-title"><h2 id="rrfr-results-title">Ukesresultater</h2><p>Bremnes-kampene samlet uke for uke. Åpne en oppsummering og trykk på kampkortene for publiserte referater.</p><ul>';
        $count=0;
        foreach($posts as $p){
            if($p->post_status!=='publish'||!empty($p->post_password)||get_post_meta($p->ID,'_rrfr_test_only',true)
                ||get_post_meta($p->ID,'_rrfr_trial_match',true)||!str_starts_with((string)get_post_meta($p->ID,'_rrfr_club_key',true),'results:'))continue;
            $count++;$html.='<li><a href="'.esc_url(get_permalink($p->ID)).'">'.esc_html($p->post_title).'</a></li>';
        }
        return $html.'</ul>'.(!$count?'<p>Ingen ukesresultater er publisert ennå.</p>':'').'</section>';
    }
    public static function identity(array $m): string {
        return hash('sha256',wp_json_encode([(int)$m['id'],(int)$m['home']['id'],(int)$m['away']['id'],
            (int)($m['competition']['id']??0),strtotime($m['kickoff']),$m['score']]));
    }
    public static function token(int $id): string {
        return hash('sha256',PublicationGate::hash(get_post($id)).EditorialQuality::hash(PublicationGate::facts($id)));
    }
    public static function selected(int $parent,int $match,string $token): array {
        $p=get_post($parent);
        if(!Robot::allowed()||!current_user_can('publish_posts')||!current_user_can('edit_post',$parent)
            ||!$p||$p->post_type!=='post'||!in_array($p->post_status,['draft','publish'],true))throw new \RuntimeException('Ingen tilgang til ukesartikkelen.');
        $facts=PublicationGate::facts($parent);
        if(($facts['kind']??'')!=='results'||empty($facts['week'])||strtotime($facts['week']['end'])>time()
            ||!hash_equals(self::token($parent),$token))throw new \RuntimeException('Ukesgrunnlaget er endret eller ikke avsluttet. Last siden på nytt.');
        foreach($facts['matches'] as $m)if((int)$m['id']===$match){
            if(empty($m['finished_confirmed'])||ClubCoverage::blocked($m)||!is_array($m['score']))break;
            $m['logos']=self::storedLogos($p->post_content,$match,$m['logos']??[]);
            return $m;
        }
        throw new \RuntimeException('Kampen mangler et bekreftet sluttresultat i denne uken.');
    }
    public static function storedLogos(string $html,int $match,array $fallback=[]): array {
        preg_match_all('~<section class="rrfr-week-card"[^>]*>.*?</section>~s',$html,$cards);
        foreach($cards[0] as $card){
            preg_match_all('~fiksId=([0-9]+)~',$card,$ids);
            if(array_values(array_unique($ids[1]))!==[(string)$match])continue;
            preg_match_all('~<img\b[^>]*\bsrc="([^"]+)"~',$card,$images);
            if(count($images[1])!==2)return $fallback;
            foreach(['home','away'] as $i=>$side){
                $url=html_entity_decode($images[1][$i],ENT_QUOTES,'UTF-8');
                if(WeeklyCoverage::validLogo($url))$fallback[$side]=$url;
            }
            return $fallback;
        }
        return $fallback;
    }
    public static function existing(int $match): int {
        $id=ClubAutomation::existing('match:'.$match);
        if($id&&get_post($id)->post_status==='trash')throw new \RuntimeException('Kampartikkelen ligger i papirkurven. Gjenopprett den ved behov.');
        if($id&&!current_user_can('edit_post',$id))throw new \RuntimeException('Ingen tilgang til kampartikkelen.');
        return $id;
    }
    /** Explicit draft-only migration; keep prose, image and decisions, invalidate old quality by content hash. */
    public static function template(int $parent,string $token): int {
        $facts=PublicationGate::facts($parent);
        if(empty($facts['matches']))throw new \RuntimeException('Ingen kampkort å oppdatere.');
        self::selected($parent,(int)$facts['matches'][0]['id'],$token);
        $p=get_post($parent);
        if($p->post_status!=='draft')throw new \RuntimeException('Publiserte artikler må bruke den separate rettelsesflyten.');
        $matches=[];foreach($facts['matches'] as $m)$matches[(int)$m['id']]=$m;
        $seen=[];
        $body=preg_replace_callback('~<section class="rrfr-week-card"[^>]*>.*?</section>~s',static function($card)use($matches,&$seen){
            preg_match_all('~fiksId=([0-9]+)~',$card[0],$ids);$ids=array_unique($ids[1]);
            if(count($ids)!==1)throw new \RuntimeException('Kampkortets kilde er uklar. Ingen endring lagret.');
            $id=(int)reset($ids);
            if(!isset($matches[$id])||isset($seen[$id]))throw new \RuntimeException('Kampkortet samsvarer ikke med faktagrunnlaget.');
            $seen[$id]=true;$m=$matches[$id];
            preg_match_all('~<img\b[^>]*\bsrc="([^"]+)"~',$card[0],$images);
            // Preserve a verified pair of existing logos; never guess a side for a single image.
            if(count($images[1])===2)foreach(['home','away'] as $i=>$side){
                $url=html_entity_decode($images[1][$i],ENT_QUOTES,'UTF-8');
                if(WeeklyCoverage::validLogo($url))$m['logos'][$side]=$url;
            }
            return WeeklyCoverage::card($m,true,true);
        },$p->post_content);
        if(count($seen)!==count($matches))throw new \RuntimeException('Ikke alle kampkort kunne gjenkjennes. Ingen endring lagret.');
        $body=preg_replace('~<style>\.rrfr-week-card\{.*?</style>~s','',$body);
        self::selected($parent,(int)$facts['matches'][0]['id'],$token);
        if(get_post($parent)->post_status!=='draft')throw new \RuntimeException('Artikkelen er ikke lenger et utkast.');
        $result=wp_update_post(['ID'=>$parent,'post_content'=>$body],true);
        if(is_wp_error($result))throw new \RuntimeException('Malen kunne ikke lagres.');
        return $parent;
    }
    public static function prepare(int $parent,int $match,string $token): int {
        $m=self::selected($parent,$match,$token);
        if($id=self::existing($match))return $id; // Never rewrite an editor's existing article.
        // Coordinate with both the senior job runner and the existing writer.
        $locks=[];
        try {
            foreach(['rrfr_match_job_lock_','rrfr_ai_lock_'] as $prefix){
                $key=$prefix.$match;
                if(!add_option($key,time(),'','no'))throw new \RuntimeException('Kampen behandles allerede. Prøv igjen etter kontroll.');
                $locks[]=$key;
            }
            if($id=self::existing($match))return $id;
            $job=MatchJobs::state($match);
            if($job&&!in_array($job['status']??'',['waiting','done'],true))throw new \RuntimeException('En eksisterende skrivejobb må avklares før nytt referat.');
            $feed=ClubCoverage::collect();$fresh=$feed['matches'][$match]??null;
            if(!$fresh||empty($fresh['finished_confirmed'])||ClubCoverage::blocked($fresh)
                ||!hash_equals(self::identity($m),self::identity($fresh)))throw new \RuntimeException('Kampresultatet eller kildegrunnlaget er endret. Kontroller ukesartikkelen først.');
            $facts=ClubCoverage::detailFacts($fresh);
            $facts['match']['logos']=$m['logos']??[];
            self::selected($parent,$match,$token);
            return ClubAutomation::create('match:'.$match,$facts,static function()use($facts,$parent,$match,$token){
                $a=Writer::briefArticle($facts);
                self::selected($parent,$match,$token); // Do not save on a stale browser/fact snapshot.
                $body=EditorialNotice::BLOCK.WeeklyCoverage::card($facts['match']);
                foreach(InlineSources::paragraphs($a,$facts) as $text)$body.='<!-- wp:paragraph --><p>'.$text.'</p><!-- /wp:paragraph -->';
                $post=ClubAutomation::reviewedPost($a,$body,$a['_quality']);
                $post['meta_input']['_rrfr_weekly_parent']=$parent;
                return $post;
            });
        }finally{foreach(array_reverse($locks) as $key)delete_option($key);}
    }
    /** Public links never point to drafts, tests, password-protected or mismatched stories. */
    public static function publicUrl(int $match,string $identity): string {
        $id=ClubAutomation::existing('match:'.$match);if(!$id)return '';
        $p=get_post($id);$f=get_post_meta($id,'_rrfr_fact_snapshot',true);
        if(!$p||$p->post_type!=='post'||$p->post_status!=='publish'||!empty($p->post_password)
            ||get_post_meta($id,'_rrfr_test_only',true)||get_post_meta($id,'_rrfr_trial_match',true)
            ||!is_array($f)||empty($f['match'])||!hash_equals($identity,self::identity($f['match'])))return '';
        return get_permalink($id);
    }
    public static function links(string $html): string {
        // Only deterministic, explicitly marked result cards participate. No content/approval writes.
        return preg_replace_callback('~<section class="rrfr-week-card" data-rrfr-match="([0-9]+)" data-rrfr-evidence="([a-f0-9]{64})">(.*?)</section>~s',static function($m){
            $url=self::publicUrl((int)$m[1],$m[2]);if(!$url)return $m[0];
            return str_replace('</section>','<a class="rrfr-match-story-link" href="'.esc_url($url).'">Les kampreferatet<span class="rrfr-sr-only"> for '.esc_html(strip_tags(preg_match('~<h2[^>]*>(.*?)</h2>~s',$m[3],$title)?$title[1]:'denne kampen')).'</span> →</a></section>',$m[0]);
        },$html);
    }
    public static function invalidate(int $id,$post,bool $update,$before): void {
        if(!get_post_meta($id,'_rrfr_ai_match',true)||($post->post_status!=='publish'&&(!$before||$before->post_status!=='publish')))return;
        // Card links depend on child publication/withdrawal. Flush the installed page cache too.
        $parent=(int)get_post_meta($id,'_rrfr_weekly_parent',true);
        if($parent)clean_post_cache($parent);
        if(function_exists('wpo_cache_flush'))wpo_cache_flush();
    }
    public static function panel(int $id): void {
        $facts=get_post_meta($id,'_rrfr_fact_snapshot',true);
        if(!is_array($facts)||($facts['kind']??'')!=='results'||empty($facts['week']))return;
        echo '<section class="rrfr-card rrfr-match-stories"><h3>Korte kampartikler</h3><p>Eksisterende artikler beholdes. Nye referater klargjøres ett om gangen med kildekontroll og språkvask. Hver artikkel godkjennes separat.</p><ul class="rrfr-review-list">';
        foreach($facts['matches'] as $m){
            echo '<li><strong>'.esc_html(($m['home']['registered_name']??$m['home']['name']).' – '.($m['away']['registered_name']??$m['away']['name'])).'</strong>';
            try {
                $child=self::existing((int)$m['id']);
                if($child)echo '<a href="'.esc_url(PlayerReview::url($child)).'">Åpne kampartikkel →</a>';
                elseif(current_user_can('publish_posts'))echo '<button type="button" data-rrfr-create-match="'.(int)$m['id'].'">Klargjør kort referat</button>';
            }catch(\Throwable $e){echo '<p>'.esc_html($e->getMessage()).'</p>';}
            echo '</li>';
        }
        echo '</ul><p id="rrfr-match-create-status" role="status" aria-live="polite"></p></section>';
        if(get_post($id)->post_status==='draft'&&current_user_can('publish_posts'))echo '<button type="button" id="rrfr-apply-week-template">Bruk fast mal på dette utkastet</button><p>Beholder artikkeltekst og hovedbilde. Kampkortene bygges fra det lagrede faktagrunnlaget. Ny kontroll kreves etterpå.</p>';
        $config=wp_json_encode(['url'=>rest_url('rr-fotballrobot/v1/weekly-match'),'nonce'=>wp_create_nonce('wp_rest'),'parent'=>$id,'token'=>self::token($id)],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
        echo '<script>document.querySelectorAll("[data-rrfr-create-match]").forEach(b=>b.addEventListener("click",async()=>{const c='.$config.';const buttons=document.querySelectorAll("[data-rrfr-create-match]");buttons.forEach(x=>x.disabled=true);const s=document.getElementById("rrfr-match-create-status");s.textContent="Klargjør referat med kilde- og språkkontroll …";try{const r=await fetch(c.url,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","X-WP-Nonce":c.nonce},body:JSON.stringify({parent:c.parent,match:Number(b.dataset.rrfrCreateMatch),token:c.token})});const d=await r.json();if(r.ok){window.location.reload();return}s.textContent=d.message||"Klargjøring stoppet. Last siden på nytt og kontroller status."}catch(e){s.textContent="Uavklart svar. Last siden på nytt før et nytt forsøk."}}));</script>';
        $template=wp_json_encode(['url'=>rest_url('rr-fotballrobot/v1/weekly-template'),'nonce'=>wp_create_nonce('wp_rest'),'parent'=>$id,'token'=>self::token($id)],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
        echo '<script>document.getElementById("rrfr-apply-week-template")?.addEventListener("click",async function(){this.disabled=true;const c='.$template.';const s=document.getElementById("rrfr-match-create-status");try{const r=await fetch(c.url,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","X-WP-Nonce":c.nonce},body:JSON.stringify({parent:c.parent,token:c.token})});const d=await r.json();if(r.ok){window.location.reload();return}s.textContent=d.message||"Ingen endring bekreftet."}catch(e){s.textContent="Uavklart svar. Last siden på nytt og kontroller utkastet."}});</script>';
    }
    public static function routes(): void {
        register_rest_route('rr-fotballrobot/v1','/weekly-template',['methods'=>'POST','permission_callback'=>[Robot::class,'allowed'],
            'callback'=>static fn($r)=>Robot::response(static function()use($r){
                return ['id'=>self::template((int)$r->get_param('parent'),(string)$r->get_param('token'))];
            })]);
        register_rest_route('rr-fotballrobot/v1','/weekly-match',['methods'=>'POST','permission_callback'=>[Robot::class,'allowed'],
            'callback'=>static fn($r)=>Robot::response(static function()use($r){
                $id=self::prepare((int)$r->get_param('parent'),(int)$r->get_param('match'),(string)$r->get_param('token'));
                return ['id'=>$id,'status'=>get_post($id)->post_status,'review_url'=>PlayerReview::url($id)];
            })]);
    }
}
