# Changelog

## 1.27.0 — 2026-09-14

- Neues "👋 Wozu dieses Modul?"-Panel ganz oben im Formular, noch vor dem "Neu in Version"-Panel: erklärt in zwei Sätzen, was das Modul tut (lokale MQTT-Anbindung einer Panasonic-Wärmepumpe über die HeishaMon-Platine, ohne Cloud-Abhängigkeit) und welchen Nutzen das stiftet (Auswertung/Automatisierung wie jedes Symcon-Gerät, Einbindung in den NRG-Stack), mit Verweis auf WPHub als Cloud-Alternative ohne eigene HeishaMon-Platine. Aufgeklappt, einmalig dauerhaft ausblendbar (nicht versioniert wie das News-Panel). Verbundweite Konvention (SUITE.md "Einheitliche Formular-Optik" Punkt 0, Auslöser: ein Nutzer wusste beim ersten Öffnen nicht, wofür das Modul gut ist), Referenzimplementierung MeterHub

## 1.26.0 — 2026-09-14

- Automatische MeterHub-Zuordnung im Bereich "Externer Stromzähler": Hat MeterHub bereits einen Zähler mit Funktionszuordnung "Wärmepumpe" (`MHUB_GetFunctions`), erscheint automatisch ein Vorschlag mit Ein-Klick-Knopf "Von MeterHub übernehmen" - keine manuelle Variablensuche mehr nötig. Nur solange PowerVariable/EnergyVariable noch nicht verknüpft sind, Übernahme ausschließlich auf Klick, nie automatisch im Hintergrund. Anlass: beim Neuinstallations-Check (InverterHub-Anstoß) als Lücke gegenüber WPHub gefunden, das dieses Muster schon hat; 1:1 nach WPHubs Quellcode übernommen, EMS hat vorab zugestimmt (kein neues Vertragsfeld, reiner Konsum von MeterHubs bestehendem Vertrag)

## 1.25.2 — 2026-09-13

- Absicherung gegen SUITE.md-Verbund-Regel 9c (Tibber/OCPPHub/Dashboard-Fund, dreimal unabhängig aufgetreten): `ReadPropertyString`/`ReadAttributeString`/`ReadPropertyInteger` liefern während eines kurzen Kernel-Reload-Fensters `false` statt des erwarteten Typs zurück - ungecastet an `json_decode()` oder eigene typisierte Funktionen (`buildShortCycleGuardRules()`, `GetValue()`, `trim()`, `SendDebug()`) weitergereicht, hätte das wegen `declare(strict_types=1)` einen `TypeError` ausgelöst und die Instanz zum Absturz gebracht. 15 Stellen mit `(string)`/`(int)`-Casts abgesichert (Datenpunkt-Liste, 1-Wire-Liste, Taktschutz-Regelwerk, HeishaMon-IP, elektrische Gesamtleistung). Kein bislang beobachteter Absturz bei Dietmar, reine Vorsorge nach dem Fund in anderen Modulen. 4 neue Regressionstests, die den Fehler vor dem Fix nachweislich auslösten
- SUITE.md-Regel 9b (Datumsformat/Umlaute) und 9d (Instanzstatus) geprüft: keine Änderung nötig - HeishaMon hat keine nutzersichtbaren Datumsfelder (die einzige interne Datumsverwendung ist ein nicht angezeigter Tagesschlüssel), verwendet bereits ausschließlich `IS_ACTIVE`/`IS_INACTIVE` statt eigener Fehlercodes, und die gefundenen ue/ae/oe-Schreibweisen stecken ausschließlich in Code-Kommentaren, nicht in nutzersichtbarem Text

## 1.25.1 — 2026-09-13

- Hilfe-Fragen der neun PopupButtons nachgeschärft: EMS wies darauf hin, dass Fragen wie "Warum ist das wichtig?" den Gegenstand nicht benennen und bei mehreren Knöpfen im Formular nicht unterscheidbar sind (SUITE.md-Konvention verlangt den konkreten Gegenstand in der Frage, Referenz MeterHub). Alle neun Fragen benennen jetzt explizit ihr Feld (z. B. "Warum muss der Zähler kumulativ sein?" statt "Warum ist das wichtig?"), Breite auf 400-460px vereinheitlicht

## 1.25.0 — 2026-09-13

