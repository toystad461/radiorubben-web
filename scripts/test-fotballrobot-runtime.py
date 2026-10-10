#!/usr/bin/env python3
"""Regression checks for false matches and changed production evidence."""
import importlib.util
import json
from pathlib import Path
import shutil
import tempfile
import unittest
import sys

sys.dont_write_bytecode = True

spec = importlib.util.spec_from_file_location('verifier', Path(__file__).with_name('verify-fotballrobot-runtime.py'))
verifier = importlib.util.module_from_spec(spec)
spec.loader.exec_module(verifier)


class RuntimeManifestTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.plugin = self.root / 'plugin'
        self.docs = self.root / 'docs'
        shutil.copytree(verifier.REPO / 'wordpress/wp-content/plugins/radio-rubben-fotballrobot', self.plugin)
        shutil.copytree(verifier.MANIFEST.parent, self.docs)
        self.manifest = self.docs / 'runtime-manifest.json'

    def verify(self):
        return verifier.verify(self.manifest, self.plugin)

    def test_exact_independent_copy(self):
        self.assertEqual(self.verify()['runtime_files'], 32)
        before = self.manifest.read_bytes()
        self.assertEqual(self.verify(), self.verify())
        self.assertEqual(before, self.manifest.read_bytes())

    def test_changed_runtime(self):
        with (self.plugin / 'includes/player-news-filter.php').open('ab') as file:
            file.write(b'\n// drift\n')
        with self.assertRaisesRegex(ValueError, 'Content mismatch'):
            self.verify()

    def test_missing_file(self):
        (self.plugin / 'includes/newsroom.php').unlink()
        with self.assertRaisesRegex(ValueError, 'Inventory mismatch'):
            self.verify()

    def test_extra_file_including_non_php(self):
        (self.plugin / 'unreviewed.txt').write_text('extra')
        with self.assertRaisesRegex(ValueError, 'Inventory mismatch'):
            self.verify()

    def test_symlink_with_identical_bytes(self):
        file = self.plugin / 'includes/newsroom.php'
        original = self.root / 'same.php'
        file.rename(original)
        file.symlink_to(original)
        with self.assertRaisesRegex(ValueError, 'Symlink forbidden'):
            self.verify()

    def test_directory_symlink(self):
        original = self.root / 'same-includes'
        (self.plugin / 'includes').rename(original)
        (self.plugin / 'includes').symlink_to(original, target_is_directory=True)
        with self.assertRaisesRegex(ValueError, 'Symlink forbidden'):
            self.verify()

    def test_modified_evidence(self):
        with (self.docs / 'evidence/mobile-newsroom-release.json').open('a') as file:
            file.write('\n')
        with self.assertRaisesRegex(ValueError, 'Evidence changed'):
            self.verify()

    def test_duplicate_inventory(self):
        m = json.loads(self.manifest.read_text())
        m['runtime_files'].append(m['runtime_files'][0])
        self.manifest.write_text(json.dumps(m))
        with self.assertRaisesRegex(ValueError, 'Duplicate manifest path'):
            self.verify()

    def test_duplicate_json_key(self):
        self.manifest.write_text('{"schema_version":1,"schema_version":1}')
        with self.assertRaisesRegex(ValueError, 'Duplicate JSON key'):
            self.verify()

    def test_mode_drift(self):
        (self.plugin / 'radio-rubben-fotballrobot.php').chmod(0o755)
        with self.assertRaisesRegex(ValueError, 'executable mode'):
            self.verify()

    def test_wrong_receipt(self):
        m = json.loads(self.manifest.read_text())
        m['runtime_files'][0]['receipt']['path'] = 'missing'
        self.manifest.write_text(json.dumps(m))
        with self.assertRaisesRegex(ValueError, 'No matching production receipt'):
            self.verify()


if __name__ == '__main__':
    unittest.main()
