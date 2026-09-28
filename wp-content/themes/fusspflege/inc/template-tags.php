<?php
/**
 * Hilfsfunktionen für Templates ("Template Tags").
 *
 * Regel: Alles, was Daten holt oder formatiert, gehört hierher.
 * Die Templates selbst bleiben dadurch schlank und lesbar.
 *
 * @package Fusspflege
 */

defined( 'ABSPATH' ) || exit;

/**
 * Standardwerte aller Customizer-Optionen an EINER Stelle.
 * Genutzt von fusspflege_option() und vom Customizer (inc/customizer.php).
 *
 * @return array<string, mixed>
 */
function fusspflege_defaults() {
	return array(
		// Praxis-Daten.
		'fusspflege_praxis_name'        => __( 'Fußpflegepraxis', 'fusspflege' ),
		'fusspflege_inhaber'            => '',
		'fusspflege_strasse'            => '',
		'fusspflege_plz'                => '',
		'fusspflege_ort'                => 'Niederlahnstein',
		'fusspflege_telefon'            => '',
		'fusspflege_email'              => '',
		'fusspflege_oeffnungszeiten'    => "Mo–Fr: 09:00–18:00\nSa: nach Vereinbarung",
		'fusspflege_anfahrt'            => __( 'Parkplätze vor Ort.', 'fusspflege' ),
		'fusspflege_karte_url'          => '',

		// Startseite.
		'fusspflege_hero_badge'         => __( 'Medizinische & kosmetische Fußpflege in Niederlahnstein', 'fusspflege' ),
		'fusspflege_hero_titel'         => __( 'Gesunde, gepflegte Füße. Termin einfach per Klick sichern.', 'fusspflege' ),
		'fusspflege_hero_text'          => __( 'Behandlungen bei Hornhaut, Hühneraugen, eingewachsenen Nägeln und schmerzenden Druckstellen. Hygienisch, einfühlsam, zertifiziert.', 'fusspflege' ),
		'fusspflege_hero_hinweis'       => __( 'Bar & EC-Karte • Barrierearmer Zugang • Kostenlose Parkplätze', 'fusspflege' ),
		'fusspflege_hero_bild'          => '',
		'fusspflege_leistungen_sub'     => __( 'Sorgfältig. Steril. Schmerzarm.', 'fusspflege' ),
		'fusspflege_preise_hinweis'     => __( 'Alle Preise inkl. MwSt. Hausbesuche auf Anfrage.', 'fusspflege' ),
		'fusspflege_ueber_text'         => __( 'Ich arbeite nach strengen Hygiene-Standards, nehme mir Zeit und erkläre jeden Schritt. Ziel: schnelle Linderung und dauerhafte Pflegeerfolge.', 'fusspflege' ),
		'fusspflege_ueber_punkte'       => "Hygiene: Instrumente im Autoklav sterilisiert\nQualität: Regelmäßige Fortbildungen\nEinfühlsam: Schmerzarm, transparent, respektvoll",
		'fusspflege_ueber_bild'         => '',

		// Rechtliches & Formular.
		'fusspflege_impressum_seite'    => 0,
		'fusspflege_datenschutz_seite'  => 0,
		'fusspflege_kontakt_empfaenger' => get_option( 'admin_email' ),
	);
}

/**
 * Customizer-Option lesen (mit Standardwert).
 *
 * @param string $key Options-Schlüssel, z. B. 'fusspflege_telefon'.
 * @return mixed
 */
function fusspflege_option( $key ) {
	$defaults = fusspflege_defaults();
	return get_theme_mod( $key, $defaults[ $key ] ?? '' );
}

/**
 * Textarea-Inhalt in Zeilen aufteilen (leere Zeilen entfernen).
 *
 * @param string $text Mehrzeiliger Text.
 * @return string[]
 */
function fusspflege_zeilen( $text ) {
	$zeilen = preg_split( '/\r\n|\r|\n/', (string) $text );
	return array_values( array_filter( array_map( 'trim', $zeilen ) ) );
}

/**
 * Telefonnummer als tel:-Link ("02621 123456" → "tel:+492621123456").
 *
 * @param string $telefon Nummer, wie sie angezeigt wird.
 * @return string Leer, wenn keine Nummer hinterlegt ist.
 */
function fusspflege_telefon_link( $telefon ) {
	$nummer = preg_replace( '/[^0-9+]/', '', (string) $telefon );

	if ( '' === $nummer ) {
		return '';
	}
	if ( str_starts_with( $nummer, '00' ) ) {
		$nummer = '+' . substr( $nummer, 2 );
	} elseif ( str_starts_with( $nummer, '0' ) ) {
		$nummer = '+49' . substr( $nummer, 1 );
	}

	return 'tel:' . $nummer;
}

/**
 * Bild aus dem Customizer ausgeben.
 * Kommt das Bild aus der Mediathek, liefert WordPress srcset + Alt-Text automatisch.
 * Ohne Bild wird ein Platzhalter-SVG aus dem Theme gezeigt (keine externen Requests).
 *
 * @param string $key      Customizer-Schlüssel (speichert eine URL).
 * @param string $fallback Dateiname in assets/img/.
 * @param string $alt      Alt-Text für den Platzhalter.
 */
