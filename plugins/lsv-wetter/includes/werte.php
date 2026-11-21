<?php
/**
 * Einzelwerte und Pistenschild als Shortcodes
 *
 * Einzelwert:   [lsv_wert feld="qnh"]   oder kurz   [lsv_qnh]
 *               Optionen: einheit="nein"  leer="–"
 * Pistenschild: [lsv_landerichtung]
 *               Optionen: wind="ja"  groesse="klein|normal|gross"
 *
 * @package LSV_Wetter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Liste aller Felder: Schlüssel => Bezeichnung (für Hilfe-Seite).
 */
function lsvw_felder() {
	return array(
		'wind'             => 'Windgeschwindigkeit (oder „Windstill“)',
		'windrichtung'     => 'Windrichtung in Grad',
		'himmelsrichtung'  => 'Windrichtung als Himmelsrichtung (z. B. SW)',
		'boeen'            => 'Böen',
		'wind_komplett'    => 'Wind komplett, z. B. „235° / 9 kt, Böen 14 kt“',
		'boe_max'          => 'Stärkste Böe heute',
		'piste'            => 'Piste in Benutzung als Text (z. B. 21)',
		'gegenwind'        => 'Gegenwind-Komponente',
		'seitenwind'       => 'Seitenwind-Komponente',
		'seitenwind_von'   => 'Seitenwind von „rechts“ oder „links“',
		'temp'             => 'Temperatur',
		'temp_min'         => 'Tiefstwert heute',
		'temp_max'         => 'Höchstwert heute',
		'taupunkt'         => 'Taupunkt',
		'spread'           => 'Spread (Temperatur − Taupunkt)',
		'feuchte'          => 'Relative Luftfeuchte',
		'qnh'              => 'QNH (abgerundet)',
		'qnh_genau'        => 'QNH mit Nachkommastelle',
		'qfe'              => 'QFE (abgerundet)',
		'qfe_genau'        => 'QFE mit Nachkommastelle',
		'wolkenbasis'      => 'Wolkenbasis MSL (Schätzung)',
		'wolkenbasis_agl'  => 'Wolkenbasis über Grund',
		'dichtehoehe'      => 'Dichtehöhe',
		'stand'            => 'Uhrzeit der Messung',
		'alter'            => 'Alter der Messung („vor 3 min“)',
	);
}

/**
 * Alle Einzelwerte berechnen: feld => array( wert, einheit ).
 *
 * @return array
 */
function lsvw_werte() {
	$d = lsvw_daten();
	if ( empty( $d ) ) {
		return array();
	}
	$e = lsvw_berechnen( $d );
	$w = array();

	$set = function ( $feld, $wert, $einheit = '' ) use ( &$w ) {
		if ( null === $wert || '' === $wert ) {
			return;
		}
		$w[ $feld ] = array( (string) $wert, (string) $einheit );
	};

	// Wind.
	if ( $e['windstill'] ) {
		$set( 'wind', 'Windstill' );
		$set( 'wind_komplett', 'Windstill' . ( null !== $e['boe_kt'] && $e['boe_kt'] > 0 ? ', Böen ' . $e['boe_kt'] . ' kt' : '' ) );
	} else {
		$set( 'wind', $e['wind_kt'], 'kt' );
		$set(
			'wind_komplett',
			str_pad( (string) $e['wind_dir'], 3, '0', STR_PAD_LEFT ) . '° / ' . $e['wind_kt'] . ' kt'
			. ( null !== $e['boe_kt'] && $e['boe_kt'] > $e['wind_kt'] ? ', Böen ' . $e['boe_kt'] . ' kt' : '' )
		);
	}
	if ( null !== $e['wind_dir'] ) {
		$set( 'windrichtung', str_pad( (string) $e['wind_dir'], 3, '0', STR_PAD_LEFT ), '°' );
		$set( 'himmelsrichtung', lsvw_himmelsrichtung( $e['wind_dir'] ) );
	}
	$set( 'boeen', $e['boe_kt'], 'kt' );
	if ( null !== $e['boe_max'] ) {
		$set( 'boe_max', lsvw_zahl( $e['boe_max'] ), 'kt' );
	}

	// Piste.
	$set( 'piste', isset( $e['piste_aktiv'] ) ? $e['piste_aktiv'] : $e['piste'] );
	if ( isset( $e['piste_aktiv'] ) ) {
		$set( 'gegenwind', max( 0, $e['gegenwind'] ), 'kt' );
		$set( 'seitenwind', $e['seitenwind'], 'kt' );
		$set( 'seitenwind_von', $e['seite'] );
	} else {
		$set( 'gegenwind', 0, 'kt' );
		$set( 'seitenwind', 0, 'kt' );
	}

	// Temperatur & Feuchte.
	foreach ( array( 'temp' => 'temp', 'temp_min' => 't_min', 'temp_max' => 't_max', 'taupunkt' => 'taupunkt', 'spread' => 'spread' ) as $feld => $key ) {
		if ( null !== $e[ $key ] ) {
			$set( $feld, lsvw_zahl( $e[ $key ], 1 ), '°C' );
		}
	}
	if ( null !== $e['feuchte'] ) {
		$set( 'feuchte', (int) round( $e['feuchte'] ), '%' );
	}

	// Druck.
	$set( 'qnh', $e['qnh'], 'hPa' );
	if ( null !== $e['qnh_gen'] ) {
		$set( 'qnh_genau', lsvw_zahl( $e['qnh_gen'], 1 ), 'hPa' );
	}
	$set( 'qfe', $e['qfe'], 'hPa' );
	if ( null !== $e['qfe_gen'] ) {
		$set( 'qfe_genau', lsvw_zahl( $e['qfe_gen'], 1 ), 'hPa' );
	}

	// Wolken & Dichtehöhe.
	$set( 'wolkenbasis', $e['wolken_msl'], 'ft MSL' );
	$set( 'wolkenbasis_agl', $e['wolken_agl'], 'ft AGL' );
	if ( isset( $e['dichte'] ) ) {
		$set( 'dichtehoehe', $e['dichte'], 'ft' );
	}

	// Zeit.
	if ( $e['zeit'] ) {
		$set( 'stand', wp_date( 'H:i', $e['zeit'] ), 'Uhr' );
		if ( null !== $e['alter_min'] ) {
			$set( 'alter', $e['alter_min'] < 1 ? 'gerade eben' : 'vor ' . $e['alter_min'] . ' min' );
		}
	}

	return $w;
}

