# Release 2.4.42 – Absagegrund bleibt sichtbar

Stand: 21.09.2026. Ein gespeicherter Absagegrund erschien nach dem Speichern
oder erneuten Öffnen der Bewerbung als leeres Textfeld. Der gemeinsame
Speicherpfad für manuelles Speichern und Autosave schrieb den Wert bereits in
`applications.rejection_reason`; die Edit-Abfrage las ihn aber nicht. Sie
lädt das Feld nun gemeinsam mit dem Bewerbungsstatus. So bleibt ein bereits
gespeicherter Grund beim erneuten Öffnen sichtbar und wird beim nächsten
Speichern nicht unbeabsichtigt durch einen leeren Formularwert ersetzt.

Keine Datenbankmigration oder Änderung bestehender Daten. Ein historisch
tatsächlich geleerter Wert kann durch diese Anzeige-Korrektur allein nicht
rekonstruiert werden.

## Verifikation und Deployment

Auszufüllen nach Tests und Veröffentlichung. Authentifizierte Prüfung und
technische Deployment-Prüfung werden getrennt dokumentiert.
