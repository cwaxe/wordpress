<?php
/**
 * Plugin Name:       WX Schwerpunkt
 * Description:       Rechner für Masse und Schwerpunkt (Weight &amp; Balance) direkt auf der Website. Flugzeuge, Hebelarme, Grenzen und Zuladungsposten werden im Backend gepflegt. Shortcode: [schwerpunkt flugzeug="d-mdmp"].
 * Version:           1.1.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Update URI:        https://github.com/cwaxe/wordpress
 * Author:            IT-Service Waxenberger
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wx-schwerpunkt
 */

defined( 'ABSPATH' ) || exit;

// Updates aus den GitHub-Releases (siehe includes/github-updater.php).
require_once __DIR__ . '/includes/github-updater.php';
WX_GitHub_Updater::register( __FILE__ );

define( 'WXSP_VERSION', '1.1.1' );
define( 'WXSP_FILE', __FILE__ );
define( 'WXSP_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/includes/class-wxsp-daten.php';
require_once __DIR__ . '/includes/class-wxsp-frontend.php';
require_once __DIR__ . '/includes/class-wxsp-admin.php';

add_action( 'init', array( 'WXSP_Frontend', 'init' ), 20 ); // nach dem Theme, damit [schwerpunkt] vom Plugin kommt
add_action( 'plugins_loaded', array( 'WXSP_Admin', 'init' ) );
