# Version 2.2.0 – formatierte Langtexte und chronologische Aktivitäten

Stand: 07.09.2026. Bereitgestellt und als Live-Version bestätigt.

## Änderungen

- Mehrzeilige fachliche Textfelder erhalten einen sprachabhängigen HTML-Mini-Editor.
- Unterstützt werden Absätze, Fett, Kursiv, Links, externe HTTPS-Bilder, Tabellen, Trennlinien und eine HTML-Ansicht.
- Eine serverseitige Positivliste entfernt aktive Inhalte, unsichere Attribute und ungeeignete URL-Protokolle.
- Formatierte Bewerbungs-E-Mails behalten ihr Layout; PDF-/Textausgaben und KI-Kontexte verwenden lesbaren Klartext.
- Statushistorien, Kontaktaktivitäten, gemischte Bewerbungsaktivitäten und Audit-Auszüge werden vom ältesten zum neuesten Eintrag gezeigt.

## Nachweis

PHP-Syntax, alle 30 PHP-Testdateien, Hilfe in fünf Sprachen, Referenzgeneratoren und Git-Diff wurden vor der TOTP-Freigabe geprüft. Nach der Freigabe wurde ausschließlich `public_html/jobs.jema.business/index.php` ersetzt; Konfiguration und Datenbankschema blieben unverändert. Die Produktionsdatei umfasst 1'060'348 Bytes und entspricht mit SHA-256 `4f0621638116d56d9a2f3d49f81fafbe622fef1560c1514f500cb96ab0e55abb` exakt dem Release-Stand. Die öffentliche Seite liefert HTTP 200, zeigt Version 2.2.0 und enthält keinen sichtbaren PHP-Laufzeitfehler.
