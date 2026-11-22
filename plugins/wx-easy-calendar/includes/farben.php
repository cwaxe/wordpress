<?php
/**
 * Farben: Kategorie-Farben, Theme-Palette, Akzentfarbe und Einstellungsseite.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Backend: Farbe pro Kategorie
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
add_action( 'ek_kategorie_add_form_fields', 'ek_kategorie_feld_neu' );

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
add_action( 'ek_kategorie_edit_form_fields', 'ek_kategorie_feld_bearbeiten' );

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
add_action( 'created_ek_kategorie', 'ek_kategorie_farbe_speichern' );
add_action( 'edited_ek_kategorie', 'ek_kategorie_farbe_speichern' );

function ek_kategorie_spalten( $spalten ) {
	$spalten['ek_farbe'] = 'Farbe';
	return $spalten;
}
add_filter( 'manage_edit-ek_kategorie_columns', 'ek_kategorie_spalten' );

function ek_kategorie_spalten_inhalt( $inhalt, $spalte, $term_id ) {
	if ( 'ek_farbe' === $spalte ) {
		$farbe = ek_kategorie_farbe( $term_id );
		if ( $farbe ) {
			$inhalt = '<span style="display:inline-block;width:1.2em;height:1.2em;border-radius:3px;vertical-align:middle;background:' . esc_attr( $farbe ) . '"></span> ' . esc_html( $farbe );
		}
	}
	return $inhalt;
}
add_filter( 'manage_ek_kategorie_custom_column', 'ek_kategorie_spalten_inhalt', 10, 3 );

/* -------------------------------------------------------------------------
 * Theme-Farben und Einstellungsseite
 * ---------------------------------------------------------------------- */

define( 'EK_STANDARDFARBE', '#2563eb' );

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
			'akzent'           => 'auto',
			'eigene_farbe'     => EK_STANDARDFARBE,
			'tec_weiterleiten' => 1,
		)
	);
}

/** Welche Theme-Farbe "automatisch" gewählt wird (oder null). */
function ek_auto_slug( $palette ) {
	foreach ( array( 'primary', 'accent', 'accent-1', 'secondary', 'accent-2' ) as $slug ) {
		if ( isset( $palette[ $slug ] ) ) {
			return $slug;
		}
	}
	return null;
}

/**
 * Ermittelt die Akzentfarbe.
 * Rückgabe: array( CSS-Wert, Hex-Wert für die Kontrastberechnung )
 */
function ek_akzentfarbe() {
	$e       = ek_einstellungen();
	$palette = ek_theme_palette();
	$wahl    = $e['akzent'];

	if ( 'eigen' === $wahl ) {
		$hex = sanitize_hex_color( $e['eigene_farbe'] );
		$hex = $hex ? $hex : EK_STANDARDFARBE;
		return array( $hex, $hex );
	}

	$slug = null;
	if ( 'auto' === $wahl ) {
		$slug = ek_auto_slug( $palette );
	} elseif ( 0 === strpos( $wahl, 'theme:' ) ) {
		$slug = substr( $wahl, 6 );
	}

	if ( $slug && isset( $palette[ $slug ] ) ) {
		$f = $palette[ $slug ];
		// CSS-Variable des Themes – ändert sich automatisch mit, wenn das Theme seine Farbe ändert.
		return array( 'var(' . $f['var'] . ', ' . $f['color'] . ')', $f['color'] );
	}
	return array( EK_STANDARDFARBE, EK_STANDARDFARBE );
}

function ek_einstellungen_menue() {
	add_submenu_page(
		'edit.php?post_type=ek_termin',
		'Kalender-Einstellungen',
		'Einstellungen',
		'manage_options',
		'ek-einstellungen',
		'ek_einstellungen_seite'
	);
}
add_action( 'admin_menu', 'ek_einstellungen_menue' );

function ek_einstellungen_registrieren() {
	register_setting(
		'ek_einstellungen',
		'ek_einstellungen',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'ek_einstellungen_bereinigen',
		)
	);
}
add_action( 'admin_init', 'ek_einstellungen_registrieren' );

function ek_einstellungen_bereinigen( $eingabe ) {
	$eingabe = (array) $eingabe;
	$akzent  = isset( $eingabe['akzent'] ) ? sanitize_text_field( $eingabe['akzent'] ) : 'auto';
	$erlaubt = array( 'auto', 'eigen', 'standard' );
	if ( ! in_array( $akzent, $erlaubt, true ) && ! ( 0 === strpos( $akzent, 'theme:' ) && isset( ek_theme_palette()[ substr( $akzent, 6 ) ] ) ) ) {
		$akzent = 'auto';
	}
	$farbe = isset( $eingabe['eigene_farbe'] ) ? sanitize_hex_color( $eingabe['eigene_farbe'] ) : '';
	return array(
		'akzent'           => $akzent,
		'eigene_farbe'     => $farbe ? $farbe : EK_STANDARDFARBE,
		'tec_weiterleiten' => empty( $eingabe['tec_weiterleiten'] ) ? 0 : 1,
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
	$auto     = ek_auto_slug( $palette );
	$optionen = 'ek_einstellungen[akzent]';
	?>
	<div class="wrap">
		<h1>Kalender-Einstellungen</h1>
		<p><a href="<?php echo esc_url( EK_URL . 'docs/Kalender-Kurzanleitung.pdf' ); ?>" target="_blank" rel="noopener">Kurzanleitung für Redakteure (PDF)</a></p>
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
								<?php if ( $auto ) : ?>
									– derzeit <?php echo ek_farbfeld( $palette[ $auto ]['color'] ) . esc_html( $palette[ $auto ]['name'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								<?php else : ?>
									– <em>Das Theme hat keine Akzentfarbe festgelegt, es wird Blau verwendet.</em>
								<?php endif; ?>
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
								<?php echo ek_farbfeld( EK_STANDARDFARBE ); // phpcs:ignore WordPress.Security.EscapeOutput ?>Plugin-Standard (Blau)
							</label>
						</fieldset>
					</td>
				</tr>
				<?php if ( get_option( 'ek_tec_pfade' ) || ek_tec_bestand()['termine'] ) : ?>
					<tr>
						<th scope="row">Alte Adressen</th>
						<td>
							<label><input type="checkbox" name="ek_einstellungen[tec_weiterleiten]" value="1" <?php checked( (int) $e['tec_weiterleiten'], 1 ); ?>> Alte Links von The Events Calendar auf die neuen Seiten weiterleiten</label>
							<p class="description">Wirkt, sobald The Events Calendar deaktiviert ist. Alte Termin-Links führen dann zur neuen Terminseite, die alte Übersicht zur Kalenderseite und der alte iCal-Link zum Kalender-Abo.</p>
						</td>
					</tr>
				<?php else : ?>
					<input type="hidden" name="ek_einstellungen[tec_weiterleiten]" value="<?php echo (int) $e['tec_weiterleiten']; ?>">
				<?php endif; ?>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php ek_tec_abschnitt(); ?>
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