function fusspflege_bild( $key, $fallback, $alt ) {
	$url = fusspflege_option( $key );

	if ( $url ) {
		$id = attachment_url_to_postid( $url );
		if ( $id ) {
			$html = wp_get_attachment_image( $id, 'large', false, array( 'loading' => 'lazy' ) );
			if ( $html ) {
				echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() escaped bereits.
				return;
			}
		}
		printf( '<img src="%s" alt="%s" loading="lazy">', esc_url( $url ), esc_attr( $alt ) );
		return;
	}

	printf(
		'<img src="%s" alt="%s" loading="lazy" width="960" height="640">',
		esc_url( FUSSPFLEGE_URI . '/assets/img/' . $fallback ),
		esc_attr( $alt )
	);
}

/**
 * Inline-SVG-Icon ausgeben.
 * Statt Emojis: sieht auf jedem Gerät gleich aus und braucht keine externen Bilder
 * (WordPress' Emoji-Loader würde sonst Grafiken von s.w.org nachladen).
 *
 * @param string $name Icon-Name: phone, check, star, map oder menu.
 */
function fusspflege_icon( $name ) {
	$icons = array(
		'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
		'check' => '<path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"/><path d="M22 4 12 14l-3-3"/>',
		'star'  => '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>',
		'map'   => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
		'menu'  => '<path d="M3 6h18M3 12h18M3 18h18"/>',
	);

	if ( ! isset( $icons[ $name ] ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Statisches SVG aus dem Theme, keine Nutzereingabe.
	echo '<svg class="icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">' . $icons[ $name ] . '</svg>';
}

/**
 * Adresse als HTML (Straße<br>PLZ Ort). Leer, wenn nichts eingetragen ist.
 *
 * @return string Bereits escaped; nur <br> enthalten.
 */
function fusspflege_adresse_html() {
	$strasse = fusspflege_option( 'fusspflege_strasse' );
	$plz_ort = trim( fusspflege_option( 'fusspflege_plz' ) . ' ' . fusspflege_option( 'fusspflege_ort' ) );
	$teile   = array_filter( array( $strasse, $plz_ort ) );

	return implode( '<br>', array_map( 'esc_html', $teile ) );
}

/**
 * Zugewiesene Rechtsseiten (Impressum, Datenschutz) aus dem Customizer.
 *
 * @return array<int, array{id:int, label:string, url:string}>
 */
function fusspflege_rechtliche_links() {
	$links = array();

	foreach ( array( 'fusspflege_impressum_seite', 'fusspflege_datenschutz_seite' ) as $key ) {
		$id = (int) fusspflege_option( $key );
		if ( $id && 'publish' === get_post_status( $id ) ) {
			$links[] = array(
				'id'    => $id,
				'label' => get_the_title( $id ),
				'url'   => get_permalink( $id ),
			);
		}
	}

	return $links;
}

/**
 * Fallback-Hauptmenü, solange unter Design → Menüs nichts zugewiesen ist:
 * Ankerlinks zu den Sektionen der Startseite.
 */
function fusspflege_nav_fallback() {
	$punkte = array(
		'leistungen'  => __( 'Leistungen', 'fusspflege' ),
		'preise'      => __( 'Preise', 'fusspflege' ),
		'ueber'       => __( 'Über mich', 'fusspflege' ),
		'kontakt'     => __( 'Kontakt', 'fusspflege' ),
		'rechtliches' => __( 'Rechtliches', 'fusspflege' ),
	);

	echo '<ul class="nav-list">';
	foreach ( $punkte as $anker => $label ) {
		printf(
			'<li class="menu-item"><a href="%s">%s</a></li>',
			esc_url( home_url( '/#' . $anker ) ),
			esc_html( $label )
		);
	}
	echo '</ul>';
}

/**
 * Fallback-Footermenü: Links zu Impressum/Datenschutz aus dem Customizer.
 */
function fusspflege_footer_nav_fallback() {
	$links = fusspflege_rechtliche_links();
	if ( ! $links ) {
		return;
	}

	echo '<ul class="footer-nav">';
	foreach ( $links as $link ) {
		printf( '<li><a href="%s">%s</a></li>', esc_url( $link['url'] ), esc_html( $link['label'] ) );
	}
	echo '</ul>';
}

/**
 * Strukturierte Daten (schema.org LocalBusiness) für Google – nur auf der Startseite.
 * Hook: wp_head.
 */
function fusspflege_json_ld() {
	if ( ! is_front_page() ) {
		return;
	}

	$adresse = array_filter(
		array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => fusspflege_option( 'fusspflege_strasse' ),
			'postalCode'      => fusspflege_option( 'fusspflege_plz' ),
			'addressLocality' => fusspflege_option( 'fusspflege_ort' ),
			'addressCountry'  => 'DE',
		)
	);

	$daten            = array_filter(
		array(
			'@context'   => 'https://schema.org',
			'@type'      => 'LocalBusiness',
			'name'       => fusspflege_option( 'fusspflege_praxis_name' ),
			'url'        => home_url( '/' ),
			'telephone'  => fusspflege_option( 'fusspflege_telefon' ),
			'email'      => fusspflege_option( 'fusspflege_email' ),
			'image'      => fusspflege_option( 'fusspflege_hero_bild' ),
			'priceRange' => '€€',
		)
	);
	$daten['address'] = $adresse;

	// Kein JSON_UNESCAPED_SLASHES: "</" bleibt so als "<\/" harmlos innerhalb von <script>.
	echo '<script type="application/ld+json">' . wp_json_encode( $daten, JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
}
add_action( 'wp_head', 'fusspflege_json_ld' );
