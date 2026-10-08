#!/usr/bin/env bash
# Two approved theme files only. No database, plugin or theme activation changes.
set -Eeuo pipefail
umask 077
release=${1:?Release id required}
[[ "$release" =~ ^[0-9]+-[0-9]+$ ]] || exit 2
root=$(php -r 'echo realpath($argv[1]);' /run/webroots/r1417157)
[[ -d "$root" && "$root" == /* ]] || { echo 'Invalid resolved WordPress root'; exit 3; }
theme="$root/wp-content/themes/radio-rubben-next"
state="$HOME/.radiorubben-deploy"
stage="$state/staging/homepage-refinement-$release"
backup="$state/backups/homepage-refinement-$release"
wp=(/usr/local/bin/wp --path="$root" --skip-plugins --skip-themes)
front=front-page.php
css=assets/css/homepage-refinement.css
hash() { sha256sum "$1" | cut -d' ' -f1; }
for dir in "$root" "$theme" "$theme/assets" "$theme/assets/css" "$state" "$state/staging" "$stage"; do
  [[ -d "$dir" && ! -L "$dir" ]] || { echo "Invalid release directory: $dir"; exit 3; }
done
[[ $(php -r 'echo realpath($argv[1]);' "$theme") == "$root/wp-content/themes/radio-rubben-next" ]]
[[ $("${wp[@]}" option get stylesheet) == radio-rubben-next ]]
[[ $("${wp[@]}" option get home) == https://www.radiorubben.no ]]
[[ ! -e "$root/.maintenance" ]]
[[ -f "$theme/$front" && ! -L "$theme/$front" && ! -e "$theme/$css" && ! -L "$theme/$css" ]]
mkdir "$state/lock" || { echo 'Deployment lock exists; no changes'; exit 3; }
trap 'rmdir "$state/lock"' EXIT
(cd "$stage/new"; sha256sum --check ../SHA256SUMS)
php -l "$stage/new/$front"
# Compare the freshly observed live source to the reviewed baseline. CRLF only
# is normalized for comparison; backup and recorded server hashes keep raw bytes.
sha256sum "$theme/$front" > "$stage/live-before.sha256"
cat "$stage/live-before.sha256"
normalize() { php -r 'echo rtrim(str_replace("\r\n", "\n", file_get_contents($argv[1])), "\n") . "\n";' "$1"; }
normalize "$theme/$front" > "$stage/live-front-lf.php"
normalize "$stage/baseline-front.php" > "$stage/baseline-front-lf.php"
cmp --silent "$stage/live-front-lf.php" "$stage/baseline-front-lf.php" || { echo 'Live entry point differs from reviewed baseline; no changes'; diff -u "$stage/baseline-front-lf.php" "$stage/live-front-lf.php" || true; exit 4; }
[[ ! -L "$state/backups" ]]
mkdir -p "$state/backups"
mkdir "$backup"
cp -p "$theme/$front" "$backup/$front"
[[ $(hash "$backup/$front") == "$(hash "$theme/$front")" ]]
printf '%s\n' "$css" > "$backup/previously-absent.txt"
(cd "$theme"; find . -type f ! -path './front-page.php' ! -path './assets/css/homepage-refinement.css' -print0 | sort -z | xargs -0 sha256sum) > "$stage/untouched.sha256"
changed=()
rollback() {
  trap - ERR HUP INT TERM
  set +e
  failed=0
  for ((i=${#changed[@]}-1;i>=0;i--)); do
    path=${changed[$i]}
    [[ $(hash "$theme/$path") == "$(hash "$stage/new/$path")" ]] || { echo "Concurrent change: refusing to overwrite $path"; failed=1; break; }
    if [[ "$path" == "$front" ]]; then
      install -m 644 "$backup/$front" "$theme/.homepage-restore-$release.php" && mv -f "$theme/.homepage-restore-$release.php" "$theme/$front" || failed=1
    else
      rm -f -- "$theme/$css" || failed=1
    fi
  done
  echo "HOMEPAGE_ROLLBACK_STATUS=$failed backup=$backup"
  exit 1
}
trap rollback ERR HUP INT TERM
# CSS first, entry point last. Each rename stays within the theme filesystem.
for path in "$css" "$front"; do
  [[ ! -L "$theme/$path" ]]
  temp="$theme/$(dirname "$path")/.homepage-$release-$(basename "$path")"
  install -m 644 "$stage/new/$path" "$temp"
  [[ $(hash "$temp") == "$(hash "$stage/new/$path")" ]]
  mv -f -- "$temp" "$theme/$path"
  changed+=("$path")
done
(cd "$theme"; sha256sum --check "$stage/SHA256SUMS")
(cd "$theme"; sha256sum --check --quiet "$stage/untouched.sha256")
curl --fail --silent --show-error --location --max-time 30 -H 'Cache-Control: no-cache' "https://www.radiorubben.no/?rr_homepage_refinement=$release" -o "$stage/home.html"
grep -q 'id="nyheter-tittel">Siste nytt' "$stage/home.html"
grep -q 'rr-home-sidebar' "$stage/home.html"
grep -q 'rr-play-toggle' "$stage/home.html"
# Minification can merge the stylesheet; verify the served CSS file directly.
curl --fail --silent --show-error --max-time 30 "https://www.radiorubben.no/wp-content/themes/radio-rubben-next/$css?ver=2026.10.08.1" -o "$stage/public.css"
cmp --silent "$stage/public.css" "$stage/new/$css"
trap - ERR HUP INT TERM
echo "HOMEPAGE_REFINEMENT_DEPLOYED source=e0e04ba79fcfc47dd27a0e3880325ae18cfaa642 release=$release backup=$backup"
(cd "$theme"; sha256sum "$front" "$css")
