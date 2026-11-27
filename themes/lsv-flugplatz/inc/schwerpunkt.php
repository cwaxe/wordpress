<?php
/**
 * Schwerpunktrechner (Masse und Schwerpunkt) als Shortcode.
 *
 *   [schwerpunkt]                       Bristell D-MDMP mit den Werten aus der Wägung
 *   [schwerpunkt leermasse="368,2" leerarm="731,5"]   z. B. nach einer neuen Wägung
 *
 * Rechnet wie die frühere Google-Tabelle:
 *   Moment = Gewicht × Arm, Schwerpunkt = Gesamtmoment / Masse,
 *   %MAC = (Schwerpunkt − MAC-Vorderkante) × 100 / MAC, Kraftstoff = Liter × kg/l.
 * Zusätzlich: Prüfung der Grenzen und Schwerpunkt bei leerem Tank.
 *
 * @package LSV_Flugplatz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Zahl aus einem Shortcode-Attribut lesen (Komma oder Punkt).
 */
function lsv_sp_zahl( $wert ) {
	return (float) str_replace( ',', '.', (string) $wert );
}

/**
 * Zahl für die Anzeige im deutschen Format.
 */
function lsv_sp_text( $wert, $nachkomma = 0 ) {
	return number_format_i18n( $wert, $nachkomma );
}

