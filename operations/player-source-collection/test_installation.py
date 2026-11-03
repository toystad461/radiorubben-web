"""Selective filesystem installer: synthetic target only; no WordPress or network."""
import contextlib
import io
import json
import subprocess
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch
import install_wordpress as installer


class InstallerTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self.tmp.cleanup)
        self.root = Path(self.tmp.name) / 'wordpress'
        self.target = self.root / 'wp-content/plugins/radio-rubben-fotballrobot'
        self.target.mkdir(parents=True)
        (self.target / 'includes').mkdir()
        self.backup = Path(self.tmp.name) / 'private-backup'
        self.before = {}
        for name in installer.FILES[1:]:
            (self.target / name).write_text('original bytes: ' + name)
            self.before[name] = installer.digest(self.target / name)

    def invoke(self, *extra, verify_fails=False):
        def run(command, **kwargs):
            if command[0] == 'test-wp' and verify_fails:
                raise subprocess.CalledProcessError(1, command)
        argv = ['installer', '--wp-root', str(self.root), '--backup-dir', str(self.backup), *extra]
        with patch.object(installer, 'BEFORE', self.before), patch('sys.argv', argv), patch.object(installer.subprocess, 'run', side_effect=run), contextlib.redirect_stdout(io.StringIO()):
            installer.main()

    def assert_original(self):
        self.assertFalse((self.target / installer.FILES[0]).exists())
        for name, expected in self.before.items():
            self.assertEqual(installer.digest(self.target / name), expected)

    def test_plan_never_writes(self):
        self.invoke()
        self.assert_original()
        self.assertFalse(self.backup.exists())

    def test_changed_baseline_stops_before_writes(self):
        (self.target / installer.FILES[1]).write_text('concurrent change')
        with self.assertRaisesRegex(RuntimeError, 'differs'):
            self.invoke('--apply')
        self.assertFalse(self.backup.exists())
        self.assertEqual((self.target / installer.FILES[1]).read_text(), 'concurrent change')

    def test_wordpress_verification_failure_restores_only_files(self):
        with self.assertRaises(subprocess.CalledProcessError):
            self.invoke('--apply', '--verify-wp-cli', 'test-wp', verify_fails=True)
        self.assert_original()
        self.assertTrue(self.backup.exists())

    def test_apply_readback_and_private_receipt(self):
        self.invoke('--apply', '--verify-wp-cli', 'test-wp')
        receipt = json.loads((self.backup / 'receipt.json').read_text())
        self.assertEqual(receipt['action'], 'installed_files_only')
        self.assertEqual(self.backup.stat().st_mode & 0o777, 0o700)
        for name, hashes in receipt['files'].items():
            self.assertEqual(installer.digest(self.target / name), hashes['after'])
            if hashes['before']:
                self.assertEqual(installer.digest(self.backup / name), hashes['before'])


if __name__ == '__main__':
    unittest.main()
