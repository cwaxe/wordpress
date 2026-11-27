=== WX Schwerpunkt ===
Tags: weight and balance, schwerpunkt, flugzeug, masse, luftsport
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later

Rechner für Masse und Schwerpunkt (Weight & Balance) direkt auf der Website.

== Beschreibung ==

* Beliebig viele Flugzeuge, jeweils mit Leermasse, Hebelarmen, Schwerpunktgrenzen (mm und %MAC), MTOM und Kraftstoffdaten
* Zuladungsposten frei festlegbar (Pilot, Passagier, Gepäck, Flügelkoffer …) mit Min./Max. und Pflichtfeld
* Prüft Startmasse, Schwerpunkt beim Start und bei leerem Tank sowie die Grenzen jedes Postens
* Zeigt die Flugzeit mit der eingetragenen Tankfüllung und das Datum der Wägung
* Eingabefelder sind bei jedem Aufruf leer
* Übernimmt die Farben des Themes (CSS-Variablen --karte, --text, --akzent …)
* Automatische Updates aus den GitHub-Releases

== Einstellungen ==

Einstellungen → Schwerpunkt. Nach einer neuen Wägung Leermasse, Hebelarm und Datum eintragen.
Beim ersten Start ist die Bristell D-MDMP mit den Werten der bisherigen Tabelle angelegt.

== Shortcode ==

[schwerpunkt]                     erstes Flugzeug
[schwerpunkt flugzeug="d-mdmp"]   bestimmtes Flugzeug (Kurzname oder Name)
  Optional: titel="Weight & Balance"

== Rechnung ==

Moment = Gewicht × Hebelarm, Schwerpunkt = Summe der Momente / Masse,
%MAC = (Schwerpunkt − MAC-Vorderkante) × 100 / MAC, Kraftstoff = Liter × Dichte.
Der Rechner ist eine Hilfe zur Flugvorbereitung; maßgeblich ist das Flughandbuch.

== Changelog ==

= 1.0.0 =
* Erste Version: Rechner aus dem Theme LSV Flugplatz übernommen, Flugzeuge im Backend pflegbar.
