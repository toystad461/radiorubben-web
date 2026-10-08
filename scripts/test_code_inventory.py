#!/usr/bin/env python3
"""Isolated fixtures only; never reads production or uses network access."""
import copy
import json
import tempfile
import unittest
from datetime import datetime, timedelta, timezone
from pathlib import Path
from compare_code_inventory import compare, read_manifest

NOW = datetime(2026, 10, 8, 10, 0, tzinfo=timezone.utc)


class InventoryTests(unittest.TestCase):
    def setUp(self):
        self.expected = {'schema_version': 1, 'scope': 'next-theme', 'source_commit': 'a'*40,
                         'files': [{'path': 'front-page.php', 'sha256': 'b'*64}]}
        self.observed = {'schema_version': 1, 'scope': 'next-theme', 'observed_at': NOW.isoformat(),
                         'complete': True, 'errors': [], 'files': copy.deepcopy(self.expected['files'])}

    def result(self):
        return compare(self.expected, self.observed, now=NOW)

    def test_match_never_authorizes_deploy(self):
        self.assertEqual(self.result()['status'], 'scope_matches')
        self.assertIs(self.result()['deployment_authorized'], False)

    def test_changed(self):
        self.observed['files'][0]['sha256'] = 'c'*64
        self.assertEqual(self.result()['changed'], ['front-page.php'])
        self.assertEqual(self.result()['status'], 'blocked')

    def test_missing_and_extra(self):
        self.observed['files'][0]['path'] = 'untracked.php'
        self.assertEqual(self.result()['missing'], ['front-page.php'])
        self.assertEqual(self.result()['unexpected'], ['untracked.php'])

    def test_incomplete(self):
        for value in [False, None, 1, 'true']:
            with self.subTest(value=value):
                self.observed['complete'] = value
                self.assertIn('incomplete_inventory', self.result()['blockers'])

    def test_collection_error(self):
        self.observed['errors'] = ['unreadable file']
        self.assertIn('unresolved_collection_errors', self.result()['blockers'])

    def test_missing_errors_field(self):
        del self.observed['errors']
        self.assertIn('unresolved_collection_errors', self.result()['blockers'])

    def test_stale(self):
        self.observed['observed_at'] = (NOW-timedelta(hours=25)).isoformat()
        self.assertIn('stale_inventory', self.result()['blockers'])

    def test_future(self):
        self.observed['observed_at'] = (NOW+timedelta(seconds=1)).isoformat()
        self.assertIn('future_observation', self.result()['blockers'])

    def test_timezone_required(self):
        self.observed['observed_at'] = '2026-10-08T10:00:00'
        with self.assertRaises(ValueError): self.result()

    def test_scope_mismatch(self):
        self.observed['scope'] = 'studio'
        with self.assertRaises(ValueError): self.result()

    def test_commit_required(self):
        for value in ['main', 'a'*7, None]:
            with self.subTest(value=value):
                self.expected['source_commit'] = value
                with self.assertRaises(ValueError): self.result()

    def test_schema(self):
        for value in [True, '1', 2, None]:
            with self.subTest(value=value):
                self.observed['schema_version'] = value
                with self.assertRaises(ValueError): self.result()

    def test_bad_paths(self):
        paths=['../a.php', '/a.php', 'a//b.php', './a.php', 'a/../b.php',
               'C:/a.php', 'a\\b.php', 'a\n.php', 'a\x00.php', 'a\x7f.php']
        for path in paths:
            with self.subTest(path=repr(path)):
                self.observed['files'][0]['path'] = path
                with self.assertRaises(ValueError): self.result()

    def test_private_paths(self):
        for path in ['wp-config.php', 'config/local.php', '.env', '.env.production',
                     'uploads/x.php', 'data/x.json', 'backups/a.php', '.git/config', 'id_ed25519']:
            with self.subTest(path=path):
                self.observed['files'][0]['path'] = path
                with self.assertRaises(ValueError): self.result()

    def test_spaces_and_unicode_are_not_normalized(self):
        self.observed['files'][0]['path'] = 'head (2)-ø.php'
        self.assertEqual(self.result()['unexpected'], ['head (2)-ø.php'])

    def test_hash_invalid(self):
        for value in ['x'*64, 'B'*64, 'b'*63, None]:
            with self.subTest(value=value):
                self.observed['files'][0]['sha256'] = value
                with self.assertRaises(ValueError): self.result()

    def test_duplicate_file(self):
        self.observed['files'].append(copy.deepcopy(self.observed['files'][0]))
        with self.assertRaises(ValueError): self.result()

    def test_unexpected_fields(self):
        self.observed['files'][0]['contents'] = 'must not be exported'
        with self.assertRaises(ValueError): self.result()

    def test_symlink_entry(self):
        self.observed['files'][0]['type'] = 'symlink'
        with self.assertRaises(ValueError): self.result()

    def test_empty_manifest(self):
        self.observed['files'] = []
        with self.assertRaises(ValueError): self.result()

    def test_freshness_cannot_be_disabled(self):
        for age in [0, -1, 25, True]:
            with self.subTest(age=age):
                with self.assertRaises(ValueError):
                    compare(self.expected, self.observed, now=NOW, max_age_hours=age)

    def test_duplicate_json_key_rejected(self):
        with tempfile.TemporaryDirectory() as temp:
            path = Path(temp)/'manifest.json'
            path.write_text('{"complete":false,"complete":true}')
            with self.assertRaises(ValueError): read_manifest(path)

    def test_symlink_manifest_rejected(self):
        with tempfile.TemporaryDirectory() as temp:
            path = Path(temp)/'real.json'
            path.write_text(json.dumps(self.expected))
            link = Path(temp)/'link.json'
            link.symlink_to(path)
            with self.assertRaises(ValueError): read_manifest(link)

    def test_read_roundtrip(self):
        with tempfile.TemporaryDirectory() as temp:
            path = Path(temp)/'manifest.json'
            path.write_text(json.dumps(self.expected), encoding='utf-8')
            self.assertEqual(read_manifest(path), self.expected)


if __name__ == '__main__':
    unittest.main()
