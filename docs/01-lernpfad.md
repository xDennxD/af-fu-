# WordPress-Lernpfad → Upwork-tauglich in 6–8 Wochen

**Startpunkt:** HTML/CSS kannst du, Python-Logik kannst du. PHP-Syntax ist damit in 1–2 Tagen drin.
**Methode:** Jede Woche ein Thema, jede Woche eine Übung **in diesem Repo**. Nicht nur lesen – bauen.

Lokal starten: `bin/setup.sh` (Docker) oder `bin/local-sqlite.sh` (nur PHP). Backend: `/wp-admin`, Login `admin / admin`.

---

## Woche 1 – WordPress als Anwender beherrschen

Du musst das Backend blind kennen, bevor du es für Kunden baust.

- **Erkunden:** Beiträge, Seiten, Medien, Kommentare · Design → Themes / Customizer / Menüs · Plugins · Einstellungen (Allgemein, Lesen, Permalinks)
- **Begriffe:** Beitrag (chronologisch) vs. Seite (statisch) · Kategorie/Schlagwort · Theme (Darstellung) vs. Plugin (Funktion) · Block-Editor „Gutenberg“ · Custom Post Type
- **Übung:** Menü unter *Design → Menüs* anlegen und der Position „Hauptnavigation“ zuweisen (ersetzt das Fallback-Menü des Themes) · Logo hochladen (*Customizer → Website-Informationen*) · Impressum-Text im Block-Editor ändern
- **Quellen:** [learn.wordpress.org](https://learn.wordpress.org) (Kurs *WordPress Basics*) · [de.wordpress.org/support](https://de.wordpress.org/support/)

## Woche 2 – PHP für WordPress

- **Syntax-Crashkurs (1 Tag):** `$variable`, Arrays `array()`/`[]`, `foreach`, Funktionen, `?:` und `??`, String-Verkettung mit `.`, `sprintf()`, alternative Syntax in Templates (`if (…) : … endif;`)
- **WordPress-Denkweise:** alles läuft über **Hooks** – `add_action()` (etwas tun) und `add_filter()` (Wert verändern). Das ist das Plugin-System.
- **The Loop:** `while ( have_posts() ) : the_post();` → danach funktionieren `the_title()`, `the_content()` (siehe `index.php`, `single.php`)
- **Übung:** In `functions.php` einen Filter schreiben, der an jeden Seitentitel „ – Test“ anhängt (`add_filter( 'the_title', … )`). Wirkung beobachten, wieder entfernen.
- **Quellen:** [php.net/manual/de](https://www.php.net/manual/de/) · [developer.wordpress.org/plugins/hooks](https://developer.wordpress.org/plugins/hooks/)

## Woche 3 – Theme-Entwicklung (dieses Repo)

- [`02-theme-anatomie.md`](02-theme-anatomie.md) komplett durcharbeiten: jede Datei öffnen, Kommentare lesen, ändern, im Browser prüfen.
- **Template-Hierarchie** verstehen: welche Datei rendert welche URL? → [developer.wordpress.org/themes/basics/template-hierarchy](https://developer.wordpress.org/themes/basics/template-hierarchy/)
- **Übungen:**
  1. Neue Sektion „Bewertungen“: `template-parts/bewertungen.php` + Customizer-Textarea (eine Bewertung pro Zeile) – Muster: `ueber.php` + `fusspflege_zeilen()`
  2. `single-leistung.php` anlegen und beobachten, dass sie `single.php` für Leistungen übersteuert
  3. Akzentfarbe im Customizer wählbar machen (`WP_Customize_Color_Control` + CSS-Variable `--brand` per `wp_add_inline_style()`)

## Woche 4 – Daten: Custom Post Types, Meta, Taxonomien, WP_Query

- Lesen: `inc/cpt-leistungen.php` – `register_post_type`, `register_post_meta`, `add_meta_box`, `save_post`-Sicherheitscheckliste, `WP_Query`
- **Übung:** Taxonomie „Leistungsart“ (`register_taxonomy`) anlegen und die Karten auf der Startseite danach gruppieren
- **Übung (Raspberry-Pi-Brücke):** Leistungen per REST-API holen: `GET /wp-json/wp/v2/leistung` → mit Python `requests` auslesen und z. B. auf einem Display anzeigen. `show_in_rest => true` macht das möglich.
- **Quelle:** [developer.wordpress.org/reference/classes/wp_query](https://developer.wordpress.org/reference/classes/wp_query/)

## Woche 5 – Plugins & Sicherheit

- **Regel:** Themes = Darstellung, Plugins = Funktion. Ein Kunde, der das Theme wechselt, will sein Kontaktformular behalten.
- **Übung:** Kontaktformular (`inc/kontaktformular.php`) in ein eigenes Plugin `wp-content/plugins/fusspflege-kontakt/` auslagern: Plugin-Header, `register_activation_hook`, Shortcode `[fusspflege_kontakt]`
- **Sicherheits-Grundregeln (Upwork-Kunden fragen das ab):**
  - Nonce prüfen (`wp_verify_nonce`) · Rechte prüfen (`current_user_can`)
  - **Eingabe sanitizen** (`sanitize_text_field`, `sanitize_email`, `absint`) · **Ausgabe escapen** (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`)
  - SQL nur über `$wpdb->prepare()` · Dateien nie direkt aufrufbar (`defined( 'ABSPATH' ) || exit;`)
- **Quellen:** [developer.wordpress.org/plugins](https://developer.wordpress.org/plugins/) · [developer.wordpress.org/apis/security](https://developer.wordpress.org/apis/security/)

## Woche 6 – Der Markt: Page-Builder, WooCommerce, Block-Themes

- **Elementor** (1 Tag): Die meisten Upwork-Kunden bringen Elementor/Divi/Bricks mit. Kostenlose Version lokal installieren, eine Landingpage nachbauen, verstehen, was „Elementor-Template“ und „Theme Builder“ bedeuten.
- **WooCommerce** (2 Tage): Shop lokal aufsetzen, Produkt anlegen, Testbestellung. Theme-Kompatibilität: `add_theme_support( 'woocommerce' )`. Viele Jobs: „Shop-Anpassung“, „Checkout-Feld“, „Versandregel“.
- **Block-Theme / FSE + `theme.json`** (1 Tag): Die Zukunft von WordPress. Twenty Twenty-Five ist eines – Site-Editor ansehen. Dein Theme hier ist ein *klassisches* Theme (Standard bei „HTML → WordPress“-Jobs).
- **Übung:** Child-Theme für Twenty Twenty-Five anlegen (nur `style.css` + `functions.php` mit Enqueue des Parent-Styles)

## Woche 7–8 – Betrieb: Deployment, Performance, Wartung

- **Hosting (DE):** all-inkl, IONOS, Hetzner, webgo, Raidboxes – SFTP/SSH, WP-CLI auf dem Server
- **Migration:** `wp db export` + `wp search-replace 'http://localhost:8080' 'https://kunde.de'` · Alternativ Plugin *Duplicator* / *All-in-One WP Migration*
- **Performance:** Caching-Plugin (WP Super Cache, LiteSpeed Cache) · Bilder als WebP · Google PageSpeed Insights / Core Web Vitals
- **Sicherheit:** Updates, Backups (UpdraftPlus), Login-Schutz (Limit Login Attempts, 2FA), `wp-config.php` (`DISALLOW_FILE_EDIT`), Dateirechte
- **E-Mail:** `wp_mail()` scheitert auf vielen Hostern → Plugin *WP Mail SMTP* + SMTP-Zugang des Kunden
- **Übung:** Dieses Theme auf einen Hoster (oder den Raspberry Pi im LAN per Docker) deployen und mit `wp search-replace` die URL umstellen

---

## Werkzeuge (Profi-Standard)

| Werkzeug | Zweck |
|---|---|
| **WP-CLI** | Alles ohne Klicken: `wp plugin list`, `wp search-replace`, `wp db export`, `wp theme mod set` – siehe `bin/setup.sh` |
| **Query Monitor** (Plugin) | Zeigt Queries, Hooks, PHP-Fehler, Template-Datei der aktuellen Seite |
| **Theme Check** (Plugin) | Prüft ein Theme gegen die WordPress-Richtlinien |
| **PHPCS + WordPress Coding Standards** | Im Repo eingerichtet: `composer install && composer lint` – Code-Stil und Sicherheitsregeln automatisch prüfen (`phpcs.xml.dist`) |
| **VS Code + PHP Intelephense** | Autovervollständigung für WordPress-Funktionen |
| **Browser DevTools** | Netzwerk-Tab: keine externen Requests? Lighthouse: Performance/Accessibility |

## Checkliste „Upwork-ready“

- [ ] Kann ein HTML/Figma-Design in ein klassisches Theme umsetzen (dieses Repo ✔)
- [ ] Kann Customizer-Optionen, CPTs und Meta-Felder anlegen
- [ ] Kennt Nonce / Sanitize / Escape und wendet es überall an
- [ ] Kann ein kleines Plugin mit Shortcode und Einstellungsseite schreiben
- [ ] Hat Elementor und WooCommerce einmal selbst benutzt
- [ ] Kann eine Seite migrieren (lokal → Hoster) und absichern
- [ ] Portfolio-Eintrag mit Screenshots + GitHub-Link steht (→ [`03-upwork.md`](03-upwork.md))
