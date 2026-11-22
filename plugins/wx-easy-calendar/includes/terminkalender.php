<?php
/**
 * Umstieg vom Plugin „Terminkalender“ (tk_termin) auf WX Easy Calendar (ek_termin).
 *
 * - Ist der Terminkalender noch aktiv, wird er abgeschaltet.
 * - Beim nächsten Aufruf im Backend werden seine Termine, Kategorien, Orte, Veranstalter
 *   und Einstellungen einmalig übernommen. Die Termine behalten ihre ID, Bilder,
 *   Autoren und ihren Status; es wird nur der Beitragstyp umgestellt.
 * - Shortcodes [terminkalender] und [veranstaltungskalender], alte Terminseiten
 *   (/termin/…) und alte iCal-Links (?tk_ics=…) funktionieren weiter.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EK_TK_FERTIG', 'ek_tk_uebernommen' );

/** Farben des Terminkalenders für Kategorien ohne eigene Farbe (gleiche Reihenfolge wie dort). */
function ek_tk_palette() {
	return array( '#2563eb', '#c2410c', '#7c3aed', '#15803d', '#0e7490', '#be185d', '#a16207', '#4f46e5', '#b91c1c', '#047857' );
}

/* -------------------------------------------------------------------------
 * Altes Plugin abschalten
 * ---------------------------------------------------------------------- */

