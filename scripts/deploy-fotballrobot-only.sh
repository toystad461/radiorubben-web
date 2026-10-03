#!/usr/bin/env bash
# Run remotely with native WP-CLI; update only the tested Fotballrobot directory.
set -Eeuo pipefail
umask 077
revision=${1:?Candidate revision required}
[[ "$revision" =~ ^[0-9a-f]{40}$ ]] || exit 2
state="$HOME/.radiorubben-deploy"
stage="$state/candidates/$revision"
candidate="$stage/wordpress/wp-content/plugins/radio-rubben-fotballrobot"
root=/run/webroots/r1417157
live="$root/wp-content/plugins/radio-rubben-fotballrobot"
wp=(/usr/local/bin/wp --path="$root" --skip-plugins --skip-themes)
[[ ! -e "$root/.maintenance" && -d "$live" && ! -L "$live" ]]
[[ -z $(find "$candidate" -type l -print -quit) ]]
[[ $("${wp[@]}" option get home) == https://www.radiorubben.no ]]
[[ $("${wp[@]}" plugin get radio-rubben-fotballrobot --field=version) == 0.9.0 ]]
[[ $("${wp[@]}" post get 1071 --field=post_status) == draft ]]
mkdir "$state/lock" || { echo 'Existing deployment lock; stop'; exit 3; }
trap 'rmdir "$state/lock"' EXIT
(cd "$live"; sha256sum --check --quiet "$stage/scripts/fotballrobot-baseline.sha256")
new=(includes/editorial-quality.php includes/publication-gate.php EDITORIAL-RULES.md)
for path in "${new[@]}"; do [[ ! -e "$live/$path" ]]; done
find "$candidate" -name '*.php' -print0 | xargs -0 -n1 php -l > /dev/null
backup="$state/backups/fotballrobot-$revision"
mkdir -p "$state/backups"
mkdir "$backup"
tar -czf "$backup/code.tar.gz" -C "$live" .
tar -tzf "$backup/code.tar.gz" > /dev/null
rollback() {
  trap - ERR HUP INT TERM
  tar -xzf "$backup/code.tar.gz" -C "$live" || exit 10
  for path in "${new[@]}"; do rm -f "$live/$path"; done
  "${wp[@]}" maintenance-mode deactivate || true
  echo 'Fotballrobot rollback completed'
  exit 1
}
trap rollback ERR HUP INT TERM
"${wp[@]}" maintenance-mode activate
rsync -rlt --chmod=D755,F644 --exclude=/tests/ "$candidate/" "$live/"
[[ $("${wp[@]}" plugin get radio-rubben-fotballrobot --field=version) == 0.9.1 ]]
/usr/local/bin/wp --path="$root" eval 'if (!class_exists("RadioRubben\\Fotballrobot\\EditorialQuality") || RadioRubben\Fotballrobot\EditorialQuality::RULES_VERSION !== "1.0.0" || RadioRubben\Fotballrobot\MatchJobs::DELAY !== 3600 || !RadioRubben\Fotballrobot\PublicationGate::current(1071,get_post(1071)) || RadioRubben\Fotballrobot\PublicationGate::canPublish(1071,get_post(1071))) { throw new RuntimeException("Release verification failed"); } echo "Runtime, quality audit and test publication block verified.\n";'
"${wp[@]}" maintenance-mode deactivate
curl --fail --silent --show-error --max-time 30 "https://www.radiorubben.no/wp-json/?rr_release=$revision" -o /dev/null
trap - ERR HUP INT TERM
printf '%s\n' "$revision" > "$state/fotballrobot-current-revision"
printf 'Fotballrobot 0.9.1 deployed from %s; backup retained at %s\n' "$revision" "$backup/code.tar.gz"
