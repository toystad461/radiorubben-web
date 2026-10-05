"""Public, bounded GET probes; never bootstrap WordPress. Python stdlib + curl.

Exit 0 = PASS only, 1 = FAIL/ERROR, 2 = WARN/SKIP. Never a release approval.
"""
import argparse
from datetime import datetime, timezone, timedelta
from email.utils import parsedate_to_datetime
import hashlib
from html.parser import HTMLParser
import json
from pathlib import Path
import re
import subprocess
import tempfile
from urllib.parse import urlsplit
import xml.etree.ElementTree as ET

BASELINE = 'c13adf7ecc2756893f8725a26419189a0199a1bd'
WEATHER = 'https://api.met.no/weatherapi/locationforecast/2.0/compact?lat=59.8156&lon=5.268'
RSS = 'https://www.bomlo.kommune.no/ArtikkelRSS.ashx?NyhetsKategoriId=26&Spraak=Nynorsk'
MATCH = 'https://www.fotball.no/fotballdata/kamp/?fiksId=8985476'
ASSET = 'https://www.radiorubben.no/wp-content/themes/radio-rubben-wordpress-v1/assets/css/theme.css'
FIXED_URLS = {WEATHER, RSS, MATCH + '&underside=kamptropper', MATCH + '&underside=kamphendelser', ASSET}
USER_AGENT = 'RadioRubben-ReadOnlyQA/1.0 (+https://www.radiorubben.no; post@radiorubben.no)'


def utcnow():
    return datetime.now(timezone.utc)


def iso(value):
    return value.isoformat().replace('+00:00', 'Z')


def date(value):
    result = datetime.fromisoformat(value.replace('Z', '+00:00'))
    if result.tzinfo is None:
        raise ValueError('timezone missing')
    return result


def allowed_url(url, stream=False):
    if not stream:
        return url in FIXED_URLS
    p = urlsplit(url)
    return (p.scheme == 'https' and p.netloc in {'streaming.radio.co', 'streaming2.radio.co'}
            and not p.query and not p.fragment
            and re.fullmatch(r'/s[a-zA-Z0-9]+/listen', p.path) is not None)


def fetch(url, stream=False):
    if not allowed_url(url, stream):
        raise ValueError('destination not allowlisted')
    cap = 65536 if stream else 2_000_000
    with tempfile.TemporaryDirectory(prefix='rr-probe-') as tmp:
        body, headers = Path(tmp) / 'body', Path(tmp) / 'headers'
        # Ignore .curlrc. No redirects, retries, cookies, auth or cache-busting.
        command = ['curl', '--disable', '--silent', '--show-error', '--proto', '=https',
                   '--request', 'GET', '--connect-timeout', '10', '--max-time', '20',
                   '--max-filesize', str(cap), '--user-agent', USER_AGENT,
                   '--header', 'Accept-Encoding: identity', '--dump-header', str(headers),
                   '--output', str(body), '--write-out', '%{http_code}', url]
        proc = subprocess.run(command, capture_output=True, timeout=23, check=False)
        raw = body.read_bytes() if body.exists() else b''
        blocks = re.split(r'\r?\n\r?\n', headers.read_text(errors='replace') if headers.exists() else '')
        blocks = [b for b in blocks if b.startswith('HTTP/')]
        hdr = {}
        for line in (blocks[-1].splitlines()[1:] if blocks else []):
            if ':' in line:
                key, value = line.split(':', 1)
                hdr[key.lower()] = value.strip()
        code = int(proc.stdout.decode().strip() or '0')
        meta = {'http_status': code, 'curl_exit': proc.returncode, 'bytes': len(raw),
                'sha256': hashlib.sha256(raw).hexdigest(),
                'headers': {k: hdr[k][:200] for k in ('content-type', 'cache-control', 'age',
                            'expires', 'last-modified', 'vary') if k in hdr}}
        if code != 200:
            return raw, meta, ('ERROR' if code == 0 else 'FAIL', 'No HTTP 200; redirects are not followed.')
        if proc.returncode and not (stream and proc.returncode in {28, 63} and raw):
            return raw, meta, ('ERROR', 'Transfer incomplete or blocked; payload not validated.')
        if not stream and len(raw) >= cap:
            return raw, meta, ('FAIL', 'Response reached size limit.')
        return raw, meta, None


