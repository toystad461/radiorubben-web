#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
release=${1:?}; commit=${2:?}; mode=${3:?}
[[ "$release" =~ ^simulation-[0-9]+-[0-9]+$ && "$commit" =~ ^[0-9a-f]{40}$ ]]
[[ "$mode" == dry-run || "$mode" == apply ]]
root=/run/webroots/r1417157
state="$HOME/.radiorubben-deploy"
stage="$state/staging/$release"
wp=(/usr/local/bin/wp --path="$root" --skip-plugins --skip-themes)
[[ "$("${wp[@]}" option get home)" == https://www.radiorubben.no ]]
[[ "$("${wp[@]}" option get stylesheet)" == radio-rubben-next ]]
[[ ! -f "$root/.maintenance" ]]
mkdir "$state/lock"
installed=false; maintenance=false
cleanup() {
  code=$?
  trap - EXIT
  if [[ $code -ne 0 && "$installed" == true ]]; then
    php "$stage/deploy-simulation-files.php" rollback "$stage" || echo 'ERROR: inspect private backup before further writes' >&2
  fi
  if [[ "$maintenance" == true ]]; then "${wp[@]}" maintenance-mode deactivate || true; fi
  rmdir "$state/lock"
  exit "$code"
}
trap cleanup EXIT
php "$stage/deploy-simulation-files.php" plan "$stage"
php "$stage/probe-simulation.php" "$stage" before | tee "$stage/before.log"
grep -q '^SIMULATION_VERIFIED: mode=before;' "$stage/before.log"
printf '%s\n' "$commit" > "$stage/commit.txt"
if [[ "$mode" == dry-run ]]; then echo 'SIMULATION_DRY_RUN_VERIFIED: production files unchanged'; exit 0; fi
"${wp[@]}" maintenance-mode activate
maintenance=true; installed=true
php "$stage/deploy-simulation-files.php" apply "$stage"
"${wp[@]}" maintenance-mode deactivate
maintenance=false
php "$stage/probe-simulation.php" "$stage" after | tee "$stage/after.log"
grep -q '^SIMULATION_VERIFIED: mode=after;' "$stage/after.log"
# Anonymous users must be redirected to login, never receive the private test page.
curl --silent --show-error --max-time 45 -D "$stage/headers.txt" -o "$stage/anonymous.html" 'https://www.radiorubben.no/avstemningstest/'
grep -Eq '^HTTP/[^ ]+ 302' "$stage/headers.txt"
grep -Eiq '^location: .*wp-login\.php' "$stage/headers.txt"
curl --fail --silent --show-error --max-time 45 -o /dev/null 'https://www.radiorubben.no/'
echo 'SIMULATION_DEPLOY_VERIFIED: module active; historical import and memory vote pass; real poll unchanged; anonymous route protected; homepage healthy'
