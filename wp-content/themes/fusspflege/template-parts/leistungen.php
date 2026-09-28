<?php
/**
 * Sektion: Leistungen (Karten aus dem Inhaltstyp "leistung").
 *
 * @package Fusspflege
 */

defined( 'ABSPATH' ) || exit;

$fusspflege_leistungen = fusspflege_get_leistungen();
?>
<section id="leistungen">
	<div class="container">
		<h2><?php esc_html_e( 'Leistungen', 'fusspflege' ); ?></h2>
		<p class="sub"><?php echo esc_html( fusspflege_option( 'fusspflege_leistungen_sub' ) ); ?></p>
		<?php fusspflege_demo_hinweis( $fusspflege_leistungen ); ?>

		<div class="grid cards">
			<?php foreach ( $fusspflege_leistungen as $fusspflege_leistung ) : ?>
				<article class="card">
					<h3>
						<?php if ( $fusspflege_leistung['link'] ) : ?>
							<a href="<?php echo esc_url( $fusspflege_leistung['link'] ); ?>"><?php echo esc_html( $fusspflege_leistung['titel'] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $fusspflege_leistung['titel'] ); ?>
						<?php endif; ?>
					</h3>
					<p class="small"><?php echo esc_html( $fusspflege_leistung['text'] ); ?></p>
					<?php if ( $fusspflege_leistung['dauer'] ) : ?>
						<span class="pill"><?php echo esc_html( $fusspflege_leistung['dauer'] ); ?></span>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>
