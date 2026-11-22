# Änderungen

Ein Abschnitt je Paket und Version, Überschrift `## <paket> <version>`.
Der Abschnitt wird beim Release als Beschreibung übernommen.

## wx-easy-template 1.12.0

- Updates direkt aus dem GitHub-Repository: Neue Versionen erscheinen unter *Dashboard → Aktualisierungen* und *Design → Themes* und lassen sich mit einem Klick oder automatisch installieren.
- Ist das Theme in einem anderen Ordner installiert (z. B. noch „dunkel-schlicht“), ersetzt das Update trotzdem das bestehende Theme.

## wx-easy-template 1.11.0

- Eingebauter Kalender auf dem Stand von WX Easy Calendar 2.0.1: Wochenansicht, Kategorie-Filter mit Mehrfachauswahl, Suche, Bildvorschau beim Darüberfahren, Vorschaubilder, Detailfenster und vergrößerbare Bilder.
- Im Backend: Serien an mehreren Wochentagen oder monatlich mit Ausfallterminen, Orte mit Adresse, Veranstalter, freie Ortseingabe, Termin kopieren und *Kalender → Aufräumen*.
- „In Kalender eintragen“, Kalender-Abo (iCal), Karten-Link und Daten für die Google-Suche.
- Farben folgen weiter dem gewählten Farbschema in Hell und Dunkel; Bilder öffnen sich in der eingebauten Lightbox.
- Bestehende Termine, Kategorien, Einstellungen und Shortcodes bleiben unverändert. Benötigt WordPress 6.2 oder neuer.

## wx-easy-calendar 2.0.1

- Erkennt die im WX Easy Template eingebaute Lightbox und nutzt sie statt der eigenen, damit sich Bilder nicht doppelt öffnen.

## wx-easy-calendar 2.0.0

Übernimmt die Verbesserungen aus dem Terminkalender, im bisherigen WX-Aussehen. Vorhandene Termine, Kategorien, Farben, Einstellungen und Shortcodes bleiben unverändert.

- **Termine & Backend:** übersichtliche Eingabe direkt unter dem Titel mit Beginn/Ende, Uhrzeit bis, Schalter „Ganztägig“. Termine ohne Uhrzeit gelten wie bisher als ganztägig.
- **Serien:** jede Woche oder alle 2 Wochen an mehreren Tagen (z. B. Dienstag und Donnerstag), monatlich am gleichen Tag, am 2. Samstag oder am letzten Freitag, mit Enddatum und Ausfallterminen.
- **Orte und Veranstalter:** Orte mit Adresse und Veranstalter mit Telefon, E-Mail und Webseite als Listen zum Auswählen oder direkt neu anlegen. Den Ort kann man auch frei eingeben, nur für diesen Termin; bisherige Ortsangaben bleiben so erhalten.
- **Termin kopieren**, Terminliste im Backend mit „Kommende / Vergangene“, Spalte für Serien.
- **Aufräumen:** neue Seite *Kalender → Aufräumen* löscht alte Termine (älter als 1–24 Monate) in den Papierkorb oder endgültig, optional täglich automatisch.
- **Ansichten & Filter:** neue Wochenansicht (Liste | Woche | Monat | Jahr), auf dem Handy als Tagesliste. Kategorie-Filter mit Mehrfachauswahl als Knöpfe und eine Suche.
- **Bilder:** Beitragsbild als Vorschaubild in der Liste und in der Woche, Vorschau beim Darüberfahren mit der Maus, Detailfenster beim Anklicken eines Termins. Alle Bilder auf der Terminseite lassen sich anklicken und vergrößern. Ist WX Lightbox aktiv, übernimmt sie, sonst eine eigene kleine Lightbox.
- **Teilen & Google:** „In Kalender eintragen“ (.ics) pro Termin, Kalender-Abo (iCal) für alle Termine, Karten-Link zur Adresse, Link „Zum Kalender“ und strukturierte Daten (schema.org/Event) für die Google-Suche.
- Neue Shortcode-Optionen: `ansicht="woche"`, `suche="nein"`, `[termine_liste bilder="ja"]`.
- Benötigt WordPress 6.2 oder neuer.

## terminkalender 1.3.1

- Kurzanleitung für Redakteure beigelegt: als PDF im Plugin (verlinkt unter *Termine → Einstellungen*) und zum Lesen auf GitHub unter `plugins/terminkalender/docs/ANLEITUNG.md`.

## terminkalender 1.3.0

