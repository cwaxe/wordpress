<?php
/**
 * Shortcodes:
 *   [webcam_archiv]                         Kalender aller Kameras (mit Umschalter, falls mehrere)
 *   [webcam_archiv kamera="cam2"]           nur eine bestimmte Kamera
 *   [webcam_archiv kamera="cam1,cam3"]      Auswahl mehrerer Kameras
 *   [webcam_archiv datum="2026-11-09" tempo="6"]
 *   [webcam_aktuell kamera="cam2" refresh="60" archiv="/webcam-archiv/"]
 *   [webcam_aktuell kamera="cam2" link="bild"]   Klick öffnet das Bild in voller Größe
 */

defined( 'ABSPATH' ) || exit;

final class WCA_Frontend {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_shortcode( 'webcam_archiv', array( __CLASS__, 'shortcode_archive' ) );
		add_shortcode( 'webcam_aktuell', array( __CLASS__, 'shortcode_latest' ) );
	}

	public static function register_assets() {
		wp_register_style( 'wca-frontend', WCA_URL . 'assets/css/frontend.css', array(), WCA_VERSION );
		wp_register_script( 'wca-frontend', WCA_URL . 'assets/js/frontend.js', array(), WCA_VERSION, true );
	}

	private static function enqueue() {
		if ( ! wp_script_is( 'wca-frontend', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'wca-frontend' );
		wp_enqueue_script( 'wca-frontend' );

		static $colors_done = false;
		if ( ! $colors_done ) {
			wp_add_inline_style( 'wca-frontend', self::color_css() );
			$colors_done = true;
		}
	}

	/**
	 * Im Backend gewählte Farben als CSS-Variablen.
	 */
	public static function color_css() {
		$colors = WCA_Plugin::options()['colors'];
		$vars   = '';
		foreach ( WCA_Plugin::color_fields() as $key => $f ) {
			$c = sanitize_hex_color( $colors[ $key ] ?? '' );
			if ( $c ) {
				$vars .= $f[0] . ':' . $c . ';';
			}
		}
		return $vars ? '.wca-archive,.wca-latest{' . $vars . '}' : '';
	}

	/**
	 * Kameras aus dem Shortcode-Attribut "kamera" auflösen (ID, Name oder Liste).
	 */
	private static function resolve_cameras( $attr ) {
		$all = WCA_Plugin::cameras();
		$attr = trim( (string) $attr );
		if ( '' === $attr || 'alle' === $attr ) {
			return $all;
		}
		$out = array();
		foreach ( array_map( 'trim', explode( ',', $attr ) ) as $want ) {
			foreach ( $all as $id => $c ) {
				if ( $want === $id || 0 === strcasecmp( $want, $c['name'] ) ) {
					$out[ $id ] = $c;
				}
			}
		}
		return $out;
	}

	private static function base_config() {
		$o = WCA_Plugin::options();
		return array(
			'rest'      => esc_url_raw( rest_url( WCA_REST::NS . '/' ) ),
			'nonce'     => $o['require_login'] ? wp_create_nonce( 'wp_rest' ) : '',
			'locale'    => str_replace( '_', '-', get_locale() ),
			'weekStart' => (int) $o['week_start'],
			'i18n'      => array(
				'latest'   => 'Neuestes Bild',
				'image'    => 'Bild',
				'images'   => 'Bilder',
				'original' => 'Originalgröße',
				'play'     => 'Zeitraffer abspielen',
				'pause'    => 'Zeitraffer anhalten',
				'prev'     => 'Vorheriges Bild',
				'next'     => 'Nächstes Bild',
				'prevMon'  => 'Vorheriger Monat',
				'nextMon'  => 'Nächster Monat',
				'prevDay'  => 'Vorheriger Tag',
				'nextDay'  => 'Nächster Tag',
				'camera'    => 'Kamera',
				'archive'   => 'Webcam-Archiv',
				'selected'  => 'gewählt',
				'tileCount' => 'Aufnahmen',
				'tileRange' => 'Zeitraum',
				'tileShown' => 'Angezeigtes Bild',
				'timeline'  => 'Tagesverlauf',
				'thisDay'   => 'an diesem Tag',
				'firstLast' => 'Erste – letzte Aufnahme',
				'stand'     => 'Stand',
				'justNow'   => 'gerade eben',
				'ago'       => 'vor',
				'days'      => 'Tagen',
				'clock'    => 'Uhr',
				'empty'    => 'Von dieser Kamera sind noch keine Bilder vorhanden.',
				'noImages' => 'Für diesen Tag sind keine Bilder vorhanden.',
				'error'    => 'Die Bilder konnten nicht geladen werden.',
				'login'    => 'Bitte melde dich an, um das Archiv zu sehen.',
			),
		);
	}

	public static function shortcode_archive( $atts ) {
		$atts = shortcode_atts(
			array(
				'kamera' => '',
				'datum'  => '',
				'tempo'  => 4,  // Bilder pro Sekunde im Zeitraffer
				'titel'  => '', // Überschrift (Standard: Kameraname bzw. "Webcam-Archiv")
				'rahmen' => 'ja', // "nein" = ohne blauen Hintergrund, falls die Seite ihn schon hat
			),
			$atts,
			'webcam_archiv'
		);

		if ( WCA_Plugin::options()['require_login'] && ! is_user_logged_in() ) {
			return '<p class="wca-message">Bitte melde dich an, um das Webcam-Archiv zu sehen.</p>';
		}

		$cams = self::resolve_cameras( $atts['kamera'] );
		if ( ! $cams ) {
			return current_user_can( 'manage_options' )
				? '<p class="wca-message">WX Webcam Archive: Kamera „' . esc_html( $atts['kamera'] ) . '“ nicht gefunden.</p>'
				: '';
		}

		self::enqueue();

		$cfg            = self::base_config();
		$cfg['cameras'] = array();
		foreach ( $cams as $id => $c ) {
			$cfg['cameras'][] = array(
				'id'   => $id,
				'name' => $c['name'],
			);
		}
		$cfg['initialDate'] = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $atts['datum'] ) ? $atts['datum'] : '';
		$cfg['fps']         = max( 1, min( 25, (int) $atts['tempo'] ) );
		$cfg['title']       = sanitize_text_field( $atts['titel'] );

		return sprintf(
			'<div class="wca-archive%s" data-wca="%s">',
			'nein' === strtolower( $atts['rahmen'] ) ? ' wca-flat' : '',
			esc_attr( wp_json_encode( $cfg ) )
		) . '<noscript>Für das Webcam-Archiv wird JavaScript benötigt.</noscript></div>';
	}

	public static function shortcode_latest( $atts ) {
		$atts = shortcode_atts(
			array(
				'kamera'  => '',
				'refresh' => 60,
				'archiv'  => '',
				'link'    => '', // "bild" = Klick öffnet das Bild in voller Größe (wenn kein Archiv-Link gesetzt ist)
			),
			$atts,
			'webcam_aktuell'
		);

		if ( WCA_Plugin::options()['require_login'] && ! is_user_logged_in() ) {
			return '';
		}

		$cams = self::resolve_cameras( '' === $atts['kamera'] ? WCA_Plugin::default_camera_id() : $atts['kamera'] );
		if ( ! $cams ) {
			return '';
		}
		reset( $cams );
		$cam_id = key( $cams );
		$cam    = current( $cams );

		self::enqueue();

		$img = WCA_Archiver::latest( $cam_id );
		$cfg = self::base_config();
		unset( $cfg['i18n'] );
		$cfg['refresh'] = max( 0, (int) $atts['refresh'] );
		$cfg['camera']  = $cam_id;
		$cfg['name']    = $cam['name'];
		if ( $atts['archiv'] && false === strpos( $atts['archiv'], '#' ) ) {
			$cfg['linkArchive'] = esc_url_raw( $atts['archiv'] );
		} elseif ( ! $atts['archiv'] && 'bild' === strtolower( $atts['link'] ) ) {
			$cfg['linkImage'] = true;
		}

		if ( ! $img ) {
			return '<div class="wca-latest"><p class="wca-message">Noch kein Bild von ' . esc_html( $cam['name'] ) . ' vorhanden.</p></div>';
		}

		$ts   = strtotime( $img['date'] . ' ' . $img['time'] );
		$when = date_i18n( get_option( 'date_format' ) . ', H:i', $ts ) . ' Uhr';

		$html  = sprintf( '<figure class="wca-latest" data-wca="%s">', esc_attr( wp_json_encode( $cfg ) ) );
		$inner = sprintf(
			'<img src="%s" width="%d" height="%d" alt="%s">',
			esc_url( $img['full'] ),
			(int) $img['w'],
			(int) $img['h'],
			esc_attr( $cam['name'] . ' – ' . $when )
		);
		// Bildunterschrift für Lightboxen (z. B. WX Lightbox), sonst kleben Name und Zeit aneinander.
		$caption = $cam['name'] . ' · ' . $when;
		if ( $atts['archiv'] ) {
			$link  = $atts['archiv'] . ( false === strpos( $atts['archiv'], '#' ) ? '#wca=' . $img['date'] . '&kamera=' . $cam_id : '' );
			$inner = sprintf( '<a href="%s">%s</a>', esc_url( $link ), $inner );
		} elseif ( 'bild' === strtolower( $atts['link'] ) ) {
			$inner = sprintf( '<a href="%s" target="_blank" rel="noopener" data-caption="%s">%s</a>', esc_url( $img['full'] ), esc_attr( $caption ), $inner );
		}
		$html .= $inner;
		$html .= sprintf( '<figcaption><span class="wca-latest-name">%s</span><span class="wca-latest-time">%s</span></figcaption></figure>', esc_html( $cam['name'] ), esc_html( $when ) );
		return $html;
	}
}
