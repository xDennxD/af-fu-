<?php
/**
 * Sektion: Über mich.
 *
 * @package Fusspflege
 */

defined( 'ABSPATH' ) || exit;

$fusspflege_inhaber = fusspflege_option( 'fusspflege_inhaber' );
$fusspflege_punkte  = fusspflege_zeilen( fusspflege_option( 'fusspflege_ueber_punkte' ) );
?>
<section id="ueber">
	<div class="container">
		<h2><?php esc_html_e( 'Über mich', 'fusspflege' ); ?></h2>

		<div class="grid cols-2">
			<div>
				<p>
					<?php if ( $fusspflege_inhaber ) : ?>
						<strong><?php echo esc_html( $fusspflege_inhaber ); ?></strong><br>
					<?php endif; ?>
					<?php echo esc_html( fusspflege_option( 'fusspflege_ueber_text' ) ); ?>
				</p>

				<?php if ( $fusspflege_punkte ) : ?>
					<ul class="small">
						<?php foreach ( $fusspflege_punkte as $fusspflege_punkt ) : ?>
							<li><?php echo esc_html( $fusspflege_punkt ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<div class="card image-card">
				<?php fusspflege_bild( 'fusspflege_ueber_bild', 'placeholder-praxis.svg', __( 'Behandlungsraum der Fußpflegepraxis', 'fusspflege' ) ); ?>
			</div>
		</div>
	</div>
</section>
