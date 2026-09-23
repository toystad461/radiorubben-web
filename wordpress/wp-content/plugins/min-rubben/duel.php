<?php
if (!defined('ABSPATH')) { exit; }
final class RRM_Duel {
    public static function boot() {
        add_action('init', [__CLASS__, 'install']);
        add_shortcode('rubben_duell', [__CLASS__, 'render']);
        add_shortcode('rubben_engasjement', [__CLASS__, 'hub']);
        add_action('admin_menu', function() { add_submenu_page('edit.php?post_type=rrm_innsending', 'Låtduellen', 'Låtduellen', 'manage_options', 'rrm-duel', [__CLASS__, 'admin']); });
        add_action('admin_post_rrm_duel_save', [__CLASS__, 'save']);
        add_action('admin_post_rrm_vote', [__CLASS__, 'vote']);
        add_action('admin_post_nopriv_rrm_vote', [__CLASS__, 'vote']);
        add_action('template_redirect', function() {
            $p = get_queried_object();
            if ($p instanceof WP_Post && (has_shortcode($p->post_content, 'rubben_duell') || has_shortcode($p->post_content, 'rubben_engasjement'))) {
                if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE', true); } nocache_headers();
            }
        });
        add_action('delete_user', [__CLASS__, 'delete_votes']);
        add_filter('wp_privacy_personal_data_exporters', function($items) { $items['rubben-votes'] = ['exporter_friendly_name'=>'Låtduellen', 'callback'=>[__CLASS__, 'export']]; return $items; });
        add_filter('wp_privacy_personal_data_erasers', function($items) { $items['rubben-votes'] = ['eraser_friendly_name'=>'Låtduellen', 'callback'=>[__CLASS__, 'erase']]; return $items; });
    }
    public static function table() { global $wpdb; return $wpdb->prefix . 'rrm_votes'; }
    public static function install() {
        if (get_option('rrm_vote_schema') === '1') { return; }
        global $wpdb; require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table(); $collate = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            duel varchar(36) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            choice char(1) NOT NULL,
            label varchar(300) NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY one_vote (duel,user_id),
            KEY user_id (user_id)
        ) $collate;");
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table) { update_option('rrm_vote_schema', '1', false); }
    }
    public static function current() { return get_option('rrm_duel', []); }
    public static function open($d) { return !empty($d['id']) && !empty($d['open']) && (int)$d['end'] > time(); }
    public static function input($k) { return isset($_POST[$k]) && is_string($_POST[$k]) ? wp_unslash($_POST[$k]) : ''; }
    public static function guard($action) {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !wp_verify_nonce(self::input('_rrm_nonce'), $action)) { wp_die('Skjemaet er utløpt. Last siden på nytt.', '', ['response'=>403]); }
    }
    public static function counts($id) {
        global $wpdb; $table = self::table(); $counts = ['a'=>0,'b'=>0];
        foreach ($wpdb->get_results($wpdb->prepare("SELECT choice, COUNT(*) AS total FROM $table WHERE duel = %s GROUP BY choice", $id)) as $r) { if (isset($counts[$r->choice])) { $counts[$r->choice] = (int)$r->total; } }
        return $counts;
    }
    public static function choice($id, $uid) { global $wpdb; $table=self::table(); return $wpdb->get_var($wpdb->prepare("SELECT choice FROM $table WHERE duel=%s AND user_id=%d", $id, $uid)); }
    public static function vote() {
        if (!is_user_logged_in() || get_user_meta(get_current_user_id(), '_rrm_pending', true)) { wp_die('Logg inn med en bekreftet konto for å stemme.', '', ['response'=>401]); }
        self::guard('rrm_vote'); $d=self::current(); $choice=self::input('choice');
        if (!self::open($d) || self::input('duel') !== $d['id'] || !in_array($choice,['a','b'],true)) { wp_die('Denne avstemningen er stengt eller valget er ugyldig.', '', ['response'=>400]); }
        global $wpdb; $table=self::table();
        // The unique database key prevents double votes, including concurrent requests.
        $ok=$wpdb->query($wpdb->prepare("INSERT IGNORE INTO $table (duel,user_id,choice,label) VALUES (%s,%d,%s,%s)", $d['id'], get_current_user_id(), $choice, $d[$choice]));
        if ($ok === false) { wp_die('Stemmen kunne ikke lagres. Prøv igjen senere.', '', ['response'=>503]); }
        $back=wp_validate_redirect(self::input('return'), RRM_Min_Rubben::url());
        wp_safe_redirect($back . '#rrm-duel'); exit;
    }
    public static function hub() {
        $url=RRM_Min_Rubben::url();
        return '<section class="rrm rrm-hub"><p class="rrm-kicker">LOKAL · INKLUDERENDE · VERDIG · ENGASJERENDE</p><h2>Du er med på Rubben</h2><p>Din favoritt. Din hilsen. Vår musikkglede.</p><nav class="rrm-actions" aria-label="Bli med på Rubben"><a class="rrm-button" href="#rrm-duel">Stem på en låt</a><a class="rrm-button" href="'.esc_url(add_query_arg('rrm_kind','greeting',$url)).'">Send en hilsen</a><a class="rrm-button" href="'.esc_url(add_query_arg('rrm_kind','wish',$url)).'">Ønsk deg musikk</a></nav></section>' . self::render();
    }
    public static function render() {
        $d=self::current(); ob_start();
        echo '<section id="rrm-duel" class="rrm rrm-duel"><p class="rrm-kicker">DU BESTEMMER</p><h2>Låtduellen</h2>';
        if (!$d) { echo '<p>Neste låtduell kommer snart. Hvilken låt håper du blir med?</p></section>'; return ob_get_clean(); }
        $open=self::open($d); $mine=is_user_logged_in() ? self::choice($d['id'],get_current_user_id()) : null;
        echo '<p>'.($open ? 'Hvilken låt vil du høre? Én stemme per konto.' : 'Avstemningen er avsluttet.').'</p><p class="rrm-small">Frist: '.esc_html(wp_date('d.m.Y H:i',$d['end'])).' ('.esc_html(wp_timezone_string()).')</p>';
        if ($open && !$mine && is_user_logged_in() && !get_user_meta(get_current_user_id(),'_rrm_pending',true)) {
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="rrm_vote"><input type="hidden" name="duel" value="'.esc_attr($d['id']).'"><input type="hidden" name="return" value="'.esc_url(get_permalink() ?: RRM_Min_Rubben::url()).'">';
            wp_nonce_field('rrm_vote','_rrm_nonce'); echo '<div class="rrm-duel-grid">';
            foreach (['a'=>'A','b'=>'B'] as $k=>$letter) { echo '<button class="rrm-song" name="choice" value="'.$k.'" type="submit"><span>LÅT '.$letter.'</span><strong>'.esc_html($d[$k]).'</strong><span>Stem på denne →</span></button>'; }
            echo '</div></form>';
        } else {
            echo '<div class="rrm-duel-grid">'; foreach (['a','b'] as $k) { echo '<div class="rrm-card"><strong>'.esc_html($d[$k]).'</strong></div>'; } echo '</div>';
        }
        if ($mine || !$open) {
            $c=self::counts($d['id']); $total=array_sum($c);
            if ($mine) { echo '<p role="status">Din stemme: <strong>'.esc_html($d[$mine]).'</strong>. Takk for at du er med!</p>'; }
            foreach (['a','b'] as $k) { $pct=$total ? (int)round(100*$c[$k]/$total) : 0; echo '<p>'.esc_html($d[$k]).' · '.$c[$k].' stemmer ('.$pct.' %)</p><meter min="0" max="100" value="'.$pct.'" aria-label="'.esc_attr($d[$k]).'">'.$pct.' %</meter>'; }
            if (!$open) { echo '<p><strong>'.(!$total ? 'Ingen stemmer denne gangen.' : ($c['a']===$c['b'] ? 'Det ble uavgjort!' : 'Vinner: '.esc_html($d[$c['a']>$c['b']?'a':'b']))).'</strong></p>'; }
        } elseif (!is_user_logged_in()) { echo '<p><a class="rrm-button" href="'.esc_url(wp_login_url(get_permalink())).'">Logg inn for å stemme</a> <a href="'.esc_url(RRM_Min_Rubben::url()).'">Opprett leserkonto</a></p>'; }
        elseif (get_user_meta(get_current_user_id(),'_rrm_pending',true)) { echo '<p>Bekreft kontoen via passordlenken i e-posten før du stemmer.</p>'; }
        echo '<p class="rrm-small">Resultatet vises etter at du har stemt, eller når duellen er avsluttet. Radio Rubben velger når vinnerlåten spilles.</p></section>';
        return ob_get_clean();
    }
    public static function admin() {
        if (!current_user_can('manage_options')) { return; } $d=self::current();
        echo '<div class="wrap"><h1>Låtduellen</h1><p>Legg <code>[rubben_engasjement]</code> på forsiden, eller bruk <code>[rubben_duell]</code> for bare avstemningen.</p>';
        if ($d) { $c=self::counts($d['id']); echo '<h2>Nåværende duell</h2><p>'.esc_html($d['a']).': '.$c['a'].' stemmer<br>'.esc_html($d['b']).': '.$c['b'].' stemmer</p><p>'.(self::open($d)?'Åpen':'Avsluttet').'</p>'; }
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="rrm_duel_save">'; wp_nonce_field('rrm_duel_save','_rrm_nonce');
        echo '<h2>Start ny duell</h2><p>Begge låtene låses ved opprettelse. En ny duell erstatter den som vises. Gamle stemmer beholdes privat, men teller aldri i den nye duellen.</p><p><label>Låt A – artist og tittel<br><input class="large-text" name="a" maxlength="240"></label></p><p><label>Låt B – artist og tittel<br><input class="large-text" name="b" maxlength="240"></label></p><p><label>Avsluttes ('.esc_html(wp_timezone_string()).')<br><input type="datetime-local" name="end"></label></p><button class="button button-primary" name="operation" value="new">Start ny duell</button> ';
        if (self::open($d)) { echo '<button class="button" name="operation" value="close">Avslutt nå</button>'; }
        echo '</form><p>Ingen automatisk avspilling. Én konto gir én stemme; dette hindrer ikke at en person oppretter flere kontoer. Unnta siden med duellen fra sidecache.</p></div>';
    }
    public static function save() {
        if (!current_user_can('manage_options')) { wp_die('Ingen tilgang.', '', ['response'=>403]); } self::guard('rrm_duel_save');
        if (self::input('operation')==='close') { $d=self::current(); if ($d) { $d['open']=0; update_option('rrm_duel',$d,false); } }
        elseif (self::input('operation')==='new') {
            $a=sanitize_text_field(self::input('a')); $b=sanitize_text_field(self::input('b'));
            $raw=self::input('end'); $end=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$raw,wp_timezone());
            if (!$a || !$b || mb_strlen($a)>240 || mb_strlen($b)>240 || mb_strtolower($a)===mb_strtolower($b) || !$end || $end->format('Y-m-d\TH:i')!==$raw || $end->getTimestamp()<=time()) { wp_die('Velg to ulike låter og en gyldig sluttdato i fremtiden.'); }
            update_option('rrm_duel',['id'=>wp_generate_uuid4(),'a'=>$a,'b'=>$b,'end'=>$end->getTimestamp(),'open'=>1],false);
        } else { wp_die('Ugyldig handling.'); }
        wp_safe_redirect(admin_url('edit.php?post_type=rrm_innsending&page=rrm-duel')); exit;
    }
    public static function delete_votes($uid) { global $wpdb; return $wpdb->delete(self::table(),['user_id'=>(int)$uid],['%d']); }
    public static function export($email,$page=1) {
        global $wpdb; $u=get_user_by('email',$email); if (!$u) { return ['data'=>[],'done'=>true]; } $table=self::table();
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE user_id=%d ORDER BY id LIMIT 100 OFFSET %d",$u->ID,(max(1,(int)$page)-1)*100)); $data=[];
        foreach ($rows as $r) { $data[]=['group_id'=>'rubben-votes','group_label'=>'Låtduellen','item_id'=>'vote-'.$r->id,'data'=>[['name'=>'Duell','value'=>$r->duel],['name'=>'Valgt låt','value'=>$r->label]]]; }
        return ['data'=>$data,'done'=>count($rows)<100];
    }
    public static function erase($email,$page=1) { $u=get_user_by('email',$email); $r=$u ? self::delete_votes($u->ID) : 0; return ['items_removed'=>$r>0,'items_retained'=>$r===false,'messages'=>$r===false?['Stemmene kunne ikke slettes.']:[],'done'=>true]; }
}
RRM_Duel::boot();
