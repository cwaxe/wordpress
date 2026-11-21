# WX Easy Calendar

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

Zurück zur [Übersicht](../../README.md).
