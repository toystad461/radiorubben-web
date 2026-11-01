#!/usr/bin/env bash
# Continue the existing #107 release: preserve its sharing file, install only #110.
set -euo pipefail
stage="$(realpath "$1")"
root=/run/webroots/r1417157
theme="$root/wp-content/themes/radio-rubben-next"
target="$theme/template-parts/content/article.php"
share="$theme/template-parts/article-share.php"
old=d3e9a51519b767f76275cd41cbcb30868563ac2835cfede6b946d2755ccd8c85
new=a2fce7798bdc57bb8e895b3cb538d8f5df24fbe4e675ee0fbb50cd07b84212c8
share_hash=3452ca0eb3a9cccf2eb703514259e73dc4ad1be613e0e384585011abf9bbd966
hash(){ sha256sum "$1" | cut -d' ' -f1; }
wp_read(){ /usr/local/bin/wp --path="$root" --skip-plugins --skip-themes "$@"; }
snapshot(){ wp_read eval-file "$stage/article-delivery-state.php" snapshot; }
cache_purge(){ /usr/local/bin/wp --path="$root" --skip-themes eval-file "$stage/article-delivery-state.php" cache; }
manifest(){ (cd "$theme"; find . -type f ! -path './template-parts/content/article.php' -print0 | sort -z | xargs -0 sha256sum); }
umask 077
mkdir -p "$HOME/.radiorubben-deploy"
exec 9>"$HOME/.radiorubben-deploy/deploy.lock"
flock -n 9
test ! -L "$target" && test ! -L "$share"
test ! -f "$root/.maintenance"
test "$(wp_read option get stylesheet)" = radio-rubben-next
test "$(wp_read option get home)" = https://www.radiorubben.no
test "$(hash "$stage/article.php")" = "$new"
test "$(hash "$target")" = "$old"
test "$(hash "$share")" = "$share_hash"
php -l "$stage/article.php"
echo "PREFLIGHT article=$old share=$share_hash"
php "$stage/verify-article-delivery.php" before
backup="$HOME/.radiorubben-deploy/backups/article-share-template-$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -m 700 "$backup"
cp -p "$target" "$backup/article.php"
test "$(hash "$backup/article.php")" = "$old"
# Verify the one-file restoration before touching live code.
cp "$stage/article.php" "$backup/rollback-test"
cp "$backup/article.php" "$backup/rollback-test"
test "$(hash "$backup/rollback-test")" = "$old"
manifest > "$backup/other-before.sha256"
snapshot > "$backup/content-before.json"
installed=0
rollback(){
 code=$?
 trap - EXIT
 if [ "$installed" = 1 ] && [ "$code" != 0 ]; then
  if [ "$(hash "$target")" = "$new" ]; then
   cp -p "$backup/article.php" "$target.rollback"
   mv "$target.rollback" "$target"
   cache_purge || true
   test "$(hash "$target")" = "$old" && echo ARTICLE_ROLLED_BACK
  else echo ARTICLE_CONCURRENT_CHANGE_MANUAL_REVIEW; fi
 fi
 exit "$code"
}
trap rollback EXIT
cp "$stage/article.php" "$target.new"
chmod --reference="$target" "$target.new"
test "$(hash "$target.new")" = "$new"
test "$(hash "$target")" = "$old"
mv "$target.new" "$target"
installed=1
test "$(hash "$target")" = "$new"
cache_purge
php "$stage/verify-article-delivery.php" after
manifest > "$backup/other-after.sha256"
cmp "$backup/other-before.sha256" "$backup/other-after.sha256"
snapshot > "$backup/content-after.json"
cmp "$backup/content-before.json" "$backup/content-after.json"
test "$(hash "$share")" = "$share_hash"
test ! -f "$root/.maintenance"
echo "ARTICLE_INSTALLED $new"
echo "SHARING_PRESERVED $share_hash"
echo "OTHER_THEME_FILES_PRESERVED $(wc -l < "$backup/other-after.sha256")"
echo CONTENT_AND_MEDIA_METADATA_PRESERVED
echo "BACKUP $backup"
