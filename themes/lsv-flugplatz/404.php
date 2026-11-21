<?php
/**
 * Seite nicht gefunden
 *
 * @package LSV_Flugplatz
 */

get_header();
?>
<section class="lsv-karte keine-treffer">
	<h1 class="seiten-titel">Seite nicht gefunden</h1>
	<p>Diese Seite gibt es leider nicht (mehr). Vielleicht hilft die Suche oder der Weg zurück zur <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Startseite</a>.</p>
	<?php get_search_form(); ?>
</section>
<?php
get_footer();
