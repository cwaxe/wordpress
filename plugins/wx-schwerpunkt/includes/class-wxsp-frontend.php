<?php
/**
 * Shortcode [schwerpunkt]
 *
 *   [schwerpunkt]                      erstes Flugzeug aus den Einstellungen
 *   [schwerpunkt flugzeug="d-mdmp"]    bestimmtes Flugzeug (ID oder Name)
 *   [schwerpunkt flugzeug="d-mdmp" titel="Weight & Balance"]
 */

defined( 'ABSPATH' ) || exit;

final class WXSP_Frontend {

	public static function init() {
		remove_shortcode( 'schwerpunkt' ); // ältere Fassung im Theme LSV Flugplatz ersetzen
		add_shortcode( 'schwerpunkt', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_style( 'wxsp-frontend', WXSP_URL . 'assets/css/frontend.css', array(), WXSP_VERSION );
		wp_register_script( 'wxsp-frontend', WXSP_URL . 'assets/js/frontend.js', array(), WXSP_VERSION, true );
	}

	private static function txt( $wert, $nachkomma = 0 ) {
		return number_format_i18n( (float) $wert, $nachkomma );
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'flugzeug' => '',
				'titel'    => '',
			),
			$atts,
			'schwerpunkt'
		);

		$f = WXSP_Daten::finde( $atts['flugzeug'] );
		if ( ! WXSP_Daten::vollstaendig( $f ) ) {
			return current_user_can( 'manage_options' )
				? '<p class="wxsp-fehler">WX Schwerpunkt: Flugzeug „' . esc_html( $atts['flugzeug'] ?: '(erstes)' ) . '“ nicht gefunden oder unvollständig – bitte unter Einstellungen → Schwerpunkt prüfen.</p>'
				: '';
		}

		if ( ! wp_style_is( 'wxsp-frontend', 'registered' ) ) {
			self::register_assets();
		}
		wp_enqueue_style( 'wxsp-frontend' );
		wp_enqueue_script( 'wxsp-frontend' );

		$cfg = array(
			'leerKg'  => (float) $f['leermasse'],
			'leerArm' => (float) $f['leerarm'],
			'mtom'    => (float) $f['mtom'],
			'vorn'    => (float) $f['cg_vorn'],
			'hinten'  => (float) $f['cg_hinten'],
			'le'      => (float) $f['mac_vorderkante'],
			'mac'     => (float) $f['mac'],
			'tank'    => (float) $f['tank_arm'],
			'tankMax' => (float) $f['tank_max'],
			'kgL'     => (float) $f['kg_pro_liter'],
			'lh'      => (float) $f['verbrauch'],
			'posten'  => array_values( $f['posten'] ),
		);

		$titel = '' !== $atts['titel'] ? $atts['titel'] : 'Schwerpunktberechnung ' . $f['name'];

		ob_start();
		?>
<div class="wxsp" data-wxsp="<?php echo esc_attr( wp_json_encode( $cfg ) ); ?>">
	<div class="wxsp-kopf">
		<h2><?php echo esc_html( $titel ); ?></h2>
		<p>Gewichte eintragen – der Rechner zeigt, wie viel Kraftstoff dazu passt. Grün = innerhalb der Grenzen.</p>
	</div>
	<table class="wxsp-tab">
		<thead><tr><th>Posten</th><th>Gewicht</th><th class="wxsp-n">Arm</th><th class="wxsp-n">Moment</th></tr></thead>
		<tbody>
			<tr><td>Leeres Flugzeug</td><td><?php echo esc_html( self::txt( $f['leermasse'], 1 ) ); ?> kg</td><td class="wxsp-n"><?php echo esc_html( self::txt( $f['leerarm'], 2 ) ); ?> mm</td><td class="wxsp-n"><?php echo esc_html( self::txt( $f['leermasse'] * $f['leerarm'] ) ); ?></td></tr>
			<?php foreach ( $f['posten'] as $i => $p ) : ?>
				<?php
				$hinweis = array();
				if ( $p['min'] > 0 && $p['max'] > 0 ) {
					$hinweis[] = self::txt( $p['min'] ) . '–' . self::txt( $p['max'] ) . ' kg';
				} elseif ( $p['max'] > 0 ) {
					$hinweis[] = 'max. ' . self::txt( $p['max'] ) . ' kg';
				} elseif ( $p['min'] > 0 ) {
					$hinweis[] = 'min. ' . self::txt( $p['min'] ) . ' kg';
				}
				?>
			<tr>
				<td><?php echo esc_html( $p['name'] ); ?><?php echo $hinweis ? ' <small>' . esc_html( implode( ', ', $hinweis ) ) . '</small>' : ''; ?></td>
				<td><label class="wxsp-ein"><input type="number" inputmode="decimal" min="0" step="0.5" value="" placeholder="0" autocomplete="off" data-posten="<?php echo (int) $i; ?>" aria-label="<?php echo esc_attr( $p['name'] . ' in kg' ); ?>"><span>kg</span></label></td>
				<td class="wxsp-n"><?php echo esc_html( self::txt( $p['arm'] ) ); ?> mm</td>
				<td class="wxsp-n" data-moment="<?php echo (int) $i; ?>"></td>
			</tr>
			<?php endforeach; ?>
			<tr>
				<td>Kraftstoff <small data-out="tank-kg"></small></td>
				<td><label class="wxsp-ein"><input type="number" inputmode="decimal" min="0" step="1" value="" placeholder="0" autocomplete="off" data-tank aria-label="Kraftstoff in Litern"><span>l</span></label></td>
				<td class="wxsp-n"><?php echo esc_html( self::txt( $f['tank_arm'] ) ); ?> mm</td>
				<td class="wxsp-n" data-out="m-tank"></td>
			</tr>
		</tbody>
	</table>

