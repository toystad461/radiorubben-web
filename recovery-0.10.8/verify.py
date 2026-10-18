#!/usr/bin/env python3
import hashlib,json,pathlib,sys

def sha(p): return hashlib.sha256(p.read_bytes()).hexdigest()
def verify(root):
    root=pathlib.Path(root)
    inventory=json.loads((root/'FILES.json').read_text())
    actual=set()
    for p in root.rglob('*'):
        if p.is_symlink(): raise ValueError('Symlink rejected: '+str(p))
        if p.is_file(): actual.add(p.relative_to(root).as_posix())
    if actual != set(inventory)|{'FILES.json'}: raise ValueError('Missing or extra package files')
    for name,h in inventory.items():
        if '..' in pathlib.PurePosixPath(name).parts or pathlib.PurePosixPath(name).is_absolute(): raise ValueError('Unsafe path')
        if sha(root/name)!=h: raise ValueError('Package checksum mismatch: '+name)
    manifest=root/'evidence/scripts/details-release.json'
    # Independent Git blob SHA-1 binds this manifest to PR92, in addition to SHA-256 for its files.
    data=manifest.read_bytes()
    if hashlib.sha1(b'blob '+str(len(data)).encode()+b'\0'+data).hexdigest()!='0450c1b1dc5d088701e348a193b56eb2372ed3a0': raise ValueError('Unrecognized production manifest')
    m=json.loads(data)
    if m['version']!='0.10.8' or len(m['after'])!=38: raise ValueError('Release scope')
    for name,h in m['after'].items():
        if sha(root/'source/wordpress'/name)!=h: raise ValueError('Runtime mismatch: '+name)
    plugin=root/'source/wordpress/wp-content/plugins/radio-rubben-fotballrobot'
    runtime={p.relative_to(root/'source/wordpress').as_posix() for p in plugin.rglob('*')
             if p.is_file() and 'tests' not in p.relative_to(plugin).parts}
    if runtime!=set(m['after']): raise ValueError('Runtime inventory mismatch')
    expected=''.join(f'{h}  source/wordpress/{p}\n' for p,h in sorted(m['after'].items()))
    if (root/'runtime.sha256').read_text()!=expected: raise ValueError('Checksum list mismatch')
    return m

if __name__=='__main__':
    verify(pathlib.Path(sys.argv[1]) if len(sys.argv)>1 else pathlib.Path(__file__).parent)
    print('PASS: full package inventory and 38/38 production manifest hashes')
