#!/usr/bin/env python3
import hashlib,json,pathlib,shutil,sys
here=pathlib.Path(__file__).resolve().parent;root=here.parents[1];out=pathlib.Path(sys.argv[1]);assert not out.exists()
m=json.loads((here/'theme-manifest.json').read_text())
b=(root/'theme-next/radio-rubben-next/template-parts/site/navigation.php').read_bytes()
for p,h in m['changed'].items():
    assert hashlib.sha256(b).hexdigest()==h
    d=out/'runtime'/p;d.parent.mkdir(parents=True,exist_ok=True);d.write_bytes(b)
shutil.copytree(here,out/'scripts')
shutil.copyfile(here/'theme-manifest.json',out/'manifest.json')
shutil.copyfile(here/'theme-install.php',out/'scripts/install.php')
shutil.copyfile(here/'theme-deploy.sh',out/'scripts/deploy.sh')
s=(here/'preflight.php').read_text().replace('/* MANIFEST */',json.dumps(json.dumps(m)))
(out/'scripts/preflight.php').write_text(s)
print(hashlib.sha256((out/'manifest.json').read_bytes()).hexdigest())
