# JeMa Jobs 2.4.58

Stand: 05.10.2026

## Mehrfachauswahl in Jobs

- Jede Jobzeile und jede Jobkarte erhält direkt in **Aktionen** den Button **Auswählen**.
- Die Auswahl wird nur im Browserzustand geführt. Das Markieren und Abwählen lädt die Seite nicht neu und baut die Liste nicht erneut auf.
- Markierte Zeilen und Karten sind optisch hervorgehoben; der Button wechselt auf **Abwählen**.
- Sobald mindestens ein Job markiert ist, erscheint eine gemeinsame Aktionsleiste mit Anzahl, **Auswahl aufheben** und **Auswahl löschen**.
- Erst **Auswahl löschen** sendet alle IDs in einem einzigen bestätigten POST-Request.
- Die serverseitige Verarbeitung bleibt benutzergebunden, transaktional und verwendet denselben Kaskaden- und Audit-Ablauf wie das Einzellöschen.

## Qualitätssicherung

- PHP-Syntaxprüfung und kompletter Regressionstestlauf.
- Neuer Vertragstest für Position, Browserzustand, Sammel-POST, fehlende Zusatzspalte und transaktionale Verarbeitung.
- Prüfung in Tabellen- und Kartenansicht ohne produktive Datensätze zu löschen.
