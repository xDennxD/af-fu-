<?php
/**
 * Custom Post Type "Leistung" + Meta-Box "Dauer & Preis".
 *
 * Die Praxis pflegt Leistungen wie Beiträge (Backend → Leistungen).
 * Das Theme rendert daraus die Karten (Startseite) und die Preistabelle.
 *
 * @package Fusspflege
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inhaltstyp "leistung" und seine Meta-Felder registrieren. Hook: init.
 */
function fusspflege_register_cpt() {
	register_post_type(
		'leistung',
		array(
			'labels'        => array(
				'name'               => __( 'Leistungen', 'fusspflege' ),
				'singular_name'      => __( 'Leistung', 'fusspflege' ),
				'add_new'            => __( 'Neue Leistung', 'fusspflege' ),
				'add_new_item'       => __( 'Neue Leistung anlegen', 'fusspflege' ),
				'edit_item'          => __( 'Leistung bearbeiten', 'fusspflege' ),
				'all_items'          => __( 'Alle Leistungen', 'fusspflege' ),
				'not_found'          => __( 'Keine Leistungen gefunden.', 'fusspflege' ),
				'not_found_in_trash' => __( 'Keine Leistungen im Papierkorb.', 'fusspflege' ),
			),
			'public'        => true,
			'has_archive'   => false,
			'show_in_rest'  => true, // Block-Editor + REST-API.
			'menu_icon'     => 'dashicons-heart',
			'menu_position' => 5,
			'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ), // page-attributes = Feld "Reihenfolge".
			'rewrite'       => array( 'slug' => 'leistungen' ),
		)
	);

	// Meta-Felder registrieren: Typ, Sanitize, Rechte, REST-Sichtbarkeit.
	foreach ( array( 'fusspflege_dauer', 'fusspflege_preis' ) as $meta ) {
		register_post_meta(
			'leistung',
			$meta,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => static function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
}
add_action( 'init', 'fusspflege_register_cpt' );

/**
 * Meta-Box "Dauer & Preis" in der Seitenleiste des Editors.
 * Hook: add_meta_boxes_{post_type}.
 */
function fusspflege_leistung_metabox() {
	add_meta_box(
		'fusspflege_leistung_details',
		__( 'Dauer & Preis', 'fusspflege' ),
		'fusspflege_leistung_metabox_html',
		'leistung',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes_leistung', 'fusspflege_leistung_metabox' );

/**
 * HTML der Meta-Box.
 *
 * @param WP_Post $post Aktueller Beitrag.
 */
function fusspflege_leistung_metabox_html( WP_Post $post ) {
	wp_nonce_field( 'fusspflege_leistung_speichern', 'fusspflege_leistung_nonce' );

	$dauer = get_post_meta( $post->ID, 'fusspflege_dauer', true );
	$preis = get_post_meta( $post->ID, 'fusspflege_preis', true );
	?>
	<p>
		<label for="fusspflege_dauer"><strong><?php esc_html_e( 'Dauer', 'fusspflege' ); ?></strong></label><br>
		<input type="text" id="fusspflege_dauer" name="fusspflege_dauer" class="widefat"
			value="<?php echo esc_attr( $dauer ); ?>" placeholder="30–45 Min">
	</p>
	<p>
		<label for="fusspflege_preis"><strong><?php esc_html_e( 'Preis', 'fusspflege' ); ?></strong></label><br>
		<input type="text" id="fusspflege_preis" name="fusspflege_preis" class="widefat"
			value="<?php echo esc_attr( $preis ); ?>" placeholder="ab 35 €">
	</p>
	<?php
}

/**
 * Meta-Felder speichern. Hook: save_post_{post_type}.
 *
 * Sicherheits-Checkliste, die JEDER save_post-Handler braucht:
 * Nonce prüfen → Autosave ignorieren → Rechte prüfen → Eingaben bereinigen.
 *
 * @param int $post_id ID des gespeicherten Beitrags.
 */
function fusspflege_leistung_speichern( $post_id ) {
	if ( ! isset( $_POST['fusspflege_leistung_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fusspflege_leistung_nonce'] ) ), 'fusspflege_leistung_speichern' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	foreach ( array( 'fusspflege_dauer', 'fusspflege_preis' ) as $meta ) {
		if ( isset( $_POST[ $meta ] ) ) {
			update_post_meta( $post_id, $meta, sanitize_text_field( wp_unslash( $_POST[ $meta ] ) ) );
		}
	}
}
add_action( 'save_post_leistung', 'fusspflege_leistung_speichern' );

/**
 * Leistungen für die Templates holen.
 *
 * Liefert einheitliche Arrays – aus echten Beiträgen oder, solange keine
 * existieren, aus Demo-Daten (damit das Theme direkt nach Aktivierung komplett aussieht).
 *
 * @return array<int, array{titel:string, text:string, dauer:string, preis:string, link:string, demo:bool}>
 */
function fusspflege_get_leistungen() {
	static $cache = null; // Wird pro Seitenaufruf mehrfach gebraucht (Karten + Tabelle) → nur eine Query.
	if ( null !== $cache ) {
		return $cache;
	}

	$query = new WP_Query(
		array(
			'post_type'      => 'leistung',
			'posts_per_page' => -1,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'no_found_rows'  => true, // Keine Pagination nötig → schnellere Query.
		)
	);

	$cache = array();
	foreach ( $query->posts as $post ) {
		$cache[] = array(
			'titel' => get_the_title( $post ),
			'text'  => has_excerpt( $post )
				? get_the_excerpt( $post )
				: wp_trim_words( wp_strip_all_tags( $post->post_content ), 25 ),
			'dauer' => (string) get_post_meta( $post->ID, 'fusspflege_dauer', true ),
			'preis' => (string) get_post_meta( $post->ID, 'fusspflege_preis', true ),
			'link'  => get_permalink( $post ),
			'demo'  => false,
		);
	}

	if ( ! $cache ) {
		$cache = fusspflege_demo_leistungen();
	}

	return $cache;
}

/**
 * Demo-Leistungen (Inhalt des ursprünglichen HTML-Templates).
 *
 * @return array<int, array{titel:string, text:string, dauer:string, preis:string, link:string, demo:bool}>
 */
function fusspflege_demo_leistungen() {
	$demo = array(
		array( __( 'Medizinische Fußpflege', 'fusspflege' ), __( 'Behandlung von Hühneraugen, eingewachsenen Nägeln, Druckstellen. Beratung zu Orthesen und Pflege.', 'fusspflege' ), '45 Min', 'ab 48 €' ),
		array( __( 'Professionelle Nagelpflege', 'fusspflege' ), __( 'Nagelkorrektur, Kürzen, Polieren. Auf Wunsch Nagelspangen-Systeme bei Fehlstellungen.', 'fusspflege' ), '20–30 Min', 'ab 35 €' ),
		array( __( 'Hornhaut & Fersenrisse', 'fusspflege' ), __( 'Sanfte, gründliche Entfernung übermäßiger Hornhaut. Pflegeplan für zu Hause inklusive.', 'fusspflege' ), '30 Min', 'ab 30 €' ),
		array( __( 'Nagelspange', 'fusspflege' ), __( 'Korrektur eingewachsener oder eingerollter Nägel mit einem Spangensystem.', 'fusspflege' ), __( 'je nach Aufwand', 'fusspflege' ), 'ab 25 €' ),
	);

	return array_map(
		static fn( $l ) => array(
			'titel' => $l[0],
			'text'  => $l[1],
			'dauer' => $l[2],
			'preis' => $l[3],
			'link'  => '',
			'demo'  => true,
		),
		$demo
	);
}

/**
 * Hinweis für eingeloggte Redakteure, solange nur Demo-Leistungen angezeigt werden.
 *
 * @param array $leistungen Ergebnis von fusspflege_get_leistungen().
 */
function fusspflege_demo_hinweis( array $leistungen ) {
	if ( empty( $leistungen[0]['demo'] ) || ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	printf(
		'<p class="form-hinweis form-hinweis--info">%s <a href="%s">%s</a></p>',
		esc_html__( 'Demo-Inhalte (nur für eingeloggte Nutzer sichtbar).', 'fusspflege' ),
		esc_url( admin_url( 'post-new.php?post_type=leistung' ) ),
		esc_html__( 'Erste echte Leistung anlegen →', 'fusspflege' )
	);
}
