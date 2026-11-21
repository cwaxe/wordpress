# WX Webcam Archive

Speichert Webcam-Bilder automatisch und macht sie über einen Kalender wieder abrufbar.

- Beliebig viele Kameras, jeweils mit eigener Quelle, eigenem Intervall und Zeitfenster
- Bilder per Snapshot-URL (optional mit Passwort) oder aus einem Server-Ordner (z. B. FTP-Upload der Kamera)
- Nachtfilter: nachts nach Sonnenstand pausieren und dunkle oder Infrarot-Bilder verwerfen
- Überspringt unveränderte Bilder, erzeugt Vorschaubilder, löscht alte Bilder nach einstellbarer Zeit
- Kalender mit markierten Tagen, Tagesansicht mit Zeitleiste, Zeitraffer, Vollbild und Kamera-Umschalter; beim Tageswechsel bleibt die Uhrzeit gleich
- Teilbare Links auf einen bestimmten Tag und eine bestimmte Kamera
- Farben im Backend anpassbar (Schemas LSV-Blau, Hell, Anthrazit, Grün)

Einstellungen: *Einstellungen → WX Webcam Archive*.

```
[webcam_archiv]                                   Archiv mit allen Kameras
[webcam_archiv kamera="cam2" tempo="6" titel="Webcams EDMP"]
[webcam_aktuell kamera="cam2" refresh="60"]       neuestes Bild, aktualisiert sich selbst
```

Tipp: Für exakte Aufnahme-Intervalle statt WP-Cron einen echten Cronjob auf die Trigger-URL aus den Einstellungen einrichten (siehe `readme.txt` im Plugin).

Zurück zur [Übersicht](../../README.md).
