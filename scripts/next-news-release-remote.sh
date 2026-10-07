#!/usr/bin/env bash
# Three-file, user-authorized Next release. No production backup is created.
set -Eeuo pipefail
umask 077
release=${1:?Release required}
source_sha=${2:?Source SHA required}
[[ "$release" =~ ^[0-9]+-[0-9]+$ ]] || exit 2
[[ "$source_sha" == 332e443349a79d225f7b659ee22e798a831ef7df ]] || exit 2
root=/run/webroots/r1417157
state="$HOME/.radiorubben-deploy"
stage="$state/staging/next-news-$release"
theme="$root/wp-content/themes/radio-rubben-next"
wp=(/usr/local/bin/wp --path="$root" --skip-plugins --skip-themes)
before=4f727fe11d63e64974b92ecd5a94ebd1c2fec3612497d584ef59461a89ddbf13
paths=(template-parts/home/news-priority.php assets/css/news-priority.css front-page.php)
hash() { sha256sum "$1" | cut -d' ' -f1; }
for dir in "$state" "$state/staging" "$stage" "$theme" "$theme/template-parts" "$theme/template-parts/home" "$theme/assets" "$theme/assets/css"; do
  [[ -d "$dir" && ! -L "$dir" ]] || { echo "Invalid release directory: $dir"; exit 3; }
done
[[ $("${wp[@]}" option get stylesheet) == radio-rubben-next ]]
[[ $("${wp[@]}" option get home) == https://www.radiorubben.no ]]
[[ ! -e "$root/.maintenance" ]]
mkdir "$state/lock" || { echo 'Deployment lock exists; no changes made'; exit 3; }
trap 'rmdir "$state/lock"' EXIT
for path in "${paths[@]}"; do
  [[ -f "$stage/new/$path" && ! -L "$stage/new/$path" && ! -L "$theme/$path" ]]
done
(cd "$stage/new"; sha256sum --check ../SHA256SUMS)
[[ $(hash "$stage/git-baseline-front.php") == "$before" ]]
# Refuse stale source or concurrent changes. Only these exact audited files apply.
[[ -f "$theme/front-page.php" && $(hash "$theme/front-page.php") == "$before" ]]
[[ ! -e "$theme/template-parts/home/news-priority.php" && ! -e "$theme/assets/css/news-priority.css" ]]
php -l "$stage/new/front-page.php"
php -l "$stage/new/template-parts/home/news-priority.php"
# Real WordPress taxonomy queries and public-visibility checks; no DB mutations.
"${wp[@]}" eval-file "$stage/verify-query.php"
# Record hashes only, not copies, of all other theme files.
(cd "$theme"; find . -type f ! -path './front-page.php' ! -path './template-parts/home/news-priority.php' ! -path './assets/css/news-priority.css' -print0 | sort -z | xargs -0 sha256sum) > "$stage/untouched.sha256"
changed=()
rollback() {
  trap - ERR HUP INT TERM
  set +e
  echo 'Verification failed; restoring the exact Git baseline, not a server backup.'
  failed=0
  # Restore the entry point first, before removing its new dependencies.
  for ((i=${#changed[@]}-1;i>=0;i--)); do
    path=${changed[$i]}
    if [[ $(hash "$theme/$path") != "$(hash "$stage/new/$path")" ]]; then
      echo "Concurrent change detected; refusing to overwrite $path"; failed=1; break
    fi
    if [[ "$path" == front-page.php ]]; then
      install -m 644 "$stage/git-baseline-front.php" "$theme/.next-news-restore-$release.php"
      mv -f "$theme/.next-news-restore-$release.php" "$theme/front-page.php" || { failed=1; break; }
    else
      rm -f -- "$theme/$path" || failed=1
    fi
  done
  for path in "${paths[@]}"; do rm -f -- "$theme/$(dirname "$path")/.next-news-$release-$(basename "$path")"; done
  echo "Rollback status: $failed"
  exit 1
}
trap rollback ERR HUP INT TERM
# Dependencies first; front-page.php changes last via same-directory rename.
for path in "${paths[@]}"; do
  temp="$theme/$(dirname "$path")/.next-news-$release-$(basename "$path")"
  install -m 644 "$stage/new/$path" "$temp"
  [[ $(hash "$temp") == "$(hash "$stage/new/$path")" ]]
  mv -f -- "$temp" "$theme/$path"
  changed+=("$path")
done
(cd "$theme"; sha256sum --check "$stage/SHA256SUMS")
(cd "$theme"; sha256sum --check --quiet "$stage/untouched.sha256")
curl --fail --silent --show-error --location --retry 2 --max-time 30 -H 'Cache-Control: no-cache' \
  "https://www.radiorubben.no/?rr_next_news_release=$release" -o "$stage/home-after.html"
grep -q 'class="rr-news-first"' "$stage/home-after.html"
grep -q 'id="nyheter-tittel">Siste nytt' "$stage/home-after.html"
grep -q 'id="sport-tittel">Sport fra Bømlo' "$stage/home-after.html"
grep -q 'rrpw-home' "$stage/home-after.html"
grep -q 'rr-play-toggle' "$stage/home-after.html"
trap - ERR HUP INT TERM
printf 'NEXT_NEWS_DEPLOYED source=%s release=%s backup=none\n' "$source_sha" "$release"
(cd "$theme"; sha256sum "${paths[@]}")
