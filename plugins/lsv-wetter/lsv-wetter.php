<?php
/**
 * Plugin Name:       LSV Wetter am Platz
 * Plugin URI:        https://wetter.lsv-vilsbiburg.de
 * Description:       Zeigt die aktuellen Wetterdaten vom Flugplatz Vilsbiburg (EDMP) als Block oder per Shortcode [lsv_wetter] an – mit Pistenwahl, Gegen- und Seitenwind, QNH, QFE, Wolkenbasis und Dichtehöhe. Funktioniert mit jedem Theme.
 * Version:           1.2.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Update URI:        https://github.com/cwaxe/wordpress
 * Author:            IT-Service Waxenberger
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       lsv-wetter
 *
 * @package LSV_Wetter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Updates aus den GitHub-Releases (siehe includes/github-updater.php).
require_once __DIR__ . '/includes/github-updater.php';
WX_GitHub_Updater::register( __FILE__ );

define( 'LSVW_VERSION', '1.2.1' );
define( 'LSVW_URL', plugin_dir_url( __FILE__ ) );

/* =========================================================================
 * Daten & Berechnung
 * ====================================================================== */



/** Adresse der Wetterseite (im Customizer änderbar) */
function lsvw_url() {
	$url = get_option( 'lsvw_url', 'https://wetter.lsv-vilsbiburg.de' );
	return untrailingslashit( $url ? $url : 'https://wetter.lsv-vilsbiburg.de' );
}

/**
 * Daten holen (60 Sekunden zwischengespeichert).
 *
 * @return array|null
 */
function lsvw_daten() {
	$cache = get_transient( 'lsvw_daten' );
	if ( is_array( $cache ) ) {
		return $cache;
	}

	$antwort = wp_remote_get(
		lsvw_url() . '/data.json?t=' . time(),
		array(
			'timeout'    => 5,
			'user-agent' => 'LSV-Wetter-Plugin/' . LSVW_VERSION,
		)
	);

	if ( is_wp_error( $antwort ) || 200 !== (int) wp_remote_retrieve_response_code( $antwort ) ) {
		// Bei Fehler: letzte bekannte Daten verwenden (max. 1 Tag alt).
		$letzte = get_option( 'lsvw_letzte' );
		set_transient( 'lsvw_daten', is_array( $letzte ) ? $letzte : array(), 30 );
		return is_array( $letzte ) ? $letzte : null;
	}

	$daten = json_decode( wp_remote_retrieve_body( $antwort ), true );
	if ( ! is_array( $daten ) || empty( $daten['current'] ) ) {
		set_transient( 'lsvw_daten', array(), 30 );
		return null;
	}

	set_transient( 'lsvw_daten', $daten, 60 );
	update_option( 'lsvw_letzte', $daten, false );
	return $daten;
}

/* ---------- Hilfsfunktionen für Zahlen ---------- */

function lsvw_zahl( $wert, $nachkomma = 0 ) {
	return number_format( (float) $wert, $nachkomma, ',', '' );
}

function lsvw_wert( $arr, $pfad ) {
	foreach ( explode( '.', $pfad ) as $teil ) {
		if ( ! is_array( $arr ) || ! array_key_exists( $teil, $arr ) || null === $arr[ $teil ] ) {
			return null;
		}
		$arr = $arr[ $teil ];
	}
	return $arr;
}

/** Winkel auf -180 … +180 normieren */
function lsvw_winkel( $w ) {
	$w = fmod( $w + 540, 360 ) - 180;
	return $w;
}

