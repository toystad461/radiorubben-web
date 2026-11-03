import copy
import datetime as dt
import hashlib
import hmac
import json
import tempfile
import time
import unittest
import uuid
import subprocess
import sys
import threading
import urllib.request
from unittest.mock import patch
from pathlib import Path
from core import Blocked, PREFIX, Store, UTC, WordPress, canonical, contract, now, rotation, validate_candidate, validate_source
from worker import auth, control, run, server
from research import Reader


def monitor():
    return {'version': '0.10.4', 'engine': 'radio-rubben-fotballrobot', 'automatic_proposals': True,
            'publication': 'manual_approval_required', 'features': ['candidate_approval', 'verified_video', 'supporting_sources'],
            'next_check': (dt.datetime.now(UTC) + dt.timedelta(hours=1)).isoformat(),
            'players': [{'id': 21, 'fiks_id': 321, 'name': 'Syntetisk Spiller', 'enabled': True},
                        {'id': 22, 'fiks_id': 322, 'name': 'Pauset Testspiller', 'enabled': False}]}


def source():
    p = {'player_id': 21, 'payload': {'fiks_id': 321, 'url': 'https://club.example/news/new', 'title': 'Synthetic source',
         'identity_note': 'Synthetic fixture only', 'published_at': dt.datetime.now(UTC).date().isoformat(),
         'event_date': None, 'facts': ['Syntetisk spiller har signert.'], 'format': 'brief', 'supporting_sources': [], 'video': None},
         'proofs': [
             {'kind': 'identity', 'url': 'https://www.fotball.no/fotballdata/person/profil?fiksId=321', 'quote': 'Syntetisk Spiller klubb Test', 'claim': 'identity'},
             {'kind': 'published_at', 'url': 'https://club.example/news/new', 'quote': 'Publisert i dag med gyldig dato', 'claim': 'published'},
             {'kind': 'fact', 'url': 'https://club.example/news/new', 'quote': 'Syntetisk spiller har signert.', 'claim': 'Syntetisk spiller har signert.'}]}
    pages = {x['url']: {'text': ' '.join(y['quote'] for y in p['proofs'] if y['url'] == x['url']), 'checked_at': now(), 'sha256': 'a' * 64} for x in p['proofs']}
    return p, pages


class FakeWP:
    def __init__(self):
        self.m = monitor(); self.calls = []; self.sources = []; self.posts = [{'id': 1088, 'title': 'Existing source'}]

    def call(self, route, method='GET', body=None):
        self.calls.append((route, method))
        if route == PREFIX + '/player-monitor':
            return copy.deepcopy(self.m)
        if route == PREFIX + '/source-collection':
            return {'version': '1.0.0'}
        if route == PREFIX + '/player-candidates':
            return {'coverage': 'partial', 'following': 'manual_approval_required', 'candidates': []}
        if route.endswith('/sources'):
            return self.sources
        if route == PREFIX + '/players/21':
            return {'fiks_id': 321, 'enabled': True, 'name': 'Syntetisk Spiller', 'snapshot': {'clubs': {'1': {'name': 'Test'}}}}
        raise AssertionError(route)

    def history(self, pid):
        return self.posts


class FakeResearcher:
    def __init__(self, packets=None, errors=None):
        self.scopes = []; self.packets = packets or []; self.errors = errors or []

    def collect(self, scope, history, since):
        self.scopes.append(scope)
        p, pages = source()
        return {'packets': copy.deepcopy(self.packets) if 'player' in scope else [], 'omitted': []}, pages, self.errors

    def verify(self, *args):
        return True, 'ok'


