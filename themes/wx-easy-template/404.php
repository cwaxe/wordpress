<?php
/**
 * 404
 *
 * @package Dunkel_Schlicht
 */

get_header();
?>
<h1><?php esc_html_e( 'Seite nicht gefunden', 'wx-easy-template' ); ?></h1>
<p><?php esc_html_e( 'Die gesuchte Seite existiert nicht oder wurde verschoben.', 'wx-easy-template' ); ?></p>
<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( '← Zur Startseite', 'wx-easy-template' ); ?></a></p>
<?php
get_footer();
