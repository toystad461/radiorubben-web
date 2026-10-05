#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
revision=${1:?Release SHA required}
[[ "$revision" =~ ^[0-9a-f]{40}$ ]]
state="$HOME/.radiorubben-deploy"
stage="$state/rss-studio/$revision"
backup="$state/backups/rss-studio-$revision"
mkdir "$state/lock"
trap 'rmdir "$state/lock"' EXIT
mkdir "$backup"
php "$stage/scripts/rss-studio-install.php" "$stage" "$backup" install
echo "RSS_PRIVATE_BACKUP_CREATED"
# The real-source test writes only its isolated board under private stage.
php "$stage/scripts/rss-studio-live-test.php" "$stage"
echo "RSS_SELECTIVE_RELEASE_AND_TEST_OK"
