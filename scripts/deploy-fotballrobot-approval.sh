#!/usr/bin/env bash
# Selective manual approval UI release; never approves, rewrites or sends an article.
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
files=(includes/review-desk-style.php includes/review-desk.php includes/player-review-page.php includes/publication-gate.php includes/player-review.php includes/match-jobs.php)
old=(includes/player-review-page.php includes/publication-gate.php includes/player-review.php includes/match-jobs.php)
[[ ! -e "$root/.maintenance" && -d "$live" && ! -L "$live" && ! -L "$live/includes" ]]
[[ $("${wp[@]}" option get home) == https://www.radiorubben.no ]]
[[ $("${wp[@]}" plugin get radio-rubben-fotballrobot --field=version) == 0.9.5 ]]
[[ -z $(find "$candidate" -type l -print -quit) ]]
(cd "$candidate"; sha256sum --check --quiet "$stage/scripts/fotballrobot-approval-target.sha256")
for path in "${files[@]}"; do [[ ! -L "$live/$path" ]]; php -l "$candidate/$path" >/dev/null; done
mkdir "$state/lock" || { echo 'Existing deployment lock; stop'; exit 3; }
trap 'rmdir "$state/lock"' EXIT
if [[ -e "$live/includes/review-desk.php" || -e "$live/includes/review-desk-style.php" ]]; then
  (cd "$live"; sha256sum --check --quiet "$stage/scripts/fotballrobot-approval-target.sha256")
  /usr/local/bin/wp --path="$root" --skip-themes eval-file "$stage/scripts/fotballrobot-approval-check.php"
  echo 'Approval desk already active'
  exit 0
fi
(cd "$live"; sha256sum --check --quiet "$stage/scripts/fotballrobot-approval-baseline.sha256")
backup="$state/backups/fotballrobot-approval-$revision"
mkdir -p "$state/backups"
mkdir "$backup"
tar -czf "$backup/code.tar.gz" -C "$live" "${old[@]}"
tar -tzf "$backup/code.tar.gz" >/dev/null
owned_maintenance=false
rollback() {
  trap - ERR HUP INT TERM
  tar -xzf "$backup/code.tar.gz" -C "$live" || exit 10
  rm -f "$live/includes/review-desk.php" "$live/includes/review-desk-style.php"
  if [[ "$owned_maintenance" == true ]]; then "${wp[@]}" maintenance-mode deactivate || true; fi
  echo 'Approval desk code restored from backup; existing data untouched'
  exit 1
}
trap rollback ERR HUP INT TERM
owned_maintenance=true
"${wp[@]}" maintenance-mode activate
before=$(/usr/local/bin/wp --path="$root" --skip-themes eval-file "$stage/scripts/fotballrobot-approval-fingerprint.php")
[[ "$before" =~ ^[0-9a-f]{64}$ ]]
printf '%s\n' "$before" > "$backup/editorial-state-before.txt"
for path in "${files[@]}"; do
  temporary=$(mktemp "$live/${path}.XXXXXX")
  install -m 644 "$candidate/$path" "$temporary"
  mv "$temporary" "$live/$path"
done
(cd "$live"; sha256sum --check --quiet "$stage/scripts/fotballrobot-approval-target.sha256")
/usr/local/bin/wp --path="$root" --skip-themes eval-file "$stage/scripts/fotballrobot-approval-check.php"
after=$(/usr/local/bin/wp --path="$root" --skip-themes eval-file "$stage/scripts/fotballrobot-approval-fingerprint.php")
[[ "$after" == "$before" ]]
printf '%s\n' "$after" > "$backup/editorial-state-after.txt"
"${wp[@]}" maintenance-mode deactivate
owned_maintenance=false
curl --fail --silent --show-error --max-time 30 "https://www.radiorubben.no/wp-json/?rr_approval_release=$revision" -o /dev/null
printf '%s\n' "$revision" > "$state/fotballrobot-approval-current-revision"
trap - ERR HUP INT TERM
printf 'Manual approval desk activated from %s; articles and player state unchanged; six runtime files only\n' "$revision"
