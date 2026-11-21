<?php
/**
 * Plugin Name:       WX Easy Backup
 * Description:       Sichert die komplette WordPress-Installation (Datenbank, wp-content, Kerndateien) in ein Archiv und spielt es auf demselben oder einem anderen Server wieder ein – inkl. automatischer Anpassung von URLs, Pfaden und Tabellen-Präfix.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            WX Easy Backup
 * License:           GPL-2.0-or-later
 * Text Domain:       wx-easy-backup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Übergang von der Vorgänger-Version „WP Komplett-Backup & Umzug“ (Ordner wp-komplett-backup).
 * Beide nutzen dieselben Backups und Einstellungen. Ist die alte Version noch aktiv,
 * wird sie ohne ihre Deaktivierungs-Routine abgeschaltet, damit nichts verloren geht.
 */
define( 'WXEB_LEGACY_PLUGIN', 'wp-komplett-backup/wp-komplett-backup.php' );

function wxeb_deactivate_legacy() {
	if ( ! function_exists( 'deactivate_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if ( is_plugin_active( WXEB_LEGACY_PLUGIN ) ) {
		deactivate_plugins( WXEB_LEGACY_PLUGIN, true );
		update_option( 'wxeb_legacy_notice', 1, false );
	}
}

function wxeb_legacy_notice() {
	if ( ! get_option( 'wxeb_legacy_notice' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	delete_option( 'wxeb_legacy_notice' );
	echo '<div class="notice notice-success is-dismissible"><p>'
		. esc_html( 'Die alte Version „WP Komplett-Backup & Umzug“ wurde deaktiviert. WX Easy Backup übernimmt ihre Backups und Einstellungen. Das alte Plugin kann jetzt gelöscht werden.' )
		. '</p></div>';
}
add_action( 'admin_notices', 'wxeb_legacy_notice' );

if ( defined( 'WPKB_VERSION' ) ) {
	// Die alte Version wurde in diesem Aufruf schon geladen.
	wxeb_deactivate_legacy();
	add_action( 'activated_plugin', 'wxeb_deactivate_legacy' );
	return;
}

define( 'WPKB_VERSION', '1.1.0' );
define( 'WPKB_FILE', __FILE__ );
define( 'WPKB_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPKB_URL', plugin_dir_url( __FILE__ ) );
define( 'WPKB_SLUG', basename( __DIR__ ) );

if ( ! defined( 'WPKB_BACKUP_DIR' ) ) {
	// Kann in der wp-config.php überschrieben werden.
	define( 'WPKB_BACKUP_DIR', WP_CONTENT_DIR . '/wpkb-backups' );
}

require_once WPKB_DIR . 'includes/class-wpkb-tar.php';
require_once WPKB_DIR . 'includes/class-wpkb-replacer.php';
require_once WPKB_DIR . 'includes/class-wpkb-database.php';
require_once WPKB_DIR . 'includes/class-wpkb-job.php';
require_once WPKB_DIR . 'includes/class-wpkb-admin.php';

add_action( 'plugins_loaded', array( 'WPKB_Admin', 'init' ) );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once WPKB_DIR . 'includes/class-wpkb-cli.php';
	WP_CLI::add_command( 'wx-easy-backup', 'WPKB_CLI' );
	WP_CLI::add_command( 'komplett-backup', 'WPKB_CLI' ); // alter Befehlsname, bleibt nutzbar
}

register_activation_hook( __FILE__, array( 'WPKB_Job', 'ensure_backup_dir' ) );
