<?php
/**
 * Beitragstyp "wx_popup" und seine Einstellungen.
 */

defined( 'ABSPATH' ) || exit;

final class WXPU_Popup {

	const TYPE = 'wx_popup';
	const META = '_wxpu';

	public static function register() {
		register_post_type(
			self::TYPE,
			array(
				'labels'              => array(
					'name'               => 'Pop-ups',
					'singular_name'      => 'Pop-up',
					'add_new'            => 'Neues Pop-up',
					'add_new_item'       => 'Neues Pop-up erstellen',
					'edit_item'          => 'Pop-up bearbeiten',
					'new_item'           => 'Neues Pop-up',
					'view_item'          => 'Pop-up ansehen',
					'search_items'       => 'Pop-ups durchsuchen',
					'not_found'          => 'Keine Pop-ups gefunden.',
					'not_found_in_trash' => 'Keine Pop-ups im Papierkorb.',
					'all_items'          => 'Alle Pop-ups',
					'menu_name'          => 'Pop-ups',
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => true, // Block-Editor
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'menu_icon'           => 'dashicons-format-image',
				'menu_position'       => 25,
				'supports'            => array( 'title', 'editor', 'revisions' ),
				'capability_type'     => 'page',
				'map_meta_cap'        => true,
			)
		);
	}

	public static function defaults() {
		return array(
			'aktiv'        => 0,
			'von'          => '',          // Y-m-d\TH:i, Ortszeit
			'bis'          => '',
			'wo'           => 'startseite', // startseite | alle | seiten
			'seiten'       => array(),      // Seiten-/Beitrags-IDs bei wo=seiten
			'verzoegerung' => 1,            // Sekunden
			'wiederholung' => 'tage',       // immer | sitzung | tage
			'tage'         => 1,
			'breite'       => 'mittel',     // klein | mittel | gross | auto
			'aussen'       => 1,            // Klick neben das Pop-up schließt es
		);
	}

	public static function get( $id ) {
		$m = get_post_meta( $id, self::META, true );
		return wp_parse_args( is_array( $m ) ? $m : array(), self::defaults() );
	}

	public static function bereinigen( $in ) {
		$d   = self::defaults();
		$zeit = function ( $v ) {
			$v = sanitize_text_field( (string) $v );
			return preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $v ) ? $v : '';
		};
		return array(
			'aktiv'        => empty( $in['aktiv'] ) ? 0 : 1,
			'von'          => $zeit( $in['von'] ?? '' ),
			'bis'          => $zeit( $in['bis'] ?? '' ),
			'wo'           => in_array( $in['wo'] ?? '', array( 'startseite', 'alle', 'seiten' ), true ) ? $in['wo'] : $d['wo'],
			'seiten'       => array_values( array_filter( array_map( 'absint', (array) ( $in['seiten'] ?? array() ) ) ) ),
			'verzoegerung' => max( 0, min( 600, (float) str_replace( ',', '.', (string) ( $in['verzoegerung'] ?? 0 ) ) ) ),
			'wiederholung' => in_array( $in['wiederholung'] ?? '', array( 'immer', 'sitzung', 'tage' ), true ) ? $in['wiederholung'] : $d['wiederholung'],
			'tage'         => max( 1, min( 365, absint( $in['tage'] ?? 1 ) ) ),
			'breite'       => in_array( $in['breite'] ?? '', array( 'klein', 'mittel', 'gross', 'auto' ), true ) ? $in['breite'] : $d['breite'],
			'aussen'       => empty( $in['aussen'] ) ? 0 : 1,
		);
	}

	/** Zeitstempel aus "Y-m-d\TH:i" in Ortszeit der Website (0 = nicht gesetzt). */
	public static function ts( $wert ) {
		if ( ! $wert ) {
			return 0;
		}
		$dt = date_create_immutable_from_format( 'Y-m-d\TH:i', $wert, wp_timezone() );
		return $dt ? $dt->getTimestamp() : 0;
	}

	/** Status für die Liste: aus | geplant | aktiv | abgelaufen */
	public static function status( $id ) {
		$s = self::get( $id );
		if ( ! $s['aktiv'] || 'publish' !== get_post_status( $id ) ) {
			return 'aus';
		}
		$jetzt = time();
		if ( self::ts( $s['von'] ) && $jetzt < self::ts( $s['von'] ) ) {
			return 'geplant';
		}
		if ( self::ts( $s['bis'] ) && $jetzt > self::ts( $s['bis'] ) ) {
			return 'abgelaufen';
		}
		return 'aktiv';
	}
}