- Alle neun "?"-Hilfe-Schaltflächen im Formular tragen jetzt die volle Frage als Beschriftung statt eines bloßen "?" (z. B. "Wie funktioniert der Taktschutz?"), Popup-Titel identisch zur Beschriftung, Breite an der Fragelänge statt am alten 70px-"?"-Quadrat ausgerichtet. Dietmars verbundweite Vorgabe (13.09.2026, bereits am 01.09.2026 in SUITE.md/MeterHub etabliert, hier nachgezogen) - Nutzer sehen vor dem Klick, welche Frage eine Schaltfläche beantwortet

## 1.24.0 — 2026-09-13

- Neue Warnung im Konfigurationsformular vor möglicher Doppelsteuerung: existiert zusätzlich eine aktive WPHub-Instanz, weist ein Hinweis ganz oben darauf hin, dass beide dieselbe physische Panasonic-Wärmepumpe ansteuern könnten (HeishaMon lokal per MQTT, WPHub über die Panasonic Comfort Cloud) - gleichzeitiges Schreiben über beide Kanäle kann sich widersprechen. Anlass: verbundweite Konfliktprüfung nach dem ChargerHub/OCPPHub-Wallbox-Vorfall deckte denselben Doppelsteuerungs-Verdacht bei Dietmars Anlage auf (bei ihm live bestätigt: WPHub ist aktiv). Rein informativ, kein Blockieren - die Geräteidentität lässt sich nicht automatisch/zuverlässig beweisen (kein gemeinsames Seriennummernfeld im Panasonic-Protokoll). WPHub spiegelt denselben Check auf HeishaMon-Instanzen (0.5.0)

## 1.23.1 — 2026-08-24

- Falsche Doku-Aussage bei `fan2SpeedID` korrigiert (Dashboard-Fund: Dietmars Panasonic hat nur einen Lüfter, meldet aber trotzdem eine echte fan2SpeedID-Variable). Der bisherige Kommentar "Fan2 nur bei größeren Geräten mit zwei Lüftern" war falsch - main/Fan2_Motor_Speed liegt an einer festen Byte-Position im CN-CNT-Protokoll und wird von der Firmware für JEDES Modell dekodiert/gesendet, unabhängig von der tatsächlichen Lüfteranzahl (decode.h: fixer Offset, generische RotationsPerMin-Formel, keine Modellunterscheidung). Reine Dokumentationskorrektur im Vertrags-PHPDoc, kein Feld-/Verhaltensänderung

## 1.23.0 — 2026-08-24

- `HEISHA_GetFunctions()` liefert jetzt `internalHeaterStateID`/`externalHeaterStateID` (Backup-/Zusatzheizstab aktiv, main/Internal_Heater_State bzw. main/External_Heater_State) sowie `forceHeaterStateID` (Notheizstab-Taste, main/Force_Heater_State) - contractVersion 1.12. Auslöser: NRGDashboard wollte den Heizstab im Hydraulikschema zeigen; im Feldregister mit EMS abgestimmt. Wichtige Klarstellung dabei: main/DHW_Heater_State und main/Room_Heater_State (Gegenstücke zu SetDHWHeaterState/SetRoomHeaterState) sind reine Freigabe-Flags, keine Laufzeit-Statuswerte - der reale Split ist Intern/Extern nach Hardware-Lage, nicht WW/Raum. Reine Vertragserweiterung, keine neuen Formularfelder in diesem Modul

## 1.22.0 — 2026-08-21

- Taktschutz-Regelwerk schützt jetzt optional auch den Kühlbetrieb (Dietmars Nachfrage: Taktung trat während laufender Kühlung auf). Grund war ein echter Geltungslücken-Fund: der Taktschutz sperrt nach Verdichterstopp die Heizanforderung (`Z1_Heat_Request_Temp`) — beim Kühlen steuert die Anlage aber über eine eigene Firmware-Variable (`Z1_Cool_Request_Temp`), die der Taktschutz nie berührt hat, daher wirkungslos während des Kühlens. Neu: Kontrollkästchen "Auch im Kühlbetrieb schützen" ergänzt einen unabhängigen, gleichartigen zweiten Regelzweig auf die Kühlanforderung (eigener Timer, eigene Außentemperatur-Obergrenze — bei großer Hitze hat Wiederanlauf Vorrang, spiegelbildlich zur Kälte-Priorität beim Heizen). Beide Namen gegen die Firmware-Tabellen (`decode.h`/`commands.h`) verifiziert; die reale Regelwerk-Syntax (mehrere unabhängige `if`-Blöcke in einem `on`-Ereignis) ist im Firmware-Beispiel `Jeisha-DHW-Radiators-Rowbuffer` bereits so belegt

