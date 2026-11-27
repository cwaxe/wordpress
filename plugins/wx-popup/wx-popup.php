<?php
/**
 * Plugin Name:       WX Popup
 * Description:       Schlanke Pop-ups (z. B. für das Flugplatzfest): Inhalt im Block-Editor, Zeitraum, Seiten, Verzögerung und Wiederholung einstellbar. Ohne Cookies, ohne Tracking. Übernimmt vorhandene Pop-ups aus Popup Maker.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Update URI:        https://github.com/cwaxe/wordpress
 * Author:            IT-Service Waxenberger
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wx-popup
 */

defined( 'ABSPATH' ) || exit;

// Updates aus den GitHub-Releases (siehe includes/github-updater.php).
require_once __DIR__ . '/includes/github-updater.php';
WX_GitHub_Updater::register( __FILE__ );

define( 'WXPU_VERSION', '1.0.0' );
define( 'WXPU_FILE', __FILE__ );
define( 'WXPU_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/includes/class-wxpu-popup.php';
require_once __DIR__ . '/includes/class-wxpu-admin.php';
require_once __DIR__ . '/includes/class-wxpu-frontend.php';
require_once __DIR__ . '/includes/class-wxpu-import.php';

add_action( 'init', array( 'WXPU_Popup', 'register' ) );
add_action( 'init', array( 'WXPU_Frontend', 'init' ) );
add_action( 'admin_init', array( 'WXPU_Import', 'maybe_import' ) );
WXPU_Admin::init();

register_activation_hook(
	__FILE__,
	function () {
		WXPU_Popup::register();
		flush_rewrite_rules();
		update_option( 'wxpu_import_pending', 1, false );
	}
);
