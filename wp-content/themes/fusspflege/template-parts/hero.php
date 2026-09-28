<?php
/**
 * Sektion: Hero (Einstieg mit Überschrift, Text, Call-to-Action, Bild).
 *
 * @package Fusspflege
 */

defined( 'ABSPATH' ) || exit;

$fusspflege_telefon = fusspflege_option( 'fusspflege_telefon' );
$fusspflege_hinweis = fusspflege_option( 'fusspflege_hero_hinweis' );
?>
<section class="hero" id="start">
	<div class="container hero-grid">
		<div>
			<p class="badge"><?php fusspflege_icon( 'star' ); ?> <span><?php echo esc_html( fusspflege_option( 'fusspflege_hero_badge' ) ); ?></span></p>
			<h1><?php echo esc_html( fusspflege_option( 'fusspflege_hero_titel' ) ); ?></h1>
			<p class="lead"><?php echo esc_html( fusspflege_option( 'fusspflege_hero_text' ) ); ?></p>

			<div class="cta">
				<?php if ( $fusspflege_telefon ) : ?>
					<a class="btn primary" href="<?php echo esc_url( fusspflege_telefon_link( $fusspflege_telefon ) ); ?>"><?php fusspflege_icon( 'phone' ); ?> <?php esc_html_e( 'Termin buchen', 'fusspflege' ); ?></a>
				<?php else : ?>
					<a class="btn primary" href="#kontakt"><?php fusspflege_icon( 'phone' ); ?> <?php esc_html_e( 'Termin anfragen', 'fusspflege' ); ?></a>
				<?php endif; ?>
				<a class="btn ghost" href="#leistungen"><?php esc_html_e( 'Mehr erfahren', 'fusspflege' ); ?></a>
			</div>

			<?php if ( $fusspflege_hinweis ) : ?>
				<p class="tick"><?php fusspflege_icon( 'check' ); ?> <span><?php echo esc_html( $fusspflege_hinweis ); ?></span></p>
			<?php endif; ?>
		</div>

		<div class="hero-card">
			<?php fusspflege_bild( 'fusspflege_hero_bild', 'placeholder-hero.svg', __( 'Gepflegte Füße auf weichem Handtuch', 'fusspflege' ) ); ?>
		</div>
	</div>
</section>
