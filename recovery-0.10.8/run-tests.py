#!/usr/bin/env python3
"""Runs offline stubs only; never loads wp-config or any live database."""
import argparse,pathlib,re,subprocess,sys
sys.dont_write_bytecode=True
ROOT=pathlib.Path(__file__).resolve().parent
from verify import verify

def run(command,pattern=None):
    p=subprocess.run(command,capture_output=True,text=True,timeout=300)
    output=p.stdout+p.stderr
    if p.returncode or re.search(r'Fatal error|Parse error|Warning:|Uncaught |AssertionError|Segmentation fault',output,re.I) or (pattern and not re.search(pattern,output)):
        raise RuntimeError('Failed: '+' '.join(command)+'\n'+output)
    return output

def main():
    ap=argparse.ArgumentParser();ap.add_argument('--php',default='php');args=ap.parse_args()
    verify(ROOT)
    print(run([sys.executable,str(ROOT/'source/scripts/test-fotballrobot.py'),'--php',args.php]),end='',flush=True)
    print(run([sys.executable,str(ROOT/'qa/test-install.py')]),end='',flush=True)
    print(run([sys.executable,str(ROOT/'qa/test-package.py')]),end='',flush=True)
    for case in ['facts','register','start','missing','closed','unauthorized','nonce','vote','duplicate','bench-vote']:
        print(run([args.php,str(ROOT/'qa/poll-flow.php'),case],r'OK:'),end='',flush=True)
    print(run([args.php,str(ROOT/'qa/guard-tests.php'),'valid'],r'OK:'),end='',flush=True)
    for case in ['wrong-host','wrong-root','wrong-db','wrong-url','production','cron-on']:
        run([args.php,str(ROOT/'qa/guard-tests.php'),case],r'miljøkontrollen feilet')
        print('PASS: guard blocks '+case,flush=True)
    for p in [ROOT/'guard.php',ROOT/'qa/poll-flow.php',ROOT/'qa/guard-tests.php']:
        run([args.php,'-l',str(p)],r'No syntax errors detected')
    verify(ROOT)
    print('PASS: all tests completed; no VPS/WordPress database/external API/publication used')

if __name__=='__main__':main()