/** Aktive Installationen des Terminkalenders (Plugin-Dateinamen). */
function ek_tk_aktive_plugins() {
	$aktiv = (array) get_option( 'active_plugins', array() );
	if ( is_multisite() ) {
		$aktiv = array_merge( $aktiv, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
	}
	return array_values(
		array_filter(
			$aktiv,
			function ( $datei ) {
				return 'terminkalender.php' === basename( (string) $datei );
			}
		)
	);
}

function ek_tk_abschalten() {
	if ( ! defined( 'TK_VERSION' ) && ! class_exists( 'TK_Model' ) ) {
		return;
	}
	$plugins = ek_tk_aktive_plugins();
	if ( ! $plugins ) {
		return;
	}
	if ( ! function_exists( 'deactivate_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	// Ohne Deaktivierungs-Routine, damit nichts verloren geht. Den Aufräum-Zeitplan entfernen wir selbst.
	deactivate_plugins( $plugins, true );
	wp_clear_scheduled_hook( 'tk_daily_cleanup' );
	// Übernahme (erneut) anstoßen – sie ist wiederholbar und legt nichts doppelt an.
	delete_option( EK_TK_FERTIG );
}
add_action( 'plugins_loaded', 'ek_tk_abschalten' );

/* -------------------------------------------------------------------------
 * Übernahme
 * ---------------------------------------------------------------------- */

/** Gibt es noch Daten des Terminkalenders? */
function ek_tk_daten_vorhanden() {
	global $wpdb;
	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$posts = $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'tk_termin' LIMIT 1" );
	$terms = $wpdb->get_var( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy IN ('tk_kategorie','tk_ort','tk_veranstalter') LIMIT 1" );
	// phpcs:enable
	return $posts || $terms || false !== get_option( 'tk_settings' );
}

function ek_tk_uebernahme_pruefen() {
	if ( get_option( EK_TK_FERTIG ) || class_exists( 'TK_Model' ) || wp_doing_ajax() ) {
		return;
	}
	if ( ! ek_tk_daten_vorhanden() ) {
		update_option( EK_TK_FERTIG, array( 'zeit' => time(), 'termine' => 0 ), false );
		return;
	}
	if ( get_transient( 'ek_tk_sperre' ) ) {
		return; // läuft gerade in einem anderen Aufruf
	}
	set_transient( 'ek_tk_sperre', 1, 5 * MINUTE_IN_SECONDS );
	$ergebnis = ek_tk_uebernehmen();
	delete_transient( 'ek_tk_sperre' );

	if ( $ergebnis ) {
		update_option( EK_TK_FERTIG, $ergebnis, false );
		update_option( 'ek_tk_hinweis', 1, false );
	}
}
add_action( 'admin_init', 'ek_tk_uebernahme_pruefen', 5 );

/**
 * Übernimmt alles. Liefert eine Zusammenfassung oder null, wenn die Zeit nicht gereicht hat
 * (dann geht es beim nächsten Aufruf weiter).
 *
 * @return array|null
 */
function ek_tk_uebernehmen() {
	global $wpdb;
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	$ende = time() + 60;

	$stand = get_option( 'ek_tk_stand', array() );
	$stand = is_array( $stand ) ? $stand : array();

	// 1. Kategorien, Orte und Veranstalter (vor den Terminen, damit die Zuordnung stimmt).
	if ( empty( $stand['begriffe'] ) ) {
		$stand['kategorien']   = ek_tk_begriffe_uebernehmen( 'tk_kategorie', 'ek_kategorie' );
		$stand['orte']         = ek_tk_begriffe_uebernehmen( 'tk_ort', 'ek_ort' );
		$stand['veranstalter'] = ek_tk_begriffe_uebernehmen( 'tk_veranstalter', 'ek_veranstalter' );
		$stand['begriffe']     = 1;
		$stand['termine']      = 0;
		update_option( 'ek_tk_stand', $stand, false );
	}

	// 2. Termine in Schritten.
	do {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'tk_termin' ORDER BY ID LIMIT 100" );
		foreach ( $ids as $id ) {
			ek_tk_termin_umwandeln( (int) $id );
			$stand['termine']++;
		}
		update_option( 'ek_tk_stand', $stand, false );
		if ( $ids && time() > $ende ) {
			return null;
		}
	} while ( $ids );

	// 3. Menüeinträge, die auf einzelne Termine zeigen.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value = 'ek_termin' WHERE meta_key = '_menu_item_object' AND meta_value = 'tk_termin'" );

	// 4. Einstellungen.
	ek_tk_einstellungen_uebernehmen();

	// 5. Zähler, Zwischenspeicher, Permalinks.
	foreach ( array( 'ek_kategorie', 'ek_ort', 'ek_veranstalter' ) as $tax ) {
		$tt = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'fields' => 'tt_ids' ) );
		if ( ! is_wp_error( $tt ) && $tt ) {
			wp_update_term_count_now( $tt, $tax );
		}
		clean_taxonomy_cache( $tax );
	}
	wp_cache_flush();
	delete_transient( 'ek_kalenderseite' );
	flush_rewrite_rules();
	delete_option( 'ek_tk_stand' );

	return array(
		'zeit'         => time(),
		'termine'      => (int) $stand['termine'],
		'kategorien'   => (int) $stand['kategorien'],
		'orte'         => (int) $stand['orte'],
		'veranstalter' => (int) $stand['veranstalter'],
	);
}

/**
 * Übernimmt die Begriffe einer Taxonomie. Gibt es schon einen gleichnamigen Begriff
 * (gleicher Slug oder Name), werden beide zusammengelegt, sonst wird der Begriff
 * umgehängt und behält seine ID.
 *
 * @return int Anzahl der übernommenen Begriffe.
 */
function ek_tk_begriffe_uebernehmen( $alt, $neu ) {
	global $wpdb;
	$meta = array(
		'tk_farbe'   => 'ek_farbe',
		'tk_strasse' => 'ek_strasse',
		'tk_plz'     => 'ek_plz',
		'tk_stadt'   => 'ek_stadt',
		'tk_telefon' => 'ek_telefon',
		'tk_email'   => 'ek_email',
		'tk_web'     => 'ek_web',
		'tk_tec_id'  => 'ek_tec_id',
	);

	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$zeilen = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT tt.term_taxonomy_id, tt.term_id, tt.parent, t.name, t.slug
			 FROM {$wpdb->term_taxonomy} tt INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
			 WHERE tt.taxonomy = %s ORDER BY t.name ASC",
			$alt
		)
	);

	// Kategorien ohne eigene Farbe bekamen im Terminkalender eine Farbe nach Reihenfolge – so bleibt es.
	$farben = array();
	if ( 'tk_kategorie' === $alt ) {
		$palette = ek_tk_palette();
		foreach ( array_values( (array) $zeilen ) as $i => $z ) {
			$farben[ (int) $z->term_id ] = $palette[ $i % count( $palette ) ];
		}
	}

	$zuordnung = array();
	$verschoben = array();
	foreach ( (array) $zeilen as $z ) {
		$term_id = (int) $z->term_id;
		$tt_id   = (int) $z->term_taxonomy_id;

		$ziel = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT tt.term_taxonomy_id, tt.term_id FROM {$wpdb->term_taxonomy} tt INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
				 WHERE tt.taxonomy = %s AND t.slug = %s LIMIT 1",
				$neu,
				$z->slug
			)
		);
		if ( ! $ziel ) {
			$ziel = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT tt.term_taxonomy_id, tt.term_id FROM {$wpdb->term_taxonomy} tt INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
					 WHERE tt.taxonomy = %s AND t.name = %s LIMIT 1",
					$neu,
					$z->name
				)
			);
		}

		if ( $ziel ) {
			// Zusammenlegen: Zuordnungen umhängen, fehlende Angaben ergänzen, alten Begriff entfernen.
			$ziel_id = (int) $ziel->term_id;
			$wpdb->query( $wpdb->prepare( "UPDATE IGNORE {$wpdb->term_relationships} SET term_taxonomy_id = %d WHERE term_taxonomy_id = %d", (int) $ziel->term_taxonomy_id, $tt_id ) );
			$wpdb->delete( $wpdb->term_relationships, array( 'term_taxonomy_id' => $tt_id ) );
			foreach ( $meta as $von => $nach ) {
				$wert = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->termmeta} WHERE term_id = %d AND meta_key = %s LIMIT 1", $term_id, $von ) );
				if ( null !== $wert && '' !== $wert && '' === (string) get_term_meta( $ziel_id, $nach, true ) ) {
					update_term_meta( $ziel_id, $nach, $wert );
				}
			}
			if ( isset( $farben[ $term_id ] ) && '' === (string) get_term_meta( $ziel_id, 'ek_farbe', true ) ) {
				update_term_meta( $ziel_id, 'ek_farbe', $farben[ $term_id ] );
			}
			$wpdb->delete( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => $tt_id ) );
			$noch = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE term_id = %d", $term_id ) );
			if ( ! $noch ) {
				$wpdb->delete( $wpdb->terms, array( 'term_id' => $term_id ) );
				$wpdb->delete( $wpdb->termmeta, array( 'term_id' => $term_id ) );
			}
			$zuordnung[ $term_id ] = $ziel_id;
		} else {
			// Umhängen: Der Begriff behält ID, Slug und Zuordnungen.
			$wpdb->update( $wpdb->term_taxonomy, array( 'taxonomy' => $neu ), array( 'term_taxonomy_id' => $tt_id ) );
			foreach ( $meta as $von => $nach ) {
				$wpdb->update( $wpdb->termmeta, array( 'meta_key' => $nach ), array( 'term_id' => $term_id, 'meta_key' => $von ) );
			}
			wp_cache_delete( $term_id, 'term_meta' );
			if ( isset( $farben[ $term_id ] ) && '' === (string) get_term_meta( $term_id, 'ek_farbe', true ) ) {
				update_term_meta( $term_id, 'ek_farbe', $farben[ $term_id ] );
			}
			$zuordnung[ $term_id ] = $term_id;
			$verschoben[ $term_id ] = (int) $z->parent;
		}
		clean_term_cache( $term_id );
	}

	// Übergeordnete Kategorie anpassen, wenn diese zusammengelegt wurde.
	foreach ( $verschoben as $term_id => $eltern ) {
		if ( $eltern && isset( $zuordnung[ $eltern ] ) && $zuordnung[ $eltern ] !== $eltern ) {
			$wpdb->update( $wpdb->term_taxonomy, array( 'parent' => $zuordnung[ $eltern ] ), array( 'term_id' => $term_id, 'taxonomy' => $neu ) );
		}
	}
	// phpcs:enable

	return count( $zuordnung );
}

