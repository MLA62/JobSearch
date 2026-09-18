# Release 2.4.30 – Lebensläufe automatisch in KI-Bewerbungstexte einbeziehen

## Ursache und Änderung

Die Bewerbungstexterstellung las bisher nur den extrahierten Text eines einzigen
aktuellen Lebenslaufs und kürzte ihn nach 24.000 Zeichen. Auf dem Hosting ist
die Textextraktion für PDF- und Word-Dateien nicht zuverlässig verfügbar;
vorhandene Stammdaten-CVs konnten deshalb faktisch fehlen.

Die erste KI-Textvorbereitung und der manuelle KI-Button lesen jetzt alle
aktuellen eigenen Stammdaten-Lebensläufe. Ihre vollständigen Originaldateien
werden als Datei- beziehungsweise Bildeingaben übergeben. Ein vorhandener
korrigierter Text kommt zusätzlich ohne Kürzung mit Vorrang hinzu. Die KI soll
belegte, stellenrelevante Erfahrung daraus in das Motivationsschreiben
einbauen, ohne fehlende Fakten zu erfinden oder Quellenprobleme im Schreiben
zu erwähnen. Historische Versionen, gelöschte und fremde Dokumente bleiben
ausgeschlossen. Bei einer unlesbaren vorhandenen Datei wird der KI-Lauf nicht
als Erfolg gemeldet.

## Datenwirkung und Rückweg

Keine Datenbankmigration und keine automatische Änderung bestehender
Bewerbungen. Ein bewusst ausgelöster KI-Lauf speichert wie bisher Entwürfe in
den drei Bewerbungsfeldern; er versendet keine Bewerbung. Produktiv ist nur
`public/index.php` auszutauschen. Rückweg: freigegebene Sicherung der
vorherigen produktiven Datei.

## Prüfung

- PHP-Syntax, 46 PHP-Tests und Hilfe-/Referenzgeneratoren sind vor der
  Bereitstellung zu prüfen.
- `application_cv_context_test.php` sichert vollständige CV-Dateien,
  korrigierten Text ohne 24.000-Zeichen-Kürzung und Eigentümergrenze.
- Produktive Datei, öffentliche Version und angemeldete Funktion sind nach
  Freigabe separat zu prüfen und hier mit Commit, Hash und Ergebnis
  nachzutragen.
