# Release 2.4.41 – Online eingereicht am richtigen Ort

Stand: 21.09.2026. Der auf der Bewerbungsseite angezeigte Hinweis verwies
auf «Online eingereicht», während der zugehörige Button erst ganz unten
nach dem langen KI- und Textbereich stand. Der Screenshot zeigte den
Onlinebewerbungsbereich ohne diese Aktion.

Der Button steht nun direkt bei «Webformular öffnen» und den
Dokumentenaktionen. Er wird wie der Hinweis nur vor einer bestätigten
Einreichung angezeigt (`draft` oder `ready` und kein `applied_at`).
Der entfernte zweite Button am Formularende ist nicht mehr nötig.
Der Handler zur transaktionalen Erfassung, zum Statuswechsel, zur
Kalenderprojektion und zur erneuten serverseitigen Prüfung bleibt
unverändert. Keine Schema- oder Bestandsdatenänderung.

## Verifikation und Deployment

Quell-Commit, Tests, Backup, Approval-ID, Live-Hash, HTTPS-Abruf und eine
allfällige angemeldete Sichtprüfung werden nach Durchführung festgehalten.
