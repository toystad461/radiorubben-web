#!/usr/bin/env bash
set -Eeuo pipefail
# Called only after the owner authorizes production deployment.
stage=${1:?Private release stage required}
backup=${2:?New private backup path required}
work="$HOME/.radiorubben-deploy"
[[ "$stage" == "$work"/ai-policy/* && "$backup" == "$work"/backups/ai-policy-* ]] || exit 1
mkdir "$work/lock" || { echo 'Another Web deployment is running.' >&2; exit 1; }
installed=false
finish() {
  result=$?
  trap - EXIT
  if [[ "$result" != 0 && "$installed" == true ]]; then
    php "$stage/ai-policy-install.php" "$stage" "$backup" rollback || { echo 'Rollback requires manual attention.' >&2; exit 1; }
  fi
  rmdir "$work/lock"
  exit "$result"
}
trap finish EXIT
health() {
  code=$(curl --silent --show-error --output /dev/null --max-time 30 --write-out '%{http_code}' https://radiorubben.no/)
  [[ "$code" == 200 ]] || { echo "Homepage returned $code" >&2; return 1; }
}
health
php -l "$stage/publication-gate.php"
php "$stage/ai-policy-install.php" "$stage" "$backup" check
php "$stage/ai-policy-install.php" "$stage" "$backup" install
installed=true
/usr/local/bin/wp --path=/run/webroots/r1417157 --skip-themes eval 'if (\RadioRubben\Fotballrobot\PublicationGate::AI_POLICY_VERSION !== "1.0.0") { throw new \RuntimeException("Unexpected policy"); } echo "ACTIVE_AI_POLICY_1.0.0\n";'
health
php "$stage/ai-policy-install.php" "$stage" "$backup" after
printf 'AI_POLICY_DEPLOYED backup=%s\n' "$backup"
