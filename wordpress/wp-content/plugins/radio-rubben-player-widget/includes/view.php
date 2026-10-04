<?php
namespace RadioRubben\PlayerWidget;

final class View {
    public static function cachePolicy(): void {
        $post = get_queried_object();
        $home = is_front_page() && get_option('rrpw_homepage_enabled', false);
        $embedded = is_singular() && $post instanceof \WP_Post && has_shortcode($post->post_content, 'rr_spillerkamper');
        if (!$home && !$embedded) return;
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        nocache_headers();
        do_action('litespeed_control_set_nocache', 'Spillerkamper trenger fersk kamp- og troppsstatus.');
    }
    public static function homepage(): void {
        if (!get_option('rrpw_homepage_enabled', false) || !Service::settings()['enabled']) return;
        echo '<section class="rr-section rr-wrap rrpw-home" id="bomlo-spillere" aria-labelledby="rrpw-home-title"><div class="rr-section-head"><div><p class="rr-eyebrow">FOTBALL</p><h2 id="rrpw-home-title">Bømlo-spillere på banen</h2><p>Følg kampene til lokale spillere ute i klubbene.</p></div><a class="rr-btn-outline" href="'.esc_url(home_url('/sport/#bomlo-spillere')).'">Flere kamper →</a></div>';
        echo self::shortcode(['limit'=>2]);
        echo '</section>';
    }
    public static function assets(): void {
        wp_enqueue_style('rr-player-widget', plugins_url('assets/widget.css', FILE), [], VERSION);
        wp_enqueue_script('rr-player-widget', plugins_url('assets/widget.js', FILE), [], VERSION, true);
    }
    public static function shortcode($attrs = []): string {
        $a = shortcode_atts(['player'=>0,'limit'=>1], (array)$attrs, 'rr_spillerkamper');
        $cards = Service::cards(absint($a['player']), (int)$a['limit']);
        if (!$cards) return '<div class="rrpw-empty" role="status">Ingen bekreftede kommende kamper å vise akkurat nå.</div>';
        return '<div class="rrpw-list">'.implode('', array_map([self::class,'card'], $cards)).'</div>';
    }
    public static function card(array $c): string {
        $m = $c['match']; $start = strtotime($m['kickoff']); $now = time(); $zone = new \DateTimeZone('Europe/Oslo');
        $today = wp_date('Y-m-d',$start,$zone) === wp_date('Y-m-d',$now,$zone);
        $heading = $today ? 'På banen i dag' : 'Neste kamp';
        $status = $start > $now ? 'Kommende' : 'Kampstart passert';
        $id = wp_unique_id('rrpw-');
        ob_start(); ?>
        <article class="rrpw" aria-labelledby="<?php echo esc_attr($id); ?>" data-start="<?php echo (int)$start; ?>" data-card-expires="<?php echo (int)min($start+3*3600,$c['team_checked_at']+Service::TEAM_FRESH); ?>">
          <header class="rrpw-brand"><img src="<?php echo esc_url(plugins_url('assets/radio-rubben.svg',FILE)); ?>" alt="Radio Rubben" width="104" height="34"><span>Bømlo-spillere ute</span></header>
          <div class="rrpw-main">
            <div class="rrpw-top"><h2><?php echo esc_html($heading); ?></h2><span class="rrpw-badge"><?php echo esc_html($status); ?></span></div>
            <p class="rrpw-date"><?php echo esc_html(wp_date('l j. F Y',$start,$zone)); ?></p>
            <div class="rrpw-match" id="<?php echo esc_attr($id); ?>">
              <?php foreach (['home','away'] as $side): if ($side === 'away'): ?>
                <div class="rrpw-kickoff"><strong><?php echo esc_html(wp_date('H:i',$start,$zone)); ?></strong><span>Kampstart</span></div>
              <?php endif; ?>
                <div class="rrpw-team"><div class="rrpw-crest">
                  <?php if (!empty($c['stream']['logos'][$side])): ?><img src="<?php echo esc_url($c['stream']['logos'][$side]); ?>" alt="" width="52" height="52" loading="lazy" referrerpolicy="no-referrer"><?php endif; ?>
                  <span class="rrpw-initial" <?php echo !empty($c['stream']['logos'][$side]) ? 'hidden' : ''; ?> aria-hidden="true"><?php echo esc_html(mb_substr($m[$side]['name'],0,1)); ?></span>
                </div><strong aria-label="<?php echo esc_attr($m[$side]['name']); ?>"><?php echo esc_html(preg_replace('/ Fotballklubb$/u','',$m[$side]['name'])); ?></strong></div>
              <?php endforeach; ?>
            </div>
            <p class="rrpw-venue"><?php echo esc_html($m['venue']); ?></p>
            <p class="rrpw-league"><?php echo esc_html($m['competition']); ?></p>
            <div class="rrpw-actions">
              <?php if (!empty($c['stream']['url'])): ?>
                <a class="rrpw-watch" data-expires="<?php echo (int)$c['expires']; ?>" href="<?php echo esc_url($c['stream']['url']); ?>" target="_blank" rel="noopener noreferrer"><span aria-hidden="true">▶</span> Se på TV 2 Play <span aria-hidden="true">↗</span></a>
                <p class="rrpw-subscription">MyGame-abonnement kreves</p>
              <?php else: ?><p class="rrpw-unconfirmed">Sending ikke bekreftet</p><?php endif; ?>
            </div>
            <div class="rrpw-players">
              <?php foreach ($c['players'] as $p): ?>
                <div class="rrpw-follow"><span class="rrpw-avatar" aria-hidden="true"><?php echo esc_html(mb_substr($p['name'],0,1)); ?></span><div><span class="rrpw-follow-label">Spiller vi følger</span><p><?php echo esc_html($p['name']); ?></p><span class="rrpw-player-state" data-lineup-expires="<?php echo (int)$c['lineup_expires']; ?>"><?php echo esc_html(['starter'=>'I startoppstillingen','bench'=>'Oppført som innbytter'][$p['role'] ?? ''] ?? 'Tropp ikke bekreftet'); ?></span></div></div>
              <?php endforeach; ?>
            </div>
            <details><summary>Kampdetaljer og kilder <span aria-hidden="true">⌄</span></summary><div class="rrpw-details"><a href="<?php echo esc_url(Sources::url('match',$m['id'])); ?>" target="_blank" rel="noopener noreferrer">Kamp og lagoppstilling ↗</a><?php if ($c['stream']): ?><a href="<?php echo esc_url($c['stream']['source']); ?>" target="_blank" rel="noopener noreferrer">Kampen hos MyGame ↗</a><?php endif; ?></div></details>
          </div>
        </article>
        <?php return (string)ob_get_clean();
    }
}
