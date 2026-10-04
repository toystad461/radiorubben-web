#!/usr/bin/env bash
# Selective candidate/media release; editorial data is fingerprinted and never mutated.
set -Eeuo pipefail
umask 077
revision=${1:?Tested revision required}
[[ "$revision" =~ ^[0-9a-f]{40}$ ]]
state="$HOME/.radiorubben-deploy"
stage="$state/candidates/$revision"
candidate="$stage/plugin"
root=/run/webroots/r1417157
live="$root/wp-content/plugins/radio-rubben-fotballrobot"
wp=(/usr/local/bin/wp --path="$root" --skip-plugins --skip-themes)
old=(radio-rubben-fotballrobot.php includes/player-monitor.php includes/players-page.php includes/player-review.php includes/inline-sources.php includes/writer.php)
new=(includes/player-candidates.php includes/player-candidates-page.php includes/media-sources.php)
files=("${old[@]}" "${new[@]}")
[[ ! -e "$root/.maintenance" && -d "$live" && ! -L "$live" && ! -L "$live/includes" ]]
[[ $("${wp[@]}" option get home) == https://www.radiorubben.no ]]
[[ -z $(find "$candidate" -type l -print -quit) ]]
(cd "$candidate"; sha256sum --check --quiet "$stage/scripts/fotballrobot-discovery-target.sha256")
for path in "${files[@]}"; do [[ ! -L "$live/$path" ]]; php -l "$candidate/$path" >/dev/null; done
mkdir "$state/lock" || { echo 'Existing deployment lock; stop'; exit 3; }
trap 'rmdir "$state/lock"' EXIT
version=$("${wp[@]}" plugin get radio-rubben-fotballrobot --field=version)
if [[ "$version" == 0.9.6 ]]; then
  (cd "$live"; sha256sum --check --quiet "$stage/scripts/fotballrobot-discovery-target.sha256")
  /usr/local/bin/wp --path="$root" --skip-themes eval-file "$stage/scripts/fotballrobot-discovery-check.php"
  echo 'Candidate and media support already active'
  exit 0
fi
[[ "$version" == 0.9.5 ]]
for path in "${new[@]}"; do [[ ! -e "$live/$path" ]]; done
(cd "$live"; sha256sum --check --quiet "$stage/scripts/fotballrobot-discovery-baseline.sha256")
backup="$state/backups/fotballrobot-discovery-$revision"
mkdir -p "$state/backups"
mkdir "$backup"
tar -czf "$backup/code.tar.gz" -C "$live" "${old[@]}"
tar -tzf "$backup/code.tar.gz" >/dev/null
owned_maintenance=false
rollback() {
  trap - ERR HUP INT TERM
  tar -xzf "$backup/code.tar.gz" -C "$live" || exit 10
  for path in "${new[@]}"; do rm -f "$live/$path"; done
  if [[ "$owned_maintenance" == true ]]; then "${wp[@]}" maintenance-mode deactivate || true; fi
  echo 'Previous code restored from backup; editorial data untouched'
  exit 1
}
trap rollback ERR HUP INT TERM
owned_maintenance=true
"${wp[@]}" maintenance-mode activate
before=$(/usr/local/bin/wp --path="$root" --skip-themes eval-file "$stage/scripts/fotballrobot-discovery-fingerprint.php")
[[ "$before" =~ ^[0-9a-f]{64}$ ]]
printf '%s\n' "$before" > "$backup/editorial-state-before.txt"
for path in "${files[@]}"; do
  temporary=$(mktemp "$live/${path}.XXXXXX")
  install -m 644 "$candidate/$path" "$temporary"
  mv "$temporary" "$live/$path"
done
(cd "$live"; sha256sum --check --quiet "$stage/scripts/fotballrobot-discovery-target.sha256")
/usr/local/bin/wp --path="$root" --skip-themes eval-file "$stage/scripts/fotballrobot-discovery-check.php"
after=$(/usr/local/bin/wp --path="$root" --skip-themes eval-file "$stage/scripts/fotballrobot-discovery-fingerprint.php")
[[ "$after" == "$before" ]]
printf '%s\n' "$after" > "$backup/editorial-state-after.txt"
"${wp[@]}" maintenance-mode deactivate
owned_maintenance=false
curl --fail --silent --show-error --max-time 30 "https://www.radiorubben.no/wp-json/?rr_discovery_release=$revision" -o /dev/null
printf '%s\n' "$revision" > "$state/fotballrobot-discovery-current-revision"
trap - ERR HUP INT TERM
printf 'Candidate and media support activated from %s; profiles and articles unchanged; nine runtime files only\n' "$revision"
