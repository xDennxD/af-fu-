<?php
/**
 * Fehlerseite "Nicht gefunden".
 *
 * @package Fusspflege
 */

get_header();
?>
<section class="entry">
	<div class="container narrow">
		<h1 class="entry-title"><?php esc_html_e( 'Seite nicht gefunden', 'fusspflege' ); ?></h1>
		<p class="lead"><?php esc_html_e( 'Die aufgerufene Adresse existiert nicht (mehr).', 'fusspflege' ); ?></p>
		<p><a class="btn primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Zur Startseite', 'fusspflege' ); ?></a></p>
	</div>
</section>
<?php
get_footer();
