<?php
/**
 * Haupt-Template: Beitragsliste (z. B. "Aktuelles"), Archive, Suche
 *
 * @package LSV_Flugplatz
 */

get_header();
?>

<?php if ( is_home() && ! is_front_page() ) : ?>
	<header class="seiten-kopf">
		<h1 class="seiten-titel"><?php single_post_title(); ?></h1>
	</header>
<?php elseif ( is_archive() ) : ?>
	<header class="seiten-kopf">
		<?php the_archive_title( '<h1 class="seiten-titel">', '</h1>' ); ?>
		<?php the_archive_description( '<div class="seiten-beschreibung">', '</div>' ); ?>
	</header>
<?php elseif ( is_search() ) : ?>
	<header class="seiten-kopf">
		<h1 class="seiten-titel">Suche: „<?php echo esc_html( get_search_query() ); ?>“</h1>
		<?php get_search_form(); ?>
	</header>
<?php endif; ?>

<?php if ( have_posts() ) : ?>
	<div class="beitrag-liste">
		<?php
		while ( have_posts() ) :
			the_post();
			get_template_part( 'template-parts/content', 'excerpt' );
		endwhile;
		?>
	</div>
	<?php lsv_pagination(); ?>
<?php else : ?>
	<section class="lsv-karte keine-treffer">
		<h2>Nichts gefunden</h2>
		<p><?php echo is_search() ? 'Zu deiner Suche gibt es keine Treffer. Versuch es mit anderen Begriffen.' : 'Hier gibt es noch keine Inhalte.'; ?></p>
		<?php get_search_form(); ?>
	</section>
<?php endif; ?>

<?php
get_footer();
