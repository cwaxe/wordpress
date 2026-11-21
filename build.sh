#!/usr/bin/env bash
#
# Baut installierbare ZIP-Dateien aus den Ordnern in themes/ und plugins/.
#
#   ./build.sh                    alle Pakete bauen
#   ./build.sh wx-lightbox …      nur die genannten Pakete bauen
#   ./build.sh --list             Pakete mit Version auflisten
#
# Ergebnis: dist/<paket>-<version>.zip, darin der Ordner <paket>/ ohne Version,
# damit WordPress neue Versionen als Update der vorhandenen erkennt.
# Die Version steht im Kopf von style.css (Themes) bzw. <paket>.php (Plugins).
#
# Mit --expect-version X.Y.Z bricht das Skript ab, wenn die Version im Kopf
# nicht passt (wird beim Release aus einem Git-Tag genutzt).

set -euo pipefail
cd "$(dirname "$0")"

DIST=dist
EXCLUDES=( 'README.md' '.DS_Store' '*/.DS_Store' '__MACOSX/*' '*.orig' '*.rej' )

die() { echo "Fehler: $*" >&2; exit 1; }

# Ordner eines Pakets finden.
package_dir() {
	local name=$1
	if [ -d "themes/$name" ]; then echo "themes/$name"
	elif [ -d "plugins/$name" ]; then echo "plugins/$name"
	else return 1
	fi
}

# Datei mit dem Kopf, aus dem WordPress Name und Version liest.
header_file() {
	local dir=$1 name
	name=$(basename "$dir")
	case "$dir" in
		themes/*)  echo "$dir/style.css" ;;
		plugins/*) echo "$dir/$name.php" ;;
	esac
}

package_version() {
	local file
	file=$(header_file "$1")
	[ -f "$file" ] || die "$file nicht gefunden"
	sed -n -E 's/^[[:space:]*#@]*Version:[[:space:]]*([^[:space:]]+).*/\1/p' "$file" | head -n 1
}

all_packages() {
	for d in themes/*/ plugins/*/; do
		[ -d "$d" ] && basename "$d"
	done
}

build() {
	local name=$1 dir version zip excludes=()
	dir=$(package_dir "$name") || die "Paket „$name“ gibt es weder in themes/ noch in plugins/"
	version=$(package_version "$dir")
	[ -n "$version" ] || die "keine Version in $(header_file "$dir")"
	if [ -n "${EXPECT:-}" ] && [ "$EXPECT" != "$version" ]; then
		die "$name: Tag nennt Version $EXPECT, $(header_file "$dir") aber $version"
	fi

	php_check "$dir"

	mkdir -p "$DIST"
	zip="$PWD/$DIST/$name-$version.zip"
	rm -f "$zip"
	for e in "${EXCLUDES[@]}"; do excludes+=( -x "$name/$e" ); done
	( cd "$(dirname "$dir")" && zip -qrX "$zip" "$name" "${excludes[@]}" )
	echo "$DIST/$name-$version.zip"
}

# Syntaxprüfung, wenn PHP installiert ist.
php_check() {
	command -v php >/dev/null || return 0
	local f out
	while IFS= read -r -d '' f; do
		out=$(php -l "$f" 2>&1) || die "$out"
	done < <(find "$1" -name '*.php' -print0)
}

EXPECT=
PACKAGES=()
while [ $# -gt 0 ]; do
	case "$1" in
		--list)
			for p in $(all_packages); do
				printf '%-22s %s\n' "$p" "$(package_version "$(package_dir "$p")")"
			done
			exit 0 ;;
		--expect-version) EXPECT=${2:-}; shift ;;
		-h|--help) sed -n '2,15p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
		*) PACKAGES+=( "$1" ) ;;
	esac
	shift
done

[ ${#PACKAGES[@]} -gt 0 ] || mapfile -t PACKAGES < <(all_packages)
for p in "${PACKAGES[@]}"; do build "$p"; done
