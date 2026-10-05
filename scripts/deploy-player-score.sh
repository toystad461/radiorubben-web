#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
revision=${1:?Tested revision required}
[[ "$revision" =~ ^[0-9a-f]{40}$ ]]
state="$HOME/.radiorubben-deploy"
stage="$state/player-score/$revision"
root=/run/webroots/r1417157
plugins="$root/wp-content/plugins"
wp=(/usr/local/bin/wp --path="$root" --skip-themes --user=toystad)
[[ ! -e "$root/.maintenance" ]]
[[ $("${wp[@]}" option get home) == https://www.radiorubben.no ]]
[[ $("${wp[@]}" plugin get radio-rubben-player-widget --field=version) == 1.3.0 ]]
files=(radio-rubben-player-widget/includes/live.php radio-rubben-player-widget/radio-rubben-player-widget.php)
for path in "${files[@]}"; do [[ ! -L "$plugins/$path" && ! -L "$stage/plugins/$path" ]]; php -l "$stage/plugins/$path"; done
(cd "$plugins"; sha256sum --check --quiet "$stage/scripts/score-baseline.sha256")
(cd "$stage/plugins"; sha256sum --check --quiet "$stage/scripts/score-target.sha256")
mkdir "$state/lock" || { echo 'Another deployment is running'; exit 3; }
trap 'rmdir "$state/lock"' EXIT
backup="$state/backups/player-score-$revision"
mkdir -p "$backup"
tar -czf "$backup/code.tar.gz" -C "$plugins" "${files[@]}"
tar -tzf "$backup/code.tar.gz" >/dev/null
owned=false
rollback() {
  trap - ERR HUP INT TERM
  tar -xzf "$backup/code.tar.gz" -C "$plugins"
  if [[ "$owned" == true ]]; then "${wp[@]}" maintenance-mode deactivate || true; fi
  echo 'Previous two widget files restored.'
  exit 1
}
trap rollback ERR HUP INT TERM
"${wp[@]}" maintenance-mode activate
owned=true
for path in "${files[@]}"; do
  temporary=$(mktemp "$plugins/${path}.XXXXXX")
  install -m 644 "$stage/plugins/$path" "$temporary"
  mv "$temporary" "$plugins/$path"
done
(cd "$plugins"; sha256sum --check --quiet "$stage/scripts/score-target.sha256")
"${wp[@]}" maintenance-mode deactivate
owned=false
"${wp[@]}" eval-file "$stage/scripts/score-check.php" | tee "$backup/check.log"
grep -q '^SCORE_RELEASE_OK$' "$backup/check.log"
curl --fail --silent --show-error --max-time 30 'https://www.radiorubben.no/wp-json/rr-player-widget/v1/widget' -o "$backup/widget.json"
php -r '$j=json_decode(file_get_contents($argv[1]),true); if(!is_array($j["matches"]??null))exit(1);' "$backup/widget.json"
printf '%s\n' "$revision" > "$state/player-score-current-revision"
trap - ERR HUP INT TERM
echo 'Player score endpoint 1.3.1 published.'
