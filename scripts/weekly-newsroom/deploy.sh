#!/usr/bin/env bash
# Approval-gated selective release. Runtime manifest must match user-approved package.
set -Eeuo pipefail
umask 077
trap 'echo "Release precheck failed at line $LINENO" >&2' ERR
for tool in php sha256sum cut flock mkdir date find mktemp grep cp rm cmp curl; do command -v "$tool" >/dev/null || { echo "Missing required command: $tool" >&2; exit 1; }; done
resolve_path(){ php -r '$p=realpath($argv[1]);if($p===false)exit(1);echo $p;' "$1"; }
stage=$(resolve_path "${1:?Private verified package path required}")
expected=${RR_WEEKLY_APPROVED_MANIFEST:?Exact approved manifest SHA-256 required}
[[ "$expected" =~ ^[0-9a-f]{64}$ ]]
[[ $(sha256sum "$stage/manifest.json" | cut -d' ' -f1) == "$expected" ]]
root=/run/webroots/r1417157
[[ $(resolve_path "$root") == /customers/9/3/1/cptk37ymg/webroots/r1417157 ]]
state=$(resolve_path "$HOME/.radiorubben-deploy")
[[ "$stage" == "$state"/* ]]
# Cooperate with the WordPress scheduler, selective releases and Studio releases.
exec 9>"$state/fotballrobot-scheduler/runner.lock"; flock -w 60 9
exec 8>"$HOME/.radiorubben-studio-deploy/deploy.lock"; flock -w 60 8
mkdir "$state/lock"
trap 'rmdir "$state/lock"' EXIT
backup="$state/backups/weekly-newsroom-$(date -u +%Y%m%dT%H%M%SZ)-${expected:0:12}"
[[ ! -e "$backup" && ! -e "$root/.maintenance" ]]
wp_read(){ /usr/local/bin/wp --path="$root" --skip-plugins --skip-themes "$@"; }
wp_live(){ /usr/local/bin/wp --path="$root" --skip-themes --user=toystad "$@"; }
[[ $(wp_read option get home) == https://www.radiorubben.no ]]
php "$stage/scripts/install.php" "$stage" "$backup" check
while IFS= read -r -d '' f; do php -l "$f"; done < <(find "$stage/runtime" -name '*.php' -print0)
# A private pre-state file is separate from the new backup directory.
prestate=$(mktemp "$state/weekly-state.XXXXXXXX")
# Preserve pre-existing schedules; rollback may remove only our newly introduced hook.
cron_before=$(wp_read cron event list --fields=hook --format=csv)
results_existed=false
if grep -qx 'rrfr_club_results' <<< "$cron_before"; then results_existed=true; fi
owned=false
rollback(){
 trap - ERR HUP INT TERM
 if [[ -f "$backup/receipt.json" ]]; then
   if [[ "$owned" == false && ! -e "$root/.maintenance" ]]; then wp_read maintenance-mode activate; owned=true; fi
   if ! php "$stage/scripts/install.php" "$stage" "$backup" rollback; then
     echo 'Rollback blocked by changed code. Maintenance retained; inspect private backup.' >&2; exit 1
   fi
 fi
 if [[ -f "$backup/receipt.json" && "$results_existed" == false ]]; then
   wp_read cron event delete rrfr_club_results
 fi
 # No database restore: preserve all concurrent/new editorial work.
 if [[ "$owned" == true ]]; then wp_read maintenance-mode deactivate; fi
 echo 'Release stopped. Previous code restored where an owned backup existed.' >&2
 exit 1
}
trap rollback ERR HUP INT TERM
wp_read maintenance-mode activate; owned=true
wp_read eval-file "$stage/scripts/state.php" > "$prestate"
php "$stage/scripts/install.php" "$stage" "$backup" install
cp "$prestate" "$backup/state-before.json"; rm "$prestate"
wp_live eval-file "$stage/scripts/postflight.php" | tee "$backup/postflight.log"
grep -q '^WEEKLY_NEWSROOM_RUNTIME_OK$' "$backup/postflight.log"
wp_read eval-file "$stage/scripts/state.php" > "$backup/state-after.json"
if ! cmp -s "$backup/state-before.json" "$backup/state-after.json"; then
 php -r '$a=json_decode(file_get_contents($argv[1]),true);$b=json_decode(file_get_contents($argv[2]),true);foreach(array_unique(array_merge(array_keys($a),array_keys($b)))as$k)if(($a[$k]??null)!==($b[$k]??null)){echo "CHANGED_GROUP ".$k.PHP_EOL;if($k==="option_hashes")foreach(array_unique(array_merge(array_keys($a[$k]),array_keys($b[$k])))as$n)if(($a[$k][$n]??null)!==($b[$k][$n]??null))echo "CHANGED_OPTION ".$n.PHP_EOL;}' "$backup/state-before.json" "$backup/state-after.json"
 false
fi
php "$stage/scripts/install.php" "$stage" "$backup" after
wp_read maintenance-mode deactivate; owned=false
curl --fail --silent --show-error --output /dev/null https://www.radiorubben.no/
curl --fail --silent --show-error --output /dev/null https://www.radiorubben.no/bremnes-kamper-19-25-oktober-2026/
php "$stage/scripts/install.php" "$stage" "$backup" after
trap - ERR HUP INT TERM
printf 'PRIVATE_BACKUP %s\n' "$backup"
printf 'WEEKLY_NEWSROOM_RELEASE_OK %s\n' "$expected"
