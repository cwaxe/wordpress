<?php
/**
 * Einzelner Beitrag
 *
 * @package LSV_Flugplatz
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<header class="seiten-kopf">
			<?php the_title( '<h1 class="seiten-titel">', '</h1>' ); ?>
			<?php lsv_beitrag_meta(); ?>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="seiten-bild alignwide"><?php the_post_thumbnail(); ?></figure>
		<?php endif; ?>

		<div class="entry-content">
			<?php
			the_content();
			wp_link_pages( array( 'before' => '<nav class="seiten-links">Seiten: ', 'after' => '</nav>' ) );
			?>
		</div>

		<footer class="seiten-fuss">
			<?php
			$tags = get_the_tag_list( '', ', ' );
			if ( $tags ) {
				echo '<div class="lsv-schlagworte">Schlagworte: ' . $tags . '</div>'; // phpcs:ignore
			}
			edit_post_link( 'Bearbeiten' );
			?>
		</footer>
	</article>

	<?php
	the_post_navigation(
		array(
			'prev_text' => '<span class="nav-klein">← Vorheriger Beitrag</span><span class="nav-titel">%title</span>',
			'next_text' => '<span class="nav-klein">Nächster Beitrag →</span><span class="nav-titel">%title</span>',
		)
	);

	if ( comments_open() || get_comments_number() ) {
		comments_template();
	}
endwhile;

get_footer();
