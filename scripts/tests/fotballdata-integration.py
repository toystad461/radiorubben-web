"""Run the existing plugin tests against the repaired files, without deploying a plugin tree."""
import io
from pathlib import Path
import shutil
import subprocess
import tempfile
import zipfile

ROOT = Path(__file__).resolve().parents[2]
BASE = '10e9684d6061deccc9bdd5ac39d3cf8251f4bfc4'
PLUGIN = Path('wordpress/wp-content/plugins/radio-rubben-fotballrobot')
with tempfile.TemporaryDirectory(prefix='rrfr-test-', dir=ROOT) as directory:
    target = Path(directory)
    archive = subprocess.check_output(['git', 'archive', '--format=zip', BASE,
                                      str(PLUGIN).replace('\\', '/'), 'scripts/fotballrobot-cron.php'], cwd=ROOT)
    zipfile.ZipFile(io.BytesIO(archive)).extractall(target)
    for name in ('fotballdata.php', 'club-coverage.php', 'match-followup.php'):
        relative = PLUGIN / 'includes' / name
        shutil.copyfile(ROOT / relative, target / relative)
    # One intentional contract change: a valid empty team list is now normal.
    test = target / PLUGIN / 'tests/club-coverage.php'
    source = test.read_text(encoding='utf-8')
    old = "rejects(fn()=>ClubCoverage::teams(['ClubId'=>827,'Teams'=>[]]),'empty feed is not no teams');"
    new = "check(ClubCoverage::teams(['ClubId'=>827,'Teams'=>[]])===[],'valid empty team list is no work');"
    assert source.count(old) == 1, 'Pinned baseline expectation changed'
    test.write_text(source.replace(old, new), encoding='utf-8')
    for name in ('match-followup.php', 'match-source.php', 'club-coverage.php', 'fotballdata-date.php', 'match-jobs.php'):
        subprocess.run(['php', str(target / PLUGIN / 'tests' / name)], check=True, cwd=target)
