#!/usr/bin/env python3
"""Build a complete, immutable 0.10.8 test kit from pinned Git objects."""
import hashlib, json, pathlib, subprocess, sys, zipfile

HERE = pathlib.Path(__file__).resolve().parent
REPO = HERE.parent
SOURCE = '1a162231716559e5f7d37ba26647c524ed367744'
BASELINE = '75326d1c26cd3b30f7c52ead8246b4e99df950a4'
WEB = 'c349fc24dd8e6df588755680f1e16c87bf281204'
PLUGIN = 'wordpress/wp-content/plugins/radio-rubben-fotballrobot/'

def git(*args):
    return subprocess.check_output(['git', '-C', str(REPO), *args])

def blob(ref, name):
    return git('show', ref + ':' + name)

def digest(data):
    return hashlib.sha256(data).hexdigest()

def build(out):
    if out.exists():
        raise ValueError('Output must be new')
    manifest_bytes = blob(SOURCE, 'scripts/details-release.json')
    manifest = json.loads(manifest_bytes)
    assert manifest['version'] == '0.10.8' and len(manifest['after']) == 38
    paths = git('ls-tree', '-r', '--name-only', SOURCE, '--', PLUGIN).decode().splitlines()
    runtime = {p for p in paths if '/tests/' not in p and pathlib.Path(p).suffix in {'.php','.css','.js'}}
    assert runtime == {'wordpress/' + p for p in manifest['after']}
    files = {}
    for name,sha1 in {'wp-config.sample':'4dfe80bf87cfbffe1b8b8167e886600b4714685b',
                      'wp-isolation.php':'9ae9e7d739f34804cc50c32b462a38883f62f135',
                      'verify.sh':'abb19f50e833e01591117a2dc78086d7532ef586'}.items():
        b=(HERE/'evidence/infra'/name).read_bytes()
        assert hashlib.sha1(b'blob '+str(len(b)).encode()+b'\0'+b).hexdigest()==sha1, name
    for name in sorted(runtime):
        data = blob(SOURCE, name)
        assert digest(data) == manifest['after'][name.removeprefix('wordpress/')], name
        files['source/' + name] = data
    for name in paths:
        if '/tests/' in name:
            files['source/' + name] = blob(SOURCE, name)
    for name in ['scripts/test-fotballrobot.py','scripts/fotballrobot-cron.php']:
        files['source/' + name] = blob(SOURCE, name)
    evidence_paths = ['docs/fotballrobot/runtime-manifest.json','docs/fotballrobot/runtime.sha256',
        'docs/fotballrobot/pr30-production-receipt.json','docs/fotballrobot/ai-policy-production-receipt.json',
        'docs/fotballrobot/ai-policy-release.json','scripts/pr30-release.json','scripts/pr86-release.json',
        'scripts/kickoff-release.json','scripts/details-release.json','docs/fotballrobot/KICKOFF-2026-10-10.md',
        'docs/fotballrobot/DETAILS-2026-10-14.md','docs/fotballrobot/PR86-ROLLOUT.md']
    for name in evidence_paths:
        files['evidence/' + name] = blob(SOURCE, name)
    # Validate every historical manifest's BEFORE state against its pinned base.
    history = []
    for name in ['scripts/pr30-release.json','scripts/pr86-release.json','scripts/kickoff-release.json','scripts/details-release.json']:
        m = json.loads(blob(SOURCE, name))
        for p,h in m['before'].items():
            if h is not None:
                assert digest(blob(m['baseline_commit'], 'wordpress/' + p)) == h, (name,p)
        history.append({'manifest':name,'version':m['version'],'base':m['baseline_commit'],
                        'guarded_paths':len(m['after']),'changed_paths':len(m['changed'])})
    old = {p: digest(blob(BASELINE,p)) for p in git('ls-tree','-r','--name-only',BASELINE,'--',PLUGIN).decode().splitlines()
           if '/tests/' not in p and pathlib.Path(p).suffix in {'.php','.css','.js'}}
    assert len(old) == 32
    chain_paths=['scripts/pr30-release.json','docs/fotballrobot/ai-policy-release.json',
                 'scripts/pr86-release.json','scripts/kickoff-release.json','scripts/details-release.json']
    chain=[json.loads(blob(SOURCE,p)) for p in chain_paths]
    for previous,current in zip(chain,chain[1:]):
        for p in previous['after'].keys() & current['before'].keys():
            assert previous['after'][p]==current['before'][p], ('Broken historical chain',p)
    baseline_manifest = json.loads(blob(BASELINE,'docs/fotballrobot/runtime-manifest.json'))
    # Compare the old baseline to the independently pinned 0.10.4 runtime source.
    for p,h in old.items():
        assert digest(blob(baseline_manifest['runtime_source_commit'],p)) == h
    changed = [p for p in sorted(old) if digest(files['source/'+p]) != old[p]]
    added = sorted(runtime - old.keys())
    poll_names = ['bremnes-poll-match.php','bremnes-poll-fixtures.php','bremnes-poll-rules.php',
                  'bremnes-poll-test.php','bremnes-speaker-events.php','bremnes-nff-refresh.php','bremnes-poll-fotballdata.php']
    for name in poll_names:
        p = 'theme-next/rr-site-functions/inc/' + name
        files['qa/site-functions/' + name] = blob(WEB, p)
    provenance = {'source_commit':SOURCE,'baseline_0_10_4':BASELINE,'site_functions_test_only_commit':WEB,
        'runtime_files':38,'release_changed_files':7,'changed_since_0_10_4':changed,'added_since_0_10_4':added,
        'historical_manifests':history,'production_claim':'PR #92 documents install; no fresh production read performed',
        'historical_chain':'0.10.5 -> policy -> 0.10.6 -> 0.10.7 -> 0.10.8: all shared hashes match',
        'release_assets':'GitHub releases endpoint returned an empty list on 2026-10-18',
        'vps_installation':'NOT PERFORMED: SSH Network is unreachable; no authenticated browser session'}
    files['provenance.json'] = (json.dumps(provenance,indent=2,ensure_ascii=False)+'\n').encode()
    files['runtime.sha256'] = ''.join(f'{h}  source/wordpress/{p}\n' for p,h in sorted(manifest['after'].items())).encode()
    for p in HERE.rglob('*'):
        if p.is_file() and '__pycache__' not in p.parts and p.name != 'build.py':
            files[str(p.relative_to(HERE))] = p.read_bytes()
    files['rebuild-zip.py'] = (HERE/'rebuild-zip.py').read_bytes()
    files['source-builder.py'] = (HERE/'build.py').read_bytes()
    for name,data in sorted(files.items()):
        p=out/name; p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(data)
    inventory={name:digest(data) for name,data in sorted(files.items())}
    (out/'FILES.json').write_text(json.dumps(inventory,indent=2)+'\n')
    subprocess.check_call([sys.executable,str(out/'verify.py'),str(out)])
    subprocess.check_call([sys.executable,str(out/'rebuild-zip.py'),str(out),str(out)+'.zip'])
    print(json.dumps({'files':len(files),'runtime':38,'changed_since_0_10_4':len(changed),'added':len(added)}))

if __name__=='__main__':
    build(pathlib.Path(sys.argv[1]).resolve())
