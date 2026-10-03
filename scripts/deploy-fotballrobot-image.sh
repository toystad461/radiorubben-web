#!/usr/bin/env bash
# Selective image-default patch, from the verified 0.9.3 baseline only.
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
[[ $("${wp[@]}" plugin get radio-rubben-fotballrobot --field=version) == 0.9.3 ]]
[[ -z $(find "$candidate" -type l -print -quit) ]]
mkdir "$state/lock" || { echo 'Existing deployment lock; stop'; exit 3; }
trap 'rmdir "$state/lock"' EXIT
(cd "$live"; sha256sum --check --quiet "$stage/scripts/fotballrobot-image-baseline.sha256")
(cd "$candidate"; sha256sum --check --quiet "$stage/image-candidate.sha256")
files=(includes/writer.php includes/robot.php radio-rubben-fotballrobot.php)
for path in "${files[@]}"; do php -l "$candidate/$path" >/dev/null; done
php -l "$stage/scripts/fotballrobot-image-check.php" >/dev/null
backup="$state/backups/fotballrobot-image-$revision"
mkdir -p "$state/backups"
mkdir "$backup"
tar -czf "$backup/code.tar.gz" -C "$live" .
tar -tzf "$backup/code.tar.gz" >/dev/null
before=$(/usr/local/bin/wp --path="$root" eval 'echo RadioRubben\Fotballrobot\PublicationGate::hash(get_post(1073)).":".get_post_status(1073).":".get_post_thumbnail_id(1073);')
[[ "$before" == *:publish:812 ]]
printf '%s\n' "$before" > "$backup/article-before.txt"
rollback() {
  trap - ERR HUP INT TERM
  tar -xzf "$backup/code.tar.gz" -C "$live" || exit 10
  "${wp[@]}" maintenance-mode deactivate || true
  echo 'Default image code patch rolled back; article unchanged'
  exit 1
}
trap rollback ERR HUP INT TERM
"${wp[@]}" maintenance-mode activate
for path in "${files[@]}"; do
  tmp_file=$(mktemp "$live/${path}.XXXXXX")
  install -m 644 "$candidate/$path" "$tmp_file"
  mv "$tmp_file" "$live/$path"
done
[[ $("${wp[@]}" plugin get radio-rubben-fotballrobot --field=version) == 0.9.4 ]]
(cd "$live"; sha256sum --check --quiet "$stage/image-candidate.sha256")
/usr/local/bin/wp --path="$root" eval-file "$stage/scripts/fotballrobot-image-check.php"
after=$(/usr/local/bin/wp --path="$root" eval 'echo RadioRubben\Fotballrobot\PublicationGate::hash(get_post(1073)).":".get_post_status(1073).":".get_post_thumbnail_id(1073);')
[[ "$after" == "$before" ]]
"${wp[@]}" maintenance-mode deactivate
curl --fail --silent --show-error --max-time 30 "https://www.radiorubben.no/wp-json/?rr_image_release=$revision" -o /dev/null
trap - ERR HUP INT TERM
printf '%s\n' "$revision" > "$state/fotballrobot-current-revision"
printf 'Fotballrobot 0.9.4 deployed from %s; backup %s; default image 812\n' "$revision" "$backup/code.tar.gz"
