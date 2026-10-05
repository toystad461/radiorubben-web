#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
revision=${1:?Tested revision required}
[[ "$revision" =~ ^[0-9a-f]{40}$ ]]
state="$HOME/.radiorubben-deploy"
stage="$state/player-followup/$revision"
root=/run/webroots/r1417157
plugins="$root/wp-content/plugins"
wp=(/usr/local/bin/wp --path="$root" --skip-themes --user=toystad)
[[ ! -e "$root/.maintenance" ]]
[[ $("${wp[@]}" option get home) == https://www.radiorubben.no ]]
[[ $("${wp[@]}" plugin get radio-rubben-fotballrobot --field=version) == 0.9.6 ]]
[[ $("${wp[@]}" plugin get radio-rubben-player-widget --field=version) == 1.2.0 ]]
[[ ! -e "$plugins/radio-rubben-player-widget/includes/live.php" ]]
[[ -z $(find "$stage/plugins" -type l -print -quit) ]]
(cd "$plugins"; sha256sum --check --quiet "$stage/scripts/player-followup-baseline.sha256")
(cd "$stage/plugins"; sha256sum --check --quiet "$stage/scripts/player-followup-target.sha256")
mapfile -t files < "$stage/scripts/player-followup-files.txt"
for path in "${files[@]}"; do [[ ! -L "$plugins/$path" ]]; php -l "$stage/plugins/$path"; done
mkdir "$state/lock" || { echo 'Another deployment is running'; exit 3; }
trap 'rmdir "$state/lock"' EXIT
backup="$state/backups/player-followup-$revision"
mkdir -p "$backup"
sed 's/^[a-f0-9]*  //' "$stage/scripts/player-followup-baseline.sha256" > "$backup/files.txt"
tar -czf "$backup/code.tar.gz" -C "$plugins" -T "$backup/files.txt"
tar -tzf "$backup/code.tar.gz" >/dev/null
owned_maintenance=false
rollback() {
  trap - ERR HUP INT TERM
  tar -xzf "$backup/code.tar.gz" -C "$plugins"
  rm -f "$plugins/radio-rubben-player-widget/includes/live.php"
  "${wp[@]}" cron event delete rrpw_refresh >/dev/null || true
  "${wp[@]}" cron event delete rrfr_profiles_tick >/dev/null || true
  if [[ "$owned_maintenance" == true ]]; then "${wp[@]}" maintenance-mode deactivate || true; fi
  echo 'Previous plugin files restored. Profile/source observations and any draft retained.'
  exit 1
}
trap rollback ERR HUP INT TERM
"${wp[@]}" maintenance-mode activate
owned_maintenance=true
for path in "${files[@]}"; do
  temporary=$(mktemp "$plugins/${path}.XXXXXX")
  install -m 644 "$stage/plugins/$path" "$temporary"
  mv "$temporary" "$plugins/$path"
done
(cd "$plugins"; sha256sum --check --quiet "$stage/scripts/player-followup-target.sha256")
"${wp[@]}" maintenance-mode deactivate
owned_maintenance=false
"${wp[@]}" eval-file "$stage/scripts/player-followup-check.php" | tee "$backup/check.log"
grep -q '^FOLLOWUP_RELEASE_OK$' "$backup/check.log"
curl --fail --silent --show-error --max-time 30 'https://www.radiorubben.no/wp-json/rr-player-widget/v1/widget' -o "$backup/widget.json"
grep -q 'data-player-id' "$backup/widget.json"
printf '%s\n' "$revision" > "$state/player-followup-current-revision"
trap - ERR HUP INT TERM
echo 'Match-driven widget 1.3.0 and player parser 0.9.7 published.'
