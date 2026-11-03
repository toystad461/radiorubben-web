#!/usr/bin/env python3
"""Existing Fotballrobot source collector; no publishing, profile creation or AI-job retries."""
from __future__ import annotations
import argparse
import datetime as dt
import fcntl
import hashlib
import hmac
import http.server
import json
import os
import re
import signal
import subprocess
import sys
import threading
import time
import uuid
from pathlib import Path
from core import Blocked, PREFIX, Store, UTC, VERSION, WordPress, canonical, contract, now, rotation, stamp, validate_candidate, validate_source
from research import Reader, Researcher


def safe_reason(exc):
    # Do not leak arbitrary exception text, URL credentials or source/model text to logs.
    s = str(exc)
    return s if isinstance(exc, Blocked) and re.fullmatch(r'[a-z0-9_]{1,100}', s) else 'internal_error'


def run(store, wp, researcher, run_id, deliver=False):
    at = dt.datetime.now(UTC).replace(microsecond=0)
    counts = dict(sources=0, candidates=0, duplicates=0, omitted=0, errors=0)
    store.set('run_id', run_id); store.set('last_start', at.isoformat()); store.set('counts', counts)
    store.set('mode', 'deliver' if deliver else 'dry-run')
    try:
        monitor = wp.call(PREFIX + '/player-monitor'); players = contract(monitor)
        # Capability handshake must exist; no fallback to posts, metadata or an old engine.
        handshake = wp.call(PREFIX + '/source-collection')
        if handshake.get('version') != VERSION:
            raise Blocked('collection_plugin_not_installed')
        candidates = wp.call(PREFIX + '/player-candidates')
        if candidates.get('coverage') != 'partial' or candidates.get('following') != 'manual_approval_required':
            raise Blocked('candidate_contract_mismatch')
        known = {p['fiks_id'] for p in monitor['players']} | {p['fiks_id'] for p in candidates['candidates']}
        scopes = [({'player': p}, f'player:{p["id"]}:{p["fiks_id"]}') for p in players]
        scopes.append(({'candidate_rotation': rotation(at), 'known_identities': sorted(known)},
                       'candidate:' + json.dumps(rotation(at), sort_keys=True)))
        for scope, scope_id in scopes:
            if store.get('paused', True):
                raise Blocked('collection_paused')
            p = scope.get('player'); pid = p['id'] if p else 0
            completed = False
            try:
                current_monitor = wp.call(PREFIX + '/player-monitor'); active = contract(current_monitor)
                if p:
                    if not any(x['id'] == pid and x['fiks_id'] == p['fiks_id'] for x in active):
                        store.event(run_id, 'omitted', 'profile_paused_since_run_start', pid)
                        counts['omitted'] += 1; continue
                    state = wp.call(f'{PREFIX}/players/{pid}')
                    if state.get('fiks_id') != p['fiks_id'] or state.get('enabled') is not True:
                        raise Blocked('profile_identity_or_status_changed')
                    # Exact current WP profile; clubs are current observations, not transfer news.
                    scope['profile'] = {k: state.get(k) for k in ['name', 'fiks_id', 'club_note', 'last_checked', 'error']}
                    scope['profile']['clubs'] = (state.get('snapshot') or {}).get('clubs')
                    existing = wp.call(f'{PREFIX}/players/{pid}/sources')
                    previous = {'posts': wp.history(pid), 'sources': existing, 'reviews': p.get('reviews', [])}
                else:
                    previous = {'candidates': candidates['candidates'], 'profiles': monitor['players']}
                checkpoint = store.checkpoint(scope_id)
                since = max(stamp(checkpoint) if checkpoint else 0, at.timestamp() - 7 * 86400)
                result, pages, errors = researcher.collect(scope, previous, dt.datetime.fromtimestamp(since, UTC).isoformat())
                for e in errors:
                    counts['errors'] += 1
                    store.event(run_id, 'source_error', e['reason'] + ' (' + e['host'] + ')', pid)
                for item in result.get('omitted', []):
                    reason = item.get('reason', 'evidence')
                    if reason not in ['duplicate', 'old', 'identity', 'evidence', 'irrelevant', 'video_unverified']:
                        reason = 'evidence'
                    counts['duplicates' if reason == 'duplicate' else 'omitted'] += 1
                    store.event(run_id, 'omitted', reason, pid)
                packet_error = False
                for packet in result['packets']:
                    if store.get('paused', True):
                        raise Blocked('collection_paused')
                    try:
                        payload = validate_source(packet, p, pages, since, at) if p else validate_candidate(packet, pages, known, at)
                        if p and any(canonical(s['url']) == payload['url'] for s in existing):
                            counts['duplicates'] += 1; store.event(run_id, 'duplicate', 'source_url_already_stored', pid); continue
                        supported, reason = researcher.verify(packet, pages, previous, scope, since)
                        if not supported:
                            counts['duplicates' if reason == 'duplicate' else 'omitted'] += 1
                            store.event(run_id, 'omitted', 'semantic_check_' + (reason if reason in ['duplicate', 'evidence', 'identity', 'old'] else 'evidence'), pid)
                            continue
                        # Preserve only short evidence bindings and hashes, never complete articles/videos.
                        audit = {'proofs': packet['proofs'], 'page_hashes': {u: x['sha256'] for u, x in pages.items()}, 'verified_at': now()}
                        evidence_id = hashlib.sha256(json.dumps(payload, sort_keys=True).encode()).hexdigest()
                        store.set('evidence:' + evidence_id, audit)
                        if not deliver:
                            store.event(run_id, 'dry_run', 'verified_packet_not_delivered', pid)
                            continue
                        fresh = wp.call(PREFIX + '/player-monitor'); enabled = contract(fresh)
                        if store.get('paused', True):
                            raise Blocked('collection_paused')
                        if p:
                            if not any(x['id'] == pid and x['fiks_id'] == payload['fiks_id'] for x in enabled):
                                raise Blocked('profile_paused_before_delivery')
                            route = f'{PREFIX}/players/{pid}/sources'
                            def find(rows):
                                for x in rows:
                                    if canonical(x['url']) == payload['url']:
                                        if x['facts'] != payload['facts'] or x['title'] != payload['title']:
                                            raise Blocked('stored_source_differs_preserve_human_data')
                                        return x
                                return None
                            saved = wp.deliver(route, payload, f'{pid}:' + hashlib.sha256(payload['url'].encode()).hexdigest(), find)
                            existing.append(saved); previous['sources'] = existing
                            counts['sources'] += 1; store.event(run_id, 'source', 'source_confirmed_in_inbox_not_published', pid)
                        else:
                            current = wp.call(PREFIX + '/player-candidates')
                            if payload['fiks_id'] in {x['fiks_id'] for x in fresh['players']} | {x['fiks_id'] for x in current['candidates']}:
                                counts['duplicates'] += 1; continue
                            def find(result):
                                for x in result['candidates']:
                                    if x['fiks_id'] == payload['fiks_id']:
                                        if x.get('status') != 'pending':
                                            raise Blocked('candidate_human_status_preserved')
                                        return x
                                return None
                            saved = wp.deliver(PREFIX + '/player-candidates', payload, 'candidate:' + str(payload['fiks_id']), find)
                            known.add(payload['fiks_id']); candidates['candidates'].append(saved)
                            counts['candidates'] += 1; store.event(run_id, 'candidate', 'candidate_pending_manual_approval')
                    except Blocked as e:
                        counts['omitted'] += 1; store.event(run_id, 'omitted', safe_reason(e), pid)
                        # A technical or validation failure must not advance the successful checkpoint.
                        packet_error = True
                completed = not errors and not packet_error
                if completed:
                    # Dry runs must not advance delivery watermarks or hide findings from the first real run.
                    if deliver:
                        store.success(scope_id, at.isoformat())
                    store.event(run_id, 'search_success', 'search_completed_no_new_delivery' if not result['packets'] else 'search_completed', pid)
                else:
                    counts['errors'] += int(packet_error)
            except Exception as e:
                counts['errors'] += 1; store.event(run_id, 'error', safe_reason(e), pid)
            finally:
                store.set('counts', counts)
        state = 'partial' if counts['errors'] else 'active'
        if state == 'active':
            store.set('last_success', now())
        store.set('last_result', state)
        return state
    except Exception as e:
        counts['errors'] += 1; store.set('counts', counts); store.set('last_result', 'failed')
        store.event(run_id, 'error', safe_reason(e))
        return 'failed'


