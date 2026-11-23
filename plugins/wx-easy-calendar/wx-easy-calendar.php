<?php
/**
 * Plugin Name:       WX Easy Calendar
 * Description:       Einfacher Termin-Kalender mit Kategorien, Orten, Veranstaltern, Bildern und Serien. Per Shortcode [einfacher_kalender] als Liste, Woche, Monat oder Jahr – mit Filter, Suche, Vorschau, Terminseiten und iCal. Übernimmt die Daten aus „Terminkalender“ und „The Events Calendar“.
 * Version:           2.2.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Update URI:        https://github.com/cwaxe/wordpress
 * Author:            IT-Service Waxenberger
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wx-easy-calendar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Updates aus den GitHub-Releases (siehe includes/github-updater.php).
require_once __DIR__ . '/includes/github-updater.php';
WX_GitHub_Updater::register( __FILE__ );

/*
 * Übergang von der Vorgänger-Version „Einfacher Kalender“ (Ordner einfacher-kalender).
 * Beide nutzen dieselben Termine, Kategorien und Einstellungen. Ist die alte Version noch aktiv,
 * wird sie ohne ihre Deaktivierungs-Routine abgeschaltet, damit nichts verloren geht.
 */
define( 'WXEC_LEGACY_PLUGIN', 'einfacher-kalender/einfacher-kalender.php' );

function wxec_deactivate_legacy() {
	if ( ! function_exists( 'deactivate_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if ( is_plugin_active( WXEC_LEGACY_PLUGIN ) ) {
		deactivate_plugins( WXEC_LEGACY_PLUGIN, true );
		update_option( 'wxec_legacy_notice', 1, false );
	}
}

function wxec_legacy_notice() {
	if ( ! get_option( 'wxec_legacy_notice' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	delete_option( 'wxec_legacy_notice' );
	echo '<div class="notice notice-success is-dismissible"><p>'
		. esc_html( 'Die alte Version „Einfacher Kalender“ wurde deaktiviert. WX Easy Calendar übernimmt ihre Termine, Kategorien und Einstellungen. Das alte Plugin kann jetzt gelöscht werden.' )
		. '</p></div>';
}
add_action( 'admin_notices', 'wxec_legacy_notice' );

if ( defined( 'EK_VERSION' ) ) {
	// Die alte Version wurde in diesem Aufruf schon geladen.
	wxec_deactivate_legacy();
	add_action( 'activated_plugin', 'wxec_deactivate_legacy' );
	return;
}

define( 'EK_VERSION', '2.2.0' );
define( 'EK_URL', plugin_dir_url( __FILE__ ) );
define( 'EK_DIR', plugin_dir_path( __FILE__ ) );

require_once EK_DIR . 'includes/daten.php';
require_once EK_DIR . 'includes/farben.php';
require_once EK_DIR . 'includes/backend.php';
require_once EK_DIR . 'includes/aufraeumen.php';
require_once EK_DIR . 'includes/frontend.php';
require_once EK_DIR . 'includes/import.php';
require_once EK_DIR . 'includes/terminkalender.php';

function ek_aktivieren() {
	ek_register_post_type();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'ek_aktivieren' );

function ek_deaktivieren() {
	wp_clear_scheduled_hook( 'ek_taeglich_aufraeumen' );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'ek_deaktivieren' );
