#!/usr/bin/env bash
#
# WordPress OHNE Docker und OHNE MySQL: nur PHP + SQLite (zum Entwickeln/Testen, nicht für Live-Seiten).
# Ideal für Ubuntu oder Raspberry Pi ohne Docker.
#
# Voraussetzungen:  php (≥ 8.1) mit pdo_sqlite, curl, unzip
#   Ubuntu/Raspberry Pi OS:  sudo apt install php-cli php-sqlite3 php-mbstring php-xml php-gd php-curl php-zip curl unzip
#
# Aufruf:  bin/local-sqlite.sh            → http://localhost:8000
#          PORT=8080 bin/local-sqlite.sh
#          NO_SERVER=1 bin/local-sqlite.sh  (nur einrichten, keinen Server starten)

set -euo pipefail
cd "$(dirname "$0")/.."
ROOT="$PWD"
WP_DIR="${WP_DIR:-$ROOT/wp}"      # steht in .gitignore
PORT="${PORT:-8000}"
TMP="${TMPDIR:-/tmp}"

mkdir -p "$WP_DIR"

if [ ! -f "$WP_DIR/wp-load.php" ]; then
	echo "Lade WordPress …"
	curl -sSL https://wordpress.org/latest.zip -o "$TMP/wordpress.zip"
	rm -rf "$TMP/wp-extract" && unzip -q "$TMP/wordpress.zip" -d "$TMP/wp-extract"
	cp -r "$TMP/wp-extract/wordpress/." "$WP_DIR/"
	rm -rf "$TMP/wordpress.zip" "$TMP/wp-extract"
fi

if [ ! -f "$WP_DIR/wp-content/db.php" ]; then
	echo "Lade SQLite-Plugin …"
	curl -sSL https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip -o "$TMP/sqlite.zip"
	unzip -q -o "$TMP/sqlite.zip" -d "$WP_DIR/wp-content/plugins/"
	rm -f "$TMP/sqlite.zip"
	# Drop-in: Liegt wp-content/db.php vor, nutzt WordPress diese Datei statt MySQL.
	sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$WP_DIR/wp-content/plugins/sqlite-database-integration#" \
		-e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" \
		"$WP_DIR/wp-content/plugins/sqlite-database-integration/db.copy" > "$WP_DIR/wp-content/db.php"
fi

if [ ! -f "$WP_DIR/wp-cli.phar" ]; then
	echo "Lade WP-CLI …"
	curl -sSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o "$WP_DIR/wp-cli.phar"
fi

WP="php $WP_DIR/wp-cli.phar --allow-root --path=$WP_DIR"

if [ ! -f "$WP_DIR/wp-config.php" ]; then
	# DB-Zugangsdaten sind Dummy-Werte – SQLite braucht keine.
	$WP config create --dbname=wp --dbuser=wp --dbpass=wp --dbhost=localhost --skip-check --extra-php <<'PHP'
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_ENVIRONMENT_TYPE', 'local' );
PHP
fi

# Theme aus dem Repo einhängen (Symlink → Änderungen sofort sichtbar).
ln -sfn "$ROOT/wp-content/themes/fusspflege" "$WP_DIR/wp-content/themes/fusspflege"

WP_CMD="$WP" SITE_URL="http://localhost:$PORT" bin/setup.sh

if [ "${NO_SERVER:-}" != "1" ]; then
	echo "Starte Server: http://localhost:$PORT   (Beenden mit Strg+C)"
	$WP server --host=localhost --port="$PORT"
fi
