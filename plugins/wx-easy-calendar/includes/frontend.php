<?php
/**
 * Frontend: Kalender-Shortcode (Liste, Woche, Monat, Jahr), Kategorie-Filter mit
 * Mehrfachauswahl, Suche, Vorschau/Detailfenster, Terminseite, iCal und Google-Daten.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Dateien
 * ---------------------------------------------------------------------- */

/** Ist WX Lightbox aktiv? Dann öffnet sie die Bilder, sonst die eigene kleine Lightbox. */
function ek_lightbox_extern() {
	return defined( 'SBL_VERSION' ) && apply_filters( 'sbl_enabled', true );
}

function ek_styles_registrieren() {
	wp_register_style( 'ek-kalender', EK_URL . 'assets/kalender.css', array(), EK_VERSION );
	wp_register_script( 'ek-kalender', EK_URL . 'assets/kalender.js', array(), EK_VERSION, true );
	wp_localize_script(
		'ek-kalender',
		'ekKalender',
		array(
			'lightbox' => ! ek_lightbox_extern(),
			'texte'    => array(
				'schliessen'  => 'Schließen',
				'details'     => 'Details ansehen',
				'eintragen'   => 'In Kalender eintragen',
				'vergroessern'=> 'Bild vergrößern',
				'zurueck'     => 'Vorheriges Bild',
				'weiter'      => 'Nächstes Bild',
			),
		)
	);

	list( $css_wert, $hex ) = ek_akzentfarbe();
	wp_add_inline_style(
		'ek-kalender',
		'.ek-kalender,.ek-liste,.ek-details,.ek-dialog,.ek-vorschau{--ek-akzent:' . $css_wert . ';--ek-akzent-text:' . ek_text_auf( $hex ) . ';}'
	);
}
add_action( 'wp_enqueue_scripts', 'ek_styles_registrieren' );

function ek_dateien_laden() {
	wp_enqueue_style( 'ek-kalender' );
	wp_enqueue_script( 'ek-kalender' );
}

/** Seite mit dem Kalender (für „Zum Kalender“), zwischengespeichert. */
function ek_kalenderseite_url() {
	$id = get_transient( 'ek_kalenderseite' );
	if ( false === $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$id = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('page','post') AND post_content LIKE '%[einfacher\\_kalender%' ORDER BY post_type = 'page' DESC, ID ASC LIMIT 1" );
		set_transient( 'ek_kalenderseite', $id, 12 * HOUR_IN_SECONDS );
	}
	return $id ? get_permalink( (int) $id ) : '';
}
add_action(
	'save_post',
	function () {
		delete_transient( 'ek_kalenderseite' );
	}
);

/* -------------------------------------------------------------------------
 * Vorschau-Daten (für Darüberfahren und Detailfenster)
 * ---------------------------------------------------------------------- */

/** Gleichbleibende Angaben eines Termins (einmal pro Seitenaufruf). */
function ek_vorschau_basis( $post ) {
	static $speicher = array();
	if ( isset( $speicher[ $post->ID ] ) ) {
		return $speicher[ $post->ID ];
	}
	$d    = ek_termin_daten( $post->ID );
	$bild = get_post_thumbnail_id( $post->ID );
	$kat  = array();
	foreach ( $d['kategorien'] as $term ) {
		$farbe = ek_kategorie_farbe( $term->term_id );
		$kat[] = array( $term->name, $farbe ? $farbe : '' );
	}
	$ort = $d['ort_daten'];
	$org = $d['veranstalter'];
	$txt = wp_strip_all_tags( strip_shortcodes( $post->post_excerpt ? $post->post_excerpt : $post->post_content ) );

	$speicher[ $post->ID ] = array(
		't' => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
		'o' => $ort ? trim( $ort['name'] . ( $ort['adresse'] ? ', ' . $ort['adresse'] : '' ) ) : '',
		'v' => $org ? $org['name'] : '',
		'x' => wp_trim_words( html_entity_decode( $txt, ENT_QUOTES, 'UTF-8' ), 30, ' …' ),
		'b' => $bild ? (string) wp_get_attachment_image_url( $bild, 'medium_large' ) : '',
		'B' => $bild ? (string) wp_get_attachment_image_url( $bild, 'full' ) : '',
		'k' => $kat,
	);
	return $speicher[ $post->ID ];
}

