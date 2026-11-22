=== Terminkalender ===
Requires at least: 6.6
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later

Schlanker Veranstaltungskalender mit einfachem Backend. Übernimmt alle Daten aus „The Events Calendar“.

== Backend ==

Menü „Termine“:
* Alle Termine – Liste mit Datum und Ort, getrennt nach Kommende / Vergangene, „Kopieren“ je Termin
* Neuer Termin – Titel, Box „Wann? Wo? Wer?“, Beschreibung, Kategorie, Bild
* Kategorien (mit Farbe), Orte (mit Adresse, oder frei je Termin), Veranstalter (mit Kontakt)
* Einstellungen – Kalenderseite, Darstellung, Übernahme aus The Events Calendar

Serien: jede Woche oder alle 2 Wochen (auch an mehreren Tagen, z. B. Di und Do), jeden Monat am gleichen Tag, am gleichen Wochentag
(z. B. 2. Samstag) oder am letzten Wochentag – jeweils mit Enddatum und Ausfallterminen.

== Frontend ==

* Shortcode [terminkalender] (auch [veranstaltungskalender]): Monat, Woche und Liste, Bildvorschau, Kategorie-Filter
  mit Mehrfachauswahl, Suche, Vorschau beim Darüberfahren, Detailfenster
* Eigene Terminseite je Termin (/termin/name/) mit Datum, Ort und Karte, Veranstalter,
  weiteren Serienterminen, „In Kalender eintragen“ (.ics) und Daten für Google
* Kalender-Abo (iCal) für alle Termine

== Umstieg von The Events Calendar ==

1. Terminkalender installieren und aktivieren (The Events Calendar noch aktiv lassen).
2. Termine → Einstellungen → „Jetzt übernehmen“. Wiederholbar, nichts wird doppelt angelegt.
3. Stichproben prüfen, Kalenderseite wählen.
4. The Events Calendar und „Veranstaltungskalender (eigene Ansicht)“ deaktivieren.
   Alte Links (/termine/…, /veranstaltung/…, iCal) werden automatisch weitergeleitet.

== Changelog ==

= 1.2.0 =
* Wochenansicht, Bildvorschau, anklickbare Bilder (Lightbox), Ort frei eingeben.

= 1.1.0 =
* Wöchentliche Serien an mehreren Wochentagen.

= 1.0.0 =
* Erste Version.
