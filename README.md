# WordPress-Themes und -Plugins

Sammlung eigener WordPress-Themes und -Plugins. Alle Pakete sind auf Deutsch, brauchen keine weiteren Plugins und lassen sich im Backend anpassen.

**Installieren:** Die passende `.zip`-Datei herunterladen und in WordPress hochladen,
Themes unter *Design → Themes → Theme hinzufügen → Theme hochladen*,
Plugins unter *Plugins → Installieren → Plugin hochladen*.
Bei einem Update bietet WordPress an, die vorhandene Version zu ersetzen. Die Einstellungen bleiben dabei erhalten.

| Paket | Art | Version | Datei |
|---|---|---|---|
| [einfaches-template-wx](#einfaches-template-wx) | Theme | 1.9.0 | `einfaches-template-wx.zip` |
| [LSV Flugplatz](#lsv-flugplatz) | Theme | 1.2.0 | `lsv-flugplatz.zip` |
| [Einfacher Kalender](#einfacher-kalender) | Plugin | 1.3.1 | `einfacher-kalender-wx.zip` |
| [Soft Blur Lightbox](#soft-blur-lightbox) | Plugin | 1.0.0 | `soft-blur-lightbox-wx.zip` |
| [Webcam Archiv](#webcam-archiv) | Plugin | 1.4.0 | `webcam-archiv-1.4.0.zip` |
| [LSV Wetter am Platz](#lsv-wetter-am-platz) | Plugin | 1.1.0 | `lsv-wetter-plugin.zip` |
| [WP Komplett-Backup & Umzug](#wp-komplett-backup--umzug) | Plugin | 1.0.0 | `wp-komplett-backup.zip` |

Alle Pakete benötigen PHP 7.4 oder neuer.

---

## einfaches-template-wx

Schlichtes Theme mit Logo oben rechts, zentriertem Hauptmenü mit gleitenden Untermenüs und einem Footer-Menü für Datenschutz und Impressum. Die Plugins *Einfacher Kalender* und *Soft Blur Lightbox* sind bereits eingebaut.

- **Farbschemata:** Marineblau, Petrol, Waldgrün, Bordeaux, Aubergine, Graphit & Bernstein oder eigene Farben (zwei Hintergründe und eine Akzentfarbe, der Rest wird automatisch passend berechnet)
- **Hell/Dunkel:** automatisch nach Geräteeinstellung, fest hell oder fest dunkel; optional ein Umschalter für Besucher und ein eigenes Logo für das helle Design
- **Schriften:** 13 kostenlose Schriften, getrennt wählbar für Text, Überschriften und die Titel der „Aktuelles“-Karten. Alle Schriften liegen im Theme, es werden keine Google Fonts geladen.
- **„Aktuelles“:** Beiträge erscheinen als Karten; ohne Beitragsbild wird automatisch das erste Bild aus dem Beitrag gezeigt
- **Einstellbar:** Logohöhe, Inhaltsbreite und Abrundung der Bildecken

Einstellungen: *Design → Customizer* (Abschnitte „Farbschema“, „Typografie“ und „Theme-Optionen“).

Kalender und Lightbox funktionieren wie die einzelnen Plugins (gleiche Shortcodes und Einstellungen). Sind die Plugins zusätzlich aktiv, übernehmen sie die Aufgabe; sie können dann deaktiviert werden.

Der Quellcode liegt entpackt unter [`themes/einfaches-template-wx/`](themes/einfaches-template-wx/).

## LSV Flugplatz

Eigenständiges Theme für lsv-vilsbiburg.de im Stil der Wetterseite wetter.lsv-vilsbiburg.de. Es ersetzt Twenty Twenty-One und übernimmt beim Aktivieren Logo, Menüs und Hintergrundbild automatisch.

- Menüs und Widget-Bereich an denselben Stellen wie bei Twenty Twenty-One; Widgets erscheinen als Karten in der Fußzeile
- Block-Stile „LSV Karte“ (Gruppe, Bild), „LSV Kachel“ (Kachel wie QNH/QFE auf der Wetterseite) und „Ohne Karten“ (Spalten)
- Farbpalette mit den LSV-Farben im Block-Editor
- Eingebaute Wetteranzeige: Vorlagen „Wetter am Platz“ unter *Vorlagen → LSV* oder Shortcode `[lsv_wetter]`. Ist das Plugin *LSV Wetter am Platz* aktiv, übernimmt es die Anzeige.

Einstellungen: *Design → Customizer → „LSV Kopf- & Fußzeile“* (Kürzel, Titel, Unterzeile, Button oben rechts, Fußzeilentext, Adresse der Wetterseite).

## Einfacher Kalender

Schlanker Terminkalender mit Kategorien und den Ansichten Liste, Monat und Jahr.

- Termine mit Datum, optionalem Enddatum, Uhrzeit, Ort, Kategorie und Beschreibung; jeder Termin hat eine eigene Seite unter `/termine/…`
- Kategorien mit eigener Farbe, Kategorie-Filter und Legende
- Monatsansicht mit den nächsten fünf Terminen, Listenansicht nach Monaten gruppiert, Blättern zwischen den Jahren
- Farben passen sich automatisch dem Theme an (auch dunkle Themes); Akzentfarbe unter *Kalender → Einstellungen*

Shortcodes:

```
[einfacher_kalender]                         Kalender mit Ansichts-Umschalter
[einfacher_kalender ansicht="jahr"]          Startansicht: liste, monat oder jahr
[einfacher_kalender kategorie="sport" filter="nein" auswahl="nein"]
[termine_liste anzahl="5"]                   die nächsten Termine, z. B. für die Seitenleiste
```

## Soft Blur Lightbox

Vergrößert Bilder beim Anklicken; der Hintergrund wird dabei sanft weichgezeichnet.

- Funktioniert automatisch mit Bild- und Galerie-Blöcken (Einstellung „Link zu: Mediendatei“) und klassischen Galerien
- Blättern per Pfeiltasten, Buttons oder Wischen; schließen mit Esc, Klick daneben oder Wischen nach unten
- Bildunterschriften, Bildzähler, Vorladen der Nachbarbilder
- Barrierearm und ohne jQuery (ca. 10 KB)

Einstellungen: *Einstellungen → Lightbox* (u. a. Stärke der Unschärfe).
Manuell nutzbar mit `data-lightbox` an Links, ausschließen mit `class="no-lightbox"`.

## Webcam Archiv

Speichert Webcam-Bilder automatisch und macht sie über einen Kalender wieder abrufbar.

- Beliebig viele Kameras, jeweils mit eigener Quelle, eigenem Intervall und Zeitfenster
- Bilder per Snapshot-URL (optional mit Passwort) oder aus einem Server-Ordner (z. B. FTP-Upload der Kamera)
- Nachtfilter: nachts nach Sonnenstand pausieren und dunkle oder Infrarot-Bilder verwerfen
- Überspringt unveränderte Bilder, erzeugt Vorschaubilder, löscht alte Bilder nach einstellbarer Zeit
- Kalender mit markierten Tagen, Tagesansicht mit Zeitleiste, Zeitraffer, Vollbild und Kamera-Umschalter; beim Tageswechsel bleibt die Uhrzeit gleich
- Teilbare Links auf einen bestimmten Tag und eine bestimmte Kamera
- Farben im Backend anpassbar (Schemas LSV-Blau, Hell, Anthrazit, Grün)

Einstellungen: *Einstellungen → Webcam Archiv*.

```
[webcam_archiv]                                   Archiv mit allen Kameras
[webcam_archiv kamera="cam2" tempo="6" titel="Webcams EDMP"]
[webcam_aktuell kamera="cam2" refresh="60"]       neuestes Bild, aktualisiert sich selbst
```

Tipp: Für exakte Aufnahme-Intervalle statt WP-Cron einen echten Cronjob auf die Trigger-URL aus den Einstellungen einrichten (siehe `readme.txt` im Plugin).

## LSV Wetter am Platz

Zeigt die aktuellen Wetterdaten vom Flugplatz Vilsbiburg (EDMP) auf jeder Seite. Die Daten kommen von wetter.lsv-vilsbiburg.de.

- Wind mit Richtungspfeil, Piste in Benutzung mit Gegen- und Seitenwind, Temperatur, Taupunkt, QNH, QFE, Wolkenbasis, Dichtehöhe und Luftfeuchte
- Aktualisiert sich jede Minute; Hinweis „Daten veraltet“, wenn die Station länger als 10 Minuten nichts liefert
- Block „Wetter am Platz“ im Editor und gelbes Pistenschild
- Jeder Wert ist auch einzeln als Shortcode verfügbar, z. B. für Fließtext
- Funktioniert mit jedem Theme

Einstellungen und Übersicht aller aktuellen Werte: *Einstellungen → LSV Wetter*.

```
[lsv_wetter]                     alle Werte ([lsv_wetter kompakt="ja"] nur die wichtigsten)
[lsv_landerichtung wind="ja"]    gelbes Pistenschild mit Wind
Aktuell [lsv_wind_komplett], QNH [lsv_qnh]
```

Die vollständige Liste der Einzelwerte steht in der `readme.txt` des Plugins.

## WP Komplett-Backup & Umzug

Sichert eine komplette WordPress-Seite in eine einzige Datei und spielt sie auf demselben oder einem anderen Server wieder ein.

- Sichert die Datenbank, `wp-content` (Themes, Plugins, Uploads) und die WordPress-Kerndateien; einzelne Ordner lassen sich ausschließen
- Arbeitet in kurzen Schritten von etwa 20 Sekunden und funktioniert damit auch bei großen Seiten auf einfachem Webspace
- Große Backups werden in Teilen hochgeladen, das Upload-Limit des Servers spielt keine Rolle
- Beim Umzug werden Domain, http/https, Server-Pfade und Tabellen-Präfix automatisch angepasst, auch in Widgets, Page-Buildern und Theme-Einstellungen
- Die alte Datenbank wird erst ganz am Ende ausgetauscht; bricht der Vorgang vorher ab, bleibt die Seite unverändert
- `wp-config.php` und `.htaccess` des Zielservers werden nie überschrieben
- Das Backup ist eine normale `.tar`-Datei und lässt sich auch mit 7-Zip oder WinRAR öffnen

Bedienung: *Werkzeuge → Komplett-Backup*.

**Kurzanleitung: Seite auf einen anderen Server umziehen**

1. Auf der alten Seite das Plugin installieren, unter *Werkzeuge → Komplett-Backup* auf „Backup jetzt erstellen“ klicken und die `.tar`-Datei herunterladen.
2. Auf dem neuen Server WordPress frisch installieren (Tabellen-Präfix beliebig) und dieses Plugin installieren und aktivieren.
3. Unter *Werkzeuge → Komplett-Backup* die `.tar`-Datei hochladen. Alternativ per FTP nach `wp-content/wpkb-backups/` kopieren.
4. Beim Backup auf „Wiederherstellen“ klicken, die Bestandteile wählen und bestätigen. Das Fenster bis zum Ende geöffnet lassen.
5. Mit den Zugangsdaten der **alten** Seite anmelden und unter *Einstellungen → Permalinks* einmal speichern.

Für sehr große Seiten per WP-CLI:

```
wp komplett-backup create                 Backup erstellen (--no-db, --no-content, --no-core, --exclude=…)
wp komplett-backup list                   vorhandene Backups anzeigen
wp komplett-backup restore <datei>        Backup einspielen (--no-db, --no-files, --no-core, --yes)
```

Hinweise: Das Backup enthält die `wp-config.php` der alten Seite mit den Datenbank-Zugangsdaten, also vertraulich behandeln. Unter nginx schützt die mitgelieferte `.htaccess` den Backup-Ordner nicht (Abhilfe siehe `readme.txt` im Plugin). Multisite-Netzwerke werden nicht wiederhergestellt. Den ersten Umzug am besten auf einer Testseite ausprobieren.
