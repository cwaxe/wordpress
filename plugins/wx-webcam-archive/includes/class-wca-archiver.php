<?php
/**
 * Abruf, Import, Speicherung und Bereinigung der Webcam-Bilder.
 */

defined( 'ABSPATH' ) || exit;

final class WCA_Archiver {

	const SUBDIR       = 'webcam-archiv';
	const IMPORT_LIMIT = 300; // max. Dateien pro Import-Durchlauf und Kamera

	/* ---------------------------------------------------------------------
	 * Pfade
	 * ------------------------------------------------------------------ */

	public static function base_dir() {
		$u = wp_upload_dir( null, false );
		return trailingslashit( $u['basedir'] ) . self::SUBDIR;
	}

	public static function path_for( $rel ) {
		$u = wp_upload_dir( null, false );
		return trailingslashit( $u['basedir'] ) . ltrim( $rel, '/' );
	}

	public static function url_for( $rel ) {
		if ( ! $rel ) {
			return '';
		}
		$u = wp_upload_dir( null, false );
		return set_url_scheme( trailingslashit( $u['baseurl'] ) . ltrim( $rel, '/' ) );
	}

	/* ---------------------------------------------------------------------
	 * Geplanter Lauf
	 * ------------------------------------------------------------------ */

	/**
	 * Läuft minütlich (WP-Cron oder Trigger-URL) und arbeitet alle fälligen Kameras ab.
	 *
	 * @param bool        $force  Intervall und Zeitfenster ignorieren.
	 * @param string|null $only   Nur diese Kamera.
	 * @return array Ergebnis je Kamera.
	 */
	public static function run_scheduled( $force = false, $only = null ) {
		$force   = ( true === $force );
		$results = array();

		foreach ( WCA_Plugin::cameras() as $id => $cam ) {
			if ( $only && $only !== $id ) {
				continue;
			}
			if ( ! $force ) {
				if ( ! self::is_due( $cam ) ) {
					continue;
				}
				if ( ! self::in_time_window( $cam ) ) {
					WCA_Plugin::set_state( $id, array( 'slot' => self::slot( $cam ) ) );
					continue;
				}
			}
			$results[ $id ] = self::run_camera( $cam );
		}

		update_option( 'wca_last_run', current_time( 'mysql' ), false );
		return $results;
	}

	private static function slot( $cam ) {
		return (int) floor( time() / ( max( 1, (int) $cam['interval'] ) * MINUTE_IN_SECONDS ) );
	}

	/**
	 * Fällig, sobald ein neues Intervall-Fenster begonnen hat (ausgerichtet auf :00, :15, … in UTC).
	 */
	public static function is_due( $cam ) {
		$state = WCA_Plugin::state( $cam['id'] );
		return self::slot( $cam ) > (int) ( $state['slot'] ?? -1 );
	}

	public static function in_time_window( $cam ) {
		$now  = WCA_Plugin::now()->format( 'H:i' );
		$from = $cam['time_from'];
		$to   = $cam['time_to'];
		if ( $from <= $to ) {
			return $now >= $from && $now <= $to;
		}
		return $now >= $from || $now <= $to; // über Mitternacht
	}

