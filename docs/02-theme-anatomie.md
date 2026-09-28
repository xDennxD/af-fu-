# Theme-Anatomie: `wp-content/themes/fusspflege/`

Was jede Datei tut und **warum** sie so gebaut ist. Reihenfolge = sinnvolle Lese-Reihenfolge.

## 1. Wie WordPress eine Seite ausliefert

```
Browser → index.php → wp-load.php → wp-settings.php (Core, Plugins, dann Theme: functions.php)
        → WP_Query (welche Inhalte?) → template-loader.php (welche Template-Datei?) → HTML
```

**Template-Hierarchie** (Auswahl der Datei, spezifisch vor allgemein):

| URL | Datei in diesem Theme |
|---|---|
| Startseite `/` | `front-page.php` (gewinnt immer) |
| Seite `/impressum/` | `page.php` |
| Leistung `/leistungen/nagelspange/` | `single.php` (eine `single-leistung.php` würde gewinnen) |
| Beitrag `/hallo-welt/` | `single.php` |
| Blog-Liste, Archive, Suche | `index.php` (Pflicht-Fallback) |
| Nicht gefunden | `404.php` |

## 2. Die Dateien

| Datei | Aufgabe | Wichtig |
|---|---|---|
| `style.css` | **Nur** der Theme-Header (Name, Version, Text Domain). Ohne ihn erkennt WordPress kein Theme. | Das echte CSS liegt in `assets/css/main.css`. |
| `functions.php` | Setup: `add_theme_support`, Menü-Positionen, `wp_enqueue_style/script`, Includes aus `/inc` | Läuft bei **jedem** Aufruf (auch Backend). Keine Logik hier – nur laden. |
| `inc/template-tags.php` | Hilfsfunktionen: `fusspflege_option()`, `fusspflege_bild()`, Telefon-Link, Fallback-Menüs, JSON-LD | Templates bleiben schlank. Alle Defaults in `fusspflege_defaults()`. |
| `inc/customizer.php` | *Design → Customizer → Fußpflege-Theme*: Panel, 3 Sections, ~22 Felder | Jedes Setting **muss** einen `sanitize_callback` haben. Ein Array beschreibt alle Felder → eine Schleife baut sie. |
| `inc/cpt-leistungen.php` | Inhaltstyp „Leistung“, Meta-Box Dauer/Preis, `fusspflege_get_leistungen()` mit Demo-Fallback | `save_post`-Checkliste: Nonce → Autosave → Rechte → Sanitize. |
| `inc/kontaktformular.php` | POST → `admin-post.php` → Handler → Mail → Redirect mit `?kontakt=ok` | Nonce (CSRF), Honeypot (Bots), Transient (Rate-Limit), DSGVO-Checkbox. |
| `header.php` / `footer.php` | Rahmen jeder Seite | `wp_head()`, `wp_body_open()`, `wp_footer()` sind **Pflicht** – sonst laden Plugins/Styles nicht. |
| `front-page.php` | One-Pager: reiht 6 `template-parts/*.php` aneinander | `get_template_part()` statt `include` (Child-Theme-fähig). |
| `template-parts/*.php` | Je eine Sektion: hero, leistungen, preise, ueber, kontakt, rechtliches | Reines Markup + `esc_*()`. Keine Queries – die stecken in `/inc`. |
| `index.php`, `single.php`, `page.php`, `404.php` | Listen, Einzelansichten, Fehlerseite | Zeigen **The Loop**: `while ( have_posts() ) : the_post();` |
| `assets/css/main.css` | Design-Tokens in `:root`, responsive, Admin-Bar-Offset, WP-Standardklassen | Geladen mit `filemtime()` als Version → kein Cache-Problem nach Änderungen. |
| `assets/js/main.js` | Mobile Menü (aria-expanded), Scroll-Reveal (respektiert `prefers-reduced-motion`) | Kein jQuery, kein Inline-JS im Template. |
| `assets/img/*.svg` | Platzhalterbilder | Keine externen Bild-URLs → keine Requests an Dritte (DSGVO). |
| `screenshot.png` | Vorschau unter *Design → Themes* | 1200 × 900 px ist die Konvention. |

