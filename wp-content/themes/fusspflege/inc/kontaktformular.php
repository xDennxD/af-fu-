<?php
/**
 * Natives Kontaktformular – ohne Plugin.
 *
 * Ablauf:
 *  1. Formular (template-parts/kontakt.php) sendet per POST an /wp-admin/admin-post.php
 *  2. WordPress ruft anhand des Feldes action="fusspflege_kontakt" den Handler hier auf
 *  3. Handler prüft, sendet die Mail und leitet zurück zur Startseite (?kontakt=ok#kontakt)
 *
 * @package Fusspflege
 */

defined( 'ABSPATH' ) || exit;

/**
 * Formular verarbeiten.
 * Hooks: admin_post_nopriv_{action} (nicht eingeloggt) und admin_post_{action} (eingeloggt).
 */
function fusspflege_kontakt_verarbeiten() {
	$zurueck = static function ( $status ) {
		wp_safe_redirect( add_query_arg( 'kontakt', $status, home_url( '/' ) ) . '#kontakt' );
		exit;
	};

	// 1) Nonce: Schutz gegen CSRF – das Formular muss von unserer Seite stammen.
	if ( ! isset( $_POST['fusspflege_kontakt_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fusspflege_kontakt_nonce'] ) ), 'fusspflege_kontakt' ) ) {
		$zurueck( 'fehler' );
	}

	// 2) Honeypot: unsichtbares Feld – Menschen lassen es leer, Spam-Bots füllen es aus.
	if ( ! empty( $_POST['fusspflege_website'] ) ) {
		$zurueck( 'ok' ); // Bot bekommt "Erfolg", Mail wird nicht gesendet.
	}

	// 3) Rate-Limit: max. 1 Anfrage pro Minute je IP. Transient = Wert mit Ablaufzeit.
	// Die IP wird nur gehasht gespeichert (Datensparsamkeit, DSGVO).
	$ip_hash = md5( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ) . wp_salt() );
	if ( get_transient( 'fusspflege_kontakt_' . $ip_hash ) ) {
		$zurueck( 'warten' );
	}

	// 4) Eingaben bereinigen + prüfen. Regel: $_POST nie ungeprüft verwenden.
	$name         = isset( $_POST['fusspflege_name'] ) ? sanitize_text_field( wp_unslash( $_POST['fusspflege_name'] ) ) : '';
	$email        = isset( $_POST['fusspflege_email'] ) ? sanitize_email( wp_unslash( $_POST['fusspflege_email'] ) ) : '';
	$nachricht    = isset( $_POST['fusspflege_nachricht'] ) ? sanitize_textarea_field( wp_unslash( $_POST['fusspflege_nachricht'] ) ) : '';
	$einwilligung = ! empty( $_POST['fusspflege_datenschutz'] );

	if ( '' === $name || ! is_email( $email ) || '' === $nachricht || ! $einwilligung ) {
		$zurueck( 'unvollstaendig' );
	}

	// 5) Mail senden. wp_mail() nutzt PHP mail() – auf Live-Servern besser per SMTP-Plugin (siehe docs).
	$empfaenger = fusspflege_option( 'fusspflege_kontakt_empfaenger' );
	if ( ! is_email( $empfaenger ) ) {
		$empfaenger = get_option( 'admin_email' );
	}

	/* translators: %s: Name des Absenders */
	$betreff = sprintf( __( 'Anfrage über die Website von %s', 'fusspflege' ), $name );
	$text    = sprintf(
		"%s\n\nName: %s\nE-Mail: %s\n\n%s\n",
		__( 'Neue Anfrage über das Kontaktformular:', 'fusspflege' ),
		$name,
		$email,
		$nachricht
	);
	$headers = array( 'Reply-To: ' . $name . ' <' . $email . '>' ); // Antworten geht direkt an den Absender.

	$gesendet = wp_mail( $empfaenger, $betreff, $text, $headers );

	set_transient( 'fusspflege_kontakt_' . $ip_hash, 1, MINUTE_IN_SECONDS );
	$zurueck( $gesendet ? 'ok' : 'fehler' );
}
add_action( 'admin_post_nopriv_fusspflege_kontakt', 'fusspflege_kontakt_verarbeiten' );
add_action( 'admin_post_fusspflege_kontakt', 'fusspflege_kontakt_verarbeiten' );

/**
 * Statusmeldung nach dem Absenden ausgeben (liest ?kontakt=… aus der URL).
 */
function fusspflege_kontakt_hinweis() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nur Anzeige einer Meldung, keine Aktion.
	$status = isset( $_GET['kontakt'] ) ? sanitize_key( $_GET['kontakt'] ) : '';

	$meldungen = array(
		'ok'             => array( 'ok', __( 'Danke! Ihre Anfrage wurde gesendet. Wir melden uns schnellstmöglich.', 'fusspflege' ) ),
		'unvollstaendig' => array( 'fehler', __( 'Bitte alle Felder ausfüllen und der Datenverarbeitung zustimmen.', 'fusspflege' ) ),
		'warten'         => array( 'fehler', __( 'Bitte warten Sie eine Minute, bevor Sie erneut senden.', 'fusspflege' ) ),
		'fehler'         => array( 'fehler', __( 'Senden fehlgeschlagen. Bitte rufen Sie uns an oder schreiben Sie eine E-Mail.', 'fusspflege' ) ),
	);

	if ( isset( $meldungen[ $status ] ) ) {
		printf(
			'<p class="form-hinweis form-hinweis--%s" role="status">%s</p>',
			esc_attr( $meldungen[ $status ][0] ),
			esc_html( $meldungen[ $status ][1] )
		);
	}
}