/** Wandelt einen Termin des Terminkalenders um (wiederholbar). */
function ek_tk_termin_umwandeln( $id ) {
	global $wpdb;

	$start  = (string) get_post_meta( $id, '_tk_start', true );
	$end    = (string) get_post_meta( $id, '_tk_end', true );
	$allday = (bool) get_post_meta( $id, '_tk_allday', true );
	$regel  = get_post_meta( $id, '_tk_rule', true );

	// Datum: „JJJJ-MM-TT HH:MM“ → Tag und Uhrzeit getrennt.
	if ( ek_datum_pruefen( substr( $start, 0, 10 ) ) ) {
		$tag_start = substr( $start, 0, 10 );
		$tag_ende  = ek_datum_pruefen( substr( $end, 0, 10 ) );
		$tag_ende  = $tag_ende ? $tag_ende : $tag_start;
		$zeit      = $allday ? '' : ek_zeit_pruefen( substr( $start, 11, 5 ) );
		$zeit_ende = ( $allday || ! $zeit ) ? '' : ek_zeit_pruefen( substr( $end, 11, 5 ) );
		if ( $zeit_ende === $zeit && $tag_ende === $tag_start ) {
			$zeit_ende = '';
		}
		ek_termin_speichern( $id, $tag_start, $tag_ende, $zeit, $zeit_ende, ( is_array( $regel ) && ! empty( $regel['freq'] ) ) ? $regel : null );
	}

	// Frei eingegebener Ort.
	$frei = get_post_meta( $id, '_tk_ort_frei', true );
	if ( is_array( $frei ) && ! empty( $frei['name'] ) ) {
		update_post_meta( $id, '_ek_ort', sanitize_text_field( $frei['name'] ) );
		if ( ! empty( $frei['address'] ) ) {
			update_post_meta( $id, '_ek_ort_adresse', sanitize_text_field( $frei['address'] ) );
		}
	}

	// Herkunft aus The Events Calendar (für Weiterleitungen und erneute Übernahme).
	foreach ( array( '_tk_tec_id' => '_ek_tec_id', '_tk_tec_slug' => '_ek_tec_slug' ) as $von => $nach ) {
		$wert = (string) get_post_meta( $id, $von, true );
		if ( '' !== $wert ) {
			update_post_meta( $id, $nach, $wert );
		}
	}

	foreach ( array( '_tk_start', '_tk_end', '_tk_allday', '_tk_last', '_tk_rule', '_tk_ort_frei', '_tk_tec_id', '_tk_tec_slug' ) as $key ) {
		delete_post_meta( $id, $key );
	}

	// Adresse eindeutig halten: Gibt es schon einen Termin mit gleichem Namen, bekommt dieser ein „-2“.
	$post  = get_post( $id );
	$daten = array( 'post_type' => 'ek_termin' );
	if ( $post && '' !== $post->post_name ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$doppelt = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'ek_termin' AND post_name = %s AND ID <> %d LIMIT 1", $post->post_name, $id ) );
		if ( $doppelt ) {
			update_post_meta( $id, '_ek_tk_name', $post->post_name );
			$daten['post_name'] = wp_unique_post_slug( $post->post_name, $id, 'publish', 'ek_termin', (int) $post->post_parent );
		}
	}
	$wpdb->update( $wpdb->posts, $daten, array( 'ID' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	clean_post_cache( $id );
}

/** Einstellungen des Terminkalenders übernehmen, soweit es sie hier gibt. */
function ek_tk_einstellungen_uebernehmen() {
	$tk = get_option( 'tk_settings' );
	$tk = is_array( $tk ) ? $tk : array();
	// Bei einer Wiederholung (Terminkalender erneut aktiviert) hier inzwischen Geändertes nicht überschreiben.
	$erstes_mal = false === get_option( 'ek_tk_ansicht' );

	// Akzentfarbe: nur eine selbst gewählte und nur, wenn hier noch nichts eingestellt ist.
	$farbe = isset( $tk['accent'] ) ? sanitize_hex_color( $tk['accent'] ) : '';
	$neu   = array();
	if ( $farbe && '#0b5cad' !== strtolower( $farbe ) && false === get_option( 'ek_einstellungen' ) ) {
		$neu['akzent']       = 'eigen';
		$neu['eigene_farbe'] = $farbe;
	}
	if ( $erstes_mal && isset( $tk['redirect_tec'] ) && ! $tk['redirect_tec'] ) {
		$neu['tec_weiterleiten'] = 0;
	}
	if ( $neu ) {
		update_option( 'ek_einstellungen', ek_einstellungen_bereinigen( array_merge( ek_einstellungen(), $neu ) ) );
	}

	// Startansicht für [terminkalender] ohne Angabe und Adresse der alten Terminseiten.
	$ansichten = array( 'month' => 'monat', 'week' => 'woche', 'list' => 'liste' );
	$ansicht   = isset( $tk['default_view'], $ansichten[ $tk['default_view'] ] ) ? $ansichten[ $tk['default_view'] ] : 'monat';
	update_option( 'ek_tk_ansicht', $ansicht, false );
	$pfad = isset( $tk['single_slug'] ) ? sanitize_title( $tk['single_slug'] ) : '';
	update_option( 'ek_tk_pfad', $pfad ? $pfad : 'termin', false );

	// Automatisch aufräumen.
	$monate = (int) get_option( 'tk_auto_cleanup', 0 );
	if ( $monate > 0 && (int) get_option( 'ek_auto_aufraeumen', 0 ) <= 0 ) {
		update_option( 'ek_auto_aufraeumen', in_array( $monate, ek_aufraeumen_zeitraeume(), true ) ? $monate : 12 );
	}
	wp_clear_scheduled_hook( 'tk_daily_cleanup' );

	// Übernahme aus The Events Calendar: alte Adressen und Zeitpunkt.
	$tec = get_option( 'tk_tec_slugs' );
	if ( is_array( $tec ) && ! get_option( 'ek_tec_pfade' ) ) {
		update_option( 'ek_tec_pfade', $tec, false );
	}
	$fertig = get_option( 'tk_import_done' );
	if ( $fertig && ! get_option( 'ek_tec_import_fertig' ) ) {
		update_option( 'ek_tec_import_fertig', (int) $fertig, false );
	}
}

function ek_tk_hinweis() {
	if ( ! get_option( 'ek_tk_hinweis' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	delete_option( 'ek_tk_hinweis' );
	$e = (array) get_option( EK_TK_FERTIG, array() );
	$text = sprintf(
		'WX Easy Calendar hat die Daten aus dem Terminkalender übernommen: %1$d Termine, %2$d Kategorien, %3$d Orte, %4$d Veranstalter. Die Shortcodes [terminkalender] und alte Links funktionieren weiter. Das Plugin „Terminkalender“ ist abgeschaltet und kann jetzt gelöscht werden.',
		isset( $e['termine'] ) ? (int) $e['termine'] : 0,
		isset( $e['kategorien'] ) ? (int) $e['kategorien'] : 0,
		isset( $e['orte'] ) ? (int) $e['orte'] : 0,
		isset( $e['veranstalter'] ) ? (int) $e['veranstalter'] : 0
	);
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
}
add_action( 'admin_notices', 'ek_tk_hinweis' );

/* -------------------------------------------------------------------------
 * Weiter nutzbar: Shortcodes, alte Terminseiten, alte iCal-Links
 * ---------------------------------------------------------------------- */

/**
 * [terminkalender ansicht="woche" kategorien="a,b"] – wie im Terminkalender,
 * dargestellt mit [einfacher_kalender].
 */
function ek_tk_shortcode( $atts ) {
	$atts    = is_array( $atts ) ? $atts : array();
	$wunsch  = isset( $atts['ansicht'] ) ? strtolower( trim( (string) $atts['ansicht'] ) ) : '';
	$namen   = array(
		'liste' => 'liste',
		'list'  => 'liste',
		'woche' => 'woche',
		'week'  => 'woche',
		'monat' => 'monat',
		'month' => 'monat',
		'jahr'  => 'jahr',
		'year'  => 'jahr',
	);
	$ansicht = isset( $namen[ $wunsch ] ) ? $namen[ $wunsch ] : (string) get_option( 'ek_tk_ansicht', 'monat' );

	$neu = array( 'ansicht' => $ansicht );
	if ( ! empty( $atts['kategorien'] ) ) {
		$neu['kategorie'] = (string) $atts['kategorien'];
	}
	return ek_shortcode_kalender( $neu );
}

function ek_tk_shortcodes() {
	foreach ( array( 'terminkalender', 'veranstaltungskalender' ) as $name ) {
		if ( ! shortcode_exists( $name ) ) {
			add_shortcode( $name, 'ek_tk_shortcode' );
		}
	}
}
add_action( 'init', 'ek_tk_shortcodes', 20 );

/** Pfad der aktuellen Anfrage ohne Unterordner der Website, z. B. „termin/sommerfest“. */
function ek_anfrage_pfad() {
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$pfad = trim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' );
	$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
	if ( $home && 0 === strpos( $pfad, $home . '/' ) ) {
		$pfad = substr( $pfad, strlen( $home ) + 1 );
	}
	return rawurldecode( $pfad );
}

/** Veröffentlichten Termin über seinen Namen oder einen gemerkten alten Namen finden. */
function ek_termin_nach_name( $name, $meta_key ) {
	$name = sanitize_title( $name );
	if ( '' === $name ) {
		return 0;
	}
	$args = array(
		'post_type'      => 'ek_termin',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	);
	$ids = get_posts( array_merge( $args, array( 'meta_key' => $meta_key, 'meta_value' => $name ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	if ( ! $ids && '_ek_tk_name' === $meta_key ) {
		$ids = get_posts( array_merge( $args, array( 'name' => $name ) ) );
	}
	return $ids ? (int) $ids[0] : 0;
}

function ek_tk_alte_adressen() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['tk_ics'] ) ) {
		$was   = sanitize_key( wp_unslash( $_GET['tk_ics'] ) );
		$datum = isset( $_GET['datum'] ) ? ek_datum_pruefen( sanitize_text_field( wp_unslash( $_GET['datum'] ) ) ) : '';
		wp_safe_redirect( ek_ics_url( 'alle' === $was ? 'alle' : absint( $was ), $datum ), 301 );
		exit;
	}
	// phpcs:enable

	if ( ! is_404() ) {
		return;
	}
	$alt = (string) get_option( 'ek_tk_pfad', '' );
	if ( '' === $alt || 'termine' === $alt ) {
		return;
	}
	if ( preg_match( '#^' . preg_quote( $alt, '#' ) . '/([^/]+)#', ek_anfrage_pfad(), $m ) ) {
		$id = ek_termin_nach_name( $m[1], '_ek_tk_name' );
		if ( $id ) {
			wp_safe_redirect( get_permalink( $id ), 301 );
			exit;
		}
	}
}
add_action( 'template_redirect', 'ek_tk_alte_adressen', 0 );
