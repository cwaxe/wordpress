<?php
/**
 * Plugin Name:       WX Webcam Archive
 * Description:       Archiviert Webcam-Bilder automatisch (per Bild-URL oder FTP-Upload-Ordner, beliebig viele Kameras) und macht sie über einen Kalender mit Tagesansicht und Zeitraffer wieder abrufbar. Shortcodes: [webcam_archiv] und [webcam_aktuell].
 * Version:           1.6.2
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Update URI:        https://github.com/cwaxe/wordpress
 * Author:            IT-Service Waxenberger
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wx-webcam-archive
 */

defined( 'ABSPATH' ) || exit;

// Updates aus den GitHub-Releases (siehe includes/github-updater.php).
require_once __DIR__ . '/includes/github-updater.php';
WX_GitHub_Updater::register( __FILE__ );

/*
 * Übergang von der Vorgänger-Version „Webcam Archiv“ (Ordner webcam-archiv).
 * Beide nutzen dieselben Einstellungen, Kameras und Bilder. Ist die alte Version noch aktiv,
 * wird sie ohne ihre Deaktivierungs-Routine abgeschaltet, damit nichts verloren geht.
 */
define( 'WXWA_LEGACY_PLUGIN', 'webcam-archiv/webcam-archiv.php' );

function wxwa_deactivate_legacy() {
	if ( ! function_exists( 'deactivate_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if ( is_plugin_active( WXWA_LEGACY_PLUGIN ) ) {
		deactivate_plugins( WXWA_LEGACY_PLUGIN, true );
		update_option( 'wxwa_legacy_notice', 1, false );
	}
}

function wxwa_legacy_notice() {
	if ( ! get_option( 'wxwa_legacy_notice' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	delete_option( 'wxwa_legacy_notice' );
	echo '<div class="notice notice-success is-dismissible"><p>'
		. esc_html( 'Die alte Version „Webcam Archiv“ wurde deaktiviert. WX Webcam Archive übernimmt ihre Einstellungen, Kameras und Bilder. Das alte Plugin kann jetzt gelöscht werden.' )
		. '</p></div>';
}
add_action( 'admin_notices', 'wxwa_legacy_notice' );

/*
 * Die uninstall.php der alten Version würde beim Löschen die gemeinsam
 * genutzten Daten entfernen. Sie wird deshalb vorher beiseitegeräumt.
 */
function wxwa_protect_legacy_uninstall( $plugin ) {
	if ( WXWA_LEGACY_PLUGIN !== plugin_basename( $plugin ) ) {
		return;
	}
	$file = WP_PLUGIN_DIR . '/' . dirname( WXWA_LEGACY_PLUGIN ) . '/uninstall.php';
	if ( file_exists( $file ) && ! @unlink( $file ) ) { // phpcs:ignore
		wp_die(
			esc_html( 'Die alte Version „Webcam Archiv“ kann hier nicht gefahrlos gelöscht werden, weil sie dabei die Einstellungen, Kameras und Bilder von WX Webcam Archive entfernen würde. Bitte den Ordner wp-content/plugins/webcam-archiv per FTP löschen.' ),
			'WX Webcam Archive',
			array( 'back_link' => true )
		);
	}
}
add_action( 'pre_uninstall_plugin', 'wxwa_protect_legacy_uninstall' );

if ( defined( 'WCA_VERSION' ) ) {
	// Die alte Version wurde in diesem Aufruf schon geladen.
	wxwa_deactivate_legacy();
	add_action( 'activated_plugin', 'wxwa_deactivate_legacy' );
	return;
}

define( 'WCA_VERSION', '1.6.2' );
define( 'WCA_FILE', __FILE__ );
define( 'WCA_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCA_URL', plugin_dir_url( __FILE__ ) );

require_once WCA_DIR . 'includes/class-wca-plugin.php';
require_once WCA_DIR . 'includes/class-wca-archiver.php';
require_once WCA_DIR . 'includes/class-wca-rest.php';
require_once WCA_DIR . 'includes/class-wca-frontend.php';
require_once WCA_DIR . 'includes/class-wca-admin.php';

register_activation_hook( __FILE__, array( 'WCA_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WCA_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'WCA_Plugin', 'init' ) );
