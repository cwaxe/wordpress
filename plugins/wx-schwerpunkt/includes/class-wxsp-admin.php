<?php
/**
 * Einstellungsseite: Einstellungen → Schwerpunkt
 */

defined( 'ABSPATH' ) || exit;

final class WXSP_Admin {

	const PAGE = 'wx-schwerpunkt';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WXSP_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function menu() {
		add_options_page( 'Schwerpunkt', 'Schwerpunkt', 'manage_options', self::PAGE, array( __CLASS__, 'seite' ) );
	}

	public static function register() {
		register_setting(
			'wxsp',
			WXSP_Daten::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'WXSP_Daten', 'bereinigen' ),
				'default'           => WXSP_Daten::vorgabe(),
			)
		);
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">Einstellungen</a>' );
		return $links;
	}

	public static function assets( $hook ) {
		if ( 'settings_page_' . self::PAGE !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wxsp-admin', WXSP_URL . 'assets/css/admin.css', array(), WXSP_VERSION );
		wp_enqueue_script( 'wxsp-admin', WXSP_URL . 'assets/js/admin.js', array(), WXSP_VERSION, true );
	}

	private static function feld( $name, $wert, $label, $einheit = '', $typ = 'text', $hilfe = '' ) {
		$wert = is_float( $wert ) || is_int( $wert ) ? str_replace( '.', ',', (string) $wert ) : (string) $wert;
		printf(
			'<label class="wxsp-feld"><span>%s</span><span class="wxsp-wert"><input type="%s" name="%s" value="%s"%s>%s</span>%s</label>',
			esc_html( $label ),
			esc_attr( $typ ),
			esc_attr( $name ),
			esc_attr( $wert ),
			'text' === $typ && $einheit ? ' inputmode="decimal"' : '',
			$einheit ? '<em>' . esc_html( $einheit ) . '</em>' : '',
			$hilfe ? '<small>' . esc_html( $hilfe ) . '</small>' : ''
		);
	}

	private static function posten_zeile( $basis, $j, $p ) {
		$n = $basis . '[posten][' . $j . ']';
		$z = function ( $v ) {
			return '' === $v ? '' : str_replace( '.', ',', (string) ( 0 == $v ? '' : $v ) ); // phpcs:ignore WordPress.PHP.StrictComparisons
		};
		?>
		<tr>
			<td><input type="text" name="<?php echo esc_attr( $n . '[name]' ); ?>" value="<?php echo esc_attr( $p['name'] ); ?>" placeholder="z. B. Passagier"></td>
			<td><input type="text" inputmode="decimal" name="<?php echo esc_attr( $n . '[arm]' ); ?>" value="<?php echo esc_attr( str_replace( '.', ',', (string) $p['arm'] ) ); ?>"> <em>mm</em></td>
			<td><input type="text" inputmode="decimal" name="<?php echo esc_attr( $n . '[min]' ); ?>" value="<?php echo esc_attr( $z( $p['min'] ) ); ?>" placeholder="–"> <em>kg</em></td>
			<td><input type="text" inputmode="decimal" name="<?php echo esc_attr( $n . '[max]' ); ?>" value="<?php echo esc_attr( $z( $p['max'] ) ); ?>" placeholder="–"> <em>kg</em></td>
			<td class="wxsp-mitte"><input type="checkbox" name="<?php echo esc_attr( $n . '[pflicht]' ); ?>" value="1" <?php checked( ! empty( $p['pflicht'] ) ); ?> aria-label="Pflichtfeld"></td>
			<td><button type="button" class="button-link wxsp-zeile-weg" aria-label="Zeile entfernen">✕</button></td>
		</tr>
		<?php
	}

	private static function flugzeug( $i, $f, $vorlage = false ) {
		$b = WXSP_Daten::OPTION . '[' . $i . ']';
		?>
		<div class="wxsp-flugzeug postbox" <?php echo $vorlage ? 'data-vorlage' : ''; ?>>
			<div class="postbox-header"><h2><?php echo $f['name'] ? esc_html( $f['name'] ) : 'Neues Flugzeug'; ?></h2></div>
			<div class="inside">
				<?php if ( $f['id'] ) : ?>
					<p class="wxsp-shortcode">Shortcode: <code>[schwerpunkt flugzeug="<?php echo esc_html( $f['id'] ); ?>"]</code><?php echo 0 === $i ? ' – oder kurz <code>[schwerpunkt]</code> (erstes Flugzeug)' : ''; ?></p>
				<?php endif; ?>

				<h3>Flugzeug</h3>
				<div class="wxsp-raster">
					<?php
					self::feld( $b . '[name]', $f['name'], 'Name / Kennzeichen', '', 'text', 'z. B. Bristell D-MDMP' );
					self::feld( $b . '[id]', $f['id'], 'Kurzname für den Shortcode', '', 'text', 'leer lassen = aus dem Namen' );
					self::feld( $b . '[waegedatum]', $f['waegedatum'], 'Datum der Wägung', '', 'date', 'wird unter dem Rechner angezeigt' );
					self::feld( $b . '[handbuch]', $f['handbuch'], 'Link zum Betriebshandbuch', '', 'text' );
					?>
				</div>

				<h3>Massen, Hebelarme und Grenzen</h3>
				<p class="description">Hebelarme in mm ab Bezugsebene, wie im Wägebericht bzw. Handbuch. Komma oder Punkt sind beide erlaubt.</p>
				<div class="wxsp-raster">
					<?php
					foreach ( WXSP_Daten::zahlenfelder() as $k => $d ) {
						self::feld( $b . '[' . $k . ']', $f[ $k ], $d[0], $d[1] );
					}
					?>
				</div>

				<h3>Zuladung</h3>
				<p class="description">Jede Zeile wird ein Eingabefeld im Rechner. Min./Max. leer lassen, wenn es keine Grenze gibt. „Pflicht“: Ergebnisse erscheinen erst, wenn dieser Posten eingetragen ist (z. B. Pilot).</p>
				<table class="widefat wxsp-posten" data-basis="<?php echo esc_attr( $b ); ?>">
					<thead><tr><th>Bezeichnung</th><th>Hebelarm</th><th>Min.</th><th>Max.</th><th class="wxsp-mitte">Pflicht</th><th></th></tr></thead>
					<tbody>
						<?php
						foreach ( array_values( $f['posten'] ) as $j => $p ) {
							self::posten_zeile( $b, $j, $p );
						}
						?>
					</tbody>
				</table>
				<p><button type="button" class="button wxsp-zeile-neu">+ Zuladungsposten</button></p>

				<p class="wxsp-entfernen"><label><input type="checkbox" name="<?php echo esc_attr( $b . '[entfernen]' ); ?>" value="1"> Dieses Flugzeug beim Speichern entfernen</label></p>
			</div>
		</div>
		<?php
	}

	public static function seite() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$alle = WXSP_Daten::alle();
		?>
		<div class="wrap wxsp-admin">
			<h1>Schwerpunkt</h1>
			<p>Daten für den Rechner auf der Website. Nach einer neuen Wägung hier Leermasse, Hebelarm und Datum eintragen – die Seite rechnet sofort mit den neuen Werten.</p>
			<?php settings_errors( WXSP_Daten::OPTION ); ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'wxsp' ); ?>
				<div class="wxsp-liste">
					<?php
					foreach ( array_values( $alle ) as $i => $f ) {
						self::flugzeug( $i, $f );
					}
					?>
				</div>
				<p><button type="button" class="button wxsp-flugzeug-neu">+ Flugzeug hinzufügen</button></p>
				<?php submit_button( 'Einstellungen speichern' ); ?>
			</form>

			<template id="wxsp-vorlage-flugzeug"><?php self::flugzeug( '__F__', WXSP_Daten::leeres_flugzeug(), true ); ?></template>
			<template id="wxsp-vorlage-zeile"><?php self::posten_zeile( '__B__', '__P__', array( 'name' => '', 'arm' => '', 'min' => '', 'max' => '', 'pflicht' => 0 ) ); ?></template>
		</div>
		<?php
	}
}
