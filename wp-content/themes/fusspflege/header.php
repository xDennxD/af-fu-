<?php
/**
 * Kopfbereich: <head> + Navigation.
 * Wird per get_header() am Anfang jedes Templates geladen.
 *
 * @package Fusspflege
 */

?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); // Pflicht: <title>, Styles, Scripts und Plugin-Ausgaben hängen hier. ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); // Pflicht seit WP 5.2, z. B. für Consent-/Tracking-Plugins. ?>
<a class="skip-link" href="#inhalt"><?php esc_html_e( 'Zum Inhalt springen', 'fusspflege' ); ?></a>

<header class="site-header" id="menu">
	<div class="container nav">
		<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php if ( has_custom_logo() ) : ?>
				<?php echo wp_get_attachment_image( (int) get_theme_mod( 'custom_logo' ), 'thumbnail', false, array( 'class' => 'logo-img' ) ); ?>
			<?php else : ?>
				<span class="logo" aria-hidden="true"></span>
			<?php endif; ?>
			<span class="brand-name"><?php echo esc_html( fusspflege_option( 'fusspflege_praxis_name' ) ); ?></span>
		</a>

		<button class="menu-btn" type="button" aria-expanded="false" aria-controls="primary-nav">
			<?php fusspflege_icon( 'menu' ); ?> <?php esc_html_e( 'Menü', 'fusspflege' ); ?>
		</button>

		<nav id="primary-nav" class="primary-nav" aria-label="<?php esc_attr_e( 'Hauptnavigation', 'fusspflege' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'nav-list',
					'fallback_cb'    => 'fusspflege_nav_fallback', // Solange kein Menü zugewiesen ist.
					'depth'          => 1,
				)
			);
			?>
		</nav>
	</div>
</header>

<main id="inhalt">
