<?php
/**
 * Übernahme der Daten aus „The Events Calendar“ und Weiterleitung seiner alten Adressen.
 *
 * Die Daten werden direkt aus der Datenbank gelesen. Das funktioniert auch,
 * wenn The Events Calendar bereits deaktiviert ist. Dort wird nichts verändert.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EK_TEC_SCHRITT', 20 );

function ek_tec_status_liste() {
	return "'publish','future','draft','pending','private'";
}

/** Anzahl der Einträge in The Events Calendar. */
function ek_tec_bestand() {
	global $wpdb;
	$in = ek_tec_status_liste();
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$posts = $wpdb->get_results(
		"SELECT post_type, COUNT(*) AS n FROM {$wpdb->posts} WHERE post_type IN ('tribe_events','tribe_venue','tribe_organizer') AND post_status IN ($in) GROUP BY post_type",
		OBJECT_K
	);
	$kat   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'tribe_events_cat'" );
	// phpcs:enable
	return array(
		'termine'      => isset( $posts['tribe_events'] ) ? (int) $posts['tribe_events']->n : 0,
		'orte'         => isset( $posts['tribe_venue'] ) ? (int) $posts['tribe_venue']->n : 0,
		'veranstalter' => isset( $posts['tribe_organizer'] ) ? (int) $posts['tribe_organizer']->n : 0,
		'kategorien'   => $kat,
	);
}

/** Abschnitt auf der Einstellungsseite (nur, wenn es etwas zu übernehmen gibt). */
function ek_tec_abschnitt() {
	$b      = ek_tec_bestand();
	$fertig = (int) get_option( 'ek_tec_import_fertig', 0 );
	if ( ! $b['termine'] && ! $fertig ) {
		return;
	}
	?>
	<h2>Daten aus „The Events Calendar“ übernehmen</h2>
	<p>Gefunden: <strong><?php echo (int) $b['termine']; ?> Termine</strong>, <?php echo (int) $b['kategorien']; ?> Kategorien, <?php echo (int) $b['orte']; ?> Orte, <?php echo (int) $b['veranstalter']; ?> Veranstalter.</p>
	<p class="description" style="max-width:48em">Übernommen werden Titel, Beschreibung, Datum und Uhrzeit, ganztägig, Kategorien (mit Farben), Ort mit Adresse, Veranstalter mit Kontakt, Bild und Status. Eintritt und Veranstaltungs-Link werden als Zeile unten an die Beschreibung angehängt. Die Übernahme lässt sich gefahrlos wiederholen: Bereits übernommene Termine werden aktualisiert, nicht doppelt angelegt. In The Events Calendar wird nichts verändert.</p>
	<?php if ( $fertig ) : ?>
		<p><em>Zuletzt übernommen: <?php echo esc_html( wp_date( 'j. F Y, H:i', $fertig ) ); ?></em></p>
	<?php endif; ?>
	<p>
		<button type="button" class="button button-primary" id="ek-import-start" <?php disabled( ! $b['termine'] ); ?>><?php echo esc_html( $fertig ? 'Erneut übernehmen' : 'Jetzt übernehmen' ); ?></button>
	</p>
	<div id="ek-import-fortschritt" hidden>
		<progress id="ek-import-balken" max="100" value="0" style="width:100%;max-width:30em"></progress>
		<p id="ek-import-status" role="status"></p>
	</div>
	<div id="ek-import-danach" <?php echo $fertig ? '' : 'hidden'; ?>>
		<p><strong>Nächste Schritte:</strong></p>
		<ol>
			<li>Unter „Kalender → Alle Termine“ stichprobenartig prüfen, ob alles stimmt.</li>
			<li>Den Shortcode <code>[einfacher_kalender]</code> auf der Kalenderseite einfügen (falls noch nicht geschehen).</li>
			<li>The Events Calendar deaktivieren. Alte Links werden dann automatisch weitergeleitet. Die alten Daten bleiben in der Datenbank, bis du The Events Calendar löschst.</li>
		</ol>
	</div>
	<?php
}

