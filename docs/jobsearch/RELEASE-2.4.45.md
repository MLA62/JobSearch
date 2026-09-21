# Release 2.4.45 – Job-Room-Favoritenlinks

Stand: 21.09.2026. Die in der App eingefügte Liste enthält 34 konkrete
`https://www.job-room.ch/job-favourites/<UUID>`-Adressen. Version 2.4.44
erkannte ausschliesslich `job-search/<UUID>` und wies deshalb alle 34 Links
sofort als «keine einzelne Stellenanzeige» ab. Es gab dabei keine
Datenbankänderung.

Beide Pfadformen werden jetzt anhand einer vollständigen UUID als
Einzelanzeige erkannt. Der Import holt die öffentlichen Originaldaten von
der Job-Room-Detail-API und speichert als Quell-URL die kanonische
`job-search/<UUID>`-Adresse. Andere Job-Room-Seiten und fremde Hosts bleiben
ausgeschlossen. Regressionstests decken beide Linktypen und ungültige Pfade
ab. Der Import der gesamten Liste wird nach dem Deployment live kontrolliert.