## 1.21.1 — 2026-08-20

- Sichtbare Rückmeldung bei "Reihenfolge und Auswahl zurücksetzen" ergänzt (neue verbindliche Verbund-Konvention, SUITE.md: jeder Button muss ohne Formular-Neuöffnen erkennen lassen, dass etwas passiert ist). Der Button änderte die Liste zwar bereits live, aber ohne Hinweis, dass die Änderung nur in der offenen Maske liegt und noch "Änderungen übernehmen" fehlt — jetzt mit Bestätigungstext. Alle anderen Buttons und schaltbaren Variablen im Formular hatten bereits sichtbares Feedback (geprüft)

## 1.21.0 — 2026-08-18

- Dokumentation & Hilfe massiv ausgebaut (Dietmars Feedback "viel zu wenig"): Das Doku-Panel erklärt jetzt jeden Formularbereich ausführlich (Verbindung, Datenpunkte inkl. beider Schaltflächen, 1-Wire, Verknüpfungsstruktur, externer Stromzähler, Energiespar-Prüfung, Regelwerke, Neustart-Watchdog, Archivierung, Zusätzliche Befehle, Platinen-Diagnose, Verbund-Integration). Vier neue ?-Hilfen direkt am Feld: Debug-Option, Leistungs- und Energiezähler-Auswahl (inkl. der klassischen Fallstricke Hauszähler bzw. Tageswert statt kumulativem Zähler) und HeishaMon-IP. Rein dokumentarisch, keine Verhaltensänderung

## 1.20.0 — 2026-08-18

- Abschlusspaket nach vollständiger Repo-/Integrations-Durchsicht (Lückenvergleich mit Home Assistant, openHAB, Domoticz, ioBroker, Node-RED):
  - **Platinen-Diagnose** aus dem bisher ignorierten `stats`-Topic der Firmware: WLAN-Qualität, Laufzeit seit Neustart, MQTT-Neuverbindungen, Bus-Lesequalität (Anteil fehlerfreier Datagramme), aktive Regeln, Firmware-Version — als Variablen in der neuen Gruppe **Platinen-Diagnose**, WLAN-Qualität und MQTT-Neuverbindungen automatisch archiviert (Datenbasis für die Aussetzer-Analyse). Aktive Regeln zeigt zudem, ob das aufgespielte Taktschutz-Regelwerk läuft
  - **S0-Zähler** direkt an der Platine (GPIO12/GPIO14): Leistung und Gesamtenergie je Port als Datenpunkte (neue Gruppe **S0-Zähler**); die Gesamtenergie wird von Wh auf kWh skaliert und taugt damit direkt als Quelle für den externen Energiezähler der COP-Berechnung — echte Messung ganz ohne zusätzlichen Shelly
  - Generischer `scale`-Faktor in der Topic-Definition (für die Wh→kWh-Umrechnung)

## 1.19.0 — 2026-08-18

- Neuer Bereich **🔄 Neustart-Watchdog** (Dietmars Wunsch nach Selbstheilung bei Netzwerk-Hängern; Rules können das nicht — die Engine hat weder eine Neustart-Funktion noch WLAN-Sichtbarkeit, im Firmware-Code verifiziert): Bleibt die Wärmepumpe länger als eingestellt (Standard 10 min) per MQTT offline, stößt das Modul über den `/reboot`-Endpunkt der Firmware einen Platinen-Neustart an. Höchstens ein Versuch je 30 Minuten (Schutz vor Neustart-Schleifen); klare Log-Meldung, wenn auch HTTP nicht mehr antwortet (dann hilft nur Stromtrennung). Standardmäßig aus (Opt-in), nutzt die Platinen-IP aus dem Regelwerke-Bereich

## 1.18.1 — 2026-08-18

- Regelwerk-Upload repariert: der Upload schlug mit "nicht erreichbar" fehl, obwohl die Platine antwortete — `Sys_GetURLContentEx` kennt laut IPS-Doku nur Timeout-/Auth-/SSL-Optionen und ignorierte die `Method`/`Content`-Parameter stillschweigend (es ging ein GET statt POST raus, den der HeishaMon-Webserver ablehnt). Der POST läuft jetzt über PHP-Streams

