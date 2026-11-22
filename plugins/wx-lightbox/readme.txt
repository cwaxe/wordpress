=== WX Lightbox ===
Tags: lightbox, gallery, images, blur
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPL-2.0-or-later

Schlanke Lightbox für Bilder und Galerien – der Hintergrund wird beim Öffnen sanft weichgezeichnet.

== Beschreibung ==

* Hintergrund wird leicht verschwommen (CSS backdrop-filter, Stärke einstellbar)
* Funktioniert automatisch mit Bild- und Galerie-Blöcken sowie klassischen Galerien
* Blättern per Pfeiltasten, Buttons oder Swipe; Esc, Klick daneben oder Wisch nach unten schließt
* Bildunterschriften, Bildzähler, Vorladen der Nachbarbilder
* Barrierearm: Fokus-Falle, ARIA-Labels, respektiert „Bewegung reduzieren“
* Kein jQuery, ca. 10 KB unkomprimiert

== Installation ==

1. ZIP unter Plugins → Installieren → Plugin hochladen installieren und aktivieren.
2. Bei Bild-/Galerie-Blöcken „Link zu: Mediendatei“ wählen.
3. Optional: Einstellungen → Lightbox.

== Verwendung im HTML ==

* <a href="bild.jpg" data-lightbox>…</a> – manuell aktivieren
* data-lightbox="reise" – eigene Gruppe über die ganze Seite
* class="no-lightbox" – ausschließen
* data-caption="Text" – eigene Bildunterschrift
* JavaScript: SoftBlurLightbox.open(['a.jpg', 'b.jpg'], 0)
* PHP-Filter: add_filter( 'sbl_enabled', fn() => ! is_page( 'kontakt' ) );
