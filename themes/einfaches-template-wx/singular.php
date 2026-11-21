<?php
/**
 * Einzelne Beiträge und Seiten
 *
 * @package Dunkel_Schlicht
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry' ); ?>>
		<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>

		<?php if ( 'post' === get_post_type() ) : ?>
			<div class="entry-meta">
				<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
				<?php
				$dunkel_cats = get_the_category_list( ', ' );
				if ( $dunkel_cats ) {
					echo ' · ' . wp_kses_post( $dunkel_cats );
				}
				?>
			</div>
		<?php else : ?>
			<div class="entry-meta" aria-hidden="true"></div>
		<?php endif; ?>

		<?php if ( has_post_thumbnail() && ( 'post' !== get_post_type() || ! dunkel_first_post_image() ) ) : ?>
			<figure class="entry-thumbnail"><?php the_post_thumbnail( 'large' ); ?></figure>
		<?php endif; ?>

		<div class="entry-content">
			<?php
			the_content();
			wp_link_pages(
				array(
					'before' => '<nav class="page-links">' . esc_html__( 'Seiten:', 'dunkel-schlicht' ),
					'after'  => '</nav>',
				)
			);
			?>
		</div>
	</article>
	<?php
	if ( is_singular( 'post' ) ) {
		the_post_navigation(
			array(
				'prev_text' => '← %title',
				'next_text' => '%title →',
			)
		);
	}

	if ( comments_open() || get_comments_number() ) {
		comments_template();
	}
endwhile;

get_footer();