## 1.18.0 — 2026-08-17

- Neuer Bereich **⚙️ Energiespar-Regelwerke** (Ebene A des Energiespar-Konzepts, EMS-Schichtung abgestimmt: Platinen-Regelwerke = autonome Schutz-Schicht, Planung bleibt bei EMS/IPS — Stellhebel-Konflikt geprüft, EMS nutzt `SetZ1HeatRequestTemperature` nicht): Das Modul kann einen **parametrisierten Taktschutz** (Vorlage: `Compressor-Short-Cycle-Guard` aus dem HeishaMon-Repo; Sperrzeit und Außentemperatur-Schwelle einstellbar) per HTTP direkt auf die HeishaMon-Platine spielen (`POST /saverules`, gleiches Format wie deren Weboberfläche) — läuft dort autonom weiter, auch wenn WLAN/IPS ausfallen. Deutliche Warnung im Formular + Bestätigungsdialog: der Upload ersetzt das komplette auf der Platine gespeicherte Regelwerk; die Firmware validiert selbst und behält bei Fehlern das alte. Neue Properties: HeishaMon-IP, Sperrzeit (Standard 45 min), Außentemperatur-Schwelle (Standard 2 °C)

## 1.17.0 — 2026-08-17

- Neuer Bereich **💡 Energiespar-Prüfung** (Dietmars Auftrag, Ebene B des Energiespar-Konzepts): bewertet die von der Anlage empfangenen Einstellwerte gegen Richtwerte aus den dokumentierten HeishaMon-Beispielregelwerken (`Examples/Rules/`) und dem Panasonic-Servicehandbuch — Takt-Analyse (Laufzeit je Verdichterstart aus den echten Zählern), Warmwasser-Sollwert und -Nachheizdelta, Heizstab-Freigabetemperatur und -Verzögerung, Heizgrenze, Heizkurven-Sollwert, Pumpenansteuerung. Reine Anzeige mit ✅/💡/⚠️-Befunden, es wird nichts verändert; Prüfungen ohne empfangene Datenbasis entfallen still. Regelwerk-Vorlagen-Verwaltung (Ebene A/C) folgt nach EMS-Abstimmung separat

## 1.16.0 — 2026-08-17

- Automatische Archivierung der Monitoring-Datenpunkte (Anfrage der HeatMonitor-Kachel, nach Dietmars Test "Kachel zeigt nichts": ohne Archivdaten bleiben alle Zeitreihen-Ansichten leer). Neues Panel "📊 Archivierung": aktiviert per Standard das Logging für elektrische/thermische Leistung, Vorlauf/Rücklauf/Außentemperatur, COP (3×), Abtaustatus sowie Verdichter-Starts/-Betriebsstunden (letztere mit Zähler-Aggregation → Starts/Stunden je Periode). Nutzer-Hoheit gewahrt: jede Variable wird nur EINMAL aktiviert — eine spätere manuelle Abwahl im Archiv-Handler bleibt dauerhaft erhalten; die Checkbox stoppt künftige Aktivierungen, deaktiviert aber nichts rückwirkend

## 1.15.0 — 2026-08-17

- Vertragsfelder für WP-Monitoring-Seiten (contractVersion 1.9 → 1.10, für die geplante Monitoring-Kachel im NRGDashboard nach OpenEnergyMonitor-Vorbild): `heatOutputPowerID` (neue Summenvariable **Thermische Leistung (gesamt)** = Heizen+Kühlen+WW-Erzeugung), `outsideTempID` (Außenfühler der WP), `compressorStartsID` (Starts-Zähler, für Takt-Analysen), `operationsHoursID` (Betriebsstunden). Rein additiv

## 1.14.0 — 2026-08-17

- `HEISHA_GetFunctions()` um COP/Arbeitszahl erweitert (contractVersion 1.8 → 1.9, mit EMS/Dashboard abgestimmt): `copEstimateID` (WP-eigene Schätzung), `copMeasuredID` (echte Messung via externem Zähler), `dailyPerformanceFactorID` (Tages-Arbeitszahl) — jeweils 0, wenn die Datenquelle nicht konfiguriert ist. Monats-/Jahres-Arbeitszahlen bewusst NICHT im Vertrag (EMS-Entscheid): Zeitraum-Aggregation über die kumulativen Werte ist Sache der Konsumenten (Archiv/GleitenderMittelwert). Rein additiv

