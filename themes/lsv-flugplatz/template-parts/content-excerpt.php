<?php
/**
 * Beitrag in der Liste (Karte)
 *
 * @package LSV_Flugplatz
 */

?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'lsv-karte beitrag-karte' ); ?>>
	<?php if ( has_post_thumbnail() ) : ?>
		<a class="beitrag-bild" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
			<?php the_post_thumbnail( 'medium_large' ); ?>
		</a>
	<?php endif; ?>
	<div class="beitrag-text">
		<?php if ( is_sticky() && is_home() ) : ?>
			<span class="beitrag-fixiert">Angeheftet</span>
		<?php endif; ?>
		<?php the_title( sprintf( '<h2 class="beitrag-titel"><a href="%s">', esc_url( get_permalink() ) ), '</a></h2>' ); ?>
		<?php lsv_beitrag_meta(); ?>
		<div class="beitrag-auszug"><?php the_excerpt(); ?></div>
		<a class="weiterlesen" href="<?php the_permalink(); ?>">Weiterlesen<span class="screen-reader-text">: <?php the_title(); ?></span> →</a>
	</div>
</article>
