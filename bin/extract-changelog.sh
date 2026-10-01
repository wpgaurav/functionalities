#!/usr/bin/env bash
# Print one changelog entry without including the matching upgrade notice.
set -euo pipefail

if [ "$#" -ne 2 ]; then
	echo 'Usage: extract-changelog.sh VERSION README' >&2
	exit 64
fi

awk -v version="$1" '
$0 == "== Changelog ==" { in_changelog = 1; next }
in_changelog && $0 == "= " version " =" { selected = 1; next }
selected && ($0 ~ /^= / || $0 ~ /^== /) { exit }
selected { print }
END { if (!selected) exit 2 }
' "$2"
