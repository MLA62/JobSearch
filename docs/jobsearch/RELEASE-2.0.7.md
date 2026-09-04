# Version 2.0.7 – Geprüfte Treffer und belegbasierter Import

Stand: 2026-09-04. Lokal vorbereitet, nicht als deployed bestätigt.

## Änderungen

- Nur verfügbare und passende Originalanzeigen in der Tabelle; abgelaufene, unprüfbare, unpassende, gelöschte und doppelte Kandidaten ausgeschlossen. Nach Ausschlüssen weiter recherchieren.
- Original-Drill-down, Verfügbarkeitsindikatoren, strukturierte KI-Einzelkriterien mit Zitatprüfung und lokal gewichteter Match statt ungeprüfter Modellprozente. Vorläufige Schwelle 70 %, unbekannte Angaben verdienen keine Punkte.
- Firmenwebsite plus begrenzte Kontakt-/Impressum-/Teamrecherche; belegte Felder in Firmen, Kontakte und Jobs. Gleiche Prüfung für Übernehmen und URL-Schnellimport einzeln/mehrfach.
- Eigene vorhandene Datensätze ergänzen, sonst anlegen; gefüllte Werte erhalten. Original-URL-Dublettenprüfung, Kontakte firmenweit wiederverwenden, widersprüchliche Arbeitgeberzuordnung nicht still ändern.
- Score, Kriterienhash, Prüfzeit, Originaltext und Herkunft im bestehenden Jobdatensatz speichern. Keine automatische Änderung aller Bestandsdatensätze.
- Suchdialog mit echten Zählern, Laufzeit und Abbruch; kein automatisches Schließen. Zielzahl, keine weiteren Kandidaten und Sicherheitsgrenze unterscheidbar. Maximal 60 Kandidaten und drei Rechercherunden je Quelle; kein Vollständigkeitsversprechen bei erreichter Grenze.
- Ergebnisse nach 15 Minuten oder geänderten Kriterien nicht weiter als aktuell anzeigen. Bekannte Original-URL eines gelöschten Treffers ebenfalls dauerhaft ausschließen.
- MDs und generierte In-App-Hilfe in fünf Sprachen nachgeführt. Keine Schemaänderung, kein neuer Render-Dienst, keine Secrets im Release.

## Nachweise und Grenzen

23 PHP-Testdateien bestanden, darunter 24 gezielte Verifikationsprüfungen und 344
Speicherprüfungen gegen simulierte DB. Beide PHP-Syntaxprüfungen, beide Generatoren
mit --check und git diff --check bestanden. Acht Chromium-Szenarien für Such- und
Importdialog bestanden; Suchdialog bei 390 Pixel Breite visuell geprüft.

Automatisierte PHP-Funktions-/Speicherprüfungen und Chromium-Dialogtests gegen simulierte externe Antworten. Kein echter OpenAI-Aufruf oder produktiver DB-Testimport als abgeschlossen behauptet. Angemeldete fachliche Abnahme und echter MariaDB-Rollbacktest bleiben offen. Belegprüfung macht die semantische KI-Einschätzung nicht fehlerfrei. Verfügbarkeitsindizien sind keine Garantie einer unbesetzten Stelle. Firmenwebsite-Recherche setzt einen erkannten Link voraus; keine unbegrenzte Websuche oder JavaScript-Ausführung.

## Deployment und Rückweg

Upload vorbereitet aus Quell-Commit 7d37e9a11bebcff060a5af511fb19151b8e53d93.
Lokaler Stand und GitHub-Branch vor dem Vorschlag identisch, Arbeitsstand sauber.
Vorgeschlagene Datei: 971638 Bytes, SHA-256 7c5c1cbff4f38538989ad6296a97553e15d1305defb7f779304765a6c31cfe86.
Remote-Vergleich vor dem Vorschlag bestätigt den unten genannten Stand 2.0.6.
Die Ausführung wartet auf externe TOTP-Freigabe; kein Live-Erfolg behauptet.

Ziel ausschließlich public_html/jobs.jema.business/index.php einschließlich generierter Hilfe.
Freigabe über externe TOTP-Seite, danach Hash- und öffentliche Versionsprüfung.
Vorheriger belegter Stand 2.0.6: Quell-Commit e81199c9d709e36c26d4099855da0809ae246679,
935715 Bytes, Modus 0644, SHA-256 721652d20b6dc45fe781e1916cd97ced6e5f2da330cd63400561d92cc82da41f.
Code-Rollback nur durch gesondert freigegebenen Upload dieses Inhalts; bereits vom Benutzer
ausgelöste Importe werden dadurch nicht rückgängig gemacht. Freigabe-ID und Link nicht in Git speichern.
