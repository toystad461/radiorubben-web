"""Portable source checks; WordPress runtime checks live in tests/integration.php."""
from pathlib import Path
import json
import re
import subprocess
import xml.etree.ElementTree as ET

root = Path(__file__).resolve().parents[1]
theme = root / 'radio-rubben-next'
packages = [theme, root / 'rr-editorial-contract', root / 'radio-rubben-child', root / 'rr-site-functions']
php_files = [p for package in packages for p in package.rglob('*.php')] + list((root / 'tests').glob('*.php'))
for file in php_files:
    subprocess.run(['php', '-l', str(file)], check=True, capture_output=True)
for file in [p for package in packages for p in package.rglob('*.js')]:
    subprocess.run(['node', '--check', str(file)], check=True)
for file in root.rglob('*.json'):
    json.loads(file.read_text())
config = json.loads((theme / 'theme.json').read_text())
assert config['version'] == 3
assert config['settings']['color']['palette']
for name in ['style.css', 'index.php', 'functions.php', 'front-page.php', 'page.php', 'single.php', 'archive.php', 'search.php', '404.php']:
    assert (theme / name).is_file(), name
assert 'Template: radio-rubben-next' in (root / 'radio-rubben-child/style.css').read_text()
# Enforce the presentation boundary: new persistent business logic belongs outside theme.
for file in theme.rglob('*.php'):
    code = file.read_text()
    assert not re.search(r'\b(?:register_post_type|register_rest_route|wp_remote_get|wp_remote_post|wp_schedule_event|wp_insert_post|wp_update_post|update_option)\s*\(', code), str(file)
# Refuse runtime data or credential files in distributable source directories.
for package in packages:
    for file in package.rglob('*'):
        assert not file.is_symlink(), str(file)
        if file.is_file():
            assert file.name not in ['wp-config.php', '.env', '.DS_Store'], str(file)
            assert file.suffix.lower() in ['.php', '.css', '.js', '.json', '.md', '.txt', '.png', '.webp', '.jpg', '.svg'], str(file)
            if file.suffix.lower() == '.svg':
                # Bundled logo outlines only; no scripts, external resources or embedded markup.
                svg = ET.parse(file).getroot()
                allowed_tags = {'svg', 'title', 'g', 'path'}
                allowed_attributes = {'width', 'height', 'viewBox', 'transform', 'fill', 'fill-rule', 'd'}
                for node in svg.iter():
                    assert node.tag.startswith('{http://www.w3.org/2000/svg}'), str(file)
                    assert node.tag.split('}')[-1] in allowed_tags, str(file)
                    assert set(node.attrib) <= allowed_attributes, str(file)
                    assert not any('url(' in value.lower() for value in node.attrib.values()), str(file)
print(f'PASS: {len(php_files)} PHP files, JavaScript syntax, JSON, templates, child parent, presentation boundary and package file allowlist.')
