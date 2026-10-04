<?php
namespace RadioRubben\PlayerWidget;

/** Public HTML adapters. Format changes fail closed; an HTTP 200 is not a broadcast. */
final class Sources {
    public static function id($v): int {
        if (!is_scalar($v) || !preg_match('/^[1-9][0-9]{0,8}$/D', (string)$v)) throw new \RuntimeException('Ugyldig NFF-ID.');
        return (int)$v;
    }
    public static function url(string $kind, int $id): string {
        self::id($id);
        if ($kind === 'stream') return 'https://kampoversikt.mygame.no/match/fiks-no'.$id;
        if (!in_array($kind, ['team','match'], true)) throw new \RuntimeException('Ukjent kilde.');
        return 'https://www.fotball.no/fotballdata/'.($kind === 'team' ? 'lag/hjem/' : 'kamp/').'?fiksId='.$id;
    }
    public static function fetch(string $kind, int $id): string {
        $r = wp_safe_remote_get(self::url($kind, $id), ['timeout'=>5, 'redirection'=>0, 'limit_response_size'=>2000000, 'headers'=>['Accept'=>'text/html']]);
        if (is_wp_error($r) || wp_remote_retrieve_response_code($r) !== 200) throw new \RuntimeException('Kilden kunne ikke hentes.');
        $html = wp_remote_retrieve_body($r);
        if (!$html || strlen($html) >= 2000000) throw new \RuntimeException('Kilden er tom eller avkortet.');
        return $html;
    }
    public static function dom(string $html): \DOMXPath {
        $d = new \DOMDocument(); $old = libxml_use_internal_errors(true);
        try { $ok = $d->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($old); }
        if (!$ok) throw new \RuntimeException('Uleselig kilde.');
        return new \DOMXPath($d);
    }
    public static function text($n): string { return $n ? trim(preg_replace('/\s+/u', ' ', $n->textContent)) : ''; }
    private static function cls(string $c): string { return "contains(concat(' ',normalize-space(@class),' '),' $c ')"; }
    private static function linkId($n): int {
        if (!$n) return 0;
        parse_str(parse_url($n->getAttribute('href'), PHP_URL_QUERY) ?: '', $q);
        try { return self::id($q['fiksId'] ?? ''); } catch (\Throwable $e) { return 0; }
    }
    private static function date(string $s): string {
        $d = \DateTimeImmutable::createFromFormat('!d.m.Y H:i', $s, new \DateTimeZone('Europe/Oslo'));
        if (!$d || $d->format('d.m.Y H:i') !== $s) throw new \RuntimeException('Ukjent kampdato.');
        return $d->format(DATE_ATOM);
    }
    public static function team(string $html, int $id): array {
        $x = self::dom($html); $clubs = [];
        foreach ($x->query('//a[contains(@href,"/fotballdata/klubb/hjem/")]') as $a) if ($cid = self::linkId($a)) $clubs[$cid] = $cid;
        if (count($clubs) !== 1) throw new \RuntimeException('Lagets klubb kunne ikke bekreftes.');
        $tables = $x->query('//*['.self::cls('a_matches').']//table');
        if (!$tables->length) throw new \RuntimeException('Terminlisten har endret format.');
        $matches = []; $name = '';
        foreach ($tables as $table) foreach ($x->query('.//tbody/tr', $table) as $row) {
            $td = $x->query('./td', $row);
            if ($td->length < 8) throw new \RuntimeException('Ukjent terminlisteformat.');
            $mid = self::linkId($x->query('.//a[contains(@href,"/fotballdata/kamp/")]', $td->item(0))->item(0));
            if (!$mid) continue;
            $home = $x->query('.//a[contains(@href,"/fotballdata/lag/hjem/")]', $td->item(3))->item(0);
            $away = $x->query('.//a[contains(@href,"/fotballdata/lag/hjem/")]', $td->item(5))->item(0);
            $hid = self::linkId($home); $aid = self::linkId($away);
            if (!in_array($id, [$hid,$aid], true)) continue;
            if (!$hid || !$aid || $hid === $aid) throw new \RuntimeException('Ukjente lag i terminlisten.');
            $name = self::text($id === $hid ? $home : $away);
            $status = self::text($td->item(4));
            if (preg_match('/cancel|postpon|avlys|utsatt|avbrutt|strøket/i', $row->getAttribute('class').' '.$status)) continue;
            if (preg_match('/^\(?\d+\s*[-–]\s*\d+\)?$/u', $status)) continue;
            $m = ['id'=>$mid, 'kickoff'=>self::date(self::text($td->item(0)).' '.self::text($td->item(2))), 'home'=>['id'=>$hid,'name'=>self::text($home)], 'away'=>['id'=>$aid,'name'=>self::text($away)], 'venue'=>self::text($td->item(6)), 'competition'=>self::text($td->item(7))];
            if (isset($matches[$mid]) && $matches[$mid] !== $m) throw new \RuntimeException('Motstridende kampdata.');
            $matches[$mid] = $m;
        }
        if (!$name) throw new \RuntimeException('Terminlisten kunne ikke knyttes til lag-ID.');
        return ['id'=>$id, 'club_id'=>reset($clubs), 'name'=>$name, 'matches'=>$matches];
    }
    public static function lineup(string $html, array $match, array $players): array {
        $x = self::dom($html); $title = self::text($x->query('//title')->item(0));
        $teams = $x->query('//*['.self::cls('a_matchCard').']//*['.self::cls('teamName').']/a');
        if ($teams->length !== 2 || self::linkId($teams->item(0)) !== $match['home']['id'] || self::linkId($teams->item(1)) !== $match['away']['id']) throw new \RuntimeException('Kampsidens lag stemmer ikke.');
        if (!preg_match('/(\d{2}\.\d{2}\.\d{4}) (\d{2}:\d{2})/', $title, $m) || strtotime(self::date($m[1].' '.$m[2])) !== strtotime($match['kickoff'])) throw new \RuntimeException('Kamptidspunktet er endret.');
        $out = [];
        foreach ($players as $pid) {
            $nodes = [];
            foreach ($x->query('//a['.self::cls('playerName').']') as $a) if (self::linkId($a) === $pid) $nodes[] = $a;
            $role = null;
            if (count($nodes) === 1) {
                $list = $x->query('ancestor::*['.self::cls('a_matchPlayerList').'][1]', $nodes[0])->item(0);
                $heading = $list ? self::text($x->query('preceding-sibling::h4[1]', $list)->item(0)) : '';
                $role = str_contains($heading,'Startoppstilling') ? 'starter' : (str_contains($heading,'Innbyttere') ? 'bench' : null);
                $card = $x->query('ancestor::*['.self::cls('a_playerWithEvents').'][1]', $nodes[0])->item(0);
                if ($card && str_contains(mb_strtolower(self::text($card)), 'strøket')) $role = null;
            }
            $out[$pid] = $role;
        }
        return $out;
    }
    private static function normalized(string $s): string { return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s))); }
    public static function tv2Url(string $url, int $match): ?string {
        $u = parse_url($url); if (!$u) return null;
        if (($u['scheme'] ?? '') !== 'https' || strtolower($u['host'] ?? '') !== 'play.tv2.no' || isset($u['user']) || isset($u['pass']) || isset($u['port']) || isset($u['fragment'])) return null;
        if (!preg_match('~^/gpid/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$~iD', $u['path'] ?? '')) return null;
        parse_str($u['query'] ?? '', $q);
        if (($q['utm_content'] ?? '') !== 'fiks-no'.$match) return null;
        return 'https://play.tv2.no'.$u['path'];
    }
    public static function stream(string $html, array $match): array {
        $x = self::dom($html); $events = [];
        foreach ($x->query('//script[@type="application/ld+json"]') as $s) {
            $d = json_decode($s->textContent, true);
            if (is_array($d) && ($d['@type'] ?? '') === 'SportsEvent') $events[] = $d;
        }
        if (count($events) !== 1) throw new \RuntimeException('Ingen entydig kamp hos MyGame.');
        $event = $events[0]; $teams = $event['competitor'] ?? [];
        if (count($teams) !== 2 || self::normalized($teams[0]['name'] ?? '') !== self::normalized($match['home']['name']) || self::normalized($teams[1]['name'] ?? '') !== self::normalized($match['away']['name']) || strtotime($event['startDate'] ?? '') !== strtotime($match['kickoff'])) throw new \RuntimeException('MyGame-kampens lag eller tidspunkt avviker fra NFF.');
        if (($event['eventStatus'] ?? '') !== 'https://schema.org/EventScheduled') throw new \RuntimeException('MyGame-kampen er ikke bekreftet planlagt.');
        $links = [];
        foreach ($x->query('//a[@href]') as $a) if ($url = self::tv2Url($a->getAttribute('href'), $match['id'])) $links[$url] = $url;
        if (count($links) !== 1) throw new \RuntimeException('Ingen entydig sendingslenke for denne kampen.');
        // Only exact official club image URLs. Never accept arbitrary remote media.
        $logos = [];
        foreach ($x->query('//img[@src]') as $img) {
            $src = $img->getAttribute('src');
            if (preg_match('~^https://storage\.googleapis\.com/mygame_club_media/fiks-no([1-9][0-9]{0,8})\.png$~D', $src, $m)) {
                $label = self::normalized($img->getAttribute('alt')) ?: self::normalized(self::text($img->parentNode));
                foreach (['home','away'] as $side) if ($label === self::normalized($match[$side]['name'])) $logos[$side] = $src;
            }
        }
        return ['url'=>reset($links), 'source'=>self::url('stream', $match['id']), 'logos'=>$logos];
    }
}
