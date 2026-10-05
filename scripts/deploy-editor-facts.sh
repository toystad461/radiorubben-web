#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
revision=${1:?Tested revision required}
[[ "$revision" =~ ^[0-9a-f]{40}$ ]]
state="$HOME/.radiorubben-deploy"
stage="$state/editor-facts/$revision"
root=/run/webroots/r1417157
live="$root/wp-content/plugins/radio-rubben-fotballrobot"
wp=(/usr/local/bin/wp --path="$root" --skip-themes --user=toystad)
[[ ! -e "$root/.maintenance" && ! -L "$live" ]]
[[ $("${wp[@]}" option get home) == https://www.radiorubben.no ]]
[[ $("${wp[@]}" plugin get radio-rubben-fotballrobot --field=version) == 0.9.7 ]]
[[ -z $(find "$stage/plugin" -type l -print -quit) ]]
(cd "$live"; sha256sum --check --quiet "$stage/scripts/editor-facts-baseline.sha256")
(cd "$stage/plugin"; sha256sum --check --quiet "$stage/scripts/editor-facts-target.sha256")
files=(includes/player-review.php includes/review-desk.php includes/writer.php radio-rubben-fotballrobot.php)
for path in "${files[@]}"; do [[ ! -L "$live/$path" ]]; php -l "$stage/plugin/$path"; done
mkdir "$state/lock" || { echo 'Another deployment is running'; exit 3; }
trap 'rmdir "$state/lock"' EXIT
backup="$state/backups/editor-facts-$revision"
mkdir -p "$backup"
tar -czf "$backup/code.tar.gz" -C "$live" "${files[@]}"
tar -tzf "$backup/code.tar.gz" >/dev/null
owned_maintenance=false
rollback(){
 trap - ERR HUP INT TERM
 tar -xzf "$backup/code.tar.gz" -C "$live"
 if [[ "$owned_maintenance" == true ]]; then "${wp[@]}" maintenance-mode deactivate || true; fi
 echo 'Previous review code restored; article data untouched.'
 exit 1
}
trap rollback ERR HUP INT TERM
"${wp[@]}" maintenance-mode activate
owned_maintenance=true
for path in "${files[@]}"; do
 temporary=$(mktemp "$live/${path}.XXXXXX")
 install -m 644 "$stage/plugin/$path" "$temporary"
 mv "$temporary" "$live/$path"
done
(cd "$live"; sha256sum --check --quiet "$stage/scripts/editor-facts-target.sha256")
"${wp[@]}" maintenance-mode deactivate
owned_maintenance=false
"${wp[@]}" eval-file "$stage/scripts/editor-facts-check.php" | tee "$backup/check.log"
grep -q '^EDITOR_FACTS_RELEASE_OK$' "$backup/check.log"
trap - ERR HUP INT TERM
echo 'Fotballrobot 0.9.8 published: explicit editor facts with unchanged publication gate.'
