#!/usr/bin/env bash
# Activate only the tested writer file; no data writes or AI calls.
set -Eeuo pipefail
umask 077
revision=${1:?Tested candidate revision required}
[[ "$revision" =~ ^[0-9a-f]{40}$ ]]
state="$HOME/.radiorubben-deploy"
stage="$state/candidates/$revision"
candidate="$stage/writer.php"
root=/run/webroots/r1417157
live="$root/wp-content/plugins/radio-rubben-fotballrobot/includes/writer.php"
wp=(/usr/local/bin/wp --path="$root" --skip-plugins --skip-themes)
expected=fb4c751963d2dcf0056a6d111a016a2840bd7f8107841f88ab0538c5da65fd24
target=0b4d737c81eb610707734763691f417daab45add1febbc42e92fba5aff50b134
[[ ! -e "$root/.maintenance" && -f "$live" && ! -L "$live" && ! -L "$candidate" ]]
[[ ! -L "$(dirname "$live")" && ! -L "$(dirname "$(dirname "$live")")" ]]
[[ $("${wp[@]}" option get home) == https://www.radiorubben.no ]]
[[ $("${wp[@]}" plugin get radio-rubben-fotballrobot --field=version) == 0.9.5 ]]
[[ $(sha256sum "$candidate" | cut -d' ' -f1) == "$target" ]]
php -l "$candidate" >/dev/null
mkdir "$state/lock" || { echo 'Existing deployment lock; stop'; exit 3; }
trap 'rmdir "$state/lock"' EXIT
current=$(sha256sum "$live" | cut -d' ' -f1)
if [[ "$current" == "$target" ]]; then
  /usr/local/bin/wp --path="$root" --skip-themes eval-file "$stage/scripts/fotballrobot-prompts-check.php"
  echo 'Editorial prompt revision already active'
  exit 0
fi
[[ "$current" == "$expected" ]] || { echo 'Writer baseline changed; no files replaced'; exit 4; }
backup="$state/backups/fotballrobot-prompts-$revision"
mkdir -p "$state/backups"
mkdir "$backup"
install -m 600 "$live" "$backup/writer.php"
[[ $(sha256sum "$backup/writer.php" | cut -d' ' -f1) == "$expected" ]]
owned_maintenance=false
rollback() {
  trap - ERR HUP INT TERM
  restore=$(mktemp "${live}.rollback.XXXXXX") || exit 10
  install -m 644 "$backup/writer.php" "$restore" && mv "$restore" "$live" || exit 10
  if [[ "$owned_maintenance" == true ]]; then "${wp[@]}" maintenance-mode deactivate || true; fi
  echo 'Writer restored from backup; saved content untouched'
  exit 1
}
trap rollback ERR HUP INT TERM
owned_maintenance=true
"${wp[@]}" maintenance-mode activate
before=$(/usr/local/bin/wp --path="$root" --skip-themes eval-file "$stage/scripts/fotballrobot-monitor-fingerprint.php")
[[ "$before" =~ ^[0-9a-f]{64}$ ]]
printf '%s\n' "$before" > "$backup/player-state-before.txt"
temporary=$(mktemp "${live}.XXXXXX")
install -m 644 "$candidate" "$temporary"
mv "$temporary" "$live"
[[ $(sha256sum "$live" | cut -d' ' -f1) == "$target" ]]
/usr/local/bin/wp --path="$root" --skip-themes eval-file "$stage/scripts/fotballrobot-prompts-check.php"
after=$(/usr/local/bin/wp --path="$root" --skip-themes eval-file "$stage/scripts/fotballrobot-monitor-fingerprint.php")
[[ "$after" == "$before" ]]
printf '%s\n' "$after" > "$backup/player-state-after.txt"
"${wp[@]}" maintenance-mode deactivate
owned_maintenance=false
curl --fail --silent --show-error --max-time 30 "https://www.radiorubben.no/wp-json/?rr_prompt_release=$revision" -o /dev/null
printf '%s\n' "$revision" > "$state/fotballrobot-prompts-current-revision"
trap - ERR HUP INT TERM
printf 'Editorial prompts 2026-10-03.1 activated from %s; player state unchanged; only writer.php replaced\n' "$revision"
