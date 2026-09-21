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
Quell-Commit, Deployment-Prüfsummen und Live-Abnahme werden nach der
Verifikation ergänzt.
