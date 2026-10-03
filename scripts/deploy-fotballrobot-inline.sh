#!/usr/bin/env bash
# Verified inline citations only: three existing files and one new helper.
set -Eeuo pipefail
umask 077
revision=${1:?Tested candidate revision required}
[[ "$revision" =~ ^[0-9a-f]{40}$ ]]
state="$HOME/.radiorubben-deploy"
stage="$state/candidates/$revision"
candidate="$stage/plugin"
root=/run/webroots/r1417157
live="$root/wp-content/plugins/radio-rubben-fotballrobot"
wp=(/usr/local/bin/wp --path="$root" --skip-plugins --skip-themes)
files=(includes/inline-sources.php includes/editorial-quality.php includes/writer.php includes/player-review.php)
old=(includes/editorial-quality.php includes/writer.php includes/player-review.php)
[[ ! -e "$root/.maintenance" && -d "$live" && ! -L "$live" && ! -L "$live/includes" ]]
[[ $("${wp[@]}" option get home) == https://www.radiorubben.no ]]
[[ $("${wp[@]}" plugin get radio-rubben-fotballrobot --field=version) == 0.9.5 ]]
[[ -z $(find "$candidate" -type l -print -quit) ]]
(cd "$candidate"; sha256sum --check --quiet "$stage/scripts/fotballrobot-inline-target.sha256")
for path in "${files[@]}"; do [[ ! -L "$live/$path" ]]; php -l "$candidate/$path" >/dev/null; done
mkdir "$state/lock" || { echo 'Existing deployment lock; stop'; exit 3; }
trap 'rmdir "$state/lock"' EXIT
if [[ -f "$live/includes/inline-sources.php" ]]; then
  (cd "$live"; sha256sum --check --quiet "$stage/scripts/fotballrobot-inline-target.sha256")
  /usr/local/bin/wp --path="$root" --skip-themes eval-file "$stage/scripts/fotballrobot-inline-check.php"
  echo 'Inline sources already active'
  exit 0
fi
(cd "$live"; sha256sum --check --quiet "$stage/scripts/fotballrobot-inline-baseline.sha256")
backup="$state/backups/fotballrobot-inline-$revision"
mkdir -p "$state/backups"
mkdir "$backup"
tar -czf "$backup/code.tar.gz" -C "$live" "${old[@]}"
tar -tzf "$backup/code.tar.gz" >/dev/null
owned_maintenance=false
rollback() {
  trap - ERR HUP INT TERM
  tar -xzf "$backup/code.tar.gz" -C "$live" || exit 10
  rm -f "$live/includes/inline-sources.php"
  if [[ "$owned_maintenance" == true ]]; then "${wp[@]}" maintenance-mode deactivate || true; fi
  echo 'Inline source code restored from backup; existing data untouched'
  exit 1
}
trap rollback ERR HUP INT TERM
owned_maintenance=true
"${wp[@]}" maintenance-mode activate
before=$(/usr/local/bin/wp --path="$root" --skip-themes eval-file "$stage/scripts/fotballrobot-monitor-fingerprint.php")
[[ "$before" =~ ^[0-9a-f]{64}$ ]]
printf '%s\n' "$before" > "$backup/player-state-before.txt"
for path in "${files[@]}"; do
  temporary=$(mktemp "$live/${path}.XXXXXX")
  install -m 644 "$candidate/$path" "$temporary"
  mv "$temporary" "$live/$path"
done
(cd "$live"; sha256sum --check --quiet "$stage/scripts/fotballrobot-inline-target.sha256")
/usr/local/bin/wp --path="$root" --skip-themes eval-file "$stage/scripts/fotballrobot-inline-check.php"
after=$(/usr/local/bin/wp --path="$root" --skip-themes eval-file "$stage/scripts/fotballrobot-monitor-fingerprint.php")
[[ "$after" == "$before" ]]
printf '%s\n' "$after" > "$backup/player-state-after.txt"
"${wp[@]}" maintenance-mode deactivate
owned_maintenance=false
curl --fail --silent --show-error --max-time 30 "https://www.radiorubben.no/wp-json/?rr_inline_release=$revision" -o /dev/null
printf '%s\n' "$revision" > "$state/fotballrobot-inline-current-revision"
trap - ERR HUP INT TERM
printf 'Inline source links activated from %s; player state unchanged; four runtime files only\n' "$revision"
