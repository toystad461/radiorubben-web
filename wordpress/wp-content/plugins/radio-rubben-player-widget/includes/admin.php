<?php
namespace RadioRubben\PlayerWidget;

final class Admin {
    public static function menu(): void { add_options_page('Spillerkamper','Radio Rubben – spillerkamper','manage_options','rr-player-widget',[self::class,'page']); }
    private static function guard(): void { if (!current_user_can('manage_options')) wp_die('Ingen tilgang.'); }
    public static function save(): void {
        self::guard(); check_admin_referer('rrpw_save');
        try { Service::save(wp_unslash($_POST)); $message = 'Lagvalg lagret. Kampene hentes i bakgrunnen.'; }
        catch (\Throwable $e) { $message = $e->getMessage(); }
        self::back($message);
    }
    public static function refresh(): void {
        self::guard(); check_admin_referer('rrpw_refresh');
        if (!get_transient('rrpw_manual_refresh')) {
            set_transient('rrpw_manual_refresh',true,60);
            // Recover a lock left by a terminated PHP process, using compare-and-delete.
            $lock = get_option('rrpw_refresh_lock',[]);
            if (!empty($lock['at']) && $lock['at'] < time()-300) {
                global $wpdb;
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", 'rrpw_refresh_lock', maybe_serialize($lock)));
                wp_cache_delete('rrpw_refresh_lock','options');
            }
            Service::tick();
        }
        self::back('Oppdateringsrunde utført. Flere lag behandles i de neste bakgrunnskjøringene.');
    }
    private static function back(string $message): void {
        set_transient('rrpw_notice_'.get_current_user_id(),$message,60);
        wp_safe_redirect(admin_url('options-general.php?page=rr-player-widget')); exit;
    }
    public static function page(): void {
        self::guard(); $s = Service::settings(); $profiles = Service::profiles(); $cache = Service::cache();
        $notice = get_transient('rrpw_notice_'.get_current_user_id()); delete_transient('rrpw_notice_'.get_current_user_id());
        echo '<div class="wrap"><h1>Radio Rubben – spillerkamper</h1>';
        if ($notice) echo '<div class="notice notice-info"><p>'.esc_html($notice).'</p></div>';
        echo '<p>Velg spillerne som skal vises offentlig, og lagene vi skal hente kamper for. Lagene kontrolleres mot spillerens registrerte klubb. Tidligere klubber og private notater vises ikke.</p>';
        if (!$profiles) echo '<div class="notice notice-warning"><p>Ingen spillerprofiler funnet. Legg dem til under Fotballroboten → Spillere jeg følger.</p></div>';
        echo '<form action="'.esc_url(admin_url('admin-post.php')).'" method="post"><input type="hidden" name="action" value="rrpw_save"><input type="hidden" name="revision" value="'.(int)$s['revision'].'">';
        wp_nonce_field('rrpw_save');
        echo '<p><label><input type="checkbox" name="enabled" value="1" '.checked($s['enabled'],true,false).'> Aktiver kampoversikten og bakgrunnsoppdatering</label></p>';
        foreach ($profiles as $id=>$p) {
            $configured = $s['players'][$id] ?? null;
            $teams = $configured['teams'] ?? array_keys($p['teams']);
            echo '<fieldset style="max-width:760px;background:#fff;border:1px solid #ccd0d4;padding:16px;margin:16px 0"><legend><strong>'.esc_html($p['name']).'</strong></legend><p><label><input type="checkbox" name="players['.(int)$id.'][show]" value="1" '.checked((bool)$configured,true,false).' '.disabled(!$p['enabled'],true,false).'> Vis i kampwidgeten'.(!$p['enabled']?' (spilleroppfølging er pauset)':'').'</label></p>';
            echo '<p><label>Lag-ID-er, atskilt med komma<br><input class="large-text" name="players['.(int)$id.'][teams]" value="'.esc_attr(implode(', ',$teams)).'" aria-describedby="rrpw-hint-'.(int)$id.'"></label></p>';
            $hints = []; foreach ($p['teams'] as $tid=>$name) $hints[] = $name.' ('.$tid.')';
            echo '<p class="description" id="rrpw-hint-'.(int)$id.'">Fra årets spillerstatistikk: '.esc_html(implode(', ',$hints) ?: 'Ingen lag registrert ennå.').'. Du kan legge til andre lag i samme klubb.</p>';
            foreach ($teams as $tid) if (isset($cache['teams'][$tid])) {
                $t = $cache['teams'][$tid];
                $problem = $t['error'] ?? ((!in_array($t['club_id']??0,$p['clubs'],true)) ? 'Laget tilhører ikke spillerens registrerte klubb og vises ikke.' : null);
                echo '<p><strong>Lag '.(int)$tid.':</strong> '.esc_html($problem ?: (($t['name']??'').' · '.count($t['matches']??[]).' planlagte kamper funnet')).'</p>';
            }
            echo '</fieldset>';
        }
        submit_button('Lagre spiller- og lagvalg'); echo '</form>';
        echo '<h2>Bruk widgeten</h2><p>Sett inn en Kortkode-blokk på ønsket side: <code>[rr_spillerkamper]</code>. Store kampkort: <code>[rr_spillerkamper layout="cards" limit="3"]</code>. Standardvisningen er en kompakt spillerrekke. Én spiller: <code>[rr_spillerkamper player="3942773"]</code>. Den finnes også som «Radio Rubben – spillerkamper» under Utseende → Widgeter.</p>';
        echo '<h2>Oppdatering</h2><p>Siste kjøring: '.esc_html(!empty($cache['last_run']) ? wp_date('d.m.Y H:i',$cache['last_run']) : 'Ikke kjørt').'. Hver runde henter ett lag og inntil to kamper. WP-Cron er avhengig av trafikk eller en serverstyrt cronjobb.</p>';
        if (get_option('rrpw_refresh_lock')) echo '<p>En oppdatering kjører eller ble avbrutt. «Oppdater neste runde» frigjør en foreldet lås etter fem minutter.</p>';
        echo '<form action="'.esc_url(admin_url('admin-post.php')).'" method="post"><input type="hidden" name="action" value="rrpw_refresh">';wp_nonce_field('rrpw_refresh');submit_button('Oppdater neste runde','secondary');echo '</form>';
        foreach ($cache['matches'] ?? [] as $id=>$r) foreach ($r['errors'] ?? [] as $error) echo '<p>Kamp '.(int)$id.': '.esc_html($error).'</p>';
        echo '<h2>Forhåndsvisning</h2><p>Dette er de samme kontrollerte kampkortene som vises offentlig når kortkoden legges inn.</p>';
        echo View::shortcode(['limit'=>3]);
        echo '</div>';
    }
}
