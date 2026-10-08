<?php
namespace RadioRubben\Fotballrobot;
require_once __DIR__.'/editorial-quality.php';

/** Publication boundary for robot posts. Quality approval never replaces human approval. */
final class PublicationGate {
    const META='_rrfr_quality_review';
    const AI_POLICY_VERSION='1.0.0';
    public static function hash($post): string {
        $p=(array)$post;
        return EditorialQuality::hash(array_map(static fn($k)=>(string)($p[$k]??''),['post_title','post_content','post_excerpt']));
    }
    public static function bind(array $review,array $post): array {
        $review['aiPolicyVersion']=self::AI_POLICY_VERSION;
        $review['postHash']=self::hash($post);
        return $review;
    }
    public static function managed(int $id,array $incoming=[]): bool {
        foreach(['_rrfr_ai_match','_rrfr_trial_match','_rrfr_player_review','_rrfr_club_key'] as $key)
            if(!empty($incoming[$key])||($id&&get_post_meta($id,$key,true))) return true;
        return false;
    }
    public static function facts(int $id): array {
        $player=get_post_meta($id,'_rrfr_player_review',true);
        if(is_array($player)&&isset($player['facts'])) return $player['facts'];
        $snapshot=get_post_meta($id,'_rrfr_fact_snapshot',true);
        if(get_post_meta($id,'_rrfr_club_key',true)) {
            if(!is_array($snapshot)||(!isset($snapshot['match']['id'])&&!isset($snapshot['week']['key']))) throw new \RuntimeException('Klubbens faktagrunnlag mangler.');
            return $snapshot;
        }
        if(!is_array($snapshot)||empty($snapshot['match']['id'])) throw new \RuntimeException('Faktagrunnlaget mangler.');
        $facts=Robot::latest((int)$snapshot['match']['id']);
        $facts['report_extras']=Report::extras((int)$facts['match']['id'],$facts['lineups']??[]);
        return $facts;
    }
    public static function current(int $id,$post): bool {
        $review=get_post_meta($id,self::META,true);
        if(!is_array($review)||($review['aiPolicyVersion']??'')!==self::AI_POLICY_VERSION
            ||($review['rulesVersion']??'')!==EditorialQuality::RULES_VERSION
            ||($review['publishable']??null)!==true||($review['languageStatus']??'')!=='completed'
            ||($review['findings']??null)!==[]||empty($review['postHash'])
            ||!hash_equals($review['postHash'],self::hash($post))) return false;
        try {return hash_equals($review['factsHash']??'',EditorialQuality::hash(EditorialQuality::packet(self::facts($id))));}
        catch(\Throwable $e) {return false;}
    }
    public static function canPublish(int $id,$post): bool {
        if(get_post_meta($id,'_rrfr_test_only',true)||!self::current($id,$post)) return false;
        $s=get_post_meta($id,'_rrfr_player_review',true);
        // The player's existing explicit approval action must have approved this exact revision.
        if(is_array($s)&&$s) return empty($s['test'])&&in_array($s['status']??'',['publishing','published'],true)
            &&hash_equals($s['hash']??'',PlayerReview::hash((object)$post));
        $decision=get_post_meta($id,'_rrfr_editor_decision',true);
        if(is_array($decision)&&($decision['status']??'')==='publishing')
            return hash_equals($decision['hash']??'',PlayerReview::hash((object)$post));
        // A background job with a passed model check has no human publishing authority.
        // Existing explicit review decisions above remain bound to the exact text.
        return current_user_can('publish_posts') && current_user_can('edit_post',$id);
    }
    public static function guard(array $data,array $postarr): array {
        $id=(int)($postarr['ID']??0);
        if(($data['post_type']??'post')!=='post'||!self::managed($id,$postarr['meta_input']??[])
            ||!in_array($data['post_status'],['publish','future','private'],true)) return $data;
        // wp_insert_post_data receives slashed database fields. Never trust incoming audit metadata.
        if(!$id||!self::canPublish($id,wp_unslash($data))) {
            $data['post_status']='draft';
            if($id) set_transient('rrfr_quality_notice_'.get_current_user_id(),'Publisering stoppet: teksten, faktagrunnlaget eller språkreglene trenger ny kontroll og godkjenning.',120);
        }
        return $data;
    }
    public static function future(int $id): void {
        $post=get_post($id);
        if($post&&$post->post_status==='future'&&self::managed($id)&&!self::canPublish($id,$post)) {
            // Core's priority-10 check_and_publish_future_post re-reads status and skips this draft.
            $result=wp_update_post(['ID'=>$id,'post_status'=>'draft'],true);
            if(is_wp_error($result)||get_post($id)->post_status!=='draft') throw new \RuntimeException('Kunne ikke stoppe planlagt publisering.');
        }
    }
    public static function notice(): void {
        $key='rrfr_quality_notice_'.get_current_user_id();$message=get_transient($key);
        if($message) {delete_transient($key);echo '<div class="notice notice-warning"><p>'.esc_html($message).'</p></div>';}
    }
    public static function box($post): void {
        if(self::managed((int)$post->ID)&&Robot::allowed()) add_meta_box('rrfr-quality','Fotballrobotens kvalitetskontroll',[self::class,'render'],'post','side');
    }
    public static function render($post): void {
        $r=get_post_meta($post->ID,self::META,true);$ok=self::current($post->ID,$post);
        echo '<p>'.esc_html($ok?'Kvalitetskontroll godkjent. Manuell sluttgodkjenning gjenstår.':'Publisering er sperret til teksten er kontrollert på nytt.').'</p>';
        echo '<p>Språkregler: '.esc_html($r['rulesVersion']??'Ikke kontrollert').'</p>';
        foreach($r['findings']??[] as $finding) echo '<p>'.esc_html($finding).'</p>';
        if(!empty($r['suggestion'])) {
            echo '<details><summary>Forslag fra språkvask</summary>';
            foreach(array_merge([$r['suggestion']['title'],$r['suggestion']['lead']],$r['suggestion']['paragraphs']) as $text) echo '<p>'.esc_html($text).'</p>';
            echo '</details>';
        }
        // No nested form in WordPress's post editor. POST only, explicit nonce and saved-text hash.
        $config=wp_json_encode(['url'=>rest_url('rr-fotballrobot/v1/quality/'.$post->ID),'nonce'=>wp_create_nonce('wp_rest'),'hash'=>self::hash($post)],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
        echo '<p>Lagre redigeringene som utkast først. Kontrollen leser bare lagret tekst og endrer ikke teksten.</p><button type="button" id="rrfr-quality-check" class="button">Kontroller lagret tekst</button><p id="rrfr-quality-status" role="status"></p>';
        echo '<script>document.getElementById("rrfr-quality-check").addEventListener("click",async function(){const c='.$config.';this.disabled=true;const s=document.getElementById("rrfr-quality-status");s.textContent="Kontrollerer språk og fakta …";try{const r=await fetch(c.url,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","X-WP-Nonce":c.nonce},body:JSON.stringify({hash:c.hash})});const d=await r.json();s.textContent=r.ok?d.message:(d.message||"Kontrollen feilet.")}catch(e){s.textContent="Kontrollen ble avbrutt. Last siden på nytt før nytt forsøk."}this.disabled=false});</script>';
    }
}
