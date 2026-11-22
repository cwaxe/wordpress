<?php
/**
 * Beim Löschen des Plugins werden nur Einstellungen und Zwischenspeicher entfernt.
 * Die Termine, Kategorien, Orte und Veranstalter bleiben in der Datenbank erhalten.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( [ 'tk_settings', 'tk_cache_generation', 'tk_import_map', 'tk_tec_slugs', 'tk_import_done', 'tk_flush_rewrite' ] as $option ) {
	delete_option( $option );
}

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_tk\_ev\_%' OR option_name LIKE '\_transient\_timeout\_tk\_ev\_%'" );
