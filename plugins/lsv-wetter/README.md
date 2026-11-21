# LSV Wetter am Platz

Zeigt die aktuellen Wetterdaten vom Flugplatz Vilsbiburg (EDMP) auf jeder Seite. Die Daten kommen von wetter.lsv-vilsbiburg.de.

- Wind mit Richtungspfeil, Piste in Benutzung mit Gegen- und Seitenwind, Temperatur, Taupunkt, QNH, QFE, Wolkenbasis, Dichtehöhe und Luftfeuchte
- Aktualisiert sich jede Minute; Hinweis „Daten veraltet“, wenn die Station länger als 10 Minuten nichts liefert
- Block „Wetter am Platz“ im Editor und gelbes Pistenschild
- Jeder Wert ist auch einzeln als Shortcode verfügbar, z. B. für Fließtext
- Funktioniert mit jedem Theme

Einstellungen und Übersicht aller aktuellen Werte: *Einstellungen → LSV Wetter*.

```
[lsv_wetter]                     alle Werte ([lsv_wetter kompakt="ja"] nur die wichtigsten)
[lsv_landerichtung wind="ja"]    gelbes Pistenschild mit Wind
Aktuell [lsv_wind_komplett], QNH [lsv_qnh]
```

Die vollständige Liste der Einzelwerte steht in der `readme.txt` des Plugins.

Zurück zur [Übersicht](../../README.md).
