<?php
// Wird beim Löschen des Plugins ausgeführt: gespeicherte Flugzeugdaten entfernen.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'wxsp_flugzeuge' );
