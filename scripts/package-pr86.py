"""Build the bounded package; baseline files and historic receipts stay immutable."""
import hashlib, json, pathlib, subprocess, sys
ROOT = pathlib.Path(__file__).resolve().parent.parent
BASE = '661228524d6ff6e05801027951cc0c82738795ef'
PREFIX = 'wordpress/wp-content/plugins/radio-rubben-fotballrobot/'
git = ['git', '-C', str(ROOT)]
names = subprocess.check_output(git + ['ls-tree', '-r', '--name-only', BASE, '--', PREFIX], text=True).splitlines()
names = [n for n in names if '/tests/' not in n and pathlib.Path(n).suffix in {'.php', '.css', '.js'}]
changed = subprocess.check_output(git + ['diff', '--name-only', BASE, 'HEAD', '--', PREFIX], text=True).splitlines()
changed = [n for n in changed if '/tests/' not in n]
before, after, updates = {}, {}, {}
for name in sorted(set(names + changed)):
    p = name.removeprefix('wordpress/')
    old = subprocess.check_output(git + ['show', f'{BASE}:{name}']) if name in names else None
    before[p] = hashlib.sha256(old).hexdigest() if old is not None else None
    after[p] = hashlib.sha256(subprocess.check_output(git + ['show', f'HEAD:{name}'])).hexdigest()
    if before[p] != after[p]: updates[p] = after[p]
manifest = {'baseline_commit': BASE, 'version': '0.10.6', 'before': before, 'after': after, 'changed': updates}
manifest_path = ROOT / 'scripts/pr86-release.json'
if sys.argv[1:] == ['manifest']:
    manifest_path.write_text(json.dumps(manifest, indent=2) + '\n', encoding='utf-8')
else:
    if json.loads(manifest_path.read_text()) != manifest: raise SystemExit('Manifest does not match candidate')
    output = pathlib.Path(sys.argv[1])
    if output.exists(): raise SystemExit('Package directory must be new')
    for p in updates:
        dest = output / 'runtime' / p
        dest.parent.mkdir(parents=True, exist_ok=True)
        dest.write_bytes(subprocess.check_output(git + ['show', f'HEAD:wordpress/{p}']))
    for p in ['pr86-release.json', 'pr86-install.php', 'deploy-pr86.sh', 'fotballrobot-cron.php']:
        dest = output / 'scripts' / p
        dest.parent.mkdir(parents=True, exist_ok=True)
        dest.write_bytes((ROOT / 'scripts' / p).read_bytes())
    print(f'PR86 package: {len(updates)} runtime files; {len(before)} guarded paths')
