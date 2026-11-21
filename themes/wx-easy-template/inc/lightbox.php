<?php
/**
 * Integrierte Bildansicht, basierend auf WX Lightbox 1.1.0.
 * Lizenz: GPL-2.0-or-later.
 * @package Dunkel_Schlicht
 */

namespace DunkelSchlicht\Lightbox;

defined( 'ABSPATH' ) || exit;

const SBL_OPTION = 'sbl_settings';

/**
 * Standardwerte.
 */
function sbl_defaults() {
	return array(
		'blur'    => 8,         // Weichzeichner-Stärke in px.
		'opacity' => 35,        // Deckkraft der Abdunklung in %.
		'color'   => '#000000', // Farbe der Abdunklung.
		'auto'    => 1,         // Alle Links auf Bilddateien automatisch öffnen.
		'group'   => 1,         // Bilder einer Galerie / eines Beitrags gruppieren.
		'caption' => 1,         // Bildunterschriften anzeigen.
	);
}

function sbl_get_settings() {
	$saved = get_option( SBL_OPTION, array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), sbl_defaults() );
}

function sbl_hex_to_rgb( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( ! preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) ) {
		return array( 0, 0, 0 );
	}
	return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
}

/* -------------------------------------------------------------------------
 * Frontend
 * ---------------------------------------------------------------------- */

add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\sbl_enqueue_assets' );
function sbl_enqueue_assets() {
	/**
	 * Filter: Lightbox auf bestimmten Seiten deaktivieren.
	 * Beispiel: add_filter( 'sbl_enabled', fn() => ! is_page( 'kontakt' ) );
	 */
	if ( ! apply_filters( 'sbl_enabled', true ) ) {
		return;
	}

	$s = sbl_get_settings();

	wp_enqueue_style( 'soft-blur-lightbox', get_theme_file_uri( 'assets/css/lightbox.css' ), array(), DUNKEL_VERSION );

	list( $r, $g, $b ) = sbl_hex_to_rgb( $s['color'] );
	wp_add_inline_style(
		'soft-blur-lightbox',
		sprintf(
			':root{--sbl-blur:%dpx;--sbl-bg:rgba(%d,%d,%d,%s);}',
			(int) $s['blur'],
			$r,
			$g,
			$b,
			number_format( (int) $s['opacity'] / 100, 2, '.', '' )
		)
	);

	wp_enqueue_script(
		'soft-blur-lightbox',
		get_theme_file_uri( 'assets/js/lightbox.js' ),
		array(),
		DUNKEL_VERSION,
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);

	$data = array(
		'auto'    => (bool) $s['auto'],
		'group'   => (bool) $s['group'],
		'caption' => (bool) $s['caption'],
		'i18n'    => array(
			'dialog'  => __( 'Bildansicht', 'wx-easy-template' ),
			'close'   => __( 'Schließen (Esc)', 'wx-easy-template' ),
			'prev'    => __( 'Vorheriges Bild', 'wx-easy-template' ),
			'next'    => __( 'Nächstes Bild', 'wx-easy-template' ),
			/* translators: 1: aktuelles Bild, 2: Anzahl Bilder */
			'counter' => __( '%1$s / %2$s', 'wx-easy-template' ),
		),
	);

	wp_add_inline_script( 'soft-blur-lightbox', 'window.sblSettings = ' . wp_json_encode( $data ) . ';', 'before' );
}

/* -------------------------------------------------------------------------
 * Einstellungen
 * ---------------------------------------------------------------------- */

add_action( 'admin_init', __NAMESPACE__ . '\\sbl_register_settings' );
function sbl_register_settings() {
	register_setting(
		'sbl_settings_group',
		SBL_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => __NAMESPACE__ . '\\sbl_sanitize_settings',
			'default'           => sbl_defaults(),
		)
	);
}

