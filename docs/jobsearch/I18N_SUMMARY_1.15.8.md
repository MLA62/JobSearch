# i18n-Summary JeMa Jobs 1.15.8

Erstellt: 2026-06-19 14:27:00 Europe/Zurich

| Arbeit | Umfang | Ergebnis |
|---|---:|---|
| Zentrale Profil-/Support-/SMTP-Schluessel | 34 Schluessel x 5 Sprachen | Ergaenzt in `translationCatalog()` |
| Legacy-Fallbacks fuer Profil/Support/SMTP | 19 Phrasen x 4 Zielsprachen | Ergaenzt in `public/i18n/ui_legacy.php` |
| Locale-Prioritaet | 1 Funktion | Session-/Login-Sprache bleibt fuehrend, auch in Support-Umgebung |
| Profil-Speichern | 1 Session-Fix | `$_SESSION['locale']` wird beim Speichern der Profilsprache aktualisiert |
| Profil-UI | Support, SMTP, Basisprofil | Harte Texte auf `tr(...)` umgestellt |
| QR-Code-JavaScript | 1 Clientmeldung | Fehlertext lokalisiert via `data-qr-error` |
| Sprachumschalter | CSS | Nur Flaggen sichtbar; Text bleibt fuer Screenreader |
| Versionshub | Code, Config, README, Docs | Version `1.15.8` gesetzt |
| 50-Zeilen-Codeaudit | 32 Code-Dateien | Bericht `I18N_LINE_AUDIT_1.15.8.md` erzeugt |
| UI-Use-Cases | 32 pro Sprache / 160 total | Bericht `I18N_USE_CASES_1.15.8.md` erzeugt |
| Syntaxpruefung | PHP 8.1 `-n -l` | `public/index.php` und `public/i18n/ui_legacy.php` ohne Syntaxfehler |
| Diff-Pruefung | `git diff --check` | Keine Whitespace-/Patch-Fehler |
| Automatische i18n-Testresultate | 54 Pruefungen | Bericht `I18N_TEST_RESULTS_1.15.8.md`, Gesamtergebnis BESTANDEN |

## Kritische Korrekturen

- Spanisch zeigt fuer SMTP-Verschluesselung `Cifrado`; Portugiesisch zeigt `Criptografia`. Eine Mischung auf derselben spanischen Oberflaeche wird durch Session-Locale-Prioritaet und explizite Profil-Schluessel vermieden.
- Beim Speichern der Profilsprache wird die Session-Locale sofort aktualisiert, damit die App direkt umstellt.
- ADMIN Support zeigt Status, Beschreibung, Button und Widerruf-Dialog in der aktiven Sprache.
- QR-Code-Erzeugung hat keinen deutschen JS-Fehlertext mehr im Script.
- Mini-Flaggen enthalten keine sichtbaren Sprachcodes mehr.
