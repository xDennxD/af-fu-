<?php
/**
 * Fußbereich. Wird per get_footer() am Ende jedes Templates geladen.
 *
 * @package Fusspflege
 */

$fusspflege_name    = fusspflege_option( 'fusspflege_praxis_name' );
$fusspflege_telefon = fusspflege_option( 'fusspflege_telefon' );
$fusspflege_email   = fusspflege_option( 'fusspflege_email' );
?>
</main>

<footer class="site-footer">
	<div class="container footer-grid">
		<div>
			<div class="brand"><span class="logo" aria-hidden="true"></span> <?php echo esc_html( $fusspflege_name ); ?></div>
			<p class="small">
				© <?php echo esc_html( date_i18n( 'Y' ) ); ?> <?php echo esc_html( $fusspflege_name ); ?>.
				<?php esc_html_e( 'Alle Rechte vorbehalten.', 'fusspflege' ); ?>
			</p>
		</div>
		<div>
			<p class="small">
				<strong><?php esc_html_e( 'Kontakt', 'fusspflege' ); ?></strong><br>
				<?php if ( $fusspflege_telefon ) : ?>
					<a href="<?php echo esc_url( fusspflege_telefon_link( $fusspflege_telefon ) ); ?>"><?php echo esc_html( $fusspflege_telefon ); ?></a><br>
				<?php endif; ?>
				<?php if ( $fusspflege_email ) : ?>
					<a href="mailto:<?php echo esc_attr( $fusspflege_email ); ?>"><?php echo esc_html( $fusspflege_email ); ?></a>
				<?php endif; ?>
			</p>
		</div>
		<div>
			<p class="small">
				<strong><?php esc_html_e( 'Adresse', 'fusspflege' ); ?></strong><br>
				<?php echo wp_kses( fusspflege_adresse_html(), array( 'br' => array() ) ); ?>
			</p>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer',
					'container'      => false,
					'menu_class'     => 'footer-nav',
					'fallback_cb'    => 'fusspflege_footer_nav_fallback', // Impressum/Datenschutz aus dem Customizer.
					'depth'          => 1,
				)
			);
			?>
		</div>
	</div>
</footer>

<?php wp_footer(); // Pflicht: Scripts, Admin-Bar, Plugin-Ausgaben. ?>
</body>
</html>
