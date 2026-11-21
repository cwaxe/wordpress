<?php
/**
 * Seiten
 *
 * @package LSV_Flugplatz
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<?php if ( ! is_front_page() ) : ?>
			<header class="seiten-kopf">
				<?php the_title( '<h1 class="seiten-titel">', '</h1>' ); ?>
			</header>
		<?php endif; ?>

		<?php if ( has_post_thumbnail() && ! is_front_page() ) : ?>
			<figure class="seiten-bild alignwide"><?php the_post_thumbnail(); ?></figure>
		<?php endif; ?>

		<div class="entry-content">
			<?php
			the_content();
			wp_link_pages( array( 'before' => '<nav class="seiten-links">Seiten: ', 'after' => '</nav>' ) );
			?>
		</div>

		<?php if ( get_edit_post_link() ) : ?>
			<footer class="seiten-fuss"><?php edit_post_link( 'Bearbeiten' ); ?></footer>
		<?php endif; ?>
	</article>

	<?php
	if ( comments_open() || get_comments_number() ) {
		comments_template();
	}
endwhile;

get_footer();
