<?php
/**
 * Kernklasse: Optionen, Kameras, Datenbank, Cron, Log.
 */

defined( 'ABSPATH' ) || exit;

final class WCA_Plugin {

	const OPTION       = 'wca_options';
	const STATE        = 'wca_state';
	const CRON_CAPTURE = 'wca_capture_event';
	const CRON_CLEANUP = 'wca_cleanup_event';
	const DB_VERSION   = '2';

	/**
	 * Allgemeine Einstellungen.
	 */
	public static function defaults() {
		return array(
			'cameras'        => array(),
			'jpeg_quality'   => 85,
			'thumb_width'    => 320,
			'retention_days' => 0,   // 0 = unbegrenzt
			'require_login'  => 0,
			'week_start'     => 1,   // 1 = Montag, 0 = Sonntag
			'trigger_key'    => '',
			'colors'         => self::color_defaults(),
		);
	}

	/**
	 * Farbfelder: Schlüssel => [ CSS-Variable, Bezeichnung, Standard (LSV-Blau) ].
	 */
	public static function color_fields() {
		return array(
			'page'        => array( '--wca-page', 'Hintergrund', '#486d97' ),
			'card'        => array( '--wca-card', 'Karten', '#2c4767' ),
			'inner'       => array( '--wca-inner', 'Felder & Schaltflächen', '#385879' ),
			'line'        => array( '--wca-line', 'Akzentlinien', '#8fb3dc' ),
			'accent'      => array( '--wca-accent', 'Auswahl (Tag, Kamera, Regler)', '#8fb3dc' ),
			'accent_text' => array( '--wca-accent-text', 'Schrift auf Auswahl', '#14273d' ),
			'header'      => array( '--wca-header', 'Kopfzeile', '#ffffff' ),
			'title'       => array( '--wca-title', 'Kartenüberschriften', '#d3e3f4' ),
			'text'        => array( '--wca-text', 'Werte & Text', '#f2f6fa' ),
			'muted'       => array( '--wca-muted', 'Beschriftungen', '#b6c7da' ),
		);
	}

	public static function color_defaults() {
		return array_map(
			function ( $f ) {
				return $f[2];
			},
			self::color_fields()
		);
	}

	/**
	 * Einstellungen je Kamera.
	 */
	public static function camera_defaults() {
		return array(
			'id'                  => '',
			'name'                => 'Webcam',
			'source_mode'         => 'url',   // url | folder | both
			'source_url'          => '',
			'http_user'           => '',
			'http_pass'           => '',
			'timeout'             => 20,
			'interval'            => 15,      // Minuten
			'time_from'           => '00:00',
			'time_to'             => '23:59',
			'skip_duplicates'     => 1,
			'import_dir'          => '',
			'delete_after_import' => 0,
			'max_width'           => 0,       // 0 = Original behalten
			// Nachtfilter
			'night_sun'           => 0,       // nachts (Sonnenstand) gar nicht abrufen
			'lat'                 => '',
			'lon'                 => '',
			'sun_offset'          => 30,      // Minuten vor Sonnenaufgang / nach Sonnenuntergang noch aufnehmen
			'night_brightness'    => 0,       // dunkle Bilder verwerfen
			'night_threshold'     => 12,      // mittlere Helligkeit in % (0–100)
			'night_gray'          => 0,       // Infrarot-/Schwarzweißbilder als Nacht werten
		);
	}

	/**
	 * Alle Einstellungen; 'cameras' ist nach Kamera-ID indiziert.
	 */
	public static function options() {
		$o = get_option( self::OPTION, array() );
		$o = is_array( $o ) ? $o : array();

		// Einstellungen aus Version 1.0 (nur eine Kamera) übernehmen.
		if ( ! isset( $o['cameras'] ) ) {
			$cam         = array_intersect_key( $o, self::camera_defaults() );
			$cam['id']   = 'cam1';
			$cam['name'] = 'Webcam 1';
			$o['cameras'] = array( $cam );
		}

		$o           = wp_parse_args( $o, self::defaults() );
		$o['colors'] = wp_parse_args( is_array( $o['colors'] ) ? $o['colors'] : array(), self::color_defaults() );
		$cams = array();
		foreach ( (array) $o['cameras'] as $c ) {
			$c = wp_parse_args( (array) $c, self::camera_defaults() );
			if ( '' !== $c['id'] ) {
				$cams[ $c['id'] ] = $c;
			}
		}
		if ( ! $cams ) {
			$cams['cam1'] = array_merge( self::camera_defaults(), array( 'id' => 'cam1', 'name' => 'Webcam 1' ) );
		}
		$o['cameras'] = $cams;
		return $o;
	}

	public static function cameras() {
		return self::options()['cameras'];
	}

	public static function camera( $id ) {
		$cams = self::cameras();
		return ( $id && isset( $cams[ $id ] ) ) ? $cams[ $id ] : null;
	}

	public static function default_camera_id() {
		$cams = self::cameras();
		reset( $cams );
		return key( $cams );
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'wca_images';
	}

	/* ---------------------------------------------------------------------
	 * Laufzeit-Status je Kamera (letzter Lauf, letzter Hash …)
	 * ------------------------------------------------------------------ */

