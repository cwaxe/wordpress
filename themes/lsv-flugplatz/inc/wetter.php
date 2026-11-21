<?php
/**
 * Wetter vom Platz – liest data.json von wetter.lsv-vilsbiburg.de
 *
 * Einfügen: Shortcode [lsv_wetter] (z. B. im Block "Shortcode")
 * oder Block-Vorlage "Wetter am Platz" (Muster → LSV).
 *
 * Optionen:
 *   [lsv_wetter kompakt="ja"]     nur Wind, Temperatur, QNH, Wolkenbasis
 *   [lsv_wetter titel="Wetter"]   eigene Überschrift ("" = keine)
 *   [lsv_wetter link="nein"]      ohne Link zur Wetterseite
 *
 * @package LSV_Flugplatz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Adresse der Wetterseite (im Customizer änderbar) */
function lsv_wetter_url() {
	$url = get_theme_mod( 'lsv_wetter_url', 'https://wetter.lsv-vilsbiburg.de' );
	return untrailingslashit( $url ? $url : 'https://wetter.lsv-vilsbiburg.de' );
}

/**
 * Daten holen (60 Sekunden zwischengespeichert).
 *
 * @return array|null
 */
function lsv_wetter_daten() {
	$cache = get_transient( 'lsv_wetter_daten' );
	if ( is_array( $cache ) ) {
		return $cache;
	}

	$antwort = wp_remote_get(
		lsv_wetter_url() . '/data.json?t=' . time(),
		array(
			'timeout'    => 5,
			'user-agent' => 'LSV-Flugplatz-Theme/' . LSV_VERSION,
		)
	);

	if ( is_wp_error( $antwort ) || 200 !== (int) wp_remote_retrieve_response_code( $antwort ) ) {
		// Bei Fehler: letzte bekannte Daten verwenden (max. 1 Tag alt).
		$letzte = get_option( 'lsv_wetter_letzte' );
		set_transient( 'lsv_wetter_daten', is_array( $letzte ) ? $letzte : array(), 30 );
		return is_array( $letzte ) ? $letzte : null;
	}

	$daten = json_decode( wp_remote_retrieve_body( $antwort ), true );
	if ( ! is_array( $daten ) || empty( $daten['current'] ) ) {
		set_transient( 'lsv_wetter_daten', array(), 30 );
		return null;
	}

	set_transient( 'lsv_wetter_daten', $daten, 60 );
	update_option( 'lsv_wetter_letzte', $daten, false );
	return $daten;
}

/* ---------- Hilfsfunktionen für Zahlen ---------- */

function lsv_w_zahl( $wert, $nachkomma = 0 ) {
	return number_format( (float) $wert, $nachkomma, ',', '' );
}

function lsv_w_wert( $arr, $pfad ) {
	foreach ( explode( '.', $pfad ) as $teil ) {
		if ( ! is_array( $arr ) || ! array_key_exists( $teil, $arr ) || null === $arr[ $teil ] ) {
			return null;
		}
		$arr = $arr[ $teil ];
	}
	return $arr;
}

/** Winkel auf -180 … +180 normieren */
function lsv_w_winkel( $w ) {
	$w = fmod( $w + 540, 360 ) - 180;
	return $w;
}

