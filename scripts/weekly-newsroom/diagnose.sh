#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
stage=$1
state="$HOME/.radiorubben-deploy"
exec 9>"$state/fotballrobot-scheduler/runner.lock"; flock -w 60 9
exec 8>"$HOME/.radiorubben-studio-deploy/deploy.lock"; flock -w 60 8
mkdir "$state/lock"; trap 'rmdir "$state/lock"' EXIT
root=/run/webroots/r1417157
/usr/local/bin/wp --path="$root" --skip-plugins --skip-themes eval-file "$stage/scripts/state.php" > "$stage/diagnostic-before.json"
/usr/local/bin/wp --path="$root" --skip-themes --user=toystad eval-file "$stage/scripts/state.php" > "$stage/diagnostic-after.json"
php -r '$a=json_decode(file_get_contents($argv[1]),true);$b=json_decode(file_get_contents($argv[2]),true);foreach(array_unique(array_merge(array_keys($a),array_keys($b)))as$k){if(($a[$k]??null)!==($b[$k]??null)){echo "CHANGED_GROUP ".$k.PHP_EOL;if($k==="option_hashes")foreach(array_unique(array_merge(array_keys($a[$k]),array_keys($b[$k])))as$n)if(($a[$k][$n]??null)!==($b[$k][$n]??null))echo "CHANGED_OPTION ".$n.PHP_EOL;}}echo "DIAGNOSTIC_COMPLETE\n";' "$stage/diagnostic-before.json" "$stage/diagnostic-after.json"
cat "$state/backups/weekly-newsroom-20261024T054244Z-aae582dc433a/state-before.json" "$state/backups/weekly-newsroom-20261024T054244Z-aae582dc433a/state-after.json"
