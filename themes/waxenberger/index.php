<?php
/**
 * Fallback-Vorlage: Beiträge, Archive, Suche.
 */
get_header();
?>

<div class="post-list">
	<?php if ( have_posts() ) : ?>

		<?php while ( have_posts() ) : the_post(); ?>
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<?php if ( is_singular() ) : ?>
					<h1 class="entry-title"><?php the_title(); ?></h1>
				<?php else : ?>
					<h2 class="entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
				<?php endif; ?>

				<?php if ( 'post' === get_post_type() ) : ?>
					<div class="entry-meta"><?php echo esc_html( get_the_date() ); ?></div>
				<?php endif; ?>

				<div class="entry-content">
					<?php is_singular() ? the_content() : the_excerpt(); ?>
				</div>
			</article>
		<?php endwhile; ?>

		<div class="pagination"><?php echo paginate_links(); ?></div>

	<?php else : ?>
		<article>
			<h1 class="entry-title"><?php esc_html_e( 'Nichts gefunden', 'waxenberger' ); ?></h1>
			<div class="entry-content"><p><?php esc_html_e( 'Die gesuchte Seite gibt es leider nicht.', 'waxenberger' ); ?></p></div>
		</article>
	<?php endif; ?>
</div>

<?php
get_footer();
