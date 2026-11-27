<?php
/**
 * Flugzeugdaten: Laden, Vorgaben und Prüfen der Eingaben aus dem Backend.
 *
 * Gespeichert in der Option "wxsp_flugzeuge" als Liste:
 *   id, name, waegedatum, leermasse, leerarm, mtom, cg_vorn, cg_hinten,
 *   mac_vorderkante, mac, tank_arm, tank_max, kg_pro_liter, verbrauch, handbuch,
 *   posten: [ { name, arm, min, max, pflicht } ]
 */

defined( 'ABSPATH' ) || exit;

final class WXSP_Daten {

	const OPTION = 'wxsp_flugzeuge';

	/** Zahlenfelder eines Flugzeugs mit Beschriftung und Einheit (Reihenfolge = Backend). */
	public static function zahlenfelder() {
		return array(
			'leermasse'       => array( 'Leermasse', 'kg' ),
			'leerarm'         => array( 'Hebelarm Leermasse', 'mm' ),
			'mtom'            => array( 'Max. Startmasse (MTOM)', 'kg' ),
			'cg_vorn'         => array( 'Schwerpunkt vordere Grenze', 'mm' ),
			'cg_hinten'       => array( 'Schwerpunkt hintere Grenze', 'mm' ),
			'mac_vorderkante' => array( 'MAC-Vorderkante', 'mm' ),
			'mac'             => array( 'MAC (Länge)', 'mm' ),
			'tank_arm'        => array( 'Hebelarm Kraftstoff', 'mm' ),
			'tank_max'        => array( 'Max. Tankinhalt (0 = unbekannt)', 'l' ),
			'kg_pro_liter'    => array( 'Kraftstoffdichte', 'kg/l' ),
			'verbrauch'       => array( 'Verbrauch für die Flugzeit', 'l/h' ),
		);
	}

	/** Ausgangsdaten: Bristell D-MDMP wie in der früheren Google-Tabelle. */
	public static function vorgabe() {
		return array(
			array(
				'id'              => 'd-mdmp',
				'name'            => 'Bristell D-MDMP',
				'waegedatum'      => '',
				'leermasse'       => 366.5,
				'leerarm'         => 730.79,
				'mtom'            => 600,
				'cg_vorn'         => 750,
				'cg_hinten'       => 871,
				'mac_vorderkante' => 412.6,
				'mac'             => 1349.7,
				'tank_arm'        => 606,
				'tank_max'        => 120,
				'kg_pro_liter'    => 0.72,
				'verbrauch'       => 19,
				'handbuch'        => '/wp-content/uploads/Betriebshandbuch-D-MDMP.pdf',
				'posten'          => array(
					array( 'name' => 'Pilot', 'arm' => 1156, 'min' => 55, 'max' => 110, 'pflicht' => 1 ),
					array( 'name' => 'Passagier', 'arm' => 1156, 'min' => 0, 'max' => 0, 'pflicht' => 0 ),
					array( 'name' => 'Gepäck hinter den Sitzen', 'arm' => 1806, 'min' => 0, 'max' => 15, 'pflicht' => 0 ),
					array( 'name' => 'Flügelkoffer links', 'arm' => 1036, 'min' => 0, 'max' => 20, 'pflicht' => 0 ),
					array( 'name' => 'Flügelkoffer rechts', 'arm' => 1036, 'min' => 0, 'max' => 20, 'pflicht' => 0 ),
				),
			),
		);
	}

	/** Leeres Flugzeug als Vorlage für „Flugzeug hinzufügen“. */
	public static function leeres_flugzeug() {
		$f = array_fill_keys( array_keys( self::zahlenfelder() ), '' );
		$f['kg_pro_liter'] = 0.72;
		return array_merge(
			array( 'id' => '', 'name' => '', 'waegedatum' => '', 'handbuch' => '' ),
			$f,
			array( 'posten' => array( array( 'name' => 'Pilot', 'arm' => '', 'min' => '', 'max' => '', 'pflicht' => 1 ) ) )
		);
	}