function lsvw_himmelsrichtung( $grad ) {
	$r = array( 'N', 'NNO', 'NO', 'ONO', 'O', 'OSO', 'SO', 'SSO', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW' );
	return $r[ (int) round( fmod( $grad + 360, 360 ) / 22.5 ) % 16 ];
}

/**
 * Alle angezeigten Werte berechnen (wie auf der Wetterseite).
 *
 * @param array $d data.json.
 * @return array
 */
function lsvw_berechnen( $d ) {
	$c   = isset( $d['current'] ) ? $d['current'] : array();
	$cfg = isset( $d['config'] ) ? $d['config'] : array();
	$e   = array();

	$hoehe          = (float) ( lsvw_wert( $cfg, 'elevation_ft' ) ?? 1450 );
	$e['zeit']      = (int) ( $d['time'] ?? 0 );
	$e['alter_min'] = $e['zeit'] ? max( 0, (int) floor( ( time() - $e['zeit'] ) / 60 ) ) : null;
	$e['veraltet']  = null !== $e['alter_min'] && $e['alter_min'] > (int) ( lsvw_wert( $cfg, 'stale_minutes' ) ?? 10 );

	// Wind (Knoten).
	$ws  = lsvw_wert( $c, 'windSpeed' );
	$wg  = lsvw_wert( $c, 'windGust' );
	$wd  = lsvw_wert( $c, 'windDir' );
	$e['wind_kt']  = null !== $ws ? (int) round( $ws ) : null;
	$e['boe_kt']   = null !== $wg ? (int) round( $wg ) : null;
	$e['wind_dir'] = null !== $wd ? (int) round( $wd ) : null;
	$e['windstill'] = null === $e['wind_kt'] || $e['wind_kt'] < 2;

	// Landebahn.
	$rwy_hdg = (float) ( lsvw_wert( $cfg, 'runway_heading' ) ?? 27 );
	$rwy1    = (string) ( lsvw_wert( $cfg, 'runway_1' ) ?? '03' );
	$rwy2    = (string) ( lsvw_wert( $cfg, 'runway_2' ) ?? '21' );
	$e['piste'] = $rwy1 . '/' . $rwy2;
	$e['piste_info'] = (string) ( lsvw_wert( $cfg, 'runway_info' ) ?? '' );

	if ( ! $e['windstill'] && null !== $e['wind_dir'] ) {
		$diff1 = lsvw_winkel( $e['wind_dir'] - $rwy_hdg );
		if ( abs( $diff1 ) <= 90 ) {
			$e['piste_aktiv'] = $rwy1;
			$diff             = $diff1;
		} else {
			$e['piste_aktiv'] = $rwy2;
			$diff             = lsvw_winkel( $e['wind_dir'] - ( $rwy_hdg + 180 ) );
		}
		$rad               = deg2rad( abs( $diff ) );
		$e['gegenwind']    = (int) round( $ws * cos( $rad ) );
		$e['seitenwind']   = (int) round( $ws * sin( $rad ) );
		$e['gegen_boe']    = null !== $wg ? (int) round( $wg * cos( $rad ) ) : null;
		$e['seiten_boe']   = null !== $wg ? (int) round( $wg * sin( $rad ) ) : null;
		$e['seite']        = $diff >= 0 ? 'rechts' : 'links';
	}

	// Temperatur.
	$e['temp']     = lsvw_wert( $c, 'outTemp' );
	$e['taupunkt'] = lsvw_wert( $c, 'dewpoint' );
	$e['feuchte']  = lsvw_wert( $c, 'outHumidity' );
	$e['spread']   = ( null !== $e['temp'] && null !== $e['taupunkt'] ) ? $e['temp'] - $e['taupunkt'] : null;
	$e['t_min']    = lsvw_wert( $d, 'day.outTemp_min' );
	$e['t_min_z']  = lsvw_wert( $d, 'day.outTemp_min_time' );
	$e['t_max']    = lsvw_wert( $d, 'day.outTemp_max' );
	$e['t_max_z']  = lsvw_wert( $d, 'day.outTemp_max_time' );
	$e['boe_max']  = lsvw_wert( $d, 'day.windGust_max' );
	$e['boe_max_z'] = lsvw_wert( $d, 'day.windGust_max_time' );

	// Druck: wie auf der Wetterseite abgerundet.
	$qnh = lsvw_wert( $c, 'altimeter' );
	$qfe = lsvw_wert( $c, 'pressure' );
	$e['qnh']     = null !== $qnh ? (int) floor( $qnh ) : null;
	$e['qnh_gen'] = $qnh;
	$e['qfe']     = null !== $qfe ? (int) floor( $qfe ) : null;
	$e['qfe_gen'] = $qfe;

	// Wolkenbasis (Schätzung).
	$wb = lsvw_wert( $c, 'cloudbase' );
	$e['wolken_msl'] = null !== $wb ? (int) ( round( $wb / 100 ) * 100 ) : null;
	$e['wolken_agl'] = null !== $wb ? (int) ( round( ( $wb - $hoehe ) / 100 ) * 100 ) : null;

	// Dichtehöhe.
	if ( null !== $qfe && null !== $e['temp'] ) {
		$pa             = 145366.45 * ( 1 - pow( $qfe / 1013.25, 0.190284 ) );
		$isa            = 15 - 1.98 * $pa / 1000;
		$da             = $pa + 118.8 * ( $e['temp'] - $isa );
		$e['dichte']    = (int) ( round( $da / 50 ) * 50 );
		$e['dichte_rel'] = (int) ( round( ( $da - $hoehe ) / 50 ) * 50 );
	}

	return $e;
}

/**
 * HTML des Wetter-Blocks.
 *
 * @param array $atts Shortcode-Optionen.
 * @return string
 */
function lsvw_html( $atts = array() ) {
	$atts = shortcode_atts(
		array(
			'kompakt' => 'nein',
			'titel'   => 'Wetter am Platz',
			'link'    => 'ja',
		),
		$atts,
		'lsv_wetter'
	);
	$kompakt = in_array( strtolower( $atts['kompakt'] ), array( 'ja', '1', 'true', 'yes' ), true );
	$mitlink = ! in_array( strtolower( $atts['link'] ), array( 'nein', '0', 'false', 'no' ), true );
	$url     = lsvw_url();

	$d = lsvw_daten();

	ob_start();
	printf(
		'<div class="lsv-wetter%1$s" data-kompakt="%2$s" data-titel="%3$s" data-link="%4$s">',
		$kompakt ? ' lsv-wetter-kompakt' : '',
		$kompakt ? 'ja' : 'nein',
		esc_attr( $atts['titel'] ),
		$mitlink ? 'ja' : 'nein'
	);

	if ( empty( $d ) ) {
		echo '<div class="lsv-karte lsv-wetter-fehler">';
		if ( '' !== $atts['titel'] ) {
			echo '<h2>' . esc_html( $atts['titel'] ) . '</h2>';
		}
		echo '<p>Die Wetterdaten sind gerade nicht erreichbar.</p>';
		printf( '<p><a href="%s">Zur Wetterseite →</a></p>', esc_url( $url ) );
		echo '</div></div>';
		return ob_get_clean();
	}

	$e = lsvw_berechnen( $d );

	// Kopfzeile.
	echo '<div class="lsv-wetter-kopf">';
	if ( '' !== $atts['titel'] ) {
		echo '<h2 class="lsv-wetter-titel">' . esc_html( $atts['titel'] ) . '</h2>';
	}
	echo '<div class="lsv-wetter-stand' . ( $e['veraltet'] ? ' ist-veraltet' : '' ) . '">';
	if ( $e['zeit'] ) {
		echo 'Stand ' . esc_html( wp_date( 'H:i', $e['zeit'] ) ) . ' Uhr';
		if ( null !== $e['alter_min'] ) {
			echo ' · <span class="lsv-wetter-alter">' . ( $e['alter_min'] < 1 ? 'gerade eben' : 'vor ' . esc_html( $e['alter_min'] ) . ' min' ) . '</span>';
		}
		if ( $e['veraltet'] ) {
			echo ' · <strong>Daten veraltet</strong>';
		}
	}
	echo '</div></div>';

	echo '<div class="lsv-wetter-raster">';

	// Wind & Landebahn.
	echo '<div class="lsv-wetter-kachel lsv-wetter-wind">';
	echo '<div class="k-name">Wind &amp; Landebahn</div>';
	echo '<div class="lsv-wetter-wind-zeile">';
	if ( ! $e['windstill'] && null !== $e['wind_dir'] ) {
		printf(
			'<svg class="lsv-wetter-pfeil" viewBox="0 0 40 40" aria-hidden="true" style="transform:rotate(%ddeg)"><circle cx="20" cy="20" r="18" fill="none" stroke="currentColor" stroke-opacity=".25" stroke-width="2"/><path d="M20 30 L20 9 M13 16 L20 9 L27 16" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>',
			(int) ( $e['wind_dir'] + 180 ) % 360
		);
		echo '<div>';
		printf(
			'<div class="k-wert">%s<small>kt</small></div>',
			esc_html( $e['wind_kt'] )
		);
		printf(
			'<div class="k-zusatz">aus %1$s° %2$s%3$s</div>',
			esc_html( str_pad( (string) $e['wind_dir'], 3, '0', STR_PAD_LEFT ) ),
			esc_html( lsvw_himmelsrichtung( $e['wind_dir'] ) ),
			null !== $e['boe_kt'] ? ' · Böen ' . esc_html( $e['boe_kt'] ) . ' kt' : ''
		);
		echo '</div>';
	} else {
		echo '<div><div class="k-wert">Windstill</div>';
		if ( null !== $e['boe_kt'] ) {
			echo '<div class="k-zusatz">Böen ' . esc_html( $e['boe_kt'] ) . ' kt</div>';
		}
		echo '</div>';
	}
	echo '</div>';

	echo '<div class="lsv-wetter-piste">';
	printf( '<span class="lsv-wetter-rwy">%s</span>', esc_html( isset( $e['piste_aktiv'] ) ? $e['piste_aktiv'] : $e['piste'] ) );
	if ( isset( $e['piste_aktiv'] ) ) {
		echo '<span>';
		printf(
			'Gegenwind %1$s kt%2$s<br>Seitenwind von %3$s %4$s kt%5$s',
			esc_html( max( 0, $e['gegenwind'] ) ),
			null !== $e['gegen_boe'] ? ' <small>(Böen ' . esc_html( max( 0, $e['gegen_boe'] ) ) . ')</small>' : '',
			esc_html( $e['seite'] ),
			esc_html( $e['seitenwind'] ),
			null !== $e['seiten_boe'] ? ' <small>(Böen ' . esc_html( $e['seiten_boe'] ) . ')</small>' : ''
		);
		echo '</span>';
	} else {
		echo '<span>Windstill – beide Richtungen</span>';
	}
	echo '</div>';
	if ( ! $kompakt && null !== $e['boe_max'] ) {
		printf(
			'<div class="k-zusatz">Max. Böe heute %1$s kt%2$s</div>',
			esc_html( lsvw_zahl( $e['boe_max'] ) ),
			$e['boe_max_z'] ? ' (' . esc_html( $e['boe_max_z'] ) . ')' : ''
		);
	}
	echo '</div>';

	// Temperatur.
	if ( null !== $e['temp'] ) {
		echo '<div class="lsv-wetter-kachel">';
		echo '<div class="k-name">Temperatur</div>';
		printf( '<div class="k-wert">%s<small>°C</small></div>', esc_html( lsvw_zahl( $e['temp'], 1 ) ) );
		$zusatz = array();
		if ( null !== $e['taupunkt'] ) {
			$zusatz[] = 'Taupunkt ' . lsvw_zahl( $e['taupunkt'], 1 ) . ' °C';
		}
		echo '<div class="k-zusatz">' . esc_html( implode( ' · ', $zusatz ) ) . '</div>';
		if ( ! $kompakt && null !== $e['t_min'] && null !== $e['t_max'] ) {
			printf(
				'<div class="k-zusatz">Heute ↓ %1$s °C · ↑ %2$s °C</div>',
				esc_html( lsvw_zahl( $e['t_min'], 1 ) ),
				esc_html( lsvw_zahl( $e['t_max'], 1 ) )
			);
		}
		echo '</div>';
	}

	// QNH.
	if ( null !== $e['qnh'] ) {
		echo '<div class="lsv-wetter-kachel">';
		echo '<div class="k-name">QNH</div>';
		printf( '<div class="k-wert">%s<small>hPa</small></div>', esc_html( $e['qnh'] ) );
		printf( '<div class="k-zusatz">%s hPa</div>', esc_html( lsvw_zahl( $e['qnh_gen'], 1 ) ) );
		echo '</div>';
	}

	// QFE.
	if ( ! $kompakt && null !== $e['qfe'] ) {
		echo '<div class="lsv-wetter-kachel">';
		echo '<div class="k-name">QFE (Platzhöhe)</div>';
		printf( '<div class="k-wert">%s<small>hPa</small></div>', esc_html( $e['qfe'] ) );
		printf( '<div class="k-zusatz">%s hPa</div>', esc_html( lsvw_zahl( $e['qfe_gen'], 1 ) ) );
		echo '</div>';
	}

	// Wolkenbasis.
	if ( null !== $e['wolken_msl'] ) {
		echo '<div class="lsv-wetter-kachel">';
		echo '<div class="k-name">Wolkenbasis (Schätzung)</div>';
		printf( '<div class="k-wert">%s<small>ft MSL</small></div>', esc_html( $e['wolken_msl'] ) );
		printf( '<div class="k-zusatz">%s ft AGL</div>', esc_html( $e['wolken_agl'] ) );
		echo '</div>';
	}

	// Dichtehöhe.
	if ( ! $kompakt && isset( $e['dichte'] ) ) {
		echo '<div class="lsv-wetter-kachel">';
		echo '<div class="k-name">Dichtehöhe</div>';
		printf( '<div class="k-wert">%s<small>ft</small></div>', esc_html( $e['dichte'] ) );
		printf(
			'<div class="k-zusatz">%s ft ggü. Platzhöhe</div>',
			esc_html( ( $e['dichte_rel'] > 0 ? '+' : ( $e['dichte_rel'] < 0 ? '−' : '' ) ) . abs( $e['dichte_rel'] ) )
		);
		echo '</div>';
	}

	// Luftfeuchte.
	if ( ! $kompakt && null !== $e['feuchte'] ) {
		echo '<div class="lsv-wetter-kachel">';
		echo '<div class="k-name">Rel. Feuchte</div>';
		printf( '<div class="k-wert">%s<small>%%</small></div>', esc_html( (int) round( $e['feuchte'] ) ) );
		if ( null !== $e['spread'] ) {
			printf( '<div class="k-zusatz">Spread %s °C</div>', esc_html( lsvw_zahl( $e['spread'], 1 ) ) );
		}
		echo '</div>';
	}

	echo '</div>'; // raster

	if ( $mitlink ) {
		printf(
			'<p class="lsv-wetter-link"><a href="%s">Alle Wetterdaten, Verlauf &amp; Archiv →</a></p>',
			esc_url( $url )
		);
	}

	echo '</div>';
	return ob_get_clean();
}

require_once __DIR__ . '/includes/werte.php';

/* =========================================================================
 * Shortcode [lsv_wetter]
 * ====================================================================== */
function lsvw_shortcode( $atts ) {
	lsvw_assets_laden();
	return lsvw_html( is_array( $atts ) ? $atts : array() );
}
add_shortcode( 'lsv_wetter', 'lsvw_shortcode' );

/* =========================================================================
 * Block "Wetter am Platz" (lsv/wetter)
 * ====================================================================== */
function lsvw_block_registrieren() {
	wp_register_style( 'lsvw-style', LSVW_URL . 'assets/wetter.css', array(), LSVW_VERSION );
	wp_register_script( 'lsvw-refresh', LSVW_URL . 'assets/wetter.js', array(), LSVW_VERSION, true );
	wp_register_script(
		'lsvw-block',
		LSVW_URL . 'assets/block.js',
		array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render' ),
		LSVW_VERSION,
		true
	);

	register_block_type(
		'lsv/wetter',
		array(
			'api_version'     => 2,
			'title'           => 'Wetter am Platz',
			'category'        => 'widgets',
			'editor_script'   => 'lsvw-block',
			'style'           => 'lsvw-style',
			'attributes'      => array(
				'kompakt' => array( 'type' => 'boolean', 'default' => false ),
				'titel'   => array( 'type' => 'string', 'default' => 'Wetter am Platz' ),
				'link'    => array( 'type' => 'boolean', 'default' => true ),
				'align'   => array( 'type' => 'string', 'default' => 'wide' ),
			),
			'supports'        => array( 'align' => array( 'wide', 'full' ), 'html' => false ),
			'render_callback' => function ( $attr ) {
				lsvw_assets_laden();
				$html = lsvw_html(
					array(
						'kompakt' => ! empty( $attr['kompakt'] ) ? 'ja' : 'nein',
						'titel'   => isset( $attr['titel'] ) ? $attr['titel'] : 'Wetter am Platz',
						'link'    => ( ! isset( $attr['link'] ) || $attr['link'] ) ? 'ja' : 'nein',
					)
				);
				$align = ! empty( $attr['align'] ) ? ' align' . sanitize_html_class( $attr['align'] ) : '';
				return '<div class="wp-block-lsv-wetter' . $align . '">' . $html . '</div>';
			},
		)
	);
}
add_action( 'init', 'lsvw_block_registrieren' );

/** CSS + Aktualisierungs-Script im Frontend laden (nur wenn gebraucht) */
function lsvw_assets_laden() {
	if ( is_admin() ) {
		return;
	}
	wp_enqueue_style( 'lsvw-style' );
	wp_enqueue_script( 'lsvw-refresh' );
	static $lokal = false;
	if ( ! $lokal ) {
		wp_localize_script(
			'lsvw-refresh',
			'lsvWetter',
			array(
				'rest'   => esc_url_raw( rest_url( 'lsv/v1/wetter' ) ),
				'werte'  => esc_url_raw( rest_url( 'lsv/v1/werte' ) ),
				'schild' => esc_url_raw( rest_url( 'lsv/v1/schild' ) ),
			)
		);
		$lokal = true;
	}
}

/** Im Editor das gleiche CSS wie auf der Seite */
function lsvw_editor_assets() {
	wp_enqueue_style( 'lsvw-style' );
}
add_action( 'enqueue_block_editor_assets', 'lsvw_editor_assets' );

/** CSS auch auf der Einstellungsseite (für die Schild-Vorschau) */
function lsvw_admin_assets( $hook ) {
	if ( 'settings_page_lsv-wetter' === $hook ) {
		wp_enqueue_style( 'lsvw-style' );
	}
}
add_action( 'admin_enqueue_scripts', 'lsvw_admin_assets' );

/* =========================================================================
 * REST-Endpunkt für die automatische Aktualisierung
 * /wp-json/lsv/v1/wetter?kompakt=ja&titel=...&link=ja
 * ====================================================================== */
function lsvw_rest() {
	register_rest_route(
		'lsv/v1',
		'/wetter',
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => function ( WP_REST_Request $req ) {
				$titel = $req->get_param( 'titel' );
				$html  = lsvw_html(
					array(
						'kompakt' => sanitize_text_field( (string) $req->get_param( 'kompakt' ) ),
						'titel'   => null === $titel ? 'Wetter am Platz' : sanitize_text_field( (string) $titel ),
						'link'    => sanitize_text_field( (string) ( $req->get_param( 'link' ) ?? 'ja' ) ),
					)
				);
				$res = new WP_REST_Response( array( 'html' => $html ) );
				$res->header( 'Cache-Control', 'public, max-age=60' );
				return $res;
			},
		)
	);
}
add_action( 'rest_api_init', 'lsvw_rest' );

