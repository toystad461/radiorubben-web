"""Offline safety and false-positive regression tests. No real network calls."""
from datetime import datetime, timezone, timedelta
import importlib.util
import json
from pathlib import Path
import subprocess
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('probes', Path(__file__).parents[1] / 'scripts/read-only-probes.py')
p = importlib.util.module_from_spec(spec)
spec.loader.exec_module(p)
NOW = datetime(2026, 9, 27, 6, tzinfo=timezone.utc)


def weather(age=0, future=True):
    return json.dumps({'properties': {'meta': {'updated_at': p.iso(NOW - timedelta(hours=age))},
        'timeseries': [{'time': p.iso(NOW + timedelta(hours=1 if future else -1)),
        'data': {'instant': {'details': {'air_temperature': 12}}}}]}}).encode()


def rss(timestamp='Sun, 27 Sep 2026 05:00:00 +0000'):
    return ('<rss><channel><item><title>Test</title><link>https://example.org/item</link><pubDate>'
            + timestamp + '</pubDate></item></channel></rss>').encode()


class Probes(unittest.TestCase):
    def test_destinations_are_closed(self):
        for url in ['http://127.0.0.1/', 'https://www.radiorubben.no/wp-cron.php',
                    p.WEATHER + '&force=1', p.ASSET + '?purge=1', 'https://api.met.no/elsewhere']:
            self.assertFalse(p.allowed_url(url))
        self.assertTrue(p.allowed_url(p.WEATHER))

    def test_audio_no_arbitrary_hosts_or_credentials(self):
        self.assertTrue(p.allowed_url('https://streaming.radio.co/s123abc/listen', True))
        for url in ['https://streaming.radio.co.evil.org/sabc/listen',
                    'https://user:pass@streaming.radio.co/sabc/listen',
                    'https://streaming.radio.co/sabc/listen?token=secret',
                    'https://streaming.radio.co:443/sabc/listen',
                    'http://streaming.radio.co/sabc/listen']:
            self.assertFalse(p.allowed_url(url, True))

    def test_no_network_for_invalid_url(self):
        with patch.object(p.subprocess, 'run') as run:
            with self.assertRaises(ValueError):
                p.fetch('https://www.radiorubben.no/admin-ajax.php')
            run.assert_not_called()

    def fake_curl(self, command, **kwargs):
        self.assertEqual(command[:2], ['curl', '--disable'])
        self.assertEqual(command[command.index('--request') + 1], 'GET')
        for forbidden in ['-L', '--location', '--cookie', '--user', '--insecure', '--retry', '--data']:
            self.assertNotIn(forbidden, command)
        self.assertEqual(command[command.index('--max-time') + 1], '20')
        self.assertEqual(command[command.index('--max-filesize') + 1], '2000000')
        Path(command[command.index('--dump-header') + 1]).write_text(
            'HTTP/1.1 200 Connection established\r\n\r\nHTTP/2 200\r\nContent-Type: text/css\r\nSet-Cookie: secret\r\n\r\n')
        Path(command[command.index('--output') + 1]).write_bytes(b'body {color:red}')
        return subprocess.CompletedProcess(command, 0, b'200', b'')

    def test_transport_policy_and_header_redaction(self):
        with patch.object(p.subprocess, 'run', side_effect=self.fake_curl):
            raw, meta, error = p.fetch(p.ASSET)
        self.assertIsNone(error)
        self.assertEqual(meta['headers'], {'content-type': 'text/css'})
        self.assertNotIn('body {', json.dumps(meta))

    def test_redirect_not_pass(self):
        def redirect(command, **kwargs):
            self.fake_curl(command, **kwargs)
            return subprocess.CompletedProcess(command, 0, b'302', b'')
        with patch.object(p.subprocess, 'run', side_effect=redirect):
            self.assertEqual(p.fetch(p.ASSET)[2][0], 'FAIL')

    def test_partial_document_not_pass(self):
        def partial(command, **kwargs):
            self.fake_curl(command, **kwargs)
            return subprocess.CompletedProcess(command, 28, b'200', b'')
        with patch.object(p.subprocess, 'run', side_effect=partial):
            self.assertEqual(p.fetch(p.ASSET)[2][0], 'ERROR')

    def test_weather_fresh(self):
        self.assertEqual(p.validate_weather(weather(), NOW)[0], 'PASS')

    def test_weather_stale_future_or_no_coverage(self):
        for raw in [weather(7), weather(-1), weather(future=False)]:
            self.assertEqual(p.validate_weather(raw, NOW)[0], 'FAIL')

    def test_rss_recency(self):
        self.assertEqual(p.validate_rss(rss(), NOW)[0], 'PASS')
        self.assertEqual(p.validate_rss(rss('Wed, 01 Jan 2020 00:00:00 +0000'), NOW)[0], 'WARN')
        self.assertEqual(p.validate_rss(rss(''), NOW)[0], 'WARN')

    def test_rss_html_empty_and_entity_rejected(self):
        for raw in [b'<html>Blocked</html>', b'<rss><channel/></rss>',
                    b'<!DOCTYPE rss [<!ENTITY a "bad">]><rss/>',
                    '<!DOCTYPE rss><rss/>'.encode('utf-16')]:
            self.assertEqual(p.validate_rss(raw, NOW)[0], 'FAIL')

    def test_atom(self):
        raw = b'<feed xmlns="http://www.w3.org/2005/Atom"><entry><title>Test</title><link href="https://example.org/a"/><updated>2026-09-27T05:00:00Z</updated></entry></feed>'
        self.assertEqual(p.validate_rss(raw, NOW)[0], 'PASS')

    def test_nff_challenge_wrong_team_missing_section(self):
        self.assertEqual(p.validate_nff(b'<html>Access denied</html>', 'nff-rosters')[0], 'FAIL')
        base = '<div class="a_matchCard teamName"><a href="?fiksId=30365">Test</a></div>'
        self.assertEqual(p.validate_nff(base.encode(), 'nff-rosters')[0], 'WARN')
        self.assertEqual(p.validate_nff(base.replace('30365', '999').encode(), 'nff-events')[0], 'FAIL')
        events = base + '<div data-tab="kamphendelser"></div>'
        self.assertEqual(p.validate_nff(events.encode(), 'nff-events')[0], 'PASS')

    def test_malformed_payload_is_error(self):
        with patch.object(p, 'fetch', return_value=(b'not json', {}, None)):
            self.assertEqual(p.probe('Weather', p.WEATHER, 'weather', NOW)['status'], 'ERROR')

    def test_network_timeout_is_error_without_exception_details(self):
        with patch.object(p, 'fetch', side_effect=OSError('private value')):
            result = p.probe('Weather', p.WEATHER, 'weather', NOW)
        self.assertEqual(result['status'], 'ERROR')
        self.assertNotIn('private value', json.dumps(result))

    def test_audio_bytes_never_equal_playback_pass(self):
        meta = {'headers': {'content-type': 'audio/mpeg'}}
        with patch.object(p, 'fetch', return_value=(b'x' * 4096, meta, None)):
            self.assertEqual(p.probe('Audio', 'unused', 'audio', NOW)['status'], 'WARN')


if __name__ == '__main__':
    unittest.main()
