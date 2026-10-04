#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
revision=${1:?Tested revision required}
[[ "$revision" =~ ^[0-9a-f]{40}$ ]]
release_state="$HOME/.radiorubben-deploy"
stage="$release_state/newsroom/$revision"
backup="$release_state/backups/newsroom-$revision"
root=/run/webroots/r1417157
wp=(/usr/local/bin/wp --path="$root" --skip-themes --user=toystad)
[[ ! -e "$root/.maintenance" ]]
[[ $("${wp[@]}" option get home) == https://www.radiorubben.no ]]
[[ $("${wp[@]}" plugin get radio-rubben-fotballrobot --field=version) == 0.10.0 ]]
mkdir "$release_state/lock" || { echo 'Another release is running'; exit 3; }
trap 'rmdir "$release_state/lock"' EXIT
mkdir "$backup"
installed=false
owned_maintenance=false
rollback(){
 trap - ERR HUP INT TERM
 if [[ "$installed" == true ]]; then php "$stage/scripts/newsroom-install.php" "$stage" "$backup" rollback; fi
 if [[ "$owned_maintenance" == true ]]; then "${wp[@]}" maintenance-mode deactivate || true; fi
 echo 'Newsroom activation stopped; prior code restored where installed.'
 exit 1
}
trap rollback ERR HUP INT TERM
"${wp[@]}" maintenance-mode activate
owned_maintenance=true
php "$stage/scripts/newsroom-install.php" "$stage" "$backup" install
installed=true
"${wp[@]}" eval-file "$stage/scripts/newsroom-postflight.php" | tee "$backup/postflight.log"
grep -q '^NEWSROOM_RUNTIME_OK$' "$backup/postflight.log"
"${wp[@]}" maintenance-mode deactivate
owned_maintenance=false
trap - ERR HUP INT TERM
echo 'Scoped Studio and Fotballrobot update installed; connection and scheduler activation follow separately.'
