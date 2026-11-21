#!/usr/bin/env bash
#
# Baut installierbare ZIP-Dateien aus den Ordnern in themes/ und plugins/.
#
#   ./build.sh                    alle Pakete bauen
#   ./build.sh wx-lightbox …      nur die genannten Pakete bauen
#   ./build.sh --list             Pakete mit Version auflisten
#   ./build.sh --sync             Version aus dem Dateikopf überall nachziehen
#                                 (…_VERSION, Stable tag, Tabelle in README.md)
#
# Ergebnis: dist/<paket>-<version>.zip, darin der Ordner <paket>/ ohne Version,
# damit WordPress neue Versionen als Update der vorhandenen erkennt.
# Die Version steht im Kopf von style.css (Themes) bzw. <paket>.php (Plugins).
#
# Mit --expect-version X.Y.Z bricht das Skript ab, wenn die Version im Kopf
# nicht passt.

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

package_name() {
	sed -n -E 's/^[[:space:]*#@]*(Theme|Plugin) Name:[[:space:]]*(.*[^[:space:]])[[:space:]]*$/\2/p' "$(header_file "$1")" | head -n 1
}

# Datei, in der die Konstante <X>_VERSION steht.
constant_file() {
	case "$1" in
		themes/*)  echo "$1/functions.php" ;;
		plugins/*) header_file "$1" ;;
	esac
}

# Version aus dem Dateikopf in die Konstante <X>_VERSION und den
# „Stable tag“ der readme.txt übernehmen.
sync_versions() {
	local dir=$1 version file
	version=$(package_version "$dir")
	file=$(constant_file "$dir")
	if [ -f "$file" ]; then
		sed -i -E "s/^(define\( *'[A-Z0-9_]+_VERSION', *')[^']*('.*)$/\1$version\2/" "$file"
	fi
	if [ -f "$dir/readme.txt" ]; then
		sed -i -E "s/^(Stable tag:[[:space:]]*).*/\1$version/" "$dir/readme.txt"
	fi
}

# Tabelle zwischen den Markierungen in README.md neu schreiben.
sync_readme() {
	local repo_url=$1 table dir name version type tag file
	table=$(mktemp)
	{
		echo '| Paket | Art | Version | Download |'
		echo '|---|---|---|---|'
		for dir in themes/*/ plugins/*/; do
			dir=${dir%/}
			[ -f "$(header_file "$dir")" ] || continue
			name=$(basename "$dir")
			version=$(package_version "$dir")
			case "$dir" in themes/*) type=Theme ;; *) type=Plugin ;; esac
			tag="$name-v$version"
			file="$name-$version.zip"
			echo "| [$(package_name "$dir")]($dir/) | $type | $version | [$file]($repo_url/releases/download/$tag/$file) |"
		done
	} > "$table"
	awk -v t="$table" '
		/<!-- pakete:start -->/ { print; while ((getline l < t) > 0) print l; skip = 1; next }
		/<!-- pakete:end -->/   { skip = 0 }
		!skip { print }
	' README.md > README.md.tmp && mv README.md.tmp README.md
	rm -f "$table"
}

EXPECT=
PACKAGES=()
while [ $# -gt 0 ]; do
	case "$1" in
		--sync)
			for p in $(all_packages); do sync_versions "$(package_dir "$p")"; done
			sync_readme "${REPO_URL:-https://github.com/cwaxe/wordpress}"
			exit 0 ;;
		--list)
			for p in $(all_packages); do
				printf '%-22s %s\n' "$p" "$(package_version "$(package_dir "$p")")"
			done
			exit 0 ;;
		--expect-version) EXPECT=${2:-}; shift ;;
		-h|--help) sed -n '2,17p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
		*) PACKAGES+=( "$1" ) ;;
	esac
	shift
done

[ ${#PACKAGES[@]} -gt 0 ] || mapfile -t PACKAGES < <(all_packages)
for p in "${PACKAGES[@]}"; do build "$p"; done
