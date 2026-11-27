<?php
// Wird beim Löschen des Plugins ausgeführt. Die Pop-ups selbst (Inhalte) bleiben erhalten,
// damit beim erneuten Installieren nichts verloren ist; nur Hilfswerte werden entfernt.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'wxpu_import_pending' );
delete_transient( 'wxpu_import_hinweis' );