function lsv_w_himmelsrichtung( $grad ) {
	$r = array( 'N', 'NNO', 'NO', 'ONO', 'O', 'OSO', 'SO', 'SSO', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW' );
	return $r[ (int) round( fmod( $grad + 360, 360 ) / 22.5 ) % 16 ];
}

/**
 * Alle angezeigten Werte berechnen (wie auf der Wetterseite).
 *
 * @param array $d data.json.
 * @return array
 */
function lsv_wetter_berechnen( $d ) {
	$c   = isset( $d['current'] ) ? $d['current'] : array();
	$cfg = isset( $d['config'] ) ? $d['config'] : array();
	$e   = array();

	$hoehe          = (float) ( lsv_w_wert( $cfg, 'elevation_ft' ) ?? 1450 );
	$e['zeit']      = (int) ( $d['time'] ?? 0 );
	$e['alter_min'] = $e['zeit'] ? max( 0, (int) floor( ( time() - $e['zeit'] ) / 60 ) ) : null;
	$e['veraltet']  = null !== $e['alter_min'] && $e['alter_min'] > (int) ( lsv_w_wert( $cfg, 'stale_minutes' ) ?? 10 );

	// Wind (Knoten).
	$ws  = lsv_w_wert( $c, 'windSpeed' );
	$wg  = lsv_w_wert( $c, 'windGust' );
	$wd  = lsv_w_wert( $c, 'windDir' );
	$e['wind_kt']  = null !== $ws ? (int) round( $ws ) : null;
	$e['boe_kt']   = null !== $wg ? (int) round( $wg ) : null;
	$e['wind_dir'] = null !== $wd ? (int) round( $wd ) : null;
	$e['windstill'] = null === $e['wind_kt'] || $e['wind_kt'] < 2;

	// Landebahn.
	$rwy_hdg = (float) ( lsv_w_wert( $cfg, 'runway_heading' ) ?? 27 );
	$rwy1    = (string) ( lsv_w_wert( $cfg, 'runway_1' ) ?? '03' );
	$rwy2    = (string) ( lsv_w_wert( $cfg, 'runway_2' ) ?? '21' );
	$e['piste'] = $rwy1 . '/' . $rwy2;
	$e['piste_info'] = (string) ( lsv_w_wert( $cfg, 'runway_info' ) ?? '' );

	if ( ! $e['windstill'] && null !== $e['wind_dir'] ) {
		$diff1 = lsv_w_winkel( $e['wind_dir'] - $rwy_hdg );
		if ( abs( $diff1 ) <= 90 ) {
			$e['piste_aktiv'] = $rwy1;
			$diff             = $diff1;
		} else {
			$e['piste_aktiv'] = $rwy2;
			$diff             = lsv_w_winkel( $e['wind_dir'] - ( $rwy_hdg + 180 ) );
		}
		$rad               = deg2rad( abs( $diff ) );
		$e['gegenwind']    = (int) round( $ws * cos( $rad ) );
		$e['seitenwind']   = (int) round( $ws * sin( $rad ) );
		$e['gegen_boe']    = null !== $wg ? (int) round( $wg * cos( $rad ) ) : null;
		$e['seiten_boe']   = null !== $wg ? (int) round( $wg * sin( $rad ) ) : null;
		$e['seite']        = $diff >= 0 ? 'rechts' : 'links';
	}

	// Temperatur.
	$e['temp']     = lsv_w_wert( $c, 'outTemp' );
	$e['taupunkt'] = lsv_w_wert( $c, 'dewpoint' );
	$e['feuchte']  = lsv_w_wert( $c, 'outHumidity' );
	$e['spread']   = ( null !== $e['temp'] && null !== $e['taupunkt'] ) ? $e['temp'] - $e['taupunkt'] : null;
	$e['t_min']    = lsv_w_wert( $d, 'day.outTemp_min' );
	$e['t_min_z']  = lsv_w_wert( $d, 'day.outTemp_min_time' );
	$e['t_max']    = lsv_w_wert( $d, 'day.outTemp_max' );
	$e['t_max_z']  = lsv_w_wert( $d, 'day.outTemp_max_time' );
	$e['boe_max']  = lsv_w_wert( $d, 'day.windGust_max' );
	$e['boe_max_z'] = lsv_w_wert( $d, 'day.windGust_max_time' );

	// Druck: wie auf der Wetterseite abgerundet.
	$qnh = lsv_w_wert( $c, 'altimeter' );
	$qfe = lsv_w_wert( $c, 'pressure' );
	$e['qnh']     = null !== $qnh ? (int) floor( $qnh ) : null;
	$e['qnh_gen'] = $qnh;
	$e['qfe']     = null !== $qfe ? (int) floor( $qfe ) : null;
	$e['qfe_gen'] = $qfe;

	// Wolkenbasis (Schätzung).
	$wb = lsv_w_wert( $c, 'cloudbase' );
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
function lsv_wetter_html( $atts = array() ) {
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
	$url     = lsv_wetter_url();

	$d = lsv_wetter_daten();

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

	$e = lsv_wetter_berechnen( $d );

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
			esc_html( lsv_w_himmelsrichtung( $e['wind_dir'] ) ),
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
			esc_html( lsv_w_zahl( $e['boe_max'] ) ),
			$e['boe_max_z'] ? ' (' . esc_html( $e['boe_max_z'] ) . ')' : ''
		);
	}
	echo '</div>';

	// Temperatur.
	if ( null !== $e['temp'] ) {
		echo '<div class="lsv-wetter-kachel">';
		echo '<div class="k-name">Temperatur</div>';
		printf( '<div class="k-wert">%s<small>°C</small></div>', esc_html( lsv_w_zahl( $e['temp'], 1 ) ) );
		$zusatz = array();
		if ( null !== $e['taupunkt'] ) {
			$zusatz[] = 'Taupunkt ' . lsv_w_zahl( $e['taupunkt'], 1 ) . ' °C';
		}
		echo '<div class="k-zusatz">' . esc_html( implode( ' · ', $zusatz ) ) . '</div>';
		if ( ! $kompakt && null !== $e['t_min'] && null !== $e['t_max'] ) {
			printf(
				'<div class="k-zusatz">Heute ↓ %1$s °C · ↑ %2$s °C</div>',
				esc_html( lsv_w_zahl( $e['t_min'], 1 ) ),
				esc_html( lsv_w_zahl( $e['t_max'], 1 ) )
			);
		}
		echo '</div>';
	}

	// QNH.
	if ( null !== $e['qnh'] ) {
		echo '<div class="lsv-wetter-kachel">';
		echo '<div class="k-name">QNH</div>';
		printf( '<div class="k-wert">%s<small>hPa</small></div>', esc_html( $e['qnh'] ) );
		printf( '<div class="k-zusatz">%s hPa</div>', esc_html( lsv_w_zahl( $e['qnh_gen'], 1 ) ) );
		echo '</div>';
	}

	// QFE.
	if ( ! $kompakt && null !== $e['qfe'] ) {
		echo '<div class="lsv-wetter-kachel">';
		echo '<div class="k-name">QFE (Platzhöhe)</div>';
		printf( '<div class="k-wert">%s<small>hPa</small></div>', esc_html( $e['qfe'] ) );
		printf( '<div class="k-zusatz">%s hPa</div>', esc_html( lsv_w_zahl( $e['qfe_gen'], 1 ) ) );
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
			printf( '<div class="k-zusatz">Spread %s °C</div>', esc_html( lsv_w_zahl( $e['spread'], 1 ) ) );
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
add_shortcode( 'lsv_wetter', 'lsv_wetter_html' );

/**
 * REST-Endpunkt für die automatische Aktualisierung im Browser:
 * /wp-json/lsv/v1/wetter?kompakt=ja&titel=...&link=ja
 */
function lsv_wetter_rest() {
	register_rest_route(
		'lsv/v1',
		'/wetter',
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => function ( WP_REST_Request $req ) {
				$html = lsv_wetter_html(
					array(
						'kompakt' => sanitize_text_field( (string) $req->get_param( 'kompakt' ) ),
						'titel'   => null === $req->get_param( 'titel' ) ? 'Wetter am Platz' : sanitize_text_field( (string) $req->get_param( 'titel' ) ),
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
add_action( 'rest_api_init', 'lsv_wetter_rest' );

/** Script nur laden, wenn der Wetter-Block auf der Seite ist */
function lsv_wetter_script() {
	if ( ! is_singular() && ! is_home() ) {
		return;
	}
	$post = get_post();
	if ( is_singular() && ( ! $post || ! has_shortcode( $post->post_content, 'lsv_wetter' ) ) ) {
		return;
	}
	wp_enqueue_script( 'lsv-wetter', get_template_directory_uri() . '/assets/js/wetter.js', array(), LSV_VERSION, true );
	wp_localize_script( 'lsv-wetter', 'lsvWetter', array( 'rest' => esc_url_raw( rest_url( 'lsv/v1/wetter' ) ) ) );
}
add_action( 'wp_enqueue_scripts', 'lsv_wetter_script' );

/** Block-Vorlage "Wetter am Platz" */
function lsv_wetter_muster() {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}
	register_block_pattern_category( 'lsv', array( 'label' => 'LSV' ) );
	register_block_pattern(
		'lsv-flugplatz/wetter',
		array(
			'title'       => 'Wetter am Platz',
			'description' => 'Aktuelle Wetterdaten von wetter.lsv-vilsbiburg.de',
			'categories'  => array( 'lsv' ),
			'content'     => "<!-- wp:shortcode -->\n[lsv_wetter]\n<!-- /wp:shortcode -->",
		)
	);
	register_block_pattern(
		'lsv-flugplatz/wetter-kompakt',
		array(
			'title'       => 'Wetter am Platz (kompakt)',
			'description' => 'Wind, Temperatur, QNH und Wolkenbasis',
			'categories'  => array( 'lsv' ),
			'content'     => "<!-- wp:shortcode -->\n[lsv_wetter kompakt=\"ja\"]\n<!-- /wp:shortcode -->",
		)
	);
}
add_action( 'init', 'lsv_wetter_muster' );

/** Customizer: Adresse der Wetterseite */
function lsv_wetter_customize( $wp_customize ) {
	$wp_customize->add_setting(
		'lsv_wetter_url',
		array(
			'default'           => 'https://wetter.lsv-vilsbiburg.de',
			'sanitize_callback' => 'esc_url_raw',
		)
	);
	$wp_customize->add_control(
		'lsv_wetter_url',
		array(
			'label'       => 'Adresse der Wetterseite',
			'description' => 'Von hier werden die Daten (data.json) für den Wetter-Block geholt.',
			'section'     => 'lsv_kopf',
			'type'        => 'url',
		)
	);
}
add_action( 'customize_register', 'lsv_wetter_customize', 20 );