function lsv_schwerpunkt_html( $atts = array() ) {
	$a = shortcode_atts(
		array(
			'kennzeichen'     => 'Bristell D-MDMP',
			'leermasse'       => '366.5',   // kg
			'leerarm'         => '730.79',  // mm
			'arm_sitze'       => '1156',
			'arm_gepaeck'     => '1806',    // Gepäck hinter den Sitzen
			'arm_fluegel'     => '1036',    // Flügelkoffer
			'arm_tank'        => '606',
			'kg_pro_liter'    => '0.72',
			'verbrauch'       => '19',      // l/h, für die Flugzeit
			'mtom'            => '600',
			'cg_vorn'         => '750',
			'cg_hinten'       => '871',
			'mac_vorderkante' => '412.6',
			'mac'             => '1349.7',
			'pilot_min'       => '55',
			'pilot_max'       => '110',
			'max_gepaeck'     => '15',
			'max_fluegel'     => '20',      // je Seite
			'handbuch'        => '/wp-content/uploads/Betriebshandbuch-D-MDMP.pdf',
		),
		$atts,
		'schwerpunkt'
	);

	$z = array();
	foreach ( $a as $k => $v ) {
		if ( ! in_array( $k, array( 'kennzeichen', 'handbuch' ), true ) ) {
			$z[ $k ] = lsv_sp_zahl( $v );
		}
	}
	if ( $z['mac'] <= 0 || $z['cg_hinten'] <= $z['cg_vorn'] ) {
		return current_user_can( 'edit_posts' ) ? '<p>[schwerpunkt]: Bitte MAC und Schwerpunktgrenzen prüfen.</p>' : '';
	}

	wp_enqueue_style( 'lsv-schwerpunkt', get_template_directory_uri() . '/assets/css/schwerpunkt.css', array( 'lsv-style' ), LSV_VERSION );
	wp_enqueue_script( 'lsv-schwerpunkt', get_template_directory_uri() . '/assets/js/schwerpunkt.js', array(), LSV_VERSION, true );

	$data = array(
		'leer-masse'      => $z['leermasse'],
		'leer-arm'        => $z['leerarm'],
		'arm-sitze'       => $z['arm_sitze'],
		'arm-gepaeck'     => $z['arm_gepaeck'],
		'arm-fluegel'     => $z['arm_fluegel'],
		'arm-tank'        => $z['arm_tank'],
		'kg-pro-liter'    => $z['kg_pro_liter'],
		'verbrauch'       => $z['verbrauch'],
		'mtom'            => $z['mtom'],
		'cg-vorn'         => $z['cg_vorn'],
		'cg-hinten'       => $z['cg_hinten'],
		'mac-vorderkante' => $z['mac_vorderkante'],
		'mac'             => $z['mac'],
		'pilot-min'       => $z['pilot_min'],
		'pilot-max'       => $z['pilot_max'],
		'max-gepaeck'     => $z['max_gepaeck'],
		'max-fluegel'     => $z['max_fluegel'],
	);
	$attr = '';
	foreach ( $data as $k => $v ) {
		$attr .= sprintf( ' data-%s="%s"', $k, esc_attr( $v ) );
	}

	// Felder starten immer leer; $wert bleibt nur für spätere Vorgaben erhalten.
	$eingabe = function ( $key, $label, $wert, $einheit = 'kg', $schritt = '0.5' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return sprintf(
			'<label class="lsv-sp-ein"><input type="number" inputmode="decimal" min="0" step="%s" data-in="%s" value="" placeholder="0" autocomplete="off" aria-label="%s"><span>%s</span></label>',
			esc_attr( $schritt ),
			esc_attr( $key ),
			esc_attr( $label . ( 'kg' === $einheit ? ' in kg' : ' in Litern' ) ),
			esc_html( $einheit )
		);
	};
	$zeile = function ( $titel, $hinweis, $gewicht, $arm_out, $moment_out ) {
		return '<tr><td>' . esc_html( $titel ) . ( $hinweis ? ' <small>' . esc_html( $hinweis ) . '</small>' : '' ) . '</td><td>' . $gewicht
			. '</td><td class="lsv-sp-n" data-out="' . esc_attr( $arm_out ) . '"></td><td class="lsv-sp-n" data-out="' . esc_attr( $moment_out ) . '"></td></tr>';
	};

	$pilot = $z['pilot_min'] > 0 ? lsv_sp_text( $z['pilot_min'] ) . '–' . lsv_sp_text( $z['pilot_max'] ) . ' kg' : '';

	ob_start();
	?>
<div class="lsv-sp"<?php echo $attr; // phpcs:ignore WordPress.Security.EscapeOutput -- oben escaped ?>>
	<div class="lsv-sp-kopf">
		<h2>Schwerpunktberechnung <?php echo esc_html( $a['kennzeichen'] ); ?></h2>
		<p>Gewichte und Kraftstoff eintragen, alles andere rechnet sich sofort. Grün = innerhalb der Grenzen.</p>
	</div>
	<table class="lsv-sp-tab">
		<thead><tr><th>Posten</th><th>Gewicht</th><th class="lsv-sp-n">Arm</th><th class="lsv-sp-n">Moment</th></tr></thead>
		<tbody>
			<?php
			echo $zeile( 'Leeres Flugzeug', '', '<span data-out="leer-kg"></span>', 'leer-arm', 'm-leer' ); // phpcs:ignore
			echo $zeile( 'Pilot', $pilot, $eingabe( 'pilot', 'Pilot', 65 ), 'arm-sitze', 'm-pilot' ); // phpcs:ignore
			echo $zeile( 'Passagier', '', $eingabe( 'passagier', 'Passagier', 90 ), 'arm-sitze', 'm-passagier' ); // phpcs:ignore
			echo $zeile( 'Gepäck hinter den Sitzen', 'max. ' . lsv_sp_text( $z['max_gepaeck'] ) . ' kg', $eingabe( 'gepaeck', 'Gepäck hinter den Sitzen', 0 ), 'arm-gepaeck', 'm-gepaeck' ); // phpcs:ignore
			echo $zeile( 'Flügelkoffer links', 'max. ' . lsv_sp_text( $z['max_fluegel'] ) . ' kg', $eingabe( 'fluegel-l', 'Flügelkoffer links', 0 ), 'arm-fluegel', 'm-fluegel-l' ); // phpcs:ignore
			echo $zeile( 'Flügelkoffer rechts', 'max. ' . lsv_sp_text( $z['max_fluegel'] ) . ' kg', $eingabe( 'fluegel-r', 'Flügelkoffer rechts', 0 ), 'arm-fluegel', 'm-fluegel-r' ); // phpcs:ignore
			?>
			<tr><td>Kraftstoff <small data-out="tank-kg"></small></td><td><?php echo $eingabe( 'liter', 'Kraftstoff', '', 'l', '1' ); // phpcs:ignore ?></td><td class="lsv-sp-n" data-out="arm-tank"></td><td class="lsv-sp-n" data-out="m-tank"></td></tr>
		</tbody>
	</table>

	<div class="lsv-sp-erg">
		<div class="lsv-sp-kachel" data-box="masse"><span>Startmasse</span><strong data-out="masse"></strong><small data-out="masse-info"></small></div>
		<div class="lsv-sp-kachel" data-box="cg"><span>Schwerpunkt beim Start</span><strong data-out="cg"></strong><small data-out="cg-mac"></small></div>
		<div class="lsv-sp-kachel" data-box="cg0"><span>Schwerpunkt bei leerem Tank</span><strong data-out="cg0"></strong><small data-out="cg0-mac"></small></div>
		<div class="lsv-sp-kachel"><span>Flugzeit mit dieser Tankfüllung</span><strong data-out="flugzeit"></strong><small data-out="verbrauch"></small></div>
	</div>

	<div class="lsv-sp-skala" aria-hidden="true">
		<div class="lsv-sp-band"></div>
		<div class="lsv-sp-marke lsv-sp-marke-start" data-marke="cg" title="Start"></div>
		<div class="lsv-sp-marke lsv-sp-marke-leer" data-marke="cg0" title="Leerer Tank"></div>
		<div class="lsv-sp-skala-text"><span data-out="grenze-vorn"></span><span>● Start &nbsp; ○ leerer Tank</span><span data-out="grenze-hinten"></span></div>
	</div>

	<ul class="lsv-sp-hinweise" data-out="hinweise"></ul>

	<p class="lsv-sp-fuss">Grenzen: max. Startmasse <span data-out="mtom"></span>, Schwerpunkt <span data-out="grenzen"></span>.
	Nur eine Hilfe zur Flugvorbereitung – maßgeblich ist das <?php echo $a['handbuch'] ? '<a href="' . esc_url( $a['handbuch'] ) . '">Betriebshandbuch</a>' : 'Betriebshandbuch'; ?>.</p>
	<noscript><p>Für die Berechnung wird JavaScript benötigt.</p></noscript>
</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'schwerpunkt', 'lsv_schwerpunkt_html' );