def auth(store, secret, method, path, body, headers):
    at = headers.get('X-RR-Time', ''); nonce = headers.get('X-RR-Nonce', ''); signature = headers.get('X-RR-Signature', '')
    if not re.fullmatch(r'\d{10}', at) or abs(time.time() - int(at)) > 60 or not re.fullmatch(r'[a-f0-9-]{36}', nonce):
        return False
    message = '\n'.join([method, path, at, nonce, hashlib.sha256(body).hexdigest()])
    if not hmac.compare_digest(hmac.new(secret.encode(), message.encode(), hashlib.sha256).hexdigest(), signature):
        return False
    with store.connect() as c:
        c.execute('DELETE FROM nonces WHERE at < ?', (int(time.time()) - 120,))
        try:
            c.execute('INSERT INTO nonces VALUES (?,?)', (nonce, int(at)))
        except Exception:
            return False
    return True


def control(store, operation, request_id):
    if operation not in ['start', 'pause', 'resume'] or not re.fullmatch(r'[a-f0-9-]{36}', request_id):
        raise Blocked('invalid_control')
    if operation == 'pause':
        store.set('paused', True)
    elif operation == 'resume':
        store.set('paused', False)
    elif not store.get('paused', True):
        request_id = store.enqueue(request_id)
    result = store.status(); result['request_id'] = request_id
    with store.connect() as c:
        row = c.execute('SELECT state FROM jobs WHERE id=?', (request_id,)).fetchone()
    result['request_state'] = row[0] if row else 'not_queued'
    return result


