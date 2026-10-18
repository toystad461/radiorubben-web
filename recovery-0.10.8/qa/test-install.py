#!/usr/bin/env python3
import importlib.util,json,pathlib,shutil,sys,tempfile,unittest
sys.dont_write_bytecode=True
ROOT=pathlib.Path(__file__).resolve().parents[1];sys.path.insert(0,str(ROOT))
spec=importlib.util.spec_from_file_location('installer',ROOT/'install-test.py')
I=importlib.util.module_from_spec(spec);spec.loader.exec_module(I)

class InstallTests(unittest.TestCase):
    def setUp(self):
        self.tmp=tempfile.TemporaryDirectory();self.base=pathlib.Path(self.tmp.name)
        self.root=self.base/'wordpress';self.state=self.base/'receipt';self.kit=self.base/'kit'
        (self.root/'wp-content/plugins').mkdir(parents=True);(self.root/'wp-content/mu-plugins').mkdir()
        self.m=json.loads((ROOT/'evidence/scripts/details-release.json').read_text())
        shutil.copytree(ROOT/'source',self.kit/'source');shutil.copy(ROOT/'guard.php',self.kit/'guard.php')
        (self.root/'unrelated.txt').write_text('keep');(self.root/'database-marker').write_text('unchanged')
    def tearDown(self): self.tmp.cleanup()
    def install(self): I.install_files(self.root,self.state,self.kit,self.m)
    def test_full_install_and_repeat_rollback(self):
        self.install();I.check_tree(self.root/I.PLUGIN,I.expected(self.m))
        self.assertEqual((self.root/I.GUARD).read_bytes(),(self.kit/'guard.php').read_bytes())
        I.rollback_files(self.root,self.state);I.rollback_files(self.root,self.state)
        self.assertFalse((self.root/I.PLUGIN).exists());self.assertFalse((self.root/I.GUARD).exists())
        self.assertEqual((self.root/'unrelated.txt').read_text(),'keep')
        self.assertEqual((self.root/'database-marker').read_text(),'unchanged')
        self.assertTrue((self.state/'quarantine/plugin').is_dir())
    def test_existing_install_never_overwritten(self):
        (self.root/I.PLUGIN).mkdir();(self.root/I.PLUGIN/'manual.php').write_text('keep')
        with self.assertRaises(ValueError):self.install()
        self.assertFalse(self.state.exists())
    def test_corrupt_package_blocked_before_activation(self):
        (self.kit/'source/wordpress'/I.PLUGIN/'admin.css').write_text('corrupt')
        with self.assertRaises(ValueError):self.install()
        self.assertFalse((self.root/I.PLUGIN).exists());self.assertFalse((self.root/I.GUARD).exists())
    def test_rollback_preserves_concurrent_edits(self):
        self.install();p=self.root/I.PLUGIN/'admin.css';p.write_text('manual edit')
        with self.assertRaises(ValueError):I.rollback_files(self.root,self.state)
        self.assertEqual(p.read_text(),'manual edit');self.assertTrue((self.root/I.GUARD).exists())
    def test_rollback_preserves_guard_changes(self):
        self.install();p=self.root/I.GUARD;p.write_text('manual edit')
        with self.assertRaises(ValueError):I.rollback_files(self.root,self.state)
        self.assertTrue((self.root/I.PLUGIN).exists())
    def test_symlink_rejected(self):
        (self.root/I.PLUGIN).symlink_to(self.base/'outside')
        with self.assertRaises(ValueError):self.install()
    def test_extra_runtime_file_rejected(self):
        self.install();(self.root/I.PLUGIN/'unexpected.php').write_text('extra')
        with self.assertRaises(ValueError):I.rollback_files(self.root,self.state)
    def test_restart_after_partial_rollback(self):
        self.install();q=self.state/'quarantine';q.mkdir()
        (self.root/I.PLUGIN).rename(q/'plugin')
        I.rollback_files(self.root,self.state)
        self.assertFalse((self.root/I.GUARD).exists())
    def test_host_gate_blocks_here(self):
        if I.socket.gethostname()=='vps-5363103':self.skipTest('Host-only negative test')
        with self.assertRaises(ValueError):I.preflight('check')

if __name__=='__main__':unittest.main()
