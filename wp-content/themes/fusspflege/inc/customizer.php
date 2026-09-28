<?php
/**
 * Customizer: Design → Customizer → Panel "Fußpflege-Theme".
 *
 * Jede Option besteht aus zwei Teilen:
 *  - add_setting(): Speichern + sanitize_callback (Pflicht – nie ungeprüft speichern)
 *  - add_control(): das Eingabefeld in der Oberfläche
 *
 * Gelesen werden die Werte über fusspflege_option() (inc/template-tags.php).
 *
 * @package Fusspflege
 */

defined( 'ABSPATH' ) || exit;

/**
 * Panel, Sections, Settings und Controls anmelden.
 *
 * @param WP_Customize_Manager $wp_customize Customizer-Instanz.
 */
function fusspflege_customize_register( WP_Customize_Manager $wp_customize ) {
	$defaults = fusspflege_defaults();

	$wp_customize->add_panel(
		'fusspflege',
		array(
			'title'    => __( 'Fußpflege-Theme', 'fusspflege' ),
			'priority' => 10,
		)
	);

	$sections = array(
		'fusspflege_praxis'      => __( 'Praxis-Daten', 'fusspflege' ),
		'fusspflege_startseite'  => __( 'Startseite (Texte & Bilder)', 'fusspflege' ),
		'fusspflege_rechtliches' => __( 'Rechtliches & Kontaktformular', 'fusspflege' ),
	);
	foreach ( $sections as $id => $title ) {
		$wp_customize->add_section(
			$id,
			array(
				'title' => $title,
				'panel' => 'fusspflege',
			)
		);
	}

	// Felder: Schlüssel => [ Section, Label, Typ, (optionale Beschreibung) ].
	$felder = array(
		'fusspflege_praxis_name'        => array( 'fusspflege_praxis', __( 'Name der Praxis', 'fusspflege' ), 'text' ),
		'fusspflege_inhaber'            => array( 'fusspflege_praxis', __( 'Inhaber/in (Vor- und Nachname)', 'fusspflege' ), 'text' ),
		'fusspflege_strasse'            => array( 'fusspflege_praxis', __( 'Straße & Hausnummer', 'fusspflege' ), 'text' ),
		'fusspflege_plz'                => array( 'fusspflege_praxis', __( 'PLZ', 'fusspflege' ), 'text' ),
		'fusspflege_ort'                => array( 'fusspflege_praxis', __( 'Ort', 'fusspflege' ), 'text' ),
		'fusspflege_telefon'            => array( 'fusspflege_praxis', __( 'Telefon', 'fusspflege' ), 'text', __( 'So, wie sie angezeigt werden soll, z. B. 02621 123456. Der Anruf-Link wird automatisch erzeugt.', 'fusspflege' ) ),
		'fusspflege_email'              => array( 'fusspflege_praxis', __( 'E-Mail', 'fusspflege' ), 'email' ),
		'fusspflege_oeffnungszeiten'    => array( 'fusspflege_praxis', __( 'Öffnungszeiten', 'fusspflege' ), 'textarea', __( 'Eine Zeile pro Eintrag.', 'fusspflege' ) ),
		'fusspflege_anfahrt'            => array( 'fusspflege_praxis', __( 'Anfahrt', 'fusspflege' ), 'textarea' ),
		'fusspflege_karte_url'          => array( 'fusspflege_praxis', __( 'Link „Route planen“', 'fusspflege' ), 'url', __( 'z. B. OpenStreetMap- oder Google-Maps-Link. Bewusst kein Karten-Embed: so fließen keine Besucherdaten an Dritte (DSGVO).', 'fusspflege' ) ),

		'fusspflege_hero_badge'         => array( 'fusspflege_startseite', __( 'Hero: Badge', 'fusspflege' ), 'text' ),
		'fusspflege_hero_titel'         => array( 'fusspflege_startseite', __( 'Hero: Überschrift', 'fusspflege' ), 'text' ),
		'fusspflege_hero_text'          => array( 'fusspflege_startseite', __( 'Hero: Text', 'fusspflege' ), 'textarea' ),
		'fusspflege_hero_hinweis'       => array( 'fusspflege_startseite', __( 'Hero: Hinweiszeile', 'fusspflege' ), 'text' ),
		'fusspflege_hero_bild'          => array( 'fusspflege_startseite', __( 'Hero: Bild', 'fusspflege' ), 'image' ),
		'fusspflege_leistungen_sub'     => array( 'fusspflege_startseite', __( 'Leistungen: Untertitel', 'fusspflege' ), 'text' ),
		'fusspflege_preise_hinweis'     => array( 'fusspflege_startseite', __( 'Preise: Hinweis unter der Tabelle', 'fusspflege' ), 'text' ),
		'fusspflege_ueber_text'         => array( 'fusspflege_startseite', __( 'Über mich: Text', 'fusspflege' ), 'textarea' ),
		'fusspflege_ueber_punkte'       => array( 'fusspflege_startseite', __( 'Über mich: Stichpunkte', 'fusspflege' ), 'textarea', __( 'Eine Zeile pro Punkt.', 'fusspflege' ) ),
		'fusspflege_ueber_bild'         => array( 'fusspflege_startseite', __( 'Über mich: Bild', 'fusspflege' ), 'image' ),

		'fusspflege_impressum_seite'    => array( 'fusspflege_rechtliches', __( 'Seite „Impressum“', 'fusspflege' ), 'dropdown-pages' ),
		'fusspflege_datenschutz_seite'  => array( 'fusspflege_rechtliches', __( 'Seite „Datenschutz“', 'fusspflege' ), 'dropdown-pages' ),
		'fusspflege_kontakt_empfaenger' => array( 'fusspflege_rechtliches', __( 'Empfänger des Kontaktformulars', 'fusspflege' ), 'email' ),
	);

	// Passende Sanitize-Funktion je Feldtyp (WordPress-Core-Funktionen).
	$sanitize = array(
		'text'           => 'sanitize_text_field',
		'textarea'       => 'sanitize_textarea_field',
		'email'          => 'sanitize_email',
		'url'            => 'esc_url_raw',
		'image'          => 'esc_url_raw',
		'dropdown-pages' => 'absint',
	);

	foreach ( $felder as $key => $feld ) {
		list( $section, $label, $typ ) = $feld;

		$wp_customize->add_setting(
			$key,
			array(
				'default'           => $defaults[ $key ] ?? '',
				'sanitize_callback' => $sanitize[ $typ ],
				'transport'         => 'refresh', // Vorschau lädt neu; 'postMessage' wäre der JS-Weg (siehe docs).
			)
		);

		$args = array(
			'section'     => $section,
			'label'       => $label,
			'type'        => $typ,
			'description' => $feld[3] ?? '',
		);

		if ( 'image' === $typ ) {
			$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, $key, $args ) );
		} else {
			$wp_customize->add_control( $key, $args );
		}
	}
}
add_action( 'customize_register', 'fusspflege_customize_register' );