class MatchShape(HTMLParser):
    def __init__(self):
        super().__init__()
        self.classes, self.tabs, self.links = set(), set(), set()

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        self.classes.update(a.get('class', '').split())
        if 'data-tab' in a:
            self.tabs.add(a['data-tab'])
        if tag == 'a':
            self.links.add(a.get('href', ''))


def validate_nff(raw, kind):
    doc = MatchShape()
    doc.feed(raw.decode('utf-8', errors='strict'))
    if not {'a_matchCard', 'teamName'} <= doc.classes:
        return 'FAIL', 'Missing NFF match-card structure.', {}
    if not any(re.search(r'[?&]fiksId=30365(?:&|$)', link) for link in doc.links):
        return 'FAIL', 'Expected Bremnes team reference missing.', {}
    required = {'homeTeamWrapper', 'awayTeamWrapper', 'playerContent'}
    available = required <= doc.classes if kind == 'nff-rosters' else 'kamphendelser' in doc.tabs
    return ('PASS' if available else 'WARN',
            'Fresh GET and HTML markers only; PHP importer/identity/event semantics remain manual.',
            {'section_present': available, 'fixture_id': 8985476})


def validate_weather(raw, now):
    obj = json.loads(raw)
    updated = date(obj['properties']['meta']['updated_at'])
    series = obj['properties']['timeseries']
    if not isinstance(series, list) or not series:
        return 'FAIL', 'No forecast series.', {}
    valid = []
    for row in series:
        temperature = row['data']['instant']['details']['air_temperature']
        if isinstance(temperature, bool) or not isinstance(temperature, (int, float)):
            raise ValueError('invalid temperature')
        valid.append(date(row['time']))
    age = (now - updated).total_seconds()
    okay = -300 <= age <= 21600 and any(now <= t <= now + timedelta(hours=3) for t in valid)
    return ('PASS' if okay else 'FAIL', 'Forecast schema, update age (max 6h) and near-future coverage.',
            {'updated_at': iso(updated), 'age_seconds': round(age), 'hours': len(valid)})


def validate_rss(raw, now):
    normalized = raw.replace(b'\x00', b'').upper()
    if b'<!DOCTYPE' in normalized or b'<!ENTITY' in normalized:
        return 'FAIL', 'DTD/entity declaration rejected.', {}
    root = ET.fromstring(raw)
    atom = '{http://www.w3.org/2005/Atom}'
    if root.tag == 'rss':
        rows = [(x.findtext('title'), x.findtext('link'), x.findtext('pubDate')) for x in root.findall('./channel/item')]
    elif root.tag == atom + 'feed':
        rows = [(x.findtext(atom + 'title'), next((a.get('href') for a in x.findall(atom + 'link')
                 if a.get('rel', 'alternate') == 'alternate'), None), x.findtext(atom + 'updated'))
                for x in root.findall(atom + 'entry')]
    else:
        return 'FAIL', 'Not RSS or Atom.', {}
    dates, usable = [], 0
    for title, link, timestamp in rows:
        if title and link and urlsplit(link).scheme in {'http', 'https'}:
            usable += 1
        if timestamp:
            try:
                parsed = date(timestamp) if root.tag == atom + 'feed' else parsedate_to_datetime(timestamp)
                if parsed.tzinfo is not None:
                    dates.append(parsed)
            except (ValueError, TypeError, OverflowError):
                pass
    if not usable:
        return 'FAIL', 'No usable feed entries.', {'items': len(rows)}
    newest = max(dates) if dates else None
    age = (now - newest).total_seconds() / 86400 if newest else None
    okay = age is not None and -1 <= age <= 30
    return ('PASS' if okay else 'WARN', 'Usable feed; missing dates or item age over 30 days requires review.',
            {'items': len(rows), 'usable_items': usable, 'newest_item': iso(newest) if newest else None,
             'newest_age_days': round(age, 2) if age is not None else None})


