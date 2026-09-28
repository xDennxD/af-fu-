#!/usr/bin/env bash
#
# Einmalige Einrichtung der Entwicklungsumgebung:
#   WordPress installieren → Theme aktivieren → Beispielinhalte anlegen.
#
# Standard: läuft gegen die Docker-Umgebung aus docker-compose.yml (http://localhost:8080).
# Eigene WordPress-Installation:  WP_CMD="wp --path=/pfad/zu/wordpress" SITE_URL=http://… bin/setup.sh
#
# Das Skript ist idempotent – mehrfaches Ausführen legt nichts doppelt an.

set -euo pipefail
cd "$(dirname "$0")/.."

SITE_URL="${SITE_URL:-http://localhost:8080}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASS="${ADMIN_PASS:-admin}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"

if [ -z "${WP_CMD:-}" ]; then
	docker compose up -d
	WP_CMD="docker compose run --rm -T cli"
	echo "Warte, bis WordPress im Container bereit ist …"
	until $WP_CMD core version >/dev/null 2>&1; do sleep 2; done
fi

wp() { $WP_CMD "$@"; }

# --- WordPress installieren -------------------------------------------------
if ! wp core is-installed >/dev/null 2>&1; then
	wp core install --url="$SITE_URL" --title="Fußpflegepraxis" \
		--admin_user="$ADMIN_USER" --admin_password="$ADMIN_PASS" \
		--admin_email="$ADMIN_EMAIL" --skip-email
fi

# --- Theme + Grundeinstellungen --------------------------------------------
if [ "$(wp theme list --status=active --field=name)" != "fusspflege" ]; then
	wp theme activate fusspflege
fi
wp option update blogdescription "Medizinische & kosmetische Fußpflege in Niederlahnstein – Termin per Klick." >/dev/null
wp option update permalink_structure "/%postname%/" >/dev/null
wp option update timezone_string "Europe/Berlin" >/dev/null
wp rewrite flush >/dev/null

# --- Rechtsseiten (Muster – vor dem Livegang juristisch prüfen lassen) -----
seite_id() {
	local slug="$1" titel="$2" inhalt="$3" id
	id="$(wp post list --post_type=page --name="$slug" --field=ID --format=ids)"
	if [ -z "$id" ]; then
		id="$(wp post create --post_type=page --post_status=publish --post_name="$slug" \
			--post_title="$titel" --post_content="$inhalt" --porcelain)"
	fi
	echo "$id"
}

IMPRESSUM_ID="$(seite_id impressum "Impressum" '<!-- wp:paragraph --><p><strong>Angaben gemäß § 5 DDG</strong><br>[Vorname Nachname]<br>[Straße Hausnummer]<br>[PLZ] Niederlahnstein</p><!-- /wp:paragraph --><!-- wp:paragraph --><p><strong>Kontakt</strong><br>Telefon: [Telefon]<br>E-Mail: [E-Mail]</p><!-- /wp:paragraph --><!-- wp:paragraph --><p><strong>Umsatzsteuer-ID</strong><br>[falls vorhanden, sonst Hinweis auf Kleinunternehmerregelung § 19 UStG]</p><!-- /wp:paragraph -->')"

DATENSCHUTZ_ID="$(seite_id datenschutz "Datenschutzerklärung" '<!-- wp:paragraph --><p><strong>Verantwortliche Stelle</strong><br>[Vorname Nachname], [Straße Hausnummer], [PLZ] Niederlahnstein</p><!-- /wp:paragraph --><!-- wp:paragraph --><p><strong>Kontaktformular</strong><br>Ihre Angaben (Name, E-Mail, Nachricht) werden ausschließlich zur Bearbeitung Ihrer Anfrage verarbeitet (Art. 6 Abs. 1 lit. b DSGVO) und nach Abschluss gelöscht.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p><strong>Server-Logfiles</strong><br>Der Hosting-Anbieter speichert automatisch Zugriffsdaten (IP-Adresse, Zeitpunkt, aufgerufene Seite) zur Sicherstellung des Betriebs.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p><strong>Ihre Rechte</strong><br>Auskunft, Berichtigung, Löschung, Einschränkung, Datenübertragbarkeit, Widerspruch sowie Beschwerde bei einer Aufsichtsbehörde.</p><!-- /wp:paragraph --><!-- wp:paragraph --><p><em>Muster-Text: Diese Website setzt keine Cookies und lädt keine externen Dienste. Vor Veröffentlichung juristisch prüfen lassen.</em></p><!-- /wp:paragraph -->')"

wp theme mod set fusspflege_impressum_seite "$IMPRESSUM_ID" >/dev/null
wp theme mod set fusspflege_datenschutz_seite "$DATENSCHUTZ_ID" >/dev/null

# --- Praxisdaten (Beispiel) -------------------------------------------------
wp theme mod set fusspflege_praxis_name "Fußpflegepraxis Lahnstein" >/dev/null
wp theme mod set fusspflege_inhaber "Erika Musterfrau" >/dev/null
wp theme mod set fusspflege_strasse "Musterstraße 12" >/dev/null
wp theme mod set fusspflege_plz "56112" >/dev/null
wp theme mod set fusspflege_ort "Niederlahnstein" >/dev/null
wp theme mod set fusspflege_telefon "02621 123456" >/dev/null
wp theme mod set fusspflege_email "kontakt@example.com" >/dev/null
wp theme mod set fusspflege_karte_url "https://www.openstreetmap.org/search?query=Niederlahnstein" >/dev/null

# --- Leistungen (Custom Post Type) -----------------------------------------
if [ "$(wp post list --post_type=leistung --post_status=any --format=count)" = "0" ]; then
	leistung() {
		wp post create --post_type=leistung --post_status=publish --post_title="$1" \
			--post_excerpt="$2" --menu_order="$3" \
			--meta_input="{\"fusspflege_dauer\":\"$4\",\"fusspflege_preis\":\"$5\"}" >/dev/null
	}
	leistung "Medizinische Fußpflege"     "Behandlung von Hühneraugen, eingewachsenen Nägeln, Druckstellen. Beratung zu Orthesen und Pflege." 1 "45 Min"          "ab 48 €"
	leistung "Professionelle Nagelpflege" "Nagelkorrektur, Kürzen, Polieren. Auf Wunsch Nagelspangen-Systeme bei Fehlstellungen."           2 "20–30 Min"       "ab 35 €"
	leistung "Hornhaut & Fersenrisse"     "Sanfte, gründliche Entfernung übermäßiger Hornhaut. Pflegeplan für zu Hause inklusive."           3 "30 Min"          "ab 30 €"
	leistung "Nagelspange"                "Korrektur eingewachsener oder eingerollter Nägel mit einem Spangensystem."                        4 "je nach Aufwand" "ab 25 €"
fi

echo
echo "Fertig!  Website: $SITE_URL   Backend: $SITE_URL/wp-admin  (Login: $ADMIN_USER / $ADMIN_PASS)"
