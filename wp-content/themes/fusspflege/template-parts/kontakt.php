<?php
/**
 * Sektion: Kontakt & Termin (Formular + Praxisdaten).
 * Die Verarbeitung des Formulars liegt in inc/kontaktformular.php.
 *
 * @package Fusspflege
 */

defined( 'ABSPATH' ) || exit;

$fusspflege_telefon     = fusspflege_option( 'fusspflege_telefon' );
$fusspflege_email       = fusspflege_option( 'fusspflege_email' );
$fusspflege_zeiten      = fusspflege_zeilen( fusspflege_option( 'fusspflege_oeffnungszeiten' ) );
$fusspflege_anfahrt     = fusspflege_option( 'fusspflege_anfahrt' );
$fusspflege_karte       = fusspflege_option( 'fusspflege_karte_url' );
$fusspflege_datenschutz = (int) fusspflege_option( 'fusspflege_datenschutz_seite' );
?>
<section id="kontakt">
	<div class="container">
		<h2><?php esc_html_e( 'Kontakt & Termin', 'fusspflege' ); ?></h2>
		<p class="sub"><?php esc_html_e( 'So erreichen Sie uns.', 'fusspflege' ); ?></p>

		<div class="grid cols-2">
			<div class="card">
				<?php fusspflege_kontakt_hinweis(); ?>

				<form class="kontakt-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="fusspflege_kontakt">
					<?php wp_nonce_field( 'fusspflege_kontakt', 'fusspflege_kontakt_nonce' ); ?>

					<?php // Honeypot: für Menschen unsichtbar (CSS), Bots füllen es aus → Anfrage wird verworfen. ?>
					<p class="fp-hp" aria-hidden="true">
						<label>Website <input type="text" name="fusspflege_website" tabindex="-1" autocomplete="off"></label>
					</p>

					<label for="fusspflege_name"><?php esc_html_e( 'Name', 'fusspflege' ); ?></label>
					<input id="fusspflege_name" name="fusspflege_name" type="text" placeholder="Max Mustermann" autocomplete="name" required>

					<label for="fusspflege_email"><?php esc_html_e( 'E-Mail', 'fusspflege' ); ?></label>
					<input id="fusspflege_email" name="fusspflege_email" type="email" placeholder="max@example.com" autocomplete="email" required>

					<label for="fusspflege_nachricht"><?php esc_html_e( 'Nachricht', 'fusspflege' ); ?></label>
					<textarea id="fusspflege_nachricht" name="fusspflege_nachricht" placeholder="<?php esc_attr_e( 'Ich hätte gern einen Termin am …', 'fusspflege' ); ?>" required></textarea>

					<label class="check">
						<input type="checkbox" name="fusspflege_datenschutz" value="1" required>
						<span>
							<?php esc_html_e( 'Ich stimme zu, dass meine Angaben zur Bearbeitung der Anfrage verarbeitet werden.', 'fusspflege' ); ?>
							<?php if ( $fusspflege_datenschutz ) : ?>
								<a href="<?php echo esc_url( get_permalink( $fusspflege_datenschutz ) ); ?>"><?php esc_html_e( 'Datenschutzerklärung', 'fusspflege' ); ?></a>
							<?php endif; ?>
						</span>
					</label>

					<button class="btn primary" type="submit"><?php esc_html_e( 'Anfrage senden', 'fusspflege' ); ?></button>
				</form>
			</div>

			<div class="card">
				<?php if ( fusspflege_adresse_html() ) : ?>
					<p class="small muted"><?php esc_html_e( 'Adresse', 'fusspflege' ); ?></p>
					<p><strong><?php echo wp_kses( fusspflege_adresse_html(), array( 'br' => array() ) ); ?></strong></p>
				<?php endif; ?>

				<?php if ( $fusspflege_telefon ) : ?>
					<p class="small muted"><?php esc_html_e( 'Telefon', 'fusspflege' ); ?></p>
					<p><a href="<?php echo esc_url( fusspflege_telefon_link( $fusspflege_telefon ) ); ?>"><strong><?php echo esc_html( $fusspflege_telefon ); ?></strong></a></p>
				<?php endif; ?>

				<?php if ( $fusspflege_email ) : ?>
					<p class="small muted"><?php esc_html_e( 'E-Mail', 'fusspflege' ); ?></p>
					<p><a href="mailto:<?php echo esc_attr( $fusspflege_email ); ?>"><?php echo esc_html( $fusspflege_email ); ?></a></p>
				<?php endif; ?>

				<?php if ( $fusspflege_zeiten ) : ?>
					<p class="small muted"><?php esc_html_e( 'Öffnungszeiten', 'fusspflege' ); ?></p>
					<ul class="small plain">
						<?php foreach ( $fusspflege_zeiten as $fusspflege_zeit ) : ?>
							<li><?php echo esc_html( $fusspflege_zeit ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php if ( $fusspflege_anfahrt ) : ?>
					<p class="small muted"><?php esc_html_e( 'Anfahrt', 'fusspflege' ); ?></p>
					<p class="small"><?php echo esc_html( $fusspflege_anfahrt ); ?></p>
				<?php endif; ?>

				<?php if ( $fusspflege_karte ) : ?>
					<p><a class="btn ghost" href="<?php echo esc_url( $fusspflege_karte ); ?>" target="_blank" rel="noopener noreferrer"><?php fusspflege_icon( 'map' ); ?> <?php esc_html_e( 'Route planen', 'fusspflege' ); ?></a></p>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
