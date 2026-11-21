<?php
/**
 * Wird beim Löschen des Plugins ausgeführt.
 * Entfernt Einstellungen und Datenbanktabelle.
 * Die Bilder unter wp-content/uploads/webcam-archiv/ bleiben bewusst erhalten,
 * außer in wp-config.php ist define( 'WCA_DELETE_IMAGES_ON_UNINSTALL', true ); gesetzt.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$wca_table = $wpdb->prefix . 'wca_images';
$wpdb->query( "DROP TABLE IF EXISTS {$wca_table}" ); // phpcs:ignore

foreach ( array( 'wca_options', 'wca_state', 'wca_log', 'wca_last_hash', 'wca_last_run', 'wca_db_version' ) as $wca_opt ) {
	delete_option( $wca_opt );
}
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wca\\_rejected\\_%'" ); // phpcs:ignore
wp_clear_scheduled_hook( 'wca_capture_event' );
wp_clear_scheduled_hook( 'wca_cleanup_event' );

if ( defined( 'WCA_DELETE_IMAGES_ON_UNINSTALL' ) && WCA_DELETE_IMAGES_ON_UNINSTALL ) {
	$wca_up  = wp_upload_dir( null, false );
	$wca_dir = trailingslashit( $wca_up['basedir'] ) . 'webcam-archiv';
	if ( is_dir( $wca_dir ) ) {
		$wca_it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $wca_dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $wca_it as $wca_f ) {
			$wca_f->isDir() ? rmdir( $wca_f->getPathname() ) : unlink( $wca_f->getPathname() ); // phpcs:ignore
		}
		rmdir( $wca_dir ); // phpcs:ignore
	}
}
