#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
revision=${1:?Tested revision required}
[[ "$revision" =~ ^[0-9a-f]{40}$ ]]
release_state="$HOME/.radiorubben-deploy"
stage="$release_state/pr30/$revision"
backup="$release_state/backups/pr30-$revision"
root=/run/webroots/r1417157
wp=(/usr/local/bin/wp --path="$root" --skip-themes --user=toystad)
[[ ! -e "$root/.maintenance" ]]
[[ $("${wp[@]}" option get home) == https://www.radiorubben.no ]]
[[ $("${wp[@]}" plugin get radio-rubben-fotballrobot --field=version) == 0.10.4 ]]
mkdir "$release_state/lock" || exit 3
trap 'rmdir "$release_state/lock"' EXIT
mkdir "$backup"
installed=false
owned_maintenance=false
rollback(){
  trap - ERR HUP INT TERM
  if [[ "$installed" == true ]]; then
    "${wp[@]}" eval-file "$stage/scripts/pr30-postflight.php" rollback "$backup/state-before.json" "$stage"
    php "$stage/scripts/pr30-install.php" "$stage" "$backup" rollback
  fi
  if [[ "$owned_maintenance" == true ]]; then "${wp[@]}" maintenance-mode deactivate; fi
  echo 'PR30 release stopped; inspect the preceding error.'
  exit 1
}
trap rollback ERR HUP INT TERM
php "$stage/scripts/pr30-install.php" "$stage" "$backup" check
"${wp[@]}" eval-file "$stage/scripts/pr30-postflight.php" before "$backup/state-before.json" "$stage"
"${wp[@]}" maintenance-mode activate
owned_maintenance=true
php "$stage/scripts/pr30-install.php" "$stage" "$backup" install
installed=true
"${wp[@]}" eval-file "$stage/scripts/pr30-postflight.php" after "$backup/state-before.json" "$stage" | tee "$backup/postflight.log"
grep -q '^PR30_RUNTIME_OK$' "$backup/postflight.log"
php "$stage/scripts/pr30-install.php" "$stage" "$backup" after
"${wp[@]}" maintenance-mode deactivate
owned_maintenance=false
trap - ERR HUP INT TERM
echo 'PR30 installed and scheduled. Existing articles preserved; manual editorial approval remains required.'
