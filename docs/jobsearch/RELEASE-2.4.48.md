# Release 2.4.48 – Absatzabstand

Stand: 21.09.2026. Auf Benutzerwunsch steigt der Abstand nach normalen
Absätzen und H1–H3 um ein Drittel von 6 auf 8 pt. In Chromium entspricht
dies rund 10,67 statt 8 px. Vorabstände, Schriftgrössen, weiche
Zeilenumbrüche und die Abstände zwischen Listenpunkten bleiben gleich.
Die CSS-Regel gilt für alle Rich-Text-Editoren und gespeicherten
HTML-Ansichten. Gespeicherte Inhalte und die Datenbank werden nicht
geändert.

Lokale Prüfung: PHP-Syntax beider PHP-Dateien, 57 PHP-Tests,
Chromium-Rich-Text-Test mit berechneten CSS-Werten, beide
Dokumentationsgeneratoren und `git diff --check` bestanden.
Deployment am 21.09.2026 um 15:41 UTC aus Quell-Commit `a259543` nach
`public_html/jobs.jema.business`; nur `index.php` und `assets/app.css`
wurden ersetzt. Zuvor wurden beide Dateien des Stands 2.4.47 unter
`index.php.bak-20260921-2.4.47-pre-2.4.48` und
`assets/app.css.bak-20260921-2.4.47-pre-2.4.48` gesichert. Die
freigegebenen Aktionen lauteten `ad51b47a8029c3a7def1e5319d694613`,
`cb0583bc071c903b661138ba2575c4cb` und für das Archiv
`79560a27f01483d3c780b53e74804181`. Das Extraktionswerkzeug legte
zusätzliche eigene Backups an.

Die Remote-SHA-256-Prüfsummen stimmen bytegenau mit den lokalen Dateien
überein:

- `index.php`: `d608c19786ef0af934e18f6c95a60ba673f5647403f59fe01acc3b2aed39a0d1`
- `assets/app.css`: `33dc12095d336bf5e12475089f78c1567ad5fc167632721df4a493035411ef01`

Die öffentliche Startseite zeigt Version 2.4.48. Die Browser-Sitzung
war bei der Kontrolle abgemeldet; eine authentifizierte Sichtprüfung
des produktiven Editors ist daher noch offen. Der lokale Chromium-Test
hat die berechneten 8-pt-Abstände im echten Editor geprüft.
