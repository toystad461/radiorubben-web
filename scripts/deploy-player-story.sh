#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
revision=${1:?Tested revision required}
[[ "$revision" =~ ^[0-9a-f]{40}$ ]]
release_state="$HOME/.radiorubben-deploy"
stage="$release_state/player-story/$revision"
backup="$release_state/backups/player-story-$revision"
root=/run/webroots/r1417157
wp=(/usr/local/bin/wp --path="$root" --skip-themes --user=toystad)
[[ ! -e "$root/.maintenance" ]]
[[ $("${wp[@]}" option get home) == https://www.radiorubben.no ]]
[[ $("${wp[@]}" plugin get radio-rubben-fotballrobot --field=version) == 0.10.2 ]]
mkdir "$release_state/lock" || exit 3
trap 'rmdir "$release_state/lock"' EXIT
mkdir "$backup"
installed=false
owned_maintenance=false
rollback(){
 trap - ERR HUP INT TERM
 if [[ "$installed" == true ]]; then php "$stage/scripts/player-story-install.php" "$stage" "$backup" rollback; fi
 if [[ "$owned_maintenance" == true ]]; then "${wp[@]}" maintenance-mode deactivate || true; fi
 echo 'Release stopped; existing content has not been rewritten.'
 exit 1
}
trap rollback ERR HUP INT TERM
"${wp[@]}" maintenance-mode activate
owned_maintenance=true
php "$stage/scripts/player-story-install.php" "$stage" "$backup" install
installed=true
"${wp[@]}" eval-file "$stage/scripts/player-story-postflight.php" | tee "$backup/postflight.log"
grep -q '^PLAYER_STORY_RUNTIME_OK$' "$backup/postflight.log"
"${wp[@]}" maintenance-mode deactivate
owned_maintenance=false
trap - ERR HUP INT TERM
echo 'Player story fixes installed. No article text published and no email sent.'
