<?php
/**
 * Hauptvorlage (Blog, Archive, Suche)
 *
 * @package Dunkel_Schlicht
 */

get_header();

$dunkel_is_news = is_home();

if ( $dunkel_is_news ) :
	$dunkel_posts_page = (int) get_option( 'page_for_posts' );
	$dunkel_news_title = ! is_front_page() && $dunkel_posts_page ? get_the_title( $dunkel_posts_page ) : '';
	?>
	<h1 class="page-title"><?php echo esc_html( $dunkel_news_title ?: __( 'Aktuelles', 'wx-easy-template' ) ); ?></h1>
	<?php
elseif ( is_archive() ) :
	the_archive_title( '<h1 class="page-title">', '</h1>' );
elseif ( is_search() ) :
	/* translators: %s: Suchbegriff */
	echo '<h1 class="page-title">' . sprintf( esc_html__( 'Suche: %s', 'wx-easy-template' ), esc_html( get_search_query() ) ) . '</h1>';
endif;

if ( have_posts() ) :
	if ( $dunkel_is_news ) :
		?>
		<div class="news-grid">
		<?php
	endif;

	while ( have_posts() ) :
		the_post();
		$dunkel_thumbnail = dunkel_listing_thumbnail();
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( $dunkel_is_news ? 'entry news-card' : 'entry' ); ?>>
			<?php if ( $dunkel_thumbnail ) : ?>
				<a class="entry-thumbnail" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
					<?php echo $dunkel_thumbnail; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
			<?php endif; ?>

			<?php if ( $dunkel_is_news ) : ?>
				<?php $dunkel_post_title = get_the_title() ?: __( 'Beitrag ohne Titel', 'wx-easy-template' ); ?>
				<h2 class="entry-title"><a href="<?php the_permalink(); ?>"><?php echo esc_html( $dunkel_post_title ); ?></a></h2>
			<?php else : ?>
				<?php the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '">', '</a></h2>' ); ?>
			<?php endif; ?>

			<?php if ( 'post' === get_post_type() ) : ?>
				<div class="entry-meta"><time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time></div>
			<?php endif; ?>

			<div class="entry-content"><?php the_excerpt(); ?></div>
			<a class="read-more" href="<?php the_permalink(); ?>">
				<?php esc_html_e( 'Weiterlesen →', 'wx-easy-template' ); ?>
				<?php if ( $dunkel_is_news ) : ?>
					<span class="screen-reader-text"><?php echo esc_html( $dunkel_post_title ); ?></span>
				<?php endif; ?>
			</a>
		</article>
		<?php
	endwhile;

	if ( $dunkel_is_news ) :
		?>
		</div>
		<?php
	endif;

	the_posts_pagination(
		array(
			'prev_text' => '←',
			'next_text' => '→',
		)
	);
else :
	?>
	<?php if ( $dunkel_is_news ) : ?>
		<h2><?php esc_html_e( 'Nichts gefunden', 'wx-easy-template' ); ?></h2>
	<?php else : ?>
		<h1><?php esc_html_e( 'Nichts gefunden', 'wx-easy-template' ); ?></h1>
	<?php endif; ?>
	<p><?php esc_html_e( 'Hier gibt es noch keine Inhalte.', 'wx-easy-template' ); ?></p>
	<?php get_search_form(); ?>
	<?php
endif;

get_footer();
