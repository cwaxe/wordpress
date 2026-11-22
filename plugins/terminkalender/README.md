# Terminkalender

Schlanker Veranstaltungskalender mit einfachem Backend. Übernimmt auf Knopfdruck alle Daten aus „The Events Calendar“.

- Backend auf das Nötigste reduziert: Titel, Box „Wann? Wo? Wer?“, Beschreibung, Kategorie, Bild
- Orte (mit Adresse) und Veranstalter (mit Kontakt) zum Auswählen oder direkt im Termin neu anlegen; ein Ort kann auch nur für einen Termin frei eingegeben werden
- Einfache Serien: wöchentlich oder alle 2 Wochen (auch an mehreren Tagen, z. B. Dienstag und Donnerstag), monatlich am gleichen Tag, am gleichen oder letzten Wochentag – mit Enddatum und Ausfallterminen
- Terminliste getrennt nach kommenden und vergangenen Terminen, „Kopieren“ je Termin
- Alte Termine aufräumen (*Termine → Aufräumen*): in den Papierkorb oder endgültig löschen, auf Wunsch täglich automatisch
- Kalender mit Monats-, Wochen- und Listenansicht, Bildvorschau, Kategorie-Filter mit Mehrfachauswahl, Suche, Vorschau beim Darüberfahren und Detailfenster
- Bilder öffnen sich per Klick vergrößert (nutzt WX Lightbox, wenn aktiv, sonst eine eigene kleine Lightbox)
- Eigene Terminseite mit Infokasten, Kartenlink, „In Kalender eintragen“ (.ics) und strukturierten Daten für Google
- Kalender-Abo (iCal) für alle Termine
- Schrift kommt aus dem Theme

Einbinden mit dem Shortcode `[terminkalender]` (auch `[veranstaltungskalender]`), Optionen `ansicht="woche"`, `ansicht="liste"` und `kategorien="slug1,slug2"`.
Einstellungen und Übernahme: *Termine → Einstellungen*.

**Umstieg von The Events Calendar:** Plugin aktivieren, unter *Termine → Einstellungen* auf „Jetzt übernehmen“ klicken (wiederholbar, nichts wird doppelt angelegt), prüfen, dann The Events Calendar deaktivieren. Alte Links (`/termine/…`, `/veranstaltung/…`, iCal) werden danach automatisch weitergeleitet.

Urheber: IT-Service Waxenberger

Zurück zur [Übersicht](../../README.md).
