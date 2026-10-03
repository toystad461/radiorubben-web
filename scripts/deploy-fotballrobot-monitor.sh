#!/usr/bin/env bash
# Selective player-monitor integration, from the verified 0.9.4 baseline only.
set -Eeuo pipefail
umask 077
revision=${1:?Tested candidate revision required}
[[ "$revision" =~ ^[0-9a-f]{40}$ ]]
state="$HOME/.radiorubben-deploy"
stage="$state/candidates/$revision"
candidate="$stage/wordpress/wp-content/plugins/radio-rubben-fotballrobot"
root=/run/webroots/r1417157
live="$root/wp-content/plugins/radio-rubben-fotballrobot"
wp=(/usr/local/bin/wp --path="$root" --skip-plugins --skip-themes)
[[ ! -e "$root/.maintenance" && -d "$live" && ! -L "$live" ]]
[[ $("${wp[@]}" option get home) == https://www.radiorubben.no ]]
[[ $("${wp[@]}" plugin get radio-rubben-fotballrobot --field=version) == 0.9.4 ]]
[[ -z $(find "$candidate" -type l -print -quit) ]]
[[ ! -e "$live/includes/player-monitor.php" ]]
mkdir "$state/lock" || { echo 'Existing deployment lock; stop'; exit 3; }
trap 'rmdir "$state/lock"' EXIT
(cd "$live"; sha256sum --check --quiet "$stage/scripts/fotballrobot-monitor-baseline.sha256")
(cd "$candidate"; sha256sum --check --quiet "$stage/monitor-candidate.sha256")
files=(includes/player-monitor.php includes/player-review.php includes/players-page.php includes/writer.php includes/robot.php radio-rubben-fotballrobot.php)
for path in "${files[@]}"; do php -l "$candidate/$path" >/dev/null; done
php -l "$stage/scripts/fotballrobot-monitor-check.php" >/dev/null
backup="$state/backups/fotballrobot-monitor-$revision"
mkdir -p "$state/backups"
mkdir "$backup"
tar -czf "$backup/code.tar.gz" -C "$live" .
tar -tzf "$backup/code.tar.gz" >/dev/null
before=$(/usr/local/bin/wp --path="$root" --skip-themes eval-file "$stage/scripts/fotballrobot-monitor-fingerprint.php")
[[ "$before" =~ ^[0-9a-f]{64}$ ]]
printf '%s\n' "$before" > "$backup/player-state-before.txt"
rollback() {
  trap - ERR HUP INT TERM
  tar -xzf "$backup/code.tar.gz" -C "$live" || exit 10
  rm -f "$live/includes/player-monitor.php"
  "${wp[@]}" maintenance-mode deactivate || true
  echo 'Player monitor code rolled back; saved player state untouched'
  exit 1
}
trap rollback ERR HUP INT TERM
"${wp[@]}" maintenance-mode activate
for path in "${files[@]}"; do
  tmp_file=$(mktemp "$live/${path}.XXXXXX")
  install -m 644 "$candidate/$path" "$tmp_file"
  mv "$tmp_file" "$live/$path"
done
[[ $("${wp[@]}" plugin get radio-rubben-fotballrobot --field=version) == 0.9.5 ]]
(cd "$live"; sha256sum --check --quiet "$stage/monitor-candidate.sha256")
/usr/local/bin/wp --path="$root" --skip-themes eval-file "$stage/scripts/fotballrobot-monitor-check.php"
after=$(/usr/local/bin/wp --path="$root" --skip-themes eval-file "$stage/scripts/fotballrobot-monitor-fingerprint.php")
[[ "$after" == "$before" ]]
"${wp[@]}" maintenance-mode deactivate
curl --fail --silent --show-error --max-time 30 "https://www.radiorubben.no/wp-json/?rr_monitor_release=$revision" -o /dev/null
trap - ERR HUP INT TERM
printf '%s\n' "$revision" > "$state/fotballrobot-current-revision"
printf 'Fotballrobot 0.9.5 deployed from %s; backup %s; player data preserved\n' "$revision" "$backup/code.tar.gz"
