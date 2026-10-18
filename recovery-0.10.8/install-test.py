#!/usr/bin/env python3
"""Host-bound, file-only installation. Never activates WordPress or starts jobs."""
import fcntl,hashlib,json,os,pathlib,shutil,socket,subprocess,sys,tempfile
sys.dont_write_bytecode=True
from verify import verify,sha

KIT=pathlib.Path(__file__).resolve().parent
ROOT=pathlib.Path('/srv/radiorubben-test/wordpress')
STATE=pathlib.Path('/srv/radiorubben-test/recovery/football-0.10.8')
PLUGIN='wp-content/plugins/radio-rubben-fotballrobot'
GUARD='wp-content/mu-plugins/rr-football-test-guard.php'

def run(args):
    p=subprocess.run(args,capture_output=True,text=True,timeout=90)
    if p.returncode: raise ValueError('Required check failed: '+args[0])
    return p.stdout.strip()

def no_links(path):
    p=pathlib.Path(path)
    for parent in [p,*p.parents]:
        if parent.is_symlink(): raise ValueError('Symlink rejected: '+str(parent))

def db(query):
    return run(['runuser','-u','rr-wordpress','--','mysql','--protocol=SOCKET',
                '--socket=/run/mysqld/mysqld.sock','--batch','--skip-column-names','rr_test_wp','-e',query])

def preflight(action):
    if os.geteuid()!=0 or socket.gethostname()!='vps-5363103': raise ValueError('Only root on vps-5363103')
    no_links(ROOT);no_links(STATE)
    if str(ROOT.resolve())!='/srv/radiorubben-test/wordpress': raise ValueError('Wrong WordPress root')
    for local,reference in [('wp-config.php','wp-config.sample'),('wp-content/mu-plugins/rr-test-isolation.php','wp-isolation.php')]:
        no_links(ROOT/local)
        if sha(ROOT/local)!=sha(KIT/'evidence/infra'/reference): raise ValueError('Infrastructure drift: '+local)
    if run(['systemctl','show','php8.3-fpm','-p','PrivateNetwork','--value'])!='yes': raise ValueError('PHP network isolation missing')
    for service in ['php8.3-fpm','mariadb','nginx']:
        if run(['systemctl','is-active',service])!='active': raise ValueError('Inactive service')
    # Confirm the specific existing infrastructure verifier, without displaying credentials.
    verifier=pathlib.Path('/usr/local/sbin/rr-test-verify');no_links(verifier)
    if sha(verifier)!=sha(KIT/'evidence/infra/verify.sh'): raise ValueError('Infrastructure verifier drift')
    run([str(verifier)])
    ident=db('SELECT DATABASE(), SUBSTRING_INDEX(CURRENT_USER(),\'@\',1);').split('\t')
    if ident!=['rr_test_wp','rr-wordpress']: raise ValueError('Unexpected database identity')
    active=db("SELECT option_value FROM rrtest_options WHERE option_name='active_plugins';")
    if 'radio-rubben-fotballrobot/' in active: raise ValueError('Plugin must be inactive before install/rollback')
    # Reject populated integration options without printing their values.
    secrets=db("SELECT COUNT(*) FROM rrtest_options WHERE option_name REGEXP '^(rrfr_|rr_fd_|rr_openai|rr_microsoft)' AND option_name REGEXP '(key|secret|token|password|cwd|cid)$' AND option_value NOT IN ('','0','a:0:{}');")
    if secrets!='0': raise ValueError('Integration credentials found; do not copy or clear automatically')
    # No background task in this package. Existing test-targeted cron/services must be absent.
    for base in [pathlib.Path('/etc/cron.d'),pathlib.Path('/var/spool/cron/crontabs')]:
        if base.exists():
            for p in base.iterdir():
                if p.is_file() and not p.is_symlink():
                    text=p.read_text(errors='replace')
                    if any(x in text for x in ['fotballrobot','rrfr_','radiorubben-test']): raise ValueError('Review existing test cron first')
    units=run(['systemctl','list-units','--state=running','--type=service','--no-legend','--plain'])
    if any(x in units.lower() for x in ['rr-node','fotballrobot','rrfr','radiorubben-robot']): raise ValueError('Review existing worker first')
    for rel in [PLUGIN,GUARD]: no_links(ROOT/rel)
    print('PASS: host, canonical config, private PHP network, access gates, test DB, inactive plugin, credential/job checks')

def expected(m):
    result={p.removeprefix(PLUGIN+'/'):h for p,h in m['after'].items()}
    if len(result)!=38 or any(p.startswith('wp-content') for p in result): raise ValueError('Invalid install scope')
    return result

