"""No network or production writes: enforce the transport boundary."""
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import tempfile
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('image_package', Path(__file__).with_name('package.py'))
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


def invoke(overrides):
    with tempfile.TemporaryDirectory() as temp:
        event = Path(temp) / 'event.json'
        event.write_text(json.dumps({'inputs': {'action': 'verify', 'source_sha': 'a' * 40, **overrides}}))
        with patch.dict(os.environ, {'RUNNER_TEMP': temp, 'GITHUB_EVENT_PATH': str(event), 'GITHUB_SHA': 'a' * 40, 'GITHUB_REF': 'refs/heads/main'}):
            module.build()
        output = Path(temp) / 'news-images'
        manifest = json.loads((output / 'manifest.json').read_text())
        assert set(manifest['files']) == set(module.FILES)
        assert len(manifest['files']) == 7
        for name, sha in manifest['files'].items():
            assert hashlib.sha256((output / 'plugin' / name).read_bytes()).hexdigest() == sha
        assert not (output / 'plugin/tests').exists()
        assert not (output / 'RadioRubben-Nyhetsbilder-v1.2.zip').exists()


invoke({})
for invalid in ({'source_sha': 'b' * 40}, {'action': 'delete'}, {'action': 'install', 'package_url': 'https://example.test/private.zip'}):
    try:
        invoke(invalid)
    except AssertionError:
        pass
    else:
        raise AssertionError('Unsafe release input accepted')
print('PASS exact seven-file package, source lease, operation and download-host guards')
