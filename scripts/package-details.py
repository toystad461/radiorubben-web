"""Two-file follow-up to 0.10.6; historical release receipts stay immutable."""
import hashlib, json, pathlib, subprocess, sys
ROOT = pathlib.Path(__file__).resolve().parent.parent
BASE = 'b0bb6ccaddeb3b07ff057a8a3a82cda64611305b'
PREFIX = 'wordpress/wp-content/plugins/radio-rubben-fotballrobot/'
git = ['git', '-C', str(ROOT)]
names = subprocess.check_output(git + ['ls-tree', '-r', '--name-only', BASE, '--', PREFIX], text=True).splitlines()
names = [n for n in names if '/tests/' not in n and pathlib.Path(n).suffix in {'.php', '.css', '.js'}]
names.append(PREFIX + 'includes/shared-rules.php')
before, after, updates = {}, {}, {}
for name in sorted(names):
    p = name.removeprefix('wordpress/')
    before[p] = None if name.endswith('/shared-rules.php') else hashlib.sha256(subprocess.check_output(git + ['show', f'{BASE}:{name}'])).hexdigest()
    after[p] = hashlib.sha256((ROOT / name).read_bytes().replace(b'\r\n', b'\n')).hexdigest()
    if before[p] != after[p]: updates[p] = after[p]
assert set(updates) == {PREFIX.removeprefix('wordpress/') + p for p in ['includes/club-coverage.php', 'includes/club-automation.php', 'includes/newsroom.php', 'includes/publication-gate.php', 'includes/writer.php', 'includes/shared-rules.php', 'radio-rubben-fotballrobot.php']}
manifest = {'baseline_commit': BASE, 'version': '0.10.8', 'before': before, 'after': after, 'changed': updates}
manifest_path = ROOT / 'scripts/details-release.json'
if sys.argv[1:] == ['manifest']:
    manifest_path.write_text(json.dumps(manifest, indent=2) + '\n', encoding='utf-8')
else:
    if json.loads(manifest_path.read_text()) != manifest: raise SystemExit('Manifest does not match candidate')
    output = pathlib.Path(sys.argv[1])
    if output.exists(): raise SystemExit('Package directory must be new')
    for p in updates:
        dest = output / 'runtime' / p
        dest.parent.mkdir(parents=True, exist_ok=True)
        dest.write_bytes((ROOT / 'wordpress' / p).read_bytes().replace(b'\r\n', b'\n'))
    for p in ['details-release.json', 'details-install.php']:
        dest = output / 'scripts' / p
        dest.parent.mkdir(parents=True, exist_ok=True)
        dest.write_bytes((ROOT / 'scripts' / p).read_bytes())
    print(f'Kickoff package: {len(updates)} runtime files; {len(before)} guarded paths')
