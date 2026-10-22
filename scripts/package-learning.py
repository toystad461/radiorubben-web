"""Four-file learning release; require exact audited production hashes."""
import hashlib, json, pathlib, shutil, sys
root = pathlib.Path(__file__).resolve().parent.parent
manifest = json.loads((root / 'scripts/learning-release.json').read_text())
out = pathlib.Path(sys.argv[1])
if out.exists():
    raise SystemExit('New package directory required')
allowed = {'includes/facts.php', 'includes/writer.php', 'includes/lineups.php', 'includes/club-coverage.php'}
prefix = 'wp-content/plugins/radio-rubben-fotballrobot/'
assert set(manifest['changed']) == {prefix + p for p in allowed}
for p, sha in manifest['changed'].items():
    source = root / 'wordpress' / p
    assert hashlib.sha256(source.read_bytes()).hexdigest() == sha, p
    dest = out / 'runtime' / p
    dest.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(source, dest)
for name in ['learning-install.php', 'learning-release.json', 'learning-postflight.php', 'deploy-learning.sh']:
    dest = out / 'scripts' / name
    dest.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(root / 'scripts' / name, dest)
print('Verified learning package: 4 changed files, 38 guarded production files')
