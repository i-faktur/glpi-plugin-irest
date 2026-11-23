#!/bin/sh
# Builds the archive of a release of iRest for GLPI, to unpack in the folder plugins of GLPI:
# glpi-irest-X.Y.Z.tar.bz2, with the folder irest/ at its root and its translations compiled, and its SHA-256.
#
# Usage: build-release.sh vX.Y.Z [tree]
# tree: what to archive (HEAD by default). DIST_DIR: where to write (dist by default).
set -eu

tag="${1:?Give the tag, e.g. v1.1.0}"
tree="${2:-HEAD}"
dist="${DIST_DIR:-dist}"
version="${tag#v}"

echo "$tag" | grep -Eq '^v[0-9]+\.[0-9]+\.[0-9]+$' || { echo "Not a release tag: $tag" >&2; exit 1; }

# The tag must be the version declared everywhere in the plugin
declared=$(sed -n "s/.*define('PLUGIN_IREST_VERSION', '\([^']*\)').*/\1/p" setup.php)
[ "$declared" = "$version" ] || { echo "setup.php declares $declared, the tag is $tag" >&2; exit 1; }
grep -q "<num>$version</num>" irest.xml || { echo "No version $version in irest.xml" >&2; exit 1; }
grep -q "^## \[$version\]" CHANGELOG.md || { echo "No entry for $version in CHANGELOG.md" >&2; exit 1; }

mkdir -p "$dist"
dist=$(cd "$dist" && pwd)
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
git archive --format=tar --prefix=irest/ "$tree" | tar -x -C "$work"

# Compiled from the sources, so that the archive cannot carry outdated translations
for po in "$work"/irest/locales/*.po; do
    msgfmt --check -o "${po%.po}.mo" "$po"
done

archive="glpi-irest-$version.tar.bz2"
tar -cjf "$dist/$archive" -C "$work" irest
(cd "$dist" && sha256sum "$archive" > "$archive.sha256")
echo "$dist/$archive"
