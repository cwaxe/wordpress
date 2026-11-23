=== WX Easy Maintenance ===
Tags: maintenance, wartungsmodus, coming soon, 503
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later

Schaltet die Website mit einem Klick in den Wartungsmodus.

== Beschreibung ==

* Wartungsseite mit HTTP-Status 503 und Retry-After, damit Suchmaschinen die Seite nicht abwerten
* Angemeldete Benutzer mit der gewählten Berechtigung sehen die normale Seite
* Optionales Enddatum, danach ist die Seite automatisch wieder erreichbar
* Schnell-Umschalter in der Admin-Leiste, rot solange der Wartungsmodus aktiv ist
* Überschrift, Nachricht und Farben anpassbar
* Eigenes Design über eine Datei wartungsmodus.php im Theme
* Login, REST-API, AJAX, Cron und WP-CLI bleiben erreichbar

== Installation ==

1. ZIP unter Plugins → Installieren → Plugin hochladen installieren und aktivieren.
2. Einstellungen → Wartungsmodus öffnen, „Wartungsmodus aktivieren“ anhaken und speichern.

== Für Entwickler ==

* PHP-Filter: add_filter( 'wxem_bypass', fn() => is_page( 'impressum' ) ); – Wartungsseite für bestimmte Anfragen überspringen
