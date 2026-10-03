<?php
namespace RadioRubben\Fotballrobot;

/** Explicit editorial examples, never model training or automatic publication. */
final class Learning {
    const KEY='_rrfr_learning';
    const SCOPES=['general'=>'Alle referater','substitute_goal'=>'Innbytter som scorer'];
    public static function allowed(int $id=0): bool {
        return Robot::allowed() && (!$id || current_user_can('edit_post',$id));
    }
    public static function article(int $id) {
        $p=get_post($id);
        if(!$p||$p->post_type!=='post'||!in_array($p->post_status,['draft','pending','publish','private','future'],true)||(int)get_post_meta($id,'_rrfr_ai_match',true)<1)throw new \RuntimeException('Velg et lagret referat fra Fotballroboten.');
        return $p;
    }
    public static function plain(string $s): string {
        return trim(preg_replace('/\s+/u',' ',html_entity_decode(wp_strip_all_tags(strip_shortcodes($s)),ENT_QUOTES|ENT_HTML5,'UTF-8')));
    }
    public static function snapshot($p): array {
        $parts=[];
        $walk=static function(array $blocks)use(&$walk,&$parts) {
            foreach($blocks as $b) {
                if(!empty($b['innerBlocks'])){$walk($b['innerBlocks']);continue;}
                $html=$b['innerHTML']??'';
                // Only prose paragraphs/headings. No dynamic blocks, notice, source, roster, embeds or notes.
                if(!in_array($b['blockName']??'', ['core/paragraph','core/heading'],true))continue;
                if(strpos($html,'rrfr-editorial-notice')!==false||preg_match('~<small[^>]*>\s*Kilde(?:r)?:|<strong[^>]*>[^<]+:</strong>~u',$html))continue;
                $text=self::plain($html);if($text!=='')$parts[]=$text;
            }
        };
        $walk(parse_blocks($p->post_content));
        return ['title'=>self::plain($p->post_title),'paragraphs'=>$parts];
    }
    public static function hash($p): string {return hash('sha256',$p->post_title."\n".$p->post_content);}
    public static function version(array $lesson): string {return hash('sha256',wp_json_encode($lesson));}
    public static function lesson(int $id): array {$v=get_post_meta($id,self::KEY,true);return is_array($v)?$v:[];}
    public static function save(int $id,string $hash,string $version,string $note,string $scope,bool $approved): array {
        if(!self::allowed($id))throw new \RuntimeException('Ingen tilgang.');
        $p=self::article($id);$old=self::lesson($id);
        if(!$hash||!hash_equals(self::hash($p),$hash)||!hash_equals(self::version($old),$version))throw new \RuntimeException('Artikkelen eller læringen er endret. Last læringssiden på nytt og kontroller teksten før lagring.');
        $note=sanitize_textarea_field($note);
        if(!$approved)throw new \RuntimeException('Bekreft at teksten og lærdommen er gjennomlest.');
        if(!isset(self::SCOPES[$scope])||mb_strlen($note)<15||mb_strlen($note)>1000)throw new \RuntimeException('Velg bruksområde og beskriv lærdommen med 15–1000 tegn.');
        $after=self::snapshot($p);
        if(!$after['title']||!$after['paragraphs']||mb_strlen(wp_json_encode($after))>12000)throw new \RuntimeException('Eksemplet trenger tittel og vanlig avsnittstekst, med høyst 12 000 tegn.');
        $baseline=get_post_meta($id,'_rrfr_original_article',true);
        $before=is_array($baseline)&&isset($baseline['title'],$baseline['paragraphs'])?$baseline:null;
        $entry=['active'=>true,'note'=>$note,'scope'=>$scope,'before'=>$before,'after'=>$after,'article_hash'=>$hash,'saved_at'=>gmdate(DATE_ATOM),'saved_by'=>get_current_user_id(),'revision'=>(int)($old['revision']??0)+1];
        if($old&&$old['active']===true&&$old['article_hash']===$hash&&$old['note']===$note&&$old['scope']===$scope)return $old;
        update_post_meta($id,self::KEY,wp_slash($entry));
        if(self::lesson($id)!==$entry)throw new \RuntimeException('Læringen kunne ikke lagres. Prøv igjen.');
        return $entry;
    }
    public static function disable(int $id,string $version): void {
        if(!self::allowed($id))throw new \RuntimeException('Ingen tilgang.');
        self::article($id);$entry=self::lesson($id);
        if(!$entry||!hash_equals(self::version($entry),$version))throw new \RuntimeException('Læringen er endret. Last siden på nytt.');
        $entry['active']=false;$entry['saved_at']=gmdate(DATE_ATOM);$entry['saved_by']=get_current_user_id();$entry['revision']++;
        update_post_meta($id,self::KEY,wp_slash($entry));
        if(self::lesson($id)!==$entry)throw new \RuntimeException('Kunne ikke deaktivere eksemplet.');
    }
    public static function has_substitute_goal(array $facts): bool {
        foreach($facts['report_extras']['manual_substitutions']??[] as $s)foreach($facts['match']['events']??[] as $e) {
            if(in_array($e['type'],['Spillemål','Straffemål'],true)&&$e['side']===$s['side']&&self::plain($e['name'])===self::plain($s['in'])&&(int)$e['minute']*60>$s['seconds'])return true;
        }
        return false;
    }
    public static function excerpt(?array $s): ?array {
        if(!$s)return null;
        return ['title'=>mb_substr(self::plain($s['title']??''),0,160),'paragraphs'=>array_map(static fn($p)=>mb_substr(self::plain($p),0,1200),array_slice($s['paragraphs']??[],0,3))];
    }
    public static function context(array $facts): array {
        $result=[];$special=self::has_substitute_goal($facts);
        foreach(get_posts(['post_type'=>'post','post_status'=>['draft','pending','publish','private','future'],'numberposts'=>-1,'meta_key'=>self::KEY]) as $p) {
            $entry=self::lesson($p->ID);
            if(!self::allowed($p->ID)||empty($entry['active'])||!isset($entry['article_hash'])||!hash_equals($entry['article_hash'],self::hash($p))||(int)get_post_meta($p->ID,'_rrfr_ai_match',true)<1)continue;
            if(!isset(self::SCOPES[$entry['scope']??''])||($entry['scope']==='substitute_goal'&&!$special))continue;
            $result[]=['post_id'=>$p->ID,'revision'=>$entry['revision'],'saved_at'=>$entry['saved_at'],'scope'=>$entry['scope'],'lesson'=>mb_substr($entry['note'],0,1000),'original'=>self::excerpt($entry['before']),'approved'=>self::excerpt($entry['after'])];
        }
        usort($result,static fn($a,$b)=>($a['scope']==='general')<=>($b['scope']==='general') ?: strcmp($b['saved_at'],$a['saved_at']) ?: $b['post_id']<=>$a['post_id']);
        return array_slice($result,0,3);
    }
    public static function url(int $id=0): string {return admin_url('admin.php?page=rrfr-learning'.($id?'&post_id='.$id:''));}
    public static function menu(): void {add_submenu_page('rr-fotballrobot','Lær av mine rettelser','Lær av mine rettelser','manage_options','rrfr-learning',[self::class,'page']);}
    public static function metabox($p): void {
        if(!self::allowed($p->ID)||(int)get_post_meta($p->ID,'_rrfr_ai_match',true)<1)return;
        add_meta_box('rrfr-learning','Lær av mine rettelser',static function($p){echo '<p>Lagre artikkelendringene først. Åpne deretter læringssiden for å kontrollere og godkjenne eksemplet.</p><p><a class="button" target="_blank" rel="noopener" href="'.esc_url(self::url($p->ID)).'">Lær av mine rettelser ↗</a></p>';},'post','side');
    }
    public static function action(): void {
        if(!self::allowed())wp_die('Ingen tilgang.');
        check_admin_referer('rrfr_learning');$id=absint($_POST['post_id']??0);
        try {
            if(($_POST['operation']??'')==='disable'){self::disable($id,(string)wp_unslash($_POST['version']??''));$msg='Eksemplet er deaktivert og brukes ikke i nye referater.';}
            elseif(($_POST['operation']??'')==='save'){self::save($id,(string)wp_unslash($_POST['article_hash']??''),(string)wp_unslash($_POST['version']??''),(string)wp_unslash($_POST['lesson']??''),(string)wp_unslash($_POST['scope']??''),($_POST['approved']??'')==='1');$msg='Læringen er lagret. Eksemplet kan nå brukes i nye referater.';}
            else throw new \RuntimeException('Ukjent handling.');
            set_transient('rrfr_learning_notice_'.get_current_user_id(),['ok'=>true,'text'=>$msg],120);
        }catch(\Throwable $e){set_transient('rrfr_learning_notice_'.get_current_user_id(),['ok'=>false,'text'=>$e->getMessage(),'note'=>sanitize_textarea_field(wp_unslash($_POST['lesson']??'')),'scope'=>sanitize_key($_POST['scope']??'')],120);}
        wp_safe_redirect(self::url($id));exit;
    }
    private static function display_snapshot(array $s): void {
        echo '<div class="rrfr-example"><h3>'.esc_html($s['title']).'</h3>';
        foreach($s['paragraphs'] as $p)echo '<p>'.esc_html($p).'</p>';
        echo '</div>';
    }
    private static function fields(int $id,array $entry,string $operation): void {
        wp_nonce_field('rrfr_learning');echo '<input type="hidden" name="action" value="rrfr_learning"><input type="hidden" name="operation" value="'.esc_attr($operation).'"><input type="hidden" name="post_id" value="'.esc_attr($id).'"><input type="hidden" name="version" value="'.esc_attr(self::version($entry)).'">';
    }
    public static function page(): void {
        if(!self::allowed())wp_die('Ingen tilgang.');
        $id=absint($_GET['post_id']??0);$notice=get_transient('rrfr_learning_notice_'.get_current_user_id());if($notice)delete_transient('rrfr_learning_notice_'.get_current_user_id());
        echo '<div class="wrap rrfr"><header><p class="rrfr-eyebrow">RADIO RUBBEN · REDAKSJON</p><h1>Lær av mine rettelser</h1><p>Gjør gode rettelser til eksempler for neste kampreferat.</p></header><nav><a href="'.esc_url(admin_url('admin.php?page=rr-fotballrobot')).'">← Fotballroboten</a><a href="#eksempler">Lagrede eksempler</a></nav>';
        if($notice)echo '<div role="status" class="rrfr-notice">'.esc_html($notice['text']).'</div>';
        echo '<section class="rrfr-card"><h2>Slik bruker du læringen</h2><ol><li>Rett teksten i artikkeleditoren og lagre endringene.</li><li>Velg artikkelen her og beskriv hva roboten skal lære.</li><li>Kontroller tekstkopien og lagre som godkjent eksempel.</li></ol><p>Inntil tre relevante, aktive eksempler følger med når roboten skriver et nytt referat. Navn, resultat og hendelser hentes alltid fra den nye kampen. Lagrede eksempler trener ikke selve modellen og publiserer ingenting.</p><form method="get"><input type="hidden" name="page" value="rrfr-learning"><label for="rrfr-learn-post">Velg kampreferat</label><div class="rrfr-row"><select id="rrfr-learn-post" name="post_id" required><option value="">Velg artikkel</option>';
        foreach(get_posts(['post_type'=>'post','post_status'=>['draft','pending','publish','private','future'],'numberposts'=>100,'meta_key'=>'_rrfr_ai_match']) as $p)if(self::allowed($p->ID))echo '<option value="'.esc_attr($p->ID).'" '.selected($id,$p->ID,false).'>'.esc_html($p->post_title).' · #'.esc_html($p->ID).'</option>';
        echo '</select><button>Åpne læring</button></div></form></section>';
        if($id)try {
            if(!self::allowed($id))throw new \RuntimeException('Ingen tilgang til artikkelen.');
            $p=self::article($id);$entry=self::lesson($id);$current=self::snapshot($p);$original=get_post_meta($id,'_rrfr_original_article',true);
            echo '<section class="rrfr-card"><h2>Kontroller eksemplet</h2><p><a href="'.esc_url(get_edit_post_link($id,'raw')).'" target="_blank" rel="noopener">Rediger artikkelen ↗</a> · <a href="'.esc_url(self::url($id)).'">Hent siste lagrede tekst</a></p><p class="rrfr-muted">Kun lagret tekst vises. Ulagrede endringer i editoren er ikke med. AI-merknad, lagoppstillinger, kildefelt og kampkort utelates.</p>';
            if($entry)echo '<p class="rrfr-badge">'.esc_html(empty($entry['active'])?'Deaktivert':(hash_equals($entry['article_hash'],self::hash($p))?'Aktivt eksempel':'Artikkelen er endret – læringen må godkjennes på nytt')).'</p>';
            if(is_array($original)&&isset($original['title'],$original['paragraphs'])){echo '<details><summary>Robotens opprinnelige tekst</summary>';self::display_snapshot($original);echo '</details>';}
            else echo '<p class="rrfr-muted">Opprinnelig AI-tekst ble ikke bevart for dette eldre utkastet. Den godkjente teksten og forklaringen din kan likevel brukes som eksempel.</p>';
            echo '<h3>Din sist lagrede tekst</h3>';self::display_snapshot($current);
            if($entry){echo '<details><summary>Eksemplet som er lagret fra før</summary>';self::display_snapshot($entry['after']);echo '<p><strong>Lærdom:</strong> '.esc_html($entry['note']).'</p></details>';}
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';self::fields($id,$entry,'save');
            echo '<input type="hidden" name="article_hash" value="'.esc_attr(self::hash($p)).'"><p><label for="rrfr-learn-note"><strong>Hva skal roboten lære av rettelsene?</strong></label><textarea id="rrfr-learn-note" name="lesson" rows="5" required minlength="15" maxlength="1000" placeholder="Eksempel: Fremhev at målscoreren kom inn som innbytter når byttet er dokumentert.">'.esc_textarea(($notice&&!$notice['ok'])?$notice['note']:($entry['note']??'')).'</textarea></p><p><label for="rrfr-learn-scope">Bruk eksemplet ved</label> <select id="rrfr-learn-scope" name="scope">';
            foreach(self::SCOPES as $key=>$label)echo '<option value="'.esc_attr($key).'" '.selected(($notice&&!$notice['ok'])?$notice['scope']:($entry['scope']??'general'),$key,false).'>'.esc_html($label).'</option>';
            echo '</select></p><p class="rrfr-muted">Ved ny AI-skriving sendes forklaringen, tittel og de tre første tekstavsnittene (høyst 1200 tegn per avsnitt) fra godkjent tekst og eventuell original til den eksisterende OpenAI-tilkoblingen. Lagringen i seg selv starter ingen AI-kall.</p><p><label><input type="checkbox" name="approved" value="1" required> Jeg har gjennomlest teksten og lærdommen og godkjenner dem som eksempel.</label></p><button class="rrfr-primary">Lagre læring fra rettelsene</button></form>';
            if(!empty($entry['active'])){echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="rrfr-disable">';self::fields($id,$entry,'disable');echo '<button>Deaktiver eksemplet</button></form>';}
            echo '</section>';
        }catch(\Throwable $e){echo '<p role="alert" class="rrfr-notice">'.esc_html($e->getMessage()).'</p>';}
        echo '<section class="rrfr-card" id="eksempler"><h2>Lagrede eksempler</h2><p>Senere artikkelendringer settes ikke automatisk i bruk som læring. Godkjenn eksemplet på nytt etter en endring. Deaktiverte eksempler beholdes og kan godkjennes igjen.</p><ul>';
        $found=false;
        foreach(get_posts(['post_type'=>'post','post_status'=>['draft','pending','publish','private','future'],'numberposts'=>-1,'meta_key'=>self::KEY]) as $p){if(!self::allowed($p->ID))continue;$e=self::lesson($p->ID);if(!$e)continue;$found=true;$status=empty($e['active'])?'Deaktivert':(hash_equals($e['article_hash'],self::hash($p))?'Aktivt':'Må godkjennes på nytt');echo '<li><a href="'.esc_url(self::url($p->ID)).'">'.esc_html($p->post_title).'</a> · '.esc_html($status.' · '.(self::SCOPES[$e['scope']]??'')).'</li>';}
        echo '</ul>'.(!$found?'<p>Ingen eksempler lagret ennå.</p>':'').'</section></div>';
    }
}
add_action('admin_menu',[Learning::class,'menu'],11);
add_action('add_meta_boxes_post',[Learning::class,'metabox']);
add_action('admin_post_rrfr_learning',[Learning::class,'action']);
