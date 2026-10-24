#!/usr/bin/env python3
"""Build the pinned nine-file release. No network and no production operations."""
import hashlib, json, pathlib, shutil, subprocess, sys
WEB = 'a9e11eab643e81557948c1a12531f714e6b9b0e1'
BASE = '6c36802ee04879f7e79d319911902a03a5157c04'
STUDIO = 'b77ac3d01eeff9b93763d6a086cab590f6cde7b2'
STUDIO_BASE = '96ff332d9b26f07b3be68c1d9cf2c9e58b6a7c60'
IMAGES = '5169765446b75f5c983695940241985beda2f7ad'
ROOT = pathlib.Path(__file__).resolve().parents[2]
ALLOWED = ['includes/club-automation.php','includes/newsroom.php','includes/player-corrections.php','radio-rubben-fotballrobot.php','includes/published-edits.php','includes/weekly-coverage.css','includes/weekly-coverage.js','includes/weekly-coverage.php']
PREFIX = 'wp-content/plugins/radio-rubben-fotballrobot/'
def read(repo, ref, path):
    return subprocess.check_output(['git','-C',str(repo),'show',ref+':'+path])
def sha(b): return hashlib.sha256(b).hexdigest()
def build(studio, out):
    if out.exists(): raise SystemExit('Output must be new')
    old = json.loads(read(ROOT,WEB,'scripts/learning-release.json'))['after']
    weekly = json.loads(read(ROOT,WEB,'docs/fotballrobot/WEEKLY-PUBLICATION-20261022.json'))['files']
    if set(weekly) != {'wordpress/'+PREFIX+p for p in ALLOWED}: raise SystemExit('Unexpected Web file set')
    before, after = dict(old), dict(old)
    payload = {}
    for path, record in weekly.items():
        target = path.removeprefix('wordpress/')
        before[target] = record['before_sha256']; after[target] = record['after_sha256']
        payload[target] = read(ROOT,WEB,path)
        if sha(payload[target]) != after[target]: raise SystemExit('Candidate mismatch: '+path)
        if before[target] is not None and sha(read(ROOT,BASE,path)) != before[target]: raise SystemExit('Base mismatch: '+path)
    target='studio-private/app/news-images.php'
    before[target]=sha(read(studio,STUDIO_BASE,'app/news-images.php'))
    payload[target]=read(studio,STUDIO,'app/news-images.php');after[target]=sha(payload[target])
    guards={}
    for relative in ['contract.php','data/01-BILDEREGISTER.json']:
        p='wp-content/plugins/rr-news-images/'+relative
        guards[p]=sha(read(ROOT,IMAGES,'wordpress/'+p))
    for relative in ['news-images-contract.php','data/01-BILDEREGISTER.json']:
        p='studio-private/app/'+relative
        guards[p]=sha(read(studio,STUDIO,'app/'+relative))
    assert guards['wp-content/plugins/rr-news-images/contract.php']==guards['studio-private/app/news-images-contract.php']
    assert guards['wp-content/plugins/rr-news-images/data/01-BILDEREGISTER.json']==guards['studio-private/app/data/01-BILDEREGISTER.json']
    before.update(guards); after.update(guards)
    manifest={'release':'weekly-newsroom-20261023','web_commit':WEB,'studio_commit':STUDIO,'images_commit':IMAGES,'before':before,'after':after,'changed':{p:after[p] for p in payload}}
    for p,b in payload.items():
        dest=out/'runtime'/p;dest.parent.mkdir(parents=True,exist_ok=True);dest.write_bytes(b)
    (out/'scripts').mkdir()
    (out/'manifest.json').write_text(json.dumps(manifest,indent=2)+'\n')
    for name in ['install.php','deploy.sh','state.php','postflight.php','diagnose.sh']:
        source=pathlib.Path(__file__).parent/name
        if source.exists():shutil.copyfile(source,out/'scripts'/name)
    probe=(pathlib.Path(__file__).parent/'preflight.php').read_text().replace('/* MANIFEST */',json.dumps(json.dumps(manifest)))
    (out/'scripts/preflight.php').write_text(probe)
    print(json.dumps({'changed_files':len(payload),'guarded_paths':len(before),'manifest_sha256':sha((out/'manifest.json').read_bytes())}))
if __name__=='__main__': build(pathlib.Path(sys.argv[1]).resolve(),pathlib.Path(sys.argv[2]).resolve())
