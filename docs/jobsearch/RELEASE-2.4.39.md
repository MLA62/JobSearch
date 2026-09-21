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

PHP-Syntax, fokussierte Regression und vollständige PHP-Suite werden vor
der Bereitstellung geprüft. Der Test reproduziert einen substanziellen
Text mit einzelnem Gesprächsaufschub und bestätigt, dass der gute Inhalt
erhalten bleibt. Produktiver Backup-Pfad, Approval-ID, SHA-256-Hash und
Live-Smoke-Test werden nach dem Deployment ergänzt. Eine authentifizierte
Funktionsabnahme ist davon getrennt auszuweisen.