	/**
	 * Eine Kamera abarbeiten (je nach Quelle URL und/oder Ordner).
	 */
	public static function run_camera( $cam ) {
		$lock = 'wca_lock_' . $cam['id'];
		if ( get_transient( $lock ) ) {
			return array( 'skipped' => 'Ein anderer Lauf ist noch aktiv.' );
		}
		set_transient( $lock, 1, 5 * MINUTE_IN_SECONDS );
		WCA_Plugin::set_state(
			$cam['id'],
			array(
				'slot'     => self::slot( $cam ),
				'last_run' => current_time( 'mysql' ),
			)
		);

		$out = array();
		if ( in_array( $cam['source_mode'], array( 'url', 'both' ), true ) ) {
			if ( $cam['night_sun'] && ! self::is_daylight( $cam, WCA_Plugin::now() ) ) {
				// Nachts gar nicht erst bei der Kamera anfragen.
				self::note_night( $cam, 'Sonnenstand' );
				$out['url'] = 'Nacht (Sonnenstand) – nicht abgerufen';
			} else {
				$r          = self::capture_from_url( $cam );
				$out['url'] = is_wp_error( $r ) ? $r->get_error_message() : $r;
			}
		}
		if ( in_array( $cam['source_mode'], array( 'folder', 'both' ), true ) ) {
			$r             = self::import_folder( $cam );
			$out['folder'] = is_wp_error( $r ) ? $r->get_error_message() : $r;
		}

		delete_transient( $lock );
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Quelle 1: Bild-URL
	 * ------------------------------------------------------------------ */

	/**
	 * @param array $cam    Kamera.
	 * @param bool  $manual Manueller Abruf aus dem Admin (meldet die Helligkeitsmessung).
	 */
	public static function capture_from_url( $cam, $manual = false ) {
		$url = trim( $cam['source_url'] );
		if ( '' === $url ) {
			return new WP_Error( 'wca_no_url', 'Keine Bild-URL konfiguriert.' );
		}

		$args = array(
			'timeout'     => max( 5, (int) $cam['timeout'] ),
			'redirection' => 3,
			'headers'     => array(
				'Cache-Control' => 'no-cache',
				'Pragma'        => 'no-cache',
			),
			'user-agent'  => 'WordPress WX Webcam Archive/' . WCA_VERSION,
		);
		if ( '' !== $cam['http_user'] ) {
			$args['headers']['Authorization'] = 'Basic ' . base64_encode( $cam['http_user'] . ':' . $cam['http_pass'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		}

		$res = wp_remote_get( $url, $args );
		if ( is_wp_error( $res ) ) {
			WCA_Plugin::log( 'error', 'Abruf fehlgeschlagen: ' . $res->get_error_message(), $cam );
			return $res;
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( 200 !== $code ) {
			$err = new WP_Error( 'wca_http', sprintf( 'Kamera antwortete mit HTTP %d.', $code ) );
			WCA_Plugin::log( 'error', $err->get_error_message(), $cam );
			return $err;
		}

		$body  = wp_remote_retrieve_body( $res );
		$hash  = md5( $body );
		$state = WCA_Plugin::state( $cam['id'] );

		if ( $cam['skip_duplicates'] && ( $state['hash'] ?? '' ) === $hash ) {
			WCA_Plugin::log( 'info', 'Bild unverändert seit letztem Abruf – übersprungen.', $cam );
			return 'unverändert';
		}

		$m       = null;
		$night   = self::night_reason( $cam, $body, $m );
		$measure = $m ? sprintf( ' (Helligkeit %s %%, Farbe %s %%)', number_format_i18n( $m['brightness'], 1 ), number_format_i18n( $m['color'], 1 ) ) : '';

		if ( $night ) {
			WCA_Plugin::set_state( $cam['id'], array( 'hash' => $hash ) );
			self::note_night( $cam, $night . $measure );
			return 'Nachtbild verworfen: ' . $night . $measure;
		}
		self::note_day( $cam );

		$r = self::store( $body, WCA_Plugin::now(), $cam, 'url', $hash );
		if ( is_wp_error( $r ) ) {
			WCA_Plugin::log( 'error', $r->get_error_message(), $cam );
			return $r;
		}
		WCA_Plugin::log( 'ok', 'Bild gespeichert: ' . $r['file'] . ( $manual ? $measure : '' ), $cam );
		return 'gespeichert' . $measure;
	}

	/* ---------------------------------------------------------------------
	 * Quelle 2: Import-Ordner (z. B. FTP-Upload der Kamera)
	 * ------------------------------------------------------------------ */

	public static function import_folder( $cam ) {
		$dir = untrailingslashit( wp_normalize_path( $cam['import_dir'] ) );

		if ( '' === $dir ) {
			return new WP_Error( 'wca_no_dir', 'Kein Import-Ordner konfiguriert.' );
		}
		if ( ! is_dir( $dir ) || ! is_readable( $dir ) ) {
			$err = new WP_Error( 'wca_dir', 'Import-Ordner nicht lesbar: ' . $dir );
			WCA_Plugin::log( 'error', $err->get_error_message(), $cam );
			return $err;
		}

		$files = array();
		try {
			$it = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS )
			);
			foreach ( $it as $f ) {
				/** @var SplFileInfo $f */
				if ( ! $f->isFile() || ! preg_match( '/\.(jpe?g|png|webp)$/i', $f->getFilename() ) ) {
					continue;
				}
				if ( $f->getMTime() > time() - 10 ) {
					continue; // wird evtl. noch hochgeladen
				}
				$files[ $f->getPathname() ] = $f->getMTime();
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'wca_dir', $e->getMessage() );
		}

		asort( $files );
		$files = array_slice( array_keys( $files ), 0, self::IMPORT_LIMIT );

		$imported = 0;
		$skipped  = 0;
		$failed   = 0;
		$nights   = 0;

		// Bereits als Nachtbild verworfene Dateien merken, damit sie nicht bei jedem Lauf neu geprüft werden.
		$rej_key  = 'wca_rejected_' . $cam['id'];
		$rejected = get_option( $rej_key, array() );
		$rejected = is_array( $rejected ) ? $rejected : array();
		$rej_new  = false;

		foreach ( $files as $path ) {
			$data = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( false === $data ) {
				++$failed;
				continue;
			}
			$hash = md5( $data );

			if ( isset( $rejected[ $hash ] ) || self::hash_exists( $hash, $cam['id'] ) ) {
				++$skipped;
			} else {
				$taken = self::detect_time( $path );
				$night = ( $cam['night_sun'] && ! self::is_daylight( $cam, $taken ) ) ? 'Sonnenstand' : self::night_reason( $cam, $data );
				if ( $night ) {
					++$nights;
					$rejected[ $hash ] = 1;
					$rej_new           = true;
					if ( $cam['delete_after_import'] ) {
						wp_delete_file( $path );
					}
					continue;
				}

				$r = self::store( $data, $taken, $cam, 'folder', $hash );
				if ( is_wp_error( $r ) ) {
					++$failed;
					WCA_Plugin::log( 'error', basename( $path ) . ': ' . $r->get_error_message(), $cam );
					continue;
				}
				++$imported;
			}

			if ( $cam['delete_after_import'] ) {
				wp_delete_file( $path );
			}
		}

		if ( $rej_new ) {
			update_option( $rej_key, array_slice( $rejected, -3000, null, true ), false );
		}

		if ( $imported || $failed || $nights ) {
			WCA_Plugin::log( $failed ? 'error' : 'ok', sprintf( 'Ordner-Import: %d importiert, %d Nachtbilder verworfen, %d bereits vorhanden, %d Fehler.', $imported, $nights, $skipped, $failed ), $cam );
		}
		return sprintf( '%d importiert, %d Nachtbilder verworfen, %d übersprungen, %d Fehler', $imported, $nights, $skipped, $failed );
	}

	/* ---------------------------------------------------------------------
	 * Nachtfilter
	 * ------------------------------------------------------------------ */

	/**
	 * Ist es am Kamerastandort hell genug (zwischen Sonnenaufgang − Puffer und Sonnenuntergang + Puffer)?
	 */
	public static function is_daylight( $cam, DateTimeImmutable $when ) {
		if ( '' === (string) $cam['lat'] || '' === (string) $cam['lon'] ) {
			return true; // ohne Standort keine Einschränkung
		}
		// Mittag des betreffenden Tages verwenden, damit Auf- und Untergang zum selben Kalendertag gehören.
		$noon = $when->setTime( 12, 0 )->getTimestamp();
		$sun  = date_sun_info( $noon, (float) $cam['lat'], (float) $cam['lon'] );

		if ( true === $sun['sunrise'] || true === $sun['sunset'] ) {
			return true;  // Mitternachtssonne
		}
		if ( false === $sun['sunrise'] || false === $sun['sunset'] ) {
			return false; // Polarnacht
		}

		$buf = (int) $cam['sun_offset'] * MINUTE_IN_SECONDS;
		$t   = $when->getTimestamp();
		return $t >= $sun['sunrise'] - $buf && $t <= $sun['sunset'] + $buf;
	}

	/**
	 * Sonnenauf- und -untergang für heute (für die Anzeige im Admin).
	 *
	 * @return array|null [ 'from' => 'H:i', 'to' => 'H:i' ] inkl. Puffer.
	 */
	public static function sun_times( $cam ) {
		if ( '' === (string) $cam['lat'] || '' === (string) $cam['lon'] ) {
			return null;
		}
		$now = WCA_Plugin::now();
		$sun = date_sun_info( $now->setTime( 12, 0 )->getTimestamp(), (float) $cam['lat'], (float) $cam['lon'] );
		if ( ! is_int( $sun['sunrise'] ) || ! is_int( $sun['sunset'] ) ) {
			return null;
		}
		$buf = (int) $cam['sun_offset'] * MINUTE_IN_SECONDS;
		$tz  = wp_timezone();
		return array(
			'from' => ( new DateTimeImmutable( '@' . ( $sun['sunrise'] - $buf ) ) )->setTimezone( $tz )->format( 'H:i' ),
			'to'   => ( new DateTimeImmutable( '@' . ( $sun['sunset'] + $buf ) ) )->setTimezone( $tz )->format( 'H:i' ),
		);
	}

	/**
	 * Mittlere Helligkeit und Farbsättigung eines Bildes messen (je 0–100 %).
	 *
	 * @return array|null [ 'brightness' => float, 'color' => float ] oder null, wenn GD fehlt.
	 */
	public static function measure( $data ) {
		if ( ! function_exists( 'imagecreatefromstring' ) ) {
			return null;
		}
		$src = @imagecreatefromstring( $data ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! $src ) {
			return null;
		}
		$n   = 48;
		$dst = imagecreatetruecolor( $n, $n );
		imagecopyresampled( $dst, $src, 0, 0, 0, 0, $n, $n, imagesx( $src ), imagesy( $src ) );
		imagedestroy( $src );

		$luma = 0;
		$sat  = 0;
		for ( $x = 0; $x < $n; $x++ ) {
			for ( $y = 0; $y < $n; $y++ ) {
				$rgb   = imagecolorat( $dst, $x, $y );
				$r     = ( $rgb >> 16 ) & 0xFF;
				$g     = ( $rgb >> 8 ) & 0xFF;
				$b     = $rgb & 0xFF;
				$luma += 0.299 * $r + 0.587 * $g + 0.114 * $b;
				$sat  += max( $r, $g, $b ) - min( $r, $g, $b );
			}
		}
		imagedestroy( $dst );

		$px = $n * $n * 2.55;
		return array(
			'brightness' => round( $luma / $px, 1 ),
			'color'      => round( $sat / $px, 1 ),
		);
	}

	/**
	 * Prüft per Helligkeit/Farbe, ob ein Bild ein Nachtbild ist.
	 *
	 * @param array      $cam  Kamera.
	 * @param string     $data Bilddaten.
	 * @param array|null $m    Messwerte (Ausgabe).
	 * @return string Grund oder '' (= Tagbild).
	 */
	public static function night_reason( $cam, $data, &$m = null ) {
		$m = null;
		if ( ! $cam['night_brightness'] && ! $cam['night_gray'] ) {
			return '';
		}
		$m = self::measure( $data );
		if ( ! $m ) {
			return ''; // ohne GD lieber speichern als verwerfen
		}
		WCA_Plugin::set_state( $cam['id'], array( 'measure' => $m + array( 'time' => current_time( 'mysql' ) ) ) );

		if ( $cam['night_brightness'] && $m['brightness'] < (float) $cam['night_threshold'] ) {
			return 'zu dunkel';
		}
		if ( $cam['night_gray'] && $m['color'] < 2.0 ) {
			return 'Infrarot/Schwarzweiß';
		}
		return '';
	}

	/**
	 * Nur beim Wechsel Tag → Nacht ins Protokoll schreiben (sonst füllt sich das Log jede Nacht).
	 */
	private static function note_night( $cam, $why ) {
		$state = WCA_Plugin::state( $cam['id'] );
		if ( empty( $state['night'] ) ) {
			WCA_Plugin::log( 'info', 'Nachtpause beginnt – Bilder werden nicht archiviert (' . $why . ').', $cam );
			WCA_Plugin::set_state( $cam['id'], array( 'night' => 1 ) );
		}
	}

	private static function note_day( $cam ) {
		$state = WCA_Plugin::state( $cam['id'] );
		if ( ! empty( $state['night'] ) ) {
			WCA_Plugin::log( 'info', 'Nachtpause beendet – Archivierung läuft wieder.', $cam );
			WCA_Plugin::set_state( $cam['id'], array( 'night' => 0 ) );
		}
	}

	/**
	 * Aufnahmezeit ermitteln: EXIF → Dateiname → Änderungsdatum.
	 */
	public static function detect_time( $path ) {
		$tz = wp_timezone();

		if ( function_exists( 'exif_read_data' ) && preg_match( '/\.jpe?g$/i', $path ) ) {
			$exif = @exif_read_data( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( ! empty( $exif['DateTimeOriginal'] ) ) {
				$d = DateTimeImmutable::createFromFormat( 'Y:m:d H:i:s', $exif['DateTimeOriginal'], $tz );
				if ( $d ) {
					return $d;
				}
			}
		}

		// z. B. 20261109_125300.jpg, 2026-11-09-12-53-00.jpg, 2026/11/09/12-53-07.jpg
		$name = wp_normalize_path( $path );
		if ( preg_match( '/(20\d{2})[-_.\/]?(\d{2})[-_.\/]?(\d{2})[-_T .\/]?(\d{2})[-_.:h]?(\d{2})(?:[-_.:m]?(\d{2}))?/', $name, $m ) ) {
			$str = sprintf( '%s-%s-%s %s:%s:%s', $m[1], $m[2], $m[3], $m[4], $m[5], isset( $m[6] ) && '' !== $m[6] ? $m[6] : '00' );
			$d   = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $str, $tz );
			if ( $d && $d->format( 'Y-m-d H:i:s' ) === $str ) {
				return $d;
			}
		}

		return ( new DateTimeImmutable( '@' . filemtime( $path ) ) )->setTimezone( $tz );
	}

	/* ---------------------------------------------------------------------
	 * Speicherung
	 * ------------------------------------------------------------------ */

	/**
	 * @return array|WP_Error Datensatz.
	 */
	public static function store( $data, DateTimeImmutable $taken, $cam, $source, $hash ) {
		global $wpdb;
		$o = WCA_Plugin::options();

		$info = @getimagesizefromstring( $data ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! $info ) {
			return new WP_Error( 'wca_noimage', 'Die Antwort ist kein gültiges Bild.' );
		}
		$types = array(
			IMAGETYPE_JPEG => 'jpg',
			IMAGETYPE_PNG  => 'png',
		);
		if ( defined( 'IMAGETYPE_WEBP' ) ) {
			$types[ IMAGETYPE_WEBP ] = 'webp';
		}
		if ( ! isset( $types[ $info[2] ] ) ) {
			return new WP_Error( 'wca_type', 'Bildformat wird nicht unterstützt (erlaubt: JPG, PNG, WebP).' );
		}
		$ext = $types[ $info[2] ];

		$dir_rel = self::SUBDIR . '/' . $cam['id'] . '/' . $taken->format( 'Y/m/d' );
		$dir     = self::path_for( $dir_rel );
		if ( ! wp_mkdir_p( $dir . '/thumbs' ) ) {
			return new WP_Error( 'wca_mkdir', 'Verzeichnis konnte nicht angelegt werden: ' . $dir );
		}

		$base = $taken->format( 'His' );
		$name = $base;
		$i    = 1;
		while ( file_exists( "{$dir}/{$name}.{$ext}" ) ) {
			$name = $base . '-' . $i++;
		}
		$file_rel = "{$dir_rel}/{$name}.{$ext}";
		$path     = self::path_for( $file_rel );

		if ( false === file_put_contents( $path, $data ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			return new WP_Error( 'wca_write', 'Bild konnte nicht gespeichert werden: ' . $path );
		}

		$width  = (int) $info[0];
		$height = (int) $info[1];

		// Optional verkleinern.
		$max_w = (int) $cam['max_width'];
		if ( $max_w > 0 && $width > $max_w ) {
			$ed = wp_get_image_editor( $path );
			if ( ! is_wp_error( $ed ) ) {
				$ed->set_quality( (int) $o['jpeg_quality'] );
				$ed->resize( $max_w, null, false );
				$saved = $ed->save( $path );
				if ( ! is_wp_error( $saved ) ) {
					$width  = (int) $saved['width'];
					$height = (int) $saved['height'];
				}
			}
		}

		// Vorschaubild.
		$thumb_rel = '';
		$ed        = wp_get_image_editor( $path );
		if ( ! is_wp_error( $ed ) ) {
			$ed->set_quality( 75 );
			$ed->resize( (int) $o['thumb_width'], null, false );
			$t_rel = "{$dir_rel}/thumbs/{$name}.jpg";
			$saved = $ed->save( self::path_for( $t_rel ), 'image/jpeg' );
			if ( ! is_wp_error( $saved ) ) {
				$thumb_rel = $t_rel;
			}
		}

		clearstatcache( true, $path );
		$row = array(
			'camera'   => $cam['id'],
			'taken_at' => $taken->format( 'Y-m-d H:i:s' ),
			'file'     => $file_rel,
			'thumb'    => $thumb_rel,
			'width'    => $width,
			'height'   => $height,
			'filesize' => (int) filesize( $path ),
			'hash'     => $hash,
			'source'   => $source,
		);
		$wpdb->insert( WCA_Plugin::table(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		WCA_Plugin::set_state( $cam['id'], array( 'hash' => $hash ) );
		$row['id'] = (int) $wpdb->insert_id;
		return $row;
	}

	public static function hash_exists( $hash, $cam_id ) {
		global $wpdb;
		$t = WCA_Plugin::table();
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE hash = %s AND camera = %s LIMIT 1", $hash, $cam_id ) ); // phpcs:ignore
	}

	/* ---------------------------------------------------------------------
	 * Löschen / Bereinigung
	 * ------------------------------------------------------------------ */

	/**
	 * Alte Bilder aller Kameras gemäß Aufbewahrungsdauer entfernen.
	 */
	public static function cleanup() {
		$days = (int) WCA_Plugin::options()['retention_days'];
		if ( $days <= 0 ) {
			return 0;
		}
		$cutoff = WCA_Plugin::now()->modify( "-{$days} days" )->format( 'Y-m-d 00:00:00' );
		$n      = self::delete_where( 'taken_at < %s', array( $cutoff ) );
		if ( $n ) {
			WCA_Plugin::log( 'info', sprintf( 'Bereinigung: %d Bilder älter als %d Tage gelöscht.', $n, $days ) );
		}
		return $n;
	}

	/**
	 * Alle Bilder einer Kamera an einem Tag löschen (YYYY-MM-DD).
	 */
	public static function delete_day( $date, $cam_id ) {
		$next = gmdate( 'Y-m-d', strtotime( $date . ' +1 day' ) );
		$n    = self::delete_where( 'camera = %s AND taken_at >= %s AND taken_at < %s', array( $cam_id, $date . ' 00:00:00', $next . ' 00:00:00' ) );
		WCA_Plugin::log( 'info', sprintf( '%d Bilder vom %s manuell gelöscht.', $n, $date ), WCA_Plugin::camera( $cam_id ) );
		return $n;
	}

	/**
	 * Alle Bilder einer Kamera löschen.
	 */
	public static function delete_camera_images( $cam_id ) {
		return self::delete_where( 'camera = %s', array( $cam_id ) );
	}

	private static function delete_where( $where, $params ) {
		global $wpdb;
		$t     = WCA_Plugin::table();
		$total = 0;
		$dirs  = array();

		do {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, file, thumb FROM {$t} WHERE {$where} ORDER BY id LIMIT 500", $params ) );
			if ( ! $rows ) {
				break;
			}
			$ids = array();
			foreach ( $rows as $r ) {
				foreach ( array( $r->file, $r->thumb ) as $rel ) {
					if ( $rel ) {
						wp_delete_file( self::path_for( $rel ) );
					}
				}
				$dirs[ dirname( self::path_for( $r->file ) ) ] = true;
				$ids[] = (int) $r->id;
			}
			$wpdb->query( "DELETE FROM {$t} WHERE id IN (" . implode( ',', $ids ) . ')' ); // phpcs:ignore
			$total += count( $ids );
		} while ( count( $rows ) === 500 );

		// Leere Verzeichnisse aufräumen (Tag → Monat → Jahr → Kamera).
		$base = wp_normalize_path( self::base_dir() );
		foreach ( array_keys( $dirs ) as $dir ) {
			@rmdir( $dir . '/thumbs' ); // phpcs:ignore
			$d = wp_normalize_path( $dir );
			while ( strlen( $d ) > strlen( $base ) && @rmdir( $d ) ) { // phpcs:ignore
				$d = dirname( $d );
			}
		}
		return $total;
	}

	/* ---------------------------------------------------------------------
	 * Abfragen
	 * ------------------------------------------------------------------ */

	public static function format_row( $r ) {
		return array(
			'id'    => (int) $r->id,
			'date'  => substr( $r->taken_at, 0, 10 ),
			'time'  => substr( $r->taken_at, 11, 8 ),
			'full'  => self::url_for( $r->file ),
			'thumb' => $r->thumb ? self::url_for( $r->thumb ) : self::url_for( $r->file ),
			'w'     => (int) $r->width,
			'h'     => (int) $r->height,
		);
	}

	public static function latest( $cam_id ) {
		global $wpdb;
		$t = WCA_Plugin::table();
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE camera = %s ORDER BY taken_at DESC, id DESC LIMIT 1", $cam_id ) ); // phpcs:ignore
		return $r ? self::format_row( $r ) : null;
	}

	public static function stats( $cam_id ) {
		global $wpdb;
		$t = WCA_Plugin::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS cnt, MIN(taken_at) AS first, MAX(taken_at) AS last, COALESCE(SUM(filesize),0) AS bytes FROM {$t} WHERE camera = %s", $cam_id ) ); // phpcs:ignore
	}

	/**
	 * Alle Kamera-IDs, zu denen es Bilder gibt (auch bereits entfernte Kameras).
	 */
	public static function camera_ids_in_db() {
		global $wpdb;
		$t = WCA_Plugin::table();
		return (array) $wpdb->get_col( "SELECT DISTINCT camera FROM {$t}" ); // phpcs:ignore
	}
}
