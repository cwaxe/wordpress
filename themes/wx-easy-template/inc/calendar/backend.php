<?php
/**
 * Integrierter Kalender, basierend auf WX Easy Calendar 2.0.1 – Backend.
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

/* -------------------------------------------------------------------------
 * Einfacher Editor und Dateien
 * ---------------------------------------------------------------------- */

/** Für Termine den einfachen (klassischen) Editor verwenden. */
function ek_klassischer_editor( $nutzen, $post_type ) {
	return 'ek_termin' === $post_type ? false : $nutzen;
}
add_filter( 'use_block_editor_for_post_type', __NAMESPACE__ . '\\ek_klassischer_editor', 10, 2 );

function ek_admin_dateien( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen ) {
		return;
	}
	$unser = 'ek_termin' === $screen->post_type || in_array( $screen->taxonomy, array( 'ek_kategorie', 'ek_ort', 'ek_veranstalter' ), true );
	if ( ! $unser ) {
		return;
	}
	wp_enqueue_style( 'ek-admin', get_theme_file_uri( 'assets/css/calendar-admin.css' ), array(), DUNKEL_VERSION );

	if ( 'post' === $screen->base ) {
		$wochentage = array();
		for ( $t = 0; $t <= 6; $t++ ) {
			$wochentage[] = wp_date( 'l', ek_ts( '2024-01-07' ) + $t * DAY_IN_SECONDS + 12 * HOUR_IN_SECONDS, new DateTimeZone( 'UTC' ) );
		}
		wp_enqueue_script( 'ek-admin', get_theme_file_uri( 'assets/js/calendar-admin.js' ), array( 'jquery' ), DUNKEL_VERSION, true );
		wp_localize_script(
			'ek-admin',
			'ekAdmin',
			array(
				'wochentage' => $wochentage,
				'texte'      => array(
					'monatTag'     => 'jeden Monat am %d.',
					'monatNter'    => 'jeden Monat am %1$d. %2$s',
					'monatLetzter' => 'jeden Monat am letzten %s',
					'starttag'     => 'Tag des Beginns – immer dabei',
				),
			)
		);
	}
}
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\ek_admin_dateien' );

function ek_titel_platzhalter( $text, $post ) {
	return 'ek_termin' === $post->post_type ? 'Titel des Termins, z. B. „Sommerfest“' : $text;
}
add_filter( 'enter_title_here', __NAMESPACE__ . '\\ek_titel_platzhalter', 10, 2 );

/* -------------------------------------------------------------------------
 * Eingabemaske
 * ---------------------------------------------------------------------- */