/** Wert als Text */
function lsvw_wert_text( $werte, $feld, $einheit, $leer ) {
	if ( ! isset( $werte[ $feld ] ) ) {
		return $leer;
	}
	list( $wert, $e ) = $werte[ $feld ];
	if ( ! $einheit || '' === $e ) {
		return $wert;
	}
	return $wert . ( '°' === $e ? '' : ' ' ) . $e;
}

/**
 * Shortcode [lsv_wert feld="qnh" einheit="ja" leer="–"]
 */
function lsvw_wert_shortcode( $atts, $content = '', $tag = 'lsv_wert' ) {
	$feld_aus_tag = 'lsv_wert' !== $tag ? substr( $tag, 4 ) : '';
	$atts         = shortcode_atts(
		array(
			'feld'    => $feld_aus_tag,
			'einheit' => 'ja',
			'leer'    => '–',
		),
		$atts,
		$tag
	);
	$feld = sanitize_key( $atts['feld'] );
	if ( ! array_key_exists( $feld, lsvw_felder() ) ) {
		return current_user_can( 'edit_posts' ) ? '<span class="lsv-wert lsv-wert-fehler">[Unbekanntes Feld: ' . esc_html( $feld ) . ']</span>' : '';
	}
	lsvw_assets_laden();
	$einheit = ! in_array( strtolower( $atts['einheit'] ), array( 'nein', '0', 'false', 'no' ), true );

	return sprintf(
		'<span class="lsv-wert lsv-wert-%1$s" data-feld="%1$s" data-einheit="%2$s" data-leer="%3$s">%4$s</span>',
		esc_attr( $feld ),
		$einheit ? 'ja' : 'nein',
		esc_attr( $atts['leer'] ),
		esc_html( lsvw_wert_text( lsvw_werte(), $feld, $einheit, $atts['leer'] ) )
	);
}

/**
 * HTML des gelben Pistenschilds.
 *
 * @param array $atts wind, groesse.
 * @return string
 */
