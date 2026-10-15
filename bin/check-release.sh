#!/usr/bin/env bash
# Pre-release checks for the WordPress.org build. Run it locally before tagging;
# CI runs it on every push and the deploy workflow on every version tag.
#
#   bash bin/check-release.sh           # check the working tree
#   bash bin/check-release.sh 1.4.0     # ...and require exactly this version (the tag)
#
# Fails when:
#   - the header "Version:", MEDBEAFGALLERY_VERSION and the readme "Stable tag"
#     disagree (1.3.2 and 1.3.3 shipped with MEDBEAFGALLERY_VERSION '1.3.4', which
#     the Pro add-on reads to decide what it can do);
#   - the expected version (the tag) differs from them;
#   - readme.txt has no "= x.y.z =" changelog entry for the version;
#   - the release build (.distignore applied, exactly as the deploy action does
#     it) ships dev-only files or is missing the plugin's own files.
set -euo pipefail
cd "$(dirname "$0")/.."

SLUG=medical-before-after-gallery
MAIN="$SLUG.php"
failed=0
fail() { echo "::error::$*"; failed=1; }   # "::error::" annotates GitHub runs; plain text locally

header=$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([^[:space:]]*\).*/\1/p' "$MAIN" | head -n1 | tr -d '\r')
constant=$(sed -n "s/.*define([[:space:]]*'MEDBEAFGALLERY_VERSION',[[:space:]]*'\([^']*\)'.*/\1/p" "$MAIN" | head -n1)
stable=$(sed -n 's/^Stable tag:[[:space:]]*\([^[:space:]]*\).*/\1/p' readme.txt | head -n1 | tr -d '\r')
expected="${1:-$header}"

echo "header=$header  MEDBEAFGALLERY_VERSION=$constant  readme Stable tag=$stable  expected=$expected"
[ -n "$header" ]            || fail "No 'Version:' line in the $MAIN header."
[ "$header" = "$constant" ] || fail "Header Version ($header) != MEDBEAFGALLERY_VERSION ($constant)."
[ "$header" = "$stable" ]   || fail "Header Version ($header) != readme.txt Stable tag ($stable)."
[ "$header" = "$expected" ] || fail "Plugin version ($header) != expected version ($expected)."
grep -qF "= $header =" readme.txt || fail "readme.txt has no changelog entry '= $header ='."

if command -v rsync >/dev/null 2>&1; then
    build=$(mktemp -d)
    trap 'rm -rf "$build"' EXIT
    rsync -rc --exclude-from=.distignore ./ "$build/$SLUG/" --delete --delete-excluded
    for f in "$MAIN" readme.txt LICENSE.txt admin assets includes languages; do
        [ -e "$build/$SLUG/$f" ] || fail "Release build is missing $f."
    done
    for f in .git .github .gitignore .gitattributes .distignore bin CLAUDE.md tests node_modules trunk tags .wordpress-org; do
        [ ! -e "$build/$SLUG/$f" ] || fail "Release build contains $f — add it to .distignore."
    done
    echo "Release build: $(find "$build/$SLUG" -type f | wc -l | tr -d ' ') files."
else
    echo "rsync not found: skipping the release-build contents check (CI runs it)."
fi

if [ "$failed" -ne 0 ]; then
    exit 1
fi
echo "All release checks passed."