/** Box „Wann? Wo? Wer?“ direkt unter dem Titel. */
function ek_eingabe_box( $post ) {
	if ( 'ek_termin' !== $post->post_type ) {
		return;
	}

	$d      = ek_termin_daten( $post->ID );
	$neu    = '' === $d['start'];
	$regel  = $d['regel'];
	$ganz   = ! $neu && '' === $d['zeit'];
	$wdh    = '';
	if ( $regel ) {
		$wdh = ( 'weekly' === $regel['freq'] && 2 === (int) $regel['interval'] ) ? 'weekly2' : $regel['freq'];
	}
	$tage   = $regel && 'weekly' === $regel['freq'] ? ek_regel_tage( $regel, $d['start'] ) : array( $d['start'] ? (int) gmdate( 'w', ek_ts( $d['start'] ) ) : -1 );
	$aus    = $regel ? implode( "\n", array_map( __NAMESPACE__ . '\ek_datum_ausgeben_kurz', $regel['exdates'] ) ) : '';

	$ort_term = ek_erster_begriff( $post->ID, 'ek_ort' );
	$ort_frei = $ort_term ? '' : (string) get_post_meta( $post->ID, '_ek_ort', true );
	$org_term = ek_erster_begriff( $post->ID, 'ek_veranstalter' );

	$orte  = get_terms( array( 'taxonomy' => 'ek_ort', 'hide_empty' => false, 'orderby' => 'name' ) );
	$orgs  = get_terms( array( 'taxonomy' => 'ek_veranstalter', 'hide_empty' => false, 'orderby' => 'name' ) );

	wp_nonce_field( 'ek_termin_speichern', 'ek_nonce' );
	?>
	<div class="ek-box postbox">
		<div class="ek-box__teil">
			<h2 class="ek-box__titel">Wann?</h2>
			<div class="ek-raster">
				<div class="ek-feld">
					<label for="ek_start">Beginn</label>
					<div class="ek-zeile">
						<input type="date" id="ek_start" name="ek_start" value="<?php echo esc_attr( $d['start'] ); ?>" required>
						<input type="time" id="ek_zeit" name="ek_zeit" value="<?php echo esc_attr( $neu ? '19:00' : $d['zeit'] ); ?>" class="ek-uhrzeit" aria-label="Uhrzeit Beginn">
					</div>
				</div>
				<div class="ek-feld">
					<label for="ek_ende">Ende</label>
					<div class="ek-zeile">
						<input type="date" id="ek_ende" name="ek_ende" value="<?php echo esc_attr( $d['ende'] ); ?>">
						<input type="time" id="ek_zeit_ende" name="ek_zeit_ende" value="<?php echo esc_attr( $d['zeit_ende'] ); ?>" class="ek-uhrzeit" aria-label="Uhrzeit Ende">
					</div>
				</div>
				<div class="ek-feld ek-feld--haken">
					<label><input type="checkbox" id="ek_ganztags" name="ek_ganztags" value="1" <?php checked( $ganz ); ?>> Ganztägig</label>
				</div>
			</div>

			<div class="ek-raster ek-raster--serie">
				<div class="ek-feld">
					<label for="ek_wiederholen">Wiederholen</label>
					<select id="ek_wiederholen" name="ek_wiederholen">
						<option value="">nicht wiederholen</option>
						<option value="weekly" <?php selected( $wdh, 'weekly' ); ?>>jede Woche</option>
						<option value="weekly2" <?php selected( $wdh, 'weekly2' ); ?>>alle 2 Wochen</option>
						<option value="monthly_day" <?php selected( $wdh, 'monthly_day' ); ?>>jeden Monat am gleichen Tag</option>
						<option value="monthly_nth" <?php selected( $wdh, 'monthly_nth' ); ?>>jeden Monat am gleichen Wochentag</option>
						<option value="monthly_last" <?php selected( $wdh, 'monthly_last' ); ?>>jeden Monat am letzten Wochentag</option>
					</select>
				</div>
				<fieldset class="ek-feld ek-tage" <?php echo ( 'weekly' === $wdh || 'weekly2' === $wdh ) ? '' : 'hidden'; ?>>
					<legend>an diesen Tagen</legend>
					<div class="ek-tage__liste">
						<?php foreach ( array( 1, 2, 3, 4, 5, 6, 0 ) as $t ) : ?>
							<?php $name = wp_date( 'l', ek_ts( '2024-01-07' ) + $t * DAY_IN_SECONDS + 12 * HOUR_IN_SECONDS, new DateTimeZone( 'UTC' ) ); ?>
							<label class="ek-tag-wahl" title="<?php echo esc_attr( $name ); ?>">
								<input type="checkbox" name="ek_tage[]" value="<?php echo esc_attr( $t ); ?>" <?php checked( in_array( $t, $tage, true ) ); ?>>
								<span><?php echo esc_html( mb_substr( $name, 0, 2 ) ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>
				<div class="ek-feld ek-serie">
					<label for="ek_bis">bis einschließlich</label>
					<input type="date" id="ek_bis" name="ek_bis" value="<?php echo esc_attr( $regel ? $regel['until'] : '' ); ?>">
				</div>
				<div class="ek-feld ek-serie ek-feld--breit">
					<label for="ek_ausfall">Fällt aus am (ein Datum pro Zeile, z. B. 24.12.2026)</label>
					<textarea id="ek_ausfall" name="ek_ausfall" rows="2"><?php echo esc_textarea( $aus ); ?></textarea>
				</div>
			</div>
		</div>

		<div class="ek-box__teil">
			<h2 class="ek-box__titel">Wo?</h2>
			<div class="ek-feld">
				<label for="ek_ort_wahl">Ort</label>
				<select id="ek_ort_wahl" name="ek_ort_wahl" class="ek-auswahl" data-neu="ek-ort-neu" data-frei="ek-ort-frei">
					<option value="">— kein Ort —</option>
					<?php if ( ! is_wp_error( $orte ) ) : ?>
						<?php foreach ( $orte as $t ) : ?>
							<?php
							$stadt = get_term_meta( $t->term_id, 'ek_stadt', true );
							$label = $t->name . ( $stadt && false === stripos( $t->name, $stadt ) ? ' (' . $stadt . ')' : '' );
							?>
							<option value="<?php echo esc_attr( $t->term_id ); ?>" <?php selected( $ort_term && (int) $ort_term->term_id === (int) $t->term_id ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					<?php endif; ?>
					<option value="frei" <?php selected( '' !== $ort_frei ); ?>>Ort frei eingeben (nur für diesen Termin) …</option>
					<option value="neu">+ Neuen Ort in der Ortsliste anlegen …</option>
				</select>
			</div>
			<div class="ek-neu" id="ek-ort-frei" <?php echo '' !== $ort_frei ? '' : 'hidden'; ?>>
				<div class="ek-raster">
					<div class="ek-feld ek-feld--breit"><label for="ek_ort">Ort</label><input type="text" id="ek_ort" name="ek_ort" value="<?php echo esc_attr( $ort_frei ); ?>" placeholder="z. B. Sportplatz"></div>
					<div class="ek-feld ek-feld--breit"><label for="ek_ort_adresse">Adresse (optional, für den Kartenlink)</label><input type="text" id="ek_ort_adresse" name="ek_ort_adresse" value="<?php echo esc_attr( get_post_meta( $post->ID, '_ek_ort_adresse', true ) ); ?>" placeholder="z. B. Sportplatzweg 2, 12345 Musterstadt"></div>
				</div>
				<p class="description">Wird nur bei diesem Termin angezeigt und nicht in die Ortsliste übernommen.</p>
			</div>
			<div class="ek-neu" id="ek-ort-neu" hidden>
				<div class="ek-raster">
					<div class="ek-feld ek-feld--breit"><label for="ek_on_name">Name des Ortes</label><input type="text" id="ek_on_name" name="ek_ort_neu[name]" placeholder="z. B. Pfarrsaal"></div>
					<div class="ek-feld ek-feld--breit"><label for="ek_on_strasse">Straße und Hausnummer</label><input type="text" id="ek_on_strasse" name="ek_ort_neu[strasse]"></div>
					<div class="ek-feld"><label for="ek_on_plz">PLZ</label><input type="text" id="ek_on_plz" name="ek_ort_neu[plz]" inputmode="numeric" class="ek-kurz"></div>
					<div class="ek-feld"><label for="ek_on_stadt">Ort</label><input type="text" id="ek_on_stadt" name="ek_ort_neu[stadt]"></div>
				</div>
			</div>
		</div>

		<div class="ek-box__teil">
			<h2 class="ek-box__titel">Wer?</h2>
			<div class="ek-feld">
				<label for="ek_org_wahl">Veranstalter</label>
				<select id="ek_org_wahl" name="ek_org_wahl" class="ek-auswahl" data-neu="ek-org-neu">
					<option value="">— kein Veranstalter —</option>
					<?php if ( ! is_wp_error( $orgs ) ) : ?>
						<?php foreach ( $orgs as $t ) : ?>
							<option value="<?php echo esc_attr( $t->term_id ); ?>" <?php selected( $org_term && (int) $org_term->term_id === (int) $t->term_id ); ?>><?php echo esc_html( $t->name ); ?></option>
						<?php endforeach; ?>
					<?php endif; ?>
					<option value="neu">+ Neuen Veranstalter anlegen …</option>
				</select>
			</div>
			<div class="ek-neu" id="ek-org-neu" hidden>
				<div class="ek-raster">
					<div class="ek-feld ek-feld--breit"><label for="ek_vn_name">Name</label><input type="text" id="ek_vn_name" name="ek_org_neu[name]" placeholder="z. B. Musikverein"></div>
					<div class="ek-feld"><label for="ek_vn_tel">Telefon</label><input type="tel" id="ek_vn_tel" name="ek_org_neu[telefon]"></div>
					<div class="ek-feld"><label for="ek_vn_mail">E-Mail</label><input type="email" id="ek_vn_mail" name="ek_org_neu[email]"></div>
					<div class="ek-feld ek-feld--breit"><label for="ek_vn_web">Webseite</label><input type="url" id="ek_vn_web" name="ek_org_neu[web]" placeholder="https://"></div>
				</div>
			</div>
		</div>
	</div>
	<h2 class="ek-beschreibung-titel">Beschreibung</h2>
	<?php
}
add_action( 'edit_form_after_title', __NAMESPACE__ . '\\ek_eingabe_box' );

/** JJJJ-MM-TT → TT.MM.JJJJ */
function ek_datum_ausgeben_kurz( $ymd ) {
	$p = explode( '-', (string) $ymd );
	return 3 === count( $p ) ? $p[2] . '.' . $p[1] . '.' . $p[0] : (string) $ymd;
}

function ek_meta_speichern( $post_id ) {
	if ( ! isset( $_POST['ek_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ek_nonce'] ) ), 'ek_termin_speichern' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- oben geprüft
	$feld = function ( $name ) {
		return isset( $_POST[ $name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) : '';
	};

	$start = ek_datum_pruefen( $feld( 'ek_start' ) );
	if ( ! $start ) {
		$start = current_time( 'Y-m-d' );
		set_transient( 'ek_hinweis_' . get_current_user_id(), 'kein_datum', 60 );
	}
	$ende      = ek_datum_pruefen( $feld( 'ek_ende' ) );
	$ganztags  = ! empty( $_POST['ek_ganztags'] );
	$zeit      = $ganztags ? '' : ek_zeit_pruefen( $feld( 'ek_zeit' ) );
	$zeit_ende = $ganztags ? '' : ek_zeit_pruefen( $feld( 'ek_zeit_ende' ) );

	// Serie.
	$regel = null;
	$wdh   = $feld( 'ek_wiederholen' );
	if ( $wdh ) {
		$aus = array();
		foreach ( preg_split( '/[\s,;]+/', isset( $_POST['ek_ausfall'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ek_ausfall'] ) ) : '' ) as $zeile ) {
			$tag = ek_datum_lesen( $zeile );
			if ( $tag ) {
				$aus[] = $tag;
			}
		}
		$regel = array(
			'freq'     => 'weekly2' === $wdh ? 'weekly' : $wdh,
			'interval' => 'weekly2' === $wdh ? 2 : 1,
			'until'    => ek_datum_pruefen( $feld( 'ek_bis' ) ),
			'exdates'  => $aus,
			'days'     => isset( $_POST['ek_tage'] ) && is_array( $_POST['ek_tage'] ) ? array_map( 'intval', wp_unslash( $_POST['ek_tage'] ) ) : array(),
		);
	}

	ek_termin_speichern( $post_id, $start, $ende, $zeit, $zeit_ende, $regel );

	// Ort.
	$ort_wahl = $feld( 'ek_ort_wahl' );
	if ( 'frei' === $ort_wahl ) {
		update_post_meta( $post_id, '_ek_ort', $feld( 'ek_ort' ) );
		update_post_meta( $post_id, '_ek_ort_adresse', $feld( 'ek_ort_adresse' ) );
		wp_set_object_terms( $post_id, array(), 'ek_ort' );
	} else {
		delete_post_meta( $post_id, '_ek_ort' );
		delete_post_meta( $post_id, '_ek_ort_adresse' );
		ek_begriff_waehlen(
			$post_id,
			'ek_ort',
			$ort_wahl,
			isset( $_POST['ek_ort_neu'] ) && is_array( $_POST['ek_ort_neu'] ) ? wp_unslash( $_POST['ek_ort_neu'] ) : array(), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			array(
				'strasse' => 'ek_strasse',
				'plz'     => 'ek_plz',
				'stadt'   => 'ek_stadt',
			)
		);
	}

	// Veranstalter.
	ek_begriff_waehlen(
		$post_id,
		'ek_veranstalter',
		$feld( 'ek_org_wahl' ),
		isset( $_POST['ek_org_neu'] ) && is_array( $_POST['ek_org_neu'] ) ? wp_unslash( $_POST['ek_org_neu'] ) : array(), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		array(
			'telefon' => 'ek_telefon',
			'email'   => 'ek_email',
			'web'     => 'ek_web',
		)
	);
	// phpcs:enable
}
add_action( 'save_post_ek_termin', __NAMESPACE__ . '\\ek_meta_speichern' );

/** Ort/Veranstalter: vorhandenen wählen, neu anlegen oder entfernen. */
function ek_begriff_waehlen( $post_id, $tax, $wahl, $neu, $felder ) {
	if ( 'neu' === $wahl ) {
		$name = isset( $neu['name'] ) ? sanitize_text_field( $neu['name'] ) : '';
		if ( '' === $name ) {
			return;
		}
		$gibt = term_exists( $name, $tax );
		if ( $gibt ) {
			$term_id = (int) $gibt['term_id'];
		} else {
			$angelegt = wp_insert_term( $name, $tax );
			if ( is_wp_error( $angelegt ) ) {
				return;
			}
			$term_id = (int) $angelegt['term_id'];
		}
		foreach ( $felder as $feld => $meta ) {
			if ( ! empty( $neu[ $feld ] ) ) {
				update_term_meta( $term_id, $meta, ek_meta_bereinigen( $meta, $neu[ $feld ] ) );
			}
		}
		wp_set_object_terms( $post_id, array( $term_id ), $tax );
		return;
	}
	$id = absint( $wahl );
	wp_set_object_terms( $post_id, $id ? array( $id ) : array(), $tax );
}

function ek_meta_bereinigen( $meta, $wert ) {
	$wert = (string) $wert;
	if ( 'ek_email' === $meta ) {
		return sanitize_email( $wert );
	}
	if ( 'ek_web' === $meta ) {
		return esc_url_raw( $wert );
	}
	return sanitize_text_field( $wert );
}

function ek_hinweise() {
	$key = 'ek_hinweis_' . get_current_user_id();
	if ( 'kein_datum' === get_transient( $key ) ) {
		delete_transient( $key );
		echo '<div class="notice notice-warning"><p>Es war kein Datum angegeben. Der Termin wurde auf heute gesetzt – bitte das Datum prüfen.</p></div>';
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['ek_kopiert'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>Kopie angelegt. Bitte Datum anpassen und veröffentlichen.</p></div>';
	}
}
add_action( 'admin_notices', __NAMESPACE__ . '\\ek_hinweise' );

/* -------------------------------------------------------------------------
 * Kopieren
 * ---------------------------------------------------------------------- */

function ek_kopier_url( $post_id ) {
	return wp_nonce_url( admin_url( 'admin-post.php?action=ek_kopieren&post=' . $post_id ), 'ek_kopieren_' . $post_id );
}

function ek_kopier_knopf( $post ) {
	if ( 'ek_termin' !== $post->post_type || 'auto-draft' === $post->post_status ) {
		return;
	}
	echo '<div class="misc-pub-section ek-kopieren"><a class="button" href="' . esc_url( ek_kopier_url( $post->ID ) ) . '">Termin kopieren</a></div>';
}
add_action( 'post_submitbox_misc_actions', __NAMESPACE__ . '\\ek_kopier_knopf' );

function ek_zeilen_aktionen( $aktionen, $post ) {
	if ( 'ek_termin' === $post->post_type && current_user_can( 'edit_posts' ) ) {
		$aktionen['ek_kopieren'] = '<a href="' . esc_url( ek_kopier_url( $post->ID ) ) . '">Kopieren</a>';
	}
	return $aktionen;
}
add_filter( 'post_row_actions', __NAMESPACE__ . '\\ek_zeilen_aktionen', 10, 2 );

function ek_kopieren() {
	$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	check_admin_referer( 'ek_kopieren_' . $id );
	$post = get_post( $id );
	if ( ! $post || 'ek_termin' !== $post->post_type || ! current_user_can( 'edit_post', $id ) ) {
		wp_die( 'Keine Berechtigung.' );
	}

	$neu = wp_insert_post(
		wp_slash(
			array(
				'post_type'    => 'ek_termin',
				'post_status'  => 'draft',
				'post_title'   => $post->post_title,
				'post_content' => $post->post_content,
				'post_author'  => get_current_user_id(),
			)
		),
		true
	);
	if ( is_wp_error( $neu ) ) {
		wp_die( esc_html( $neu->get_error_message() ) );
	}

	$d = ek_termin_daten( $id );
	ek_termin_speichern( $neu, get_post_meta( $id, '_ek_start', true ), get_post_meta( $id, '_ek_ende', true ), $d['zeit'], $d['zeit_ende'], $d['regel'] );
	foreach ( array( '_ek_ort', '_ek_ort_adresse' ) as $key ) {
		$wert = get_post_meta( $id, $key, true );
		if ( '' !== $wert ) {
			update_post_meta( $neu, $key, $wert );
		}
	}
	$bild = get_post_thumbnail_id( $id );
	if ( $bild ) {
		set_post_thumbnail( $neu, $bild );
	}
	foreach ( array( 'ek_kategorie', 'ek_ort', 'ek_veranstalter' ) as $tax ) {
		$ids = wp_get_object_terms( $id, $tax, array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $ids ) ) {
			wp_set_object_terms( $neu, $ids, $tax );
		}
	}

	wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $neu . '&ek_kopiert=1' ) );
	exit;
}
add_action( 'admin_post_ek_kopieren', __NAMESPACE__ . '\\ek_kopieren' );

/* -------------------------------------------------------------------------
 * Terminliste: Spalten, Kommende/Vergangene, Sortierung
 * ---------------------------------------------------------------------- */

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
		if ( ! $d['start'] ) {
			echo '<em>kein Datum</em>';
			return;
		}
		echo esc_html( ek_termin_zeitraum( $d ) );
		if ( $d['regel'] ) {
			echo '<br><span class="ek-serie-badge">Serie</span> <small>' . esc_html( ek_regel_text( $d ) ) . '</small>';
		}
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

/** Aktueller Zeitfilter der Terminliste: kommend, vergangen oder alle. */
function ek_listen_zeit() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$zeit   = isset( $_GET['ek_zeit'] ) ? sanitize_key( wp_unslash( $_GET['ek_zeit'] ) ) : '';
	$status = isset( $_GET['post_status'] ) ? sanitize_key( wp_unslash( $_GET['post_status'] ) ) : '';
	// phpcs:enable
	if ( in_array( $zeit, array( 'kommend', 'vergangen', 'alle' ), true ) ) {
		return $zeit;
	}
	return $status ? 'alle' : 'kommend';
}

function ek_admin_sortierung( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || 'ek_termin' !== $query->get( 'post_type' ) ) {
		return;
	}
	global $pagenow;
	if ( 'edit.php' !== $pagenow ) {
		return;
	}

	$zeit    = ek_listen_zeit();
	$heute   = current_time( 'Y-m-d' );
	$klausel = array(
		'relation' => 'AND',
		'ek_start' => array(
			'key'     => '_ek_start',
			'compare' => 'EXISTS',
		),
	);
	if ( 'kommend' === $zeit ) {
		$klausel[] = array(
			'key'     => '_ek_letzter',
			'value'   => $heute,
			'compare' => '>=',
			'type'    => 'DATE',
		);
	} elseif ( 'vergangen' === $zeit ) {
		$klausel[] = array(
			'key'     => '_ek_letzter',
			'value'   => $heute,
			'compare' => '<',
			'type'    => 'DATE',
		);
	}
	$query->set( 'meta_query', $klausel );

	$orderby = $query->get( 'orderby' );
	if ( ! $orderby || 'ek_datum' === $orderby ) {
		$order = strtoupper( (string) $query->get( 'order' ) );
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) || ! isset( $_GET['order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order = 'kommend' === $zeit ? 'ASC' : 'DESC';
		}
		$query->set( 'orderby', array( 'ek_start' => $order ) );
	}
}
add_action( 'pre_get_posts', __NAMESPACE__ . '\\ek_admin_sortierung' );

/** Ansichten „Kommende | Vergangene | Alle“ statt „Alle“. */
function ek_listen_ansichten( $ansichten ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$status = isset( $_GET['post_status'] ) ? sanitize_key( wp_unslash( $_GET['post_status'] ) ) : '';
	$zeit   = ek_listen_zeit();
	$basis  = admin_url( 'edit.php?post_type=ek_termin' );
	$meine  = array();
	foreach ( array( 'kommend' => 'Kommende', 'vergangen' => 'Vergangene', 'alle' => 'Alle' ) as $key => $label ) {
		$meine[ 'ek_' . $key ] = sprintf(
			'<a href="%s"%s>%s</a>',
			esc_url( add_query_arg( 'ek_zeit', $key, $basis ) ),
			( $zeit === $key && '' === $status ) ? ' class="current" aria-current="page"' : '',
			esc_html( $label )
		);
	}
	unset( $ansichten['all'], $ansichten['publish'] );
	return array_merge( $meine, $ansichten );
}
add_filter( 'views_edit-ek_termin', __NAMESPACE__ . '\\ek_listen_ansichten' );

/* -------------------------------------------------------------------------
 * Felder für Orte und Veranstalter
 * ---------------------------------------------------------------------- */

function ek_begriff_felder() {
	return array(
		'ek_ort'          => array(
			'ek_strasse' => array( 'Straße und Hausnummer', 'text' ),
			'ek_plz'     => array( 'PLZ', 'text' ),
			'ek_stadt'   => array( 'Ort', 'text' ),
		),
		'ek_veranstalter' => array(
			'ek_telefon' => array( 'Telefon', 'tel' ),
			'ek_email'   => array( 'E-Mail', 'email' ),
			'ek_web'     => array( 'Webseite', 'url' ),
		),
	);
}

function ek_begriff_feld_neu( $tax ) {
	$alle = ek_begriff_felder();
	foreach ( $alle[ $tax ] as $key => $f ) {
		printf(
			'<div class="form-field"><label for="%1$s">%2$s</label><input type="%3$s" name="%1$s" id="%1$s" value=""></div>',
			esc_attr( $key ),
			esc_html( $f[0] ),
			esc_attr( $f[1] )
		);
	}
}

function ek_begriff_feld_bearbeiten( $term, $tax ) {
	$alle = ek_begriff_felder();
	foreach ( $alle[ $tax ] as $key => $f ) {
		printf(
			'<tr class="form-field"><th scope="row"><label for="%1$s">%2$s</label></th><td><input type="%3$s" name="%1$s" id="%1$s" value="%4$s"></td></tr>',
			esc_attr( $key ),
			esc_html( $f[0] ),
			esc_attr( $f[1] ),
			esc_attr( get_term_meta( $term->term_id, $key, true ) )
		);
	}
}

/* Die Nonce prüft WordPress selbst, bevor diese Hooks beim Formular ausgelöst werden. */
function ek_begriff_speichern( $term_id ) {
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	$term = get_term( $term_id );
	$alle = ek_begriff_felder();
	if ( ! $term || is_wp_error( $term ) || empty( $alle[ $term->taxonomy ] ) ) {
		return;
	}
	foreach ( array_keys( $alle[ $term->taxonomy ] ) as $key ) {
		if ( isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_term_meta( $term_id, $key, ek_meta_bereinigen( $key, wp_unslash( $_POST[ $key ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}
	}
}

function ek_begriff_spalten( $spalten ) {
	unset( $spalten['description'], $spalten['slug'] );
	$screen                = get_current_screen();
	$spalten['ek_info']    = ( $screen && 'ek_ort' === $screen->taxonomy ) ? 'Adresse' : 'Kontakt';
	if ( isset( $spalten['posts'] ) ) {
		unset( $spalten['posts'] );
		$spalten['posts'] = 'Termine';
	}
	return $spalten;
}

function ek_begriff_spalte( $inhalt, $spalte, $term_id ) {
	if ( 'ek_info' !== $spalte ) {
		return $inhalt;
	}
	$term = get_term( $term_id );
	if ( ! $term || is_wp_error( $term ) ) {
		return $inhalt;
	}
	if ( 'ek_ort' === $term->taxonomy ) {
		$teile = array( get_term_meta( $term_id, 'ek_strasse', true ), trim( get_term_meta( $term_id, 'ek_plz', true ) . ' ' . get_term_meta( $term_id, 'ek_stadt', true ) ) );
	} else {
		$teile = array( get_term_meta( $term_id, 'ek_telefon', true ), get_term_meta( $term_id, 'ek_email', true ) );
	}
	return esc_html( implode( ' · ', array_filter( $teile ) ) );
}

foreach ( array( 'ek_ort', 'ek_veranstalter' ) as $ek_tax ) {
	add_action( $ek_tax . '_add_form_fields', __NAMESPACE__ . '\\ek_begriff_feld_neu' );
	add_action( $ek_tax . '_edit_form_fields', __NAMESPACE__ . '\\ek_begriff_feld_bearbeiten', 10, 2 );
	add_action( 'created_' . $ek_tax, __NAMESPACE__ . '\\ek_begriff_speichern' );
	add_action( 'edited_' . $ek_tax, __NAMESPACE__ . '\\ek_begriff_speichern' );
	add_filter( 'manage_edit-' . $ek_tax . '_columns', __NAMESPACE__ . '\\ek_begriff_spalten' );
	add_filter( 'manage_' . $ek_tax . '_custom_column', __NAMESPACE__ . '\\ek_begriff_spalte', 10, 3 );
}
unset( $ek_tax );
