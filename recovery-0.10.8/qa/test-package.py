#!/usr/bin/env python3
import hashlib, json, pathlib, shutil, subprocess, sys, tempfile, unittest
sys.dont_write_bytecode=True
ROOT=pathlib.Path(__file__).resolve().parents[1];sys.path.insert(0,str(ROOT))
from verify import verify

class PackageTests(unittest.TestCase):
    def setUp(self):
        self.temp=tempfile.TemporaryDirectory();self.base=pathlib.Path(self.temp.name);self.copy=self.base/'kit'
        shutil.copytree(ROOT,self.copy)
    def tearDown(self):self.temp.cleanup()
    def test_corruption(self):
        (self.copy/'guard.php').write_text('changed')
        with self.assertRaises(ValueError):verify(self.copy)
    def test_missing(self):
        (self.copy/'guard.php').unlink()
        with self.assertRaises(ValueError):verify(self.copy)
    def test_extra(self):
        (self.copy/'unexpected.txt').write_text('extra')
        with self.assertRaises(ValueError):verify(self.copy)
    def test_manifest_anchor(self):
        name='evidence/scripts/details-release.json';p=self.copy/name
        p.write_bytes(p.read_bytes()+b'\n')
        inventory=json.loads((self.copy/'FILES.json').read_text())
        inventory[name]=hashlib.sha256(p.read_bytes()).hexdigest()
        (self.copy/'FILES.json').write_text(json.dumps(inventory))
        with self.assertRaisesRegex(ValueError,'Unrecognized'):verify(self.copy)
    def test_reproducible_archive(self):
        outputs=[self.base/'one.zip',self.base/'two.zip']
        for out in outputs:subprocess.check_call([sys.executable,str(ROOT/'rebuild-zip.py'),str(ROOT),str(out)])
        self.assertEqual(outputs[0].read_bytes(),outputs[1].read_bytes())

if __name__=='__main__':unittest.main()
