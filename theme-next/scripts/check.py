"""Portable source checks; WordPress runtime checks live in tests/integration.php."""
from pathlib import Path
import json
import re
import subprocess

root = Path(__file__).resolve().parents[1]
theme = root / 'radio-rubben-next'
packages = [theme, root / 'rr-editorial-contract', root / 'radio-rubben-child']
php_files = [p for package in packages for p in package.rglob('*.php')]
for file in php_files:
    subprocess.run(['php', '-l', str(file)], check=True, capture_output=True)
for file in theme.rglob('*.js'):
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
            assert file.suffix.lower() in ['.php', '.css', '.js', '.json', '.md', '.txt', '.png', '.webp'], str(file)
print(f'PASS: {len(php_files)} PHP files, JavaScript syntax, JSON, templates, child parent, presentation boundary and package file allowlist.')