## 1.13.2 — 2026-08-16

- Mischventil-Positionsvariablen verbessert (Beitrag der NRGDashboard-Sitzung, ausgelöst durch Dietmars Frage zum Prozentwert im Anlagenschema): `Z1/Z2_Valve_PID` zeigen jetzt das Suffix " %" und heißen "Mischventil Zone 1/2 Stellung" statt des Implementierungsdetails "Zone 1/2 Ventil PID". Bestehende Installationen: Suffix kommt automatisch beim Übernehmen; die Umbenennung über die Schaltfläche "Variablennamen aktualisieren" (erkennt den alten deutschen UND englischen Standardnamen, eigene Namen bleiben unangetastet)

## 1.13.1 — 2026-08-15

- Mobile-App-Absturz behoben ("Invalid Configuration: type 'Null' is not a subtype of type 'int'" auf iPad/iPhone, z. B. bei der SmartGrid-Modus-Kachel) — Fund aus einer Nutzerrückmeldung im Symcon-Forum: alle Options-Darstellungen (Enums, Online/Offline-Status) nutzten den falschen Schlüssel `ColorValue` statt `Color`. Die Web-/Konsolen-Darstellung tolerierte das, die Mobile-App nicht (von Symcon im Community-Forum bestätigtes Muster). Bestehende Variablen werden beim Übernehmen der Konfiguration bzw. Modul-Update automatisch einmalig migriert

## 1.13.0 — 2026-08-14

- Neue Variable **Betriebsart (vereinheitlicht)** + Vertragsfeld `operatingModeNormID` (contractVersion 1.7 → 1.8): herstellerneutraler Verbund-Enum (0=standby, 1=heating, 2=cooling, 3=dhw, 4=heating+dhw, 5=cooling+dhw, in SUITE.md festgeschrieben), vom Modul aus der Panasonic-Betriebsart abgeleitet — Konsumenten wie NRGDashboard müssen den rohen Hersteller-Enum nicht mehr selbst interpretieren (Einwand von Dashboard, Entscheid von EMS). `operatingModeID` (Rohwert) bleibt für Diagnose. Erscheint auch in der Verknüpfungsstruktur (Gruppe Betrieb)

## 1.12.0 — 2026-08-14

- `HEISHA_GetFunctions()` erweitert (contractVersion 1.6 → 1.7, mit EMS/Dashboard abgestimmt, ausgelöst durch zwei Live-Funde auf Dietmars Anlage):
  - `operatingModeID` — konfigurierte Betriebsart (Enum 0–8: Heizen/Kühlen/Auto/WW-Kombinationen); Konsumenten mussten den Kühlbetrieb bisher aus dem Vorzeichen der Spreizung ableiten
  - `z1MixingValvePositionID`/`z2MixingValvePositionID` — absolute Mischventil-Position in Prozent (`Z1/Z2_Valve_PID`), aussagekräftiger als die bestehenden Stellrichtungs-Felder (die unverändert bleiben)
  - `indoorPipeTempID` — Rohrtemperatur Inneneinheit, im Kühlbetrieb die tatsächlich kalte Kältemittelseite
  - Quellenverbesserung `z1PumpID`/`z2PumpID`: bevorzugt jetzt `main/Z1/Z2_Pump_State` aus dem Kernprotokoll (meldet auch eine echte, physisch verbaute CZ-NS4P — die bisherigen `optional/...`-Emulations-Topics blieben bei echter Platine stumm), Emulations-Variablen als Fallback. Semantik unverändert

## 1.11.0 — 2026-08-14

- `HEISHA_GetFunctions()` um `suctionTempID` erweitert (contractVersion 1.5 → 1.6): Sauggas-/Kaltgastemperatur als Gegenstück zur Heißgastemperatur, für Dashboards Kältekreis-Darstellung. Funktional-herstellerneutral benannt — bei Panasonic gibt es keinen expliziten Sauggas-Sensor, geliefert wird die beste verfügbare Messstelle `Eva_Outlet_Temp` (Verdampferaustritt, direkt vor dem Verdichter). Mit EMS/Dashboard abgestimmt. Rein additiv

## 1.10.0 — 2026-08-13

