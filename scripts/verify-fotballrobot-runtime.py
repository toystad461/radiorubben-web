#!/usr/bin/env python3
"""Verify the pinned, documented production baseline. Read-only; no network."""
import argparse
import hashlib
import json
from pathlib import Path, PurePosixPath
import re
import sys

REPO = Path(__file__).resolve().parents[1]
MANIFEST = REPO / 'docs/fotballrobot/runtime-manifest.json'


def unique_object(pairs):
    result = {}
    for key, value in pairs:
        if key in result:
            raise ValueError(f'Duplicate JSON key: {key}')
        result[key] = value
    return result


def read_json(path):
    return json.loads(path.read_text(), object_pairs_hook=unique_object)


def safe_path(value):
    path = PurePosixPath(value)
    if not value or path.is_absolute() or '..' in path.parts or str(path) != value or '\\' in value or '\n' in value:
        raise ValueError(f'Unsafe relative path: {value!r}')
    return value


def no_symlink(path):
    for part in (path, *path.parents):
        if part.is_symlink():
            raise ValueError(f'Symlink forbidden: {part}')


def sha256(data):
    return hashlib.sha256(data).hexdigest()


def git_blob(data):
    return hashlib.sha1(b'blob ' + str(len(data)).encode() + b'\0' + data).hexdigest()


def verify(manifest_path=MANIFEST, plugin_path=None):
    manifest_path = Path(manifest_path).absolute()
    no_symlink(manifest_path)
    m = read_json(manifest_path)
    if m['schema_version'] != 1:
        raise ValueError('Unsupported manifest schema')
    plugin = Path(plugin_path).absolute() if plugin_path else REPO / safe_path(m['plugin_path'])
    no_symlink(plugin)
    records = m['runtime_files'] + m['support_files']
    expected = {}
    for record in records:
        path = safe_path(record['path'])
        if path in expected:
            raise ValueError(f'Duplicate manifest path: {path}')
        if not re.fullmatch(r'[0-9a-f]{64}', record['sha256']) or not re.fullmatch(r'[0-9a-f]{40}', record['git_blob']):
            raise ValueError(f'Invalid hash: {path}')
        if record['mode'] != '100644':
            raise ValueError(f'Unexpected source mode: {path}')
        expected[path] = record
    runtime = m['runtime_files']
    runtime_paths = [r['path'] for r in runtime]
    if runtime_paths != sorted(runtime_paths) or not runtime:
        raise ValueError('Runtime inventory must be nonempty and sorted')
    if any(p.startswith('tests/') or p.endswith('.md') for p in runtime_paths):
        raise ValueError('Support files incorrectly classified as runtime')
    if any(not (r['path'].startswith('tests/') or r['path'].endswith('.md')) for r in m['support_files']):
        raise ValueError('Runtime hidden in support inventory')
    actual = set()
    if not plugin.is_dir():
        raise ValueError(f'Missing plugin directory: {plugin}')
    for path in plugin.rglob('*'):
        no_symlink(path)
        if path.is_dir():
            continue
        if not path.is_file():
            raise ValueError(f'Non-regular plugin entry: {path}')
        actual.add(path.relative_to(plugin).as_posix())
    if actual != set(expected):
        raise ValueError(f'Inventory mismatch; missing={sorted(set(expected)-actual)}, extra={sorted(actual-set(expected))}')
    for path, record in expected.items():
        file = plugin / path
        data = file.read_bytes()
        if file.stat().st_mode & 0o111:
            raise ValueError(f'Unexpected executable mode: {path}')
        if sha256(data) != record['sha256'] or git_blob(data) != record['git_blob']:
            raise ValueError(f'Content mismatch: {path}')
    evidence = {}
    for name, record in m['evidence'].items():
        path = manifest_path.parent / 'evidence' / safe_path(name)
        no_symlink(path)
        data = path.read_bytes()
        if sha256(data) != record['sha256']:
            raise ValueError(f'Evidence changed: {name}')
        if name.endswith('.json'):
            evidence[name] = read_json(path)['after']
        else:
            values = {}
            for line in data.decode().splitlines():
                digest, item_path = line.split('  ', 1)
                if item_path in values:
                    raise ValueError(f'Duplicate receipt path: {name}: {item_path}')
                values[item_path] = digest
            evidence[name] = values
    for record in runtime:
        receipt = record['receipt']
        if evidence[receipt['file']].get(receipt['path']) != record['sha256']:
            raise ValueError(f'No matching production receipt: {record["path"]}')
    canonical = ''.join(f'{r["sha256"]}  {r["path"]}\n' for r in runtime).encode()
    if sha256(canonical) != m['runtime_sha256']:
        raise ValueError('Runtime digest mismatch')
    if (manifest_path.parent / 'runtime.sha256').read_bytes() != canonical:
        raise ValueError('Canonical SHA-256 inventory mismatch')
    entry = (plugin / 'radio-rubben-fotballrobot.php').read_text()
    if not re.search(r'^\s*\*?\s*Version:\s*' + re.escape(m['plugin_version']) + r'\s*$', entry, re.M):
        raise ValueError('Plugin version mismatch')
    return {'version': m['plugin_version'], 'runtime_files': len(runtime), 'support_files': len(m['support_files']), 'runtime_sha256': m['runtime_sha256'], 'source_commit': m['runtime_source_commit']}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--manifest', type=Path, default=MANIFEST)
    parser.add_argument('--plugin', type=Path, help='Optional independent plugin copy to compare with the pinned baseline')
    args = parser.parse_args()
    try:
        print(json.dumps(verify(args.manifest, args.plugin), sort_keys=True))
    except (ValueError, KeyError, TypeError, OSError) as error:
        print(f'FAIL: {error}', file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    sys.exit(main())
