#!/usr/bin/env bash
# Member login return: one reviewed plugin file, verified backup and rollback.
set -Eeuo pipefail
umask 077
release=${1:?Release id required}
[[ "$release" =~ ^[0-9]+-[0-9]+$ ]] || exit 2
root=$(php -r 'echo realpath($argv[1]);' /run/webroots/r1417157)
[[ "$root" == /customers/9/3/1/cptk37ymg/webroots/r1417157 ]] || exit 3
theme="$root/wp-content/plugins/rr-site-functions"
state="$HOME/.radiorubben-deploy"
stage="$state/staging/member-login-return-$release"
backup="$state/backups/member-login-return-$release"
paths=(inc/quiz-controls.php)
existing=(inc/quiz-controls.php)
new=()
wp=(/usr/local/bin/wp --path="$root" --skip-plugins --skip-themes)
hash() { sha256sum "$1" | cut -d' ' -f1; }
normalize() { php -r 'echo rtrim(str_replace("\r\n", "\n", file_get_contents($argv[1])), "\n") . "\n";' "$1"; }
for dir in "$root" "$theme" "$theme/inc" "$state" "$state/staging" "$state/backups" "$stage"; do
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
for path in inc/quiz-controls.php; do php -l "$stage/new/$path"; done
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
(cd "$theme"; find . -type f ! -path './inc/quiz-controls.php' -print0 | sort -z | xargs -0 sha256sum) > "$stage/untouched.sha256"
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
/usr/local/bin/wp --path="$root" eval 'foreach (["/min-side/"=>home_url("/min-side/"),"/quiz/"=>home_url("/quiz/#rr-weekly")] as $path=>$expected) { $session=new ArrayObject(["referer"=>home_url($path)]); if (rr_quiz_vipps_return($session)!==$expected) { throw new Exception("Return route failed"); } do_action("continue_with_vipps_before_wordpress_login_redirect",new WP_User(),$session); if (apply_filters("login_redirect",admin_url("profile.php"),"",new WP_User())!==$expected) { throw new Exception("Login hook failed"); } if (apply_filters("continue_with_vipps_wordpress_confirm_redirect",admin_url("profile.php"),0,$session)!==$expected) { throw new Exception("Confirmation hook failed"); } } echo "LIVE_MEMBER_AND_QUIZ_RETURN_HOOKS_PASSED\n";'
curl --fail --silent --show-error --location --max-time 45 "https://www.radiorubben.no/min-side/?rr_login_return=$release" -o "$stage/member.html"
grep -q 'Min Rubben' "$stage/member.html"
trap - ERR HUP INT TERM
echo "MEMBER_LOGIN_RETURN_DEPLOYED source=47de32dc6c329d1a50a4b423ee4626fba6c6e8c2 release=$release backup=$backup"
(cd "$theme"; sha256sum "${paths[@]}")
