"""Build an exact plugin payload; temporary archive URL is never logged/stored."""
import hashlib
import json
import os
from pathlib import Path
import shutil
import urllib.parse
import urllib.request

FILES = ('contract.php', 'data/01-BILDEREGISTER.json', 'import.php', 'publication.php',
         'rr-news-images.php', 'images.css', 'lib/media-policy.php')
ZIP_SHA = 'a2d27533d3b9455a96d7c967ed4f87f6aba3827156914a468104bb8b21303adc'


def build():
    event = json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text())
    inputs = event['inputs']
    url = inputs.get('package_url', '')
    if url:
        # Actions command escaping is required even for a temporary URL.
        masked = url.replace('%', '%25').replace('\r', '%0D').replace('\n', '%0A')
        print('::add-mask::' + masked, flush=True)
    assert inputs['source_sha'] == os.environ['GITHUB_SHA'], 'Source commit moved'
    assert os.environ['GITHUB_REF'] == 'refs/heads/main'
    assert inputs['action'] in ('install', 'verify', 'activate-studio')
    output = Path(os.environ['RUNNER_TEMP']) / 'news-images'
    output.mkdir(mode=0o700)
    manifest = {'commit': inputs['source_sha'], 'files': {}}
    source = Path('wordpress/wp-content/plugins/rr-news-images')
    for relative in FILES:
        target = output / 'plugin' / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(source / relative, target)
        manifest['files'][relative] = hashlib.sha256(target.read_bytes()).hexdigest()
    for name in ('wordpress-release.php', 'studio-activate.php'):
        shutil.copyfile(Path('scripts/news-images') / name, output / name)
    (output / 'manifest.json').write_text(json.dumps(manifest, indent=2))
    if inputs['action'] == 'install':
        parsed = urllib.parse.urlparse(url)
        assert parsed.scheme == 'https' and not parsed.username and not parsed.password
        assert parsed.hostname.endswith('.oaiusercontent.com') and parsed.hostname.startswith('sdmntpr'), 'Unexpected transfer host'
        try:
            with urllib.request.urlopen(url, timeout=60) as response:
                assert response.status == 200
                archive = response.read(39646212)
        except Exception:
            raise RuntimeError('Archive transfer failed; obtain a fresh OneDrive download reference') from None
        assert len(archive) == 39646211 and hashlib.sha256(archive).hexdigest() == ZIP_SHA, 'Archive differs from approved original'
        (output / 'RadioRubben-Nyhetsbilder-v1.2.zip').write_bytes(archive)
    checks = []
    for file in sorted(output.rglob('*')):
        if file.is_file():
            checks.append(hashlib.sha256(file.read_bytes()).hexdigest() + '  ' + str(file.relative_to(output)))
    (output / 'transport.sha256').write_text('\n'.join(checks) + '\n')
    print('Scoped release package verified: ' + manifest['commit'])


if __name__ == '__main__':
    build()
