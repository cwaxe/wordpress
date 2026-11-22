=== WX Webcam Archive ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.6.0
License: GPL-2.0-or-later

Archiviert Webcam-Bilder automatisch und macht sie über einen Kalender wieder abrufbar.

== Funktionen ==

* Beliebig viele Kameras, jede mit eigener Quelle, Intervall und Zeitfenster
* Automatischer Abruf eines Kamera-Snapshots per URL (optional mit HTTP-Basic-Auth)
* Alternativ/zusätzlich: Import aus einem Server-Ordner (z. B. FTP-Upload der Kamera),
  Aufnahmezeit aus EXIF, Dateiname oder Dateidatum
* Frei wählbares Intervall und Aufnahme-Zeitfenster (z. B. nur 06:00–21:00)
* Nachtfilter: nachts nach Sonnenstand gar nicht abrufen und/oder zu dunkle bzw.
  Infrarot-Schwarzweißbilder automatisch verwerfen
* Überspringt unveränderte Bilder, erzeugt Vorschaubilder, optionales Verkleinern
* Automatische Bereinigung nach X Tagen, Tage manuell löschbar
* Frontend-Kalender: Tage mit Bildern sind markiert (Intensität = Anzahl Bilder),
  Tagesansicht mit Zeitleiste, Vorschaustreifen, Zeitraffer, Vollbild, Tastatur & Wischen
* Beim Tageswechsel wird die gleiche Uhrzeit angezeigt – ideal zum Vergleichen
* Kamera-Umschalter im Kalender – Tag und Uhrzeit bleiben beim Wechsel erhalten
* Teilbare Links: …/archivseite/#wca=2026-11-09&kamera=cam2
* Farben im Backend anpassbar (Farbwähler, fertige Schemas, Live-Vorschau)
* Optional nur für angemeldete Benutzer

== Installation ==

1. Ordner `wx-webcam-archive` nach `wp-content/plugins/` hochladen
   (oder ZIP über Plugins → Installieren → Plugin hochladen).
2. Plugin aktivieren.
3. Unter Einstellungen → WX Webcam Archive die Bildquelle eintragen und speichern.
4. Mit „Jetzt Bild abrufen“ testen.
5. Auf einer Seite den Shortcode einfügen.

== Shortcodes ==

[webcam_archiv]
  Alle Kameras mit Umschalter.
  Optional: kamera="cam2"        (nur diese Kamera; mehrere: "cam1,cam3")
            datum="2026-11-09"  (Starttag)
            tempo="6"           (Zeitraffer, Bilder pro Sekunde)
            titel="Webcams EDMP" (Überschrift)
            rahmen="nein"       (ohne blauen Hintergrund)

[webcam_aktuell kamera="cam2" refresh="60" archiv="/webcam-archiv/"]
  Zeigt das neueste Bild einer Kamera und aktualisiert es alle 60 Sekunden.

== Zuverlässige Aufnahmen ==

WP-Cron läuft nur, wenn die Website besucht wird. Für exakte Intervalle
einen echten Cronjob einrichten, der jede Minute die Trigger-URL aus den
Einstellungen aufruft – jede Kamera wird dann in ihrem eigenen Intervall abgerufen:

  * * * * * curl -s "https://example.de/wp-json/wca/v1/trigger?key=…" >/dev/null

und in wp-config.php: define( 'DISABLE_WP_CRON', true );

== Speicherort ==

wp-content/uploads/webcam-archiv/KAMERA/JJJJ/MM/TT/HHMMSS.jpg (+ thumbs/)
(Bilder aus Version 1.0 bleiben am alten Ort und werden Kamera „cam1“ zugeordnet.)

Beim Löschen des Plugins bleiben die Bilder erhalten. Sollen sie mit
gelöscht werden: define( 'WCA_DELETE_IMAGES_ON_UNINSTALL', true );

== REST-API ==

GET /wp-json/wca/v1/cameras
GET /wp-json/wca/v1/range?kamera=cam1
GET /wp-json/wca/v1/days?kamera=cam1&month=2026-11
GET /wp-json/wca/v1/images?kamera=cam1&date=2026-11-09
GET /wp-json/wca/v1/latest?kamera=cam1

== Changelog ==

= 1.4.0 =
* Farben unter Einstellungen → WX Webcam Archive → Farben anpassbar; Schemas LSV-Blau, Hell, Anthrazit, Grün.

= 1.3.1 =
* Gelbe Akzente durch Hellblau ersetzt.

= 1.3.0 =
* Neues Design im Stil von wetter.lsv-vilsbiburg.de; neue Anordnung mit Kopfzeile, Bild neben Kalender, Info-Kacheln und Tagesverlauf.
* Shortcode-Optionen titel="…" und rahmen="nein".

= 1.2.0 =
* Nachtfilter je Kamera (Sonnenstand, Helligkeit, Infrarot-Erkennung).

= 1.1.0 =
* Mehrere Kameras; bestehende Einstellungen und Bilder werden automatisch als „Webcam 1“ (cam1) übernommen.

= 1.0.0 =
* Erste Version.