function sbl_sanitize_settings( $input ) {
	$d     = sbl_defaults();
	$input = is_array( $input ) ? $input : array();
	$color = isset( $input['color'] ) ? sanitize_hex_color( $input['color'] ) : '';

	return array(
		'blur'    => min( 30, max( 0, absint( $input['blur'] ?? $d['blur'] ) ) ),
		'opacity' => min( 100, max( 0, absint( $input['opacity'] ?? $d['opacity'] ) ) ),
		'color'   => $color ? $color : $d['color'],
		'auto'    => empty( $input['auto'] ) ? 0 : 1,
		'group'   => empty( $input['group'] ) ? 0 : 1,
		'caption' => empty( $input['caption'] ) ? 0 : 1,
	);
}

add_action( 'admin_menu', __NAMESPACE__ . '\\sbl_add_settings_page' );
function sbl_add_settings_page() {
	add_options_page(
		__( 'WX Lightbox', 'wx-easy-template' ),
		__( 'Lightbox', 'wx-easy-template' ),
		'manage_options',
		'soft-blur-lightbox',
		__NAMESPACE__ . '\\sbl_render_settings_page'
	);
}

function sbl_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s    = sbl_get_settings();
	$name = SBL_OPTION;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'WX Lightbox', 'wx-easy-template' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'sbl_settings_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="sbl-blur"><?php esc_html_e( 'Weichzeichner-Stärke', 'wx-easy-template' ); ?></label></th>
					<td>
						<input type="range" id="sbl-blur" name="<?php echo esc_attr( $name ); ?>[blur]" min="0" max="30" step="1" value="<?php echo esc_attr( $s['blur'] ); ?>" oninput="this.nextElementSibling.value=this.value+' px'">
						<output><?php echo esc_html( $s['blur'] ); ?> px</output>
						<p class="description"><?php esc_html_e( '4–10 px wirkt „leicht verschwommen“, höhere Werte machen den Hintergrund unkenntlich.', 'wx-easy-template' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sbl-opacity"><?php esc_html_e( 'Abdunklung', 'wx-easy-template' ); ?></label></th>
					<td>
						<input type="range" id="sbl-opacity" name="<?php echo esc_attr( $name ); ?>[opacity]" min="0" max="100" step="5" value="<?php echo esc_attr( $s['opacity'] ); ?>" oninput="this.nextElementSibling.value=this.value+' %'">
						<output><?php echo esc_html( $s['opacity'] ); ?> %</output>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sbl-color"><?php esc_html_e( 'Farbe der Abdunklung', 'wx-easy-template' ); ?></label></th>
					<td><input type="color" id="sbl-color" name="<?php echo esc_attr( $name ); ?>[color]" value="<?php echo esc_attr( $s['color'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Verhalten', 'wx-easy-template' ); ?></th>
					<td>
						<fieldset>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[auto]" value="1" <?php checked( $s['auto'] ); ?>> <?php esc_html_e( 'Alle Links auf Bilddateien automatisch in der Lightbox öffnen', 'wx-easy-template' ); ?></label><br>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[group]" value="1" <?php checked( $s['group'] ); ?>> <?php esc_html_e( 'Bilder einer Galerie bzw. eines Beitrags durchblättern', 'wx-easy-template' ); ?></label><br>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[caption]" value="1" <?php checked( $s['caption'] ); ?>> <?php esc_html_e( 'Bildunterschriften anzeigen', 'wx-easy-template' ); ?></label>
						</fieldset>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<h2><?php esc_html_e( 'So funktioniert’s', 'wx-easy-template' ); ?></h2>
		<ul style="list-style:disc;padding-left:1.5em">
			<li><?php esc_html_e( 'Bild- oder Galerie-Block: „Link zu“ → „Mediendatei“ auswählen.', 'wx-easy-template' ); ?></li>
			<li><?php echo wp_kses_post( __( 'Einzelne Links manuell aktivieren: <code>data-lightbox</code> oder Klasse <code>lightbox</code>. Eigene Gruppe: <code>data-lightbox="reise"</code>.', 'wx-easy-template' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'Ausschließen: Klasse <code>no-lightbox</code> am Link oder an einem Eltern-Element.', 'wx-easy-template' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'Eigene Bildunterschrift: <code>data-caption="…"</code> am Link.', 'wx-easy-template' ) ); ?></li>
		</ul>
	</div>
	<?php
}
