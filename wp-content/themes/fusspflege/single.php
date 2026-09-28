<?php
/**
 * Einzelansicht für Beiträge UND Leistungen (Custom Post Type "leistung").
 * Hierarchie: single-leistung.php würde single.php für Leistungen übersteuern – hier reicht eine Datei.
 *
 * @package Fusspflege
 */

get_header();

while ( have_posts() ) :
	the_post();
	$fusspflege_ist_leistung = 'leistung' === get_post_type();
	?>
	<article <?php post_class( 'entry' ); ?>>
		<div class="container narrow">
			<header class="entry-header">
				<h1 class="entry-title"><?php the_title(); ?></h1>

				<?php if ( $fusspflege_ist_leistung ) : ?>
					<p class="pills">
						<?php foreach ( array( 'fusspflege_dauer', 'fusspflege_preis' ) as $fusspflege_meta ) : ?>
							<?php $fusspflege_wert = get_post_meta( get_the_ID(), $fusspflege_meta, true ); ?>
							<?php if ( $fusspflege_wert ) : ?>
								<span class="pill"><?php echo esc_html( $fusspflege_wert ); ?></span>
							<?php endif; ?>
						<?php endforeach; ?>
					</p>
				<?php else : ?>
					<p class="small muted"><?php echo esc_html( get_the_date() ); ?></p>
				<?php endif; ?>
			</header>

			<?php if ( has_post_thumbnail() ) : ?>
				<div class="card image-card"><?php the_post_thumbnail( 'large' ); ?></div>
			<?php endif; ?>

			<div class="prose">
				<?php the_content(); ?>
			</div>

			<p>
				<a class="btn ghost" href="<?php echo esc_url( home_url( $fusspflege_ist_leistung ? '/#leistungen' : '/' ) ); ?>">
					← <?php $fusspflege_ist_leistung ? esc_html_e( 'Alle Leistungen', 'fusspflege' ) : esc_html_e( 'Zur Startseite', 'fusspflege' ); ?>
				</a>
			</p>
		</div>
	</article>
	<?php
endwhile;

get_footer();
