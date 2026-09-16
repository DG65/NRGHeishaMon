# Changelog

## 1.1.2 — 2026-09-16

- Undokumentierte Presentation-Parameter entfernt, die der Symcon-Kernel bislang nur still ignoriert hat: `CAPTION_ON`/`CAPTION_OFF` bei `VARIABLE_PRESENTATION_SWITCH` sowie `DIGITS` bei `VARIABLE_PRESENTATION_VALUE_INPUT` (schaltbare Ganzzahlfelder ohne Min/Max wie Zonen-Solltemperaturen). Kein sichtbarer Verhaltensunterschied - beide Parameter hatten laut SDK-Dokumentation nie eine Wirkung. Anlass: eine bevorstehende strengere Presentation-Validierung im Symcon-Kernel macht aus dem bisherigen stillen Ignorieren einen harten Abbruch. Gezielter Vorab-Fix für den Store-Stand, unabhängig vom weiter fortgeschrittenen `beta`-Zweig

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
