<?php
namespace RadioRubben\PlayerWidget;

/** Approved followed players, sorted by their nearest verified team fixture. */
final class Compact {
    public static function render(int $filter = 0): string {
        $s = Service::settings(); if (!$s['enabled']) return '';
        $profiles = Service::selected($s,Service::profiles()); $cache = Service::cache(); $tiles = []; $rows=[];
        foreach ($profiles as $id=>$p) {
            if ($filter && $p['fiks_id'] !== $filter) continue;
            $valid = [];
            foreach ($p['selected_teams'] as $tid) {
                $team = $cache['teams'][$tid] ?? null;
                if (!$team || !empty($team['error']) || ($team['checked_at']??0) < time()-Service::TEAM_FRESH || !in_array($team['club_id'],$p['clubs'],true)) continue;
                $valid[$tid] = $team;
            }
            $card = Service::cards($p['fiks_id'],1)[0] ?? null;
            $rows[] = ['player'=>$p,'teams'=>$valid,'card'=>$card];
        }
        usort($rows,static fn($a,$b)=> (($a['card'] ? strtotime($a['card']['match']['kickoff']) : PHP_INT_MAX) <=> ($b['card'] ? strtotime($b['card']['match']['kickoff']) : PHP_INT_MAX)) ?: (strcmp($a['player']['name'],$b['player']['name'])) ?: ($a['player']['id']<=>$b['player']['id']));
        foreach ($rows as $row) $tiles[]=self::tile($row['player'],$row['teams'],$row['card']);
        if (!$tiles) return '';
        $id = wp_unique_id('rrpw-strip-');
        return '<details class="rrpw-compact" open><summary><span class="rrpw-compact-title">Bømlo-spillere ute</span><span class="rrpw-count">'.count($tiles).'</span><span class="rrpw-chevron" aria-hidden="true">⌄</span></summary><div class="rrpw-strip" id="'.esc_attr($id).'" role="region" tabindex="0" aria-label="Spillere og kamper. Bla sideveis for flere spillere.">'.implode('',$tiles).'</div><p class="rrpw-scroll-hint">Sveip eller bla sideveis for flere spillere</p></details>';
    }
    private static function tile(array $p, array $teams, ?array $c): string {
        $parts = preg_split('/\s+/u',trim($p['name']));
        $name = count($parts)>1 ? $parts[0].' '.end($parts) : $p['name'];
        $club = $teams ? reset($teams)['name'] : implode(' / ',$p['club_names']);
        $role = $c['players'][$p['fiks_id']]['role'] ?? null;
        $label = ['starter'=>'Startellever','bench'=>'Innbytter'][$role??''] ?? '';
        $expiry = $c ? min(strtotime($c['match']['kickoff'])+10800,$c['team_checked_at']+Service::TEAM_FRESH) : ($teams ? min(array_column($teams,'checked_at'))+Service::TEAM_FRESH : 0);
        $logo = ''; $opponent = ''; $time = '';
        if ($c) {
            $m = $c['match']; $side = isset($teams[$m['home']['id']]) ? 'home' : 'away';
            $club = $m[$side]['name'];
            $opponent = 'Mot '.preg_replace('/ Fotballklubb$/u','',$m[$side==='home'?'away':'home']['name']);
            $logo = $c['stream']['logos'][$side] ?? '';
            $zone = new \DateTimeZone('Europe/Oslo'); $start = strtotime($m['kickoff']);
            $time = (wp_date('Y-m-d',$start,$zone)===wp_date('Y-m-d',null,$zone) ? 'I dag' : wp_date('d.m.',$start,$zone)).' · '.wp_date('H:i',$start,$zone);
        }
        $photo = get_the_post_thumbnail_url($p['id'],'thumbnail');
        $nameId = wp_unique_id('rrpw-player-');
        ob_start(); ?>
        <article class="rrpw-mini" aria-labelledby="<?php echo esc_attr($nameId); ?>" data-player-id="<?php echo (int)$p['id']; ?>" data-kickoff="<?php echo $c?(int)strtotime($m['kickoff']):0; ?>" data-card-expires="<?php echo (int)$expiry; ?>">
          <div class="rrpw-portrait">
            <?php if ($photo): ?><img src="<?php echo esc_url($photo); ?>" alt="" width="68" height="68" loading="lazy"><?php endif; ?>
            <svg class="rrpw-silhouette" <?php echo $photo?'hidden':''; ?> viewBox="0 0 80 80" aria-hidden="true"><circle cx="40" cy="25" r="14"/><path d="M12 75v-9a28 28 0 0 1 56 0v9z"/></svg>
          </div>
          <span class="rrpw-mini-status <?php echo $label?'is-confirmed':''; ?>" <?php echo $label?'':'hidden'; ?> data-lineup-expires="<?php echo $c?(int)$c['lineup_expires']:0; ?>"><?php echo esc_html($label); ?></span>
          <h3 id="<?php echo esc_attr($nameId); ?>" aria-label="<?php echo esc_attr($p['name']); ?>" title="<?php echo esc_attr($p['name']); ?>"><?php echo esc_html($name); ?></h3>
          <p class="rrpw-mini-club"><?php if ($logo): ?><img src="<?php echo esc_url($logo); ?>" alt="" width="18" height="18" loading="lazy" referrerpolicy="no-referrer"><?php endif; ?><span><?php echo esc_html($club); ?></span></p>
          <div class="rrpw-mini-fixture">
            <?php if ($c): ?><time datetime="<?php echo esc_attr($m['kickoff']); ?>"><?php echo esc_html($time); ?></time><span class="rrpw-opponent" title="<?php echo esc_attr($opponent); ?>"><?php echo esc_html($opponent); ?></span>
            <?php endif; ?>
          </div>
          <?php if ($c): ?><div class="rrpw-mini-links">
            <div class="rrpw-mini-media">
            <?php if (!empty($c['stream']['source'])): ?><a class="rrpw-mygame" data-expires="<?php echo (int)$c['expires']; ?>" href="<?php echo esc_url($c['stream']['source']); ?>" target="_blank" rel="noopener noreferrer">MyGame ↗</a>
            <?php else: ?><span class="rrpw-mini-unknown">Sending uavklart</span><?php endif; ?>
            <?php if (!empty($c['stream']['url'])): ?><a class="rrpw-mini-watch" data-expires="<?php echo (int)$c['expires']; ?>" href="<?php echo esc_url($c['stream']['url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="Se kampen på TV 2 Play. MyGame-abonnement kreves.">TV 2 ↗</a><?php endif; ?>
            </div><a class="rrpw-mini-nff" href="<?php echo esc_url(Sources::url('match',$m['id'])); ?>" target="_blank" rel="noopener noreferrer">Kampinfo ↗</a>
          </div><?php endif; ?>
        </article>
        <?php return (string)ob_get_clean();
    }
}
