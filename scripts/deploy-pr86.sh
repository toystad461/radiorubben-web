#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
stage=${1:?private stage required}
backup=${2:?new private backup required}
action=${3:-check}
case "$stage" in "$HOME"/.radiorubben-deploy/pr86/*) ;; *) exit 2;; esac
case "$backup" in "$HOME"/.radiorubben-deploy/backups/pr86-*) ;; *) exit 2;; esac
case "$action" in check|install|rollback) ;; *) exit 2;; esac
work="$HOME/.radiorubben-deploy"
mkdir "$work/lock" || { echo 'Another deployment is active'; exit 1; }
installed=false
finish() {
  result=$?
  trap - EXIT
  if [ "$result" -ne 0 ] && [ "$installed" = true ]; then
    php "$stage/scripts/pr86-install.php" "$stage" "$backup" rollback || result=1
  fi
  rmdir "$work/lock"
  exit "$result"
}
trap finish EXIT
command -v flock >/dev/null
command -v timeout >/dev/null
test -x /usr/local/bin/wp
php -l "$stage/scripts/pr86-install.php"
php -l "$stage/scripts/fotballrobot-cron.php"
for file in "$stage"/runtime/wp-content/plugins/radio-rubben-fotballrobot/*.php "$stage"/runtime/wp-content/plugins/radio-rubben-fotballrobot/includes/*.php; do php -l "$file"; done
if [ "$action" = rollback ]; then
  php "$stage/scripts/pr86-install.php" "$stage" "$backup" rollback
  exit
fi
php "$stage/scripts/pr86-install.php" "$stage" "$backup" check
# Read-only: plugins are skipped, no queue handlers or model calls are loaded.
/usr/local/bin/wp --path=/run/webroots/r1417157 --skip-plugins --skip-themes option get rrfr_match_job_8985501 --format=json | php -r '$s=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);echo json_encode(array_intersect_key($s,array_flip(["status","phase","confirmed_at","due_at","created_at","updated_at","post_id"])),JSON_PRETTY_PRINT).PHP_EOL;'
if [ "$action" = check ]; then echo 'PR86_PREFLIGHT_OK; no runtime or editorial data changed'; exit; fi
test "$(curl -sS -o /dev/null -w '%{http_code}' https://www.radiorubben.no/)" = 200
php "$stage/scripts/pr86-install.php" "$stage" "$backup" install
installed=true
php "$stage/scripts/pr86-install.php" "$stage" "$backup" after
test "$(curl -sS -o /dev/null -w '%{http_code}' https://www.radiorubben.no/)" = 200
echo "PR86_CODE_INSTALLED; backup=$backup; scheduler activation remains separate"