def check_tree(path,wanted):
    no_links(path)
    actual={}
    for p in path.rglob('*'):
        if p.is_symlink(): raise ValueError('Runtime symlink')
        if p.is_file(): actual[p.relative_to(path).as_posix()]=sha(p)
    if actual!=wanted: raise ValueError('Runtime drift or incomplete install')

def atomic_json(path,data):
    temp=path.with_suffix('.tmp');temp.write_text(json.dumps(data,indent=2)+'\n');os.chmod(temp,0o600);os.replace(temp,path)

def install_files(root,state,kit,m):
    """Fresh install only. No overwrite and no database operation."""
    target=root/PLUGIN;guard=root/GUARD
    for p in [root,state,target,guard]: no_links(p)
    if target.exists() or guard.exists() or state.exists(): raise ValueError('Fresh target and receipt required')
    state.mkdir(parents=True,mode=0o700)
    stage=pathlib.Path(tempfile.mkdtemp(prefix='.rrfr-stage-',dir=target.parent))
    staged_guard=guard.with_name('.rr-football-test-guard.stage')
    if staged_guard.exists(): raise ValueError('Unexpected guard staging file')
    wanted=expected(m);guard_hash=sha(kit/'guard.php')
    atomic_json(state/'receipt.json',{'version':'0.10.8','phase':'prepared','runtime':wanted,'guard':guard_hash,'prior':'absent','database_changed':False})
    try:
        for p,h in wanted.items():
            src=kit/'source/wordpress'/PLUGIN/p;dst=stage/p
            if sha(src)!=h: raise ValueError('Source changed')
            dst.parent.mkdir(parents=True,exist_ok=True);dst.write_bytes(src.read_bytes());os.chmod(dst,0o644)
        for p in [stage,*[p for p in stage.rglob('*') if p.is_dir()]]: os.chmod(p,0o755)
        check_tree(stage,wanted)
        with staged_guard.open('xb') as out: out.write((kit/'guard.php').read_bytes())
        os.chmod(staged_guard,0o644)
        if sha(staged_guard)!=guard_hash: raise ValueError('Guard mismatch')
        # The guard is installed first; the plugin remains inactive throughout.
        os.rename(staged_guard,guard);os.rename(stage,target)
        check_tree(target,wanted)
        atomic_json(state/'receipt.json',{'version':'0.10.8','phase':'installed-inactive','runtime':wanted,'guard':guard_hash,'prior':'absent','database_changed':False})
    except Exception:
        # Preserve staged bytes for inspection. Do not silently remove concurrent edits.
        raise

def rollback_files(root,state):
    no_links(state)
    receipt=json.loads((state/'receipt.json').read_text())
    if receipt['prior']!='absent' or receipt['version']!='0.10.8': raise ValueError('Wrong receipt')
    target=root/PLUGIN;guard=root/GUARD
    for p in [target,guard,state/'quarantine']: no_links(p)
    quarantine=state/'quarantine'
    # Validate every installed or previously quarantined file before moving any.
    tp=target if target.exists() else quarantine/'plugin'
    gp=guard if guard.exists() else quarantine/'guard.php'
    if tp.exists(): check_tree(tp,receipt['runtime'])
    if gp.exists() and sha(gp)!=receipt['guard']: raise ValueError('Guard changed; rollback stopped')
    quarantine.mkdir(mode=0o700,exist_ok=True)
    if target.exists():
        if (quarantine/'plugin').exists(): raise ValueError('Quarantine conflict')
        os.rename(target,quarantine/'plugin')
    if guard.exists():
        if (quarantine/'guard.php').exists(): raise ValueError('Guard quarantine conflict')
        os.rename(guard,quarantine/'guard.php')
    receipt['phase']='rolled-back';atomic_json(state/'receipt.json',receipt)

def main():
    action=sys.argv[1] if len(sys.argv)==2 else ''
    if action not in {'check','install','after','rollback'}: raise ValueError('Use check|install|after|rollback')
    m=verify(KIT)
    # Fixed location, no caller-selected host/root or security-bypass switch.
    preflight(action)
    lock=pathlib.Path('/run/lock/rr-football-test.lock');no_links(lock)
    with lock.open('a') as handle:
        fcntl.flock(handle,fcntl.LOCK_EX|fcntl.LOCK_NB)
        if action=='install': install_files(ROOT,STATE,KIT,m)
        elif action=='after':
            check_tree(ROOT/PLUGIN,expected(m))
            if sha(ROOT/GUARD)!=sha(KIT/'guard.php'): raise ValueError('Guard drift')
        elif action=='rollback': rollback_files(ROOT,STATE)
        print('PASS: '+action+'; no activation, database writes, external jobs or publication')

if __name__=='__main__':
    try: main()
    except Exception as e: print('BLOCKED: '+str(e),file=sys.stderr);sys.exit(1)
