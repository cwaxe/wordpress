<?php
/**
 * Integrierter Kalender, basierend auf WX Easy Calendar 2.0.1.
 * Gleiche Termine, Kategorien, Einstellungen und Shortcodes wie das Plugin.
 * Lizenz: GPL-2.0-or-later.
 * @package Dunkel_Schlicht
 */

namespace DunkelSchlicht\Calendar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/calendar/daten.php';
require_once __DIR__ . '/calendar/farben.php';
require_once __DIR__ . '/calendar/backend.php';
require_once __DIR__ . '/calendar/aufraeumen.php';
require_once __DIR__ . '/calendar/frontend.php';

/**
 * Termin-Permalinks einmalig registrieren (neue Installation oder neue Kalender-Version).
 */
function ek_rewrite_rules_initialisieren() {
	if ( is_customize_preview() || (int) get_option( 'dunkel_calendar_rewrite' ) >= 2 ) {
		return;
	}
	flush_rewrite_rules( false );
	update_option( 'dunkel_calendar_rewrite', 2, false );
}
add_action( 'wp_loaded', __NAMESPACE__ . '\\ek_rewrite_rules_initialisieren' );

/** Beim Wechsel auf ein anderes Theme das tägliche Aufräumen abmelden. */
function ek_theme_gewechselt() {
	wp_clear_scheduled_hook( 'ek_taeglich_aufraeumen' );
	delete_option( 'dunkel_calendar_rewrite' );
}
add_action( 'switch_theme', __NAMESPACE__ . '\\ek_theme_gewechselt' );
