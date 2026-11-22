# WX Easy Calendar

Schlanker Terminkalender mit Kategorien und den Ansichten Liste, Woche, Monat und Jahr – passt sich Schrift und Farben des Themes an.

- Termine mit Beginn, Ende, Uhrzeit oder ganztägig, Ort, Veranstalter, Kategorie, Bild und Beschreibung; jeder Termin hat eine eigene Seite unter `/termine/…`
- Serien: wöchentlich oder alle 2 Wochen an mehreren Tagen, monatlich am gleichen Tag, am n-ten oder letzten Wochentag; mit Enddatum und Ausfallterminen
- Orte mit Adresse und Veranstalter mit Kontakt als Auswahllisten; Ort auch frei eingeben, nur für einen Termin
- Kategorien mit eigener Farbe, Filter mit Mehrfachauswahl, Suche
- Vorschau beim Darüberfahren, Detailfenster, Vorschaubilder; Bilder auf der Terminseite lassen sich vergrößern (mit WX Lightbox oder eigener Lightbox)
- „In Kalender eintragen“ (.ics), Kalender-Abo (iCal), Karten-Link, strukturierte Daten für Google
- Termin kopieren, alte Termine aufräumen (auch automatisch)
- Farben passen sich automatisch dem Theme an (auch dunkle Themes); Akzentfarbe unter *Kalender → Einstellungen*

Shortcodes:

```
[einfacher_kalender]                         Kalender mit Ansichts-Umschalter, Filter und Suche
[einfacher_kalender ansicht="woche"]         Startansicht: liste, woche, monat oder jahr
[einfacher_kalender kategorie="sport" filter="nein" auswahl="nein" suche="nein"]
[termine_liste anzahl="5"]                   die nächsten Termine, z. B. für die Seitenleiste
[termine_liste anzahl="3" bilder="ja"]       mit Vorschaubildern
```

Kalender-Abo für Outlook, Google oder Apple: `https://deine-seite.de/?ek_ics=alle`

Zurück zur [Übersicht](../../README.md).