	public static function alle() {
		$daten = get_option( self::OPTION, null );
		return is_array( $daten ) ? $daten : self::vorgabe();
	}

	/** Flugzeug per ID oder Name; ohne Angabe das erste. */
	public static function finde( $wer ) {
		$alle = self::alle();
		$wer  = trim( (string) $wer );
		if ( '' === $wer ) {
			return $alle ? $alle[0] : null;
		}
		foreach ( $alle as $f ) {
			if ( 0 === strcasecmp( $wer, $f['id'] ) || 0 === strcasecmp( $wer, $f['name'] ) ) {
				return $f;
			}
		}
		return null;
	}

	public static function zahl( $wert ) {
		$wert = str_replace( array( ' ', ',' ), array( '', '.' ), (string) $wert );
		return is_numeric( $wert ) ? (float) $wert : 0.0;
	}

	/**
	 * Eingaben aus dem Backend bereinigen (register_setting → sanitize_callback).
	 */
	public static function bereinigen( $eingabe ) {
		$aus = array();
		$ids = array();
		foreach ( (array) $eingabe as $f ) {
			if ( ! is_array( $f ) || ! empty( $f['entfernen'] ) ) {
				continue;
			}
			$name = sanitize_text_field( $f['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}
			$id = sanitize_title( ( $f['id'] ?? '' ) !== '' ? $f['id'] : $name );
			$basis = $id;
			for ( $i = 2; in_array( $id, $ids, true ); $i++ ) {
				$id = $basis . '-' . $i;
			}
			$ids[] = $id;

			$neu = array(
				'id'         => $id,
				'name'       => $name,
				'waegedatum' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $f['waegedatum'] ?? '' ) ? $f['waegedatum'] : '',
				'handbuch'   => esc_url_raw( $f['handbuch'] ?? '' ),
			);
			foreach ( array_keys( self::zahlenfelder() ) as $k ) {
				$neu[ $k ] = self::zahl( $f[ $k ] ?? 0 );
			}

			$neu['posten'] = array();
			foreach ( (array) ( $f['posten'] ?? array() ) as $p ) {
				$pname = sanitize_text_field( $p['name'] ?? '' );
				if ( '' === $pname ) {
					continue;
				}
				$neu['posten'][] = array(
					'name'    => $pname,
					'arm'     => self::zahl( $p['arm'] ?? 0 ),
					'min'     => max( 0, self::zahl( $p['min'] ?? 0 ) ),
					'max'     => max( 0, self::zahl( $p['max'] ?? 0 ) ),
					'pflicht' => empty( $p['pflicht'] ) ? 0 : 1,
				);
			}

			$fehler = array();
			if ( $neu['mac'] <= 0 ) {
				$fehler[] = 'MAC muss größer als 0 sein';
			}
			if ( $neu['cg_hinten'] <= $neu['cg_vorn'] ) {
				$fehler[] = 'die hintere Schwerpunktgrenze muss hinter der vorderen liegen';
			}
			if ( $neu['leermasse'] <= 0 || $neu['mtom'] <= 0 ) {
				$fehler[] = 'Leermasse und MTOM fehlen';
			}
			if ( ! $neu['posten'] ) {
				$fehler[] = 'es ist kein Zuladungsposten angelegt';
			}
			if ( $fehler ) {
				add_settings_error( self::OPTION, 'wxsp-' . $id, $name . ': ' . implode( ', ', $fehler ) . '. Der Rechner wird für dieses Flugzeug erst angezeigt, wenn das korrigiert ist.', 'warning' );
			}
			$aus[] = $neu;
		}
		return $aus;
	}

	/** Prüft, ob ein Flugzeug genug Daten zum Rechnen hat. */
	public static function vollstaendig( $f ) {
		return $f && $f['mac'] > 0 && $f['cg_hinten'] > $f['cg_vorn'] && $f['leermasse'] > 0 && $f['mtom'] > 0 && ! empty( $f['posten'] );
	}
}