class Tests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.store = Store(Path(self.tmp.name) / 'state.sqlite3')
        self.store.set('paused', False); self.store.set('worker_seen', now())

    def tearDown(self):
        self.tmp.cleanup()

    def test_contract_all_enabled_and_paused(self):
        m = monitor(); m['players'].append({'id': 27, 'fiks_id': 999, 'name': 'Another', 'enabled': True})
        self.assertEqual([p['id'] for p in contract(m)], [21, 27])

    def test_contract_rejects_unsafe_policies(self):
        for field, bad in [('engine', 'rr_poll'), ('version', '0.9.5'), ('publication', 'automatic'), ('automatic_proposals', False), ('next_check', None), ('features', [])]:
            m = monitor(); m[field] = bad
            with self.subTest(field=field), self.assertRaises(Blocked):
                contract(m)

    def test_identity_collision_rejected(self):
        m = monitor(); m['players'][1]['fiks_id'] = 321
        with self.assertRaises(Blocked):
            contract(m)

    def test_rotation_all_twenty_buckets(self):
        at = dt.datetime(2026, 1, 1, tzinfo=UTC)
        buckets = {tuple(rotation(at + dt.timedelta(days=d)).values()) for d in range(20)}
        self.assertEqual(len(buckets), 20)
        self.assertEqual({b[0] for b in buckets}, {'Bremnes', 'Moster', 'Finnås', 'Bømlo', 'Rubbestadneset'})
        self.assertEqual(sorted({(b[1], b[2]) for b in buckets}), [(2006, 2010), (2011, 2015), (2016, 2020), (2021, 2026)])

    def test_canonical_duplicate(self):
        self.assertEqual(canonical('https://club.example/news/new/?utm_source=mail#top'), 'https://club.example/news/new')

    def test_read_evidence_and_exact_identity(self):
        p, pages = source(); at = dt.datetime.now(UTC)
        self.assertTrue(validate_source(p, monitor()['players'][0], pages, at.timestamp()-86400, at)['public_read'])
        p['payload']['fiks_id'] = 999
        with self.assertRaises(Blocked):
            validate_source(p, monitor()['players'][0], pages, 0, at)

    def test_unavailable_source_not_attested(self):
        p, pages = source(); del pages[p['payload']['url']]
        with self.assertRaises(Blocked):
            validate_source(p, monitor()['players'][0], pages, 0, dt.datetime.now(UTC))

    def test_old_source_rejected(self):
        p, pages = source(); p['payload']['published_at'] = '2001-01-01'
        with self.assertRaises(Blocked):
            validate_source(p, monitor()['players'][0], pages, 0, dt.datetime.now(UTC))

    def test_nora_never_requeued(self):
        p, pages = source(); p['payload']['url'] = 'https://www.instagram.com/reel/DeC2-KuISzj/'
        with self.assertRaisesRegex(Blocked, 'already_published_1088'):
            validate_source(p, monitor()['players'][0], pages, 0, dt.datetime.now(UTC))

    def test_title_player_is_not_verified_video(self):
        p, pages = source(); p['payload']['format'] = 'feature'; p['payload']['video'] = {'url': p['payload']['url'], 'publisher': 'Test', 'content_verified': True}
        with self.assertRaisesRegex(Blocked, 'video_content_not_verified'):
            validate_source(p, monitor()['players'][0], pages, 0, dt.datetime.now(UTC))

    def test_candidates_require_all_evidence_and_preserve_known(self):
        p, pages = source(); p['payload']['fiks_id'] = 324
        with self.assertRaisesRegex(Blocked, 'candidate_evidence_incomplete'):
            validate_candidate(p, pages, set(), dt.datetime.now(UTC))
        with self.assertRaisesRegex(Blocked, 'candidate_identity_existing'):
            validate_candidate(p, pages, {324}, dt.datetime.now(UTC))

    def test_timeout_after_post_readback_no_duplicate(self):
        remote, posts = [], []
        def transport(url, method, body, **unused):
            if method == 'POST':
                posts.append(body); remote.append({'url': body['url'], 'facts': body['facts']})
                raise Blocked('transport_uncertain')
            return remote
        wp = WordPress('https://www.radiorubben.no', 'test', 'synthetic', self.store, transport)
        payload = {'url': 'https://club.example/a', 'facts': ['synthetic']}
        find = lambda rows: rows[0] if rows else None
        self.assertEqual(wp.deliver(PREFIX + '/players/21/sources', payload, 'source-key', find), remote[0])
        wp.deliver(PREFIX + '/players/21/sources', payload, 'source-key', find)
        self.assertEqual(len(posts), 1)

    def test_uncertain_delivery_never_blindly_retried(self):
        posts = []
        def transport(url, method, body, **unused):
            if method == 'POST':
                posts.append(body); raise Blocked('transport_uncertain')
            return []
        wp = WordPress('https://www.radiorubben.no', 'test', 'synthetic', self.store, transport)
        for i in range(2):
            with self.assertRaises(Blocked):
                wp.deliver(PREFIX + '/players/21/sources', {'url': 'https://club.example/a'}, 'key', lambda rows: None)
        self.assertEqual(len(posts), 1)

    def test_no_alternative_write_route(self):
        wp = WordPress('https://www.radiorubben.no', 'test', 'synthetic', self.store, lambda **unused: None)
        for path in ['/wp/v2/posts', PREFIX + '/players/21', PREFIX + '/review']:
            with self.assertRaises(Blocked):
                wp.call(path, 'POST', {})

    def test_dry_run_processes_enabled_and_candidate_no_watermark(self):
        wp = FakeWP(); researcher = FakeResearcher([source()[0]])
        self.assertEqual(run(self.store, wp, researcher, 'dry', deliver=False), 'active')
        self.assertEqual(len(researcher.scopes), 2)
        self.assertEqual(researcher.scopes[0]['player']['id'], 21)
        self.assertIn('candidate_rotation', researcher.scopes[1])
        self.assertFalse(any(method == 'POST' for path, method in wp.calls))
        self.assertIsNone(self.store.checkpoint('player:21:321'))

    def test_source_failure_preserves_previous_checkpoint_and_data(self):
        self.store.success('player:21:321', '2026-01-01T00:00:00+00:00')
        wp = FakeWP(); wp.sources = [{'url': 'https://club.example/old', 'facts': ['old']}]
        researcher = FakeResearcher(errors=[{'host': 'club.example', 'reason': 'source_access_denied'}])
        self.assertEqual(run(self.store, wp, researcher, 'partial', deliver=True), 'partial')
        self.assertEqual(self.store.checkpoint('player:21:321'), '2026-01-01T00:00:00+00:00')
        self.assertEqual(wp.sources[0]['facts'], ['old'])

    def test_success_no_results_distinct_from_failure(self):
        self.assertEqual(run(self.store, FakeWP(), FakeResearcher(), 'empty', deliver=True), 'active')
        self.assertIsNotNone(self.store.checkpoint('player:21:321'))
        self.assertEqual(self.store.get('counts')['errors'], 0)

    def test_pause_resume_only_collection_and_manual_queue_idempotent(self):
        key = str(uuid.uuid4())
        control(self.store, 'start', key); control(self.store, 'start', key)
        with self.store.connect() as c:
            self.assertEqual(c.execute('SELECT count(*) FROM jobs').fetchone()[0], 1)
        control(self.store, 'pause', str(uuid.uuid4()))
        self.assertTrue(self.store.get('paused'))
        self.assertEqual(run(self.store, FakeWP(), FakeResearcher(), 'paused'), 'failed')
        control(self.store, 'resume', str(uuid.uuid4())); self.assertFalse(self.store.get('paused'))

    def test_worker_liveness_not_api_liveness(self):
        self.store.set('worker_seen', '2020-01-01T00:00:00+00:00')
        self.assertEqual(self.store.status()['state'], 'unavailable')

    def test_hmac_and_replay_protection(self):
        secret = 'synthetic-' * 8; at = str(int(time.time())); nonce = str(uuid.uuid4()); path = '/rrfr-source-collection/v1/status'
        msg = '\n'.join(['GET', path, at, nonce, hashlib.sha256(b'').hexdigest()])
        sig = hmac.new(secret.encode(), msg.encode(), hashlib.sha256).hexdigest()
        headers = {'X-RR-Time': at, 'X-RR-Nonce': nonce, 'X-RR-Signature': sig}
        self.assertTrue(auth(self.store, secret, 'GET', path, b'', headers))
        self.assertFalse(auth(self.store, secret, 'GET', path, b'', headers))
        self.assertFalse(auth(self.store, 'wrong', 'GET', path, b'', headers))

    def test_actual_control_http_is_fast_and_queues_once(self):
        secret = 'synthetic-' * 8
        http = server(self.store, secret, 0)
        thread = threading.Thread(target=http.serve_forever, daemon=True); thread.start()
        try:
            path = '/rrfr-source-collection/v1/control'; request_id = str(uuid.uuid4())
            body = json.dumps({'operation': 'start', 'request_id': request_id}).encode()
            def send():
                at = str(int(time.time())); nonce = str(uuid.uuid4())
                message = '\n'.join(['POST', path, at, nonce, hashlib.sha256(body).hexdigest()])
                headers = {'X-RR-Time': at, 'X-RR-Nonce': nonce,
                           'X-RR-Signature': hmac.new(secret.encode(), message.encode(), hashlib.sha256).hexdigest()}
                req = urllib.request.Request('http://127.0.0.1:' + str(http.server_port) + path, data=body, headers=headers)
                with urllib.request.urlopen(req, timeout=2) as response:
                    return json.load(response)
            start = time.monotonic(); result = send()
            self.assertLess(time.monotonic() - start, 2)
            self.assertEqual(result['request_state'], 'queued'); send()
            with self.store.connect() as c:
                self.assertEqual(c.execute('SELECT COUNT(*) FROM jobs').fetchone()[0], 1)
        finally:
            http.shutdown(); http.server_close(); thread.join()

    def test_failed_semantic_check_is_not_delivered(self):
        researcher = FakeResearcher([source()[0]])
        researcher.verify = lambda *args: (False, 'duplicate')
        wp = FakeWP()
        self.assertEqual(run(self.store, wp, researcher, 'duplicate', deliver=True), 'active')
        self.assertEqual(self.store.get('counts')['duplicates'], 1)
        self.assertFalse(any(method == 'POST' for path, method in wp.calls))

    def test_profile_paused_during_search_not_delivered(self):
        researcher = FakeResearcher([source()[0]]); wp = FakeWP()
        def verify(*args):
            wp.m['players'][0]['enabled'] = False
            return True, 'ok'
        researcher.verify = verify
        self.assertEqual(run(self.store, wp, researcher, 'changed', deliver=True), 'partial')
        self.assertIsNone(self.store.checkpoint('player:21:321'))
        self.assertFalse(any(method == 'POST' for path, method in wp.calls))

    def test_blocked_host_is_not_requested_again(self):
        reader = Reader(self.store); reader.blocked('source.example', 'access_denied')
        with patch('research.socket.getaddrinfo', return_value=[(2, 1, 6, '', ('8.8.8.8', 443))]), patch('research.urllib.request.build_opener') as opener:
            with self.assertRaisesRegex(Blocked, 'source_previously_blocked'):
                reader.raw('https://source.example/article')
            opener.assert_not_called()

    def test_private_source_address_never_requested(self):
        reader = Reader(self.store)
        with patch('research.socket.getaddrinfo', return_value=[(2, 1, 6, '', ('127.0.0.1', 443))]), patch('research.urllib.request.build_opener') as opener:
            with self.assertRaisesRegex(Blocked, 'non_public_source_address'):
                reader.raw('https://source.example/article')
            opener.assert_not_called()

    def test_robots_disallow_does_not_fetch_article(self):
        reader = Reader(self.store)
        with patch.object(reader, 'raw', return_value='User-agent: *\nDisallow: /') as transport:
            with self.assertRaisesRegex(Blocked, 'source_robots_disallow'):
                reader.read('https://source.example/article')
            self.assertEqual(transport.call_count, 1)
            self.assertTrue(transport.call_args.args[0].endswith('/robots.txt'))


if __name__ == '__main__':
    unittest.main()
