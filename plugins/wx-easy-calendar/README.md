# WX Easy Calendar

Schlanker Kalender mit Kategorien und den Ansichten Liste, Woche, Monat und Jahr – passt sich Schrift und Farben des Themes an.

- Termine mit Beginn, Ende, Uhrzeit oder ganztägig, Ort, Veranstalter, Kategorie, Bild und Beschreibung; jeder Termin hat eine eigene Seite unter `/termine/…`
- Serien: wöchentlich oder alle 2 Wochen an mehreren Tagen, monatlich am gleichen Tag, am n-ten oder letzten Wochentag; mit Enddatum und Ausfallterminen
- Orte mit Adresse und Veranstalter mit Kontakt als Auswahllisten; Ort auch frei eingeben, nur für einen Termin
- Kategorien mit eigener Farbe, Filter mit Mehrfachauswahl, Suche
- Vorschau beim Darüberfahren, Detailfenster, Vorschaubilder; Bilder auf der Terminseite lassen sich vergrößern (mit WX Lightbox oder eigener Lightbox)
- „In Kalender eintragen“ (.ics), Kalender-Abo (iCal), Karten-Link, strukturierte Daten für Google
- Termin kopieren, alte Termine aufräumen (auch automatisch)
- Farben passen sich automatisch dem Theme an (auch dunkle Themes); Akzentfarbe unter *Kalender → Einstellungen*
- Übernimmt auf Knopfdruck alle Daten aus „The Events Calendar“ (*Kalender → Einstellungen*) und leitet dessen alte Links weiter
- Übernimmt automatisch alle Daten aus dem früheren Plugin „Terminkalender“ (siehe unten)

Shortcodes:

```
[einfacher_kalender]                         Kalender mit Ansichts-Umschalter, Filter und Suche
[einfacher_kalender ansicht="woche"]         Startansicht: liste, woche, monat oder jahr
[einfacher_kalender kategorie="sport" filter="nein" auswahl="nein" suche="nein"]
[termine_liste anzahl="5"]                   die nächsten Termine, z. B. für die Seitenleiste
[termine_liste anzahl="3" bilder="ja"]       mit Vorschaubildern
```

Kalender-Abo für Outlook, Google oder Apple: `https://deine-seite.de/?ek_ics=alle`

**Umstieg vom Terminkalender:** WX Easy Calendar hochladen und aktivieren. Der Terminkalender wird abgeschaltet, beim nächsten Aufruf im Backend werden Termine, Serien, Kategorien (mit Farben), Orte, Veranstalter, Bilder und Einstellungen übernommen; ein Hinweis zeigt, wie viele. Gibt es eine Kategorie, einen Ort oder Veranstalter schon, werden beide zusammengelegt. `[terminkalender]` und `[veranstaltungskalender]` funktionieren weiter (Optionen `ansicht` und `kategorien`), alte Terminseiten (`/termin/…`) und iCal-Links (`?tk_ics=…`) werden weitergeleitet. Danach den Terminkalender löschen.

**Umstieg von The Events Calendar:** unter *Kalender → Einstellungen* auf „Jetzt übernehmen“ klicken (wiederholbar, nichts wird doppelt angelegt), prüfen, dann The Events Calendar deaktivieren. Alte Links (`/events/…`, `/event/…`, iCal) werden danach weitergeleitet.

**Anleitung für Redakteure:** [lesen](docs/ANLEITUNG.md) · [als PDF](docs/Kalender-Kurzanleitung.pdf) – die PDF ist auch unter *Kalender → Einstellungen* verlinkt.

Zurück zur [Übersicht](../../README.md).
