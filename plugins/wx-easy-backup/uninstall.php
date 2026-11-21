<?php
/**
 * Beim Löschen des Plugins: temporäre Dateien entfernen.
 * Die Backup-Archive (.tar) bleiben absichtlich erhalten.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
$wpkb_dir = defined( 'WPKB_BACKUP_DIR' ) ? WPKB_BACKUP_DIR : WP_CONTENT_DIR . '/wpkb-backups';
foreach ( (array) glob( $wpkb_dir . '/{job-*,upload-*.part,*.tar.part}', GLOB_BRACE ) as $wpkb_f ) {
	@unlink( $wpkb_f ); // phpcs:ignore
}
foreach ( (array) glob( $wpkb_dir . '/tmp-*', GLOB_ONLYDIR ) as $wpkb_d ) {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $wpkb_d, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $it as $f ) {
		$f->isDir() ? @rmdir( $f->getPathname() ) : @unlink( $f->getPathname() ); // phpcs:ignore
	}
	@rmdir( $wpkb_d ); // phpcs:ignore
}
