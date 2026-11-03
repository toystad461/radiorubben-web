#!/usr/bin/env python3
"""Selective local installation only. No database, settings, profiles, cron or content changes."""
import argparse
import hashlib
import json
import os
import shutil
import subprocess
import tempfile
from pathlib import Path

FILES = ['includes/source-collection.php', 'includes/player-monitor.php', 'includes/player-candidates.php',
         'includes/players.php', 'radio-rubben-fotballrobot.php']
BEFORE = {
    'includes/player-monitor.php': '77482c834650288a41b33c372a30f773a0c12d72140613f3aaf031b907df1c7f',
    'includes/player-candidates.php': 'f0f84e58badf5c514b5f52b13011eadfcd5202c9db456676d8a522e6dae17848',
    'includes/players.php': '725203ccf3c419b30a247db549873706323229e801255ef057683053e84ee0b2',
    'radio-rubben-fotballrobot.php': '3c0e0dc0190a6838663565a68b15656a9ab368f52b519323960b39002603b296',
}


def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--wp-root', type=Path, required=True)
    p.add_argument('--backup-dir', type=Path, required=True, help='New private directory outside the web root')
    p.add_argument('--apply', action='store_true')
    p.add_argument('--php', default='php')
    a = p.parse_args()
    repo = Path(__file__).resolve().parents[2]
    source = repo / 'wordpress/wp-content/plugins/radio-rubben-fotballrobot'
    root = a.wp_root.resolve(strict=True)
    target = root / 'wp-content/plugins/radio-rubben-fotballrobot'
    backup = a.backup_dir.resolve()
    if backup.is_relative_to(root) or backup.exists():
        raise RuntimeError('Backup must be a new private directory outside the web root')
    receipt = {'action': 'plan', 'files': {}}
    for name in FILES:
        path = target / name
        if path.is_symlink() or not path.resolve().is_relative_to(target.resolve()):
            raise RuntimeError('Unsafe plugin path')
        before = digest(path) if path.exists() else None
        if before != BEFORE.get(name):
            raise RuntimeError('Installed file differs; stop for reconciliation: ' + name)
        subprocess.run([a.php, '-l', str(source / name)], check=True, stdout=subprocess.DEVNULL)
        receipt['files'][name] = {'before': before, 'after': digest(source / name)}
    if not a.apply:
        print(json.dumps(receipt, indent=2)); return
    os.umask(0o077); backup.mkdir(parents=True, mode=0o700)
    owner = (target / FILES[-1]).stat()
    for name in FILES:
        path = target / name
        if path.exists():
            dst = backup / name; dst.parent.mkdir(parents=True, exist_ok=True); shutil.copy2(path, dst)
    changed = []
    try:
        for name in FILES:
            path = target / name
            if (digest(path) if path.exists() else None) != receipt['files'][name]['before']:
                raise RuntimeError('Concurrent file change: ' + name)
            fd, tmp = tempfile.mkstemp(dir=path.parent, prefix='.rrfr-collection-')
            try:
                with os.fdopen(fd, 'wb') as stream:
                    stream.write((source / name).read_bytes()); stream.flush(); os.fsync(stream.fileno())
                os.chmod(tmp, 0o644)
                if os.geteuid() == 0:
                    os.chown(tmp, owner.st_uid, owner.st_gid)
                os.replace(tmp, path); changed.append(name)
            finally:
                if os.path.exists(tmp):
                    os.unlink(tmp)
            if digest(path) != receipt['files'][name]['after']:
                raise RuntimeError('Readback mismatch')
    except BaseException:
        # Only revert our own bytes, never concurrent edits or any WordPress data.
        for name in reversed(changed):
            path = target / name
            if digest(path) != receipt['files'][name]['after']:
                continue
            if (backup / name).exists():
                shutil.copy2(backup / name, path)
            else:
                path.unlink()
        raise
    receipt['action'] = 'installed_files_only'
    (backup / 'receipt.json').write_text(json.dumps(receipt, indent=2))
    print(json.dumps(receipt, indent=2))


if __name__ == '__main__':
    main()