/* =========================================================================
 * Einstellungen → LSV Wetter
 * ====================================================================== */
function lsvw_einstellungen_registrieren() {
	register_setting(
		'lsvw',
		'lsvw_url',
		array(
			'type'              => 'string',
			'default'           => 'https://wetter.lsv-vilsbiburg.de',
			'sanitize_callback' => function ( $wert ) {
				delete_transient( 'lsvw_daten' );
				$wert = esc_url_raw( trim( (string) $wert ) );
				return $wert ? $wert : 'https://wetter.lsv-vilsbiburg.de';
			},
		)
	);
}
add_action( 'admin_init', 'lsvw_einstellungen_registrieren' );

function lsvw_menue() {
	add_options_page( 'LSV Wetter', 'LSV Wetter', 'manage_options', 'lsv-wetter', 'lsvw_einstellungen_seite' );
}
add_action( 'admin_menu', 'lsvw_menue' );

function lsvw_einstellungen_seite() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$daten = lsvw_daten();
	?>
	<div class="wrap">
		<h1>LSV Wetter am Platz</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'lsvw' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="lsvw_url">Adresse der Wetterseite</label></th>
					<td>
						<input type="url" class="regular-text" id="lsvw_url" name="lsvw_url" value="<?php echo esc_attr( lsvw_url() ); ?>">
						<p class="description">Von hier wird <code>data.json</code> geladen (alle 60 Sekunden neu).</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Verbindung</th>
					<td>
						<?php if ( ! empty( $daten['time'] ) ) : ?>
							<span style="color:#2e8b57;font-weight:600">✓ Daten erreichbar</span>
							– Stand <?php echo esc_html( wp_date( 'd.m.Y H:i', (int) $daten['time'] ) ); ?> Uhr
						<?php else : ?>
							<span style="color:#c0392b;font-weight:600">✗ Keine Daten</span>
							– Adresse prüfen oder später erneut versuchen.
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<h2>So fügst du das Wetter ein</h2>
		<ol>
			<li>Seite im Editor öffnen, auf <strong>„+“</strong> klicken und <strong>„Wetter am Platz“</strong> suchen.</li>
			<li>In der Seitenleiste rechts: Überschrift, kompakte Ansicht und Link zur Wetterseite einstellen.</li>
		</ol>
		<p>Oder per Shortcode: <code>[lsv_wetter]</code>, <code>[lsv_wetter kompakt="ja"]</code>, <code>[lsv_wetter titel=""]</code>, <code>[lsv_wetter link="nein"]</code></p>

		<h2>Gelbes Pistenschild</h2>
		<p>Zeigt die Piste in Benutzung (bei Windstille <strong>03/21</strong>):</p>
		<table class="widefat striped" style="max-width:900px">
			<thead><tr><th>Shortcode</th><th>Ergebnis</th></tr></thead>
			<tbody>
				<tr><td><code>[lsv_landerichtung]</code></td><td><?php echo lsvw_schild_html( array() ); // phpcs:ignore ?></td></tr>
				<tr><td><code>[lsv_landerichtung wind="ja"]</code></td><td><?php echo lsvw_schild_html( array( 'wind' => 'ja' ) ); // phpcs:ignore ?></td></tr>
				<tr><td><code>[lsv_landerichtung groesse="klein"]</code> – für Fließtext</td><td><?php echo lsvw_schild_html( array( 'groesse' => 'klein' ) ); // phpcs:ignore ?></td></tr>
				<tr><td><code>[lsv_landerichtung groesse="gross" wind="ja"]</code></td><td><?php echo lsvw_schild_html( array( 'groesse' => 'gross', 'wind' => 'ja' ) ); // phpcs:ignore ?></td></tr>
			</tbody>
		</table>

		<h2>Einzelwerte</h2>
		<p>Jeder Wert hat einen eigenen Shortcode, z. B. <code>[lsv_qnh]</code>. Gleichwertig: <code>[lsv_wert feld="qnh"]</code>.<br>
		Optionen: <code>einheit="nein"</code> (nur die Zahl) · <code>leer="k. A."</code> (Text, wenn kein Wert da ist).<br>
		Beispiel: <code>Aktuell [lsv_wind_komplett], QNH [lsv_qnh], Landerichtung [lsv_landerichtung groesse="klein"].</code></p>
		<table class="widefat striped" style="max-width:900px">
			<thead><tr><th>Shortcode</th><th>Bedeutung</th><th>Aktuell</th></tr></thead>
			<tbody>
			<?php
			$werte = lsvw_werte();
			foreach ( lsvw_felder() as $feld => $text ) {
				printf(
					'<tr><td><code>[lsv_%1$s]</code></td><td>%2$s</td><td>%3$s</td></tr>',
					esc_html( $feld ),
					esc_html( $text ),
					esc_html( lsvw_wert_text( $werte, $feld, true, '–' ) )
				);
			}
			?>
			</tbody>
		</table>
	</div>
	<?php
}

/** Link "Einstellungen" in der Plugin-Liste */
function lsvw_plugin_links( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=lsv-wetter' ) ) . '">Einstellungen</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'lsvw_plugin_links' );

/** Beim Deinstallieren aufräumen */
function lsvw_deinstallieren() {
	delete_option( 'lsvw_url' );
	delete_option( 'lsvw_letzte' );
	delete_transient( 'lsvw_daten' );
}
register_uninstall_hook( __FILE__, 'lsvw_deinstallieren' );