/** data-ek-Attribut eines Termin-Vorkommens. */
function ek_vorschau_attr( $eintrag ) {
	$v      = ek_vorschau_basis( $eintrag['post'] );
	$d      = $eintrag['daten'];
	$v['u'] = ek_termin_url( $eintrag );
	$v['w'] = ek_wann_text( $d );
	$v['i'] = ek_ics_url( $eintrag['post']->ID, ! empty( $eintrag['serie'] ) ? $d['start'] : '' );
	$v['s'] = ! empty( $eintrag['serie'] ) ? 'Regelmäßiger Termin' : '';
	return ' data-ek="' . esc_attr( wp_json_encode( $v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) . '"';
}

/** „Samstag, 7. November 2026 · 20:00–23:00 Uhr“ */
function ek_wann_text( $d ) {
	$text = ek_datum_ausgeben( $d['start'], 'l, j. F Y' );
	if ( $d['ende'] !== $d['start'] ) {
		$text .= ' – ' . ek_datum_ausgeben( $d['ende'], 'l, j. F Y' );
	}
	$zeit = ek_zeit_text( $d );
	return $text . ' · ' . ( $zeit ? $zeit : 'ganztägig' );
}

/* -------------------------------------------------------------------------
 * Bausteine
 * ---------------------------------------------------------------------- */

/** Baut eine Kalender-URL mit geänderten Parametern. */
function ek_url( $args ) {
	return add_query_arg( $args ) . '#ek-kalender';
}

/**
 * Baut das Tagesraster eines Monats (Wochen ab Montag).
 * $zelle( DateTimeImmutable $datum ) liefert array( klassen[], inner_html ).
 */
function ek_monats_raster( DateTimeImmutable $erster, callable $zelle ) {
	$versatz  = (int) $erster->format( 'N' ) - 1;
	$anz_tage = (int) $erster->format( 't' );
	$html     = '<tr>';
	$nr       = 0;

	for ( $i = 0; $i < $versatz; $i++, $nr++ ) {
		$html .= '<td class="ek-leer"></td>';
	}
	for ( $t = 1; $t <= $anz_tage; $t++, $nr++ ) {
		if ( $nr > 0 && 0 === $nr % 7 ) {
			$html .= '</tr><tr>';
		}
		list( $klassen, $inhalt ) = $zelle( $erster->setDate( (int) $erster->format( 'Y' ), (int) $erster->format( 'n' ), $t ) );
		$html                    .= '<td class="' . esc_attr( implode( ' ', $klassen ) ) . '">' . $inhalt . '</td>';
	}
	while ( 0 !== $nr % 7 ) {
		$html .= '<td class="ek-leer"></td>';
		$nr++;
	}
	return $html . '</tr>';
}

function ek_wochentage() {
	return array( 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So' );
}

/** Vorschaubild eines Termins als <img> oder ''. */
function ek_bild_html( $post, $klasse ) {
	$v = ek_vorschau_basis( $post );
	return $v['b'] ? '<span class="' . esc_attr( $klasse ) . '"><img src="' . esc_url( $v['b'] ) . '" alt="" loading="lazy"></span>' : '';
}

/* -------------------------------------------------------------------------
 * Ansichten
 * ---------------------------------------------------------------------- */

/** Ansicht "Monat". */
function ek_ansicht_monat( DateTimeImmutable $erster, $pro_tag, $heute_ymd ) {
	$wochentage = ek_wochentage();
	$html       = '<table class="ek-tabelle"><thead><tr>';
	foreach ( $wochentage as $wt ) {
		$html .= '<th scope="col">' . esc_html( $wt ) . '</th>';
	}
	$html .= '</tr></thead><tbody>';

	$html .= ek_monats_raster(
		$erster,
		function ( $datum ) use ( $pro_tag, $heute_ymd, $wochentage ) {
			$ymd     = $datum->format( 'Y-m-d' );
			$termine = isset( $pro_tag[ $ymd ] ) ? $pro_tag[ $ymd ] : array();
			$klassen = array( 'ek-tag', $termine ? 'ek-hat-termine' : 'ek-ohne-termine' );
			if ( $ymd === $heute_ymd ) {
				$klassen[] = 'ek-heute';
			}
			if ( (int) $datum->format( 'N' ) >= 6 ) {
				$klassen[] = 'ek-wochenende';
			}

			$inhalt  = '<span class="ek-nr">' . esc_html( $datum->format( 'j' ) ) . '</span>';
			$inhalt .= '<span class="ek-datum-mobil">' . esc_html( $wochentage[ (int) $datum->format( 'N' ) - 1 ] . ', ' . $datum->format( 'j.n.' ) ) . '</span>';

			foreach ( $termine as $eintrag ) {
				$d       = $eintrag['daten'];
				$info    = ek_termin_zeitraum( $d ) . ( $d['ort'] ? ' · ' . $d['ort'] : '' );
				$inhalt .= '<a class="ek-termin" href="' . esc_url( ek_termin_url( $eintrag ) ) . '" title="' . esc_attr( $info ) . '"' . ek_farb_style( ek_termin_farbe( $d ) ) . ek_vorschau_attr( $eintrag ) . '>';
				if ( $d['zeit'] && $d['start'] === $ymd ) {
					$inhalt .= '<span class="ek-zeit">' . esc_html( $d['zeit'] ) . '</span> ';
				}
				$inhalt .= esc_html( get_the_title( $eintrag['post'] ) ) . '</a>';
			}
			return array( $klassen, $inhalt );
		}
	);

	return $html . '</tbody></table>';
}

/** Ansicht "Woche": sieben Spalten mit Terminkarten. */
function ek_ansicht_woche( $montag, $pro_tag, $heute_ymd ) {
	$html = '<div class="ek-woche">';
	for ( $i = 0; $i < 7; $i++ ) {
		$ymd     = ek_plus_tage( $montag, $i );
		$termine = isset( $pro_tag[ $ymd ] ) ? $pro_tag[ $ymd ] : array();
		$klassen = array( 'ek-wochentag', $termine ? 'ek-hat-termine' : 'ek-ohne-termine' );
		if ( $ymd === $heute_ymd ) {
			$klassen[] = 'ek-heute';
		}
		if ( $i >= 5 ) {
			$klassen[] = 'ek-wochenende';
		}
		if ( $ymd < $heute_ymd ) {
			$klassen[] = 'ek-vergangen';
		}

		$html .= '<div class="' . esc_attr( implode( ' ', $klassen ) ) . '">';
		$html .= '<div class="ek-wochentag-kopf"><span class="ek-wt">' . esc_html( ek_datum_ausgeben( $ymd, 'D' ) ) . '</span> <span class="ek-wt-nr">' . esc_html( ek_datum_ausgeben( $ymd, 'j.' ) ) . '</span>'
			. '<span class="ek-wt-lang">' . esc_html( ek_datum_ausgeben( $ymd, 'l, j. F' ) ) . '</span></div>';
		$html .= '<div class="ek-wochentag-termine">';
		foreach ( $termine as $eintrag ) {
			$d     = $eintrag['daten'];
			$zeit  = ( $d['zeit'] && $d['start'] === $ymd ) ? ek_zeit_text( $d ) : ( $d['zeit'] ? 'fortlaufend' : 'ganztägig' );
			$html .= '<a class="ek-karte" href="' . esc_url( ek_termin_url( $eintrag ) ) . '"' . ek_farb_style( ek_termin_farbe( $d ) ) . ek_vorschau_attr( $eintrag ) . '>'
				. ek_bild_html( $eintrag['post'], 'ek-karte-bild' )
				. '<span class="ek-karte-zeit">' . esc_html( $zeit ) . '</span>'
				. '<span class="ek-karte-titel">' . esc_html( get_the_title( $eintrag['post'] ) ) . '</span>'
				. ( $d['ort'] ? '<span class="ek-karte-ort">' . esc_html( $d['ort'] ) . '</span>' : '' )
				. '</a>';
		}
		if ( ! $termine ) {
			$html .= '<span class="ek-keine-kurz">Keine Termine</span>';
		}
		$html .= '</div></div>';
	}
	return $html . '</div>';
}

/** Ansicht "Jahr": zwölf Mini-Monate, Tage mit Terminen sind markiert. */
function ek_ansicht_jahr( $jahr, $pro_tag, $heute_ymd ) {
	$tz   = wp_timezone();
	$html = '<div class="ek-jahr">';

	for ( $m = 1; $m <= 12; $m++ ) {
		$erster = new DateTimeImmutable( sprintf( '%04d-%02d-01', $jahr, $m ), $tz );
		$url    = ek_url(
			array(
				'ek_ansicht' => 'monat',
				'ek_monat'   => $erster->format( 'Y-m' ),
				'ek_woche'   => false,
			)
		);

		$html .= '<div class="ek-minimonat">';
		$html .= '<a class="ek-minititel" href="' . esc_url( $url ) . '">' . esc_html( wp_date( 'F', $erster->getTimestamp() ) ) . '</a>';
		$html .= '<table class="ek-minitabelle"><thead><tr>';
		foreach ( ek_wochentage() as $wt ) {
			$html .= '<th scope="col">' . esc_html( mb_substr( $wt, 0, 1 ) ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';

		$html .= ek_monats_raster(
			$erster,
			function ( $datum ) use ( $pro_tag, $heute_ymd, $url ) {
				$ymd     = $datum->format( 'Y-m-d' );
				$klassen = array( 'ek-minitag' );
				if ( $ymd === $heute_ymd ) {
					$klassen[] = 'ek-heute';
				}
				if ( empty( $pro_tag[ $ymd ] ) ) {
					return array( $klassen, esc_html( $datum->format( 'j' ) ) );
				}

				$klassen[] = 'ek-hat-termine';
				$titel     = array();
				$farbe     = '';
				foreach ( $pro_tag[ $ymd ] as $eintrag ) {
					$titel[] = get_the_title( $eintrag['post'] );
					if ( ! $farbe ) {
						$farbe = ek_termin_farbe( $eintrag['daten'] );
					}
				}
				$inhalt = '<a href="' . esc_url( $url ) . '" title="' . esc_attr( implode( "\n", $titel ) ) . '"' . ek_farb_style( $farbe ) . '>' . esc_html( $datum->format( 'j' ) ) . '</a>';
				return array( $klassen, $inhalt );
			}
		);

		$html .= '</tbody></table></div>';
	}

	return $html . '</div>';
}

/** Gibt Termine als Liste mit dem Stil der Kalenderansichten aus. */
function ek_ansicht_liste( $termine, $bilder = true ) {
	if ( ! $termine ) {
		return '';
	}
	$html = '<ul class="ek-liste ek-liste-ansicht">';
	foreach ( $termine as $eintrag ) {
		$html .= ek_listeneintrag( $eintrag, $bilder );
	}
	return $html . '</ul>';
}

/** Gruppiert die Termine des Jahres nach ihrem ersten Tag im Jahr. */
function ek_jahresliste( $termine, $jahresbeginn ) {
	$monate = array();
	foreach ( $termine as $eintrag ) {
		$monat              = substr( max( $eintrag['daten']['start'], $jahresbeginn ), 0, 7 );
		$monate[ $monat ][] = $eintrag;
	}

	$html = '';
	foreach ( $monate as $monat => $eintraege ) {
		$html .= '<section class="ek-listenmonat">';
		$html .= '<h3 class="ek-listenueberschrift">' . esc_html( ek_datum_ausgeben( $monat . '-01', 'F' ) ) . '</h3>';
		$html .= ek_ansicht_liste( $eintraege ) . '</section>';
	}
	return $html;
}

/** Ein Eintrag für Liste und [termine_liste]. */
function ek_listeneintrag( $eintrag, $bild = false ) {
	$d     = $eintrag['daten'];
	$bhtml = $bild ? ek_bild_html( $eintrag['post'], 'ek-listenbild' ) : '';
	$html  = '<li class="ek-listeneintrag' . ( $bhtml ? ' ek-mit-bild' : '' ) . '"' . ek_farb_style( ek_termin_farbe( $d ) ) . ek_vorschau_attr( $eintrag ) . '>';
	$html .= '<span class="ek-listentext">';
	$html .= '<span class="ek-listendatum">' . esc_html( ek_termin_zeitraum( $d ) ) . '</span>';
	$html .= '<a class="ek-listentitel" href="' . esc_url( ek_termin_url( $eintrag ) ) . '">' . esc_html( get_the_title( $eintrag['post'] ) ) . '</a>';
	if ( $d['ort'] || $d['kategorien'] ) {
		$html .= '<span class="ek-listenmeta">';
		if ( $d['ort'] ) {
			$html .= '<span class="ek-listenort">' . esc_html( $d['ort'] ) . '</span>';
		}
		$html .= ek_kategorie_badges( $d ) . '</span>';
	}
	$html .= '</span>' . $bhtml;
	return $html . '</li>';
}

/** Kategorie-Filter als Knöpfe, mehrere gleichzeitig wählbar. */
function ek_kategorie_filter( $aktiv ) {
	$terms = get_terms(
		array(
			'taxonomy'   => 'ek_kategorie',
			'hide_empty' => true,
		)
	);
	if ( ! $terms || is_wp_error( $terms ) || count( $terms ) < 2 ) {
		return '';
	}

	$html  = '<div class="ek-chips" role="group" aria-label="Nach Kategorien filtern (mehrere möglich)">';
	$html .= '<a class="ek-chip ek-chip-alle' . ( $aktiv ? '' : ' ek-aktiv' ) . '" href="' . esc_url( ek_url( array( 'ek_kat' => false ) ) ) . '"' . ( $aktiv ? '' : ' aria-current="true"' ) . '>Alle</a>';
	foreach ( $terms as $term ) {
		$an    = in_array( $term->slug, $aktiv, true );
		$neu   = $an ? array_values( array_diff( $aktiv, array( $term->slug ) ) ) : array_merge( $aktiv, array( $term->slug ) );
		$html .= '<a class="ek-chip' . ( $an ? ' ek-aktiv' : '' ) . '" href="' . esc_url( ek_url( array( 'ek_kat' => $neu ? implode( ',', $neu ) : false ) ) ) . '"'
			. ek_farb_style( ek_kategorie_farbe( $term->term_id ) ) . ( $an ? ' aria-current="true"' : '' ) . '>'
			. '<span class="ek-chip-punkt" aria-hidden="true"></span>' . esc_html( $term->name )
			. ( $an ? '<span class="ek-chip-haken" aria-hidden="true">✓</span>' : '' ) . '</a>';
	}
	return $html . '</div>';
}

/** Suchfeld (GET-Formular, behält die übrigen Parameter). */
function ek_suchfeld( $suche ) {
	$html = '<form class="ek-suche" method="get" action="#ek-kalender" role="search">';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reine Anzeige-Parameter
	foreach ( $_GET as $key => $wert ) {
		if ( in_array( $key, array( 'ek_suche', 'ek_woche', 'ek_monat' ), true ) || ! is_scalar( $wert ) ) {
			continue;
		}
		$html .= '<input type="hidden" name="' . esc_attr( sanitize_key( $key ) ) . '" value="' . esc_attr( sanitize_text_field( wp_unslash( $wert ) ) ) . '">';
	}
	$html .= '<label class="screen-reader-text" for="ek-suchfeld">Termin suchen</label>';
	$html .= '<input type="search" id="ek-suchfeld" name="ek_suche" value="' . esc_attr( $suche ) . '" placeholder="Termin suchen">';
	$html .= '<button type="submit">Suchen</button></form>';
	return $html;
}

/** Legende mit den Kategorie-Farben. */
function ek_legende( $nur_slugs ) {
	$args = array(
		'taxonomy'   => 'ek_kategorie',
		'hide_empty' => true,
	);
	if ( $nur_slugs ) {
		$args['slug'] = $nur_slugs;
	}
	$terms = get_terms( $args );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return '';
	}
	$html = '<div class="ek-legende">';
	foreach ( $terms as $term ) {
		$html .= '<span class="ek-legende-eintrag"' . ek_farb_style( ek_kategorie_farbe( $term->term_id ) ) . '><span class="ek-legende-punkt"></span>' . esc_html( $term->name ) . '</span>';
	}
	return $html . '</div>';
}

/** „23.–29. November 2026“ */
function ek_wochen_titel( $montag ) {
	$sonntag = ek_plus_tage( $montag, 6 );
	if ( substr( $montag, 0, 7 ) === substr( $sonntag, 0, 7 ) ) {
		return ek_datum_ausgeben( $montag, 'j.' ) . '–' . ek_datum_ausgeben( $sonntag, 'j. F Y' );
	}
	if ( substr( $montag, 0, 4 ) === substr( $sonntag, 0, 4 ) ) {
		return ek_datum_ausgeben( $montag, 'j. F' ) . ' – ' . ek_datum_ausgeben( $sonntag, 'j. F Y' );
	}
	return ek_datum_ausgeben( $montag, 'j. F Y' ) . ' – ' . ek_datum_ausgeben( $sonntag, 'j. F Y' );
}

/** Montag der Woche, in der $ymd liegt. */
function ek_montag( $ymd ) {
	return ek_plus_tage( $ymd, -( ( (int) gmdate( 'N', ek_ts( $ymd ) ) ) - 1 ) );
}

/* -------------------------------------------------------------------------
 * Shortcode [einfacher_kalender]
 * ---------------------------------------------------------------------- */

/**
 * Attribute:
 *   ansicht   = monat | woche | liste | jahr   Startansicht (Standard: monat)
 *   auswahl   = ja | nein                      Umschalter für die Ansichten zeigen (Standard: ja)
 *   kategorie = slug[,slug]                    nur diese Kategorien zeigen (blendet den Filter aus)
 *   filter    = ja | nein                      Kategorie-Filter zeigen (Standard: ja)
 *   suche     = ja | nein                      Suchfeld zeigen (Standard: ja)
 *
 * URL-Parameter: ek_ansicht, ek_monat (JJJJ-MM), ek_woche (JJJJ-MM-TT), ek_kat (Slugs, mit Komma), ek_suche.
 */
function ek_shortcode_kalender( $atts ) {
	ek_dateien_laden();

	$atts = shortcode_atts(
		array(
			'ansicht'   => 'monat',
			'auswahl'   => 'ja',
			'kategorie' => '',
			'filter'    => 'ja',
			'suche'     => 'ja',
		),
		$atts,
		'einfacher_kalender'
	);

	$ansichten = array(
		'liste' => 'Liste',
		'woche' => 'Woche',
		'monat' => 'Monat',
		'jahr'  => 'Jahr',
	);
	$auswahl   = 'nein' !== strtolower( $atts['auswahl'] );

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- reine Anzeige-Parameter
	$ansicht = strtolower( $atts['ansicht'] );
	if ( $auswahl && isset( $_GET['ek_ansicht'] ) ) {
		$ansicht = sanitize_key( wp_unslash( $_GET['ek_ansicht'] ) );
	}
	if ( ! isset( $ansichten[ $ansicht ] ) ) {
		$ansicht = 'monat';
	}

	$feste_kategorien = ek_kategorien_parsen( $atts['kategorie'] );
	$filter_an        = ! $feste_kategorien && 'nein' !== strtolower( $atts['filter'] );
	$kat_auswahl      = array();
	if ( $filter_an && isset( $_GET['ek_kat'] ) ) {
		$kat_auswahl = ek_kategorien_parsen( sanitize_text_field( wp_unslash( $_GET['ek_kat'] ) ) );
	}
	$kategorien = $feste_kategorien ? $feste_kategorien : $kat_auswahl;
	$suche_an   = 'nein' !== strtolower( $atts['suche'] );
	$suche      = ( $suche_an && isset( $_GET['ek_suche'] ) ) ? mb_substr( sanitize_text_field( wp_unslash( $_GET['ek_suche'] ) ), 0, 80 ) : '';

	$tz        = wp_timezone();
	$heute     = new DateTimeImmutable( 'now', $tz );
	$heute_ymd = $heute->format( 'Y-m-d' );
	$param     = isset( $_GET['ek_monat'] ) ? sanitize_text_field( wp_unslash( $_GET['ek_monat'] ) ) : '';
	$erster    = DateTimeImmutable::createFromFormat( '!Y-m', $param, $tz );
	$woche_p   = isset( $_GET['ek_woche'] ) ? ek_datum_pruefen( sanitize_text_field( wp_unslash( $_GET['ek_woche'] ) ) ) : '';
	// phpcs:enable

	if ( ! $erster || $erster->format( 'Y-m' ) !== $param ) {
		$erster = null;
	}
	// Monat und Woche gegenseitig ableiten, damit beim Umschalten der Zeitraum passt.
	if ( $woche_p ) {
		$montag = ek_montag( $woche_p );
		if ( ! $erster ) {
			$erster = new DateTimeImmutable( substr( ek_plus_tage( $montag, 3 ), 0, 7 ) . '-01', $tz );
		}
	} elseif ( $erster && $erster->format( 'Y-m' ) !== $heute->format( 'Y-m' ) ) {
		$montag = ek_montag( $erster->format( 'Y-m-d' ) );
	} else {
		$montag = ek_montag( $heute_ymd );
	}
	if ( ! $erster ) {
		$erster = $heute->modify( 'first day of this month' )->setTime( 0, 0 );
	}

	// Zeitraum und Blättern je Ansicht.
	if ( 'monat' === $ansicht ) {
		$von         = $erster->format( 'Y-m-d' );
		$bis         = $erster->modify( 'last day of this month' )->format( 'Y-m-d' );
		$titel       = wp_date( 'F Y', $erster->getTimestamp() );
		$url_zurueck = ek_url( array( 'ek_monat' => $erster->modify( '-1 month' )->format( 'Y-m' ), 'ek_woche' => false ) );
		$url_vor     = ek_url( array( 'ek_monat' => $erster->modify( '+1 month' )->format( 'Y-m' ), 'ek_woche' => false ) );
		$label       = array( 'Vorheriger Monat', 'Nächster Monat' );
	} elseif ( 'woche' === $ansicht ) {
		$von         = $montag;
		$bis         = ek_plus_tage( $montag, 6 );
		$titel       = ek_wochen_titel( $montag );
		$url_zurueck = ek_url( array( 'ek_woche' => ek_plus_tage( $montag, -7 ), 'ek_monat' => false ) );
		$url_vor     = ek_url( array( 'ek_woche' => ek_plus_tage( $montag, 7 ), 'ek_monat' => false ) );
		$label       = array( 'Vorherige Woche', 'Nächste Woche' );
	} else {
		$titel       = $erster->format( 'Y' );
		$von         = $titel . '-01-01';
		$bis         = $titel . '-12-31';
		$url_zurueck = ek_url( array( 'ek_monat' => $erster->modify( '-12 months' )->format( 'Y-m' ), 'ek_woche' => false ) );
		$url_vor     = ek_url( array( 'ek_monat' => $erster->modify( '+12 months' )->format( 'Y-m' ), 'ek_woche' => false ) );
		$label       = array( 'Vorheriges Jahr', 'Nächstes Jahr' );
	}
	$url_heute = ek_url( array( 'ek_monat' => false, 'ek_woche' => false ) );

	$termine          = ek_termine_holen( $von, $bis, -1, $kategorien, $suche );
	$naechste_termine = 'monat' === $ansicht ? ek_termine_holen( $heute_ymd, '', 5, $kategorien, $suche ) : array();
	$pro_tag          = 'liste' !== $ansicht ? ek_termine_pro_tag( $termine, $von, $bis ) : array();
	$gefiltert        = ( $kat_auswahl || '' !== $suche );

	ob_start();
	?>
	<div class="ek-kalender ek-ansicht-<?php echo esc_attr( $ansicht ); ?>" id="ek-kalender">
		<div class="ek-kopf">
			<div class="ek-blaettern">
				<a class="ek-nav" href="<?php echo esc_url( $url_zurueck ); ?>" aria-label="<?php echo esc_attr( $label[0] ); ?>">&#8249;</a>
				<h2 class="ek-titel"><?php echo esc_html( $titel ); ?></h2>
				<a class="ek-nav" href="<?php echo esc_url( $url_vor ); ?>" aria-label="<?php echo esc_attr( $label[1] ); ?>">&#8250;</a>
				<?php if ( 'liste' !== $ansicht ) : ?>
					<a class="ek-heute-link" href="<?php echo esc_url( $url_heute ); ?>">Heute</a>
				<?php endif; ?>
			</div>
			<div class="ek-werkzeuge">
				<?php
				if ( $suche_an ) {
					echo ek_suchfeld( $suche ); // phpcs:ignore WordPress.Security.EscapeOutput -- intern escaped
				}
				if ( $auswahl ) :
					?>
					<nav class="ek-ansichten" aria-label="Ansicht wählen">
						<?php foreach ( $ansichten as $key => $text ) : ?>
							<a href="<?php echo esc_url( ek_url( array( 'ek_ansicht' => $key ) ) ); ?>" class="<?php echo $key === $ansicht ? 'ek-aktiv' : ''; ?>" <?php echo $key === $ansicht ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $text ); ?></a>
						<?php endforeach; ?>
					</nav>
				<?php endif; ?>
			</div>
		</div>

		<?php
		if ( $filter_an ) {
			echo ek_kategorie_filter( $kat_auswahl ); // phpcs:ignore WordPress.Security.EscapeOutput -- intern escaped
		}
		?>

		<?php if ( 'monat' === $ansicht && ! $gefiltert ) : ?>
			<section class="ek-naechste-termine">
				<h3 class="ek-listenueberschrift">Nächste Termine</h3>
				<?php if ( $naechste_termine ) : ?>
					<?php echo ek_ansicht_liste( $naechste_termine ); // phpcs:ignore WordPress.Security.EscapeOutput -- intern escaped ?>
				<?php else : ?>
					<p class="ek-keine">Derzeit sind keine Termine geplant.</p>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php if ( ! $termine ) : ?>
			<p class="ek-keine">
				<?php
				if ( $gefiltert ) {
					echo 'Keine Termine passen zu deiner Auswahl. <a href="' . esc_url( ek_url( array( 'ek_kat' => false, 'ek_suche' => false ) ) ) . '">Filter zurücksetzen</a>';
				} else {
					$texte = array(
						'monat' => 'Keine Termine in diesem Monat.',
						'woche' => 'Keine Termine in dieser Woche.',
					);
					echo esc_html( isset( $texte[ $ansicht ] ) ? $texte[ $ansicht ] : 'Keine Termine in diesem Jahr.' );
				}
				?>
			</p>
		<?php endif; ?>

		<?php
		// phpcs:disable WordPress.Security.EscapeOutput -- die Ansichten escapen intern
		if ( 'jahr' === $ansicht ) {
			echo ek_ansicht_jahr( (int) $erster->format( 'Y' ), $pro_tag, $heute_ymd );
		} elseif ( 'liste' === $ansicht ) {
			echo ek_jahresliste( $termine, $von );
		} elseif ( 'woche' === $ansicht ) {
			echo ek_ansicht_woche( $montag, $pro_tag, $heute_ymd );
		} else {
			echo ek_ansicht_monat( $erster, $pro_tag, $heute_ymd );
		}
		echo '<div class="ek-fuss">';
		if ( ! $filter_an ) {
			echo ek_legende( $feste_kategorien );
		}
		echo '<a class="ek-abo" href="' . esc_url( ek_ics_url( 'alle' ) ) . '">Kalender abonnieren (iCal)</a></div>';
		// phpcs:enable
		?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'einfacher_kalender', 'ek_shortcode_kalender' );

/**
 * Shortcode [termine_liste anzahl="5" kategorie="slug" bilder="nein"]
 * Zeigt die nächsten anstehenden Termine als Liste.
 */
function ek_shortcode_liste( $atts ) {
	ek_dateien_laden();
	$atts = shortcode_atts(
		array(
			'anzahl'    => 5,
			'kategorie' => '',
			'bilder'    => 'nein',
		),
		$atts,
		'termine_liste'
	);

	$termine = ek_termine_holen( current_time( 'Y-m-d' ), '', max( 1, (int) $atts['anzahl'] ), ek_kategorien_parsen( $atts['kategorie'] ) );
	if ( ! $termine ) {
		return '<p class="ek-keine-liste">Derzeit sind keine Termine geplant.</p>';
	}

	$html = '<ul class="ek-liste">';
	foreach ( $termine as $eintrag ) {
		$html .= ek_listeneintrag( $eintrag, 'ja' === strtolower( $atts['bilder'] ) );
	}
	return $html . '</ul>';
}
add_shortcode( 'termine_liste', 'ek_shortcode_liste' );

/* -------------------------------------------------------------------------
 * Terminseite
 * ---------------------------------------------------------------------- */

/** Welches Vorkommen auf der Terminseite gezeigt wird (bei Serien über ?datum=). */
function ek_gezeigtes_vorkommen( $post_id ) {
	$d = ek_termin_daten( $post_id );
	if ( ! $d['start'] ) {
		return null;
	}
	if ( $d['regel'] ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$wunsch = isset( $_GET['datum'] ) ? ek_datum_pruefen( sanitize_text_field( wp_unslash( $_GET['datum'] ) ) ) : '';
		if ( $wunsch ) {
			foreach ( ek_vorkommen( $d, $wunsch, $wunsch ) as $v ) {
				if ( $v['start'] === $wunsch ) {
					$d['start'] = $v['start'];
					$d['ende']  = $v['ende'];
					return $d;
				}
			}
		}
		$naechste = ek_naechste_vorkommen( $post_id, 1 );
		if ( $naechste ) {
			$d['start'] = $naechste[0]['start'];
			$d['ende']  = $naechste[0]['ende'];
		}
	}
	return $d;
}

/** Auf der Einzelseite eines Termins Datum, Ort, Veranstalter und mehr anzeigen. */
function ek_inhalt_ergaenzen( $inhalt ) {
	if ( ! is_singular( 'ek_termin' ) || ! in_the_loop() || ! is_main_query() ) {
		return $inhalt;
	}
	$id = get_the_ID();
	$d  = ek_gezeigtes_vorkommen( $id );
	if ( ! $d ) {
		return $inhalt;
	}
	ek_dateien_laden();
	$serie = (bool) $d['regel'];

	$box  = '<div class="ek-details"' . ek_farb_style( ek_termin_farbe( $d ) ) . '>';
	$box .= '<p><strong>Wann:</strong> ' . esc_html( ek_termin_zeitraum( $d ) );
	if ( $serie ) {
		$box .= '<br><span class="ek-leise">' . esc_html( ucfirst( ek_regel_text( ek_termin_daten( $id ) ) ) ) . '</span>';
	}
	$box .= '</p>';

	if ( $d['ort_daten'] ) {
		$o    = $d['ort_daten'];
		$box .= '<p><strong>Wo:</strong> ' . esc_html( $o['name'] );
		if ( $o['adresse'] ) {
			$box .= '<br>' . esc_html( $o['adresse'] )
				. '<br><a href="' . esc_url( 'https://www.openstreetmap.org/search?query=' . rawurlencode( $o['name'] . ', ' . $o['adresse'] ) ) . '" target="_blank" rel="noopener">Auf der Karte anzeigen</a>';
		}
		$box .= '</p>';
	}

	if ( $d['veranstalter'] ) {
		$v    = $d['veranstalter'];
		$box .= '<p><strong>Veranstalter:</strong> ' . esc_html( $v['name'] );
		if ( $v['telefon'] ) {
			$box .= '<br><a href="' . esc_url( 'tel:' . preg_replace( '/[^\d+]/', '', $v['telefon'] ) ) . '">' . esc_html( $v['telefon'] ) . '</a>';
		}
		if ( $v['email'] ) {
			$box .= '<br><a href="' . esc_url( 'mailto:' . antispambot( $v['email'] ) ) . '">' . esc_html( antispambot( $v['email'] ) ) . '</a>';
		}
		if ( $v['web'] ) {
			$box .= '<br><a href="' . esc_url( $v['web'] ) . '" target="_blank" rel="noopener">' . esc_html( preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( $v['web'] ) ) ) . '</a>';
		}
		$box .= '</p>';
	}

	if ( $d['kategorien'] ) {
		$box .= '<p><strong>Kategorie:</strong> ' . ek_kategorie_badges( $d ) . '</p>';
	}

	if ( $serie ) {
		$weitere = array_filter(
			ek_naechste_vorkommen( $id, 7 ),
			function ( $v ) use ( $d ) {
				return $v['start'] !== $d['start'];
			}
		);
		if ( $weitere ) {
			$box .= '<p class="ek-weitere"><strong>Weitere Termine:</strong> ';
			foreach ( array_slice( $weitere, 0, 6 ) as $v ) {
				$box .= '<a href="' . esc_url( add_query_arg( 'datum', $v['start'], get_permalink( $id ) ) ) . '">' . esc_html( ek_datum_ausgeben( $v['start'], 'D, j. M' ) ) . '</a> ';
			}
			$box .= '</p>';
		}
	}

	$kal  = ek_kalenderseite_url();
	$box .= '<p class="ek-aktionen"><a class="ek-knopf" href="' . esc_url( ek_ics_url( $id, $serie ? $d['start'] : '' ) ) . '" rel="nofollow">In Kalender eintragen</a>';
	if ( $kal ) {
		$box .= ' <a class="ek-knopf ek-knopf-leise" href="' . esc_url( add_query_arg( array( 'ek_ansicht' => 'monat', 'ek_monat' => substr( $d['start'], 0, 7 ) ), $kal ) . '#ek-kalender' ) . '">Zum Kalender</a>';
	}
	$box .= '</p></div>';

	return $box . ek_bilder_verlinken( $inhalt );
}
add_filter( 'the_content', 'ek_inhalt_ergaenzen', 20 );

/**
 * Verlinkt Bilder auf ihre Originalgröße, damit sie sich vergrößern lassen.
 * Vorhandene Links auf Bilddateien werden markiert, andere Links bleiben unverändert.
 */
function ek_bilder_verlinken( $html ) {
	if ( false === stripos( $html, '<img' ) ) {
		return $html;
	}
	$teile = preg_split( '#(<a\b[^>]*>.*?</a>)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
	if ( ! is_array( $teile ) ) {
		return $html;
	}
	foreach ( $teile as $i => $teil ) {
		if ( preg_match( '#^<a\b#i', $teil ) ) {
			if ( preg_match( '#<img\b#i', $teil )
				&& preg_match( '#^<a\b[^>]*\bhref=(["\'])[^"\']+\.(?:jpe?g|png|gif|webp|avif)(?:\?[^"\']*)?\1#i', $teil )
				&& false === strpos( $teil, 'ek-zoom' ) ) {
				$teil        = preg_replace( '#^<a\b#i', '<a data-lightbox="ek-termin"', $teil, 1 );
				$teile[ $i ] = preg_match( '#^<a\b[^>]*\bclass=(["\'])#i', $teil )
					? preg_replace( '#^(<a\b[^>]*\bclass=(["\']))#i', '$1ek-zoom ', $teil, 1 )
					: preg_replace( '#^<a\b#i', '<a class="ek-zoom"', $teil, 1 );
			}
			continue;
		}
		$teile[ $i ] = preg_replace_callback(
			'#<img\b[^>]*>#i',
			function ( $m ) {
				$tag = $m[0];
				if ( preg_match( '#class=(["\'])[^"\']*\b(wp-smiley|emoji|no-lightbox)\b#i', $tag ) ) {
					return $tag;
				}
				$gross = '';
				if ( preg_match( '#\bwp-image-(\d+)\b#', $tag, $id ) ) {
					$gross = (string) wp_get_attachment_image_url( (int) $id[1], 'full' );
				}
				if ( ! $gross && preg_match( '#\bsrc=(["\'])([^"\']+)\1#i', $tag, $src ) ) {
					$gross = html_entity_decode( $src[2], ENT_QUOTES, 'UTF-8' );
				}
				return $gross ? '<a href="' . esc_url( $gross ) . '" class="ek-zoom" data-lightbox="ek-termin">' . $tag . '</a>' : $tag;
			},
			$teil
		);
	}
	return implode( '', $teile );
}

/** Beitragsbild auf der Terminseite vergrößerbar machen. */
function ek_beitragsbild_verlinken( $html, $post_id, $bild_id ) {
	if ( ! $html || ! $bild_id || ! is_singular( 'ek_termin' ) || (int) $post_id !== (int) get_queried_object_id() || false !== strpos( $html, '<a ' ) ) {
		return $html;
	}
	$gross = wp_get_attachment_image_url( $bild_id, 'full' );
	if ( ! $gross ) {
		return $html;
	}
	ek_dateien_laden();
	return '<a href="' . esc_url( $gross ) . '" class="ek-zoom" data-lightbox="ek-termin">' . $html . '</a>';
}
add_filter( 'post_thumbnail_html', 'ek_beitragsbild_verlinken', 20, 3 );

/** Strukturierte Daten für Suchmaschinen (schema.org/Event). */
function ek_json_ld() {
	if ( ! is_singular( 'ek_termin' ) ) {
		return;
	}
	$id = get_queried_object_id();
	$d  = ek_gezeigtes_vorkommen( $id );
	if ( ! $d ) {
		return;
	}
	$tz    = wp_timezone();
	$start = $d['zeit'] ? ( new DateTimeImmutable( $d['start'] . ' ' . $d['zeit'], $tz ) )->format( 'c' ) : $d['start'];
	$ende  = $d['zeit'] ? ( new DateTimeImmutable( $d['ende'] . ' ' . ( $d['zeit_ende'] ? $d['zeit_ende'] : $d['zeit'] ), $tz ) )->format( 'c' ) : $d['ende'];
	$post  = get_post( $id );

	$ld = array(
		'@context'            => 'https://schema.org',
		'@type'               => 'Event',
		'name'                => html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ),
		'startDate'           => $start,
		'endDate'             => $ende,
		'eventStatus'         => 'https://schema.org/EventScheduled',
		'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
		'url'                 => get_permalink( $id ),
		'description'         => wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 60, ' …' ),
	);
	if ( $d['ort_daten'] ) {
		$o              = $d['ort_daten'];
		$ld['location'] = array(
			'@type'   => 'Place',
			'name'    => $o['name'],
			'address' => $o['frei']
				? $o['adresse']
				: array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => $o['strasse'],
					'postalCode'      => $o['plz'],
					'addressLocality' => $o['stadt'],
				),
		);
	}
	if ( $d['veranstalter'] ) {
		$ld['organizer'] = array_filter(
			array(
				'@type' => 'Organization',
				'name'  => $d['veranstalter']['name'],
				'url'   => $d['veranstalter']['web'],
			)
		);
	}
	$bild = get_the_post_thumbnail_url( $id, 'large' );
	if ( $bild ) {
		$ld['image'] = array( $bild );
	}
	echo '<script type="application/ld+json">' . wp_json_encode( $ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) . "</script>\n";
}
add_action( 'wp_head', 'ek_json_ld' );

