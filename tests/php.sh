#!/bin/sh
set -eu

cd "$(dirname "$0")/.."
set -- -d zend.assertions=1 -d assert.exception=1 -d auto_prepend_file=tests/bootstrap.php

# Exclude the private webhook configuration; tests never read live credentials.
find xExtension-* tests -type f \( -name '*.php' -o -name '*.phtml' \) ! -name config.php -print |
	while IFS= read -r file; do php -n -l "$file"; done
for test in xExtension-*/tests/*Test.php; do
	case "$test" in
		# Replace only the webhook's HTTP functions with the test transport.
		xExtension-N8nWebhook/*) php -d disable_functions=curl_init,curl_setopt_array,curl_exec,curl_errno,curl_getinfo,curl_error "$@" "$test" ;;
		*) php "$@" "$test" ;;
	esac
done
