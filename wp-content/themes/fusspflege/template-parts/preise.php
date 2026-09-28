<?php
/**
 * Sektion: Preistabelle (gleiche Datenquelle wie die Leistungskarten).
 *
 * @package Fusspflege
 */

defined( 'ABSPATH' ) || exit;

$fusspflege_leistungen = fusspflege_get_leistungen();
?>
<section id="preise">
	<div class="container">
		<h2><?php esc_html_e( 'Preise', 'fusspflege' ); ?></h2>

		<div class="table-wrap">
			<table aria-label="<?php esc_attr_e( 'Preisliste', 'fusspflege' ); ?>">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Leistung', 'fusspflege' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Dauer', 'fusspflege' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Preis', 'fusspflege' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $fusspflege_leistungen as $fusspflege_leistung ) : ?>
						<tr>
							<td><?php echo esc_html( $fusspflege_leistung['titel'] ); ?></td>
							<td><?php echo esc_html( '' !== $fusspflege_leistung['dauer'] ? $fusspflege_leistung['dauer'] : '–' ); ?></td>
							<td><?php echo esc_html( '' !== $fusspflege_leistung['preis'] ? $fusspflege_leistung['preis'] : __( 'auf Anfrage', 'fusspflege' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<p class="small muted"><?php echo esc_html( fusspflege_option( 'fusspflege_preise_hinweis' ) ); ?></p>
	</div>
</section>
