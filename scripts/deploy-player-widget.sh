#!/usr/bin/env bash
# First selective installation. Existing robot and all its settings are untouched.
set -Eeuo pipefail
umask 077
revision=${1:?Tested revision required}
[[ "$revision" =~ ^[0-9a-f]{40}$ ]]
state="$HOME/.radiorubben-deploy"
stage="$state/candidates/$revision"
root=/run/webroots/r1417157
live="$root/wp-content/plugins/radio-rubben-player-widget"
theme="$root/wp-content/themes/radio-rubben-wordpress-v1/front-page.php"
wp=(/usr/local/bin/wp --path="$root" --skip-themes --user=toystad)
[[ ! -e "$root/.maintenance" && ! -e "$live" && ! -L "$theme" ]]
[[ $("${wp[@]}" option get home) == https://www.radiorubben.no ]]
[[ -z $(find "$stage/plugin" -type l -print -quit) ]]
(cd "$stage/plugin"; sha256sum --check --quiet "$stage/scripts/player-widget-target.sha256")
find "$stage/plugin" -name '*.php' -print0 | xargs -0 -n1 php -l
php "$stage/scripts/player-widget-home-patch.php" "$theme"
php -l "$theme.candidate"
mkdir "$state/lock" || { echo 'Another deployment is running'; exit 3; }
trap 'rmdir "$state/lock"' EXIT
backup="$state/backups/player-widget-$revision"
mkdir -p "$backup"
install -m 600 "$theme" "$backup/front-page.php"
rollback() {
  trap - ERR HUP INT TERM
  restore=$(mktemp "${theme}.rollback.XXXXXX")
  install -m 644 "$backup/front-page.php" "$restore" && mv "$restore" "$theme"
  "${wp[@]}" plugin deactivate radio-rubben-player-widget || true
  echo 'Homepage restored and new widget deactivated.'
  exit 1
}
trap rollback ERR HUP INT TERM
cp -R "$stage/plugin" "$live"
find "$live" -type d -exec chmod 755 {} +
find "$live" -type f -exec chmod 644 {} +
"${wp[@]}" plugin activate radio-rubben-player-widget
"${wp[@]}" eval-file "$stage/scripts/player-widget-configure.php"
chmod 644 "$theme.candidate"
mv "$theme.candidate" "$theme"
"${wp[@]}" eval "do_action('litespeed_purge_url', home_url('/'));"
curl --fail --silent --show-error --max-time 30 "https://www.radiorubben.no/?rr_widget_release=$revision" -o "$backup/home-verified.html"
grep -q 'rrpw-home-title' "$backup/home-verified.html"
grep -q 'c849b4db-02ae-451f-a507-dd01135f8359' "$backup/home-verified.html"
printf '%s\n' "$revision" > "$state/player-widget-current-revision"
trap - ERR HUP INT TERM
echo 'Player widget installed, configured and published on homepage.'