- Neuer optionaler Bereich "Zusätzliche Befehle": Relais 1/2 der großen HeishaMon-Platine (`gpio/relay/one`/`two`) sowie SmartGrid-Modus (`SetSmartGridMode`) als digitaler Ersatz für die native SG-Ready-Funktion, die sonst nur über physische Trockenkontakte am Außengerät geschaltet werden kann (erfordert an der Wärmepumpe selbst die Service-Einstellung "Optional PCB" = Ja). Beides reine Schreibbefehle ohne Rückmeldung von der Anlage — Fund aus einer Nutzerrückmeldung im Symcon-Forum. Standardmäßig deaktiviert (Checkbox)

## 1.9.0 — 2026-08-13

- `HEISHA_GetFunctions()` um Lüfterdrehzahl erweitert (contractVersion 1.4 → 1.5): `fan1SpeedID`, `fan2SpeedID` (U/min, 0 wenn nicht empfangen) — für Dashboards Anlagenschema-Animation des Außengeräts (Lüfter-Rotation statt Ersatzkopplung an die Verdichterfrequenz). Mit EMS abgestimmt. Rein additiv

## 1.8.0 — 2026-08-13

- `HEISHA_GetFunctions()` um 4 weitere Felder erweitert (contractVersion 1.3 → 1.4): `z1PumpID`, `z1MixingValveID`, `z2PumpID`, `z2MixingValveID` — externe Heizkreispumpe/-Mischventil an der optionalen 2. Steuerplatine (physikalisch getrennt von der bereits vorhandenen internen Pumpe `pumpFlowID`/`pumpSpeedID`/`pumpDutyID`). Mit WPHub/Dashboard/EMS abgestimmt. Mischventil liefert eine Stellrichtung (Zu/Auf/Aus), keine absolute Position. Rein additiv

## 1.7.1 — 2026-08-12

- Doku (Code-Kommentar in `GetFunctions()` und README) klargestellt: die Heizkreislauf-Felder sind Teil des gemeinsamen, herstellerneutralen `heatpump`-Vertragstyps (von EMS final abgestimmt), nicht HeishaMon-spezifisch. Rein dokumentarisch, keine Verhaltens- oder Feldnamensänderung

## 1.7.0 — 2026-08-12

- `HEISHA_GetFunctions()` um 14 Heizkreislauf-Felder erweitert (contractVersion 1.2 → 1.3): `pumpFlowID`, `pumpSpeedID`, `pumpDutyID`, `threeWayValveStateID`, `twoWayValveStateID`, `mainInletTempID`, `mainOutletTempID`, `z1WaterTempID`, `z2WaterTempID`, `dhwTempID`, `bufferTempID`, `compressorFreqID`, `dischargeTempID`, `defrostingStateID` — abgestimmt mit WPHub/NRGDashboard für eine Anlagenschema-Visualisierung. Rein additiv, kein Formular-Feld betroffen

## 1.6.1 — 2026-08-12

- Erklärtext der Verknüpfungsstruktur korrigiert: der vorige Hinweis "nicht im WebFront sichtbar" (v1.5.5) war falsch — die Verknüpfungsstruktur ist genau dafür gedacht, im WebFront eine übersichtliche gruppierte Navigation statt einer langen flachen Variablenliste zu ergeben. Text stellt das jetzt richtig dar. Rein textlich, keine Verhaltensänderung

## 1.6.0 — 2026-08-12

- Neu: 1-Wire-Temperatursensoren (z. B. DS18B20) am HeishaMon werden automatisch erkannt und angelegt, sobald ein Messwert eintrifft — eigenes Formularpanel "1-Wire-Sensoren (optional)" zur Benennung und Aktivierung. Bisher mussten Nutzer diese Sensoren manuell als eigene MQTT-Client-Variable einbinden (Fund aus einer Nutzerrückmeldung im Symcon-Forum). Erscheinen auch in der optionalen Verknüpfungsstruktur (eigene Gruppe "1-Wire-Sensoren")

## 1.5.5 — 2026-08-12

- Erklärtext der Verknüpfungsstruktur präzisiert — Fund aus einer echten Nutzerrückmeldung im Symcon-Forum: ohne gewählte Zielkategorie legt das Modul stillschweigend nichts an (kein Fehlerhinweis), und das Ergebnis erscheint nur im Objektbaum der Verwaltungskonsole, nicht im WebFront. Beides steht jetzt explizit im Panel-Text; Feldbeschriftung der Zielkategorie ergänzt um "(erforderlich, damit die Verknüpfungsstruktur angelegt wird)". Rein textlich, keine Verhaltensänderung

