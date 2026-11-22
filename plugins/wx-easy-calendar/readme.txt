=== WX Easy Calendar ===
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later

Ein schlanker Termin-Kalender mit Kategorien, Serien, Orten und Veranstaltern und Listen-, Wochen-, Monats- und Jahresansicht. Ohne Abhängigkeiten.

== Installation ==

1. Im WordPress-Backend unter "Plugins > Installieren > Plugin hochladen" die ZIP-Datei hochladen.
2. Plugin aktivieren.
3. Unter "Kalender > Kategorien" Kategorien anlegen und je eine Farbe wählen.
4. Unter "Kalender > Neuer Termin" Termine anlegen: Titel, Beschreibung, Beginn und Ende
   (oder "Ganztägig"), Wiederholen, Ort, Veranstalter, Kategorie und Beitragsbild.

Orte und Veranstalter können direkt im Termin neu angelegt werden und stehen dann in der
Liste zur Auswahl ("Kalender > Orte", "Kalender > Veranstalter"). Einen Ort kann man auch
frei eingeben – er gilt dann nur für diesen Termin.

Serien: "jede Woche" oder "alle 2 Wochen" an einem oder mehreren Tagen, "jeden Monat am
gleichen Tag", "am 2. Samstag" oder "am letzten Freitag", jeweils bis zu einem Enddatum.
Einzelne Tage, an denen der Termin ausfällt, trägst du unter "Fällt aus am" ein.

"Termin kopieren" (in der Terminliste und im Termin) legt einen Entwurf mit allen Angaben an.

"Kalender > Aufräumen" löscht alte Termine in den Papierkorb oder endgültig, auf Wunsch
täglich automatisch.

== Verwendung ==

Kalender mit Umschalter Liste / Woche / Monat / Jahr, Kategorie-Filter und Suche:
[einfacher_kalender]

Die Monatsansicht zeigt die nächsten bis zu fünf Termine ab heute über dem Monatsraster.
Die Listenansicht zeigt alle Termine des angezeigten Jahres chronologisch nach Monat
gruppiert. Standardmäßig öffnet sich das laufende Jahr. Mit den Pfeilen neben der
Jahreszahl wechselst du zum vorherigen oder nächsten Jahr. Die Wochenansicht zeigt
sieben Tage mit Bild, auf dem Handy als Liste der Tage mit Terminen.

Im Kategorie-Filter lassen sich mehrere Kategorien gleichzeitig wählen. Fährt man mit
der Maus über einen Termin, erscheint eine Vorschau; ein Klick öffnet ein Detailfenster
mit Bild, "Details ansehen" und "In Kalender eintragen".

Optionen (alle optional):
  ansicht="monat"      Startansicht: liste, woche, monat oder jahr
  auswahl="nein"       Umschalter für die Ansichten ausblenden
  kategorie="sport"    nur bestimmte Kategorien zeigen (mehrere mit Komma: "sport,kultur")
  filter="nein"        Kategorie-Auswahl ausblenden
  suche="nein"         Suchfeld ausblenden

Beispiele:
[einfacher_kalender ansicht="jahr"]
[einfacher_kalender ansicht="liste" auswahl="nein" kategorie="vereinsleben"]

Liste der nächsten Termine (z. B. in der Seitenleiste):
[termine_liste anzahl="5"]
[termine_liste anzahl="3" kategorie="sport"]
[termine_liste anzahl="3" bilder="ja"]

Jeder Termin hat eine eigene Seite unter /termine/termin-name/ mit Datum, Uhrzeit, Ort
(mit Karten-Link), Veranstalter, Kategorie, weiteren Terminen der Serie und den Knöpfen
"In Kalender eintragen" und "Zum Kalender" über der Beschreibung. Bilder lassen sich
anklicken und vergrößern (mit WX Lightbox, falls aktiv, sonst mit eigener Lightbox).
Für Google werden strukturierte Daten (schema.org/Event) ausgegeben.

Kalender-Abo (iCal) für Outlook, Google Kalender oder Apple: der Link "Kalender abonnieren"
unter dem Kalender, bzw. https://deine-seite.de/?ek_ics=alle

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

= 2.0.0 =
* Wochenansicht, Kategorie-Filter mit Mehrfachauswahl, Suche
* Serien (mehrere Wochentage, alle 2 Wochen, monatlich) mit Ausfallterminen
* Orte mit Adresse, Veranstalter mit Kontakt, freie Ortseingabe
* Vorschaubilder, Vorschau beim Darüberfahren, Detailfenster, vergrößerbare Bilder
* In Kalender eintragen (.ics), Kalender-Abo, Karten-Link, Daten für Google
* Termin kopieren, alte Termine aufräumen
* Bestehende Termine, Kategorien und Shortcodes bleiben unverändert

= 1.4.0 =
* Neuer Name: WX Easy Calendar

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
