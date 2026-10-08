#!/usr/bin/env bash
# Quiz archive release: four plugin files with baseline, backup and rollback.
set -Eeuo pipefail
umask 077
release=${1:?Release id required}
[[ "$release" =~ ^[0-9]+-[0-9]+$ ]] || exit 2
root=$(php -r 'echo realpath($argv[1]);' /run/webroots/r1417157)
[[ "$root" == /customers/9/3/1/cptk37ymg/webroots/r1417157 ]] || exit 3
theme="$root/wp-content/plugins/rr-site-functions"
state="$HOME/.radiorubben-deploy"
stage="$state/staging/quiz-history-$release"
backup="$state/backups/quiz-history-$release"
paths=(inc/weekly-quiz-history.php assets/css/weekly-quiz.css assets/js/weekly-quiz.js inc/weekly-quiz.php)
existing=(assets/css/weekly-quiz.css assets/js/weekly-quiz.js inc/weekly-quiz.php)
new=(inc/weekly-quiz-history.php)
wp=(/usr/local/bin/wp --path="$root" --skip-plugins --skip-themes)
hash() { sha256sum "$1" | cut -d' ' -f1; }
normalize() { php -r 'echo rtrim(str_replace("\r\n", "\n", file_get_contents($argv[1])), "\n") . "\n";' "$1"; }
for dir in "$root" "$theme" "$theme/assets" "$theme/assets/css" "$theme/assets/js" "$theme/inc" "$state" "$state/staging" "$state/backups" "$stage"; do
  [[ -d "$dir" && ! -L "$dir" ]] || { echo "Invalid directory: $dir"; exit 3; }
done
[[ $(php -r 'echo realpath($argv[1]);' "$theme") == "$root/wp-content/plugins/rr-site-functions" ]]
[[ $("${wp[@]}" option get stylesheet) == radio-rubben-next ]]
[[ $("${wp[@]}" option get home) == https://www.radiorubben.no ]]
"${wp[@]}" plugin is-active rr-site-functions
[[ ! -e "$root/.maintenance" ]]
mkdir "$state/lock" || { echo 'Deployment lock exists'; exit 3; }
trap 'rmdir "$state/lock"' EXIT
(cd "$stage/new"; sha256sum --check ../SHA256SUMS)
for path in inc/weekly-quiz-history.php inc/weekly-quiz.php; do php -l "$stage/new/$path"; done
: > "$stage/live-before.sha256"
for path in "${existing[@]}"; do
  [[ -f "$theme/$path" && ! -L "$theme/$path" ]]
  (cd "$theme"; sha256sum "$path") >> "$stage/live-before.sha256"
  normalize "$theme/$path" > "$stage/live-lf"
  normalize "$stage/baseline/$path" > "$stage/baseline-lf"
  cmp --silent "$stage/live-lf" "$stage/baseline-lf" || { echo "Baseline differs: $path; no changes"; exit 4; }
done
for path in "${new[@]}"; do [[ ! -e "$theme/$path" && ! -L "$theme/$path" ]]; done
cat "$stage/live-before.sha256"
mkdir "$backup"
for path in "${existing[@]}"; do
  mkdir -p "$backup/$(dirname "$path")"
  cp -p "$theme/$path" "$backup/$path"
  [[ $(hash "$backup/$path") == "$(hash "$theme/$path")" ]]
done
cp "$stage/live-before.sha256" "$backup/SHA256SUMS"
printf '%s\n' "${new[@]}" > "$backup/previously-absent.txt"
(cd "$backup"; sha256sum --check SHA256SUMS)
(cd "$theme"; find . -type f ! -path './inc/weekly-quiz-history.php' ! -path './assets/css/weekly-quiz.css' ! -path './assets/js/weekly-quiz.js' ! -path './inc/weekly-quiz.php' -print0 | sort -z | xargs -0 sha256sum) > "$stage/untouched.sha256"
refresh_cache() {
  /usr/local/bin/wp --path="$root" --skip-themes eval 'if (class_exists("WP_Optimize_Minify_Cache_Functions")) { WP_Optimize_Minify_Cache_Functions::cache_increment(); echo "MINIFY_CACHE_REBUILT\n"; } if (function_exists("WP_Optimize")) { $c = WP_Optimize()->get_page_cache(); if ($c->is_enabled() && !$c->purge()) { throw new Exception("Page cache purge failed"); } echo "PAGE_CACHE_REFRESHED\n"; }'
}
changed=()
rollback() {
  trap - ERR HUP INT TERM
  set +e
  failed=0
  for ((i=${#changed[@]}-1;i>=0;i--)); do
    path=${changed[$i]}
    [[ $(hash "$theme/$path") == "$(hash "$stage/new/$path")" ]] || { echo "Concurrent change: $path; refusing overwrite"; failed=1; break; }
    if [[ -f "$backup/$path" ]]; then
      temp="$theme/$(dirname "$path")/.followup-restore-$release-$(basename "$path")"
      install -m 644 "$backup/$path" "$temp" && mv -f -- "$temp" "$theme/$path" || failed=1
      [[ $(hash "$theme/$path") == "$(hash "$backup/$path")" ]] || failed=1
    else
      rm -f -- "$theme/$path" || failed=1
    fi
  done
  refresh_cache || failed=1
  echo "FOLLOWUP_ROLLBACK_STATUS=$failed backup=$backup"
  exit 1
}
trap rollback ERR HUP INT TERM
for path in "${paths[@]}"; do
  [[ ! -L "$theme/$path" ]]
  temp="$theme/$(dirname "$path")/.followup-$release-$(basename "$path")"
  install -m 644 "$stage/new/$path" "$temp"
  [[ $(hash "$temp") == "$(hash "$stage/new/$path")" ]]
  mv -f -- "$temp" "$theme/$path"
  changed+=("$path")
done
(cd "$theme"; sha256sum --check "$stage/SHA256SUMS")
(cd "$theme"; sha256sum --check --quiet "$stage/untouched.sha256")
refresh_cache
/usr/local/bin/wp --path="$root" eval 'if (!has_action("wp_ajax_nopriv_rrwq_history") || !function_exists("rrwq_history_data")) { throw new Exception("History endpoint unavailable"); } $data=rrwq_history_data(); if (!is_array($data) || !isset($data["weeks"],$data["overall"])) { throw new Exception("Invalid history response"); } echo "LIVE_PUBLIC_HISTORY_SERVICE_PASSED\n";'
curl --fail --silent --show-error --location --max-time 45 "https://www.radiorubben.no/quiz/?rr_history=$release" -o "$stage/quiz.html"
grep -q 'rrq-board' "$stage/quiz.html"
for path in assets/css/weekly-quiz.css assets/js/weekly-quiz.js; do
  curl --fail --silent --show-error --max-time 30 "https://www.radiorubben.no/wp-content/plugins/rr-site-functions/$path?rr_history=$release" -o "$stage/public.asset"
  cmp --silent "$stage/public.asset" "$stage/new/$path"
done
trap - ERR HUP INT TERM
echo "QUIZ_HISTORY_DEPLOYED source=f28bb43516ba012bb0ed539f9cbf752191613a31 release=$release backup=$backup"
(cd "$theme"; sha256sum "${paths[@]}")
