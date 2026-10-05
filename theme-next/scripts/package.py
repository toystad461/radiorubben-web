from pathlib import Path
import zipfile,hashlib,json
b=Path(__file__).resolve().parents[1];out=b/'dist';out.mkdir(exist_ok=True)
packages={'radio-rubben-next':'2.0.0-rc.3','rr-editorial-contract':'1.0.0-rc.1','radio-rubben-child':'1.0.0','rr-site-functions':'1.0.0-rc.4'}
manifest=[]
for name,version in packages.items():
 root=b/name;dest=out/(name+'-'+version+'.zip');files=[]
 with zipfile.ZipFile(dest,'w',zipfile.ZIP_DEFLATED,compresslevel=9) as z:
  for p in sorted(root.rglob('*')):
   if p.is_file() and not any(x.startswith('.') for x in p.relative_to(root).parts):
    assert p.suffix.lower() in ['.php','.css','.js','.json','.md','.txt','.png','.webp', '.jpg','.svg'],str(p)
    z.write(p,p.relative_to(b));files.append(str(p.relative_to(root)))
 with zipfile.ZipFile(dest) as z: assert z.testzip() is None
 manifest.append({'file':dest.name,'bytes':dest.stat().st_size,'sha256':hashlib.sha256(dest.read_bytes()).hexdigest(),'files':len(files)})
(out/'manifest.json').write_text(json.dumps(manifest,indent=2))
(out/'SHA256SUMS.txt').write_text(''.join(x['sha256']+'  '+x['file']+'\n' for x in manifest))
print(json.dumps(manifest,indent=2))