function ek_tec_skript( $hook ) {
	if ( false === strpos( (string) $hook, 'ek-einstellungen' ) ) {
		return;
	}
	wp_enqueue_script( 'ek-import', EK_URL . 'assets/import.js', array(), EK_VERSION, true );
	wp_localize_script(
		'ek-import',
		'ekImport',
		array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'ek_tec_import' ),
			'texte' => array(
				'frage'  => 'Jetzt alle Daten aus The Events Calendar übernehmen? Bereits übernommene Termine werden dabei aktualisiert, nicht doppelt angelegt.',
				'laeuft' => 'Übernehme Daten …',
				'fertig' => 'Fertig! %d Termine wurden übernommen.',
				'fehler' => 'Fehler bei der Übernahme:',
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'ek_tec_skript' );

/* -------------------------------------------------------------------------
 * Übernahme in Schritten (AJAX)
 * ---------------------------------------------------------------------- */

function ek_tec_ajax() {
	check_ajax_referer( 'ek_tec_import', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Keine Berechtigung.' );
	}
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- oben geprüft
	$schritt = isset( $_POST['schritt'] ) ? sanitize_key( wp_unslash( $_POST['schritt'] ) ) : '';
	$ab      = isset( $_POST['ab'] ) ? absint( $_POST['ab'] ) : 0;
	// phpcs:enable

	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	try {
		$gesamt = ek_tec_bestand()['termine'];

		if ( 'begriffe' === $schritt ) {
			wp_defer_term_counting( true );
			ek_tec_pfade_merken();
			$map = array(
				'kat' => ek_tec_kategorien(),
				'ort' => ek_tec_verknuepfte( 'tribe_venue', 'ek_ort' ),
				'org' => ek_tec_verknuepfte( 'tribe_organizer', 'ek_veranstalter' ),
			);
			update_option( 'ek_tec_zuordnung', $map, false );
			wp_defer_term_counting( false );
			wp_send_json_success(
				array(
					'weiter'    => 'termine',
					'ab'        => 0,
					'erledigt'  => 0,
					'gesamt'    => $gesamt,
					'nachricht' => sprintf( '%1$d Kategorien, %2$d Orte, %3$d Veranstalter übernommen. Jetzt die Termine …', count( $map['kat'] ), count( $map['ort'] ), count( $map['org'] ) ),
				)
			);
		}

		if ( 'termine' === $schritt ) {
			wp_defer_term_counting( true );
			$jetzt = $ab + ek_tec_termine( $ab );
			wp_defer_term_counting( false );
			wp_send_json_success(
				array(
					'weiter'    => $jetzt - $ab >= EK_TEC_SCHRITT ? 'termine' : 'ende',
					'ab'        => $jetzt,
					'erledigt'  => $jetzt,
					'gesamt'    => $gesamt,
					'nachricht' => sprintf( '%1$d von %2$d Terminen übernommen …', $jetzt, $gesamt ),
				)
			);
		}

		if ( 'ende' === $schritt ) {
			update_option( 'ek_tec_import_fertig', time(), false );
			delete_transient( 'ek_kalenderseite' );
			wp_send_json_success(
				array(
					'weiter'   => '',
					'erledigt' => $ab,
					'gesamt'   => $gesamt,
				)
			);
		}
	} catch ( Throwable $e ) {
		wp_send_json_error( $e->getMessage() );
	}
	wp_send_json_error( 'Unbekannter Schritt.' );
}
add_action( 'wp_ajax_ek_tec_import', 'ek_tec_ajax' );

/** Merkt sich die alten Adressen (z. B. /termine/ und /veranstaltung/). */
function ek_tec_pfade_merken() {
	$liste  = '';
	$einzel = '';
	if ( class_exists( 'Tribe__Events__Main' ) && method_exists( 'Tribe__Events__Main', 'instance' ) ) {
		$main = Tribe__Events__Main::instance();
		if ( method_exists( $main, 'getRewriteSlug' ) ) {
			$liste = (string) $main->getRewriteSlug();
		}
		if ( method_exists( $main, 'getRewriteSlugSingular' ) ) {
			$einzel = (string) $main->getRewriteSlugSingular();
		}
	}
	$opts = (array) get_option( 'tribe_events_calendar_options', array() );
	if ( ! $liste ) {
		$liste = ! empty( $opts['eventsSlug'] ) ? $opts['eventsSlug'] : 'events';
	}
	if ( ! $einzel ) {
		$einzel = ! empty( $opts['singleEventSlug'] ) ? $opts['singleEventSlug'] : 'event';
	}
	update_option(
		'ek_tec_pfade',
		array(
			'events' => sanitize_title( $liste ),
			'single' => sanitize_title( $einzel ),
		),
		false
	);
}

/** Begriff über die alte ID finden, sonst über den Namen, sonst anlegen. */
function ek_tec_begriff( $tax, $alt_id, $name, $slug = '' ) {
	$gefunden = get_terms(
		array(
			'taxonomy'   => $tax,
			'hide_empty' => false,
			'number'     => 1,
			'fields'     => 'ids',
			'meta_key'   => 'ek_tec_id', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value' => (string) $alt_id, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	if ( ! is_wp_error( $gefunden ) && $gefunden ) {
		$term_id = (int) $gefunden[0];
		wp_update_term( $term_id, $tax, array( 'name' => $name ) );
		return $term_id;
	}

	$gibt = term_exists( $name, $tax );
	if ( $gibt ) {
		$term_id = (int) $gibt['term_id'];
	} else {
		$neu = wp_insert_term( $name, $tax, $slug ? array( 'slug' => $slug ) : array() );
		if ( is_wp_error( $neu ) ) {
			$neu = wp_insert_term( $name, $tax );
		}
		if ( is_wp_error( $neu ) ) {
			return 0;
		}
		$term_id = (int) $neu['term_id'];
	}
	update_term_meta( $term_id, 'ek_tec_id', (string) $alt_id );
	return $term_id;
}

/** @return array<int,int> alte ID → neue ID */
function ek_tec_kategorien() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$zeilen = $wpdb->get_results( "SELECT t.term_id, t.name, t.slug FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = 'tribe_events_cat'" );
	$map    = array();
	foreach ( (array) $zeilen as $z ) {
		$neu = ek_tec_begriff( 'ek_kategorie', (int) $z->term_id, html_entity_decode( $z->name, ENT_QUOTES, 'UTF-8' ), $z->slug );
		if ( ! $neu ) {
			continue;
		}
		$map[ (int) $z->term_id ] = $neu;
		$farbe = sanitize_hex_color( (string) get_term_meta( (int) $z->term_id, 'tec-events-cat-colors-primary', true ) );
		if ( $farbe && ! get_term_meta( $neu, 'ek_farbe', true ) ) {
			update_term_meta( $neu, 'ek_farbe', $farbe );
		}
	}
	return $map;
}

/**
 * Orte (tribe_venue) bzw. Veranstalter (tribe_organizer) als Begriffe übernehmen.
 *
 * @return array<int,int> alte ID → neue ID
 */
function ek_tec_verknuepfte( $typ, $tax ) {
	global $wpdb;
	$in     = ek_tec_status_liste();
	$felder = 'ek_ort' === $tax
		? array( 'ek_strasse' => '_VenueAddress', 'ek_plz' => '_VenueZip', 'ek_stadt' => '_VenueCity' )
		: array( 'ek_telefon' => '_OrganizerPhone', 'ek_email' => '_OrganizerEmail', 'ek_web' => '_OrganizerWebsite' );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$zeilen = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ($in) ORDER BY ID", $typ ) );
	$map    = array();
	foreach ( (array) $zeilen as $p ) {
		$name = trim( html_entity_decode( wp_strip_all_tags( $p->post_title ), ENT_QUOTES, 'UTF-8' ) );
		if ( '' === $name ) {
			continue;
		}
		$neu = ek_tec_begriff( $tax, (int) $p->ID, $name );
		if ( ! $neu ) {
			continue;
		}
		$map[ (int) $p->ID ] = $neu;
		foreach ( $felder as $meins => $deins ) {
			$wert = trim( (string) get_post_meta( (int) $p->ID, $deins, true ) );
			if ( '' !== $wert ) {
				update_term_meta( $neu, $meins, ek_meta_bereinigen( $meins, $wert ) );
			}
		}
	}
	return $map;
}

/** Beschreibung von Block-Markierungen der Events-Calendar-Blöcke befreien. */
function ek_tec_inhalt( $inhalt ) {
	$inhalt = (string) $inhalt;
	// Blöcke für Datum, Ort, Veranstalter usw. – diese Angaben stehen jetzt in eigenen Feldern.
	$inhalt = preg_replace( '#<!--\s*wp:tribe/[^>]*?/-->#s', '', $inhalt );
	$inhalt = preg_replace( '#<!--\s*wp:tribe/([a-z0-9-]+)\b[^>]*?-->.*?<!--\s*/wp:tribe/\1\s*-->#s', '', $inhalt );
	// Übrige Block-Kommentare entfernen, der HTML-Inhalt bleibt.
	$inhalt = preg_replace( '#<!--\s*/?wp:[^>]*?-->#s', '', $inhalt );
	$inhalt = preg_replace( "/\n{3,}/", "\n\n", $inhalt );
	return trim( $inhalt );
}

/** Termine ab Position $ab übernehmen; liefert die Anzahl. */
function ek_tec_termine( $ab ) {
	global $wpdb;
	$in = ek_tec_status_liste();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$zeilen = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE post_type = 'tribe_events' AND post_status IN ($in) ORDER BY ID LIMIT %d, %d", $ab, EK_TEC_SCHRITT ) );
	$map    = get_option( 'ek_tec_zuordnung', array() );
	$map    = is_array( $map ) ? $map : array();
	foreach ( (array) $zeilen as $alt ) {
		ek_tec_termin( $alt, $map );
	}
	return count( (array) $zeilen );
}

function ek_tec_termin( $alt, $map ) {
	global $wpdb;
	$alt_id = (int) $alt->ID;

	$vorhanden = get_posts(
		array(
			'post_type'      => 'ek_termin',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_ek_tec_id', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => (string) $alt_id, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);

	$inhalt = ek_tec_inhalt( $alt->post_content );

	// Eintritt und Link als Zeile anhängen, damit nichts verloren geht.
	$extra  = array();
	$preis  = trim( (string) get_post_meta( $alt_id, '_EventCost', true ) );
	$url    = trim( (string) get_post_meta( $alt_id, '_EventURL', true ) );
	if ( '' !== $preis ) {
		$zeichen = trim( (string) get_post_meta( $alt_id, '_EventCurrencySymbol', true ) );
		$vorne   = 'prefix' === (string) get_post_meta( $alt_id, '_EventCurrencyPosition', true );
		if ( $zeichen && is_numeric( str_replace( ',', '.', $preis ) ) ) {
			$preis = $vorne ? $zeichen . ' ' . $preis : $preis . ' ' . $zeichen;
		}
		$extra[] = '<strong>Eintritt:</strong> ' . esc_html( $preis );
	}
	if ( '' !== $url ) {
		$extra[] = '<strong>Weitere Infos:</strong> <a href="' . esc_url( $url ) . '">' . esc_html( preg_replace( '#^https?://#', '', $url ) ) . '</a>';
	}
	if ( $extra && false === strpos( $inhalt, 'ek-import-extra' ) ) {
		$inhalt .= "\n\n<p class=\"ek-import-extra\">" . implode( '<br>', $extra ) . '</p>';
	}

	$daten = array(
		'post_type'      => 'ek_termin',
		'post_title'     => $alt->post_title,
		'post_content'   => $inhalt,
		'post_excerpt'   => $alt->post_excerpt,
		'post_status'    => $alt->post_status,
		'post_author'    => (int) $alt->post_author,
		'post_date'      => $alt->post_date,
		'post_date_gmt'  => $alt->post_date_gmt,
		'post_name'      => $alt->post_name,
		'comment_status' => 'closed',
		'ping_status'    => 'closed',
	);
	if ( $vorhanden ) {
		$daten['ID'] = (int) $vorhanden[0];
		$neu_id      = wp_update_post( wp_slash( $daten ), true );
	} else {
		$neu_id = wp_insert_post( wp_slash( $daten ), true );
	}
	if ( is_wp_error( $neu_id ) || ! $neu_id ) {
		return;
	}
	update_post_meta( $neu_id, '_ek_tec_id', (string) $alt_id );
	update_post_meta( $neu_id, '_ek_tec_slug', $alt->post_name );

	// Datum: TEC speichert „JJJJ-MM-TT HH:MM:SS“ in Ortszeit.
	$start = substr( (string) get_post_meta( $alt_id, '_EventStartDate', true ), 0, 16 );
	$ende  = substr( (string) get_post_meta( $alt_id, '_EventEndDate', true ), 0, 16 );
	$ganz  = in_array( strtolower( (string) get_post_meta( $alt_id, '_EventAllDay', true ) ), array( 'yes', 'true', '1' ), true );
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $start ) ) {
		$start = substr( $alt->post_date, 0, 16 );
	}
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $ende ) ) {
		$ende = $start;
	}
	$tag_start = substr( $start, 0, 10 );
	$tag_ende  = substr( $ende, 0, 10 );
	// Ganztägige Termine enden in TEC um 23:59:59 – oder um 00:00 des Folgetags.
	if ( $ganz && '00:00' === substr( $ende, 11, 5 ) && $tag_ende > $tag_start ) {
		$tag_ende = ek_plus_tage( $tag_ende, -1 );
	}
	$zeit      = $ganz ? '' : ek_zeit_pruefen( substr( $start, 11, 5 ) );
	$zeit_ende = $zeit ? ek_zeit_pruefen( substr( $ende, 11, 5 ) ) : '';
	ek_termin_speichern( $neu_id, $tag_start, $tag_ende, $zeit, $zeit_ende, null );

	$bild = (int) get_post_meta( $alt_id, '_thumbnail_id', true );
	if ( $bild ) {
		set_post_thumbnail( $neu_id, $bild );
	}

	// Kategorien.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$alte_kat = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT tt.term_id FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tr.object_id = %d AND tt.taxonomy = 'tribe_events_cat'",
			$alt_id
		)
	);
	$kat = array();
	foreach ( (array) $alte_kat as $k ) {
		if ( ! empty( $map['kat'][ (int) $k ] ) ) {
			$kat[] = (int) $map['kat'][ (int) $k ];
		}
	}
	wp_set_object_terms( $neu_id, $kat, 'ek_kategorie' );

	// Ort.
	$ort = (int) get_post_meta( $alt_id, '_EventVenueID', true );
	wp_set_object_terms( $neu_id, ! empty( $map['ort'][ $ort ] ) ? array( (int) $map['ort'][ $ort ] ) : array(), 'ek_ort' );
	if ( ! empty( $map['ort'][ $ort ] ) ) {
		delete_post_meta( $neu_id, '_ek_ort' );
		delete_post_meta( $neu_id, '_ek_ort_adresse' );
	}

	// Veranstalter (TEC erlaubt mehrere – der erste wird übernommen).
	$orgs = array_filter( array_map( 'intval', (array) get_post_meta( $alt_id, '_EventOrganizerID', false ) ) );
	$org  = $orgs ? reset( $orgs ) : 0;
	wp_set_object_terms( $neu_id, ! empty( $map['org'][ $org ] ) ? array( (int) $map['org'][ $org ] ) : array(), 'ek_veranstalter' );
}

