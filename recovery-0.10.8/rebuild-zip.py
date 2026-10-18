#!/usr/bin/env python3
"""Same files yield identical ZIP bytes; no clock, network or absolute paths."""
import pathlib,sys,zipfile
root=pathlib.Path(sys.argv[1]).resolve();out=pathlib.Path(sys.argv[2]).resolve()
if out.is_relative_to(root): raise SystemExit('Output must be outside input tree')
with zipfile.ZipFile(out,'w',compression=zipfile.ZIP_STORED) as z:
    for p in sorted(root.rglob('*')):
        if p.is_symlink(): raise SystemExit('Symlink rejected')
        if p.is_file():
            info=zipfile.ZipInfo(p.relative_to(root).as_posix(),(2026,10,18,0,0,0))
            info.create_system=3;info.external_attr=0o100644<<16
            z.writestr(info,p.read_bytes())
