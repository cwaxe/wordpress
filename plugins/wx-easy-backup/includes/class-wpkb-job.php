<?php
/**
 * Ablaufsteuerung für Sicherung und Wiederherstellung.
 *
 * Jeder Vorgang wird in viele kurze Schritte zerlegt (jeweils ~20 Sekunden),
 * damit auch große Seiten auf Shared Hosting ohne Timeout gesichert werden.
 * Der Zustand liegt als JSON-Datei im Backup-Ordner – nicht in der
 * Datenbank, denn diese wird bei der Wiederherstellung ersetzt.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPKB_Job {

	/** Dateien, die beim Einspielen nie überschrieben werden. */
	const PROTECTED_ROOT = array( 'wp-config.php', '.htaccess', '.user.ini', 'php.ini', 'web.config', '.maintenance' );

	/** Standardmäßig ausgeschlossene Pfade (relativ zu wp-content). */
	const DEFAULT_EXCLUDES = array(
		'cache',
		'upgrade',
		'upgrade-temp-backup',
		'ai1wm-backups',
		'updraft',
		'backups-dup-lite',
		'backups-dup-pro',
		'backup-db',
		'wflogs',
		'et-cache',
		'litespeed',
	);

	public $data;
	private $new_log = array();
	private $budget;

	/* ------------------------------------------------------------------ */
	/* Ordner & Hilfen                                                     */
	/* ------------------------------------------------------------------ */

	public static function ensure_backup_dir() {
		$dir = WPKB_BACKUP_DIR;
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$files = array(
			'.htaccess'  => "# Zugriff von außen verbieten\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n",
			'index.php'  => "<?php // Silence is golden.\n",
			'index.html' => '',
			'web.config' => "<?xml version=\"1.0\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
		);
		foreach ( $files as $name => $content ) {
			if ( ! file_exists( $dir . '/' . $name ) ) {
				@file_put_contents( $dir . '/' . $name, $content ); // phpcs:ignore
			}
		}
		if ( ! is_dir( $dir ) || ! wp_is_writable( $dir ) ) {
			throw new Exception( 'Backup-Ordner ist nicht beschreibbar: ' . $dir );
		}
		return $dir;
	}

	private static function job_file( $id ) {
		return WPKB_BACKUP_DIR . '/job-' . $id . '.json';
	}

	private static function work_dir( $id ) {
		return WPKB_BACKUP_DIR . '/tmp-' . $id;
	}

	private static function valid_id( $id ) {
		return is_string( $id ) && preg_match( '/^[a-z0-9]{16}$/', $id );
	}

	public static function backups() {
		$out = array();
		foreach ( (array) glob( WPKB_BACKUP_DIR . '/*.tar' ) as $file ) {
			$out[] = array(
				'name'     => basename( $file ),
				'size'     => filesize( $file ),
				'mtime'    => filemtime( $file ),
				'manifest' => WPKB_Tar::read_manifest( $file ),
			);
		}
		usort(
			$out,
			function ( $a, $b ) {
				return $b['mtime'] - $a['mtime'];
			}
		);
		return $out;
	}

	/**
	 * Liefert den absoluten Pfad eines Backups oder null (Schutz vor Path Traversal).
	 */
	/**
	 * Stammt das Archiv von diesem Plugin? Backups der Vorgänger-Version
	 * „WP Komplett-Backup & Umzug“ (Kennung wp-komplett-backup) gelten weiter.
	 */
	public static function is_own_manifest( $manifest ) {
		return is_array( $manifest ) && in_array( $manifest['plugin'] ?? '', array( 'wx-easy-backup', 'wp-komplett-backup' ), true );
	}

	public static function backup_path( $name ) {
		$name = basename( (string) $name );
		if ( ! preg_match( '/^[A-Za-z0-9._-]+\.tar$/', $name ) ) {
			return null;
		}
		$path = WPKB_BACKUP_DIR . '/' . $name;
		return is_file( $path ) ? $path : null;
	}

	public static function running_job() {
		foreach ( (array) glob( WPKB_BACKUP_DIR . '/job-*.json' ) as $f ) {
			$d = json_decode( (string) file_get_contents( $f ), true );
			if ( is_array( $d ) && 'running' === $d['status'] && filemtime( $f ) > time() - 600 ) {
				return $d;
			}
		}
		return null;
	}

	private function compute_budget() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 120;
		}
		@set_time_limit( 0 ); // phpcs:ignore
		$max = (int) ini_get( 'max_execution_time' );
		$b   = 20;
		if ( $max > 0 ) {
			$b = min( $b, max( 3, (int) floor( $max / 2 ) ) );
		}
		return (int) apply_filters( 'wpkb_step_seconds', $b );
	}

	private function log( $msg ) {
		$line                = gmdate( 'H:i:s' ) . '  ' . $msg;
		$this->data['log'][] = $line;
		$this->new_log[]     = $line;
		if ( count( $this->data['log'] ) > 500 ) {
			$this->data['log'] = array_slice( $this->data['log'], -500 );
		}
	}

	private function save() {
		$this->data['updated'] = time();
		file_put_contents( self::job_file( $this->data['id'] ), wp_json_encode( $this->data ), LOCK_EX );
	}

	public static function load( $id ) {
		if ( ! self::valid_id( $id ) || ! is_file( self::job_file( $id ) ) ) {
			throw new Exception( 'Vorgang nicht gefunden.' );
		}
		$job       = new self();
		$job->data = json_decode( (string) file_get_contents( self::job_file( $id ) ), true );
		if ( ! is_array( $job->data ) ) {
			throw new Exception( 'Vorgangsdatei ist beschädigt.' );
		}
		return $job;
	}

	public function response() {
		return array(
			'id'       => $this->data['id'],
			'type'     => $this->data['type'],
			'status'   => $this->data['status'],
			'phase'    => $this->data['phase'],
			'progress' => round( (float) $this->data['progress'], 1 ),
			'log'      => $this->new_log,
			'result'   => $this->data['result'] ?? null,
		);
	}

	private static function rrmdir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $it as $f ) {
			$f->isDir() && ! $f->isLink() ? @rmdir( $f->getPathname() ) : @unlink( $f->getPathname() ); // phpcs:ignore
		}
		@rmdir( $dir ); // phpcs:ignore
	}

	public static function cancel( $id ) {
		$job = self::load( $id );
		self::rrmdir( self::work_dir( $id ) );
		if ( 'backup' === $job->data['type'] && ! empty( $job->data['archive'] ) && '.part' === substr( $job->data['archive'], -5 ) ) {
			@unlink( $job->data['archive'] ); // phpcs:ignore
		}
		if ( 'restore' === $job->data['type'] && empty( $job->data['swapped'] ) ) {
			WPKB_Database::drop_with_prefix( WPKB_Database::TMP_PREFIX );
		}
		@unlink( self::job_file( $id ) ); // phpcs:ignore
	}

	/** Alte, liegengebliebene Vorgangsdateien aufräumen. */
	public static function cleanup_stale() {
		foreach ( (array) glob( WPKB_BACKUP_DIR . '/job-*.json' ) as $f ) {
			if ( filemtime( $f ) < time() - DAY_IN_SECONDS ) {
				$id = substr( basename( $f, '.json' ), 4 );
				try {
					self::cancel( $id );
				} catch ( Exception $e ) {
					@unlink( $f ); // phpcs:ignore
				}
			}
		}
	}

	private static function new_id() {
		return strtolower( wp_generate_password( 16, false, false ) );
	}

	/* ------------------------------------------------------------------ */
	/* Start                                                               */
	/* ------------------------------------------------------------------ */

	public static function start_backup( array $opts ) {
		global $wpdb;
		self::ensure_backup_dir();
		self::cleanup_stale();
		if ( self::running_job() ) {
			throw new Exception( 'Es läuft bereits ein Vorgang. Bitte warten oder ihn abbrechen.' );
		}
		$id   = self::new_id();
		$host = sanitize_title( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$name = sprintf( 'backup-%s-%s-%s.tar', $host ? $host : 'site', wp_date( 'Ymd-His' ), strtolower( wp_generate_password( 10, false, false ) ) );

		$excludes = array();
		foreach ( preg_split( '/[\r\n,]+/', (string) ( $opts['exclude'] ?? '' ) ) as $ex ) {
			$ex = trim( str_replace( '\\', '/', $ex ), " /\t" );
			if ( '' !== $ex && false === strpos( $ex, '..' ) ) {
				$excludes[] = $ex;
			}
		}

		$job       = new self();
		$job->data = array(
			'id'       => $id,
			'type'     => 'backup',
			'status'   => 'running',
			'phase'    => 'init',
			'progress' => 0,
			'created'  => time(),
			'log'      => array(),
			'options'  => array(
				'db'       => ! empty( $opts['db'] ),
				'content'  => ! empty( $opts['content'] ),
				'core'     => ! empty( $opts['core'] ),
				'excludes' => $excludes,
			),
			'archive'  => WPKB_BACKUP_DIR . '/' . $name . '.part',
			'final'    => WPKB_BACKUP_DIR . '/' . $name,
		);
		if ( ! $job->data['options']['db'] && ! $job->data['options']['content'] && ! $job->data['options']['core'] ) {
			throw new Exception( 'Bitte mindestens einen Bestandteil für die Sicherung auswählen.' );
		}
		wp_mkdir_p( self::work_dir( $id ) );
		$job->log( 'Sicherung gestartet: ' . $name );
		$job->save();
		return $job;
	}

	public static function start_restore( $file, array $opts ) {
		self::ensure_backup_dir();
		self::cleanup_stale();
		if ( self::running_job() ) {
			throw new Exception( 'Es läuft bereits ein Vorgang. Bitte warten oder ihn abbrechen.' );
		}
		$path = self::backup_path( $file );
		if ( ! $path ) {
			throw new Exception( 'Backup-Datei nicht gefunden.' );
		}
		$manifest = WPKB_Tar::read_manifest( $path );
		if ( ! self::is_own_manifest( $manifest ) ) {
			throw new Exception( 'Die Datei ist kein gültiges Backup dieses Plugins.' );
		}
		if ( ! empty( $manifest['multisite'] ) || is_multisite() ) {
			throw new Exception( 'Multisite-Installationen werden für die Wiederherstellung nicht unterstützt.' );
		}

		$id        = self::new_id();
		$job       = new self();
		$job->data = array(
			'id'       => $id,
			'type'     => 'restore',
			'status'   => 'running',
			'phase'    => 'init',
			'progress' => 0,
			'created'  => time(),
			'log'      => array(),
			'archive'  => $path,
			'manifest' => $manifest,
			'options'  => array(
				'db'    => ! empty( $opts['db'] ) && ! empty( $manifest['includes']['db'] ),
				'files' => ! empty( $opts['files'] ) && ! empty( $manifest['includes']['content'] ),
				'core'  => ! empty( $opts['core'] ) && ! empty( $manifest['includes']['core'] ),
			),
		);
		wp_mkdir_p( self::work_dir( $id ) );
		$job->log( 'Wiederherstellung gestartet aus ' . basename( $path ) );
		$job->save();
		return $job;
	}

	/* ------------------------------------------------------------------ */
	/* Schritt ausführen                                                   */
	/* ------------------------------------------------------------------ */

	public static function step( $id ) {
		$job = self::load( $id );
		if ( 'running' !== $job->data['status'] ) {
			return $job;
		}
		$lock = fopen( self::job_file( $id ) . '.lock', 'c' );
		if ( ! $lock || ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
			return $job; // Ein anderer Schritt läuft gerade noch.
		}
		$job           = self::load( $id ); // Nach dem Sperren frisch lesen.
		$job->budget   = $job->compute_budget();
		$deadline      = microtime( true ) + $job->budget;
		ignore_user_abort( true );

		try {
			if ( 'backup' === $job->data['type'] ) {
				$job->run_backup( $deadline );
			} else {
				$job->run_restore( $deadline );
			}
		} catch ( Throwable $e ) {
			$job->data['status'] = 'error';
			$job->log( 'FEHLER: ' . $e->getMessage() );
			if ( 'restore' === $job->data['type'] && empty( $job->data['swapped'] ) ) {
				$job->log( 'Die Datenbank der Seite wurde nicht verändert.' );
			}
		}
		$job->save();
		flock( $lock, LOCK_UN );
		fclose( $lock );
		@unlink( self::job_file( $id ) . '.lock' ); // phpcs:ignore
		if ( in_array( $job->data['status'], array( 'done', 'error' ), true ) ) {
			// Erfolgreich beendete Vorgänge aufräumen, Fehler zur Diagnose noch behalten.
			if ( 'done' === $job->data['status'] ) {
				self::rrmdir( self::work_dir( $id ) );
			}
		}
		return $job;
	}

	/* ------------------------------------------------------------------ */
	/* Sicherung                                                           */
	/* ------------------------------------------------------------------ */

	private function run_backup( $deadline ) {
		global $wpdb;
		$d    = &$this->data;
		$work = self::work_dir( $d['id'] );

		do {
			switch ( $d['phase'] ) {

				case 'init':
					$tables   = $d['options']['db'] ? WPKB_Database::tables_with_prefix( $wpdb->base_prefix ) : array();
					$manifest = array(
						'plugin'       => 'wx-easy-backup',
						'version'      => WPKB_VERSION,
						'created'      => gmdate( 'c' ),
						'wp_version'   => get_bloginfo( 'version' ),
						'php_version'  => PHP_VERSION,
						'db_version'   => $wpdb->db_version(),
						'home'         => get_option( 'home' ),
						'siteurl'      => get_option( 'siteurl' ),
						'abspath'      => untrailingslashit( ABSPATH ),
						'content_dir'  => untrailingslashit( WP_CONTENT_DIR ),
						'table_prefix' => $wpdb->base_prefix,
						'charset'      => $wpdb->charset,
						'multisite'    => is_multisite(),
						'tables'       => $tables,
						'includes'     => array(
							'db'      => $d['options']['db'],
							'content' => $d['options']['content'],
							'core'    => $d['options']['core'],
						),
					);
					$json = wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
					file_put_contents( $d['archive'], WPKB_Tar::header( 'manifest.json', strlen( $json ), time() ) . WPKB_Tar::pad( $json ) );
					$d['manifest_tables'] = count( $tables );
					if ( $d['options']['db'] ) {
						$d['db']    = array(
							'tables'  => $tables,
							't'       => 0,
							'prefix'  => $wpdb->base_prefix,
							'started' => false,
						);
						$d['phase'] = 'db';
						$this->log( 'Exportiere Datenbank (' . count( $tables ) . ' Tabellen) …' );
					} else {
						$d['phase'] = 'scan';
					}
					break;

				case 'db':
					$sql = $work . '/database.sql';
					if ( ! file_exists( $sql ) ) {
						file_put_contents( $sql, '-- WX Easy Backup ' . WPKB_VERSION . ' · ' . gmdate( 'c' ) . "\n" );
					}
					$done          = WPKB_Database::export_step( $d['db'], $sql, $deadline );
					$total         = max( 1, count( $d['db']['tables'] ) );
					$d['progress'] = 25 * min( $d['db']['t'], $total ) / $total;
					if ( $done ) {
						$this->log( 'Datenbank exportiert (' . size_format( filesize( $sql ) ) . ').' );
						$d['cur']   = array(
							'abs'  => $sql,
							'name' => 'database.sql',
						);
						$d['phase'] = 'db_add';
					}
					break;

				case 'db_add':
					$out = fopen( $d['archive'], 'ab' );
					$ok  = $this->add_entry_step( $d['cur'], $out, $deadline );
					fclose( $out );
					if ( $ok ) {
						@unlink( $work . '/database.sql' ); // phpcs:ignore
						$d['cur']   = null;
						$d['phase'] = 'scan';
					}
					break;

				case 'scan':
					$this->log( 'Ermittle Dateien …' );
					$stats         = $this->scan_files( $work . '/files.txt' );
					$d['files']    = array(
						'pos'   => 0,
						'count' => $stats['count'],
						'bytes' => $stats['bytes'],
						'done'  => 0,
						'n'     => 0,
					);
					$d['cur']      = null;
					$d['phase']    = 'files';
					$d['progress'] = $d['options']['db'] ? 25 : 0;
					$this->log( sprintf( '%s Dateien, %s werden gesichert …', number_format_i18n( $stats['count'] ), size_format( $stats['bytes'] ) ) );
					break;

				case 'files':
					$done = $this->files_step( $work . '/files.txt', $deadline );
					$base = $d['options']['db'] ? 25 : 0;
					$f    = $d['files'];
					$d['progress'] = $base + ( 99 - $base ) * ( $f['bytes'] > 0 ? min( 1, $f['done'] / $f['bytes'] ) : 1 );
					if ( $done ) {
						$d['phase'] = 'finalize';
					}
					break;

				case 'finalize':
					file_put_contents( $d['archive'], WPKB_Tar::end_of_archive(), FILE_APPEND );
					if ( ! @rename( $d['archive'], $d['final'] ) ) { // phpcs:ignore
						throw new Exception( 'Archiv konnte nicht umbenannt werden.' );
					}
					$d['archive']  = $d['final'];
					$d['status']   = 'done';
					$d['phase']    = 'done';
					$d['progress'] = 100;
					$d['result']   = array(
						'file' => basename( $d['final'] ),
						'size' => size_format( filesize( $d['final'] ) ),
					);
					$this->log( 'Fertig! Backup: ' . basename( $d['final'] ) . ' (' . $d['result']['size'] . ')' );
					break;
			}
		} while ( microtime( true ) < $deadline && 'running' === $d['status'] );
	}

	/**
	 * Erstellt die Dateiliste (eine Zeile je Eintrag: Bereich \t Typ \t relativer Pfad).
	 */
	private function scan_files( $list_file ) {
		$o       = $this->data['options'];
		$fh      = fopen( $list_file, 'wb' );
		$count   = 0;
		$bytes   = 0;
		$content = wp_normalize_path( untrailingslashit( WP_CONTENT_DIR ) );
		$root    = wp_normalize_path( untrailingslashit( ABSPATH ) );
		$backup  = wp_normalize_path( untrailingslashit( WPKB_BACKUP_DIR ) );

		$content_excludes = array_merge( self::DEFAULT_EXCLUDES, $o['excludes'] );
		$root_excludes    = $o['excludes'];

		$jobs = array();
		if ( $o['content'] ) {
			$jobs[] = array( 'w', $content, $content_excludes );
		}
		if ( $o['core'] ) {
			$jobs[] = array( 'r', $root, $root_excludes );
		}

		foreach ( $jobs as list( $area, $base, $excludes ) ) {
			$skip_abs = array( $backup );
			if ( 'r' === $area ) {
				$skip_abs[] = $content; // wp-content separat
			}
			$filter = function ( $current ) use ( $base, $excludes, $skip_abs ) {
				$path = wp_normalize_path( $current->getPathname() );
				if ( in_array( $path, $skip_abs, true ) ) {
					return false;
				}
				$rel = ltrim( substr( $path, strlen( $base ) ), '/' );
				foreach ( $excludes as $ex ) {
					if ( $rel === $ex || 0 === strpos( $rel, rtrim( $ex, '/' ) . '/' ) || ( false !== strpos( $ex, '*' ) && fnmatch( $ex, $rel ) ) ) {
						return false;
					}
				}
				if ( $current->isFile() && preg_match( '/\.wpkb-part$/', $path ) ) {
					return false;
				}
				return true;
			};
			try {
				$it = new RecursiveIteratorIterator(
					new RecursiveCallbackFilterIterator(
						new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ),
						$filter
					),
					RecursiveIteratorIterator::SELF_FIRST,
					RecursiveIteratorIterator::CATCH_GET_CHILD
				);
				foreach ( $it as $f ) {
					$rel = ltrim( substr( wp_normalize_path( $f->getPathname() ), strlen( $base ) ), '/' );
					if ( false !== strpos( $rel, "\n" ) || false !== strpos( $rel, "\t" ) ) {
						continue;
					}
					if ( $f->isDir() ) {
						fwrite( $fh, "{$area}\td\t{$rel}\n" );
					} elseif ( $f->isFile() && $f->isReadable() ) {
						fwrite( $fh, "{$area}\tf\t{$rel}\n" );
						$count++;
						$bytes += $f->getSize();
					}
				}
			} catch ( Exception $e ) {
				$this->log( 'Warnung beim Durchsuchen: ' . $e->getMessage() );
			}
		}
		fclose( $fh );
		return array(
			'count' => $count,
			'bytes' => $bytes,
		);
	}

	private function files_step( $list_file, $deadline ) {
		$d   = &$this->data;
		$out = fopen( $d['archive'], 'ab' );
		$in  = fopen( $list_file, 'rb' );
		fseek( $in, $d['files']['pos'] );

		$did = false;
		try {
			while ( true ) {
				if ( $d['cur'] ) {
					$did    = true;
					$before = $d['cur']['written'] ?? 0;
					$ok     = $this->add_entry_step( $d['cur'], $out, $deadline );
					$d['files']['done'] += ( $d['cur']['written'] ?? 0 ) - $before;
					if ( ! $ok ) {
						return false;
					}
					$d['cur'] = null;
					$d['files']['n']++;
				}
				if ( $did && microtime( true ) > $deadline ) {
					return false;
				}
				$line = fgets( $in );
				if ( false === $line ) {
					return true;
				}
				$d['files']['pos'] = ftell( $in );
				$parts             = explode( "\t", rtrim( $line, "\n" ), 3 );
				if ( 3 !== count( $parts ) ) {
					continue;
				}
				list( $area, $type, $rel ) = $parts;
				$base = 'w' === $area ? WP_CONTENT_DIR : untrailingslashit( ABSPATH );
				$name = ( 'w' === $area ? 'wp-content/' : 'root/' ) . $rel;
				$abs  = $base . '/' . $rel;

				$did = true;
				if ( 'd' === $type ) {
					fwrite( $out, WPKB_Tar::header( $name . '/', 0, @filemtime( $abs ), '5', 0755 ) ); // phpcs:ignore
					continue;
				}
				if ( ! is_readable( $abs ) ) {
					$this->log( 'Übersprungen (nicht lesbar): ' . $name );
					continue;
				}
				$d['cur'] = array(
					'abs'  => $abs,
					'name' => $name,
				);
			}
		} finally {
			fclose( $in );
			fclose( $out );
		}
	}

	/**
	 * Fügt eine Datei (fortsetzbar) dem Archiv hinzu.
	 *
	 * @return bool true, wenn vollständig geschrieben.
	 */
	private function add_entry_step( array &$cur, $out, $deadline ) {
		if ( empty( $cur['header'] ) ) {
			clearstatcache( true, $cur['abs'] );
			$cur['size']    = (int) @filesize( $cur['abs'] ); // phpcs:ignore
			$cur['written'] = 0;
			fwrite( $out, WPKB_Tar::header( $cur['name'], $cur['size'], (int) @filemtime( $cur['abs'] ) ) ); // phpcs:ignore
			$cur['header'] = true;
		}
		$in = @fopen( $cur['abs'], 'rb' ); // phpcs:ignore
		if ( $in ) {
			fseek( $in, $cur['written'] );
		}
		while ( $cur['written'] < $cur['size'] ) {
			$want  = min( 1048576, $cur['size'] - $cur['written'] );
			$chunk = $in ? fread( $in, $want ) : '';
			if ( false === $chunk || '' === $chunk ) {
				// Datei wurde während der Sicherung kleiner – mit Nullen auffüllen.
				$chunk = str_repeat( "\0", $want );
			}
			fwrite( $out, $chunk );
			$cur['written'] += strlen( $chunk );
			if ( $cur['written'] < $cur['size'] && microtime( true ) > $deadline ) {
				if ( $in ) {
					fclose( $in );
				}
				return false;
			}
		}
		if ( $in ) {
			fclose( $in );
		}
		$pad = WPKB_Tar::padding_for( $cur['size'] );
		if ( $pad ) {
			fwrite( $out, str_repeat( "\0", $pad ) );
		}
		return true;
	}

	/* ------------------------------------------------------------------ */
	/* Wiederherstellung                                                   */
	/* ------------------------------------------------------------------ */

	private function run_restore( $deadline ) {
		global $wpdb;
		$d    = &$this->data;
		$m    = $d['manifest'];
		$work = self::work_dir( $d['id'] );

		do {
			switch ( $d['phase'] ) {

				case 'init':
					$d['target'] = array(
						'home'         => get_option( 'home' ),
						'siteurl'      => get_option( 'siteurl' ),
						'abspath'      => untrailingslashit( ABSPATH ),
						'content_dir'  => untrailingslashit( WP_CONTENT_DIR ),
						'table_prefix' => $wpdb->base_prefix,
						'login_url'    => wp_login_url(),
					);
					$this->log( sprintf( 'Quelle: %s (WordPress %s) → Ziel: %s', $m['home'], $m['wp_version'], $d['target']['home'] ) );
					if ( version_compare( $m['wp_version'], get_bloginfo( 'version' ), '!=' ) && ! $d['options']['core'] ) {
						$this->log( sprintf( 'Hinweis: Das Backup stammt von WordPress %s, hier läuft %s. Nach dem Einspielen ggf. Datenbank-Update im Backend bestätigen.', $m['wp_version'], get_bloginfo( 'version' ) ) );
					}
					if ( $d['options']['db'] ) {
						WPKB_Database::drop_with_prefix( WPKB_Database::TMP_PREFIX );
					}
					$d['extract']  = array(
						'pos'      => 0,
						'root_pos' => null,
					);
					$d['cur']      = null;
					$d['size']     = filesize( $d['archive'] );
					$d['phase']    = 'extract';
					$this->log( $d['options']['files'] ? 'Entpacke Dateien …' : 'Lese Archiv …' );
					break;

				case 'extract':
					$done          = $this->extract_step( $deadline );
					$d['progress'] = 45 * min( 1, $d['extract']['pos'] / max( 1, $d['size'] ) );
					if ( $done ) {
						if ( $d['options']['files'] ) {
							$this->log( 'Dateien in wp-content wiederhergestellt.' );
						}
						if ( $d['options']['db'] ) {
							if ( ! file_exists( $work . '/database.sql' ) ) {
								throw new Exception( 'Im Archiv wurde keine Datenbank gefunden.' );
							}
							$d['import'] = array(
								'pos'   => 0,
								'count' => 0,
							);
							$d['phase']  = 'import';
							$this->log( 'Importiere Datenbank in temporäre Tabellen …' );
						} else {
							$d['phase'] = 'finish';
						}
					}
					break;

				case 'import':
					$sql           = $work . '/database.sql';
					$done          = WPKB_Database::import_step( $d['import'], $sql, $m['charset'] ?? '', $deadline );
					$d['progress'] = 45 + 30 * min( 1, $d['import']['pos'] / max( 1, filesize( $sql ) ) );
					if ( $done ) {
						$this->log( 'Datenbank importiert (' . number_format_i18n( $d['import']['count'] ) . ' Anweisungen).' );
						$pairs = WPKB_Replacer::build_pairs( $m, $d['target'] );
						if ( $pairs ) {
							$bases     = $this->table_bases();
							$d['repl'] = array(
								'tables'  => array_map(
									function ( $b ) {
										return WPKB_Database::TMP_PREFIX . $b;
									},
									$bases
								),
								't'       => 0,
								'started' => false,
								'changed' => 0,
							);
							$d['phase'] = 'replace';
							$this->log( sprintf( 'Passe URLs und Pfade an: %s → %s', $m['home'], $d['target']['home'] ) );
						} else {
							$d['phase'] = 'finish';
						}
					}
					break;

				case 'replace':
					$rep           = new WPKB_Replacer( WPKB_Replacer::build_pairs( $m, $d['target'] ) );
					$needles       = WPKB_Replacer::build_needles( $m );
					$done          = WPKB_Database::replace_step( $d['repl'], $rep, $needles, $deadline );
					$d['progress'] = 75 + 20 * min( 1, $d['repl']['t'] / max( 1, count( $d['repl']['tables'] ) ) );
					if ( $done ) {
						$this->log( number_format_i18n( $d['repl']['changed'] ?? 0 ) . ' Datensätze angepasst.' );
						$d['phase'] = 'finish';
					}
					break;

				case 'finish':
					$this->finish_restore();
					break;
			}
		} while ( microtime( true ) < $deadline && 'running' === $d['status'] );
	}

	private function table_bases() {
		$m     = $this->data['manifest'];
		$bases = array();
		foreach ( (array) $m['tables'] as $t ) {
			$bases[] = substr( $t, strlen( $m['table_prefix'] ) );
		}
		return $bases;
	}

	/**
	 * Letzter Schritt – läuft absichtlich in einem einzigen Request,
	 * weil die Anmeldung danach (neue Benutzertabelle) nicht mehr gültig ist.
	 */
	private function finish_restore() {
		$d = &$this->data;
		$m = $d['manifest'];
		@set_time_limit( 0 ); // phpcs:ignore

		if ( $d['options']['db'] ) {
			WPKB_Database::prepare_tmp_tables( $m['table_prefix'], $d['target']['table_prefix'] );
			WPKB_Database::swap_tables( $this->table_bases(), $d['target']['table_prefix'] );
			$d['swapped'] = true;
			$this->log( 'Datenbank ausgetauscht.' );
			$this->replace_legacy_activation();
		}

		if ( $d['options']['core'] && null !== $d['extract']['root_pos'] ) {
			$this->log( 'Stelle WordPress-Kerndateien und Hauptverzeichnis wieder her …' );
			$this->extract_root( $d['extract']['root_pos'] );
		}

		if ( function_exists( 'opcache_reset' ) ) {
			@opcache_reset(); // phpcs:ignore
		}
		wp_cache_flush();

		$d['status']   = 'done';
		$d['phase']    = 'done';
		$d['progress'] = 100;
		$d['result']   = array(
			'relogin'   => (bool) $d['options']['db'],
			'login_url' => $d['target']['login_url'],
		);
		$this->log( 'Wiederherstellung abgeschlossen.' );
		if ( $d['options']['db'] ) {
			$this->log( 'Bitte jetzt mit den Zugangsdaten der gesicherten Seite neu anmelden und unter Einstellungen → Permalinks einmal speichern.' );
		}
	}

	/**
	 * War in der eingespielten Datenbank die Vorgänger-Version
	 * „WP Komplett-Backup & Umzug“ aktiv, wird stattdessen dieses Plugin aktiviert.
	 */
	private function replace_legacy_activation() {
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'active_plugins', 'options' );
		$active = (array) get_option( 'active_plugins', array() );
		$legacy = 'wp-komplett-backup/wp-komplett-backup.php';
		if ( ! in_array( $legacy, $active, true ) ) {
			return;
		}
		$active   = array_values( array_diff( $active, array( $legacy ) ) );
		$active[] = plugin_basename( WPKB_FILE );
		$active   = array_values( array_unique( $active ) );
		sort( $active );
		update_option( 'active_plugins', $active );
		$this->log( 'Die alte Version „WP Komplett-Backup & Umzug“ wurde durch WX Easy Backup ersetzt.' );
	}

	/** Relativer Pfad des Backup-Ordners innerhalb von wp-content (oder null). */
	private static function backup_rel_in_content() {
		$c = wp_normalize_path( untrailingslashit( WP_CONTENT_DIR ) ) . '/';
		$b = wp_normalize_path( untrailingslashit( WPKB_BACKUP_DIR ) );
		return 0 === strpos( $b . '/', $c ) ? substr( $b, strlen( $c ) ) : null;
	}

	private static function safe_rel( $rel ) {
		$rel = str_replace( '\\', '/', (string) $rel );
		$rel = rtrim( $rel, '/' );
		if ( '' === $rel || '/' === $rel[0] || preg_match( '#^[a-zA-Z]:#', $rel ) || false !== strpos( $rel, "\0" ) ) {
			return null;
		}
		foreach ( explode( '/', $rel ) as $seg ) {
			if ( '..' === $seg || '' === $seg ) {
				return null;
			}
		}
		return $rel;
	}

	/**
	 * Ermittelt das Ziel eines Archiv-Eintrags oder null zum Überspringen.
	 */
	private function destination( $name ) {
		$d    = $this->data;
		$work = self::work_dir( $d['id'] );

		if ( 'database.sql' === $name ) {
			return $d['options']['db'] ? $work . '/database.sql' : null;
		}
		if ( 0 === strpos( $name, 'wp-content/' ) ) {
			if ( ! $d['options']['files'] ) {
				return null;
			}
			$rel = self::safe_rel( substr( $name, 11 ) );
			if ( null === $rel ) {
				return null;
			}
			$backup = self::backup_rel_in_content();
			// Dieses Plugin nicht während der Ausführung überschreiben und die
			// Vorgänger-Version aus älteren Backups nicht wieder anlegen.
			foreach ( array( 'plugins/' . WPKB_SLUG, 'plugins/wp-komplett-backup' ) as $own ) {
				if ( $rel === $own || 0 === strpos( $rel, $own . '/' ) ) {
					return null;
				}
			}
			if ( $backup && ( $rel === $backup || 0 === strpos( $rel, $backup . '/' ) ) ) {
				return null;
			}
			return untrailingslashit( WP_CONTENT_DIR ) . '/' . $rel;
		}
		if ( 0 === strpos( $name, 'root/' ) ) {
			$rel = self::safe_rel( substr( $name, 5 ) );
			if ( null === $rel || in_array( $rel, self::PROTECTED_ROOT, true ) ) {
				return null;
			}
			return untrailingslashit( ABSPATH ) . '/' . $rel;
		}
		return null;
	}

	/**
	 * Entpackt wp-content und database.sql (fortsetzbar). Stoppt beim
	 * ersten root/-Eintrag und merkt sich dessen Position.
	 */
	private function extract_step( $deadline ) {
		$d  = &$this->data;
		$fh = fopen( $d['archive'], 'rb' );
		if ( ! $fh ) {
			throw new Exception( 'Archiv nicht lesbar.' );
		}
		$did = false;
		try {
			while ( true ) {
				if ( $d['cur'] ) {
					$did = true;
					if ( ! $this->extract_entry_step( $fh, $d['cur'], $deadline ) ) {
						return false;
					}
					$d['extract']['pos'] = $d['cur']['next'];
					$d['cur']            = null;
				}
				if ( $did && microtime( true ) > $deadline ) {
					return false;
				}
				$did   = true;
				$entry = WPKB_Tar::read_entry( $fh, $d['extract']['pos'] );
				if ( ! $entry ) {
					return true;
				}
				if ( 0 === strpos( $entry['name'], 'root/' ) ) {
					$d['extract']['root_pos'] = $d['extract']['pos'];
					return true;
				}
				$dest = $this->destination( $entry['name'] );
				if ( null === $dest ) {
					$d['extract']['pos'] = $entry['next'];
					continue;
				}
				if ( '5' === $entry['type'] ) {
					wp_mkdir_p( $dest );
					$d['extract']['pos'] = $entry['next'];
					continue;
				}
				if ( '0' !== $entry['type'] ) {
					$d['extract']['pos'] = $entry['next']; // Links etc. überspringen.
					continue;
				}
				$d['cur'] = array(
					'dest'   => $dest,
					'size'   => $entry['size'],
					'offset' => $entry['data_offset'],
					'next'   => $entry['next'],
					'done'   => 0,
				);
			}
		} finally {
			fclose( $fh );
		}
	}

	/**
	 * Schreibt eine Datei aus dem Archiv – zuerst in eine Temp-Datei,
	 * dann atomares Umbenennen (keine halb geschriebenen PHP-Dateien).
	 */
	private function extract_entry_step( $fh, array &$cur, $deadline ) {
		$tmp = $cur['dest'] . '.wpkb-part';
		if ( 0 === $cur['done'] ) {
			wp_mkdir_p( dirname( $cur['dest'] ) );
			$out = @fopen( $tmp, 'wb' ); // phpcs:ignore
		} else {
			$out = @fopen( $tmp, 'r+b' ); // phpcs:ignore
			if ( $out ) {
				fseek( $out, $cur['done'] );
			}
		}
		if ( ! $out ) {
			$this->log( 'Nicht beschreibbar, übersprungen: ' . $cur['dest'] );
			return true;
		}
		fseek( $fh, $cur['offset'] + $cur['done'] );
		while ( $cur['done'] < $cur['size'] ) {
			$chunk = fread( $fh, min( 1048576, $cur['size'] - $cur['done'] ) );
			if ( false === $chunk || '' === $chunk ) {
				fclose( $out );
				throw new Exception( 'Archiv ist unvollständig (vorzeitiges Dateiende).' );
			}
			fwrite( $out, $chunk );
			$cur['done'] += strlen( $chunk );
			if ( $cur['done'] < $cur['size'] && microtime( true ) > $deadline ) {
				fclose( $out );
				return false;
			}
		}
		fclose( $out );
		if ( ! @rename( $tmp, $cur['dest'] ) ) { // phpcs:ignore
			@unlink( $cur['dest'] ); // phpcs:ignore
			if ( ! @rename( $tmp, $cur['dest'] ) ) { // phpcs:ignore
				@unlink( $tmp ); // phpcs:ignore
				$this->log( 'Konnte nicht ersetzt werden: ' . $cur['dest'] );
			}
		}
		return true;
	}

	/** Entpackt alle root/-Einträge in einem Rutsch. */
	private function extract_root( $pos ) {
		$fh = fopen( $this->data['archive'], 'rb' );
		$n  = 0;
		try {
			while ( ( $entry = WPKB_Tar::read_entry( $fh, $pos ) ) ) {
				$pos  = $entry['next'];
				$dest = $this->destination( $entry['name'] );
				if ( null === $dest ) {
					continue;
				}
				if ( '5' === $entry['type'] ) {
					wp_mkdir_p( $dest );
				} elseif ( '0' === $entry['type'] ) {
					$cur = array(
						'dest'   => $dest,
						'size'   => $entry['size'],
						'offset' => $entry['data_offset'],
						'done'   => 0,
					);
					$this->extract_entry_step( $fh, $cur, PHP_INT_MAX );
					$n++;
				}
			}
		} finally {
			fclose( $fh );
		}
		$this->log( number_format_i18n( $n ) . ' Dateien im Hauptverzeichnis wiederhergestellt (wp-config.php und .htaccess wurden beibehalten).' );
	}
}
