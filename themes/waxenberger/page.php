<?php
/**
 * Einzelne Seite. Auf der Startseite wird der Seitentitel ausgeblendet
 * (wie im Original: dort steht nur der Text "Herzlich willkommen").
 */
get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'item-page' ); ?>>
		<?php if ( ! is_front_page() ) : ?>
			<h1 class="entry-title"><?php the_title(); ?></h1>
		<?php endif; ?>

		<div class="entry-content">
			<?php
			the_content();
			wp_link_pages();
			?>
		</div>
	</article>
	<?php
endwhile;

get_footer();
