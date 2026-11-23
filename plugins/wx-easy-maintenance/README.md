# WX Easy Maintenance

Schaltet die Website mit einem Klick in den Wartungsmodus. Besucher sehen eine schlichte Wartungsseite, angemeldete Benutzer arbeiten ganz normal weiter.

- Wartungsseite mit HTTP-Status 503 und `Retry-After`, damit Suchmaschinen die Seite nicht abwerten
- Wer die normale Seite sieht, ist einstellbar: nur Administratoren, Redakteure, Mitarbeiter oder alle angemeldeten Benutzer
- Optionales Enddatum, danach ist die Seite automatisch wieder erreichbar
- Schnell-Umschalter in der Admin-Leiste, rot solange der Wartungsmodus aktiv ist, dazu ein Hinweis im Backend
- Überschrift, Nachricht und Farben anpassbar
- Login, REST-API, AJAX, Cron und WP-CLI bleiben erreichbar

Einstellungen: *Einstellungen → Wartungsmodus*.
Eigenes Design: eine Datei `wartungsmodus.php` im Theme ersetzt die eingebaute Seite. Ausnahmen per Filter `wxem_bypass`.

Zurück zur [Übersicht](../../README.md).
