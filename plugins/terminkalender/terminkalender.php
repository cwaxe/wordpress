<?php
/**
 * Plugin Name:       Terminkalender
 * Description:       Schlanker Veranstaltungskalender mit einfachem Backend: Termine mit Datum, Ort, Veranstalter, Kategorie, Bild und einfachen Serien. Monats- und Listenansicht, Kategorie-Filter, eigene Terminseiten, iCal. Übernimmt alle Daten aus „The Events Calendar“.
 * Version:           1.1.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            IT-Service Waxenberger
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       terminkalender
 */

defined( 'ABSPATH' ) || exit;

define( 'TK_VERSION', '1.1.0' );
define( 'TK_FILE', __FILE__ );
define( 'TK_DIR', plugin_dir_path( __FILE__ ) );
define( 'TK_URL', plugin_dir_url( __FILE__ ) );

require_once TK_DIR . 'includes/class-tk-settings.php';
require_once TK_DIR . 'includes/class-tk-model.php';
require_once TK_DIR . 'includes/class-tk-admin.php';
require_once TK_DIR . 'includes/class-tk-import.php';
require_once TK_DIR . 'includes/class-tk-frontend.php';

TK_Model::init();
TK_Settings::init();
TK_Admin::init();
TK_Import::init();
TK_Frontend::init();

register_activation_hook(
	__FILE__,
	static function () {
		TK_Model::register();
		flush_rewrite_rules();

		// Kalenderseite des bisherigen Ansicht-Plugins übernehmen ([veranstaltungskalender] wird weiter unterstützt).
		if ( ! TK_Settings::get( 'page_id' ) ) {
			$old = get_option( 'tecm_settings' );
			if ( is_array( $old ) && ! empty( $old['page_id'] ) && 'publish' === get_post_status( (int) $old['page_id'] ) ) {
				TK_Settings::update( [ 'page_id' => (int) $old['page_id'] ] );
			}
		}
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		flush_rewrite_rules();
	}
);