- Neue Seite *Termine → Aufräumen*: alte Termine (älter als 1, 3, 6, 12 oder 24 Monate) in den Papierkorb verschieben oder endgültig löschen. Die Anzahl steht vorher dabei, es wird jedes Mal nachgefragt.
- Bei Serien zählt das Ende der Serie; laufende Serien bleiben erhalten. Kategorien, Orte, Veranstalter und Bilder bleiben unverändert.
- Optional automatisch: täglich alte Termine in den Papierkorb verschieben.

## terminkalender 1.2.0

- Neue Wochenansicht (Monat | Woche | Liste), auf dem Handy als Tagesliste. Auch als Startansicht und per `[terminkalender ansicht="woche"]`.
- Bildvorschau: beim Darüberfahren mit der Maus, als Vorschaubild in der Terminliste und in der Woche, groß im Detailfenster.
- Bilder sind automatisch anklickbar und öffnen sich vergrößert – in der Terminbeschreibung, beim Beitragsbild und im Detailfenster. Ist WX Lightbox aktiv, übernimmt sie, sonst eine eigene kleine Lightbox.
- Ort frei eingeben: Ort und optional Adresse nur für diesen Termin, ohne ihn in der Ortsliste zu speichern.

## terminkalender 1.1.0

- Wöchentliche Serien an mehreren Tagen, z. B. jeden Dienstag und Donnerstag oder alle 2 Wochen am Montag, Mittwoch und Freitag.
- Der Wochentag des Beginns ist immer dabei; weitere Tage per Klick dazuwählen.

## terminkalender 1.0.0

- Erste Version: eigener Veranstaltungskalender mit vereinfachtem Backend (Termine, Kategorien mit Farbe, Orte mit Adresse, Veranstalter mit Kontakt, Bild, einfache Serien).
- Monats- und Listenansicht, Kategorie-Filter mit Mehrfachauswahl, Suche, Detailfenster, eigene Terminseiten, iCal-Export und -Abo.
- Übernimmt alle Daten aus „The Events Calendar“ und leitet dessen alte Adressen weiter.

## wx-easy-template 1.10.1

- Hauptmenü und Seiten-Navigation zeigen beliebig viele Ebenen statt höchstens drei.
- Ruhigere Untermenüs mit erkennbarem aktivem Pfad; tiefere Ebenen werden nicht mehr abgeschnitten.
- Untermenüs am rechten Bildschirmrand öffnen nach links.
- Mobiles Menü mit Trennlinien, Einrückung und größeren Untermenü-Buttons.

## wx-easy-template 1.10.0

- Neuer Name: WX Easy Template (vorher einfaches-template-wx, Ordner dunkel-schlicht).
- Übernimmt beim ersten Aktivieren die Customizer-Einstellungen, Menüs, Logo und das zusätzliche CSS der Vorgänger-Version.

## wx-easy-calendar 1.4.0

- Neuer Name: WX Easy Calendar (vorher Einfacher Kalender). Termine, Kategorien, Einstellungen und Shortcodes bleiben gleich.
- Schaltet eine noch aktive Vorgänger-Version beim Aktivieren selbst ab.

## wx-lightbox 1.1.0

- Neuer Name: WX Lightbox (vorher Soft Blur Lightbox). Einstellungen bleiben erhalten.
- Schaltet eine noch aktive Vorgänger-Version beim Aktivieren selbst ab und verhindert, dass sie beim Löschen die Einstellungen entfernt.

## wx-webcam-archive 1.5.0

- Neuer Name: WX Webcam Archive (vorher Webcam Archiv). Kameras, Bilder, Einstellungen und Shortcodes bleiben gleich.
- Schaltet eine noch aktive Vorgänger-Version beim Aktivieren selbst ab und verhindert, dass sie beim Löschen Einstellungen und Bilderliste entfernt.

## wx-easy-backup 1.1.0

- Neuer Name: WX Easy Backup (vorher WP Komplett-Backup & Umzug). Backups der alten Version lassen sich weiter einspielen.
- WP-CLI-Befehl heißt jetzt `wp wx-easy-backup`, `wp komplett-backup` funktioniert weiterhin.
- Beim Einspielen eines alten Backups wird die alte Plugin-Version nicht wieder angelegt, sondern durch WX Easy Backup ersetzt.

## lsv-flugplatz 1.2.0

- Erste Version in diesem Repo.

## lsv-wetter 1.1.0

- Erste Version in diesem Repo.