	public static function state( $cam_id ) {
		$s = get_option( self::STATE, array() );
		return ( is_array( $s ) && isset( $s[ $cam_id ] ) ) ? (array) $s[ $cam_id ] : array();
	}

	public static function set_state( $cam_id, array $values ) {
		$s = get_option( self::STATE, array() );
		$s = is_array( $s ) ? $s : array();
		$s[ $cam_id ] = array_merge( isset( $s[ $cam_id ] ) ? (array) $s[ $cam_id ] : array(), $values );
		update_option( self::STATE, $s, false );
	}

	/* ---------------------------------------------------------------------
	 * Initialisierung
	 * ------------------------------------------------------------------ */

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( self::CRON_CAPTURE, array( 'WCA_Archiver', 'run_scheduled' ) );
		add_action( self::CRON_CLEANUP, array( 'WCA_Archiver', 'cleanup' ) );

		if ( get_option( 'wca_db_version' ) !== self::DB_VERSION ) {
			self::upgrade();
		}

		WCA_REST::init();
		WCA_Frontend::init();
		if ( is_admin() ) {
			WCA_Admin::init();
		}
	}

	public static function activate() {
		self::upgrade();

		$o = self::options();
		if ( empty( $o['trigger_key'] ) ) {
			$o['trigger_key'] = wp_generate_password( 32, false );
		}
		self::save_options( $o );

		wp_mkdir_p( WCA_Archiver::base_dir() );

		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		self::schedule();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_CAPTURE );
		wp_clear_scheduled_hook( self::CRON_CLEANUP );
	}

	/**
	 * Speichert Optionen in der gespeicherten Form (Kameras als Liste).
	 */
	public static function save_options( array $o ) {
		$clean            = array_intersect_key( $o, self::defaults() );
		$clean['cameras'] = array_values( $o['cameras'] );
		update_option( self::OPTION, $clean );
	}

	/**
	 * Datenbank anlegen bzw. aktualisieren und alte Daten migrieren.
	 */
	public static function upgrade() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			camera varchar(40) NOT NULL DEFAULT '',
			taken_at datetime NOT NULL,
			file varchar(255) NOT NULL,
			thumb varchar(255) NOT NULL DEFAULT '',
			width int(10) unsigned NOT NULL DEFAULT 0,
			height int(10) unsigned NOT NULL DEFAULT 0,
			filesize bigint(20) unsigned NOT NULL DEFAULT 0,
			hash char(32) NOT NULL DEFAULT '',
			source varchar(20) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY camera_taken (camera,taken_at),
			KEY taken_at (taken_at),
			KEY hash (hash)
		) {$charset};";
		dbDelta( $sql );

		// Migration 1.0 → 1.1: bestehende Bilder gehören zu "cam1".
		$raw = get_option( self::OPTION, null );
		if ( is_array( $raw ) && ! isset( $raw['cameras'] ) ) {
			self::save_options( self::options() );
			$old_hash = get_option( 'wca_last_hash' );
			if ( $old_hash ) {
				self::set_state( 'cam1', array( 'hash' => $old_hash ) );
			}
			delete_option( 'wca_last_hash' );
		}
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET camera = %s WHERE camera = ''", self::default_camera_id() ) ); // phpcs:ignore

		update_option( 'wca_db_version', self::DB_VERSION, false );

		// Ab 1.1 läuft der Cron minütlich und prüft selbst, welche Kamera fällig ist.
		if ( function_exists( 'wp_get_schedule' ) && wp_get_schedule( self::CRON_CAPTURE ) && 'wca_minute' !== wp_get_schedule( self::CRON_CAPTURE ) ) {
			add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
			self::schedule();
		}
	}

	/* ---------------------------------------------------------------------
	 * Cron
	 * ------------------------------------------------------------------ */

	public static function cron_schedules( $schedules ) {
		$schedules['wca_minute'] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => 'WX Webcam Archive: jede Minute',
		);
		return $schedules;
	}

	public static function schedule() {
		wp_clear_scheduled_hook( self::CRON_CAPTURE );
		wp_schedule_event( time() + 30, 'wca_minute', self::CRON_CAPTURE );

		if ( ! wp_next_scheduled( self::CRON_CLEANUP ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_CLEANUP );
		}
	}

	public static function ensure_scheduled() {
		if ( 'wca_minute' !== wp_get_schedule( self::CRON_CAPTURE ) || ! wp_next_scheduled( self::CRON_CLEANUP ) ) {
			self::schedule();
		}
	}

	/* ---------------------------------------------------------------------
	 * Log (die letzten 80 Einträge)
	 * ------------------------------------------------------------------ */

	public static function log( $level, $message, $cam = null ) {
		if ( is_array( $cam ) ) {
			$message = '[' . $cam['name'] . '] ' . $message;
		}
		$log = get_option( 'wca_log', array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		array_unshift(
			$log,
			array(
				'time'  => current_time( 'mysql' ),
				'level' => $level,
				'msg'   => $message,
			)
		);
		update_option( 'wca_log', array_slice( $log, 0, 80 ), false );
	}

	public static function now() {
		return new DateTimeImmutable( 'now', wp_timezone() );
	}
}
