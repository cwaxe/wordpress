=== LSV Wetter am Platz ===
Contributors: lsv-vilsbiburg
Tags: wetter, flugplatz, weewx, qnh, wind
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later

Aktuelle Wetterdaten vom Flugplatz Vilsbiburg (EDMP) auf jeder WordPress-Seite.

== Beschreibung ==

Holt die Daten aus data.json von wetter.lsv-vilsbiburg.de und zeigt:
Wind mit Richtungspfeil, passende Piste mit Gegen- und Seitenwind,
Temperatur, Taupunkt, QNH, QFE, Wolkenbasis, Dichtehöhe und Luftfeuchte.

* 60 Sekunden Zwischenspeicher auf dem Server, Aktualisierung im Browser jede Minute
* Hinweis "Daten veraltet", wenn die Station länger als 10 Minuten nichts liefert
* Funktioniert mit jedem Theme (Farben passen sich an das Theme "LSV Flugplatz" an)

== Installation ==

1. Plugins → Installieren → Plugin hochladen → lsv-wetter.zip → Aktivieren
2. Einstellungen → LSV Wetter: Verbindung prüfen (Adresse ist voreingestellt)
3. Seite bearbeiten → "+" → "Wetter am Platz"

== Shortcode ==

[lsv_wetter]                  alle Werte
[lsv_wetter kompakt="ja"]     nur Wind, Temperatur, QNH, Wolkenbasis
[lsv_wetter titel="Wetter"]   eigene Überschrift, titel="" = keine
[lsv_wetter link="nein"]      ohne Link zur Wetterseite

== Gelbes Pistenschild ==

[lsv_landerichtung]                        Schild mit der Piste in Benutzung (bei Windstille 03/21)
[lsv_landerichtung wind="ja"]              mit Wind, Gegen- und Seitenwind daneben
[lsv_landerichtung groesse="klein"]        für Fließtext
[lsv_landerichtung groesse="gross"]        groß, z. B. für die Startseite
Kurzform: [lsv_schild]

== Einzelwerte ==

Jeder Wert hat einen eigenen Shortcode. Optionen: einheit="nein", leer="k. A."
Beispiel: Aktuell [lsv_wind_komplett], QNH [lsv_qnh].

[lsv_wind] [lsv_windrichtung] [lsv_himmelsrichtung] [lsv_boeen] [lsv_wind_komplett]
[lsv_boe_max] [lsv_piste] [lsv_gegenwind] [lsv_seitenwind] [lsv_seitenwind_von]
[lsv_temp] [lsv_temp_min] [lsv_temp_max] [lsv_taupunkt] [lsv_spread] [lsv_feuchte]
[lsv_qnh] [lsv_qnh_genau] [lsv_qfe] [lsv_qfe_genau]
[lsv_wolkenbasis] [lsv_wolkenbasis_agl] [lsv_dichtehoehe] [lsv_stand] [lsv_alter]

Gleichwertig: [lsv_wert feld="qnh"]
Eine Übersicht mit den aktuellen Werten steht unter Einstellungen → LSV Wetter.

== Hinweis zum Theme "LSV Flugplatz" ==

Ab Theme-Version 1.1.1 schaltet das Theme seinen eingebauten Wetter-Block ab,
sobald dieses Plugin aktiv ist. Vorhandene [lsv_wetter]-Shortcodes laufen
dann automatisch über das Plugin weiter.
