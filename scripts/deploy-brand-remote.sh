#!/usr/bin/env bash
# Apply only the versioned logo patch to current production code; retain unrelated drift.
set -Eeuo pipefail
umask 077
release=${1:?}; mode=${2:?}; commit=${3:?}
[[ "$release" =~ ^brand-[0-9]+-[0-9]+$ ]]
[[ "$commit" =~ ^[0-9a-f]{40}$ ]]
[[ "$mode" == brand-dry-run || "$mode" == brand-apply ]]
root=/run/webroots/r1417157
theme="$root/wp-content/themes/radio-rubben-wordpress-v1"
state="$HOME/.radiorubben-deploy"
stage="$state/staging/$release"
candidate="$stage/candidate"
wp=(/usr/local/bin/wp --path="$root" --skip-plugins --skip-themes)
files=(footer.php functions.php header.php inc/bremnes-poll-test.php)
assets=(assets/brand/2026-09 assets/css/brand.css inc/brand.php)
for tool in patch php rsync tar sha256sum curl; do command -v "$tool" >/dev/null; done
[[ $("${wp[@]}" option get stylesheet) == radio-rubben-wordpress-v1 ]]
[[ $("${wp[@]}" option get home) == https://www.radiorubben.no ]]
[[ ! -f "$root/.maintenance" && ! -e "$candidate" ]]
mkdir "$state/lock"
trap 'rmdir "$state/lock"' EXIT
mkdir -p "$candidate/inc"
for file in "${files[@]}"; do
  [[ -f "$theme/$file" && ! -L "$theme/$file" ]]
  cp "$theme/$file" "$candidate/$file"
done
(cd "$theme" && sha256sum "${files[@]}") > "$stage/before.sha256"
(cd "$candidate" && patch --batch --forward --fuzz=0 -p1 < "$stage/brand-logo.patch")
for file in "${files[@]}"; do php -l "$candidate/$file"; done
php -l "$stage/inc/brand.php"
echo "BRAND RELEASE $release; Git commit $commit; mode $mode"
echo 'Before hashes:'; cat "$stage/before.sha256"
echo 'After hashes:'; (cd "$candidate" && sha256sum "${files[@]}")
if [[ "$mode" == brand-dry-run ]]; then
  echo 'Brand patch verified against current production; no public files changed.'
  exit
fi
backup="$state/backups/$release"
[[ ! -e "$backup" ]]
mkdir -p "$backup"
existing=("${files[@]}")
for file in "${assets[@]}"; do
  [[ ! -L "$theme/$file" ]]
  if [[ -e "$theme/$file" ]]; then existing+=("$file"); fi
done
tar -czf "$backup/code.tar.gz" -C "$theme" "${existing[@]}"
tar -tzf "$backup/code.tar.gz" >/dev/null
rollback() {
  trap - ERR HUP INT TERM
  echo "Brand deployment failed; restoring $backup/code.tar.gz"
  tar -xzf "$backup/code.tar.gz" -C "$theme" || exit 10
  "${wp[@]}" maintenance-mode deactivate || true
  exit 1
}
# Refuse concurrent edits after patch preparation and backup.
(cd "$theme" && sha256sum -c "$stage/before.sha256")
trap rollback ERR HUP INT TERM
"${wp[@]}" maintenance-mode activate
rsync -rlt --chmod=D755,F644 "$stage/assets/brand/" "$theme/assets/brand/"
install -m 644 "$stage/assets/css/brand.css" "$theme/assets/css/brand.css"
install -m 644 "$stage/inc/brand.php" "$theme/inc/brand.php"
for file in "${files[@]}"; do install -m 644 "$candidate/$file" "$theme/$file"; done
"${wp[@]}" maintenance-mode deactivate
curl --fail --silent --show-error --location --max-time 30 "https://www.radiorubben.no/?rr_brand=$release" -o "$stage/live.html"
grep -q 'assets/brand/2026-09/SVG/03-Hovedlogo-transparent-hvit.svg' "$stage/live.html"
grep -q 'assets/brand/2026-09/Ikoner/ikon-32.png' "$stage/live.html"
printf '%s\n' "$commit" > "$backup/commit.txt"
cp "$stage/brand-logo.patch" "$stage/before.sha256" "$backup/"
(cd "$theme" && sha256sum "${files[@]}") > "$backup/after.sha256"
trap - ERR HUP INT TERM
echo "Deployed $release; registered patch and receipt: $backup; unrelated code preserved."
