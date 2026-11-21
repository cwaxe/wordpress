<?php
/**
 * Admin-Oberfläche und AJAX-Endpunkte.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPKB_Admin {

	const CAP   = 'manage_options';
	const NONCE = 'wpkb_nonce';

	public static function init() {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_wpkb_download', array( __CLASS__, 'download' ) );
		foreach ( array( 'start_backup', 'start_restore', 'step', 'cancel', 'delete', 'upload', 'list' ) as $a ) {
			add_action( 'wp_ajax_wpkb_' . $a, array( __CLASS__, 'ajax_' . $a ) );
		}
		add_filter( 'plugin_action_links_' . plugin_basename( WPKB_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'tools.php?page=wpkb' ) ) . '">Backups</a>' );
		return $links;
	}

	public static function menu() {
		add_management_page( 'WX Easy Backup', 'WX Easy Backup', self::CAP, 'wpkb', array( __CLASS__, 'page' ) );
	}

	public static function assets( $hook ) {
		if ( 'tools_page_wpkb' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wpkb-admin', WPKB_URL . 'assets/admin.css', array(), WPKB_VERSION );
		wp_enqueue_script( 'wpkb-admin', WPKB_URL . 'assets/admin.js', array(), WPKB_VERSION, true );

		$max   = (int) wp_max_upload_size();
		$chunk = max( 512 * 1024, min( 8 * 1024 * 1024, $max - 256 * 1024 ) );
		wp_localize_script(
			'wpkb-admin',
			'WPKB',
			array(
				'ajax'      => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( self::NONCE ),
				'chunkSize' => $chunk,
				'download'  => wp_nonce_url( admin_url( 'admin-post.php?action=wpkb_download' ), 'wpkb_download' ),
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Seite                                                               */
	/* ------------------------------------------------------------------ */

	public static function page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		$dir_error = '';
		try {
			WPKB_Job::ensure_backup_dir();
		} catch ( Exception $e ) {
			$dir_error = $e->getMessage();
		}
		$running = WPKB_Job::running_job();
		$free    = function_exists( 'disk_free_space' ) ? @disk_free_space( WPKB_BACKUP_DIR ) : false; // phpcs:ignore
		?>
		<div class="wrap wpkb">
			<h1>WX Easy Backup</h1>

			<?php if ( $dir_error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $dir_error ); ?></p></div>
			<?php endif; ?>
			<?php if ( is_multisite() ) : ?>
				<div class="notice notice-warning"><p>Hinweis: Multisite-Netzwerke können gesichert, aber mit diesem Plugin nicht wiederhergestellt werden.</p></div>
			<?php endif; ?>

			<div id="wpkb-progress" class="wpkb-card" hidden>
				<h2 id="wpkb-progress-title">Vorgang läuft …</h2>
				<div class="wpkb-bar"><span id="wpkb-bar-fill"></span></div>
				<p class="wpkb-bar-label"><span id="wpkb-percent">0 %</span> · <span id="wpkb-phase"></span></p>
				<pre id="wpkb-log"></pre>
				<p>
					<button type="button" class="button" id="wpkb-cancel">Abbrechen</button>
					<span class="description">Bitte dieses Fenster geöffnet lassen, bis der Vorgang abgeschlossen ist.</span>
				</p>
				<div id="wpkb-result"></div>
			</div>

			<div class="wpkb-grid">
				<div class="wpkb-card">
					<h2>1. Backup erstellen</h2>
					<form id="wpkb-backup-form">
						<p><label><input type="checkbox" name="db" value="1" checked> Datenbank (alle Tabellen mit Präfix <code><?php echo esc_html( $GLOBALS['wpdb']->base_prefix ); ?></code>)</label></p>
						<p><label><input type="checkbox" name="content" value="1" checked> wp-content (Themes, Plugins, Uploads)</label></p>
						<p><label><input type="checkbox" name="core" value="1" checked> WordPress-Kerndateien &amp; weitere Dateien im Hauptverzeichnis</label></p>
						<p>
							<label for="wpkb-exclude"><strong>Ausschließen</strong> (ein Pfad je Zeile, relativ zu wp-content bzw. zum Hauptverzeichnis, <code>*</code> erlaubt):</label><br>
							<textarea id="wpkb-exclude" name="exclude" rows="3" class="large-text code" placeholder="uploads/grosse-videos&#10;*.log"></textarea>
							<span class="description">Automatisch ausgeschlossen: Cache-Ordner und Backups anderer Plugins.</span>
						</p>
						<p><button type="submit" class="button button-primary button-hero">Backup jetzt erstellen</button></p>
						<?php if ( false !== $free ) : ?>
							<p class="description">Freier Speicher auf dem Server: <?php echo esc_html( size_format( $free ) ); ?></p>
						<?php endif; ?>
					</form>
				</div>

				<div class="wpkb-card">
					<h2>2. Backup hochladen</h2>
					<p>Auf dem neuen Server: frisches WordPress installieren, dieses Plugin aktivieren und die <code>.tar</code>-Datei hier hochladen.</p>
					<p><input type="file" id="wpkb-upload-file" accept=".tar"></p>
					<p><button type="button" class="button" id="wpkb-upload-btn">Hochladen</button></p>
					<div class="wpkb-bar" id="wpkb-upload-bar" hidden><span></span></div>
					<p class="description">
						Große Dateien werden in Teilen hochgeladen – Upload-Limits des Servers spielen keine Rolle.
						Alternativ die Datei per FTP in <code><?php echo esc_html( str_replace( ABSPATH, '/', WPKB_BACKUP_DIR ) ); ?>/</code> kopieren.
					</p>
				</div>
			</div>

			<div class="wpkb-card">
				<h2>3. Vorhandene Backups</h2>
				<div id="wpkb-list"><?php self::render_list(); ?></div>
			</div>

			<div class="wpkb-card wpkb-help">
				<h2>So ziehst du eine Seite auf einen anderen Server um</h2>
				<ol>
					<li>Auf der alten Seite ein Backup erstellen und herunterladen.</li>
					<li>Auf dem neuen Server WordPress frisch installieren (beliebiges Tabellen-Präfix) und dieses Plugin installieren.</li>
					<li>Backup hochladen und auf <em>Wiederherstellen</em> klicken.</li>
					<li>Das Plugin passt Domain, Pfade und Tabellen-Präfix automatisch an. Die <code>wp-config.php</code> des neuen Servers bleibt erhalten.</li>
					<li>Danach mit den Zugangsdaten der <strong>alten</strong> Seite anmelden und unter <em>Einstellungen → Permalinks</em> einmal speichern.</li>
				</ol>
				<p class="description">Für sehr große Seiten gibt es WP-CLI-Befehle: <code>wp wx-easy-backup create</code>, <code>wp wx-easy-backup restore &lt;datei&gt;</code>, <code>wp wx-easy-backup list</code>.</p>
			</div>

			<?php if ( $running ) : ?>
				<script>window.WPKB_RUNNING = <?php echo wp_json_encode( array( 'id' => $running['id'], 'type' => $running['type'] ) ); ?>;</script>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function render_list() {
		$list = WPKB_Job::backups();
		if ( ! $list ) {
			echo '<p>Noch keine Backups vorhanden.</p>';
			return;
		}
		echo '<table class="widefat striped wpkb-table"><thead><tr><th>Datei</th><th>Quelle</th><th>Inhalt</th><th>Größe</th><th>Datum</th><th>Aktionen</th></tr></thead><tbody>';
		foreach ( $list as $b ) {
			$m     = $b['manifest'];
			$parts = array();
			if ( $m ) {
				if ( ! empty( $m['includes']['db'] ) ) {
					$parts[] = 'Datenbank';
				}
				if ( ! empty( $m['includes']['content'] ) ) {
					$parts[] = 'wp-content';
				}
				if ( ! empty( $m['includes']['core'] ) ) {
					$parts[] = 'Kerndateien';
				}
			}
			$valid = WPKB_Job::is_own_manifest( $m );
			echo '<tr data-file="' . esc_attr( $b['name'] ) . '"'
				. ' data-db="' . ( ! empty( $m['includes']['db'] ) ? '1' : '0' ) . '"'
				. ' data-content="' . ( ! empty( $m['includes']['content'] ) ? '1' : '0' ) . '"'
				. ' data-core="' . ( ! empty( $m['includes']['core'] ) ? '1' : '0' ) . '"'
				. ' data-source="' . esc_attr( $m['home'] ?? '' ) . '">';
			echo '<td><code>' . esc_html( $b['name'] ) . '</code></td>';
			echo '<td>' . ( $valid ? esc_html( $m['home'] ) . '<br><small>WP ' . esc_html( $m['wp_version'] ) . ' · PHP ' . esc_html( $m['php_version'] ) . '</small>' : '<em>unbekannt / ungültig</em>' ) . '</td>';
			echo '<td>' . esc_html( implode( ', ', $parts ) ) . '</td>';
			echo '<td>' . esc_html( size_format( $b['size'], 1 ) ) . '</td>';
			echo '<td>' . esc_html( wp_date( 'd.m.Y H:i', $b['mtime'] ) ) . '</td>';
			echo '<td class="wpkb-actions">';
			echo '<a class="button" href="' . esc_url( add_query_arg( 'file', rawurlencode( $b['name'] ), wp_nonce_url( admin_url( 'admin-post.php?action=wpkb_download' ), 'wpkb_download' ) ) ) . '">Herunterladen</a> ';
			if ( $valid ) {
				echo '<button type="button" class="button button-primary wpkb-restore">Wiederherstellen</button> ';
			}
			echo '<button type="button" class="button-link-delete wpkb-delete">Löschen</button>';
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/* ------------------------------------------------------------------ */
	/* AJAX                                                                */
	/* ------------------------------------------------------------------ */

	private static function guard() {
		if ( ! current_user_can( self::CAP ) || ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Keine Berechtigung oder Sitzung abgelaufen. Bitte Seite neu laden.' ), 403 );
		}
		@ini_set( 'display_errors', '0' ); // phpcs:ignore – kein HTML in JSON-Antworten
	}

	private static function post( $key, $default = '' ) {
		return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $default; // phpcs:ignore
	}

	public static function ajax_list() {
		self::guard();
		ob_start();
		self::render_list();
		wp_send_json_success( array( 'html' => ob_get_clean() ) );
	}

	public static function ajax_start_backup() {
		self::guard();
		try {
			$job = WPKB_Job::start_backup(
				array(
					'db'      => self::post( 'db' ),
					'content' => self::post( 'content' ),
					'core'    => self::post( 'core' ),
					'exclude' => self::post( 'exclude' ),
				)
			);
			wp_send_json_success( $job->response() );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	public static function ajax_start_restore() {
		self::guard();
		try {
			$job = WPKB_Job::start_restore(
				(string) self::post( 'file' ),
				array(
					'db'    => self::post( 'db' ),
					'files' => self::post( 'files' ),
					'core'  => self::post( 'core' ),
				)
			);
			wp_send_json_success( $job->response() );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	public static function ajax_step() {
		self::guard();
		try {
			$job = WPKB_Job::step( sanitize_key( self::post( 'id' ) ) );
			wp_send_json_success( $job->response() );
		} catch ( Throwable $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	public static function ajax_cancel() {
		self::guard();
		try {
			WPKB_Job::cancel( sanitize_key( self::post( 'id' ) ) );
			wp_send_json_success();
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	public static function ajax_delete() {
		self::guard();
		$path = WPKB_Job::backup_path( self::post( 'file' ) );
		if ( ! $path || ! @unlink( $path ) ) { // phpcs:ignore
			wp_send_json_error( array( 'message' => 'Datei konnte nicht gelöscht werden.' ) );
		}
		wp_send_json_success();
	}

	/**
	 * Gestückelter Upload: Teile werden an eine .part-Datei angehängt.
	 */
	public static function ajax_upload() {
		self::guard();
		try {
			WPKB_Job::ensure_backup_dir();
			$uid    = sanitize_key( self::post( 'upload_id' ) );
			$name   = sanitize_file_name( basename( self::post( 'name' ) ) );
			$offset = (int) self::post( 'offset', 0 );
			$total  = (int) self::post( 'total', 0 );

			if ( ! preg_match( '/^[a-z0-9]{8,32}$/', $uid ) || '.tar' !== strtolower( substr( $name, -4 ) ) || $total <= 0 ) {
				throw new Exception( 'Ungültiger Upload – erlaubt sind nur .tar-Backups dieses Plugins.' );
			}
			if ( empty( $_FILES['chunk']['tmp_name'] ) || ! is_uploaded_file( $_FILES['chunk']['tmp_name'] ) ) { // phpcs:ignore
				throw new Exception( 'Kein Datenteil empfangen (Upload-Limit des Servers zu klein?).' );
			}
			$part = WPKB_BACKUP_DIR . '/upload-' . $uid . '.part';
			clearstatcache( true, $part );
			$have = file_exists( $part ) ? filesize( $part ) : 0;
			if ( 0 === $offset ) {
				$have = 0;
				file_put_contents( $part, '' );
			} elseif ( $have !== $offset ) {
				wp_send_json_error(
					array(
						'message' => 'Versatz stimmt nicht.',
						'expect'  => $have,
					)
				);
			}

			$in  = fopen( $_FILES['chunk']['tmp_name'], 'rb' ); // phpcs:ignore
			$out = fopen( $part, 'ab' );
			stream_copy_to_stream( $in, $out );
			fclose( $in );
			fclose( $out );
			clearstatcache( true, $part );
			$size = filesize( $part );

			if ( $size < $total ) {
				wp_send_json_success( array( 'received' => $size ) );
			}
			if ( $size > $total ) {
				@unlink( $part ); // phpcs:ignore
				throw new Exception( 'Upload ist größer als angekündigt – abgebrochen.' );
			}

			$manifest = WPKB_Tar::read_manifest( $part );
			if ( ! WPKB_Job::is_own_manifest( $manifest ) ) {
				@unlink( $part ); // phpcs:ignore
				throw new Exception( 'Die Datei ist kein gültiges Backup dieses Plugins.' );
			}

			$base = preg_replace( '/[^A-Za-z0-9._-]/', '-', substr( $name, 0, -4 ) );
			if ( ! preg_match( '/[a-z0-9]{10}$/i', $base ) ) {
				$base .= '-' . strtolower( wp_generate_password( 10, false, false ) );
			}
			$target = WPKB_BACKUP_DIR . '/' . $base . '.tar';
			if ( file_exists( $target ) ) {
				$target = WPKB_BACKUP_DIR . '/' . $base . '-' . strtolower( wp_generate_password( 6, false, false ) ) . '.tar';
			}
			rename( $part, $target );
			wp_send_json_success(
				array(
					'done' => true,
					'file' => basename( $target ),
				)
			);
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Download                                                            */
	/* ------------------------------------------------------------------ */

	public static function download() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Keine Berechtigung.', 403 );
		}
		check_admin_referer( 'wpkb_download' );
		$path = WPKB_Job::backup_path( isset( $_GET['file'] ) ? rawurldecode( wp_unslash( $_GET['file'] ) ) : '' ); // phpcs:ignore
		if ( ! $path ) {
			wp_die( 'Datei nicht gefunden.', 404 );
		}
		@set_time_limit( 0 ); // phpcs:ignore
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: application/x-tar' );
		header( 'Content-Disposition: attachment; filename="' . basename( $path ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Accel-Buffering: no' );
		$fh = fopen( $path, 'rb' );
		while ( ! feof( $fh ) ) {
			echo fread( $fh, 1048576 ); // phpcs:ignore
			flush();
		}
		fclose( $fh );
		exit;
	}
}
