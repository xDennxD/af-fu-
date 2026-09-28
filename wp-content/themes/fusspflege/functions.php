<?php
/**
 * Fußpflege One-Pager – Theme-Setup.
 *
 * Diese Datei wird bei JEDEM Seitenaufruf geladen (Frontend und Backend).
 * Sie enthält nur Setup + Includes; die eigentliche Logik liegt in /inc.
 *
 * @package Fusspflege
 */

defined( 'ABSPATH' ) || exit; // Direktaufruf der Datei im Browser verhindern.

define( 'FUSSPFLEGE_VERSION', '1.0.0' );
define( 'FUSSPFLEGE_DIR', get_template_directory() );     // Server-Pfad, z. B. /var/www/html/wp-content/themes/fusspflege.
define( 'FUSSPFLEGE_URI', get_template_directory_uri() ); // URL, z. B. https://praxis.de/wp-content/themes/fusspflege.

require FUSSPFLEGE_DIR . '/inc/template-tags.php';   // Hilfsfunktionen für Templates.
require FUSSPFLEGE_DIR . '/inc/customizer.php';      // Design → Customizer.
require FUSSPFLEGE_DIR . '/inc/cpt-leistungen.php';  // Inhaltstyp "Leistung" + Meta-Box.
require FUSSPFLEGE_DIR . '/inc/kontaktformular.php'; // Formular-Verarbeitung.

/**
 * Theme-Features anmelden.
 * Hook: after_setup_theme – läuft nach dem Laden des Themes, vor init.
 */
function fusspflege_setup() {
	// Übersetzungen aus /languages (Text Domain "fusspflege", siehe style.css).
	load_theme_textdomain( 'fusspflege', FUSSPFLEGE_DIR . '/languages' );

	add_theme_support( 'title-tag' );            // <title> erzeugt WordPress selbst (über wp_head).
	add_theme_support( 'post-thumbnails' );      // Beitragsbilder für Leistungen/Beiträge.
	add_theme_support( 'automatic-feed-links' ); // RSS-Links im <head>.
	add_theme_support( 'responsive-embeds' );    // Eingebettete Videos skalieren mit.
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' )
	);
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 72,
			'width'       => 72,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	// Menü-Positionen: erscheinen unter Design → Menüs → "Positionen".
	register_nav_menus(
		array(
			'primary' => __( 'Hauptnavigation', 'fusspflege' ),
			'footer'  => __( 'Footer-Navigation', 'fusspflege' ),
		)
	);
}
add_action( 'after_setup_theme', 'fusspflege_setup' );

/**
 * CSS/JS einbinden – nie per <link>/<script> direkt im Template.
 * Vorteile: Plugins können eingreifen, keine Doppel-Ladungen, sauberes Caching.
 * filemtime() als Version = automatischer Cache-Buster bei jeder Dateiänderung.
 */
function fusspflege_assets() {
	wp_enqueue_style(
		'fusspflege-main',
		FUSSPFLEGE_URI . '/assets/css/main.css',
		array(),
		(string) filemtime( FUSSPFLEGE_DIR . '/assets/css/main.css' )
	);

	wp_enqueue_script(
		'fusspflege-main',
		FUSSPFLEGE_URI . '/assets/js/main.js',
		array(),
		(string) filemtime( FUSSPFLEGE_DIR . '/assets/js/main.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer', // Blockiert das Rendering nicht (WP ≥ 6.3).
		)
	);
}
add_action( 'wp_enqueue_scripts', 'fusspflege_assets' );

/**
 * Zusätzliche <head>-Tags.
 * Die Meta-Description kommt aus Einstellungen → Allgemein → "Untertitel".
 * SEO-Plugins (Yoast, Rank Math) bringen ihre eigene mit – dann diese Funktion entfernen.
 */
function fusspflege_head_meta() {
	echo '<meta name="theme-color" content="#0ea5e9">' . "\n";

	if ( is_front_page() && get_bloginfo( 'description' ) ) {
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( get_bloginfo( 'description' ) ) );
	}
}
add_action( 'wp_head', 'fusspflege_head_meta', 1 );

/**
 * Kleine Härtung: WordPress-Version nicht im Quelltext verraten.
 */
remove_action( 'wp_head', 'wp_generator' );

/**
 * DSGVO: WordPress' Emoji-Loader lädt Bilder von s.w.org nach, sobald ein Browser
 * keine nativen Emojis kann → externe Verbindung ohne Einwilligung. Deshalb aus.
 * Moderne Browser zeigen Emojis ohnehin nativ an; das Theme nutzt Inline-SVG-Icons.
 */
function fusspflege_emojis_deaktivieren() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'emoji_svg_url', '__return_false' );
}
add_action( 'init', 'fusspflege_emojis_deaktivieren' );

/**
 * Nach Theme-Aktivierung Permalinks neu schreiben,
 * damit die URLs des Inhaltstyps "Leistung" (/leistungen/…) sofort funktionieren.
 */
function fusspflege_nach_aktivierung() {
	fusspflege_register_cpt();
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'fusspflege_nach_aktivierung' );
