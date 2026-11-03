"""VPS source collection: state and existing WordPress contract. Standard library only."""
from __future__ import annotations
import base64
import datetime as dt
import hashlib
import json
import os
import re
import sqlite3
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

UTC = dt.timezone.utc
VERSION = '1.0.0'
PREFIX = '/rr-fotballrobot/v1'
NORA_URL = 'https://www.instagram.com/reel/DeC2-KuISzj'
CLUBS = ['Bremnes', 'Moster', 'Finnås', 'Bømlo', 'Rubbestadneset']


class Blocked(Exception):
    """Safe, short error code, without remote body, secrets or request headers."""


def now():
    return dt.datetime.now(UTC).replace(microsecond=0).isoformat()


def stamp(value):
    try:
        d = dt.datetime.fromisoformat(value.replace('Z', '+00:00'))
        if d.tzinfo is None:
            raise ValueError()
        return d.timestamp()
    except (ValueError, AttributeError, TypeError):
        raise Blocked('invalid_timestamp') from None


def canonical(url):
    try:
        p = urllib.parse.urlsplit(url)
        if p.scheme != 'https' or not p.hostname or p.username or p.password or p.port or not p.path.strip('/'):
            raise ValueError()
        query = sorted((k, v) for k, v in urllib.parse.parse_qsl(p.query, keep_blank_values=True)
                       if not re.match(r'^(utm_|fbclid$|gclid$)', k, re.I))
        return urllib.parse.urlunsplit(('https', p.hostname.lower(), p.path.rstrip('/'), urllib.parse.urlencode(query), ''))
    except (ValueError, TypeError):
        raise Blocked('invalid_source_url') from None


