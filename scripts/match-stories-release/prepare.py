#!/usr/bin/env python3
"""Package only the approved eight runtime files, with immutable source checks."""
import hashlib,json,pathlib,shutil,subprocess,sys
root=pathlib.Path(__file__).resolve().parents[2]
here=pathlib.Path(__file__).resolve().parent
out=pathlib.Path(sys.argv[1]);assert not out.exists()
m=json.loads((here/'manifest.json').read_text())
for p,h in m['changed'].items():
    b=subprocess.check_output(['git','-C',str(root),'show',m['web_commit']+':wordpress/'+p])
    assert hashlib.sha256(b).hexdigest()==h,p
    d=out/'runtime'/p;d.parent.mkdir(parents=True,exist_ok=True);d.write_bytes(b)
shutil.copytree(here,out/'scripts')
shutil.copyfile(here/'manifest.json',out/'manifest.json')
shutil.copyfile(root/'docs/fotballrobot/SPORT-NAVIGATION-20261024.json',out/'navigation.json')
s=(here/'preflight.php').read_text().replace('/* MANIFEST */',json.dumps(json.dumps(m)))
(out/'scripts/preflight.php').write_text(s)
print(hashlib.sha256((out/'manifest.json').read_bytes()).hexdigest())
