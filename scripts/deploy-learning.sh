#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
revision=$1
[[ "$revision" =~ ^[0-9a-f]{40}$ ]]
root=/run/webroots/r1417157
state="$HOME/.radiorubben-deploy"
stage="$state/learning/$revision"
backup="$state/backups/learning-$revision"
wp_cmd(){ /usr/local/bin/wp --path="$root" --skip-themes --user=toystad "$@"; }
# Cooperate with both the selective release lock and the existing cron runner.
exec 9>"$state/fotballrobot-scheduler/runner.lock"
flock -w 60 9
mkdir "$state/lock"
owned_maintenance=false
cleanup(){ rmdir "$state/lock"; }
trap cleanup EXIT
# Never interpret a prior release receipt as a backup owned by this invocation.
[[ ! -e "$backup" ]]
rollback(){
  trap - ERR HUP INT TERM
  if [[ -f "$backup/receipt.json" ]]; then
    if ! php "$stage/scripts/learning-install.php" "$stage" "$backup" rollback; then
      echo 'Rollback blocked; preserve maintenance and inspect private backup.'
      exit 1
    fi
  fi
  if [[ "$owned_maintenance" == true ]]; then wp_cmd maintenance-mode deactivate; fi
  echo 'Learning release stopped; original code restored where backup existed.'
  exit 1
}
trap rollback ERR HUP INT TERM
[[ ! -e "$root/.maintenance" ]]
[[ $(wp_cmd option get home) == https://www.radiorubben.no ]]
[[ $(wp_cmd plugin get radio-rubben-fotballrobot --field=version) == 0.10.8 ]]
php "$stage/scripts/learning-install.php" "$stage" "$backup" check
while IFS= read -r -d '' file; do php -l "$file"; done < <(find "$stage/runtime" -name '*.php' -print0)
wp_cmd maintenance-mode activate
owned_maintenance=true
php "$stage/scripts/learning-install.php" "$stage" "$backup" install
php "$stage/scripts/learning-install.php" "$stage" "$backup" after
wp_cmd eval-file "$stage/scripts/learning-postflight.php" | tee "$backup/postflight.log"
grep -q '^LEARNING_RUNTIME_OK$' "$backup/postflight.log"
wp_cmd maintenance-mode deactivate
owned_maintenance=false
curl --fail --silent --show-error --output /dev/null https://www.radiorubben.no/
curl --fail --silent --show-error --output /dev/null https://www.radiorubben.no/wp-json/
php "$stage/scripts/learning-install.php" "$stage" "$backup" after
trap - ERR HUP INT TERM
echo "LEARNING_RELEASE_OK $revision"
