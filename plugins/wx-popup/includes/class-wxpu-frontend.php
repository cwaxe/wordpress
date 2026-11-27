<?php
/**
 * Ausgabe der Pop-ups im Frontend.
 *
 * Passende Pop-ups werden am Seitenende als versteckte Dialoge ausgegeben,
 * das Skript öffnet sie nach der eingestellten Zeit. Ob ein Besucher ein Pop-up
 * schon gesehen hat, steht im localStorage bzw. sessionStorage des Browsers –
 * es werden keine Cookies gesetzt.
 */

defined( 'ABSPATH' ) || exit;

final class WXPU_Frontend {

	public static function init() {
		if ( is_admin() ) {
			return;
		}
		add_action( 'wp_footer', array( __CLASS__, 'ausgabe' ), 5 ); // vor wp_print_footer_scripts (20)
	}

	/** Pop-ups, die auf der aktuellen Seite erscheinen sollen. */
	private static function passende() {
		$vorschau = isset( $_GET['wxpu_vorschau'] ) ? absint( $_GET['wxpu_vorschau'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $vorschau && current_user_can( 'edit_post', $vorschau ) && WXPU_Popup::TYPE === get_post_type( $vorschau ) ) {
			return array( get_post( $vorschau ) );
		}

		$popups = get_posts(
			array(
				'post_type'        => WXPU_Popup::TYPE,
				'post_status'      => 'publish',
				'numberposts'      => 20,
				'orderby'          => 'menu_order date',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		);
		$id  = get_queried_object_id();
		$aus = array();
		foreach ( $popups as $p ) {
			$s  = WXPU_Popup::get( $p->ID );
			$st = WXPU_Popup::status( $p->ID );
			// "geplant" trotzdem ausgeben: Bei zwischengespeicherten Seiten prüft das Skript den Zeitraum noch einmal.
			if ( ! in_array( $st, array( 'aktiv', 'geplant' ), true ) ) {
				continue;
			}
			if ( 'startseite' === $s['wo'] && ! ( is_front_page() || is_home() ) ) {
				continue;
			}
			if ( 'seiten' === $s['wo'] && ! in_array( (int) $id, $s['seiten'], true ) ) {
				continue;
			}
			$aus[] = $p;
		}
		return $aus;
	}

	public static function ausgabe() {
		$popups   = self::passende();
		$vorschau = isset( $_GET['wxpu_vorschau'] ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $popups ) {
			return;
		}

		wp_enqueue_style( 'wxpu', WXPU_URL . 'assets/css/frontend.css', array(), WXPU_VERSION );
		wp_enqueue_script( 'wxpu', WXPU_URL . 'assets/js/frontend.js', array(), WXPU_VERSION, true );
		wp_print_styles( 'wxpu' );

		foreach ( $popups as $p ) {
			$s    = WXPU_Popup::get( $p->ID );
			$data = array(
				'id'      => (int) $p->ID,
				'delay'   => $vorschau ? 0.3 : (float) $s['verzoegerung'],
				'repeat'  => $vorschau ? 'vorschau' : $s['wiederholung'],
				'days'    => (int) $s['tage'],
				'from'    => WXPU_Popup::ts( $s['von'] ) * 1000,
				'until'   => WXPU_Popup::ts( $s['bis'] ) * 1000,
				'outside' => (bool) $s['aussen'],
				// Version: Nach einer Änderung am Pop-up sehen es alle noch einmal.
				'version' => (int) get_post_modified_time( 'U', true, $p ),
			);
			$titel = get_the_title( $p );
			// phpcs:disable WordPress.Security.EscapeOutput -- the_content liefert gefilterten Inhalt
			?>
<div class="wxpu wxpu-<?php echo esc_attr( $s['breite'] ); ?>" id="popup-<?php echo (int) $p->ID; ?>" data-wxpu="<?php echo esc_attr( wp_json_encode( $data ) ); ?>" hidden>
	<div class="wxpu-hintergrund" data-wxpu-schliessen-aussen></div>
	<div class="wxpu-fenster" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( $titel ); ?>" tabindex="-1">
		<button type="button" class="wxpu-zu" data-wxpu-schliessen aria-label="Schließen">&times;</button>
		<div class="wxpu-inhalt"><?php echo apply_filters( 'the_content', $p->post_content ); ?></div>
	</div>
</div>
			<?php
			// phpcs:enable
		}
	}
}
