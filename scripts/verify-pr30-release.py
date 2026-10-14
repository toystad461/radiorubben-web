#!/usr/bin/env python3
"""Bind the narrow PR30 candidate to the immutable, verified 0.10.4 receipt."""
import hashlib
import json
from pathlib import Path
r=Path(__file__).resolve().parents[1]
m=json.loads((r/'scripts/pr30-release.json').read_text())
baseline=json.loads((r/'docs/fotballrobot/runtime-manifest.json').read_text())
prefix='wp-content/plugins/radio-rubben-fotballrobot/'
assert m['baseline_commit']=='75326d1c26cd3b30f7c52ead8246b4e99df950a4'
assert m['before']=={prefix+x['path']:x['sha256'] for x in baseline['runtime_files']}
root=r/'wordpress'/prefix
runtime=[p for p in root.rglob('*') if p.is_file() and p.suffix!='.md' and 'tests' not in p.relative_to(root).parts]
assert not any(p.is_symlink() or any(a.is_symlink() for a in p.parents) for p in runtime)
actual={prefix+str(p.relative_to(root)):hashlib.sha256(p.read_bytes()).hexdigest() for p in runtime}
assert actual==m['after'], 'Candidate runtime differs from release manifest'
assert m['changed']=={p:h for p,h in actual.items() if m['before'].get(p)!=h}
assert set(m['changed'])=={prefix+p for p in ['includes/club-coverage.php','includes/fotballdata.php','includes/club-automation.php','includes/writer.php','includes/publication-gate.php','includes/review-desk.php','includes/newsroom.php','radio-rubben-fotballrobot.php']}
assert 'Version: '+m['version'] in (root/'radio-rubben-fotballrobot.php').read_text()
print(f'PASS: {len(actual)} runtime hashes, {len(m["changed"])} narrowly scoped changes, immutable baseline preserved')
