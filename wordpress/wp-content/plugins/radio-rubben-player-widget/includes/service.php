<?php
namespace RadioRubben\PlayerWidget;

final class Service {
    public const SETTINGS = 'rrpw_settings_v1';
    public const CACHE = 'rrpw_cache_v1';
    public const FRESH = 1800;
    public const TEAM_FRESH = 21600;
    public static function settings(): array { return get_option(self::SETTINGS, ['revision'=>0,'enabled'=>false,'players'=>[]]); }
    public static function cache(): array { return get_option(self::CACHE, ['teams'=>[],'matches'=>[]]); }
    public static function profiles(?array $ids = null): array {
        $out = [];
        $ids = $ids ?? get_posts(['post_type'=>'rr_robot_player','post_status'=>'private','numberposts'=>-1,'fields'=>'ids','orderby'=>'ID','order'=>'ASC']);
        foreach ($ids as $id) {
            $post = get_post((int)$id);
            if (!$post || $post->post_type !== 'rr_robot_player' || $post->post_status !== 'private') continue;
            $s = get_option('rrfr_player_'.(int)$id);
            if (!is_array($s) || empty($s['fiks_id']) || empty($s['name'])) continue;
            $teams = [];
            foreach ($s['snapshot']['stats'] ?? [] as $r) if ((int)$r['year'] === (int)wp_date('Y')) $teams[(int)$r['team_id']] = (string)$r['team'];
            // Never copy private notes, events, contact data or editorial history into this plugin.
            $out[(int)$id] = ['id'=>(int)$id,'fiks_id'=>(int)$s['fiks_id'],'name'=>(string)$s['name'],'enabled'=>!empty($s['enabled']),'group'=>$s['group']??'','clubs'=>array_map('intval', array_keys($s['snapshot']['clubs'] ?? [])), 'club_names'=>array_column($s['snapshot']['clubs']??[],'name'), 'teams'=>$teams];
        }
        return $out;
    }
    public static function save(array $input): void {
        if (!current_user_can('manage_options')) throw new \RuntimeException('Ingen tilgang.');
        $old = self::settings();
        if ((int)($input['revision'] ?? -1) !== $old['revision']) throw new \RuntimeException('Innstillingene er endret i en annen fane. Last siden på nytt.');
        $profiles = self::profiles(); $selected = [];
        foreach ((array)($input['players'] ?? []) as $id=>$data) {
            if (!is_array($data) || empty($data['show'])) continue;
            if (!isset($profiles[$id]) || !$profiles[$id]['enabled']) throw new \RuntimeException('En valgt spiller er fjernet eller pauset.');
            $raw = trim((string)($data['teams'] ?? ''));
            if (!preg_match('/^[0-9,\s]+$/D', $raw)) throw new \RuntimeException('Oppgi lag-ID-er med komma mellom.');
            $ids = array_values(array_unique(array_map([Sources::class,'id'], preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY))));
            if (!$ids || count($ids) > 8) throw new \RuntimeException('Velg mellom ett og åtte lag per spiller.');
            $selected[(int)$id] = ['fiks_id'=>$profiles[$id]['fiks_id'], 'teams'=>$ids];
        }
        if (count($selected) > 20) throw new \RuntimeException('Maksimalt 20 spillere kan følges i widgeten.');
        update_option(self::SETTINGS, ['revision'=>$old['revision']+1,'enabled'=>!empty($input['enabled']),'players'=>$selected], false);
        // New settings invalidate every old association immediately, including removed players.
        update_option(self::CACHE, ['teams'=>[],'matches'=>[]], false);
        self::init();
    }
    public static function schedules(array $s): array {
        $s['rrpw_minute'] = ['interval'=>60,'display'=>'Hvert minutt (spillerkamper)']; return $s;
    }
    public static function init(): void {
        add_shortcode('rr_spillerkamper', [View::class,'shortcode']);
        $s = self::settings();
        if ($s['enabled'] && self::selected($s,self::profiles())) {
            if (function_exists('wp_get_scheduled_event')) { $event=wp_get_scheduled_event('rrpw_refresh'); if($event && $event->schedule!=='rrpw_minute') wp_clear_scheduled_hook('rrpw_refresh'); }
            if (!wp_next_scheduled('rrpw_refresh')) wp_schedule_event(time()+10, 'rrpw_minute', 'rrpw_refresh');
        } else self::stop();
    }
    public static function stop(): void { wp_clear_scheduled_hook('rrpw_refresh'); }
    public static function selected(array $settings, array $profiles): array {
        $out = []; $decisions = get_option('rrfr_player_candidates',[]); $approvedIds=[];
        foreach ($decisions as $d) if (!empty($d['player_id'])) $approvedIds[(int)$d['player_id']] = (int)$d['fiks_id'];
        foreach ($profiles as $id=>$p) {
            if (!$p['enabled']) continue;
            if (isset($approvedIds[$id]) && $approvedIds[$id] !== $p['fiks_id']) continue;
            $manual = $settings['players'][$id] ?? null;
            if ($manual && $p['fiks_id'] !== $manual['fiks_id']) continue;
            $decision = $decisions[$p['fiks_id']] ?? null;
            $approved = $decision && ($decision['status']??'')==='approved' && (int)($decision['player_id']??0)===$id && (int)($decision['fiks_id']??0)===$p['fiks_id'];
            // A manually followed group member is eligible; unresolved candidates are never approvals.
            $group = !$decision && ($p['group']??'')==='bomlo-away';
            if (!$manual && !$approved && !$group) continue;
            $teams = array_values(array_unique(array_merge($manual['teams']??[],array_keys($p['teams']))));
            $out[$id] = $p + ['selected_teams'=>$teams];
        }
        return $out;
    }
    public static function candidates(array $settings, array $profiles, array $cache, int $now): array {
        $out = [];
        foreach (self::selected($settings, $profiles) as $player) foreach ($player['selected_teams'] as $tid) {
            $team = $cache['teams'][$tid] ?? null;
            if (!$team || !empty($team['error']) || ($team['checked_at'] ?? 0) < $now-self::TEAM_FRESH || !in_array($team['club_id'], $player['clubs'], true)) continue;
            foreach ($team['matches'] as $id=>$m) {
                $start = strtotime($m['kickoff']);
                if ($start < $now-6*3600) continue;
                if (isset($out[$id]) && array_diff_assoc($out[$id]['match']['home'], $m['home'])) { $out[$id]['conflict']=true; continue; }
                if (isset($out[$id]) && $out[$id]['match'] !== $m) { $out[$id]['conflict']=true; continue; }
                if (!isset($out[$id])) $out[$id] = ['match'=>$m, 'players'=>[], 'team_checked_at'=>$team['checked_at']];
                $out[$id]['players'][$player['fiks_id']] = ['name'=>$player['name'],'fiks_id'=>$player['fiks_id']];
                $out[$id]['team_checked_at'] = min($out[$id]['team_checked_at'], $team['checked_at']);
            }
        }
        return array_filter($out, static fn($c)=>empty($c['conflict']));
    }
    public static function tick(): void { Live::tick(); }
    public static function cards(int $player = 0, int $limit = 1): array {
        $s = self::settings(); if (!$s['enabled']) return [];
        $cache = self::cache(); $now = time(); $cards = [];
        foreach (self::candidates($s,self::profiles(),$cache,$now) as $id=>$c) {
            if ($player && !isset($c['players'][$player])) continue;
            $r = $cache['matches'][$id] ?? [];
            // Schedule changes invalidate old stream and lineup confirmations immediately.
            if (($r['match'] ?? null) !== $c['match']) $r = [];
            $streamFresh = ($r['stream_checked_at'] ?? 0) > $now-self::FRESH;
            $near = strtotime($c['match']['kickoff']) <= $now+4500;
            $roleFresh = $near ? 150 : self::FRESH;
            if (in_array($r['phase']??'', ['finished','cancelled','postponed'],true)) continue;
            if(strtotime($c['match']['kickoff'])<$now-10800) continue;
            $lineupFresh = ($r['lineup_checked_at'] ?? 0) > $now-$roleFresh;
            $c['phase']=$lineupFresh ? ($r['phase']??'unknown') : 'unknown';
            $c['stream'] = $streamFresh ? ($r['stream'] ?? null) : null;
            $c['expires'] = min(($r['stream_checked_at'] ?? 0)+self::FRESH, $c['team_checked_at']+self::TEAM_FRESH);
            $c['lineup_expires'] = ($r['lineup_checked_at'] ?? 0)+$roleFresh;
            foreach ($c['players'] as $pid=>&$p) $p['role'] = $lineupFresh ? ($r['roles'][$pid] ?? null) : null;
            unset($p);
            $cards[] = $c;
        }
        usort($cards, static fn($a,$b)=>strtotime($a['match']['kickoff'])<=>strtotime($b['match']['kickoff']));
        return array_slice($cards,0,max(1,min(6,$limit)));
    }
}
