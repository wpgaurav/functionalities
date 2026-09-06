#!/usr/bin/env bash
#
# Single source of truth for regenerating languages/functionalities.pot.
#
#   bin/make-pot.sh           rewrite the committed template
#   bin/make-pot.sh --check   regenerate to a temp file and diff (used by CI)
#
# CI runs the exact same command a developer does, so the freshness check can
# never fail on a flag or header that only one side passes.

set -euo pipefail

cd "$(dirname "$0")/.."

TARGET="languages/functionalities.pot"
HEADERS='{"Report-Msgid-Bugs-To":"https://github.com/wpgaurav/functionalities/issues"}'
EXCLUDE="tests,src,node_modules,vendor,build,assets/vendor,bin"

if [ -n "${WP_CLI:-}" ]; then
	WP="$WP_CLI"
elif command -v wp >/dev/null 2>&1; then
	WP="wp"
else
	PHAR="${TMPDIR:-/tmp}/wp-cli.phar"
	[ -f "$PHAR" ] || curl -sSL -o "$PHAR" \
		https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
	WP="php $PHAR"
fi

generate() {
	# shellcheck disable=SC2086
	$WP i18n make-pot . "$1" \
		--slug=functionalities \
		--domain=functionalities \
		--headers="$HEADERS" \
		--exclude="$EXCLUDE" >/dev/null
}

if [ "${1:-}" = "--check" ]; then
	EXPECTED="$(mktemp)"
	trap 'rm -f "$EXPECTED"' EXIT
	generate "$EXPECTED"
	if diff <(grep -v 'POT-Creation-Date' "$TARGET") \
	        <(grep -v 'POT-Creation-Date' "$EXPECTED"); then
		echo "$TARGET is current."
	else
		echo "::error::$TARGET is stale. Run bin/make-pot.sh and commit the result."
		exit 1
	fi
else
	generate "$TARGET"
	echo "Wrote $TARGET"
fi