def probe(name, url, kind, now):
    record = {'check': name, 'kind': kind, 'url': url, 'started_at': iso(utcnow())}
    try:
        raw, meta, error = fetch(url, stream=kind == 'audio')
        record.update(meta)
        if error:
            status, detail = error
            evidence = {}
        elif kind.startswith('nff-'):
            status, detail, evidence = validate_nff(raw, kind)
        elif kind == 'weather':
            status, detail, evidence = validate_weather(raw, now)
        elif kind == 'rss':
            status, detail, evidence = validate_rss(raw, now)
        elif kind == 'audio':
            audio_type = meta['headers'].get('content-type', '').split(';')[0].strip().startswith('audio/')
            status = 'WARN' if audio_type and len(raw) >= 1024 else 'FAIL'
            detail = ('Audio bytes observed; decoding, audible content and browser playback NOT verified.'
                      if status == 'WARN' else 'No usable audio response.')
            evidence = {}
        else:
            css = 'text/css' in meta['headers'].get('content-type', '') and bool(raw.strip())
            status, detail, evidence = ('PASS' if css else 'FAIL'), 'Static CSS only; no application/cache invalidation tested.', {}
        record.update(status=status, detail=detail, evidence=evidence)
    except (ValueError, KeyError, TypeError, IndexError, UnicodeError, ET.ParseError, OSError, subprocess.TimeoutExpired) as exc:
        record.update(status='ERROR', detail='Probe could not complete: ' + type(exc).__name__)
    record['finished_at'] = iso(utcnow())
    return record


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path, required=True)
    parser.add_argument('--stream-url', default='', help='Confirmed public Radio.co listen URL; no WordPress discovery.')
    parser.add_argument('--source-ref', default=BASELINE, help='Commit under review (full SHA).')
    args = parser.parse_args()
    if not re.fullmatch('[0-9a-f]{40}', args.source_ref):
        parser.error('--source-ref must be a full commit SHA')
    if args.stream_url and not allowed_url(args.stream_url, True):
        parser.error('--stream-url must be an allowlisted public Radio.co URL without query/credentials')
    now = utcnow()
    checks = [probe('MET Rubbestadneset', WEATHER, 'weather', now),
              probe('Bømlo kommune RSS', RSS, 'rss', now),
              probe('NFF roster structure', MATCH + '&underside=kamptropper', 'nff-rosters', now),
              probe('NFF event structure', MATCH + '&underside=kamphendelser', 'nff-events', now),
              probe('Legacy static CSS/cache observation 1', ASSET, 'static-cache', now),
              probe('Legacy static CSS/cache observation 2', ASSET, 'static-cache', now)]
    first, second = checks[-2:]
    comparable = first['status'] == second['status'] == 'PASS'
    same = comparable and first['sha256'] == second['sha256']
    checks.append({'check': 'Static CSS repeated-response consistency', 'kind': 'static-cache',
                   'status': ('PASS' if same else 'WARN') if comparable else 'SKIP',
                   'detail': 'Same asset hash in two GETs; not proof of CDN/cache isolation.' if same
                   else 'Two comparable, identical CSS responses were not confirmed.'})
    if args.stream_url:
        checks.append(probe('Audio transport sample', args.stream_url, 'audio', now))
    else:
        checks.append({'check': 'Audio transport sample', 'kind': 'audio', 'status': 'SKIP',
                       'detail': 'No confirmed active stream URL supplied. Existing pause state is not changed.'})
    counts = {s: sum(x['status'] == s for x in checks) for s in ('PASS', 'FAIL', 'ERROR', 'WARN', 'SKIP')}
    result = {'schema_version': 1, 'source_ref': args.source_ref,
              'probe_sha256': hashlib.sha256(Path(__file__).read_bytes()).hexdigest(), 'started_at': iso(now), 'finished_at': iso(utcnow()),
              'scope': 'External GET/structure observations, not WordPress integration or production approval',
              'production_approved': False, 'wordpress_bootstrapped': False,
              'policy': {'methods': ['GET'], 'redirects': False, 'retries': 0, 'credentials': False,
                         'response_bodies_in_report': False, 'timeout_seconds_per_request': 20},
              'checks': checks, 'summary': counts,
              'manual_gates': ['vipps-return', 'wordpress-fresh-import', 'audible-browser-playback',
                               'mail-cron-payment-automation', 'cache-operations-release']}
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n')
    print(json.dumps(counts))
    return 1 if counts['FAIL'] or counts['ERROR'] else 2 if counts['WARN'] or counts['SKIP'] else 0


if __name__ == '__main__':
    raise SystemExit(main())
