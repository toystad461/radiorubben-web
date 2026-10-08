#!/usr/bin/env python3
"""Compare two local code-hash manifests. No network, SSH, or deployment operations."""
from __future__ import annotations
import argparse
import json
import re
import sys
from datetime import datetime, timedelta, timezone
from pathlib import Path
from typing import Any

MAX_BYTES = 8_000_000
MAX_FILES = 20_000
SHA256 = re.compile(r"[0-9a-f]{64}\Z")
COMMIT = re.compile(r"[0-9a-f]{40}\Z")
SCOPE = re.compile(r"[a-z0-9][a-z0-9-]{0,79}\Z")
FORBIDDEN = {'.git', '.env', 'config', 'cache', 'data', 'uploads', 'backup', 'backups',
             'wp-config.php', 'local.php', 'id_rsa', 'id_ed25519'}


def unique_object(pairs: list[tuple[str, Any]]) -> dict[str, Any]:
    result: dict[str, Any] = {}
    for key, value in pairs:
        if key in result:
            raise ValueError('Duplicate JSON key')
        result[key] = value
    return result


def read_manifest(path: Path) -> dict[str, Any]:
    if path.is_symlink() or not path.is_file():
        raise ValueError('Manifest must be a regular local file, not a symlink')
    with path.open('rb') as stream:
        raw = stream.read(MAX_BYTES + 1)
    if len(raw) > MAX_BYTES:
        raise ValueError('Manifest exceeds size limit')
    value = json.loads(raw.decode('utf-8'), object_pairs_hook=unique_object)
    if not isinstance(value, dict):
        raise ValueError('Manifest must be an object')
    return value


def file_map(value: Any) -> dict[str, str]:
    if not isinstance(value, list) or not value or len(value) > MAX_FILES:
        raise ValueError('Expected a nonempty bounded file list')
    result: dict[str, str] = {}
    for entry in value:
        if not isinstance(entry, dict) or set(entry) != {'path', 'sha256'}:
            raise ValueError('Each file must contain only path and sha256')
        path, digest = entry['path'], entry['sha256']
        if not isinstance(path, str) or not path or len(path) > 512:
            raise ValueError('Invalid path')
        parts = path.split('/')
        if (any(p in {'', '.', '..'} for p in parts) or '\\' in path or ':' in path
                or any(ord(c) < 32 or ord(c) == 127 for c in path)):
            raise ValueError('Unsafe relative path')
        if any(p.lower() in FORBIDDEN or p.lower().startswith('.env.') for p in parts):
            raise ValueError('Runtime, private, or credential path is outside code scope')
        if not isinstance(digest, str) or not SHA256.fullmatch(digest):
            raise ValueError('Invalid SHA-256')
        if path in result:
            raise ValueError('Duplicate path')
        result[path] = digest
    return result


def compare(expected: dict[str, Any], observed: dict[str, Any], *,
            now: datetime | None = None, max_age_hours: int = 24) -> dict[str, Any]:
    """A match describes ONLY the supplied scope; it never authorizes deployment."""
    now = now or datetime.now(timezone.utc)
    if now.tzinfo is None or type(max_age_hours) is not int or not 1 <= max_age_hours <= 24:
        raise ValueError('Use an aware clock and a freshness window of 1-24 hours')
    for manifest in (expected, observed):
        if type(manifest.get('schema_version')) is not int or manifest['schema_version'] != 1:
            raise ValueError('Unsupported schema')
        if not isinstance(manifest.get('scope'), str) or not SCOPE.fullmatch(manifest['scope']):
            raise ValueError('Invalid code scope')
    if expected['scope'] != observed['scope']:
        raise ValueError('Cannot compare different scopes')
    commit = expected.get('source_commit')
    if not isinstance(commit, str) or not COMMIT.fullmatch(commit):
        raise ValueError('Expected source must be pinned to a full commit SHA')
    timestamp = observed.get('observed_at')
    if not isinstance(timestamp, str):
        raise ValueError('Missing observation time')
    captured = datetime.fromisoformat(timestamp.replace('Z', '+00:00'))
    if captured.tzinfo is None:
        raise ValueError('Observation time requires a timezone')
    wanted, actual = file_map(expected.get('files')), file_map(observed.get('files'))
    missing = sorted(wanted.keys() - actual.keys())
    unexpected = sorted(actual.keys() - wanted.keys())
    changed = sorted(p for p in wanted.keys() & actual.keys() if wanted[p] != actual[p])
    blockers = []
    if observed.get('complete') is not True:
        blockers.append('incomplete_inventory')
    if 'errors' not in observed or not isinstance(observed['errors'], list) or observed['errors']:
        blockers.append('unresolved_collection_errors')
    if captured > now:
        blockers.append('future_observation')
    elif now - captured > timedelta(hours=max_age_hours):
        blockers.append('stale_inventory')
    if missing or unexpected or changed:
        blockers.append('code_drift')
    return {
        'schema_version': 1, 'scope': expected['scope'], 'source_commit': commit,
        'status': 'blocked' if blockers else 'scope_matches',
        'observed_at': timestamp, 'compared_at': now.isoformat(),
        'expected_files': len(wanted), 'observed_files': len(actual),
        'missing': missing, 'unexpected': unexpected, 'changed': changed,
        'blockers': blockers, 'deployment_authorized': False,
        'evidence_limit': 'Local manifests only; provenance, other scopes, database, cron, '
                          'authentication, backups and real user flows are not verified.'
    }


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('expected', type=Path)
    parser.add_argument('observed', type=Path)
    parser.add_argument('--max-age-hours', type=int, default=24)
    args = parser.parse_args()
    try:
        result = compare(read_manifest(args.expected), read_manifest(args.observed),
                         max_age_hours=args.max_age_hours)
    except (OSError, ValueError, TypeError, KeyError, RecursionError):
        # Do not print malformed content: it may contain credentials or private data.
        print(json.dumps({'status': 'invalid_input', 'deployment_authorized': False}))
        return 2
    print(json.dumps(result, indent=2, ensure_ascii=False))
    return 0 if result['status'] == 'scope_matches' else 1


if __name__ == '__main__':
    sys.exit(main())