## 1.5.4 — 2026-07-28

- Panel-Titel "Link structure" und "External energy meter" um "(optional)" ergänzt — Fund nach expliziter Prüfung gegen Dietmars EMS-Usability-Befund (Panel wirkte wie Pflichtfeld, war aber optional/Fallback). Beide Panels sind bei uns per Default aus/leer und das Modul funktioniert ohne sie vollständig; das war aus dem Titel allein nicht ersichtlich. Rein optisch, keine inhaltliche Änderung

## 1.5.3 — 2026-07-27

- PopupButton-Caption final auf `"?"` gesetzt (statt `"i"`) — Dietmars Entscheidung, `"i"` wirkt bei 70px Breite optisch verloren. Rein optisch, keine inhaltliche Änderung

## 1.5.2 — 2026-07-27

- PopupButton-Konvention präzisiert (Erkenntnis von InverterHub: unter ~70px hat `width` im WebFront-Skin keinen sichtbaren Effekt, Icon-Größe/Hintergrund nicht änderbar): Caption der beiden Info-Buttons von ℹ️-Emoji auf reinen Buchstaben `i` mit `width: 70px` umgestellt. Rein optisch, keine inhaltliche Änderung

## 1.5.1 — 2026-07-27

- Zwei ℹ️-Infoschaltflächen ergänzt (PopupButton, Klick statt Hover - Symcon kennt keine nativen Mouseover-Tooltips): beim MQTT-Basistopic (muss exakt zum HeishaMon passen) und bei der Mindestleistung für die COP-Berechnung (Grund/Wirkung des Schwellwerts). Reine Formular-Politur, kein Code-/Vertragsimpact

## 1.5.0 — 2026-07-27

- `HEISHA_GetFunctions()` liefert additiv `reachable` (bool): meldet, ob die Wärmepumpe gerade erreichbar ist. `PowerID` wird bei Nichterreichbarkeit nicht zurückgesetzt, sondern friert beim letzten bekannten Wert ein — Konsumenten können damit einen veralteten Wert erkennen, statt ihn ungeprüft zu übernehmen. Vertragsversion damit `1.2`. Proaktiv gefunden bei der Prüfung gegen das Verbund-Zielbild (Zuverlässigkeit ohne KI-Krücke)

## 1.4.2 — 2026-07-27

- README: neuer Abschnitt „§14a-Stellhebel" — dokumentiert, welche bestehenden Set-Befehle (Heizstab sperren/freigeben, Quiet Mode, Sollwert-/Kurvenverschiebung) als Lasthebel für eine §14a-Steuerbox-Anbindung dienen, plus die zwei Schutzbedingungen (keine Eingriffe während Abtauung, Sterilisation nur verschieben). Reine Dokumentation, keine Code-Änderung, kein neuer Vertrag

## 1.4.1 — 2026-07-24

- Layout-Politur: Doku-Panel steht jetzt an erster Stelle (vor den Funktionsfeldern), passend zur einheitlichen NRG-Stack-Formular-Optik und zur Referenzimplementierung InverterHub

## 1.4.0 — 2026-07-24 (Formular-Optik)

- Konfigurationsmaske auf die einheitliche NRG-Stack-Formular-Optik umgestellt: „📖 Dokumentation & Hilfe“ (eingeklappt, mit Versionsnummer), „🆕 Neu in Version …“ (aufgeklappt, pro Version bestätigbar) und ein einmalig ausblendbarer Forum-Hinweis. Referenzimplementierung: InverterHub

## 1.3.0 — 2026-07-24

- `HEISHA_GetFunctions()` liefert additiv `unit` ('W'): physikalische Einheit von `PowerID`, rein informativ für Konsumenten ohne eigenes Variablenprofil auf der Presentation-basierten Leistungsvariable. Vertragsversion damit `1.1`

## 1.2.1 — 2026-07-23

- Lizenzwechsel von MIT auf **PolyForm Noncommercial License 1.0.0** (NRG-Stack-weit): private/nicht-kommerzielle Nutzung frei, gewerbliche Nutzung lizenzpflichtig. Gilt ab diesem Stand; ältere, unter MIT veröffentlichte Versionen bleiben MIT. Code und Verträge unverändert

## 1.2.0 — 2026-07-23