def server(store, secret, port):
    class Handler(http.server.BaseHTTPRequestHandler):
        def log_message(self, *args):
            pass

        def handle_request(self):
            try:
                self.connection.settimeout(5)
                size = int(self.headers.get('Content-Length', '0'))
                if size < 0 or size > 2000:
                    raise Blocked('invalid_size')
                body = self.rfile.read(size)
                if not auth(store, secret, self.command, self.path, body, self.headers):
                    self.send_error(403); return
                if self.command == 'GET' and self.path == '/rrfr-source-collection/v1/status':
                    value = store.status()
                elif self.command == 'POST' and self.path == '/rrfr-source-collection/v1/control':
                    value = json.loads(body)
                    value = control(store, value['operation'], value['request_id'])
                else:
                    self.send_error(404); return
                encoded = json.dumps(value, ensure_ascii=False).encode()
                self.send_response(200); self.send_header('Content-Type', 'application/json'); self.send_header('Cache-Control', 'no-store')
                self.send_header('Content-Length', str(len(encoded))); self.end_headers(); self.wfile.write(encoded)
            except Exception:
                self.send_error(400)

        do_GET = handle_request
        do_POST = handle_request
    return http.server.ThreadingHTTPServer(('127.0.0.1', port), Handler)


def main():
    os.umask(0o077)
    parser = argparse.ArgumentParser()
    parser.add_argument('command', choices=['serve', 'run', 'enqueue', 'status'])
    parser.add_argument('--run-id', default='')
    args = parser.parse_args()
    state = Path(os.environ.get('RRFR_COLLECTION_STATE', '/var/lib/rrfr-source-collection'))
    store = Store(state / 'state.sqlite3')
    mode = os.environ.get('RRFR_COLLECTION_MODE', 'dry-run')
    if mode not in ['dry-run', 'deliver']:
        raise Blocked('invalid_mode')
    store.set('mode', mode)
    if args.command == 'status':
        print(json.dumps(store.status(), ensure_ascii=False)); return 0
    if args.command == 'enqueue':
        if not store.get('paused', True):
            store.enqueue('scheduled-' + str(int(time.time()) // 21600))
        return 0
    if args.command == 'run':
        # A process lock survives daemon faults and is released by the kernel on exit.
        with (state / 'run.lock').open('a') as lock:
            try:
                fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
            except BlockingIOError:
                return 3
            run_id = args.run_id or str(uuid.uuid4())
            try:
                for required in ['RRFR_WP_USER', 'RRFR_WP_APP_PASSWORD', 'RRFR_OPENAI_KEY', 'RRFR_RESEARCH_MODEL']:
                    if not os.environ.get(required):
                        raise Blocked('missing_config_' + required.lower())
                wp = WordPress('https://www.radiorubben.no', os.environ['RRFR_WP_USER'], os.environ['RRFR_WP_APP_PASSWORD'], store)
                priorities = json.loads(Path(__file__).with_name('priorities.json').read_text())
                researcher = Researcher(os.environ['RRFR_OPENAI_KEY'], os.environ['RRFR_RESEARCH_MODEL'], Reader(store), priorities)
                return 0 if run(store, wp, researcher, run_id, deliver=mode == 'deliver') == 'active' else 1
            except Exception as e:
                store.event(run_id, 'error', safe_reason(e)); store.set('last_result', 'failed'); return 1
    secret = os.environ.get('RRFR_CONTROL_SECRET', '')
    if len(secret) < 32:
        raise Blocked('missing_control_secret')
    # Fail interrupted jobs closed. Delivery journal reconciles ambiguous POSTs on a future run.
    with store.connect() as c:
        c.execute("UPDATE jobs SET state='interrupted' WHERE state='running'")
    http = server(store, secret, int(os.environ.get('RRFR_CONTROL_PORT', '9086')))
    threading.Thread(target=http.serve_forever, daemon=True).start()
    child = None; child_start = 0; job_id = None; stop = False
    def stopping(*unused):
        nonlocal stop
        stop = True
    signal.signal(signal.SIGTERM, stopping); signal.signal(signal.SIGINT, stopping)
    try:
        while not stop:
            store.set('worker_seen', now())
            if child:
                if time.monotonic() - child_start > 2700:
                    child.kill(); child.wait(); store.event(job_id, 'error', 'run_time_budget_exhausted')
                if child.poll() is not None:
                    with store.connect() as c:
                        c.execute('UPDATE jobs SET state=? WHERE id=?', ('completed' if child.returncode == 0 else 'failed', job_id))
                    child = None
            if child is None and not store.get('paused', True):
                with store.connect() as c:
                    c.execute('BEGIN IMMEDIATE')
                    row = c.execute("SELECT id FROM jobs WHERE state='queued' ORDER BY created LIMIT 1").fetchone()
                    if row:
                        job_id = row[0]; c.execute("UPDATE jobs SET state='running' WHERE id=?", (job_id,))
                if row:
                    child = subprocess.Popen([sys.executable, str(Path(__file__).resolve()), 'run', '--run-id', job_id], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
                    child_start = time.monotonic()
            time.sleep(2)
    finally:
        if child:
            child.terminate()
            try:
                child.wait(timeout=10)
            except subprocess.TimeoutExpired:
                child.kill(); child.wait()
        http.shutdown(); http.server_close()
    return 0


if __name__ == '__main__':
    try:
        sys.exit(main())
    except Exception as e:
        print(safe_reason(e), file=sys.stderr); sys.exit(1)
