<?php
/**
 * Startseite (One-Pager).
 *
 * Template-Hierarchie: front-page.php gewinnt IMMER für die Startseite –
 * egal ob unter Einstellungen → Lesen "Neueste Beiträge" oder eine statische Seite gewählt ist.
 * Jede Sektion liegt als eigene Datei in template-parts/.
 *
 * @package Fusspflege
 */

get_header();

get_template_part( 'template-parts/hero' );
get_template_part( 'template-parts/leistungen' );
get_template_part( 'template-parts/preise' );
get_template_part( 'template-parts/ueber' );
get_template_part( 'template-parts/kontakt' );
get_template_part( 'template-parts/rechtliches' );

get_footer();