- `HEISHA_GetFunctions()` liefert additiv `contractVersion` (Vertragsversion `Major.Minor`, Start `1.0`) — Teil der Versionierungskonvention des NRG-Stack. Ändert die bestehenden Felder nicht
- README: Verweis auf das Suite-Manifest (welche Modulstände zusammenpassen)

## 1.1.2 — 2026-07-22

- Sprachregel des Modul-Verbunds umgesetzt: „Link/Linkstruktur" heißt jetzt durchgängig „Verknüpfung/Verknüpfungsstruktur", „Drag & Drop" wurde ersetzt. Betrifft nur Anzeigetexte — Idents, Eigenschaften und der `HEISHA_GetFunctions`-Vertrag bleiben unverändert
- Weitere Anzeigetexte eingedeutscht: Button → Schaltfläche, Slider → Schieberegler, Checkbox → Spalte/Auswahl, Update → Aktualisierung, Logging → Aufzeichnung im Archiv
- Konventionen in CLAUDE.md festgehalten (Sprachregel, Idents sind API, Zweig-Modell, Stolperfallen)

## 1.1.1 — 2026-06-18

- `HEISHA_GetFunctions()` liefert zusätzlich `Measured` (bool): unterscheidet echte Messung von der HeishaMon-Schätzung. Notwendig, weil Leistungs- und Energievariable unabhängig konfigurierbar sind und die Genauigkeit sich daher nicht aus `EnergyID` ableiten lässt

## 1.1.0 — 2026-06-18

- Neue Variable **Elektrische Leistung (gesamt)** (W): gemessener Wert des externen Stromzählers, sonst Summe der HeishaMon-Schätzwerte
- Neue Funktion `HEISHA_GetFunctions()`: meldet Art, Bezeichnung sowie Leistungs- und Energiezähler-Variable, damit andere Module (z. B. Energiefluss-Visualisierungen) die Wärmepumpe ohne manuelle Zuweisung einbinden können

## 1.0.1 — 2026-06-18 — Review-Anpassungen

- Vendor auf „HeishaMon“ gesetzt
- „Reihenfolge und Auswahl zurücksetzen“ aktualisiert nur noch die offene Konfiguration (UpdateFormField) statt die Eigenschaft direkt zu schreiben; persistiert wird erst beim Übernehmen durch den Nutzer

## 1.0 — 2026-06-18 — Erstveröffentlichung im Module Store

### Funktionen

- Anbindung eines HeishaMon an IP-Symcon über MQTT Server oder MQTT Client
- Alle HeishaMon-Datenpunkte (`main/TOP0` … `main/TOP143` sowie Optional-PCB-Topics) mit passenden Darstellungen (Temperaturen, Leistungen, Betriebsarten, Schalter)
- Automatisches Anlegen der Statusvariablen beim ersten Empfang — es erscheinen nur Datenpunkte, die die eigene Anlage liefert
- Schreibbare Werte (Solltemperaturen, Betriebsart, Flüstermodus, Powerful-Modus u. v. m.) direkt schaltbar über `<Basistopic>/commands/SetXxx`
- Datenpunkt-Auswahl in der Konfiguration: Spalten Aktiv/Name/Gruppe/Topic/Empfangen; abgewählte Datenpunkte werden ausgeblendet (Objekt-ID und Archivdaten bleiben erhalten)
- Sortierung der Datenpunkte per Drag & Drop; Variablen-Positionen und Linkstruktur folgen der Reihenfolge, sinnvolle Standard-Sortierung nach Gruppen
- Optionale gruppierte Linkstruktur (Betrieb, Heizen, Kühlen, Warmwasser, Leistung & COP, Gerätewerte, Anlagenkonfiguration, Optional-PCB) an frei wählbarer Zielkategorie
- COP-Berechnung: HeishaMon-Schätzung, gemessener COP über externen Stromzähler (z. B. Shelly 3EM) sowie Tages-Arbeitszahl mit Wärmemengen-Integration und exakter Stromenergie aus dem Zählerstand
- Verfügbarkeitsanzeige über das LWT-Topic
- Skript-Funktionen `HEISHA_SendSetCommand` und `HEISHA_SetCurves` (Heiz-/Kühlkurven als JSON)
- Vollständige deutsche Übersetzung
- Button „Variablennamen aktualisieren“: übernimmt verbesserte Übersetzungen für Variablen mit Standardnamen, selbst vergebene Namen bleiben erhalten
