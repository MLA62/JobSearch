# Release 2.4.25 – Job-Room-Helper und Absagegrund

Stand: 18.09.2026.

## Umfang

- Der Helper und seine Monatsauswahl enthalten nur datierte Bewerbungen, die noch nicht als im Job-Room erfasst markiert wurden.
- Strasse, Hausnummer, Postleitzahl und Ort erscheinen in dieser Reihenfolge als einzeln kopierbare Felder. Ein nicht eindeutig trennbarer Hausnummernteil wird nicht erfunden.
- Eine Absage verlangt einen mehrzeiligen Absagegrund mit 1–249 Zeichen. Er wird im Bewerbungsformular nur bei Absage oberhalb der übrigen Karten gezeigt und ist im Helper kopierbar.
- Direkte Formularänderung, Kontakt-/E-Mail-Protokoll und Admin-KI-Schreibweg prüfen die Pflicht serverseitig.

## Datenbank

Neue nullable Spalte `applications.rejection_reason VARCHAR(249)`, implementiert über die idempotente Runtime-Erweiterung `runtime_schema_2_4_25`; `sql/jobsearch/19_application_rejection_reason.sql` dokumentiert die entsprechende Einzelmigration für kontrollierte Neuaufbauten. Bestehende Absagen werden nicht automatisch mit unbelegten Gründen ergänzt. Keine vorhandenen Bewerbungen werden gelöscht.

## Nachweise

- Lokal: PHP-Syntax, beide Dokumentationsgeneratoren im Prüfmodus und 44 PHP-Tests erfolgreich.
- Release-Commit: noch offen.
- Deployment-Paket-Hash und cPanel-Freigabe: noch offen.
- Datenbankeffekt auf Produktion: noch nicht geprüft.
- Authentifizierte Live-Abnahme: noch offen.