def rotation(at):
    day = int(at.timestamp()) // 86400
    block = (day // 5) % 4
    start = at.year - 20 + block * 5
    return {'club': CLUBS[day % 5], 'from_year': start,
            'to_year': at.year if block == 3 else start + 4, 'coverage': 'partial'}


def contract(m):
    match = re.fullmatch(r'(\d+)\.(\d+)\.(\d+)', str(m.get('version', '')))
    if (m.get('engine') != 'radio-rubben-fotballrobot' or not match or
            tuple(map(int, match.groups())) < (0, 9, 6)):
        raise Blocked('wrong_monitor_version')
    if m.get('automatic_proposals') is not True or m.get('publication') != 'manual_approval_required':
        raise Blocked('robot_paused_or_wrong_publication_policy')
    if not {'candidate_approval', 'verified_video', 'supporting_sources'} <= set(m.get('features', [])):
        raise Blocked('missing_features')
    if stamp(m.get('next_check')) < time.time() - 7200:
        raise Blocked('missing_or_stale_next_check')
    ids, fiks = set(), set()
    for p in m.get('players', []):
        if type(p.get('id')) is not int or type(p.get('fiks_id')) is not int or min(p['id'], p['fiks_id']) < 1:
            raise Blocked('invalid_identity')
        if p['id'] in ids or p['fiks_id'] in fiks or type(p.get('enabled')) is not bool:
            raise Blocked('ambiguous_identity')
        ids.add(p['id']); fiks.add(p['fiks_id'])
    return [p for p in m.get('players', []) if p['enabled'] is True]


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


def request(url, method='GET', body=None, headers=None, timeout=20, limit=3_000_000):
    raw = None if body is None else json.dumps(body, ensure_ascii=False).encode()
    h = {'Accept': 'application/json', 'Content-Type': 'application/json', **(headers or {})}
    req = urllib.request.Request(url, data=raw, headers=h, method=method)
    try:
        # Inherited proxy/CA configuration remains effective; never follow auth redirects.
        with urllib.request.build_opener(NoRedirect).open(req, timeout=timeout) as r:
            data = r.read(limit + 1)
            if len(data) > limit:
                raise Blocked('response_too_large')
            return json.loads(data)
    except urllib.error.HTTPError as e:
        raise Blocked('http_' + str(e.code)) from None
    except (urllib.error.URLError, TimeoutError, OSError):
        raise Blocked('transport_uncertain') from None
    except (ValueError, UnicodeError):
        raise Blocked('invalid_json') from None


class Store:
    def __init__(self, path):
        Path(path).parent.mkdir(mode=0o700, parents=True, exist_ok=True)
        self.path = str(path)
        with self.connect() as c:
            c.executescript('''
            CREATE TABLE IF NOT EXISTS settings(k TEXT PRIMARY KEY,v TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS jobs(id TEXT PRIMARY KEY,state TEXT NOT NULL,created TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS checkpoints(scope TEXT PRIMARY KEY,at TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS delivery(k TEXT PRIMARY KEY,payload TEXT NOT NULL,state TEXT NOT NULL,at TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS blocked_hosts(host TEXT PRIMARY KEY,reason TEXT NOT NULL,at TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS nonces(n TEXT PRIMARY KEY,at INTEGER NOT NULL);
            CREATE TABLE IF NOT EXISTS events(id INTEGER PRIMARY KEY,at TEXT,run_id TEXT,kind TEXT,player_id INTEGER,message TEXT);
            ''')
        os.chmod(path, 0o600)

    def connect(self):
        c = sqlite3.connect(self.path, timeout=5)
        c.execute('PRAGMA busy_timeout=5000')
        return c

    def get(self, key, default=None):
        with self.connect() as c:
            r = c.execute('SELECT v FROM settings WHERE k=?', (key,)).fetchone()
            return json.loads(r[0]) if r else default

    def set(self, key, value):
        with self.connect() as c:
            c.execute('INSERT OR REPLACE INTO settings VALUES (?,?)', (key, json.dumps(value)))

    def event(self, run_id, kind, message, player_id=0):
        # Caller supplies controlled reason codes, never exception repr or model text.
        with self.connect() as c:
            c.execute('INSERT INTO events(at,run_id,kind,player_id,message) VALUES(?,?,?,?,?)',
                      (now(), run_id, kind, player_id, message[:400]))

    def checkpoint(self, scope):
        with self.connect() as c:
            r = c.execute('SELECT at FROM checkpoints WHERE scope=?', (scope,)).fetchone()
            return r[0] if r else None

    def success(self, scope, at):
        with self.connect() as c:
            c.execute('INSERT OR REPLACE INTO checkpoints VALUES (?,?)', (scope, at))

    def enqueue(self, request_id):
        with self.connect() as c:
            c.execute('BEGIN IMMEDIATE')
            row = c.execute('SELECT id FROM jobs WHERE state IN (\'queued\',\'running\') LIMIT 1').fetchone()
            if row:
                return row[0]
            c.execute('INSERT OR IGNORE INTO jobs VALUES (?,\'queued\',?)', (request_id, now()))
            return request_id

    def status(self):
        at = time.time()
        seen = self.get('worker_seen', '')
        paused = self.get('paused', True)
        with self.connect() as c:
            running = c.execute("SELECT id FROM jobs WHERE state='running' LIMIT 1").fetchone()
            events = c.execute('SELECT at,kind,player_id,message FROM events ORDER BY id DESC LIMIT 50').fetchall()
        alive = bool(seen) and stamp(seen) >= at - 180
        return {'engine': 'rrfr-source-collection', 'version': VERSION,
                'state': 'unavailable' if not alive else 'paused' if paused else 'running' if running else self.get('last_result', 'active'),
                'worker_seen': seen, 'last_start': self.get('last_start', ''), 'last_success': self.get('last_success', ''),
                'next_run': '' if paused else dt.datetime.fromtimestamp((int(at) // 21600 + 1) * 21600, UTC).isoformat(),
                'mode': self.get('mode', 'dry-run'), 'counts': self.get('counts', {}), 'run_id': self.get('run_id', ''),
                'events': [dict(zip(['at', 'kind', 'player_id', 'message'], e)) for e in events]}


class WordPress:
    def __init__(self, base, user, password, store, transport=request):
        if base != 'https://www.radiorubben.no':
            raise Blocked('wrong_wordpress_target')
        self.base, self.store, self.transport = base, store, transport
        self.headers = {'Authorization': 'Basic ' + base64.b64encode((user + ':' + password).encode()).decode()}

    def call(self, route, method='GET', body=None):
        if not re.fullmatch(r'/rr-fotballrobot/v1/(?:player-monitor|player-candidates|source-collection|players/[1-9][0-9]*(?:/sources|/source-history\?page=[1-9][0-9]*)?)', route):
            raise Blocked('forbidden_route')
        if method == 'POST' and not (route == PREFIX + '/player-candidates' or re.fullmatch(PREFIX + r'/players/[1-9][0-9]*/sources', route)):
            raise Blocked('forbidden_write')
        tries = 3 if method == 'GET' else 1
        for attempt in range(tries):
            try:
                return self.transport(self.base + '/wp-json' + route, method=method, body=body, headers=self.headers)
            except Blocked as e:
                if attempt + 1 == tries or str(e) not in {'transport_uncertain', 'http_502', 'http_503', 'http_504'}:
                    raise
                time.sleep(2 ** attempt)

    def history(self, player_id):
        posts, page = [], 1
        while page:
            if page > 100:
                raise Blocked('history_incomplete')
            result = self.call(f'{PREFIX}/players/{player_id}/source-history?page={page}')
            if result.get('player_id') != player_id:
                raise Blocked('history_identity_mismatch')
            posts.extend(result['posts']); page = result['next_page']
        return posts

    def deliver(self, route, payload, key, find):
        # Persist before POST. An uncertain call is only reconciled by GET, never blindly repeated.
        with self.store.connect() as c:
            row = c.execute('SELECT payload,state FROM delivery WHERE k=?', (key,)).fetchone()
        existing = find(self.call(route))
        if existing:
            state = 'confirmed'
        elif row:
            raise Blocked('delivery_uncertain_requires_reconciliation')
        else:
            with self.store.connect() as c:
                c.execute('INSERT INTO delivery VALUES (?,?,?,?)', (key, json.dumps(payload, sort_keys=True), 'sending', now()))
            try:
                self.call(route, 'POST', payload)
            except Blocked:
                pass
            existing = find(self.call(route))
            if not existing:
                raise Blocked('delivery_not_confirmed')
            state = 'confirmed'
        with self.store.connect() as c:
            c.execute('UPDATE delivery SET state=?,at=? WHERE k=?', (state, now(), key))
        return existing


def evidence(packet, pages):
    proofs = packet.get('proofs', [])
    if not proofs:
        raise Blocked('missing_evidence')
    for p in proofs:
        url = canonical(p.get('url', ''))
        quote = ' '.join(str(p.get('quote', '')).split())
        if url not in pages or len(quote) < 12 or len(quote) > 800 or quote not in ' '.join(pages[url]['text'].split()):
            raise Blocked('evidence_not_in_read_source')
    return proofs


def validate_source(packet, player, pages, since, at):
    p = packet['payload']
    if type(p.get('fiks_id')) is not int or p['fiks_id'] != player['fiks_id'] or packet.get('player_id') != player['id']:
        raise Blocked('source_identity_mismatch')
    url = canonical(p.get('url', ''))
    if url == NORA_URL:
        raise Blocked('already_published_1088')
    proofs = evidence(packet, pages)
    kinds = {x['kind'] for x in proofs}
    if not {'identity', 'published_at', 'fact'} <= kinds or url not in pages:
        raise Blocked('missing_source_proof')
    # Direct NFF identity anchor is mandatory. A same-name search is insufficient.
    anchor = f'https://www.fotball.no/fotballdata/person/profil?fiksId={player["fiks_id"]}'
    if anchor not in pages or not any(x['kind'] == 'identity' and canonical(x['url']) == anchor for x in proofs):
        raise Blocked('missing_fiks_identity_anchor')
    pub = p.get('published_at', '')
    try:
        pub_day = dt.date.fromisoformat(pub[:10])
    except (ValueError, TypeError):
        raise Blocked('unknown_publication_date') from None
    floor = max(at.date() - dt.timedelta(days=7), dt.datetime.fromtimestamp(since, UTC).date())
    if pub_day < floor or pub_day > at.date():
        raise Blocked('old_or_future_source')
    if p.get('event_date') is not None:
        try:
            dt.date.fromisoformat(p['event_date'])
        except (ValueError, TypeError):
            raise Blocked('invalid_event_date') from None
        if 'event_date' not in kinds:
            raise Blocked('missing_event_date_proof')
    facts = p.get('facts', [])
    if not isinstance(facts, list) or not 1 <= len(facts) <= 6 or any(not isinstance(x, str) or not x.strip() or len(x) > 350 for x in facts) or len(' '.join(facts).split()) > 120:
        raise Blocked('invalid_facts')
    if any(not any(x['kind'] == 'fact' and x.get('claim') == fact and canonical(x['url']) == url for x in proofs) for fact in facts):
        raise Blocked('fact_without_exact_evidence_binding')
    p['url'] = url; p['public_read'] = True; p['checked_at'] = pages[url]['checked_at']
    for source in p.get('supporting_sources', []):
        u = canonical(source['url'])
        if u not in pages:
            raise Blocked('supporting_source_not_read')
        source['url'] = u; source['public_read'] = True; source['checked_at'] = pages[u]['checked_at']
        if any(not any(x.get('claim') == f and canonical(x['url']) == u for x in proofs) for f in source.get('facts', [])):
            raise Blocked('supporting_fact_without_proof')
    if p.get('format', 'brief') == 'feature' or p.get('video'):
        # Only a fetched publisher transcript can authorize video here; never infer from a player/title.
        trans = [x for x in proofs if x['kind'] == 'transcript']
        if not trans or not packet.get('transcript_publisher_verified'):
            raise Blocked('video_content_not_verified')
        types = {s.get('kind') for s in p.get('supporting_sources', [])}
        hosts = {urllib.parse.urlsplit(s['url']).hostname.removeprefix('www.') for s in [p] + p.get('supporting_sources', [])}
        if not {'match', 'background'} <= types or len(hosts) < 2 or sum(len(s['facts']) for s in [p] + p.get('supporting_sources', [])) < 6:
            raise Blocked('feature_incomplete')
    return p


def validate_candidate(packet, pages, known, at):
    p = packet['payload']; fiks = p.get('fiks_id')
    if type(fiks) is not int or fiks < 1 or fiks in known:
        raise Blocked('candidate_identity_existing_or_invalid')
    proofs = evidence(packet, pages)
    if not {'identity', 'history', 'current', 'activity'} <= {x['kind'] for x in proofs}:
        raise Blocked('candidate_evidence_incomplete')
    anchor = f'https://www.fotball.no/fotballdata/person/profil?fiksId={fiks}'
    if anchor not in pages or not any(x['kind'] == 'identity' and canonical(x['url']) == anchor for x in proofs):
        raise Blocked('candidate_no_fiks_proof')
    local = lambda name: any(re.search(r'(?:^|\s)' + re.escape(c) + r'(?:$|\s)', name, re.I) for c in CLUBS)
    if not local(p.get('former_club', '')) or local(p.get('current_club', '')):
        raise Blocked('candidate_not_outside_bomlo')
    years = p.get('former_seasons', [])
    if not years or any(type(y) is not int or not at.year - 20 <= y <= at.year for y in years) or p.get('active_season') not in [at.year, at.year - 1]:
        raise Blocked('candidate_season_outside_window')
    sources = p.get('sources', [])
    if not {'history', 'current', 'activity'} <= {s.get('kind') for s in sources}:
        raise Blocked('candidate_evidence_incomplete')
    for s in sources:
        u = canonical(s['url'])
        if u not in pages or not any(x['kind'] == s['kind'] and x.get('claim') == s['fact'] and canonical(x['url']) == u for x in proofs):
            raise Blocked('candidate_fact_without_proof')
        s['url'] = u; s['public_read'] = True
    p['checked_at'] = at.replace(microsecond=0).isoformat()
    # status is assigned by the existing WP implementation, not the researcher.
    p.pop('status', None)
    return p
