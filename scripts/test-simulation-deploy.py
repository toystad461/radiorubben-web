"""Exercise selective install/rollback on disposable files, never WordPress."""
import json, subprocess, tempfile, sys
from pathlib import Path

base = Path(__file__).resolve().parents[1]
payload = json.loads(subprocess.check_output([sys.executable, str(base / 'scripts/build-simulation-deploy.py')]))
with tempfile.TemporaryDirectory() as tmp:
    work = Path(tmp)
    root = work / 'real-webroot' / 'plugin'
    (root / 'inc').mkdir(parents=True)
    originals = {}
    for name, edits in payload['replacements'].items():
        contents = (base / 'theme-next/rr-site-functions' / name).read_text(encoding='utf-8')
        for before, after in edits:
            contents = contents.replace(after, before)
        contents += '\n// Unrelated production change must survive.\n'
        if name == 'rr-site-functions.php':
            contents = contents.replace("'dashboard-prototype'", "'dashboard-prototype', 'match-day-embed'")
        (root / name).write_text(contents, encoding='utf-8')
        originals[name] = contents.encode()
    script = (base / 'scripts/deploy-simulation-files.php').read_text(encoding='utf-8')
    alias = work / 'webroot-alias'
    alias.symlink_to(root.parent, target_is_directory=True)
    script = script.replace('/run/webroots/r1417157/wp-content/plugins/rr-site-functions', str(alias / 'plugin'))
    executable = work / 'deploy.php'
    executable.write_text(script, encoding='utf-8')
    stage = work / 'stage'
    stage.mkdir()
    (stage / 'payload.json').write_text(json.dumps(payload), encoding='utf-8')
    def run(mode, ok=True):
        result = subprocess.run(['php', str(executable), mode, str(stage)], capture_output=True, text=True)
        assert (result.returncode == 0) == ok, result.stdout + result.stderr
    run('plan')
    assert all((root / name).read_bytes() == contents for name, contents in originals.items())
    # A concurrent edit after planning must stop before any installation.
    first = next(iter(originals))
    (root / first).write_bytes(originals[first] + b'\n// Concurrent edit\n')
    run('apply', ok=False)
    assert not (root / payload['new_file']).exists()
    (root / first).write_bytes(originals[first])
    run('apply')
    assert (root / payload['new_file']).read_text() == payload['new_content']
    assert all(b'Unrelated production change must survive.' in (root / name).read_bytes() for name in originals)
    run('rollback')
    assert not (root / payload['new_file']).exists()
    assert all((root / name).read_bytes() == contents for name, contents in originals.items())
    # A source mismatch must fail during planning, before live file writes.
    bad = work / 'bad-stage'
    bad.mkdir()
    (bad / 'payload.json').write_text(json.dumps(payload), encoding='utf-8')
    (root / first).write_text('<?php // Unknown production revision\n')
    result = subprocess.run(['php', str(executable), 'plan', str(bad)], capture_output=True)
    assert result.returncode != 0
    assert not (root / payload['new_file']).exists()
print('PASS: aliased webroot resolves; plan is read-only; exact install preserves drift; concurrent edits stop; rollback restores bytes; unknown source fails closed.')
