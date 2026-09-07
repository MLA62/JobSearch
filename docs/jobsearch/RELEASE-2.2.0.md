# Version 2.2.0 – formatierte Langtexte und chronologische Aktivitäten

Stand: 07.09.2026. Deployment vorbereitet; bestätigter Live-Vorgänger ist Version 2.1.9.

## Änderungen

- Mehrzeilige fachliche Textfelder erhalten einen sprachabhängigen HTML-Mini-Editor.
- Unterstützt werden Absätze, Fett, Kursiv, Links, externe HTTPS-Bilder, Tabellen, Trennlinien und eine HTML-Ansicht.
- Eine serverseitige Positivliste entfernt aktive Inhalte, unsichere Attribute und ungeeignete URL-Protokolle.
- Formatierte Bewerbungs-E-Mails behalten ihr Layout; PDF-/Textausgaben und KI-Kontexte verwenden lesbaren Klartext.
- Statushistorien, Kontaktaktivitäten, gemischte Bewerbungsaktivitäten und Audit-Auszüge werden vom ältesten zum neuesten Eintrag gezeigt.

## Nachweis

PHP-Syntax, alle 30 PHP-Testdateien, Hilfe in fünf Sprachen, Referenzgeneratoren und Git-Diff sind vor der TOTP-Freigabe geprüft. Das Deployment ersetzt ausschließlich `public_html/jobs.jema.business/index.php`; Konfiguration und Datenbankschema bleiben unverändert.
