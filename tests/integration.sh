#!/bin/sh
set -eu

cd "$(dirname "$0")/.."

# FreshRSS is installed only in this disposable test directory.
freshrss_version=1.30.0
test_directory=$(mktemp -d)
trap 'rm -rf "$test_directory"' EXIT
fresh="$test_directory/freshrss"
mkdir "$fresh"
curl --fail --silent --show-error --location --retry 3 \
	"https://github.com/FreshRSS/FreshRSS/archive/refs/tags/$freshrss_version.tar.gz" \
	-o "$test_directory/freshrss.tar.gz"
tar -xzf "$test_directory/freshrss.tar.gz" --strip-components=1 -C "$fresh"

cp -R xExtension-Translate xExtension-StopVideos "$fresh/extensions/"
mkdir "$fresh/extensions/xExtension-N8nWebhook"
cp xExtension-N8nWebhook/metadata.json xExtension-N8nWebhook/extension.php "$fresh/extensions/xExtension-N8nWebhook/"

php "$fresh/cli/do-install.php" --default-user test --db-type sqlite \
	--auth-type form --allow-anonymous=false --disable-update --base-url http://127.0.0.1
php "$fresh/cli/create-user.php" --user test --password test-only-password --language en --no-default-feeds
php -d zend.assertions=1 -d assert.exception=1 -d auto_prepend_file=tests/bootstrap.php \
	tests/integration/RegistrationTest.php "$fresh"
php -d zend.assertions=1 -d assert.exception=1 -d auto_prepend_file=tests/bootstrap.php \
	tests/integration/HttpTest.php "$fresh"
