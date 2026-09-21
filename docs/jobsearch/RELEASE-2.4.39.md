# Hotfix 2.4.39 – KI-Entwurf vor der Prüfung bereinigen

Stand: 21.09.2026. Anlass: Fehlerreferenz `65C070A70D1C` bei Bewerbung 66.
Das produktive Fehlerprotokoll nennt als unmittelbare Ursache drei
KI-Rückläufe mit als ungeeignet erkannter Sprache. Die vorhandene
Bereinigungsfunktion wurde erst nach dem Abbruchpfad verwendet.

## Änderung

- Angeforderte KI-Textfelder werden unmittelbar nach dem API-Rücklauf von
  einzelnen Sätzen über fehlende Unterlagen oder aufgeschobene Aussagen
  bereinigt. Ein ansonsten substanzieller Entwurf bleibt erhalten.
- Nur wenn danach ein Text zu kurz oder weiter ungeeignet ist, fordert die
  App einen neuen Entwurf an. Die explizite Nutzeranweisung bleibt in
  jedem Versuch enthalten.
- Die anschliessenden Prüfungen auf Briefstruktur, Quellenbezüge,
  Empfängerperspektive und den konkreten Bearbeitungsauftrag bleiben aktiv.
- Nicht angeforderte Felder und vorhandene gespeicherte Texte bleiben
  unangetastet; kein automatischer Versand.

## Verifikation und Deployment

PHP-Syntax, fokussierte Regression, alle 54 PHP-Tests und die beiden
Dokumentationsgeneratoren wurden vor der Bereitstellung geprüft. Der Test
reproduziert einen substanziellen Text mit einzelnem Gesprächsaufschub
und bestätigt, dass der gute Inhalt erhalten bleibt.

- Quell-Commit: `b17b138` (GitHub-Branch
  `feature/jema-jobs-ki-2.1.0`, gepusht).
- Vorheriges Live-`index.php`: SHA-256
  `4496066a8b3ec69afad1a86297b6195a6ab2087798c1bb065a642896ae2eea34`.
- Backup: `public_html/jobs.jema.business/index.php.bak-20260921-2.4.38-pre-2.4.39`;
  1'367'053 Byte. Connector-Approval `d3134658df4dd8de5f7f0ea73b635990`.
- Upload/Extraktion: Connector-Approval
  `bcc3f75eb2eef1ff800bd96f1cc9b435`, genau ein überschriebenes Ziel
  `public_html/jobs.jema.business/index.php`; Connector legte zusätzlich
  ein eigenes Dateibackup an.
- Aktuelles Live-`index.php`: SHA-256
  `0f22cfdde3e9b6679e849281c4e7a07a556808f7b3ffec8441438af11aa232a8`,
  identisch mit lokalem Release-Asset; 1'367'690 Byte, Modus 0644.
- Anonymer HTTPS-Smoke-Test `/?page=login`: HTTP 200, Version 2.4.39
  sichtbar.
- Authentifizierter KI-End-to-End-Lauf: nicht durchgeführt. Ohne
  angemeldete Testsitzung darf der Smoke-Test nicht als funktionale
  Abnahme ausgegeben werden.
