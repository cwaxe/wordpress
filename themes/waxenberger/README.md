# Waxenberger IT-Service

Theme für [edv-waxenberger.de](https://edv-waxenberger.de), nachgebaut nach der früheren Joomla-Seite (Template JoomSpirit 99): hellblauer Kopfbereich mit zentriertem Logo und Menü, darunter zentrierter Inhalt in Dunkelblau.

- **Aussehen wie bisher:** Farben, Maße, Menü mit Linie über dem aktiven Punkt, Schrift Arial. Es werden keine Google Fonts und keine Dienste von Drittanbietern geladen, auch das Emoji-Skript von WordPress ist abgeschaltet.
- **Animationen wie bisher:** Der Inhalt blendet beim Öffnen einer Seite ein und gleitet 12 px von oben herein (1,33 s). Das Menü bleibt am Desktop beim Scrollen oben stehen, ab 200 px erscheint unten rechts ein „Nach oben“-Pfeil. Bei „Bewegung reduzieren“ wird nur eingeblendet.
- **Logo und Favicon:** Das Logo liegt im Theme und lässt sich unter *Design → Customizer → Website-Informationen* ersetzen. Das Favicon (das „x“ mit dem Bogen aus dem Logo) wird verwendet, solange dort kein eigenes Website-Icon eingestellt ist.
- **Werbeseite:** Gestaltungsklassen für Seiten wie die Gemeinde-App-Seite (Gruppe mit der CSS-Klasse `wx-app`, darin `wx-section`, `wx-cards`/`wx-card`, `wx-phones`/`wx-phone`, `wx-split`, `wx-cta`, `wx-teaser`).
- **Alte Adressen:** Beim Speichern der Permalinks trägt das Theme Weiterleitungen von `/index.php/service`, `/index.php/impressum` und `/index.php/datenschutz` in die `.htaccess` ein.
- **Fußzeile:** Text unter *Design → Customizer → Fußzeile*.

Menü: *Design → Menüs*, Position „Hauptmenü“. Auf der Startseite wird der Seitentitel ausgeblendet.

Der Ordner `inhalte/` enthält die Seiteninhalte (Willkommen, Service, Impressum, Datenschutz, Gemeinde-App) als Block-Code zum Einfügen im Code-Editor und eine Einrichtungsanleitung.

**Updates:** Das Theme fragt die Releases in diesem Repository ab. Neue Versionen erscheinen unter *Dashboard → Aktualisierungen* und *Design → Themes*. Version 1.3.0 muss einmal von Hand hochgeladen werden, alle folgenden kommen automatisch.

Zurück zur [Übersicht](../../README.md).
