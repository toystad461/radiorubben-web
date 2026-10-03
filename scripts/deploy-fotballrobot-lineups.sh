#!/usr/bin/env bash
# One selective release of the exact candidate validated against both official match pages.
set -Eeuo pipefail
umask 077
revision=${1:?Tested candidate revision required}
[[ "$revision" == 097928615bdc900738b98b9be68e79f6f7e20a85 ]]
state="$HOME/.radiorubben-deploy"
stage="$state/candidates/$revision"
candidate="$stage/wordpress/wp-content/plugins/radio-rubben-fotballrobot"
root=/run/webroots/r1417157
live="$root/wp-content/plugins/radio-rubben-fotballrobot"
wp=(/usr/local/bin/wp --path="$root" --skip-plugins --skip-themes)
[[ ! -e "$root/.maintenance" && -d "$live" && ! -L "$live" ]]
[[ $("${wp[@]}" option get home) == https://www.radiorubben.no ]]
[[ $("${wp[@]}" plugin get radio-rubben-fotballrobot --field=version) == 0.9.2 ]]
[[ ! -e "$live/includes/lineups.php" ]]
[[ -z $(find "$candidate" -type l -print -quit) ]]
[[ -s "$stage/lineup-preflight-ok.sha256" ]]
mkdir "$state/lock" || { echo 'Existing deployment lock; stop'; exit 3; }
trap 'rmdir "$state/lock"' EXIT
(cd "$live"; sha256sum --check --quiet "$stage/scripts/fotballrobot-lineup-baseline.sha256")
(cd "$candidate"; sha256sum --check --quiet "$stage/lineup-preflight-ok.sha256")
files=(includes/lineups.php includes/robot.php includes/writer.php radio-rubben-fotballrobot.php)
for path in "${files[@]}"; do php -l "$candidate/$path" >/dev/null; done
backup="$state/backups/fotballrobot-lineups-$revision"
mkdir -p "$state/backups"
mkdir "$backup"
tar -czf "$backup/code.tar.gz" -C "$live" .
tar -tzf "$backup/code.tar.gz" >/dev/null
before=$(/usr/local/bin/wp --path="$root" eval 'echo RadioRubben\Fotballrobot\PublicationGate::hash(get_post(1073)).":".get_post_status(1073);')
[[ "$before" == *:publish ]]
printf '%s\n' "$before" > "$backup/article-before.txt"
rollback() {
  trap - ERR HUP INT TERM
  tar -xzf "$backup/code.tar.gz" -C "$live" || exit 10
  rm -f "$live/includes/lineups.php"
  "${wp[@]}" maintenance-mode deactivate || true
  echo 'Lineup fix rolled back; no articles edited'
  exit 1
}
trap rollback ERR HUP INT TERM
"${wp[@]}" maintenance-mode activate
for path in "${files[@]}"; do
  tmp_file=$(mktemp "$live/${path}.XXXXXX")
  install -m 644 "$candidate/$path" "$tmp_file"
  mv "$tmp_file" "$live/$path"
done
[[ $("${wp[@]}" plugin get radio-rubben-fotballrobot --field=version) == 0.9.3 ]]
(cd "$live"; sha256sum --check --quiet "$stage/lineup-preflight-ok.sha256")
/usr/local/bin/wp --path="$root" eval 'use RadioRubben\Fotballrobot\PublicationGate as G; use RadioRubben\Fotballrobot\Lineups as L; $r=new ReflectionClass(L::class); if (L::VERSION !== "1.0.0" || $r->getFileName() !== WP_PLUGIN_DIR."/radio-rubben-fotballrobot/includes/lineups.php" || !method_exists(RadioRubben\Fotballrobot\Writer::class,"body") || G::canPublish(1071,get_post(1071))) {throw new RuntimeException("Lineup runtime or publication barrier failed");} echo "Installed lineup parser, renderer and test-only publication barrier verified.\n";'
after=$(/usr/local/bin/wp --path="$root" eval 'echo RadioRubben\Fotballrobot\PublicationGate::hash(get_post(1073)).":".get_post_status(1073);')
[[ "$after" == "$before" ]]
"${wp[@]}" maintenance-mode deactivate
curl --fail --silent --show-error --max-time 30 "https://www.radiorubben.no/wp-json/?rr_lineup_release=$revision" -o /dev/null
trap - ERR HUP INT TERM
printf '%s\n' "$revision" > "$state/fotballrobot-current-revision"
printf 'Fotballrobot 0.9.3 deployed from %s; backup %s; existing article unchanged\n' "$revision" "$backup/code.tar.gz"
