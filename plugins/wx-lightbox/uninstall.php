<?php
// Wird beim Löschen des Plugins ausgeführt: Einstellungen entfernen.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'sbl_settings' );
