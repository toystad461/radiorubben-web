#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
revision=${1:?Tested revision required}
[[ "$revision" =~ ^[0-9a-f]{40}$ ]]
state="$HOME/.radiorubben-deploy"
stage="$state/player-news-filter/$revision"
root=/run/webroots/r1417157
live="$root/wp-content/plugins/radio-rubben-fotballrobot"
wp=(/usr/local/bin/wp --path="$root" --skip-themes --user=toystad)
[[ ! -e "$root/.maintenance" && ! -L "$live" && ! -L "$live/includes" ]]
[[ $("${wp[@]}" option get home) == https://www.radiorubben.no ]]
[[ $("${wp[@]}" plugin get radio-rubben-fotballrobot --field=version) == 0.9.8 ]]
[[ -z $(find "$stage/plugin" -type l -print -quit) ]]
existing=(includes/player-review.php includes/players.php includes/player-monitor.php includes/writer.php radio-rubben-fotballrobot.php)
added=includes/player-news-filter.php
[[ ! -e "$live/$added" && ! -L "$live/$added" ]]
files=("$added" "${existing[@]}")
for path in "${files[@]}"; do [[ ! -L "$live/$path" ]]; php -l "$stage/plugin/$path"; done
(cd "$stage/plugin"; sha256sum --check --quiet "$stage/scripts/player-news-filter-target.sha256")
mkdir "$state/lock" || { echo 'Another deployment is running'; exit 3; }
trap 'rmdir "$state/lock"' EXIT
(cd "$live"; sha256sum --check --quiet "$stage/scripts/player-news-filter-baseline.sha256")
backup="$state/backups/player-news-filter-$revision"
mkdir -p "$backup"
tar -czf "$backup/code.tar.gz" -C "$live" "${existing[@]}"
tar -tzf "$backup/code.tar.gz" >/dev/null
owned_maintenance=false
installed_helper=false
rollback(){
 trap - ERR HUP INT TERM
 tar -xzf "$backup/code.tar.gz" -C "$live"
 if [[ "$installed_helper" == true ]]; then rm -f "$live/$added"; fi
 if [[ "$owned_maintenance" == true ]]; then "${wp[@]}" maintenance-mode deactivate || true; fi
 echo 'Previous player code restored; existing articles and options were not edited.'
 exit 1
}
trap rollback ERR HUP INT TERM
"${wp[@]}" maintenance-mode activate
owned_maintenance=true
"${wp[@]}" eval-file "$stage/scripts/player-news-filter-check.php" fingerprint > "$backup/state-before.sha256"
grep -Eq '^[0-9a-f]{64}$' "$backup/state-before.sha256"
for path in "${files[@]}"; do
 temporary=$(mktemp "$live/${path}.XXXXXX")
 install -m 644 "$stage/plugin/$path" "$temporary"
 mv "$temporary" "$live/$path"
 if [[ "$path" == "$added" ]]; then installed_helper=true; fi
done
(cd "$live"; sha256sum --check --quiet "$stage/scripts/player-news-filter-target.sha256")
"${wp[@]}" eval-file "$stage/scripts/player-news-filter-check.php" check | tee "$backup/check.log"
grep -q '^PLAYER_NEWS_FILTER_RELEASE_OK$' "$backup/check.log"
"${wp[@]}" eval-file "$stage/scripts/player-news-filter-check.php" fingerprint > "$backup/state-after.sha256"
cmp "$backup/state-before.sha256" "$backup/state-after.sha256"
"${wp[@]}" maintenance-mode deactivate
owned_maintenance=false
trap - ERR HUP INT TERM
echo 'Fotballrobot 0.9.9 published: news selection before writing, no existing state changed.'
