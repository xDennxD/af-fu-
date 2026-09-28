<?php
/**
 * Statische Seiten (z. B. Impressum, Datenschutz).
 *
 * @package Fusspflege
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'entry' ); ?>>
		<div class="container narrow">
			<header class="entry-header">
				<h1 class="entry-title"><?php the_title(); ?></h1>
			</header>
			<div class="prose">
				<?php the_content(); ?>
			</div>
			<p><a class="btn ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>">← <?php esc_html_e( 'Zur Startseite', 'fusspflege' ); ?></a></p>
		</div>
	</article>
	<?php
endwhile;

get_footer();
