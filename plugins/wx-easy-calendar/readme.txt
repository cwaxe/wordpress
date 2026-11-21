=== WX Easy Calendar ===
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later

Ein schlanker Termin-Kalender mit Kategorien und Listen-, Monats- und Jahresansicht. Ohne Abhängigkeiten.

== Installation ==

1. Im WordPress-Backend unter "Plugins > Installieren > Plugin hochladen" die ZIP-Datei hochladen.
2. Plugin aktivieren.
3. Unter "Kalender > Kategorien" Kategorien anlegen und je eine Farbe wählen.
4. Unter "Kalender > Neuer Termin" Termine anlegen (Titel, Beschreibung, Datum, optional Enddatum, Uhrzeit, Ort, Kategorie).

== Verwendung ==

Kalender mit Umschalter Liste / Monat / Jahr und Kategorie-Filter:
[einfacher_kalender]

Die Monatsansicht zeigt die nächsten bis zu fünf Termine ab heute über dem Monatsraster.
Die Listenansicht zeigt alle Termine des angezeigten Jahres chronologisch nach Monat
gruppiert. Standardmäßig öffnet sich das laufende Jahr. Mit den Pfeilen neben der
Jahreszahl wechselst du zum vorherigen oder nächsten Jahr.

Optionen (alle optional):
  ansicht="monat"      Startansicht: liste, monat oder jahr
  auswahl="nein"       Umschalter für die Ansichten ausblenden
  kategorie="sport"    nur bestimmte Kategorien zeigen (mehrere mit Komma: "sport,kultur")
  filter="nein"        Kategorie-Auswahl ausblenden

Beispiele:
[einfacher_kalender ansicht="jahr"]
[einfacher_kalender ansicht="liste" auswahl="nein" kategorie="vereinsleben"]

Liste der nächsten Termine (z. B. in der Seitenleiste):
[termine_liste anzahl="5"]
[termine_liste anzahl="3" kategorie="sport"]

Jeder Termin hat eine eigene Seite unter /termine/termin-name/ mit Datum, Uhrzeit, Ort und Kategorie über der Beschreibung.

== Farben anpassen ==

Schrift, Hintergrund und Rahmen übernimmt der Kalender automatisch vom Theme.

Die Akzentfarbe (Navigation, "Heute", Termine ohne Kategorie) stellst du unter
"Kalender > Einstellungen" ein:
  - Automatisch vom Theme (Standard): nimmt die Farbe "primary" bzw. "accent" aus der Theme-Palette
  - eine bestimmte Farbe aus der Theme-Palette
  - eine eigene Farbe oder den Plugin-Standard (Blau)
Theme-Farben werden als CSS-Variable eingebunden und ändern sich mit, wenn du sie im Theme änderst.

Jede Kategorie hat ihre eigene Farbe (Kalender > Kategorien). Der Farbwähler bietet die
Theme-Farben als Vorschläge an. Die Schriftfarbe auf farbigen Flächen (Weiß oder Dunkel)
wird automatisch so gewählt, dass sie lesbar bleibt.

== Changelog ==

= 1.3.1 =
* Pfeile in der Listenansicht wechseln zum vorherigen oder nächsten Jahr.

= 1.3.0 =
* Die nächsten fünf Termine stehen über dem Monatsraster.
* Die Listenansicht gruppiert alle Termine des laufenden Jahres nach Monat.

= 1.2.0 =
* Einstellungsseite: Akzentfarbe automatisch vom Theme oder aus der Theme-Palette
* Theme-Farben als Vorschläge im Kategorie-Farbwähler
* Schriftfarbe auf farbigen Flächen passt sich automatisch an (hell/dunkel)

= 1.1.1 =
* Lesbarkeit auf dunklen Themes: Farben passen sich der Textfarbe des Themes an

= 1.1.0 =
* Kategorien mit eigener Farbe, Kategorie-Filter und Legende
* Ansichten Liste, Monat und Jahr mit Umschalter

= 1.0.0 =
* Erste Version: Termine, Monatskalender, Terminliste
