<?php
/**
 * Index-Template – Pflichtdatei und letzter Fallback der Template-Hierarchie.
 * Rendert Listen: Blog, Archive, Suchergebnisse.
 *
 * @package Fusspflege
 */

get_header();
?>
<section class="listing">
	<div class="container">
		<h1 class="page-title">
			<?php
			if ( is_search() ) {
				/* translators: %s: Suchbegriff */
				printf( esc_html__( 'Suchergebnisse für „%s“', 'fusspflege' ), esc_html( get_search_query() ) );
			} elseif ( is_home() ) {
				esc_html_e( 'Beiträge', 'fusspflege' );
			} else {
				the_archive_title();
			}
			?>
		</h1>

		<?php if ( have_posts() ) : ?>
			<div class="grid cards">
				<?php
				while ( have_posts() ) :
					the_post(); // Setzt den globalen $post – danach funktionieren the_title() & Co.
					?>
					<article <?php post_class( 'card' ); ?>>
						<h2 class="card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<p class="small muted"><?php echo esc_html( get_the_date() ); ?></p>
						<div class="small"><?php the_excerpt(); ?></div>
					</article>
				<?php endwhile; ?>
			</div>
			<?php the_posts_pagination(); ?>
		<?php else : ?>
			<p><?php esc_html_e( 'Keine Beiträge gefunden.', 'fusspflege' ); ?></p>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