/* -------------------------------------------------------------------------
 * Alte Adressen von The Events Calendar weiterleiten
 * ---------------------------------------------------------------------- */

function ek_tec_weiterleiten() {
	if ( ! is_404() || class_exists( 'Tribe__Events__Main' ) || ! ek_einstellungen()['tec_weiterleiten'] ) {
		return;
	}
	$pfade = get_option( 'ek_tec_pfade' );
	if ( ! is_array( $pfade ) || empty( $pfade['events'] ) ) {
		return;
	}
	$pfad = ek_anfrage_pfad();

	// Einzelner Termin: /veranstaltung/name/ (ggf. mit Datum dahinter).
	if ( ! empty( $pfade['single'] ) && preg_match( '#^' . preg_quote( $pfade['single'], '#' ) . '/([^/]+)#', $pfad, $m ) ) {
		$id = ek_termin_nach_name( $m[1], '_ek_tec_slug' );
		if ( $id ) {
			wp_safe_redirect( get_permalink( $id ), 301 );
			exit;
		}
	}

	// Übersicht: /termine/, /termine/monat/, /termine/kategorie/x/ …
	if ( $pfad === $pfade['events'] || 0 === strpos( $pfad, $pfade['events'] . '/' ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['ical'] ) ) {
			wp_safe_redirect( ek_ics_url( 'alle' ), 301 );
			exit;
		}
		$seite = ek_kalenderseite_url();
		wp_safe_redirect( $seite ? $seite : home_url( '/' ), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'ek_tec_weiterleiten', 1 );
