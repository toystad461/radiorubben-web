"""Exercise exact file publication and forced rollback in a disposable filesystem."""
from pathlib import Path
import os
import subprocess
import tempfile

repo = Path(__file__).resolve().parents[1]
paths = ['inc/quiz-controls.php']
existing = paths
baseline = '2b4779dd778faf8b02913f75fdfacf2523e81a15'
source = (repo / 'scripts/member-login-return-release.sh').read_text()
for fail in (False, True):
    with tempfile.TemporaryDirectory() as tmp:
        home = Path(tmp)
        root = home / 'webroot'
        theme = root / 'wp-content/plugins/rr-site-functions'
        stage = home / '.radiorubben-deploy/staging/member-login-return-123-1'
        (home / '.radiorubben-deploy/backups').mkdir(parents=True)
        originals = {}
        for path in paths:
            target = stage / 'new' / path
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes((repo / 'theme-next/rr-site-functions' / path).read_bytes())
            (theme / path).parent.mkdir(parents=True, exist_ok=True)
        for path in existing:
            data = subprocess.check_output(['git', 'show', f'{baseline}:theme-next/rr-site-functions/{path}'], cwd=repo)
            originals[path] = data
            (theme / path).write_bytes(data)
            target = stage / 'baseline' / path
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(data)
        (theme / 'sentinel.txt').write_text('untouched\n')
        sums = subprocess.check_output(['sha256sum', *paths], cwd=stage / 'new')
        (stage / 'SHA256SUMS').write_bytes(sums)
        bin_dir = home / 'bin'
        bin_dir.mkdir()
        wp = bin_dir / 'wp'
        wp.write_text('#!/usr/bin/env bash\ncase "$*" in *"option get stylesheet"*) echo radio-rubben-next;; *"option get home"*) echo https://www.radiorubben.no;; esac\n')
        wp.chmod(0o700)
        curl = bin_dir / 'curl'
        curl.write_text('''#!/usr/bin/env python3
import os,sys
from pathlib import Path
args=sys.argv[1:]
url=next(a for a in args if a.startswith('https://'))
out=Path(args[args.index('-o')+1])
if os.environ.get('RR_TEST_FAIL') == '1': sys.exit(22)
if '/assets/css/' in url:
 p=url.split('/radio-rubben-next/')[1].split('?')[0]
 out.write_bytes((Path(os.environ['RR_TEST_STAGE'])/'new'/p).read_bytes())
else:
 out.write_text('Min Rubben')
''')
        curl.chmod(0o700)
        script = home / 'release.sh'
        adapted = source.replace('root=$(php -r \'echo realpath($argv[1]);\' /run/webroots/r1417157)', f'root="{root}"')
        adapted = adapted.replace('/customers/9/3/1/cptk37ymg/webroots/r1417157', str(root))
        adapted = adapted.replace('/usr/local/bin/wp', str(wp))
        script.write_text(adapted)
        env = dict(os.environ, HOME=str(home), PATH=f'{bin_dir}:{os.environ["PATH"]}', RR_TEST_STAGE=str(stage), RR_TEST_FAIL='1' if fail else '0')
        result = subprocess.run(['bash', str(script), '123-1'], env=env, capture_output=True, text=True)
        assert result.returncode == (1 if fail else 0), result.stdout + result.stderr
        assert (theme / 'sentinel.txt').read_text() == 'untouched\n'
        assert not (home / '.radiorubben-deploy/lock').exists()
        if fail:
            assert 'FOLLOWUP_ROLLBACK_STATUS=0' in result.stdout, result.stdout
            for path, data in originals.items(): assert (theme / path).read_bytes() == data, path
            assert (theme / paths[0]).exists()
        else:
            for path in paths: assert (theme / path).read_bytes() == (stage / 'new' / path).read_bytes(), path
        print(f'PASS: {"forced rollback" if fail else "one-file publication"}; original/untouched files and lock verified')
