=== WX Easy Backup ===
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later

Komplette Sicherung einer WordPress-Seite (Datenbank + alle Dateien) und Wiederherstellung auf demselben oder einem anderen Server.

== Beschreibung ==

* Sichert Datenbank, wp-content (Themes, Plugins, Uploads) und die Dateien im Hauptverzeichnis in eine einzige .tar-Datei.
* Arbeitet in kurzen Schritten (~20 Sekunden) – funktioniert auch bei großen Seiten auf Shared Hosting ohne Timeouts.
* Upload großer Backups in Teilen – das Upload-Limit des Servers spielt keine Rolle.
* Beim Umzug werden Domain, http/https, Server-Pfade und Tabellen-Präfix automatisch angepasst – auch in serialisierten Daten (Widgets, Page-Builder, Theme-Einstellungen) und JSON.
* Sicherer Datenbank-Import: zuerst in temporäre Tabellen, dann atomarer Austausch. Bricht der Vorgang ab, bleibt die alte Datenbank unverändert.
* Die wp-config.php und .htaccess des Zielservers werden nie überschrieben.
* MySQL-8-Kollationen werden beim Umzug auf MariaDB automatisch angepasst.
* Das Archiv ist ein normales TAR – es lässt sich auch mit 7-Zip, WinRAR oder `tar -xf` öffnen.
* WP-CLI: `wp wx-easy-backup create`, `wp wx-easy-backup restore <datei>`, `wp wx-easy-backup list`.

== Installation ==

1. Den Ordner `wx-easy-backup` nach `/wp-content/plugins/` hochladen oder die ZIP-Datei unter Plugins → Installieren → Plugin hochladen installieren.
2. Plugin aktivieren.
3. Werkzeuge → WX Easy Backup öffnen.

== Umzug auf einen anderen Server ==

1. Alte Seite: Werkzeuge → WX Easy Backup → „Backup jetzt erstellen“ → herunterladen.
2. Neuer Server: WordPress frisch installieren, dieses Plugin installieren und aktivieren.
3. Backup hochladen (oder per FTP nach `wp-content/wpkb-backups/` kopieren).
4. „Wiederherstellen“ klicken.
5. Mit den Zugangsdaten der ALTEN Seite anmelden und unter Einstellungen → Permalinks einmal speichern.

== Hinweise ==

* Backups liegen in `wp-content/wpkb-backups/`. Der Ordner ist per .htaccess/web.config gesperrt, die Dateinamen enthalten eine Zufallskennung. Unter nginx greift .htaccess nicht – dort zusätzlich `location ~ /wpkb-backups/ { deny all; }` setzen oder den Ordner per `define( 'WPKB_BACKUP_DIR', '/pfad/außerhalb/des/webroots' );` in der wp-config.php verlegen.
* Das Backup enthält auch die wp-config.php der Quellseite (mit DB-Zugangsdaten) – Backups daher vertraulich behandeln.
* Automatisch ausgeschlossen: Cache-Ordner sowie Backup-Ordner anderer Plugins.
* Multisite-Netzwerke werden für die Wiederherstellung nicht unterstützt.
* Vor dem Wiederherstellen auf einer bestehenden Seite am besten erst ein Backup dieser Seite erstellen.
* Der Server braucht während der Sicherung freien Speicher etwa in Größe der Seite.
* Schrittdauer anpassen: `add_filter( 'wpkb_step_seconds', fn() => 10 );`
