#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
resolve_path(){ php -r '$p=realpath($argv[1]);if($p===false)exit(1);echo $p;' "$1"; }
stage=$(resolve_path "${1:?Private package required}")
expected=${RR_SPORT_APPROVED_MANIFEST:?Approved manifest required}
[[ "$expected" =~ ^[a-f0-9]{64}$ ]]
[[ $(sha256sum "$stage/manifest.json" | cut -d' ' -f1) == "$expected" ]]
root=/run/webroots/r1417157
state="$HOME/.radiorubben-deploy"
[[ $(resolve_path "$root") == /customers/9/3/1/cptk37ymg/webroots/r1417157 ]]
[[ $(resolve_path "$stage") == "$(resolve_path "$state")"/* ]]
exec 9>"$state/fotballrobot-scheduler/runner.lock"; flock -w 60 9
exec 8>"$HOME/.radiorubben-studio-deploy/deploy.lock"; flock -w 60 8
mkdir "$state/lock"; trap 'rmdir "$state/lock"' EXIT
backup="$state/backups/match-stories-sport-$(date -u +%Y%m%dT%H%M%SZ)-${expected:0:12}"
[[ ! -e "$backup" && ! -e "$root/.maintenance" ]]
wp_read(){ /usr/local/bin/wp --path="$root" --skip-plugins --skip-themes "$@"; }
wp_live(){ /usr/local/bin/wp --path="$root" --skip-themes --user=toystad "$@"; }
[[ $(wp_read option get home) == https://www.radiorubben.no ]]
export RR_SPORT_STAGE="$stage" RR_SPORT_BACKUP="$backup"
php "$stage/scripts/install.php" "$stage" "$backup" check
RR_SPORT_NAV_ACTION=check wp_read eval-file "$stage/scripts/navigation.php"
while IFS= read -r -d '' f; do php -l "$f" >/dev/null; done < <(find "$stage/runtime" -name '*.php' -print0)
prestate=$(mktemp "$state/sport-state.XXXXXXXX")
owned=false
rollback(){
 trap - ERR HUP INT TERM
 echo 'Release failed; restoring only this delivery.' >&2
 if [[ "$owned" == false && ! -e "$root/.maintenance" ]]; then wp_read maintenance-mode activate; owned=true; fi
 if [[ -f "$backup/navigation.json" ]]; then
   RR_SPORT_NAV_ACTION=rollback wp_read eval-file "$stage/scripts/navigation.php" || { echo 'Navigation changed concurrently; maintenance retained' >&2; exit 1; }
 fi
 if [[ -f "$backup/receipt.json" ]]; then
   php "$stage/scripts/install.php" "$stage" "$backup" rollback || { echo 'Code rollback blocked; maintenance retained' >&2; exit 1; }
 fi
 wp_live eval 'if(function_exists("wpo_cache_flush"))wpo_cache_flush();' || true
 if [[ "$owned" == true ]]; then wp_read maintenance-mode deactivate; fi
 echo 'SPORT_RELEASE_ROLLED_BACK' >&2
 exit 1
}
trap rollback ERR HUP INT TERM
wp_read maintenance-mode activate; owned=true
wp_read eval-file "$stage/scripts/state.php" > "$prestate"
php "$stage/scripts/install.php" "$stage" "$backup" install
cp "$prestate" "$backup/state-before.json"; rm "$prestate"
wp_live eval-file "$stage/scripts/postflight.php"
RR_SPORT_NAV_ACTION=apply wp_read eval-file "$stage/scripts/navigation.php" | tee "$backup/navigation-result.json"
RR_SPORT_NAV_ACTION=after wp_read eval-file "$stage/scripts/navigation.php"
wp_live eval-file "$stage/scripts/postflight.php"
wp_read eval-file "$stage/scripts/state.php" > "$backup/state-after.json"
cmp "$backup/state-before.json" "$backup/state-after.json"
php "$stage/scripts/install.php" "$stage" "$backup" after
wp_read maintenance-mode deactivate; owned=false
for route in / /sport/; do
  curl --fail --silent --show-error --location --max-time 30 "https://www.radiorubben.no${route}?rr_sport_release=${expected:0:12}" -o "$backup/http-$(echo "$route" | tr '/' '_').html"
done
grep -q 'href="https://www.radiorubben.no/sport/"' "$backup/http-_.html"
grep -q 'id="rr-weekly-results"' "$backup/http-_sport_.html"
if grep -q '\[rrfr_weekly_results\]' "$backup/http-_sport_.html"; then false; fi
php "$stage/scripts/install.php" "$stage" "$backup" after
trap - ERR HUP INT TERM
printf 'PRIVATE_BACKUP %s\nSPORT_RELEASE_OK %s\n' "$backup" "$expected"
