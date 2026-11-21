<?php
/**
 * REST-API für Kalender, Tagesansicht und externen Cron-Trigger.
 * Alle Abfragen akzeptieren ?kamera=cam2 (Standard: erste Kamera).
 *
 *   GET /wp-json/wca/v1/cameras                         Liste der Kameras
 *   GET /wp-json/wca/v1/range?kamera=cam1               erstes/letztes Bild
 *   GET /wp-json/wca/v1/days?kamera=cam1&month=2026-11  Bilder je Tag eines Monats
 *   GET /wp-json/wca/v1/images?kamera=cam1&date=2026-11-09
 *   GET /wp-json/wca/v1/latest?kamera=cam1
 *   GET /wp-json/wca/v1/trigger?key=…                   fällige Kameras abrufen (für Server-Cron)
 */

defined( 'ABSPATH' ) || exit;

final class WCA_REST {

	const NS = 'wca/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function can_view() {
		if ( ! WCA_Plugin::options()['require_login'] ) {
			return true;
		}
		return is_user_logged_in()
			? true
			: new WP_Error( 'wca_forbidden', 'Bitte melde dich an, um das Webcam-Archiv zu sehen.', array( 'status' => 401 ) );
	}

	public static function routes() {
		$cam_arg = array(
			'kamera' => array(
				'required'          => false,
				'sanitize_callback' => 'sanitize_key',
			),
		);
		$view    = array( __CLASS__, 'can_view' );

		register_rest_route(
			self::NS,
			'/cameras',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'cameras' ),
				'permission_callback' => $view,
			)
		);

		register_rest_route(
			self::NS,
			'/range',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'range' ),
				'permission_callback' => $view,
				'args'                => $cam_arg,
			)
		);

		register_rest_route(
			self::NS,
			'/days',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'days' ),
				'permission_callback' => $view,
				'args'                => $cam_arg + array(
					'month' => array(
						'required'          => true,
						'validate_callback' => function ( $v ) {
							return (bool) preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', (string) $v );
						},
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/images',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'images' ),
				'permission_callback' => $view,
				'args'                => $cam_arg + array(
					'date' => array(
						'required'          => true,
						'validate_callback' => function ( $v ) {
							return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $v );
						},
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/latest',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'latest' ),
				'permission_callback' => $view,
				'args'                => $cam_arg,
			)
		);

		register_rest_route(
			self::NS,
			'/trigger',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( __CLASS__, 'trigger' ),
				'permission_callback' => function ( WP_REST_Request $req ) {
					$key = (string) WCA_Plugin::options()['trigger_key'];
					return '' !== $key && hash_equals( $key, (string) $req->get_param( 'key' ) );
				},
			)
		);
	}

	/**
	 * Kamera aus der Anfrage ermitteln.
	 *
	 * @return string|WP_Error
	 */
	private static function cam_id( WP_REST_Request $req ) {
		$id = (string) $req->get_param( 'kamera' );
		if ( '' === $id ) {
			return WCA_Plugin::default_camera_id();
		}
		return WCA_Plugin::camera( $id ) ? $id : new WP_Error( 'wca_camera', 'Unbekannte Kamera.', array( 'status' => 404 ) );
	}

	private static function nocache( $data ) {
		$res = rest_ensure_response( $data );
		if ( ! is_wp_error( $data ) ) {
			$res->header( 'Cache-Control', 'no-cache, must-revalidate, max-age=0' );
		}
		return $res;
	}

	public static function cameras() {
		$out = array();
		foreach ( WCA_Plugin::cameras() as $id => $c ) {
			$out[] = array(
				'id'   => $id,
				'name' => $c['name'],
			);
		}
		return self::nocache( array( 'cameras' => $out ) );
	}

	public static function range( WP_REST_Request $req ) {
		$cam = self::cam_id( $req );
		if ( is_wp_error( $cam ) ) {
			return $cam;
		}
		$s = WCA_Archiver::stats( $cam );
		return self::nocache(
			array(
				'camera' => $cam,
				'first'  => $s && $s->first ? $s->first : null,
				'last'   => $s && $s->last ? $s->last : null,
				'count'  => $s ? (int) $s->cnt : 0,
			)
		);
	}

	public static function days( WP_REST_Request $req ) {
		global $wpdb;
		$cam = self::cam_id( $req );
		if ( is_wp_error( $cam ) ) {
			return $cam;
		}
		$t     = WCA_Plugin::table();
		$month = $req->get_param( 'month' );
		$start = $month . '-01 00:00:00';
		$end   = ( new DateTime( $month . '-01' ) )->modify( '+1 month' )->format( 'Y-m-d 00:00:00' );

		$rows = $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare( "SELECT DATE(taken_at) AS d, COUNT(*) AS c FROM {$t} WHERE camera = %s AND taken_at >= %s AND taken_at < %s GROUP BY DATE(taken_at)", $cam, $start, $end ) // phpcs:ignore
		);

		$days = new stdClass();
		foreach ( $rows as $r ) {
			$days->{$r->d} = (int) $r->c;
		}
		return self::nocache(
			array(
				'camera' => $cam,
				'month'  => $month,
				'days'   => $days,
			)
		);
	}

	public static function images( WP_REST_Request $req ) {
		global $wpdb;
		$cam = self::cam_id( $req );
		if ( is_wp_error( $cam ) ) {
			return $cam;
		}
		$t    = WCA_Plugin::table();
		$date = $req->get_param( 'date' );
		$next = gmdate( 'Y-m-d', strtotime( $date . ' +1 day' ) );

		$rows = $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare( "SELECT * FROM {$t} WHERE camera = %s AND taken_at >= %s AND taken_at < %s ORDER BY taken_at ASC, id ASC", $cam, $date . ' 00:00:00', $next . ' 00:00:00' ) // phpcs:ignore
		);

		return self::nocache(
			array(
				'camera' => $cam,
				'date'   => $date,
				'images' => array_map( array( 'WCA_Archiver', 'format_row' ), $rows ),
			)
		);
	}

	public static function latest( WP_REST_Request $req ) {
		$cam = self::cam_id( $req );
		if ( is_wp_error( $cam ) ) {
			return $cam;
		}
		return self::nocache(
			array(
				'camera' => $cam,
				'image'  => WCA_Archiver::latest( $cam ),
			)
		);
	}

	public static function trigger() {
		return self::nocache( array( 'result' => WCA_Archiver::run_scheduled( false ) ) );
	}
}
