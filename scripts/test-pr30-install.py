#!/usr/bin/env python3
"""Isolated real installer tests: drift rejection, atomic install and ownership-aware rollback."""
import argparse, hashlib, json, os, re, subprocess, tempfile
from pathlib import Path
r=Path(__file__).resolve().parents[1]
a=argparse.ArgumentParser();a.add_argument('--php',default='php');args=a.parse_args()
m=json.loads((r/'scripts/pr30-release.json').read_text())
with tempfile.TemporaryDirectory() as folder:
    t=Path(folder);live=t/'live';stage=t/'stage';backup=t/'backup'
    (stage/'scripts').mkdir(parents=True);backup.mkdir()
    (stage/'scripts/pr30-release.json').write_text(json.dumps(m))
    for path,h in m['before'].items():
        target=live/path;target.parent.mkdir(parents=True,exist_ok=True)
        target.write_bytes(subprocess.check_output(['git','show',m['baseline_commit']+':wordpress/'+path],cwd=r))
        assert hashlib.sha256(target.read_bytes()).hexdigest()==h
    for path in m['changed']:
        target=stage/'runtime'/path;target.parent.mkdir(parents=True,exist_ok=True)
        target.write_bytes((r/'wordpress'/path).read_bytes())
    code=(r/'scripts/pr30-install.php').read_text()
    assert code.count("$root='/run/webroots/r1417157';")==1
    installer=t/'installer.php';installer.write_text(code.replace("$root='/run/webroots/r1417157';", "$root="+repr(str(live))+";"))
    def run(mode,ok):
        result=subprocess.run([args.php,str(installer),str(stage),str(backup),mode],capture_output=True,text=True,timeout=90)
        out=result.stdout+result.stderr
        failed=result.returncode or re.search(r'Fatal error|Uncaught |Warning:|Parse error',out,re.I)
        assert bool(failed)==(not ok),out
        return out
    def inventory():return {str(p.relative_to(live)):hashlib.sha256(p.read_bytes()).hexdigest() for p in live.rglob('*') if p.is_file()}
    run('check',True);assert inventory()==m['before']
    drift=live/next(iter(m['before']));original=drift.read_bytes();drift.write_bytes(original+b'\ndrift\n')
    modified=inventory();run('install',False);assert inventory()==modified
    drift.write_bytes(original)
    package=stage/'runtime'/next(iter(m['changed']));original=package.read_bytes();package.write_bytes(original+b'\ndrift\n')
    run('install',False);assert inventory()==m['before'];package.write_bytes(original)
    run('install',True);run('after',True);assert inventory()==m['after']
    changed=live/next(iter(m['changed']));original=changed.read_bytes();changed.write_bytes(original+b'\nconcurrent\n')
    assert 'concurrent code change' in run('rollback',False)
    changed.write_bytes(original);run('rollback',True);assert inventory()==m['before']
print('PASS: installer rejects live/package drift, installs exact bytes and restores owned changes')
