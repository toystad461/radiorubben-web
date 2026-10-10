<?php
namespace RadioRubben\Fotballrobot;

/** Mutable working facts; article snapshots remain immutable in article metadata. */
final class FactStore {
    public static function fingerprint(array $p): string {
        unset($p['created_at'],$p['fact_hash']);
        foreach($p['sources']??[] as $i=>$source)unset($p['sources'][$i]['fetched_at']);
        return hash('sha256',wp_json_encode($p));
    }
    public static function valid(int $post,int $match): bool {
        $p=get_post($post);$data=get_post_meta($post,'_rrfr_payload',true);
        return $p&&$p->post_type==='rr_robot_fact'&&$p->post_status==='private'
            &&(int)get_post_meta($post,'_rrfr_match_id',true)===$match&&is_array($data)&&(int)($data['match']['id']??0)===$match&&!empty($data['fact_hash']);
    }
    public static function save(int $id,array $payload): array {
        $lock='rrfr_fact_store_lock_'.$id;
        if(!add_option($lock,time(),'',false))throw new \RuntimeException('Kampgrunnlaget oppdateres allerede. Prøv igjen om litt.');
        try {
            wp_cache_delete('rrfr_fact_'.$id,'options');
            $post=(int)get_option('rrfr_fact_'.$id,0);
            $valid=$post&&self::valid($post,$id);
            if($valid){
                $old=get_post_meta($post,'_rrfr_payload',true);
                // Keep the hash stable while a writer/reviewer is using unchanged facts.
                if(hash_equals(self::fingerprint($old),self::fingerprint($payload)))return $old;
            }
            $payload['fact_hash']=self::fingerprint($payload);
            $title=$payload['match']['home']['name'].' – '.$payload['match']['away']['name'];
            if($valid) {
                update_post_meta($post,'_rrfr_payload',wp_slash($payload));
                if(get_post_meta($post,'_rrfr_payload',true)!==$payload)throw new \RuntimeException('Kunne ikke oppdatere faktagrunnlaget.');
                $result=wp_update_post(['ID'=>$post,'post_title'=>$title],true);
                if(is_wp_error($result))throw new \RuntimeException('Kunne ikke oppdatere kampoversikten.');
            } else {
                $post=wp_insert_post(wp_slash(['post_type'=>'rr_robot_fact','post_status'=>'private','post_title'=>$title,'meta_input'=>['_rrfr_payload'=>$payload,'_rrfr_match_id'=>$id]]),true);
                if(is_wp_error($post)||!$post)throw new \RuntimeException('Kunne ikke lagre faktagrunnlaget.');
                update_option('rrfr_fact_'.$id,$post,false);
                if((int)get_option('rrfr_fact_'.$id)!==(int)$post)throw new \RuntimeException('Kunne ikke koble kampgrunnlaget til kampen.');
            }
            return $payload;
        } finally {delete_option($lock);}
    }
    public static function cleanup(): int {
        // wp_trash_post permanently deletes when EMPTY_TRASH_DAYS is zero.
        if(!defined('EMPTY_TRASH_DAYS')||EMPTY_TRASH_DAYS<1)throw new \RuntimeException('Papirkurven må være aktiv før eldre kopier kan ryddes.');
        $count=0;
        foreach(get_posts(['post_type'=>'rr_robot_fact','post_status'=>'private','numberposts'=>-1]) as $p) {
            $id=(int)get_post_meta($p->ID,'_rrfr_match_id',true);
            $keep=(int)get_option('rrfr_fact_'.$id,0);
            if(!$id||$keep===$p->ID||!self::valid($keep,$id)||!self::valid($p->ID,$id)||MatchJobs::active($id))continue;
            $lock='rrfr_fact_store_lock_'.$id;
            if(!add_option($lock,time(),'',false))continue;
            try {
                wp_cache_delete('rrfr_fact_'.$id,'options');
                $keep=(int)get_option('rrfr_fact_'.$id,0);
                if($keep===$p->ID||!self::valid($keep,$id))continue;
                if(!wp_trash_post($p->ID))throw new \RuntimeException('Kunne ikke flytte en gammel kopi til papirkurven.');
                $count++;
            } finally {delete_option($lock);}
        }
        return $count;
    }
    public static function cleanupAction(): void {
        if(!Robot::allowed())wp_die('Ingen tilgang.',403);
        check_admin_referer('rrfr_fact_cleanup');
        try{$count=self::cleanup();$message=$count.' eldre mellomkopier er flyttet til papirkurven. Artikkelfakta og kamparkiv er beholdt.';}
        catch(\Throwable $e){$message=$e->getMessage();}
        set_transient('rrfr_notice_'.get_current_user_id(),$message,120);
        wp_safe_redirect(admin_url('admin.php?page=rr-fotballrobot&match_id='.absint($_POST['match_id']??0).'#arkiv'));exit;
    }
    public static function archive(int $selected): void {
        echo '<section id="arkiv" class="rrfr-card"><h2>Kamper</h2><p>Ett oppdatert kampgrunnlag per kamp. Referatene beholder en egen kopi av faktaene de ble skrevet fra.</p><ul>';
        $seen=[];
        foreach(get_posts(['post_type'=>'rr_robot_fact','post_status'=>'private','numberposts'=>-1,'orderby'=>'modified','order'=>'DESC']) as $p){
            $id=(int)get_post_meta($p->ID,'_rrfr_match_id',true);
            if(!$id||isset($seen[$id]))continue;
            $keep=(int)get_option('rrfr_fact_'.$id,0);if(!self::valid($keep,$id))continue;
            $seen[$id]=true;$f=get_post_meta($keep,'_rrfr_payload',true);
            echo '<li><a href="'.esc_url(admin_url('admin.php?page=rr-fotballrobot&match_id='.$id)).'">'.esc_html($f['match']['home']['name'].' – '.$f['match']['away']['name']).'</a>';
            $article=MatchJobs::existing($id);
            if($article&&$article->post_status!=='trash')echo ' · <a href="'.esc_url(get_edit_post_link($article->ID,'raw')).'">Åpne referat</a>';
            echo '</li>';
        }
        echo '</ul><details><summary>Rydd eldre mellomlagringer</summary><p>Beholder gjeldende kampgrunnlag, kamparkiv og faktakopiene i artiklene. Eldre mellomkopier flyttes til WordPress-papirkurven og følger nettstedets vanlige oppbevaringstid.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('rrfr_fact_cleanup');echo '<input type="hidden" name="action" value="rrfr_fact_cleanup"><input type="hidden" name="match_id" value="'.esc_attr($selected).'"><button>Rydd eldre mellomkopier</button></form></details></section>';
    }
}
add_action('admin_post_rrfr_fact_cleanup',[FactStore::class,'cleanupAction']);
