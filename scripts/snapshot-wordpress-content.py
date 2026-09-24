#!/usr/bin/env python3
"""Create a deterministic Git snapshot of public WordPress pages and posts."""

import argparse
import json
from pathlib import Path


FIELDS = ("ID", "post_type", "post_status", "post_title", "post_name", "post_modified", "post_content", "post_password")
PUBLIC_FIELDS = ("ID", "post_type", "post_title", "post_name", "post_modified", "post_content")
KINDS = {"page": "pages", "post": "posts"}


def export(source: Path, destination: Path, artifact: Path) -> int:
    rows = json.loads(source.read_text(encoding="utf-8"))
    if not isinstance(rows, list) or not rows:
        raise ValueError("Tom eller ugyldig WordPress-eksport; historikken endres ikke.")

    public = []
    seen = set()
    for row in rows:
        if not isinstance(row, dict) or any(field not in row for field in FIELDS):
            raise ValueError("WordPress-eksporten mangler forventede felt.")
        if row["post_type"] not in KINDS or row["post_status"] != "publish":
            raise ValueError("WordPress returnerte innhold utenfor publiserte sider/innlegg.")
        if row["post_password"]:
            continue
        post_id = int(row["ID"])
        if post_id < 1 or post_id in seen:
            raise ValueError("Ugyldig eller duplisert WordPress-ID.")
        seen.add(post_id)
        public.append({field: row[field] for field in PUBLIC_FIELDS})

    if not public:
        raise ValueError("Ingen ubeskyttede sider/innlegg; historikken endres ikke.")
    public.sort(key=lambda row: int(row["ID"]))

    destination.mkdir(parents=True, exist_ok=True)
    (destination / "README.md").write_text(
        "# Publisert WordPress-innhold\n\n"
        "Automatisk historikk fra RadioRubben.no. Hver fil har en stabil WordPress-ID "
        "og kan sammenlignes mellom Git-commits. Kun publiserte, ubeskyttede "
        "sider og innlegg er med. Endringer her publiseres ikke til WordPress.\n",
        encoding="utf-8",
    )
    for row in public:
        folder = destination / KINDS[row["post_type"]]
        folder.mkdir(exist_ok=True)
        (folder / f'{int(row["ID"])}.json').write_text(
            json.dumps(row, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
        )
    artifact.write_text(json.dumps(public, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    return len(public)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("source", type=Path)
    parser.add_argument("destination", type=Path)
    parser.add_argument("artifact", type=Path)
    args = parser.parse_args()
    print(f"Eksporterte {export(args.source, args.destination, args.artifact)} publiserte sider/innlegg.")
