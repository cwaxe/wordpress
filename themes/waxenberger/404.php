<?php
get_header();
?>
<article class="item-page">
	<h1 class="entry-title"><?php esc_html_e( 'Seite nicht gefunden', 'waxenberger' ); ?></h1>
	<div class="entry-content">
		<p><?php esc_html_e( 'Die gesuchte Seite gibt es leider nicht.', 'waxenberger' ); ?></p>
		<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Zur Startseite', 'waxenberger' ); ?></a></p>
	</div>
</article>
<?php
get_footer();