/* -------------------------------------------------------------------------
 * iCal
 * ---------------------------------------------------------------------- */

/** Adresse der .ics-Datei eines Termins (oder 'alle' für das Abo). */
function ek_ics_url( $was, $datum = '' ) {
	$args = array( 'ek_ics' => $was );
	if ( $datum ) {
		$args['datum'] = $datum;
	}
	return add_query_arg( $args, home_url( '/' ) );
}

function ek_ics_text( $text ) {
	return str_replace( array( '\\', ';', ',', "\r\n", "\n" ), array( '\\\\', '\;', '\,', '\n', '\n' ), (string) $text );
}

/** Lange Zeilen nach 75 Bytes umbrechen (RFC 5545), ohne UTF-8-Zeichen zu trennen. */
function ek_ics_falten( $zeile ) {
	$aus = '';
	while ( strlen( $zeile ) > 75 ) {
		$schnitt = 75;
		while ( $schnitt > 0 && ( ord( $zeile[ $schnitt ] ) & 0xC0 ) === 0x80 ) {
			$schnitt--;
		}
		$aus  .= substr( $zeile, 0, $schnitt ) . "\r\n ";
		$zeile = substr( $zeile, $schnitt );
	}
	return $aus . $zeile;
}

function ek_ics_ausgeben() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET['ek_ics'] ) ) {
		return;
	}
	$was   = sanitize_key( wp_unslash( $_GET['ek_ics'] ) );
	$datum = isset( $_GET['datum'] ) ? ek_datum_pruefen( sanitize_text_field( wp_unslash( $_GET['datum'] ) ) ) : '';
	// phpcs:enable

	if ( 'alle' === $was ) {
		$termine = ek_termine_holen( ek_plus_tage( current_time( 'Y-m-d' ), -30 ), ek_plus_tage( current_time( 'Y-m-d' ), 365 ) );
		$name    = 'termine';
	} else {
		$post = get_post( absint( $was ) );
		if ( ! $post || 'ek_termin' !== $post->post_type || 'publish' !== $post->post_status ) {
			return;
		}
		$d       = ek_termin_daten( $post->ID );
		$termine = array();
		$von     = $datum ? $datum : $d['start'];
		$bis     = $datum ? $datum : ( $d['regel'] ? $d['regel']['until'] : $d['ende'] );
		foreach ( ek_vorkommen( $d, $von, $bis ) as $v ) {
			if ( $datum && $v['start'] !== $datum ) {
				continue;
			}
			$dv          = $d;
			$dv['start'] = $v['start'];
			$dv['ende']  = $v['ende'];
			$termine[]   = array( 'post' => $post, 'daten' => $dv, 'serie' => (bool) $d['regel'] );
		}
		$name = $post->post_name;
	}

	$tz     = wp_timezone();
	$utc    = new DateTimeZone( 'UTC' );
	$host   = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$stempel = gmdate( 'Ymd\THis\Z' );
	$zeilen = array( 'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//' . $host . '//WX Easy Calendar//DE', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'X-WR-CALNAME:' . ek_ics_text( get_bloginfo( 'name' ) ) );

	foreach ( $termine as $e ) {
		$d        = $e['daten'];
		$p        = $e['post'];
		$zeilen[] = 'BEGIN:VEVENT';
		$zeilen[] = 'UID:' . $p->ID . '-' . $d['start'] . '@' . $host;
		$zeilen[] = 'DTSTAMP:' . $stempel;
		if ( ! $d['zeit'] ) {
			$zeilen[] = 'DTSTART;VALUE=DATE:' . str_replace( '-', '', $d['start'] );
			$zeilen[] = 'DTEND;VALUE=DATE:' . str_replace( '-', '', ek_plus_tage( $d['ende'], 1 ) );
		} else {
			$s        = new DateTimeImmutable( $d['start'] . ' ' . $d['zeit'], $tz );
			$ende     = $d['zeit_ende'] ? new DateTimeImmutable( $d['ende'] . ' ' . $d['zeit_ende'], $tz ) : ( $d['ende'] !== $d['start'] ? new DateTimeImmutable( $d['ende'] . ' 23:59', $tz ) : $s->modify( '+1 hour' ) );
			$zeilen[] = 'DTSTART:' . $s->setTimezone( $utc )->format( 'Ymd\THis\Z' );
			$zeilen[] = 'DTEND:' . $ende->setTimezone( $utc )->format( 'Ymd\THis\Z' );
		}
		$zeilen[] = 'SUMMARY:' . ek_ics_text( html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ) );
		$text     = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $p->post_content ) ), 80, ' …' );
		if ( $text ) {
			$zeilen[] = 'DESCRIPTION:' . ek_ics_text( html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) );
		}
		if ( $d['ort_daten'] ) {
			$zeilen[] = 'LOCATION:' . ek_ics_text( trim( $d['ort_daten']['name'] . ', ' . $d['ort_daten']['adresse'], ', ' ) );
		}
		$zeilen[] = 'URL:' . ek_termin_url( $e );
		$zeilen[] = 'END:VEVENT';
	}
	$zeilen[] = 'END:VCALENDAR';

	nocache_headers();
	header( 'Content-Type: text/calendar; charset=utf-8' );
	header( 'Content-Disposition: ' . ( 'alle' === $was ? 'inline' : 'attachment' ) . '; filename="' . sanitize_file_name( $name ) . '.ics"' );
	foreach ( $zeilen as $z ) {
		echo ek_ics_falten( $z ) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- iCal-Text, oben maskiert
	}
	exit;
}
add_action( 'template_redirect', 'ek_ics_ausgeben', 0 );
