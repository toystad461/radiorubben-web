#!/usr/bin/env python3
"""Lint and run all isolated PHP suites, including runtimes with unreliable exit codes."""
import argparse
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path
import re
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1] / 'wordpress/wp-content/plugins/radio-rubben-fotballrobot'
ERROR = re.compile(r'(?:Fatal error|Parse error|Warning:|Uncaught |AssertionError|Segmentation fault)', re.I)


def checked(command, success_pattern):
    result = subprocess.run(command, capture_output=True, text=True, timeout=90)
    output = result.stdout + result.stderr
    if result.returncode or ERROR.search(output) or not re.search(success_pattern, output, re.I):
        raise RuntimeError(f'Failed: {" ".join(command)}\n{output}')
    return output


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--php', default='php', help='PHP executable; may also be php-wasm-cli')
    args = parser.parse_args()
    files = sorted(ROOT.rglob('*.php'))
    try:
        with ThreadPoolExecutor(max_workers=4) as pool:
            list(pool.map(lambda path: checked([args.php, '-l', str(path)], r'No syntax errors detected'), files))
        print(f'PASS: syntax for {len(files)} PHP files', flush=True)
        for test in sorted((ROOT / 'tests').glob('*.php')):
            checked([args.php, str(test)], r'\bOK:|\bpassed\b')
            print(f'PASS: {test.name}', flush=True)
    except (RuntimeError, OSError, subprocess.TimeoutExpired) as error:
        print(error, file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    sys.exit(main())
