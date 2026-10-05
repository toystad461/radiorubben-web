#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
revision=${1:?Tested revision required}
[[ "$revision" =~ ^[0-9a-f]{40}$ ]]
release_state="$HOME/.radiorubben-deploy"
stage="$release_state/mobile-newsroom/$revision"
backup="$release_state/backups/mobile-newsroom-$revision"
root=/run/webroots/r1417157
wp=(/usr/local/bin/wp --path="$root" --skip-themes --user=toystad)
[[ ! -e "$root/.maintenance" ]]
[[ $("${wp[@]}" option get home) == https://www.radiorubben.no ]]
[[ $("${wp[@]}" plugin get radio-rubben-fotballrobot --field=version) == 0.10.3 ]]
mkdir "$release_state/lock" || exit 3
trap 'rmdir "$release_state/lock"' EXIT
mkdir "$backup"
installed=false
owned_maintenance=false
rollback(){
 trap - ERR HUP INT TERM
 if [[ "$installed" == true ]]; then php "$stage/scripts/mobile-newsroom-install.php" "$stage" "$backup" rollback; fi
 if [[ "$owned_maintenance" == true ]]; then "${wp[@]}" maintenance-mode deactivate || true; fi
 echo 'Release stopped; existing content has not been rewritten.'
 exit 1
}
trap rollback ERR HUP INT TERM
"${wp[@]}" eval-file "$stage/scripts/mobile-newsroom-postflight.php" before > "$backup/content-before.json"
"${wp[@]}" maintenance-mode activate
owned_maintenance=true
php "$stage/scripts/mobile-newsroom-install.php" "$stage" "$backup" install
installed=true
"${wp[@]}" eval-file "$stage/scripts/mobile-newsroom-postflight.php" after "$backup/content-before.json" | tee "$backup/postflight.log"
grep -q '^MOBILE_NEWSROOM_RUNTIME_OK$' "$backup/postflight.log"
"${wp[@]}" maintenance-mode deactivate
owned_maintenance=false
trap - ERR HUP INT TERM
echo 'Mobile newsroom installed. No article text published and no email sent.'
