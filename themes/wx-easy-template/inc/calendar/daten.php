<?php
/**
 * Integrierter Kalender, basierend auf WX Easy Calendar 2.0.1 – Daten und Abfragen.
 * Lizenz: GPL-2.0-or-later.
 * @package Dunkel_Schlicht
 */

namespace DunkelSchlicht\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const EK_DB_VERSION = 2;

/* -------------------------------------------------------------------------
 * Beitragstyp und Taxonomien
 * ---------------------------------------------------------------------- */

function ek_register_post_type() {
	register_post_type(
		'ek_termin',
		array(
			'labels'       => array(
				'name'                  => 'Termine',
				'singular_name'         => 'Termin',
				'menu_name'             => 'Kalender',
				'add_new'               => 'Neuer Termin',
				'add_new_item'          => 'Neuen Termin anlegen',
				'edit_item'             => 'Termin bearbeiten',
				'new_item'              => 'Neuer Termin',
				'view_item'             => 'Termin ansehen',
				'all_items'             => 'Alle Termine',
				'search_items'          => 'Termine durchsuchen',
				'not_found'             => 'Keine Termine gefunden.',
				'not_found_in_trash'    => 'Keine Termine im Papierkorb.',
				'featured_image'        => 'Bild',
				'set_featured_image'    => 'Bild auswählen',
				'remove_featured_image' => 'Bild entfernen',
			),
			'public'       => true,
			'has_archive'  => false,
			'rewrite'      => array( 'slug' => 'termine' ),
			'menu_icon'    => 'dashicons-calendar-alt',
			'supports'     => array( 'title', 'editor', 'thumbnail' ),
			'show_in_rest' => true,
		)
	);

	register_taxonomy(
		'ek_kategorie',
		'ek_termin',
		array(
			'labels'            => array(
				'name'          => 'Kategorien',
				'singular_name' => 'Kategorie',
				'menu_name'     => 'Kategorien',
				'all_items'     => 'Alle Kategorien',
				'edit_item'     => 'Kategorie bearbeiten',
				'add_new_item'  => 'Neue Kategorie hinzufügen',
				'new_item_name' => 'Name der neuen Kategorie',
				'search_items'  => 'Kategorien durchsuchen',
				'not_found'     => 'Keine Kategorien gefunden.',
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'termin-kategorie' ),
		)
	);

	register_taxonomy(
		'ek_ort',
		'ek_termin',
		array(
			'labels'       => array(
				'name'          => 'Orte',
				'singular_name' => 'Ort',
				'menu_name'     => 'Orte',
				'add_new_item'  => 'Neuen Ort hinzufügen',
				'edit_item'     => 'Ort bearbeiten',
				'search_items'  => 'Orte durchsuchen',
				'not_found'     => 'Keine Orte gefunden.',
			),
			'hierarchical' => false,
			'public'       => false,
			'show_ui'      => true,
			'show_in_rest' => false,
			'meta_box_cb'  => false,
			'rewrite'      => false,
		)
	);

	register_taxonomy(
		'ek_veranstalter',
		'ek_termin',
		array(
			'labels'       => array(
				'name'          => 'Veranstalter',
				'singular_name' => 'Veranstalter',
				'menu_name'     => 'Veranstalter',
				'add_new_item'  => 'Neuen Veranstalter hinzufügen',
				'edit_item'     => 'Veranstalter bearbeiten',
				'search_items'  => 'Veranstalter durchsuchen',
				'not_found'     => 'Keine Veranstalter gefunden.',
			),
			'hierarchical' => false,
			'public'       => false,
			'show_ui'      => true,
			'show_in_rest' => false,
			'meta_box_cb'  => false,
			'rewrite'      => false,
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\ek_register_post_type' );

/**
 * Einmalige Ergänzung bestehender Termine aus Version 1: „letzter Tag“ setzen,
 * damit Serien und Abfragen einheitlich funktionieren. Bestehende Daten bleiben unverändert.
 */
function ek_daten_aktualisieren() {
	if ( (int) get_option( 'ek_db_version', 1 ) >= EK_DB_VERSION ) {
		return;
	}
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$ids = $wpdb->get_col(
		"SELECT p.ID FROM {$wpdb->posts} p
		 INNER JOIN {$wpdb->postmeta} s ON s.post_id = p.ID AND s.meta_key = '_ek_start'
		 LEFT JOIN {$wpdb->postmeta} l ON l.post_id = p.ID AND l.meta_key = '_ek_letzter'
		 WHERE p.post_type = 'ek_termin' AND l.meta_id IS NULL"
	);
	foreach ( (array) $ids as $id ) {
		$ende = get_post_meta( (int) $id, '_ek_ende', true );
		$ende = $ende ? $ende : get_post_meta( (int) $id, '_ek_start', true );
		update_post_meta( (int) $id, '_ek_letzter', $ende );
	}
	update_option( 'ek_db_version', EK_DB_VERSION, true );
}
add_action( 'init', __NAMESPACE__ . '\\ek_daten_aktualisieren', 20 );

/* -------------------------------------------------------------------------
 * Prüfen und Formatieren
 * ---------------------------------------------------------------------- */

/** Prüft ein Datum im Format JJJJ-MM-TT und gibt es zurück oder ''. */
function ek_datum_pruefen( $wert ) {
	$wert = is_string( $wert ) ? trim( $wert ) : '';
	$d    = DateTimeImmutable::createFromFormat( '!Y-m-d', $wert );
	return ( $d && $d->format( 'Y-m-d' ) === $wert ) ? $wert : '';
}

/** „24.12.2026“ oder „2026-12-24“ → „2026-12-24“, sonst ''. */
function ek_datum_lesen( $wert ) {
	$wert = trim( (string) $wert );
	if ( preg_match( '/^(\d{1,2})\.(\d{1,2})\.(\d{2,4})$/', $wert, $m ) ) {
		$j = (int) $m[3];
		$j = $j < 100 ? 2000 + $j : $j;
		return checkdate( (int) $m[2], (int) $m[1], $j ) ? sprintf( '%04d-%02d-%02d', $j, $m[2], $m[1] ) : '';
	}
	return ek_datum_pruefen( $wert );
}

/** Prüft eine Uhrzeit im Format HH:MM und gibt sie zurück oder ''. */
function ek_zeit_pruefen( $wert ) {
	$wert = is_string( $wert ) ? trim( $wert ) : '';
	return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $wert ) ? $wert : '';
}

/** Zeitstempel eines Tages (UTC-Mitternacht, nur zum Rechnen). */
function ek_ts( $ymd ) {
	$d = DateTimeImmutable::createFromFormat( '!Y-m-d', substr( (string) $ymd, 0, 10 ), new DateTimeZone( 'UTC' ) );
	return $d ? $d->getTimestamp() : 0;
}

/** Tag verschieben: ek_plus_tage( '2026-11-30', 2 ) → '2026-12-02'. */
function ek_plus_tage( $ymd, $tage ) {
	return gmdate( 'Y-m-d', ek_ts( $ymd ) + (int) $tage * DAY_IN_SECONDS );
}

/** Formatiert ein JJJJ-MM-TT-Datum (Standard: Datumsformat der Website). */
function ek_datum_ausgeben( $ymd, $format = '' ) {
	$ts = ek_ts( $ymd );
	if ( ! $ts ) {
		return '';
	}
	return wp_date( $format ? $format : get_option( 'date_format' ), $ts + 12 * HOUR_IN_SECONDS, new DateTimeZone( 'UTC' ) );
}

/* -------------------------------------------------------------------------
 * Termindaten
 * ---------------------------------------------------------------------- */

/** Erster Begriff einer Taxonomie am Termin oder null. */
function ek_erster_begriff( $post_id, $tax ) {
	$terms = get_the_terms( $post_id, $tax );
	return ( $terms && ! is_wp_error( $terms ) ) ? reset( $terms ) : null;
}

/** Ort eines Termins: aus der Ortsliste oder frei eingegeben. */
function ek_ort_daten( $post_id ) {
	$term = ek_erster_begriff( $post_id, 'ek_ort' );
	if ( $term ) {
		$plz   = (string) get_term_meta( $term->term_id, 'ek_plz', true );
		$stadt = (string) get_term_meta( $term->term_id, 'ek_stadt', true );
		return array(
			'name'    => $term->name,
			'adresse' => trim( implode( ', ', array_filter( array( (string) get_term_meta( $term->term_id, 'ek_strasse', true ), trim( $plz . ' ' . $stadt ) ) ) ) ),
			'strasse' => (string) get_term_meta( $term->term_id, 'ek_strasse', true ),
			'plz'     => $plz,
			'stadt'   => $stadt,
			'frei'    => false,
		);
	}
	$name = (string) get_post_meta( $post_id, '_ek_ort', true );
	if ( '' === $name ) {
		return null;
	}
	$adresse = (string) get_post_meta( $post_id, '_ek_ort_adresse', true );
	return array(
		'name'    => $name,
		'adresse' => $adresse,
		'strasse' => $adresse,
		'plz'     => '',
		'stadt'   => '',
		'frei'    => true,
	);
}

/** Veranstalter mit Kontakt oder null. */
function ek_veranstalter_daten( $post_id ) {
	$term = ek_erster_begriff( $post_id, 'ek_veranstalter' );
	if ( ! $term ) {
		return null;
	}
	return array(
		'name'    => $term->name,
		'telefon' => (string) get_term_meta( $term->term_id, 'ek_telefon', true ),
		'email'   => (string) get_term_meta( $term->term_id, 'ek_email', true ),
		'web'     => (string) get_term_meta( $term->term_id, 'ek_web', true ),
	);
}

/** Liefert alle Termin-Daten eines Beitrags. */
function ek_termin_daten( $post_id ) {
	$start = (string) get_post_meta( $post_id, '_ek_start', true );
	$ende  = (string) get_post_meta( $post_id, '_ek_ende', true );
	$terms = get_the_terms( $post_id, 'ek_kategorie' );
	$regel = get_post_meta( $post_id, '_ek_regel', true );
	$ort   = ek_ort_daten( $post_id );

	return array(
		'start'       => $start,
		'ende'        => $ende ? $ende : $start,
		'zeit'        => (string) get_post_meta( $post_id, '_ek_zeit', true ),
		'zeit_ende'   => (string) get_post_meta( $post_id, '_ek_zeit_ende', true ),
		'ort'         => $ort ? $ort['name'] : '', // wie in Version 1: Name des Ortes als Text
		'ort_daten'   => $ort,
		'veranstalter'=> ek_veranstalter_daten( $post_id ),
		'kategorien'  => ( $terms && ! is_wp_error( $terms ) ) ? $terms : array(),
		'regel'       => ( is_array( $regel ) && ! empty( $regel['freq'] ) ) ? $regel : null,
	);
}

/**
 * Speichert Datum, Uhrzeit und Serie und berechnet den letzten Tag.
 *
 * @param int         $post_id   Termin.
 * @param string      $start     JJJJ-MM-TT.
 * @param string      $ende      JJJJ-MM-TT.
 * @param string      $zeit      HH:MM oder '' (ganztägig).
 * @param string      $zeit_ende HH:MM oder ''.
 * @param array|null  $regel     Serie.
 */
function ek_termin_speichern( $post_id, $start, $ende, $zeit, $zeit_ende, $regel ) {
	if ( ! $ende || $ende < $start ) {
		$ende = $start;
	}
	if ( ! $zeit ) {
		$zeit_ende = '';
	}
	if ( $zeit_ende && $ende === $start && $zeit_ende <= $zeit ) {
		$zeit_ende = '';
	}

	update_post_meta( $post_id, '_ek_start', $start );
	update_post_meta( $post_id, '_ek_ende', $ende );
	update_post_meta( $post_id, '_ek_zeit', $zeit );
	update_post_meta( $post_id, '_ek_zeit_ende', $zeit_ende );

	$regel = ek_regel_bereinigen( $regel, $start );
	if ( $regel ) {
		update_post_meta( $post_id, '_ek_regel', $regel );
		$dauer = (int) round( ( ek_ts( $ende ) - ek_ts( $start ) ) / DAY_IN_SECONDS );
		update_post_meta( $post_id, '_ek_letzter', ek_plus_tage( $regel['until'], $dauer ) );
	} else {
		delete_post_meta( $post_id, '_ek_regel' );
		update_post_meta( $post_id, '_ek_letzter', $ende );
	}
}

/* -------------------------------------------------------------------------
 * Serien
 * ---------------------------------------------------------------------- */

/** Prüft eine Serie und vereinheitlicht sie, sonst null. */
function ek_regel_bereinigen( $regel, $start ) {
	if ( ! is_array( $regel ) || empty( $regel['freq'] ) ) {
		return null;
	}
	$freq = (string) $regel['freq'];
	if ( ! in_array( $freq, array( 'weekly', 'monthly_day', 'monthly_nth', 'monthly_last' ), true ) ) {
		return null;
	}

	$until = isset( $regel['until'] ) ? ek_datum_pruefen( (string) $regel['until'] ) : '';
	if ( ! $until || $until <= $start ) {
		$until = ek_plus_tage( $start, 365 ); // ohne gültiges Ende: ein Jahr
	}
	$max = ek_plus_tage( $start, 5 * 366 ); // höchstens fünf Jahre
	if ( $until > $max ) {
		$until = $max;
	}

	$aus = array();
	if ( ! empty( $regel['exdates'] ) && is_array( $regel['exdates'] ) ) {
		foreach ( $regel['exdates'] as $d ) {
			$d = ek_datum_pruefen( (string) $d );
			if ( $d ) {
				$aus[] = $d;
			}
		}
	}
	$aus = array_values( array_unique( $aus ) );
	sort( $aus );

	$sauber = array(
		'freq'     => $freq,
		'interval' => isset( $regel['interval'] ) ? max( 1, min( 12, (int) $regel['interval'] ) ) : 1,
		'until'    => $until,
		'exdates'  => $aus,
	);

	if ( 'weekly' === $freq ) {
		$tage = array( (int) gmdate( 'w', ek_ts( $start ) ) ); // Tag des Beginns ist immer dabei
		if ( ! empty( $regel['days'] ) && is_array( $regel['days'] ) ) {
			foreach ( $regel['days'] as $t ) {
				if ( is_numeric( $t ) && (int) $t >= 0 && (int) $t <= 6 ) {
					$tage[] = (int) $t;
				}
			}
		}
		$tage = array_values( array_unique( $tage ) );
		sort( $tage );
		$sauber['days'] = $tage;
	}
	return $sauber;
}

/** Wochentage einer wöchentlichen Serie (0 = Sonntag). */
function ek_regel_tage( $regel, $start ) {
	if ( ! empty( $regel['days'] ) && is_array( $regel['days'] ) ) {
		return array_map( 'intval', $regel['days'] );
	}
	return array( (int) gmdate( 'w', ek_ts( $start ) ) );
}

/**
 * Alle Vorkommen eines Termins im Zeitraum.
 *
 * @return array<int,array{start:string,ende:string}>
 */
function ek_vorkommen( $daten, $von, $bis ) {
	$start = $daten['start'];
	if ( ! $start ) {
		return array();
	}
	$dauer = max( 0, (int) round( ( ek_ts( $daten['ende'] ) - ek_ts( $start ) ) / DAY_IN_SECONDS ) );
	$regel = $daten['regel'];
	$aus   = array();

	$treffer = function ( $tag ) use ( $dauer, $von, $bis ) {
		return $tag <= $bis && ek_plus_tage( $tag, $dauer ) >= $von;
	};

	if ( ! $regel ) {
		return $treffer( $start ) ? array( array( 'start' => $start, 'ende' => $daten['ende'] ) ) : array();
	}

	$until = $regel['until'];
	$aus   = isset( $regel['exdates'] ) ? $regel['exdates'] : array();
	$step  = max( 1, (int) $regel['interval'] );
	$basis = ek_ts( $start );
	$wt    = (int) gmdate( 'w', $basis );
	$tag_m = (int) gmdate( 'j', $basis );
	$nth   = (int) ceil( $tag_m / 7 );
	$liste = array();

	if ( 'weekly' === $regel['freq'] ) {
		$abstaende = array();
		foreach ( ek_regel_tage( $regel, $start ) as $t ) {
			$abstaende[] = ( $t + 6 ) % 7; // Abstand zum Montag
		}
		$abstaende = array_values( array_unique( $abstaende ) );
		sort( $abstaende );
		$montag = ek_plus_tage( $start, -( ( $wt + 6 ) % 7 ) );

		for ( $w = 0; $w < 2000 && count( $liste ) < 500; $w++ ) {
			$woche = ek_plus_tage( $montag, $w * 7 * $step );
			if ( $woche > $until || $woche > $bis ) {
				break;
			}
			foreach ( $abstaende as $a ) {
				$tag = ek_plus_tage( $woche, $a );
				if ( $tag > $until || $tag > $bis ) {
					break 2;
				}
				if ( $tag >= $start && ! in_array( $tag, $aus, true ) && $treffer( $tag ) ) {
					$liste[] = array( 'start' => $tag, 'ende' => ek_plus_tage( $tag, $dauer ) );
				}
			}
		}
		return $liste;
	}

	$j0 = (int) gmdate( 'Y', $basis );
	$m0 = (int) gmdate( 'n', $basis );
	for ( $i = 0; $i < 600 && count( $liste ) < 500; $i++ ) {
		$gesamt  = ( $m0 - 1 ) + $i * $step;
		$j       = $j0 + intdiv( $gesamt, 12 );
		$m       = ( $gesamt % 12 ) + 1;
		$erster  = sprintf( '%04d-%02d-01', $j, $m );
		if ( $erster > $until || $erster > $bis ) {
			break;
		}
		$tage_im = (int) gmdate( 't', ek_ts( $erster ) );
		$tag     = '';

		if ( 'monthly_day' === $regel['freq'] ) {
			if ( $tag_m <= $tage_im ) {
				$tag = sprintf( '%04d-%02d-%02d', $j, $m, $tag_m );
			}
		} elseif ( 'monthly_nth' === $regel['freq'] ) {
			$erster_wt = (int) gmdate( 'w', ek_ts( $erster ) );
			$d         = 1 + ( ( $wt - $erster_wt + 7 ) % 7 ) + ( $nth - 1 ) * 7;
			if ( $d <= $tage_im ) {
				$tag = sprintf( '%04d-%02d-%02d', $j, $m, $d );
			}
		} else { // monthly_last
			$letzter_wt = (int) gmdate( 'w', ek_ts( sprintf( '%04d-%02d-%02d', $j, $m, $tage_im ) ) );
			$tag        = sprintf( '%04d-%02d-%02d', $j, $m, $tage_im - ( ( $letzter_wt - $wt + 7 ) % 7 ) );
		}

		if ( ! $tag || $tag < $start || $tag > $until || in_array( $tag, $aus, true ) ) {
			continue;
		}
		if ( $tag > $bis ) {
			break;
		}
		if ( $treffer( $tag ) ) {
			$liste[] = array( 'start' => $tag, 'ende' => ek_plus_tage( $tag, $dauer ) );
		}
	}
	return $liste;
}

/** „jeden Dienstag und Donnerstag bis 31. Dezember 2026“ */
function ek_regel_text( $daten ) {
	$r = $daten['regel'];
	if ( ! $r ) {
		return '';
	}
	$ts    = ek_ts( $daten['start'] ) + 12 * HOUR_IN_SECONDS;
	$utc   = new DateTimeZone( 'UTC' );
	$wt    = wp_date( 'l', $ts, $utc );
	$tag   = (int) gmdate( 'j', $ts );
	$bis   = ek_datum_ausgeben( $r['until'], 'j. F Y' );
	$schr  = (int) $r['interval'];

	switch ( $r['freq'] ) {
		case 'weekly':
			$namen = ek_wochentage_liste( ek_regel_tage( $r, $daten['start'] ) );
			$text  = 1 === $schr ? 'jeden ' . $namen : 'alle ' . $schr . ' Wochen am ' . $namen;
			break;
		case 'monthly_day':
			$text = 'jeden Monat am ' . $tag . '.';
			break;
		case 'monthly_nth':
			$text = 'jeden ' . (int) ceil( $tag / 7 ) . '. ' . $wt . ' im Monat';
			break;
		default:
			$text = 'jeden letzten ' . $wt . ' im Monat';
	}
	return $text . ' bis ' . $bis;
}

/** „Dienstag und Donnerstag“ (Montag zuerst). */
function ek_wochentage_liste( $tage ) {
	usort(
		$tage,
		function ( $a, $b ) {
			return ( ( $a + 6 ) % 7 ) - ( ( $b + 6 ) % 7 );
		}
	);
	$namen = array();
	foreach ( $tage as $t ) {
		// 7. Januar 2024 war ein Sonntag.
		$namen[] = wp_date( 'l', ek_ts( '2024-01-07' ) + $t * DAY_IN_SECONDS + 12 * HOUR_IN_SECONDS, new DateTimeZone( 'UTC' ) );
	}
	if ( count( $namen ) < 2 ) {
		return (string) reset( $namen );
	}
	$letzter = array_pop( $namen );
	return implode( ', ', $namen ) . ' und ' . $letzter;
}

/* -------------------------------------------------------------------------
 * Kategorien und Farben
 * ---------------------------------------------------------------------- */

/** Farbe einer Kategorie (Hex) oder ''. */
function ek_kategorie_farbe( $term_id ) {
	$farbe = sanitize_hex_color( (string) get_term_meta( $term_id, 'ek_farbe', true ) );
	return $farbe ? $farbe : '';
}

/** Farbe eines Termins = Farbe seiner ersten Kategorie mit Farbe. */
function ek_termin_farbe( $daten ) {
	foreach ( $daten['kategorien'] as $term ) {
		$farbe = ek_kategorie_farbe( $term->term_id );
		if ( $farbe ) {
			return $farbe;
		}
	}
	return '';
}

/** style-Attribut, das die Terminfarbe und eine gut lesbare Schriftfarbe dazu setzt. */
function ek_farb_style( $farbe ) {
	if ( ! $farbe ) {
		return '';
	}
	return ' style="--ek-farbe:' . esc_attr( $farbe ) . ';--ek-farbe-text:' . esc_attr( ek_text_auf( $farbe ) ) . '"';
}

/** Liefert Weiß oder Fast-Schwarz – je nachdem, was auf $farbe besser lesbar ist. */
function ek_text_auf( $farbe ) {
	$hex = sanitize_hex_color( (string) $farbe );
	if ( ! $hex ) {
		return '#fff';
	}
	$hex = ltrim( $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	$kanal = array();
	foreach ( str_split( $hex, 2 ) as $teil ) {
		$c       = hexdec( $teil ) / 255;
		$kanal[] = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
	}
	$lum = 0.2126 * $kanal[0] + 0.7152 * $kanal[1] + 0.0722 * $kanal[2];
	// Kontrast zu Weiß (Leuchtdichte 1) gegen Kontrast zu #111 (Leuchtdichte ~0,0056).
	return ( 1.05 / ( $lum + 0.05 ) ) >= ( ( $lum + 0.05 ) / 0.0556 ) ? '#fff' : '#111';
}

/** Wandelt "slug1, slug2" in ein Array gültiger Kategorie-Slugs um. */
function ek_kategorien_parsen( $wert ) {
	$slugs = array_filter( array_map( 'sanitize_title', explode( ',', (string) $wert ) ) );
	return array_values(
		array_unique(
			array_filter(
				$slugs,
				function ( $slug ) {
					return (bool) get_term_by( 'slug', $slug, 'ek_kategorie' );
				}
			)
		)
	);
}

/* -------------------------------------------------------------------------
 * Abfragen
 * ---------------------------------------------------------------------- */

/**
 * Holt veröffentlichte Termine (Serien als einzelne Vorkommen), die den Zeitraum berühren.
 * $bis = '' bedeutet: ohne Ende (alle ab $von; Serien höchstens ein Jahr voraus).
 *
 * Jeder Eintrag: array( 'post' => WP_Post, 'daten' => …, 'serie' => bool )
 * – bei Serien stehen in 'daten' Start und Ende des jeweiligen Vorkommens.
 */
function ek_termine_holen( $von, $bis, $anzahl = -1, $kategorien = array(), $suche = '' ) {
	$meta_query = array(
		'relation' => 'AND',
		array(
			'key'     => '_ek_letzter',
			'value'   => $von,
			'compare' => '>=',
			'type'    => 'DATE',
		),
	);
	if ( $bis ) {
		$meta_query[] = array(
			'key'     => '_ek_start',
			'value'   => $bis,
			'compare' => '<=',
			'type'    => 'DATE',
		);
	}

	$args = array(
		'post_type'        => 'ek_termin',
		'post_status'      => 'publish',
		'posts_per_page'   => -1,
		'meta_query'       => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery
		'suppress_filters' => false,
	);
	if ( $kategorien ) {
		$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array(
				'taxonomy' => 'ek_kategorie',
				'field'    => 'slug',
				'terms'    => $kategorien,
			),
		);
	}
	if ( '' !== $suche ) {
		$args['s'] = $suche;
	}

	$grenze  = $bis ? $bis : ek_plus_tage( $von, 400 );
	$termine = array();
	foreach ( get_posts( $args ) as $post ) {
		$daten = ek_termin_daten( $post->ID );
		foreach ( ek_vorkommen( $daten, $von, $grenze ) as $v ) {
			$d          = $daten;
			$d['start'] = $v['start'];
			$d['ende']  = $v['ende'];
			$termine[]  = array(
				'post'  => $post,
				'daten' => $d,
				'serie' => (bool) $daten['regel'],
			);
		}
	}

	// Nach Startdatum und Uhrzeit sortieren (ganztägige zuerst).
	usort(
		$termine,
		function ( $a, $b ) {
			$ka = $a['daten']['start'] . ( $a['daten']['zeit'] ? $a['daten']['zeit'] : '00:00' );
			$kb = $b['daten']['start'] . ( $b['daten']['zeit'] ? $b['daten']['zeit'] : '00:00' );
			return strcmp( $ka, $kb );
		}
	);

	return $anzahl > 0 ? array_slice( $termine, 0, $anzahl ) : $termine;
}

/** Ordnet Termine den einzelnen Tagen zu (mehrtägige an jedem Tag). */
function ek_termine_pro_tag( $termine, $von, $bis ) {
	$pro_tag = array();
	foreach ( $termine as $eintrag ) {
		$d   = $eintrag['daten'];
		$tag = max( $d['start'], $von );
		$end = min( $d['ende'], $bis );
		for ( $i = 0; $tag <= $end && $i < 400; $i++ ) {
			$pro_tag[ $tag ][] = $eintrag;
			$tag               = ek_plus_tage( $tag, 1 );
		}
	}
	return $pro_tag;
}

/** Nächste Vorkommen ab heute (für die Terminseite). */
function ek_naechste_vorkommen( $post_id, $anzahl = 6 ) {
	$daten = ek_termin_daten( $post_id );
	$heute = current_time( 'Y-m-d' );
	$bis   = $daten['regel'] ? $daten['regel']['until'] : $daten['ende'];
	if ( $bis < $heute ) {
		return array();
	}
	return array_slice( ek_vorkommen( $daten, $heute, $bis ), 0, $anzahl );
}

/** Adresse eines Termins (mit ?datum= bei Serien). */
function ek_termin_url( $eintrag ) {
	$url = get_permalink( $eintrag['post'] );
	return ! empty( $eintrag['serie'] ) ? add_query_arg( 'datum', $eintrag['daten']['start'], $url ) : $url;
}

/* -------------------------------------------------------------------------
 * Texte
 * ---------------------------------------------------------------------- */

/** Uhrzeit: „18:00 Uhr“, „18:00–21:00 Uhr“ oder ''. */
function ek_zeit_text( $daten ) {
	if ( ! $daten['zeit'] ) {
		return '';
	}
	return $daten['zeit'] . ( $daten['zeit_ende'] ? '–' . $daten['zeit_ende'] : '' ) . ' Uhr';
}

/** Lesbare Zusammenfassung: "9. November 2026, 18:00–21:00 Uhr" bzw. Zeitraum. */
function ek_termin_zeitraum( $daten ) {
	$text = ek_datum_ausgeben( $daten['start'] );
	if ( $daten['ende'] && $daten['ende'] !== $daten['start'] ) {
		$text .= ' – ' . ek_datum_ausgeben( $daten['ende'] );
	}
	$zeit = ek_zeit_text( $daten );
	return $zeit ? $text . ', ' . $zeit : $text;
}

/** Kategorie-Etiketten als HTML. */
function ek_kategorie_badges( $daten ) {
	$html = '';
	foreach ( $daten['kategorien'] as $term ) {
		$html .= '<span class="ek-badge"' . ek_farb_style( ek_kategorie_farbe( $term->term_id ) ) . '>' . esc_html( $term->name ) . '</span>';
	}
	return $html;
}
