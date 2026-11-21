<?php
/**
 * WP-CLI-Befehle – empfehlenswert für sehr große Seiten.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPKB_CLI {

	/**
	 * Erstellt ein vollständiges Backup.
	 *
	 * ## OPTIONS
	 *
	 * [--no-db]
	 * : Datenbank nicht sichern.
	 *
	 * [--no-content]
	 * : wp-content nicht sichern.
	 *
	 * [--no-core]
	 * : Kerndateien/Hauptverzeichnis nicht sichern.
	 *
	 * [--exclude=<pfade>]
	 * : Kommagetrennte Pfade, die ausgeschlossen werden.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wx-easy-backup create
	 *     wp wx-easy-backup create --no-core --exclude=uploads/videos
	 */
	public function create( $args, $assoc ) {
		$job = WPKB_Job::start_backup(
			array(
				'db'      => WP_CLI\Utils\get_flag_value( $assoc, 'db', true ),
				'content' => WP_CLI\Utils\get_flag_value( $assoc, 'content', true ),
				'core'    => WP_CLI\Utils\get_flag_value( $assoc, 'core', true ),
				'exclude' => $assoc['exclude'] ?? '',
			)
		);
		$this->run( $job );
		WP_CLI::success( 'Backup: ' . WPKB_BACKUP_DIR . '/' . $job->data['result']['file'] );
	}

	/**
	 * Spielt ein Backup ein.
	 *
	 * ## OPTIONS
	 *
	 * <datei>
	 * : Dateiname (im Backup-Ordner) oder Pfad zu einer .tar-Datei.
	 *
	 * [--no-db]
	 * : Datenbank nicht wiederherstellen.
	 *
	 * [--no-files]
	 * : wp-content nicht wiederherstellen.
	 *
	 * [--no-core]
	 * : Kerndateien nicht wiederherstellen.
	 *
	 * [--yes]
	 * : Ohne Rückfrage ausführen.
	 */
	public function restore( $args, $assoc ) {
		$file = $args[0];
		if ( is_file( $file ) && dirname( realpath( $file ) ) !== realpath( WPKB_BACKUP_DIR ) ) {
			WPKB_Job::ensure_backup_dir();
			$target = WPKB_BACKUP_DIR . '/' . basename( $file );
			WP_CLI::log( 'Kopiere Archiv in den Backup-Ordner …' );
			if ( ! copy( $file, $target ) ) {
				WP_CLI::error( 'Kopieren fehlgeschlagen.' );
			}
			$file = $target;
		}
		WP_CLI::confirm( 'Diese Seite wird mit dem Backup überschrieben. Fortfahren?', $assoc );
		$job = WPKB_Job::start_restore(
			basename( $file ),
			array(
				'db'    => WP_CLI\Utils\get_flag_value( $assoc, 'db', true ),
				'files' => WP_CLI\Utils\get_flag_value( $assoc, 'files', true ),
				'core'  => WP_CLI\Utils\get_flag_value( $assoc, 'core', true ),
			)
		);
		$this->run( $job );
		WP_CLI::success( 'Wiederherstellung abgeschlossen.' );
	}

	/**
	 * Listet vorhandene Backups auf.
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc ) {
		$rows = array();
		foreach ( WPKB_Job::backups() as $b ) {
			$rows[] = array(
				'datei'  => $b['name'],
				'quelle' => $b['manifest']['home'] ?? '?',
				'größe'  => size_format( $b['size'], 1 ),
				'datum'  => wp_date( 'Y-m-d H:i', $b['mtime'] ),
			);
		}
		WP_CLI\Utils\format_items( 'table', $rows, array( 'datei', 'quelle', 'größe', 'datum' ) );
	}

	private function run( WPKB_Job $job ) {
		$id = $job->data['id'];
		foreach ( $job->data['log'] as $l ) {
			WP_CLI::log( $l );
		}
		do {
			$job = WPKB_Job::step( $id );
			$r   = $job->response();
			foreach ( $r['log'] as $l ) {
				WP_CLI::log( $l );
			}
		} while ( 'running' === $r['status'] );
		if ( 'error' === $r['status'] ) {
			WP_CLI::error( 'Vorgang fehlgeschlagen.' );
		}
	}
}