## 3. Die fünf Konzepte, die du erklären können musst

**Hooks.** `add_action( 'wp_enqueue_scripts', 'fusspflege_assets' )` heißt: „Wenn WordPress an der Stelle *wp_enqueue_scripts* ankommt, ruf meine Funktion auf.“ `add_filter` gibt zusätzlich einen Wert zurück, den du verändern kannst. Plugins funktionieren ausschließlich so.

**Escaping (Ausgabe) vs. Sanitizing (Eingabe).**
- Speichern: `sanitize_text_field()`, `sanitize_email()`, `absint()` – *bevor* etwas in die Datenbank geht.
- Ausgeben: `esc_html()` in Text, `esc_attr()` in Attributen, `esc_url()` in `href/src`, `wp_kses_post()` für erlaubtes HTML – *jedes Mal*, auch wenn der Wert „sicher aussieht“.

**Enqueue statt `<link>`/`<script>`.** Über `wp_enqueue_style/script` weiß WordPress, was geladen ist: keine Doppelladungen, Plugins können eingreifen (z. B. Caching/Minify), Abhängigkeiten (`array( 'jquery' )`) werden aufgelöst.

**Nonce.** Ein Einmal-Token in Formularen (`wp_nonce_field`) – bei der Verarbeitung `wp_verify_nonce`. Verhindert, dass eine fremde Seite ein Formular in deinem Namen abschickt (CSRF). Ersetzt **nicht** die Rechteprüfung (`current_user_can`).

**Custom Post Type + Meta.** Alles, was der Kunde selbst pflegen soll und mehrfach vorkommt (Leistungen, Team, Referenzen), ist ein CPT. Zusatzfelder sind Post-Meta. Große Projekte nutzen dafür das Plugin *ACF* – die Core-Variante hier zeigt, was darunter passiert.

## 4. Datenfluss im Überblick

```
Customizer (Design → Customizer)  ──get_theme_mod()──►  fusspflege_option()  ──►  template-parts/*.php
Backend → Leistungen (CPT + Meta) ──WP_Query──────────►  fusspflege_get_leistungen()  ──►  leistungen.php + preise.php
Seiten „Impressum“/„Datenschutz“  ──ID im Customizer──►  fusspflege_rechtliche_links()  ──►  rechtliches.php + Footer
Formular  ──POST──►  admin-post.php  ──►  fusspflege_kontakt_verarbeiten()  ──wp_mail()──►  Redirect ?kontakt=ok#kontakt
```

## 5. Typische Anfängerfehler, die hier vermieden sind

- CSS/JS direkt in `header.php` verlinkt → stattdessen `wp_enqueue_*`
- `wp_head()`/`wp_footer()` vergessen → Admin-Bar, Plugins, Styles fehlen
- `$_POST` direkt speichern → immer Nonce + Sanitize
- Werte mit `echo $x` ausgeben → `echo esc_html( $x )`
- Google Fonts / Maps / CDN eingebunden → in DE abmahnrelevant, hier: alles lokal
- Texte hart im Template → `__()` / `esc_html_e()` mit Text Domain (übersetzbar, Kunde kann Sprache wechseln)
- Logik im Template → in `/inc` auslagern

## 6. Sinnvolle nächste Ausbaustufen (Übungen)

1. **Selective Refresh** im Customizer: `transport => 'postMessage'` + `selective_refresh->add_partial()` → Vorschau ohne Neuladen
2. **Taxonomie** „Leistungsart“ für Filter
3. **Kontaktformular als Plugin** mit Shortcode (Themes = Design, Plugins = Funktion)
4. **`theme.json`** ergänzen → Editor-Farbpalette passend zum Theme
5. **Übersetzungsdatei** `languages/fusspflege.pot` mit `wp i18n make-pot .` erzeugen
6. **Child-Theme**-Test: Kann ein Kunde `template-parts/hero.php` überschreiben, ohne dein Theme anzufassen? (Ja – wegen `get_template_part`)
