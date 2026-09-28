<?php
/**
 * Sektion: Rechtliches (Impressum/Datenschutz eingeklappt auf der Startseite).
 * Inhalte kommen aus normalen WordPress-Seiten, die im Customizer zugewiesen werden.
 *
 * @package Fusspflege
 */

defined( 'ABSPATH' ) || exit;

$fusspflege_seiten = fusspflege_rechtliche_links();

if ( ! $fusspflege_seiten ) {
	// Nur Admins sehen den Hinweis – Besucher bekommen keine leere Sektion.
	if ( current_user_can( 'edit_theme_options' ) ) {
		printf(
			'<section id="rechtliches"><div class="container"><p class="form-hinweis form-hinweis--info">%s <a href="%s">%s</a></p></div></section>',
			esc_html__( 'Impressum und Datenschutz sind Pflicht: Seiten anlegen und im Customizer zuweisen.', 'fusspflege' ),
			esc_url( admin_url( 'customize.php?autofocus[section]=fusspflege_rechtliches' ) ),
			esc_html__( 'Zum Customizer →', 'fusspflege' )
		);
	}
	return;
}
?>
<section id="rechtliches">
	<div class="container">
		<h2><?php esc_html_e( 'Rechtliches', 'fusspflege' ); ?></h2>

		<?php foreach ( $fusspflege_seiten as $fusspflege_seite ) : ?>
			<details>
				<summary><strong><?php echo esc_html( $fusspflege_seite['label'] ); ?></strong></summary>
				<div class="prose small">
					<?php
					// Seiteninhalt durch die Content-Filter jagen (Absätze, Blöcke, Shortcodes) und escapen.
					echo wp_kses_post( apply_filters( 'the_content', get_post_field( 'post_content', $fusspflege_seite['id'] ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
					?>
				</div>
				<p class="small"><a href="<?php echo esc_url( $fusspflege_seite['url'] ); ?>"><?php esc_html_e( 'Als eigene Seite öffnen →', 'fusspflege' ); ?></a></p>
			</details>
		<?php endforeach; ?>
	</div>
</section>
