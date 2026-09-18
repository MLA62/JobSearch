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

## Prüfung und Bereitstellung am 18.09.2026

- PHP-Syntax, alle 46 PHP-Tests und Hilfe-/Referenzgeneratoren bestanden.
  `application_cv_context_test.php` sichert vollständige CV-Dateien,
  korrigierten Text ohne 24.000-Zeichen-Kürzung und Eigentümergrenze.
- Quell-Commit `37be2497d870d58d0e9d66ba2f53e54e8b168cfd` ist auf
  `origin/feature/jema-jobs-ki-2.1.0` veröffentlicht.
- Vorherige produktive Datei vor dem Austausch als
  `public_html/jobs.jema.business/index.php.bak-20260918-1542-2.4.29`
  gesichert (Approval `fcdd334e8b20fc091d19f84df12dad0c`); SHA-256
  `eb973c5cdba9537496a75660a1d321d084dfe7a48203a5cee79ac10fbc40b3d1`,
  1'331'070 Bytes, Modus `0644`, bytegleich mit dem vorherigen Live-Stand.
- Nur `public_html/jobs.jema.business/index.php` mit dem gesondert
  freigegebenen Proposal `9bd274b17e91cad49ed560b222200429` ersetzt.
  Live-SHA-256 und lokaler SHA-256:
  `8696f609b0f554f23476e37171db1095f6bc1091ff3fd7e2bbcf7828dfa9a7fc`,
  1'335'612 Bytes, Modus `0644`.
- Öffentliche Seite: HTTP 200, Footer Version 2.4.30, HSTS und CSP.
- Angemeldete fachliche Abnahme ist noch offen: Die bestehende Browser-Sitzung
  war beim Neuladen abgelaufen. Der Benutzer wurde um eigene Neuanmeldung
  gebeten; ein produktiver KI-Lauf mit einem echten CV wurde noch nicht
  behauptet oder ausgelöst.
- DB-Effekt beim Deployment: keine Migration, keine Bestandsdatenänderung.