	<div class="wxsp-erg">
		<div class="wxsp-kachel" data-box="masse"><span>Startmasse</span><strong data-out="masse"></strong><small data-out="masse-info"></small></div>
		<div class="wxsp-kachel" data-box="cg"><span>Schwerpunkt beim Start</span><strong data-out="cg"></strong><small data-out="cg-mac"></small></div>
		<div class="wxsp-kachel" data-box="cg0"><span>Schwerpunkt bei leerem Tank</span><strong data-out="cg0"></strong><small data-out="cg0-mac"></small></div>
		<div class="wxsp-kachel" data-box="sprit"><span>Max. Kraftstoff bei dieser Beladung</span><strong data-out="sprit"></strong><small data-out="sprit-info"></small></div>
		<?php if ( $f['verbrauch'] > 0 ) : ?>
		<div class="wxsp-kachel"><span>Flugzeit mit dieser Tankfüllung</span><strong data-out="flugzeit"></strong><small data-out="flugzeit-info">bei <?php echo esc_html( self::txt( $f['verbrauch'] ) ); ?> l/h, ohne Reserve</small></div>
		<?php endif; ?>
	</div>

	<div class="wxsp-skala" aria-hidden="true">
		<div class="wxsp-band"></div>
		<div class="wxsp-marke wxsp-marke-start" data-marke="cg" title="Start"></div>
		<div class="wxsp-marke wxsp-marke-leer" data-marke="cg0" title="Leerer Tank"></div>
		<div class="wxsp-skala-text"><span><?php echo esc_html( self::txt( $f['cg_vorn'] ) ); ?> mm</span><span>● Start &nbsp; ○ leerer Tank</span><span><?php echo esc_html( self::txt( $f['cg_hinten'] ) ); ?> mm</span></div>
	</div>

	<ul class="wxsp-hinweise" data-out="hinweise" aria-live="polite"></ul>

	<p class="wxsp-fuss">
		Grenzen: max. Startmasse <?php echo esc_html( self::txt( $f['mtom'] ) ); ?> kg, Schwerpunkt <?php echo esc_html( self::txt( $f['cg_vorn'] ) . '–' . self::txt( $f['cg_hinten'] ) ); ?> mm
		(<?php echo esc_html( self::txt( ( $f['cg_vorn'] - $f['mac_vorderkante'] ) * 100 / $f['mac'] ) . '–' . self::txt( ( $f['cg_hinten'] - $f['mac_vorderkante'] ) * 100 / $f['mac'] ) ); ?> %MAC).
		Nur eine Hilfe zur Flugvorbereitung – maßgeblich ist das <?php echo $f['handbuch'] ? '<a href="' . esc_url( $f['handbuch'] ) . '">Betriebshandbuch</a>' : 'Betriebshandbuch'; ?>.
		<?php if ( $f['waegedatum'] ) : ?>
		<br>Leermasse laut Wägung vom <?php echo esc_html( date_i18n( 'd.m.Y', strtotime( $f['waegedatum'] ) ) ); ?>.
		<?php endif; ?>
	</p>
	<noscript><p>Für die Berechnung wird JavaScript benötigt.</p></noscript>
</div>
		<?php
		return ob_get_clean();
	}
}
