<?php
/**
 * Einmalige Übernahme der Pop-ups aus Popup Maker (Beitragstyp "popup").
 *
 * Läuft nach der Aktivierung beim nächsten Aufruf im Backend. Inhalt, Titel und die
 * wichtigsten Einstellungen werden übernommen; der Aktiv-Status bleibt wie er war.
 * Funktioniert auch, wenn Popup Maker schon deaktiviert ist.
 */

defined( 'ABSPATH' ) || exit;

final class WXPU_Import {

	const QUELLE = '_wxpu_popup_maker_id';

	public static function maybe_import() {
		if ( ! get_option( 'wxpu_import_pending' ) || ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		delete_option( 'wxpu_import_pending' );
		$anzahl = self::import();
		if ( $anzahl ) {
			set_transient( 'wxpu_import_hinweis', $anzahl, 600 );
		}
		add_action( 'admin_notices', array( __CLASS__, 'hinweis' ) );
	}

	public static function hinweis() {
		$anzahl = get_transient( 'wxpu_import_hinweis' );
		if ( ! $anzahl ) {
			return;
		}
		delete_transient( 'wxpu_import_hinweis' );
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s <a href="%s">Pop-ups ansehen</a></p></div>',
			esc_html( sprintf( 'WX Popup hat %d Pop-up(s) aus Popup Maker übernommen. Popup Maker kann jetzt deaktiviert und gelöscht werden.', (int) $anzahl ) ),
			esc_url( admin_url( 'edit.php?post_type=' . WXPU_Popup::TYPE ) )
		);
	}

	public static function import() {
		global $wpdb;
		$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'popup' AND post_status IN ('publish','draft','private') ORDER BY ID" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$n   = 0;
		foreach ( $ids as $alt_id ) {
			$schon = get_posts(
				array(
					'post_type'   => WXPU_Popup::TYPE,
					'post_status' => 'any',
					'meta_key'    => self::QUELLE, // phpcs:ignore WordPress.DB.SlowDBQuery
					'meta_value'  => (int) $alt_id, // phpcs:ignore WordPress.DB.SlowDBQuery
					'fields'      => 'ids',
				)
			);
			if ( $schon ) {
				continue;
			}
			$alt = get_post( $alt_id );
			$neu = wp_insert_post(
				array(
					'post_type'    => WXPU_Popup::TYPE,
					'post_status'  => 'publish' === $alt->post_status ? 'publish' : 'draft',
					'post_title'   => $alt->post_title,
					'post_content' => $alt->post_content,
				),
				true
			);
			if ( is_wp_error( $neu ) ) {
				continue;
			}
			update_post_meta( $neu, WXPU_Popup::META, self::einstellungen( $alt_id ) );
			update_post_meta( $neu, self::QUELLE, (int) $alt_id );
			$n++;
		}
		return $n;
	}

	/** Popup-Maker-Einstellungen in WX-Popup-Einstellungen übersetzen. */
	private static function einstellungen( $alt_id ) {
		$pm = get_post_meta( $alt_id, 'popup_settings', true );
		if ( ! is_array( $pm ) ) {
			$pm = array();
		}
		$s = WXPU_Popup::defaults();

		$aktiv      = get_post_meta( $alt_id, 'enabled', true );
		$s['aktiv'] = ( '' === $aktiv || null === $aktiv ) ? 0 : (int) (bool) $aktiv;

		// Wo: nur Startseite, wenn alle Bedingungen Startseite/Blogseite sind.
		$ziele = array();
		foreach ( (array) ( $pm['conditions'] ?? array() ) as $gruppe ) {
			foreach ( (array) $gruppe as $c ) {
				if ( ! empty( $c['target'] ) ) {
					$ziele[] = $c['target'];
				}
			}
		}
		$s['wo'] = ( $ziele && ! array_diff( $ziele, array( 'is_front_page', 'is_home' ) ) ) ? 'startseite' : 'alle';

		// Verzögerung aus dem ersten Auslöser „automatisch öffnen“.
		foreach ( (array) ( $pm['triggers'] ?? array() ) as $t ) {
			if ( 'auto_open' === ( $t['type'] ?? '' ) ) {
				$s['verzoegerung'] = round( ( (int) ( $t['settings']['delay'] ?? 500 ) ) / 1000, 1 );
				break;
			}
		}

		// Ohne Cookie zeigt Popup Maker das Pop-up bei jedem Aufruf.
		$cookies = (array) ( $pm['cookies'] ?? array() );
		if ( ! $cookies ) {
			$s['wiederholung'] = 'immer';
		} else {
			$exp = (string) ( $cookies[0]['settings']['expires'] ?? '1 day' );
			if ( isset( $cookies[0]['settings']['session'] ) && $cookies[0]['settings']['session'] ) {
				$s['wiederholung'] = 'sitzung';
			} else {
				$sek               = strtotime( '+' . $exp, 0 );
				$s['wiederholung'] = 'tage';
				$s['tage']         = max( 1, min( 365, (int) round( ( $sek ? $sek : DAY_IN_SECONDS ) / DAY_IN_SECONDS ) ) );
			}
		}

		$groesse     = (string) ( $pm['size'] ?? 'medium' );
		$s['breite'] = in_array( $groesse, array( 'nano', 'micro', 'tiny', 'small' ), true ) ? 'klein'
			: ( in_array( $groesse, array( 'large', 'xlarge' ), true ) ? 'gross'
			: ( 'medium' === $groesse ? 'mittel' : 'auto' ) );

		$s['aussen'] = empty( $pm['close_on_overlay_click'] ) ? 0 : 1;
		return $s;
	}
}