function lsvw_schild_html( $atts ) {
	$atts = shortcode_atts(
		array(
			'wind'    => 'nein',
			'groesse' => 'normal',
		),
		$atts,
		'lsv_landerichtung'
	);
	$mitwind = in_array( strtolower( $atts['wind'] ), array( 'ja', '1', 'true', 'yes' ), true );
	$groesse = in_array( $atts['groesse'], array( 'klein', 'normal', 'gross' ), true ) ? $atts['groesse'] : 'normal';

	$d = lsvw_daten();
	$e = empty( $d ) ? null : lsvw_berechnen( $d );

	if ( $e ) {
		$piste = isset( $e['piste_aktiv'] ) ? $e['piste_aktiv'] : $e['piste'];
		$label = isset( $e['piste_aktiv'] ) ? 'Landerichtung ' . $piste : 'Windstill – Landerichtung ' . $e['piste'] . ' frei wählbar';
	} else {
		$piste = '03/21';
		$label = 'Landerichtung: keine aktuellen Wetterdaten';
	}

	$html = sprintf(
		'<span class="lsv-schild-wrap lsv-schild-%1$s%2$s" data-wind="%3$s" data-groesse="%1$s">',
		esc_attr( $groesse ),
		$mitwind ? ' mit-wind' : '',
		$mitwind ? 'ja' : 'nein'
	);
	$html .= sprintf(
		'<span class="lsv-schild" role="img" aria-label="%1$s" title="%1$s">%2$s</span>',
		esc_attr( $label ),
		esc_html( $piste )
	);

	if ( $mitwind ) {
		$html .= '<span class="lsv-schild-info">';
		if ( ! $e ) {
			$html .= '<span class="lsv-schild-titel">Keine Daten</span>';
		} elseif ( isset( $e['piste_aktiv'] ) ) {
			$html .= '<span class="lsv-schild-titel">Wind ' . esc_html( str_pad( (string) $e['wind_dir'], 3, '0', STR_PAD_LEFT ) ) . '° / ' . esc_html( $e['wind_kt'] ) . ' kt'
				. ( null !== $e['boe_kt'] && $e['boe_kt'] > $e['wind_kt'] ? ', Böen ' . esc_html( $e['boe_kt'] ) . ' kt' : '' ) . '</span>';
			$html .= '<span class="lsv-schild-text">Gegenwind ' . esc_html( max( 0, $e['gegenwind'] ) ) . ' kt · Seitenwind von ' . esc_html( $e['seite'] ) . ' ' . esc_html( $e['seitenwind'] ) . ' kt</span>';
		} else {
			$html .= '<span class="lsv-schild-titel">Windstill</span>';
			$html .= '<span class="lsv-schild-text">Beide Richtungen möglich' . ( null !== $e['boe_kt'] && $e['boe_kt'] > 0 ? ' · Böen ' . esc_html( $e['boe_kt'] ) . ' kt' : '' ) . '</span>';
		}
		$html .= '</span>';
	}

	$html .= '</span>';
	return $html;
}

/** Shortcode [lsv_landerichtung wind="ja" groesse="gross"] */
function lsvw_schild_shortcode( $atts ) {
	lsvw_assets_laden();
	return lsvw_schild_html( is_array( $atts ) ? $atts : array() );
}

function lsvw_werte_shortcodes_registrieren() {
	add_shortcode( 'lsv_wert', 'lsvw_wert_shortcode' );
	foreach ( array_keys( lsvw_felder() ) as $feld ) {
		if ( ! shortcode_exists( 'lsv_' . $feld ) ) {
			add_shortcode( 'lsv_' . $feld, 'lsvw_wert_shortcode' );
		}
	}
	add_shortcode( 'lsv_landerichtung', 'lsvw_schild_shortcode' );
	add_shortcode( 'lsv_schild', 'lsvw_schild_shortcode' );
}
add_action( 'init', 'lsvw_werte_shortcodes_registrieren' );

/**
 * REST: /wp-json/lsv/v1/werte – alle Einzelwerte für die Live-Aktualisierung.
 * REST: /wp-json/lsv/v1/schild?wind=ja&groesse=gross – Pistenschild als HTML.
 */
function lsvw_werte_rest() {
	register_rest_route(
		'lsv/v1',
		'/werte',
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => function () {
				$res = new WP_REST_Response( array( 'werte' => (object) lsvw_werte() ) );
				$res->header( 'Cache-Control', 'public, max-age=60' );
				return $res;
			},
		)
	);
	register_rest_route(
		'lsv/v1',
		'/schild',
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => function ( WP_REST_Request $req ) {
				$html = lsvw_schild_html(
					array(
						'wind'    => sanitize_text_field( (string) $req->get_param( 'wind' ) ),
						'groesse' => sanitize_key( (string) $req->get_param( 'groesse' ) ),
					)
				);
				$res = new WP_REST_Response( array( 'html' => $html ) );
				$res->header( 'Cache-Control', 'public, max-age=60' );
				return $res;
			},
		)
	);
}
add_action( 'rest_api_init', 'lsvw_werte_rest' );
