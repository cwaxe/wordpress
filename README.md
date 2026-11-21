# WordPress-Themes und -Plugins

Sammlung eigener WordPress-Themes und -Plugins. Alle Pakete sind auf Deutsch, brauchen keine weiteren Plugins und lassen sich im Backend anpassen.

## Herunterladen und installieren

Die fertigen ZIP-Dateien liegen unter [**Releases**](https://github.com/cwaxe/wordpress/releases), dort auch alle älteren Versionen.

<!-- pakete:start -->
| Paket | Art | Version | Download |
|---|---|---|---|
| [WX Easy Template](themes/wx-easy-template/) | Theme | 1.10.1 | [wx-easy-template-1.10.1.zip](https://github.com/cwaxe/wordpress/releases/download/wx-easy-template-v1.10.1/wx-easy-template-1.10.1.zip) |
| [LSV Flugplatz](themes/lsv-flugplatz/) | Theme | 1.2.0 | [lsv-flugplatz-1.2.0.zip](https://github.com/cwaxe/wordpress/releases/download/lsv-flugplatz-v1.2.0/lsv-flugplatz-1.2.0.zip) |
| [WX Easy Calendar](plugins/wx-easy-calendar/) | Plugin | 1.4.0 | [wx-easy-calendar-1.4.0.zip](https://github.com/cwaxe/wordpress/releases/download/wx-easy-calendar-v1.4.0/wx-easy-calendar-1.4.0.zip) |
| [WX Lightbox](plugins/wx-lightbox/) | Plugin | 1.1.0 | [wx-lightbox-1.1.0.zip](https://github.com/cwaxe/wordpress/releases/download/wx-lightbox-v1.1.0/wx-lightbox-1.1.0.zip) |
| [WX Webcam Archive](plugins/wx-webcam-archive/) | Plugin | 1.5.0 | [wx-webcam-archive-1.5.0.zip](https://github.com/cwaxe/wordpress/releases/download/wx-webcam-archive-v1.5.0/wx-webcam-archive-1.5.0.zip) |
| [LSV Wetter am Platz](plugins/lsv-wetter/) | Plugin | 1.1.0 | [lsv-wetter-1.1.0.zip](https://github.com/cwaxe/wordpress/releases/download/lsv-wetter-v1.1.0/lsv-wetter-1.1.0.zip) |
| [WX Easy Backup](plugins/wx-easy-backup/) | Plugin | 1.1.0 | [wx-easy-backup-1.1.0.zip](https://github.com/cwaxe/wordpress/releases/download/wx-easy-backup-v1.1.0/wx-easy-backup-1.1.0.zip) |
<!-- pakete:end -->

Beschreibung, Einstellungen und Shortcodes stehen in der README des jeweiligen Pakets.

ZIP-Datei in WordPress hochladen: Themes unter *Design → Themes → Theme hinzufügen → Theme hochladen*, Plugins unter *Plugins → Installieren → Plugin hochladen*. Bei einem Update bietet WordPress an, die vorhandene Version zu ersetzen. Die Einstellungen bleiben dabei erhalten. Alle Pakete benötigen PHP 7.4 oder neuer.

**Umstieg von den alten Namen:** Fünf Pakete hießen früher anders (einfaches-template-wx, Einfacher Kalender, Soft Blur Lightbox, Webcam Archiv, WP Komplett-Backup & Umzug). WordPress behandelt die neuen Pakete als eigenständige Plugins bzw. als eigenes Theme, ein Update ersetzt die alte Version also nicht automatisch. Alle Einstellungen und Daten bleiben trotzdem erhalten:

1. Neues Paket hochladen und aktivieren. Ein Plugin schaltet seine alte Version dabei selbst ab und übernimmt deren Einstellungen und Daten. Das Theme übernimmt beim ersten Aktivieren die Customizer-Einstellungen, Menüs, Logo und das zusätzliche CSS.
2. Danach die alte Version unter *Plugins* bzw. *Design → Themes* löschen, **während die neue aktiv ist**. Bei Webcam Archiv und Soft Blur Lightbox verhindert die neue Version dabei, dass die alte beim Löschen die gemeinsamen Daten entfernt.

## Aufbau des Repos

```
themes/<paket>/      Quellcode der Themes (Version im Kopf von style.css)
plugins/<paket>/     Quellcode der Plugins (Version im Kopf von <paket>.php)
build.sh             baut die ZIP-Dateien nach dist/
CHANGELOG.md         Änderungen je Paket und Version, erscheinen als Release-Text
.github/workflows/   veröffentlicht neue Versionen automatisch als Release
```

ZIP-Dateien werden nicht eingecheckt. `dist/` ist in `.gitignore` eingetragen.

`./build.sh` baut alle Pakete, `./build.sh wx-lightbox` nur eines, `./build.sh --list` zeigt alle Pakete mit Version. Die ZIP heißt `<paket>-<version>.zip`, der Ordner darin trägt keine Version, damit WordPress neue Versionen als Update erkennt. `README.md` wird nicht mit verpackt.

Das Template enthält eigene, angepasste Kopien von WX Easy Calendar und WX Lightbox (`inc/calendar.php`, `inc/lightbox.php` und die zugehörigen Dateien in `assets/`). Änderungen an den Plugins bei Bedarf dort von Hand nachziehen.

## Neue Version veröffentlichen

1. Änderungen im Paketordner vornehmen und die Version im Kopf erhöhen: `Version:` in `style.css` (Themes) bzw. in `<paket>.php` (Plugins).
2. Optional in `CHANGELOG.md` einen Abschnitt `## <paket> <version>` ergänzen. Er wird zum Text des Releases.
3. Committen und auf `main` pushen.

Den Rest erledigt die GitHub Action:

- Sie übernimmt die Version aus dem Dateikopf in die Konstante `…_VERSION` und den `Stable tag` der `readme.txt`.
- Sie aktualisiert die Tabelle oben mit Versionen und Download-Links.
- Diese Änderungen committet sie selbst auf `main`.
- Für jede Version ohne Release baut sie die ZIP und veröffentlicht sie als Release `<paket>-v<version>`.

Ein neues Paket braucht nur einen eigenen Ordner in `themes/` oder `plugins/`. Es erscheint beim nächsten Push in der Tabelle und unter Releases.

Lokal macht `./build.sh --sync` dasselbe Nachziehen wie die Action, `./build.sh` baut alle ZIPs nach `dist/`. Von Hand starten lässt sich die Action unter *Actions → Release → Run workflow*.
