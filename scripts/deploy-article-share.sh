#!/usr/bin/env bash
set -euo pipefail
stage="$1"
root=/run/webroots/r1417157
target="$root/wp-content/themes/radio-rubben-next/template-parts/article-share.php"
old=ecaef0c3db0738760c4f69a687447955f0341a63a947163a080ae3b05ce2f3e1
new=3452ca0eb3a9cccf2eb703514259e73dc4ad1be613e0e384585011abf9bbd966
hash(){ sha256sum "$1" | cut -d' ' -f1; }
umask 077
mkdir -p "$HOME/.radiorubben-deploy"
exec 9>"$HOME/.radiorubben-deploy/deploy.lock"
flock -n 9
test ! -L "$target"
test "$(hash "$stage/article-share.php")" = "$new"
test "$(/usr/local/bin/wp --path="$root" --skip-plugins --skip-themes option get stylesheet)" = radio-rubben-next
test "$(/usr/local/bin/wp --path="$root" --skip-plugins --skip-themes option get home)" = https://www.radiorubben.no
test "$(hash "$target")" = "$old"
php -l "$stage/article-share.php"
backup="$HOME/.radiorubben-deploy/backups/article-share-$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -m 700 "$backup"
cp -p "$target" "$backup/article-share.php"
test "$(hash "$backup/article-share.php")" = "$old"
# Exercise restoration into private staging, never over the live file.
cp "$stage/article-share.php" "$backup/rollback-test"
cp "$backup/article-share.php" "$backup/rollback-test"
test "$(hash "$backup/rollback-test")" = "$old"
theme="$root/wp-content/themes/radio-rubben-next"
(cd "$theme"; find . -type f ! -path './template-parts/article-share.php' -print0 | sort -z | xargs -0 sha256sum) > "$backup/other-before.sha256"
installed=0
rollback(){
 code=$?
 if [ "$installed" = 1 ] && [ "$code" != 0 ]; then
  if [ "$(hash "$target")" = "$new" ]; then
   cp -p "$backup/article-share.php" "$target.rollback"
   mv "$target.rollback" "$target"
   echo SHARE_ROLLED_BACK
  else echo SHARE_CONCURRENT_CHANGE_MANUAL_REVIEW; fi
 fi
 exit "$code"
}
trap rollback EXIT
cp "$stage/article-share.php" "$target.new"
chmod --reference="$target" "$target.new"
test "$(hash "$target.new")" = "$new"
test "$(hash "$target")" = "$old"
mv "$target.new" "$target"
installed=1
test "$(hash "$target")" = "$new"
(cd "$theme"; sha256sum -c "$backup/other-before.sha256" > /dev/null)
echo "SHARE_INSTALLED $new"
echo "BACKUP $backup"
