#!/usr/bin/env bash
# Selectively replace this plugin's compact-view files. No theme/settings/content writes.
set -Eeuo pipefail
umask 077
revision=${1:?Tested revision required}
[[ "$revision" =~ ^[0-9a-f]{40}$ ]]
state="$HOME/.radiorubben-deploy"
stage="$state/candidates/$revision"
root=/run/webroots/r1417157
live="$root/wp-content/plugins/radio-rubben-player-widget"
wp=(/usr/local/bin/wp --path="$root" --skip-themes --user=toystad)
[[ ! -e "$root/.maintenance" && -d "$live" && ! -L "$live" && ! -e "$live/includes/compact.php" ]]
[[ $("${wp[@]}" option get home) == https://www.radiorubben.no ]]
[[ $("${wp[@]}" plugin get radio-rubben-player-widget --field=version) == 1.0.1 ]]
[[ -z $(find "$stage/plugin" -type l -print -quit) ]]
(cd "$stage/plugin"; sha256sum --check --quiet "$stage/scripts/player-widget-compact-target.sha256")
(cd "$live"; sha256sum --check --quiet "$stage/scripts/player-widget-compact-baseline.sha256")
files=(assets/widget.css assets/widget.js includes/admin.php includes/service.php includes/sources.php includes/view.php radio-rubben-player-widget.php)
for path in "${files[@]}" includes/compact.php; do [[ ! -L "$live/$path" ]]; done
find "$stage/plugin" -name '*.php' -print0 | xargs -0 -n1 php -l
mkdir "$state/lock" || { echo 'Another deployment is running'; exit 3; }
trap 'rmdir "$state/lock"' EXIT
backup="$state/backups/player-widget-compact-$revision"
mkdir -p "$backup"
tar -czf "$backup/code.tar.gz" -C "$live" "${files[@]}"
tar -tzf "$backup/code.tar.gz" >/dev/null
owned_maintenance=false
rollback() {
  trap - ERR HUP INT TERM
  tar -xzf "$backup/code.tar.gz" -C "$live"
  rm -f "$live/includes/compact.php"
  if [[ "$owned_maintenance" == true ]]; then "${wp[@]}" maintenance-mode deactivate || true; fi
  echo 'Previous widget restored from backup; settings unchanged.'
  exit 1
}
trap rollback ERR HUP INT TERM
"${wp[@]}" maintenance-mode activate
owned_maintenance=true
for path in includes/compact.php "${files[@]}"; do
  temporary=$(mktemp "$live/${path}.XXXXXX")
  install -m 644 "$stage/plugin/$path" "$temporary"
  mv "$temporary" "$live/$path"
done
(cd "$live"; sha256sum --check --quiet "$stage/scripts/player-widget-compact-target.sha256")
"${wp[@]}" eval-file "$stage/scripts/player-widget-compact-check.php"
"${wp[@]}" maintenance-mode deactivate
owned_maintenance=false
"${wp[@]}" eval "do_action('litespeed_purge_url', home_url('/')); do_action('litespeed_purge_url', home_url('/sport/'));"
curl --fail --silent --show-error --max-time 30 "https://www.radiorubben.no/?rr_compact_release=$revision" -o "$backup/home.html"
grep -q 'rrpw-compact' "$backup/home.html"
printf '%s\n' "$revision" > "$state/player-widget-current-revision"
trap - ERR HUP INT TERM
echo 'Compact player widget 1.1.0 published; selected players and theme unchanged.'
