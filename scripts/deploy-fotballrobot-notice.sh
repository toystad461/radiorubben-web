#!/usr/bin/env bash
# One release of the four reviewed runtime files. Requires successful isolated preflight.
set -Eeuo pipefail
umask 077
revision=${1:?Tested candidate revision required}
[[ "$revision" == 4f5af6467196a10a96775262dcd2312f6447b799 ]]
state="$HOME/.radiorubben-deploy"
stage="$state/candidates/$revision"
candidate="$stage/wordpress/wp-content/plugins/radio-rubben-fotballrobot"
root=/run/webroots/r1417157
live="$root/wp-content/plugins/radio-rubben-fotballrobot"
wp=(/usr/local/bin/wp --path="$root" --skip-plugins --skip-themes)
[[ ! -e "$root/.maintenance" && -d "$live" && ! -L "$live" ]]
[[ $("${wp[@]}" option get home) == https://www.radiorubben.no ]]
[[ $("${wp[@]}" plugin get radio-rubben-fotballrobot --field=version) == 0.9.1 ]]
[[ $(cat "$stage/notice-preflight-ok") == ef3deb807b8126f014a875b9f2904f11341e1e7f48281b7b94d0b79249344dcc ]]
[[ $("${wp[@]}" post get 1073 --field=post_status) == draft ]]
[[ ! -e "$live/includes/editorial-notice.php" ]]
[[ -z $(find "$candidate" -type l -print -quit) ]]
mkdir "$state/lock" || { echo 'Existing deployment lock; stop'; exit 3; }
trap 'rmdir "$state/lock"' EXIT
(cd "$live"; sha256sum --check --quiet "$stage/scripts/fotballrobot-notice-baseline.sha256")
(cd "$candidate"; sha256sum --check --quiet "$stage/notice-candidate.sha256")
files=(includes/editorial-notice.php includes/writer.php includes/robot.php radio-rubben-fotballrobot.php)
for path in "${files[@]}"; do php -l "$candidate/$path" >/dev/null; done
backup="$state/backups/fotballrobot-notice-$revision"
mkdir -p "$state/backups"
mkdir "$backup"
tar -czf "$backup/code.tar.gz" -C "$live" .
tar -tzf "$backup/code.tar.gz" >/dev/null
rollback() {
  trap - ERR HUP INT TERM
  tar -xzf "$backup/code.tar.gz" -C "$live" || exit 10
  rm -f "$live/includes/editorial-notice.php"
  "${wp[@]}" maintenance-mode deactivate || true
  echo 'Notice fix rolled back; original article retained'
  exit 1
}
trap rollback ERR HUP INT TERM
"${wp[@]}" maintenance-mode activate
for path in "${files[@]}"; do install -m 644 "$candidate/$path" "$live/$path"; done
[[ $("${wp[@]}" plugin get radio-rubben-fotballrobot --field=version) == 0.9.2 ]]
/usr/local/bin/wp --path="$root" eval 'use RadioRubben\Fotballrobot\PublicationGate as G; use RadioRubben\Fotballrobot\EditorialNotice as N; $p=get_post(1073); if (!class_exists(N::class) || N::VERSION !== "1.0.0" || !G::current(1073,$p) || !G::canPublish(1073,$p) || $p->post_status !== "draft" || G::hash($p) !== "ef3deb807b8126f014a875b9f2904f11341e1e7f48281b7b94d0b79249344dcc" || N::reviewContent($p->post_content) === $p->post_content || G::canPublish(1071,get_post(1071))) { throw new RuntimeException("Notice release verification failed"); } echo "Runtime, unchanged draft, quality approval and test-only barrier verified.\n";'
"${wp[@]}" maintenance-mode deactivate
curl --fail --silent --show-error --max-time 30 "https://www.radiorubben.no/wp-json/?rr_notice_release=$revision" -o /dev/null
trap - ERR HUP INT TERM
printf '%s\n' "$revision" > "$state/fotballrobot-current-revision"
printf 'Fotballrobot 0.9.2 deployed from %s; backup %s\n' "$revision" "$backup/code.tar.gz"
