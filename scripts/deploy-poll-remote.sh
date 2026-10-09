#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
trap 'echo "ERROR: remote preflight failed at line $LINENO" >&2' ERR
release=${1:?}; commit=${2:?}
mode=${3:?}
[[ "$release" =~ ^poll-[0-9]+-[0-9]+$ && "$commit" =~ ^[0-9a-f]{40}$ ]]
[[ "$mode" == dry-run || "$mode" == apply ]]
root=/run/webroots/r1417157
state="$HOME/.radiorubben-deploy"
stage="$state/staging/$release"
wp=(/usr/local/bin/wp --path="$root" --skip-plugins --skip-themes)
site_home=$("${wp[@]}" option get home)
site_theme=$("${wp[@]}" option get stylesheet)
printf 'PREFLIGHT home=%q stylesheet=%q\n' "$site_home" "$site_theme"
[[ "$site_home" == https://www.radiorubben.no ]]
[[ "$site_theme" == radio-rubben-next ]]
[[ ! -f "$root/.maintenance" ]]
mkdir "$state/lock"
installed=false; maintenance=false
cleanup() {
  code=$?
  trap - EXIT
  if [[ $code -ne 0 && "$installed" == true ]]; then
    php "$stage/deploy-poll-files.php" rollback "$stage" || echo 'ERROR: inspect private backup before further writes' >&2
  fi
  if [[ "$maintenance" == true ]]; then "${wp[@]}" maintenance-mode deactivate || true; fi
  rmdir "$state/lock"
  exit "$code"
}
trap cleanup EXIT
php "$stage/deploy-poll-files.php" plan "$stage"
printf '%s\n' "$commit" > "$stage/commit.txt"
if [[ "$mode" == dry-run ]]; then echo 'DRY RUN: candidates linted; production unchanged'; exit 0; fi
"${wp[@]}" maintenance-mode activate
maintenance=true
installed=true
php "$stage/deploy-poll-files.php" apply "$stage"
"${wp[@]}" maintenance-mode deactivate
maintenance=false
# Verify public page code and anonymous vote eligibility; never submit a vote.
curl --fail --silent --show-error --max-time 60 'https://www.radiorubben.no/dagenskamp/?rr_deploy_probe=1' > "$stage/page.html"
grep -Fq '!busy&&!document.hidden' "$stage/page.html"
curl --fail --silent --show-error --max-time 60 'https://www.radiorubben.no/dagenskamp/?rr_poll_api=1' > "$stage/api.json"
php -r '$d=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); if (empty($d["ok"]) || !array_key_exists("eligible",$d) || $d["eligible"]!==false) exit(1);' "$stage/api.json"
curl --fail --silent --show-error --max-time 60 -o /dev/null 'https://www.radiorubben.no/'
echo 'VERIFIED: page, anonymous API, homepage; private backup retained'
