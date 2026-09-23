#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
release=${1:?Release required}
[[ "$release" =~ ^[0-9]+-[0-9]+$ ]] || exit 2
root=/run/webroots/r1417157
state="$HOME/.radiorubben-deploy"
stage="$state/staging/$release"
backup="$state/backups/$release"
paths=(themes/radio-rubben-wordpress-v1 plugins/min-rubben plugins/RR_News plugins/RR_Quiz plugins/rr-radio-co-plugin-v1)
wp=(/usr/local/bin/wp --path="$root" --skip-plugins --skip-themes)
for tool in rsync tar curl; do command -v "$tool" >/dev/null; done
[[ $("${wp[@]}" option get stylesheet) == radio-rubben-wordpress-v1 ]]
[[ $("${wp[@]}" option get home) == https://www.radiorubben.no ]]
# Refuse to deploy over another running deployment or existing maintenance window.
[[ ! -f "$root/.maintenance" ]]
mkdir -p "$state/backups"
mkdir "$state/lock" || { echo 'Deployment lock exists; inspect before retrying'; exit 3; }
trap 'rmdir "$state/lock"' EXIT
for path in "${paths[@]}"; do
  [[ -d "$root/wp-content/$path" && ! -L "$root/wp-content/$path" ]]
  [[ -d "$stage/$path" && ! -L "$stage/$path" ]]
  [[ -z $(find "$stage/$path" -type l -print -quit) ]]
done
[[ ! -e "$backup" ]]
mkdir "$backup"
tar -czf "$backup/code.tar.gz" -C "$root/wp-content" "${paths[@]}"
tar -tzf "$backup/code.tar.gz" >/dev/null
rollback() {
  trap - ERR HUP INT TERM
  echo "Deployment failed; restoring code from $backup/code.tar.gz"
  mkdir -p "$backup/restore"
  tar -xzf "$backup/code.tar.gz" -C "$backup/restore" || exit 10
  for path in "${paths[@]}"; do
    rsync -rlt --delete "$backup/restore/$path/" "$root/wp-content/$path/" || exit 11
  done
  "${wp[@]}" maintenance-mode deactivate || true
  exit 1
}
trap rollback ERR HUP INT TERM
"${wp[@]}" maintenance-mode activate
for path in "${paths[@]}"; do
  # Overlay only: removed source files are not automatically deleted from production.
  rsync -rlt --chmod=D755,F644 "$stage/$path/" "$root/wp-content/$path/"
done
"${wp[@]}" maintenance-mode deactivate
for route in / /wp-json/; do
  curl --fail --silent --show-error --location --max-time 30 \
    "https://www.radiorubben.no${route}?rr_deploy=${release}" -o /dev/null
done
trap - ERR HUP INT TERM
printf 'Deployed %s; code backup: %s\n' "$release" "$backup/code.tar.gz"
