=== LSV Flugplatz ===

Eigenständiges WordPress-Theme für lsv-vilsbiburg.de im Stil von wetter.lsv-vilsbiburg.de.
Version 1.1.0 · Lizenz GPLv2 oder später

== Installation ==

1. Design → Themes → Theme hinzufügen → Theme hochladen → lsv-flugplatz.zip
2. Zuerst "Live-Vorschau" ansehen, dann "Aktivieren".
3. Beim Aktivieren werden Logo, Menüzuordnung und Hintergrundbild aus
   Twenty Twenty-One automatisch übernommen.
4. Design → Customizer → "LSV Kopf- & Fußzeile": Kürzel, Titel, Unterzeile,
   Button rechts oben (Standard: "Wetter") und Fußzeilentext anpassen.
5. Cache leeren (falls ein Cache-Plugin aktiv ist).

Twenty Twenty-One kann installiert bleiben – es wird nicht mehr benötigt,
schadet aber nicht und dient als Rückfallebene.

== Was weiter funktioniert ==

* Menüs: gleiche Positionen wie Twenty Twenty-One ("primary" = Hauptmenü,
  "footer" = Fußzeilen-Menü), inkl. Untermenüs.
* Widgets: gleicher Bereich "sidebar-1" (wird in der Fußzeile als Karten angezeigt).
* Block-Inhalte: Titelbild, Spalten, Bilder, Galerien, Videos, Tabellen, Buttons usw.
* Twenty-Twenty-One-Block-Stile (z. B. "Rahmen" beim Titelbild) und deren
  Farb- und Schriftgrößen-Klassen.
* Plugins, die sich über wp_head/wp_footer einklinken (z. B. Real Cookie Banner).

== Gestaltungs-Werkzeuge im Block-Editor ==

* Spalten erscheinen automatisch als dunkelblaue Karten.
  Abschalten: Spalten-Block auswählen → Stile → "Ohne Karten".
  Ausnahme: Enthält eine Spalte nur ein Bild oder ein Webcambild
  ([webcam_aktuell]), hat sie keine Karte. Bilder bekommen nie einen Rahmen.
* Gruppe → Stil "LSV Karte": dunkelblaue Karte.
* Gruppe → Stil "LSV Kachel": Kachel wie QNH/QFE auf der Wetterseite.
  Aufbau: 1. Absatz = Bezeichnung (klein), 2. Absatz mit fettem Wert (groß),
  weitere Absätze = Zusatzinfo (klein).
* Farbpalette mit den LSV-Farben.

== Schwerpunktrechner ==

[schwerpunkt] zeigt den Rechner für Masse und Schwerpunkt der Bristell D-MDMP.
Nach einer neuen Wägung die Werte als Attribute angeben, z. B.
  [schwerpunkt leermasse="368,2" leerarm="731,5"]
Weitere Attribute: kennzeichen, arm_sitze, arm_gepaeck, arm_fluegel, arm_tank,
kg_pro_liter, verbrauch, liter, mtom, cg_vorn, cg_hinten, mac_vorderkante, mac,
pilot_min, pilot_max, max_gepaeck, max_fluegel, handbuch.

== Wetter auf der Startseite ==

Im Block-Editor: "+" → Reiter "Vorlagen" (Muster) → Kategorie "LSV" →
"Wetter am Platz" oder "Wetter am Platz (kompakt)".
Alternativ: Block "Shortcode" einfügen und [lsv_wetter] eintragen.

Optionen:
  [lsv_wetter kompakt="ja"]    nur Wind, Temperatur, QNH, Wolkenbasis
  [lsv_wetter titel="Wetter"]  eigene Überschrift, titel="" = keine
  [lsv_wetter link="nein"]     ohne Link zur Wetterseite

Die Daten kommen aus data.json der Wetterseite, werden 60 Sekunden
zwischengespeichert und im Browser jede Minute aktualisiert.
Adresse der Wetterseite: Customizer → "LSV Kopf- & Fußzeile".

== Farben ==

Hintergrund #486d97 · Karte #2c4767 · Karte hell #385879 · Akzent #8fb3dc
Titel #d3e3f4 · Text #f2f6fa · Text leise #b6c7da · Schrift: Segoe UI / Systemschrift
