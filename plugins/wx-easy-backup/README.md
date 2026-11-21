# WX Easy Backup

Sichert eine komplette WordPress-Seite in eine einzige Datei und spielt sie auf demselben oder einem anderen Server wieder ein.

- Sichert die Datenbank, `wp-content` (Themes, Plugins, Uploads) und die WordPress-Kerndateien; einzelne Ordner lassen sich ausschließen
- Arbeitet in kurzen Schritten von etwa 20 Sekunden und funktioniert damit auch bei großen Seiten auf einfachem Webspace
- Große Backups werden in Teilen hochgeladen, das Upload-Limit des Servers spielt keine Rolle
- Beim Umzug werden Domain, http/https, Server-Pfade und Tabellen-Präfix automatisch angepasst, auch in Widgets, Page-Buildern und Theme-Einstellungen
- Die alte Datenbank wird erst ganz am Ende ausgetauscht; bricht der Vorgang vorher ab, bleibt die Seite unverändert
- `wp-config.php` und `.htaccess` des Zielservers werden nie überschrieben
- Das Backup ist eine normale `.tar`-Datei und lässt sich auch mit 7-Zip oder WinRAR öffnen

Bedienung: *Werkzeuge → WX Easy Backup*. Backups der alten Version „WP Komplett-Backup & Umzug“ lassen sich weiterhin einspielen.

**Kurzanleitung: Seite auf einen anderen Server umziehen**

1. Auf der alten Seite das Plugin installieren, unter *Werkzeuge → WX Easy Backup* auf „Backup jetzt erstellen“ klicken und die `.tar`-Datei herunterladen.
2. Auf dem neuen Server WordPress frisch installieren (Tabellen-Präfix beliebig) und dieses Plugin installieren und aktivieren.
3. Unter *Werkzeuge → WX Easy Backup* die `.tar`-Datei hochladen. Alternativ per FTP nach `wp-content/wpkb-backups/` kopieren.
4. Beim Backup auf „Wiederherstellen“ klicken, die Bestandteile wählen und bestätigen. Das Fenster bis zum Ende geöffnet lassen.
5. Mit den Zugangsdaten der **alten** Seite anmelden und unter *Einstellungen → Permalinks* einmal speichern.

Für sehr große Seiten per WP-CLI (der alte Befehl `wp komplett-backup` funktioniert weiterhin):

```
wp wx-easy-backup create                  Backup erstellen (--no-db, --no-content, --no-core, --exclude=…)
wp wx-easy-backup list                    vorhandene Backups anzeigen
wp wx-easy-backup restore <datei>         Backup einspielen (--no-db, --no-files, --no-core, --yes)
```

Hinweise: Das Backup enthält die `wp-config.php` der alten Seite mit den Datenbank-Zugangsdaten, also vertraulich behandeln. Unter nginx schützt die mitgelieferte `.htaccess` den Backup-Ordner nicht (Abhilfe siehe `readme.txt` im Plugin). Multisite-Netzwerke werden nicht wiederhergestellt. Den ersten Umzug am besten auf einer Testseite ausprobieren.

Zurück zur [Übersicht](../../README.md).
