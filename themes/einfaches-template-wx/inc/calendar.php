<?php
/**
 * Integrierter Kalender, basierend auf Einfacher Kalender 1.3.1.
 * Lizenz: GPL-2.0-or-later.
 * @package Dunkel_Schlicht
 */

namespace DunkelSchlicht\Calendar;

use DateTimeImmutable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * 1. Beitragstyp "Termin" und Taxonomie "Kategorie"
 * ---------------------------------------------------------------------- */

function ek_register_post_type() {
	register_post_type(
		'ek_termin',
		array(
			'labels'       => array(
				'name'               => 'Termine',
				'singular_name'      => 'Termin',
				'menu_name'          => 'Kalender',
				'add_new'            => 'Neuer Termin',
				'add_new_item'       => 'Neuen Termin anlegen',
				'edit_item'          => 'Termin bearbeiten',
				'new_item'           => 'Neuer Termin',
				'view_item'          => 'Termin ansehen',
				'all_items'          => 'Alle Termine',
				'search_items'       => 'Termine durchsuchen',
				'not_found'          => 'Keine Termine gefunden.',
				'not_found_in_trash' => 'Keine Termine im Papierkorb.',
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
}
add_action( 'init', __NAMESPACE__ . '\\ek_register_post_type' );

/**
 * Für bestehende Theme-Installationen einmalig die Termin-Permalinks registrieren.
 */
function ek_rewrite_rules_initialisieren() {
	if ( is_customize_preview() || get_option( 'dunkel_calendar_rewrite' ) ) {
		return;
	}

	flush_rewrite_rules( false );
	update_option( 'dunkel_calendar_rewrite', 1, false );
}
add_action( 'wp_loaded', __NAMESPACE__ . '\\ek_rewrite_rules_initialisieren' );

/* -------------------------------------------------------------------------
 * 2. Hilfsfunktionen
 * ---------------------------------------------------------------------- */

/** Prüft ein Datum im Format JJJJ-MM-TT und gibt es zurück oder ''. */
function ek_datum_pruefen( $wert ) {
	$wert = is_string( $wert ) ? trim( $wert ) : '';
	$d    = DateTimeImmutable::createFromFormat( '!Y-m-d', $wert );
	return ( $d && $d->format( 'Y-m-d' ) === $wert ) ? $wert : '';
}

/** Prüft eine Uhrzeit im Format HH:MM und gibt sie zurück oder ''. */
function ek_zeit_pruefen( $wert ) {
	$wert = is_string( $wert ) ? trim( $wert ) : '';
	return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $wert ) ? $wert : '';
}

/** Formatiert ein JJJJ-MM-TT-Datum im Datumsformat der Website. */
function ek_datum_ausgeben( $ymd, $format = '' ) {
	$d = DateTimeImmutable::createFromFormat( '!Y-m-d', $ymd, wp_timezone() );
	if ( ! $d ) {
		return '';
	}
	return wp_date( $format ? $format : get_option( 'date_format' ), $d->getTimestamp() );
}

/** Liefert alle Termin-Daten eines Beitrags. */
function ek_termin_daten( $post_id ) {
	$start = get_post_meta( $post_id, '_ek_start', true );
	$ende  = get_post_meta( $post_id, '_ek_ende', true );
	$terms = get_the_terms( $post_id, 'ek_kategorie' );
	return array(
		'start'      => $start,
		'ende'       => $ende ? $ende : $start,
		'zeit'       => get_post_meta( $post_id, '_ek_zeit', true ),
		'ort'        => get_post_meta( $post_id, '_ek_ort', true ),
		'kategorien' => ( $terms && ! is_wp_error( $terms ) ) ? $terms : array(),
	);
}

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

/** Lesbare Zusammenfassung: "9. November 2026, 18:00 Uhr" bzw. Zeitraum. */
function ek_termin_zeitraum( $daten ) {
	$text = ek_datum_ausgeben( $daten['start'] );
	if ( $daten['ende'] && $daten['ende'] !== $daten['start'] ) {
		$text .= ' – ' . ek_datum_ausgeben( $daten['ende'] );
	}
	if ( $daten['zeit'] ) {
		$text .= ', ' . $daten['zeit'] . ' Uhr';
	}
	return $text;
}

/** Kategorie-Etiketten als HTML. */
function ek_kategorie_badges( $daten ) {
	$html = '';
	foreach ( $daten['kategorien'] as $term ) {
		$html .= '<span class="ek-badge"' . ek_farb_style( ek_kategorie_farbe( $term->term_id ) ) . '>' . esc_html( $term->name ) . '</span>';
	}
	return $html;
}

/** Wandelt "slug1, slug2" in ein Array gültiger Kategorie-Slugs um. */
function ek_kategorien_parsen( $wert ) {
	$slugs = array_filter( array_map( 'sanitize_title', explode( ',', (string) $wert ) ) );
	return array_values(
		array_filter(
			$slugs,
			function ( $slug ) {
				return (bool) get_term_by( 'slug', $slug, 'ek_kategorie' );
			}
		)
	);
}

/**
 * Holt veröffentlichte Termine, die den Zeitraum $von bis $bis berühren.
 * $bis = '' bedeutet: ohne Ende (alle ab $von).
 */
function ek_termine_holen( $von, $bis, $anzahl = -1, $kategorien = array() ) {
	$meta_query = array(
		'relation' => 'AND',
		array(
			'key'     => '_ek_ende',
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

	$termine = array();
	foreach ( get_posts( $args ) as $post ) {
		$termine[] = array(
			'post'  => $post,
			'daten' => ek_termin_daten( $post->ID ),
		);
	}

	// Nach Startdatum und Uhrzeit sortieren.
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
	$tz      = wp_timezone();
	$pro_tag = array();
	foreach ( $termine as $eintrag ) {
		$d   = $eintrag['daten'];
		$tag = new DateTimeImmutable( max( $d['start'], $von ), $tz );
		$end = min( $d['ende'], $bis );
		while ( $tag->format( 'Y-m-d' ) <= $end ) {
			$pro_tag[ $tag->format( 'Y-m-d' ) ][] = $eintrag;
			$tag = $tag->modify( '+1 day' );
		}
	}
	return $pro_tag;
}

/* -------------------------------------------------------------------------
 * 3. Backend: Termin-Felder
 * ---------------------------------------------------------------------- */

function ek_meta_box_hinzufuegen() {
	add_meta_box( 'ek_termin_details', 'Termin-Details', __NAMESPACE__ . '\\ek_meta_box_anzeigen', 'ek_termin', 'side', 'high' );
}
add_action( 'add_meta_boxes', __NAMESPACE__ . '\\ek_meta_box_hinzufuegen' );

function ek_meta_box_anzeigen( $post ) {
	wp_nonce_field( 'ek_termin_speichern', 'ek_nonce' );
	$d = ek_termin_daten( $post->ID );
	if ( get_post_meta( $post->ID, '_ek_ende', true ) === $d['start'] ) {
		$d['ende'] = ''; // eintägige Termine: Enddatum-Feld leer lassen
	}
	?>
	<p>
		<label for="ek_start"><strong>Datum *</strong></label><br>
		<input type="date" id="ek_start" name="ek_start" value="<?php echo esc_attr( $d['start'] ); ?>" required style="width:100%">
	</p>
	<p>
		<label for="ek_ende">Enddatum <span class="description">(nur bei mehrtägigen Terminen)</span></label><br>
		<input type="date" id="ek_ende" name="ek_ende" value="<?php echo esc_attr( $d['ende'] ); ?>" style="width:100%">
	</p>
	<p>
		<label for="ek_zeit">Uhrzeit <span class="description">(optional)</span></label><br>
		<input type="time" id="ek_zeit" name="ek_zeit" value="<?php echo esc_attr( $d['zeit'] ); ?>" style="width:100%">
	</p>
	<p>
		<label for="ek_ort">Ort <span class="description">(optional)</span></label><br>
		<input type="text" id="ek_ort" name="ek_ort" value="<?php echo esc_attr( $d['ort'] ); ?>" style="width:100%">
	</p>
	<?php
}

function ek_meta_speichern( $post_id ) {
	if ( ! isset( $_POST['ek_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ek_nonce'] ) ), 'ek_termin_speichern' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$start = ek_datum_pruefen( isset( $_POST['ek_start'] ) ? sanitize_text_field( wp_unslash( $_POST['ek_start'] ) ) : '' );
	$ende  = ek_datum_pruefen( isset( $_POST['ek_ende'] ) ? sanitize_text_field( wp_unslash( $_POST['ek_ende'] ) ) : '' );
	$zeit  = ek_zeit_pruefen( isset( $_POST['ek_zeit'] ) ? sanitize_text_field( wp_unslash( $_POST['ek_zeit'] ) ) : '' );
	$ort   = isset( $_POST['ek_ort'] ) ? sanitize_text_field( wp_unslash( $_POST['ek_ort'] ) ) : '';

	if ( ! $start ) {
		delete_post_meta( $post_id, '_ek_start' );
		delete_post_meta( $post_id, '_ek_ende' );
	} else {
		if ( ! $ende || $ende < $start ) {
			$ende = $start; // Enddatum wird immer gespeichert, damit die Abfrage einfach bleibt.
		}
		update_post_meta( $post_id, '_ek_start', $start );
		update_post_meta( $post_id, '_ek_ende', $ende );
	}
	update_post_meta( $post_id, '_ek_zeit', $zeit );
	update_post_meta( $post_id, '_ek_ort', $ort );
}
add_action( 'save_post_ek_termin', __NAMESPACE__ . '\\ek_meta_speichern' );

/* Spalten "Termin" und "Ort" in der Terminübersicht, sortierbar. */
function ek_spalten( $spalten ) {
	$neu = array();
	foreach ( $spalten as $key => $label ) {
		$neu[ $key ] = $label;
		if ( 'title' === $key ) {
			$neu['ek_datum'] = 'Termin';
			$neu['ek_ort']   = 'Ort';
		}
	}
	unset( $neu['date'] );
	return $neu;
}
add_filter( 'manage_ek_termin_posts_columns', __NAMESPACE__ . '\\ek_spalten' );

function ek_spalten_inhalt( $spalte, $post_id ) {
	$d = ek_termin_daten( $post_id );
	if ( 'ek_datum' === $spalte ) {
		echo $d['start'] ? esc_html( ek_termin_zeitraum( $d ) ) : '<em>kein Datum</em>';
	} elseif ( 'ek_ort' === $spalte ) {
		echo esc_html( $d['ort'] );
	}
}
add_action( 'manage_ek_termin_posts_custom_column', __NAMESPACE__ . '\\ek_spalten_inhalt', 10, 2 );

function ek_spalten_sortierbar( $spalten ) {
	$spalten['ek_datum'] = 'ek_datum';
	return $spalten;
}
add_filter( 'manage_edit-ek_termin_sortable_columns', __NAMESPACE__ . '\\ek_spalten_sortierbar' );

function ek_admin_sortierung( $query ) {
	if ( is_admin() && $query->is_main_query() && 'ek_termin' === $query->get( 'post_type' ) ) {
		if ( ! $query->get( 'orderby' ) || 'ek_datum' === $query->get( 'orderby' ) ) {
			$query->set( 'meta_key', '_ek_start' );
			$query->set( 'orderby', 'meta_value' );
			if ( ! $query->get( 'order' ) ) {
				$query->set( 'order', 'DESC' );
			}
		}
	}
}
add_action( 'pre_get_posts', __NAMESPACE__ . '\\ek_admin_sortierung' );

/* -------------------------------------------------------------------------
 * 4. Backend: Farbe pro Kategorie
 * ---------------------------------------------------------------------- */

function ek_kategorie_feld_neu() {
	?>
	<div class="form-field">
		<label for="ek_farbe">Farbe</label>
		<?php list( , $vorschlag ) = ek_akzentfarbe(); ?>
		<input type="color" name="ek_farbe" id="ek_farbe" list="ek-theme-farben" value="<?php echo esc_attr( sanitize_hex_color( $vorschlag ) ? strtolower( $vorschlag ) : EK_STANDARDFARBE ); ?>">
		<?php echo ek_palette_datalist(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<p>Farbe, in der Termine dieser Kategorie im Kalender erscheinen. Die Farben deines Themes werden im Farbwähler als Vorschläge angezeigt.</p>
	</div>
	<?php
}
add_action( 'ek_kategorie_add_form_fields', __NAMESPACE__ . '\\ek_kategorie_feld_neu' );

function ek_kategorie_feld_bearbeiten( $term ) {
	$farbe = ek_kategorie_farbe( $term->term_id );
	?>
	<tr class="form-field">
		<th scope="row"><label for="ek_farbe">Farbe</label></th>
		<td>
			<input type="color" name="ek_farbe" id="ek_farbe" list="ek-theme-farben" value="<?php echo esc_attr( $farbe ? $farbe : EK_STANDARDFARBE ); ?>">
			<?php echo ek_palette_datalist(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<p class="description">Farbe, in der Termine dieser Kategorie im Kalender erscheinen. Die Farben deines Themes werden im Farbwähler als Vorschläge angezeigt.</p>
		</td>
	</tr>
	<?php
}
add_action( 'ek_kategorie_edit_form_fields', __NAMESPACE__ . '\\ek_kategorie_feld_bearbeiten' );

/* Die Nonce prüft WordPress selbst, bevor diese Hooks ausgelöst werden. */
function ek_kategorie_farbe_speichern( $term_id ) {
	if ( ! current_user_can( 'manage_categories' ) || ! isset( $_POST['ek_farbe'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}
	$farbe = sanitize_hex_color( sanitize_text_field( wp_unslash( $_POST['ek_farbe'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $farbe ) {
		update_term_meta( $term_id, 'ek_farbe', $farbe );
	} else {
		delete_term_meta( $term_id, 'ek_farbe' );
	}
}
add_action( 'created_ek_kategorie', __NAMESPACE__ . '\\ek_kategorie_farbe_speichern' );
add_action( 'edited_ek_kategorie', __NAMESPACE__ . '\\ek_kategorie_farbe_speichern' );

function ek_kategorie_spalten( $spalten ) {
	$spalten['ek_farbe'] = 'Farbe';
	return $spalten;
}
add_filter( 'manage_edit-ek_kategorie_columns', __NAMESPACE__ . '\\ek_kategorie_spalten' );

function ek_kategorie_spalten_inhalt( $inhalt, $spalte, $term_id ) {
	if ( 'ek_farbe' === $spalte ) {
		$farbe = ek_kategorie_farbe( $term_id );
		if ( $farbe ) {
			$inhalt = '<span style="display:inline-block;width:1.2em;height:1.2em;border-radius:3px;vertical-align:middle;background:' . esc_attr( $farbe ) . '"></span> ' . esc_html( $farbe );
		}
	}
	return $inhalt;
}
add_filter( 'manage_ek_kategorie_custom_column', __NAMESPACE__ . '\\ek_kategorie_spalten_inhalt', 10, 3 );

/* -------------------------------------------------------------------------
 * 5. Theme-Farben und Einstellungsseite
 * ---------------------------------------------------------------------- */

const EK_STANDARDFARBE = '#2563eb';

/**
 * Farbpalette des aktiven Themes (theme.json bzw. editor-color-palette)
 * plus selbst angelegte Farben aus dem Website-Editor.
 * Rückgabe: slug => array( name, color, var )
 */
function ek_theme_palette() {
	if ( ! function_exists( 'wp_get_global_settings' ) ) {
		return array();
	}
	$palette = wp_get_global_settings( array( 'color', 'palette' ) );
	if ( ! is_array( $palette ) ) {
		return array();
	}
	if ( isset( $palette['theme'] ) || isset( $palette['custom'] ) || isset( $palette['default'] ) ) {
		$palette = array_merge(
			isset( $palette['theme'] ) ? $palette['theme'] : array(),
			isset( $palette['custom'] ) ? $palette['custom'] : array()
		);
	}

	$farben = array();
	foreach ( $palette as $f ) {
		if ( empty( $f['slug'] ) || empty( $f['color'] ) ) {
			continue;
		}
		$slug            = sanitize_key( $f['slug'] );
		$kebab           = function_exists( '_wp_to_kebab_case' ) ? _wp_to_kebab_case( $f['slug'] ) : $slug;
		$farben[ $slug ] = array(
			'name'  => isset( $f['name'] ) ? $f['name'] : $f['slug'],
			'color' => preg_replace( '/[^#a-zA-Z0-9(),.%\s-]/', '', $f['color'] ),
			'var'   => '--wp--preset--color--' . sanitize_key( $kebab ),
		);
	}
	return $farben;
}

/** Gespeicherte Einstellungen mit Standardwerten. */
function ek_einstellungen() {
	return wp_parse_args(
		(array) get_option( 'ek_einstellungen', array() ),
		array(
			'akzent'       => 'auto',
			'eigene_farbe' => EK_STANDARDFARBE,
		)
	);
}

/**
 * Ermittelt Akzentfarbe, farbige Fläche und Schriftfarbe des Kalenders.
 */
function ek_akzentfarbe() {
	$e    = ek_einstellungen();
	$wahl = $e['akzent'];

	if ( 'auto' === $wahl ) {
		return array( 'var(--accent)', 'var(--button-bg)', 'var(--on-accent)' );
	}

	if ( 'eigen' === $wahl ) {
		$hex = sanitize_hex_color( $e['eigene_farbe'] );
		$hex = $hex ? $hex : EK_STANDARDFARBE;
		return array( $hex, $hex, ek_text_auf( $hex ) );
	}

	if ( 0 === strpos( $wahl, 'theme:' ) ) {
		$farbe = ek_theme_palette()[ substr( $wahl, 6 ) ] ?? null;
		if ( $farbe ) {
			$css = 'var(' . $farbe['var'] . ', ' . $farbe['color'] . ')';
			return array( $css, $css, ek_text_auf( $farbe['color'] ) );
		}
	}

	return array( EK_STANDARDFARBE, EK_STANDARDFARBE, ek_text_auf( EK_STANDARDFARBE ) );
}

function ek_einstellungen_menue() {
	add_submenu_page(
		'edit.php?post_type=ek_termin',
		'Kalender-Einstellungen',
		'Einstellungen',
		'manage_options',
		'ek-einstellungen',
		__NAMESPACE__ . '\\ek_einstellungen_seite'
	);
}
add_action( 'admin_menu', __NAMESPACE__ . '\\ek_einstellungen_menue' );

function ek_einstellungen_registrieren() {
	register_setting(
		'ek_einstellungen',
		'ek_einstellungen',
		array(
			'type'              => 'array',
			'sanitize_callback' => __NAMESPACE__ . '\\ek_einstellungen_bereinigen',
		)
	);
}
add_action( 'admin_init', __NAMESPACE__ . '\\ek_einstellungen_registrieren' );

function ek_einstellungen_bereinigen( $eingabe ) {
	$eingabe = (array) $eingabe;
	$akzent  = isset( $eingabe['akzent'] ) ? sanitize_text_field( $eingabe['akzent'] ) : 'auto';
	$erlaubt = array( 'auto', 'eigen', 'standard' );
	if ( ! in_array( $akzent, $erlaubt, true ) && ! ( 0 === strpos( $akzent, 'theme:' ) && isset( ek_theme_palette()[ substr( $akzent, 6 ) ] ) ) ) {
		$akzent = 'auto';
	}
	$farbe = isset( $eingabe['eigene_farbe'] ) ? sanitize_hex_color( $eingabe['eigene_farbe'] ) : '';
	return array(
		'akzent'       => $akzent,
		'eigene_farbe' => $farbe ? $farbe : EK_STANDARDFARBE,
	);
}

/** Kleines Farbfeld für die Einstellungsseite. */
function ek_farbfeld( $css ) {
	return '<span style="display:inline-block;width:1.4em;height:1.4em;border-radius:4px;vertical-align:middle;margin-right:.4em;border:1px solid rgba(0,0,0,.15);background:' . esc_attr( $css ) . '"></span>';
}

function ek_einstellungen_seite() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$e        = ek_einstellungen();
	$palette  = ek_theme_palette();
	$optionen = 'ek_einstellungen[akzent]';
	?>
	<div class="wrap">
		<h1>Kalender-Einstellungen</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'ek_einstellungen' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Akzentfarbe</th>
					<td>
						<fieldset>
							<p class="description" style="margin-bottom:1em">
								Für Navigation, „Heute“-Markierung und Termine ohne Kategorie.
								Schrift, Hintergrund und Rahmen übernimmt der Kalender immer automatisch vom Theme.
							</p>

							<label style="display:block;margin-bottom:.6em">
								<input type="radio" name="<?php echo esc_attr( $optionen ); ?>" value="auto" <?php checked( $e['akzent'], 'auto' ); ?>>
								<strong>Automatisch vom Theme</strong>
								– übernimmt das gewählte Farbschema im Hell- und Dunkelmodus.
							</label>

							<?php if ( $palette ) : ?>
								<p style="margin:1em 0 .4em"><strong>Farbe aus der Theme-Palette:</strong></p>
								<?php foreach ( $palette as $slug => $f ) : ?>
									<label style="display:inline-block;min-width:13em;margin:0 1em .5em 0">
										<input type="radio" name="<?php echo esc_attr( $optionen ); ?>" value="<?php echo esc_attr( 'theme:' . $slug ); ?>" <?php checked( $e['akzent'], 'theme:' . $slug ); ?>>
										<?php echo ek_farbfeld( $f['color'] ) . esc_html( $f['name'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
									</label>
								<?php endforeach; ?>
							<?php else : ?>
								<p><em>Dieses Theme stellt keine Farbpalette bereit.</em></p>
							<?php endif; ?>

							<p style="margin:1em 0 .4em"><strong>Oder:</strong></p>
							<label style="display:block;margin-bottom:.6em">
								<input type="radio" name="<?php echo esc_attr( $optionen ); ?>" value="eigen" <?php checked( $e['akzent'], 'eigen' ); ?>>
								Eigene Farbe
								<input type="color" name="ek_einstellungen[eigene_farbe]" value="<?php echo esc_attr( $e['eigene_farbe'] ); ?>" style="vertical-align:middle;margin-left:.5em">
							</label>
							<label style="display:block">
								<input type="radio" name="<?php echo esc_attr( $optionen ); ?>" value="standard" <?php checked( $e['akzent'], 'standard' ); ?>>
								<?php echo ek_farbfeld( EK_STANDARDFARBE ); // phpcs:ignore WordPress.Security.EscapeOutput ?>Kalender-Standard (Blau)
							</label>
						</fieldset>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/** Theme-Farben als Vorschläge im Farbwähler der Kategorien. */
function ek_palette_datalist() {
	$html = '<datalist id="ek-theme-farben">';
	foreach ( ek_theme_palette() as $f ) {
		$hex = sanitize_hex_color( $f['color'] );
		if ( $hex ) {
			$html .= '<option value="' . esc_attr( strtolower( $hex ) ) . '" label="' . esc_attr( $f['name'] ) . '"></option>';
		}
	}
	return $html . '</datalist>';
}

/* -------------------------------------------------------------------------
 * 6. Frontend: Kalender-Shortcode
 * ---------------------------------------------------------------------- */

function ek_styles_registrieren() {
	wp_register_style( 'ek-kalender', get_theme_file_uri( 'assets/css/calendar.css' ), array( 'dunkel-style' ), DUNKEL_VERSION );

	list( $akzent, $flaeche, $text ) = ek_akzentfarbe();
	wp_add_inline_style(
		'ek-kalender',
		'.ek-kalender,.ek-liste,.ek-details{--ek-akzent:' . $akzent . ';--ek-akzent-flaeche:' . $flaeche . ';--ek-akzent-text:' . $text . ';}'
	);
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\ek_styles_registrieren' );

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

			$inhalt  = '<span class="ek-nr">' . esc_html( $datum->format( 'j' ) ) . '</span>';
			$inhalt .= '<span class="ek-datum-mobil">' . esc_html( $wochentage[ (int) $datum->format( 'N' ) - 1 ] . ', ' . $datum->format( 'j.n.' ) ) . '</span>';

			foreach ( $termine as $eintrag ) {
				$d       = $eintrag['daten'];
				$info    = ek_termin_zeitraum( $d ) . ( $d['ort'] ? ' · ' . $d['ort'] : '' );
				$inhalt .= '<a class="ek-termin" href="' . esc_url( get_permalink( $eintrag['post'] ) ) . '" title="' . esc_attr( $info ) . '"' . ek_farb_style( ek_termin_farbe( $d ) ) . '>';
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
function ek_ansicht_liste( $termine ) {
	if ( ! $termine ) {
		return '';
	}
	$html = '<ul class="ek-liste ek-liste-ansicht">';
	foreach ( $termine as $eintrag ) {
		$html .= ek_listeneintrag( $eintrag );
	}
	return $html . '</ul>';
}

/** Gruppiert die Termine des Jahres nach ihrem ersten Tag im Jahr. */
function ek_jahresliste( $termine, $jahresbeginn ) {
	$monate = array();
	foreach ( $termine as $eintrag ) {
		$monat             = substr( max( $eintrag['daten']['start'], $jahresbeginn ), 0, 7 );
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
function ek_listeneintrag( $eintrag ) {
	$d     = $eintrag['daten'];
	$html  = '<li class="ek-listeneintrag"' . ek_farb_style( ek_termin_farbe( $d ) ) . '>';
	$html .= '<span class="ek-listendatum">' . esc_html( ek_termin_zeitraum( $d ) ) . '</span>';
	$html .= '<a class="ek-listentitel" href="' . esc_url( get_permalink( $eintrag['post'] ) ) . '">' . esc_html( get_the_title( $eintrag['post'] ) ) . '</a>';
	if ( $d['ort'] || $d['kategorien'] ) {
		$html .= '<span class="ek-listenmeta">';
		if ( $d['ort'] ) {
			$html .= '<span class="ek-listenort">' . esc_html( $d['ort'] ) . '</span>';
		}
		$html .= ek_kategorie_badges( $d ) . '</span>';
	}
	return $html . '</li>';
}

/** Auswahlfeld für Kategorien (GET-Formular, behält die übrigen Parameter). */
function ek_kategorie_filter( $aktiv ) {
	$terms = get_terms(
		array(
			'taxonomy'   => 'ek_kategorie',
			'hide_empty' => true,
		)
	);
	if ( ! $terms || is_wp_error( $terms ) ) {
		return '';
	}

	$html = '<form class="ek-filter" method="get" action="#ek-kalender">';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reine Anzeige-Parameter
	foreach ( $_GET as $key => $wert ) {
		if ( 'ek_kat' === $key || ! is_scalar( $wert ) ) {
			continue;
		}
		$html .= '<input type="hidden" name="' . esc_attr( sanitize_key( $key ) ) . '" value="' . esc_attr( sanitize_text_field( wp_unslash( $wert ) ) ) . '">';
	}
	$html .= '<label class="screen-reader-text" for="ek-kat-auswahl">Kategorie</label>';
	$html .= '<select id="ek-kat-auswahl" name="ek_kat" onchange="this.form.submit()">';
	$html .= '<option value="">Alle Kategorien</option>';
	foreach ( $terms as $term ) {
		$html .= '<option value="' . esc_attr( $term->slug ) . '"' . selected( $aktiv, $term->slug, false ) . '>' . esc_html( $term->name ) . '</option>';
	}
	$html .= '</select><noscript><button type="submit">Filtern</button></noscript></form>';
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

/**
 * Shortcode [einfacher_kalender]
 *
 * Attribute:
 *   ansicht   = monat | liste | jahr   Startansicht (Standard: monat)
 *   auswahl   = ja | nein              Umschalter für die Ansichten zeigen (Standard: ja)
 *   kategorie = slug[,slug]            nur diese Kategorien zeigen (blendet den Filter aus)
 *   filter    = ja | nein              Kategorie-Auswahl zeigen (Standard: ja)
 *
 * URL-Parameter: ek_ansicht, ek_monat (JJJJ-MM), ek_kat (Slug).
 */
function ek_shortcode_kalender( $atts ) {
	wp_enqueue_style( 'ek-kalender' );

	$atts = shortcode_atts(
		array(
			'ansicht'   => 'monat',
			'auswahl'   => 'ja',
			'kategorie' => '',
			'filter'    => 'ja',
		),
		$atts,
		'einfacher_kalender'
	);

	$ansichten = array(
		'liste' => 'Liste',
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
	$kat_auswahl      = '';
	if ( ! $feste_kategorien && isset( $_GET['ek_kat'] ) ) {
		$kat_auswahl = implode( '', ek_kategorien_parsen( sanitize_title( wp_unslash( $_GET['ek_kat'] ) ) ) );
	}
	$kategorien = $feste_kategorien ? $feste_kategorien : ( $kat_auswahl ? array( $kat_auswahl ) : array() );

	$tz     = wp_timezone();
	$heute  = new DateTimeImmutable( 'now', $tz );
	$param  = isset( $_GET['ek_monat'] ) ? sanitize_text_field( wp_unslash( $_GET['ek_monat'] ) ) : '';
	$erster = DateTimeImmutable::createFromFormat( '!Y-m', $param, $tz );
	// phpcs:enable
	if ( ! $erster || $erster->format( 'Y-m' ) !== $param ) {
		$erster = $heute->modify( 'first day of this month' )->setTime( 0, 0 );
	}
	$heute_ymd = $heute->format( 'Y-m-d' );

	// Monatsansicht blättert monatsweise, Listen- und Jahresansicht jahresweise.
	if ( 'monat' === $ansicht ) {
		$von     = $erster->format( 'Y-m-d' );
		$bis     = $erster->modify( 'last day of this month' )->format( 'Y-m-d' );
		$titel   = wp_date( 'F Y', $erster->getTimestamp() );
		$schritt = '1 month';
	} else {
		$titel   = $erster->format( 'Y' );
		$von     = $titel . '-01-01';
		$bis     = $titel . '-12-31';
		$schritt = '12 months';
	}

	$termine          = ek_termine_holen( $von, $bis, -1, $kategorien );
	$naechste_termine = 'monat' === $ansicht ? ek_termine_holen( $heute_ymd, '', 5, $kategorien ) : array();

	if ( 'liste' !== $ansicht ) {
		$pro_tag   = ek_termine_pro_tag( $termine, $von, $bis );
		$url_heute = ek_url( array( 'ek_monat' => false ) );
	}
	$url_zurueck = ek_url( array( 'ek_monat' => $erster->modify( '-' . $schritt )->format( 'Y-m' ) ) );
	$url_vor     = ek_url( array( 'ek_monat' => $erster->modify( '+' . $schritt )->format( 'Y-m' ) ) );

	ob_start();
	?>
	<div class="ek-kalender ek-ansicht-<?php echo esc_attr( $ansicht ); ?>" id="ek-kalender">
		<div class="ek-kopf">
			<div class="ek-blaettern">
				<a class="ek-nav" href="<?php echo esc_url( $url_zurueck ); ?>" aria-label="<?php echo 'monat' === $ansicht ? 'Vorheriger Monat' : 'Vorheriges Jahr'; ?>">&#8249;</a>
				<h2 class="ek-titel"><?php echo esc_html( $titel ); ?></h2>
				<a class="ek-nav" href="<?php echo esc_url( $url_vor ); ?>" aria-label="<?php echo 'monat' === $ansicht ? 'Nächster Monat' : 'Nächstes Jahr'; ?>">&#8250;</a>
				<?php if ( 'liste' !== $ansicht ) : ?>
					<a class="ek-heute-link" href="<?php echo esc_url( $url_heute ); ?>">Heute</a>
				<?php endif; ?>
			</div>
			<div class="ek-werkzeuge">
				<?php
				if ( ! $feste_kategorien && 'nein' !== strtolower( $atts['filter'] ) ) {
					echo ek_kategorie_filter( $kat_auswahl ); // phpcs:ignore WordPress.Security.EscapeOutput -- intern escaped
				}
				if ( $auswahl ) :
					?>
					<nav class="ek-ansichten" aria-label="Ansicht wählen">
						<?php foreach ( $ansichten as $key => $label ) : ?>
							<a href="<?php echo esc_url( ek_url( array( 'ek_ansicht' => $key ) ) ); ?>" class="<?php echo $key === $ansicht ? 'ek-aktiv' : ''; ?>" <?php echo $key === $ansicht ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
						<?php endforeach; ?>
					</nav>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( 'monat' === $ansicht ) : ?>
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
			<p class="ek-keine"><?php echo 'monat' === $ansicht ? 'Keine Termine in diesem Monat.' : 'Keine Termine in diesem Jahr.'; ?></p>
		<?php endif; ?>

		<?php
		// phpcs:disable WordPress.Security.EscapeOutput -- die Ansichten escapen intern
		if ( 'jahr' === $ansicht ) {
			echo ek_ansicht_jahr( (int) $erster->format( 'Y' ), $pro_tag, $heute_ymd );
		} elseif ( 'liste' === $ansicht ) {
			echo ek_jahresliste( $termine, $von );
		} else {
			echo ek_ansicht_monat( $erster, $pro_tag, $heute_ymd );
		}
		echo ek_legende( $feste_kategorien );
		// phpcs:enable
		?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'einfacher_kalender', __NAMESPACE__ . '\\ek_shortcode_kalender' );

/**
 * Shortcode [termine_liste anzahl="5" kategorie="slug"]
 * Zeigt die nächsten anstehenden Termine als Liste.
 */
function ek_shortcode_liste( $atts ) {
	wp_enqueue_style( 'ek-kalender' );
	$atts = shortcode_atts(
		array(
			'anzahl'    => 5,
			'kategorie' => '',
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
		$html .= ek_listeneintrag( $eintrag );
	}
	return $html . '</ul>';
}
add_shortcode( 'termine_liste', __NAMESPACE__ . '\\ek_shortcode_liste' );

/* Auf der Einzelseite eines Termins Datum, Uhrzeit, Ort und Kategorien anzeigen. */
function ek_inhalt_ergaenzen( $inhalt ) {
	if ( ! is_singular( 'ek_termin' ) || ! in_the_loop() || ! is_main_query() ) {
		return $inhalt;
	}
	$d = ek_termin_daten( get_the_ID() );
	if ( ! $d['start'] ) {
		return $inhalt;
	}
	wp_enqueue_style( 'ek-kalender' );
	$box  = '<div class="ek-details"' . ek_farb_style( ek_termin_farbe( $d ) ) . '>';
	$box .= '<p><strong>Wann:</strong> ' . esc_html( ek_termin_zeitraum( $d ) ) . '</p>';
	if ( $d['ort'] ) {
		$box .= '<p><strong>Wo:</strong> ' . esc_html( $d['ort'] ) . '</p>';
	}
	if ( $d['kategorien'] ) {
		$box .= '<p><strong>Kategorie:</strong> ' . ek_kategorie_badges( $d ) . '</p>';
	}
	$box .= '</div>';
	return $box . $inhalt;
}
add_filter( 'the_content', __NAMESPACE__ . '\\ek_inhalt_ergaenzen' );
