# [Modul] HeishaMon — Panasonic Aquarea Wärmepumpe in IP-Symcon

*(Aktualisierung der Erstvorstellung vom 18. Juni — das Modul hat seither einen großen Entwicklungsschub gemacht, aktueller Stand: v1.28.0. Wichtig vorweg: die Lizenz hat seit v1.2.1 gewechselt, siehe unten — das Modul steht NICHT mehr unter der im Erstbeitrag genannten MIT-Lizenz.)*

## Worum geht es?

[HeishaMon](https://github.com/heishamon/HeishaMon) ist eine kleine Platine, die am CN-CNT-Bus einer Panasonic Aquarea Wärmepumpe (H/J/K/L/M-Serie) mitliest und über 150 Datenpunkte per MQTT bereitstellt — **komplett lokal, ohne Cloud, ohne Abo**. Dieses Modul bindet den HeishaMon vollständig in IP-Symcon ein: alle Datenpunkte als Variablen, schaltbare Werte direkt bedienbar, dazu eine ganze Reihe Funktionen, die deutlich über das reine Anzeigen hinausgehen.

[BILD 1: Architektur-Schaubild — heishamon_architektur.png]

## Was das Modul von anderen Anbindungen unterscheidet

Alle anderen SmartHome-Anbindungen für HeishaMon (Home Assistant, openHAB, Domoticz, ioBroker, Node-RED) sind reine Datenpunkt-Mapper. Dieses Modul kann vier Dinge, die es dort nicht gibt:

[BILD 2: Übersicht Alleinstellungsmerkmale — heishamon_usps.png]

- **💡 Energiespar-Prüfung** — bewertet die von der Anlage empfangenen Service-Einstellungen (Warmwasser-Sollwert, Heizstab-Freigaben, Heizgrenze, Heizkurve, Pumpenansteuerung) und das *echte* Taktverhalten (Laufzeit je Verdichterstart aus den Betriebszählern) anhand von Richtwerten aus den dokumentierten HeishaMon-Referenz-Regelwerken und dem Panasonic-Servicehandbuch. Konkrete Sparhinweise direkt in der Konfiguration — reine Anzeige, verändert nichts.
- **⚙️ Taktschutz per Knopfdruck** — das Modul spielt ein parametrisiertes Schutz-Regelwerk (Wiederanlauf-Sperre nach Verdichterstopp, mit Kälte-Override) direkt auf die HeishaMon-Platine. Es läuft dort autonom weiter, selbst wenn WLAN oder IP-Symcon ausfallen. Sperrzeit und Temperaturschwelle sind einstellbar, optional auch für den Kühlbetrieb (eigener Regelzweig, da die Anlage Heiz- und Kühlanforderung getrennt führt); die Firmware validiert jeden Upload selbst.
- **🔄 Neustart-Watchdog + 🩺 Platinen-Diagnose** — hängt MQTT länger als eingestellt, startet das Modul die Platine über deren Reboot-Schnittstelle neu (Opt-in, mit Schleifenschutz). Und die Diagnose-Gruppe (WLAN-Qualität, Laufzeit, MQTT-Neuverbindungen, Bus-Lesequalität, Firmware-Version) zeigt bei Verbindungsproblemen die *Ursache* statt nur das Symptom — WLAN-Qualität und Neuverbindungen werden automatisch archiviert.
- **🤝 NRG-Stack-Verbund** — andere Module der NRG-Stack-Familie (Energiemanagement, Anlagenschema-Kachel, Wärmepumpen-Monitor) finden die Wärmepumpe automatisch über `HEISHA_GetFunctions()` — ohne manuelle Variablen-Zuweisung, inklusive gemessenem COP, Tages-Arbeitszahl, herstellerneutraler Betriebsart und Heizstab-Status. Ist bereits ein MeterHub-Zähler mit Funktionszuordnung „Wärmepumpe" vorhanden, schlägt das Modul ihn automatisch vor. Wer zusätzlich WPHub (Panasonic Comfort Cloud) für dieselbe Anlage nutzt, bekommt einen Hinweis, damit beide Kanäle nicht gleichzeitig schreiben.

## Funktionsumfang im Überblick

**Datenpunkte & Bedienung**
- 151+ Datenpunkte, automatisch angelegt sobald die Anlage sie sendet; Spalte „Empfangen" zeigt, was die eigene Anlage tatsächlich liefert
- Schaltbare Werte (Betriebsart, Solltemperaturen, Flüstermodus, Powerful, Force-DHW, Heizkurven u. v. m.) direkt aus Symcon
- Auswahl und Reihenfolge der Datenpunkte frei konfigurierbar (Ziehen mit der Maus); Abwählen blendet aus, Archivdaten bleiben
- Optionale **Verknüpfungsstruktur**: gruppierter Kategoriebaum (Betrieb/Heizen/Warmwasser/…) für eine aufgeräumte WebFront-Navigation

[SCREENSHOT A: Instanz-Konfiguration mit Datenpunkt-Liste]

**COP & Arbeitszahl**
- COP aus den Anlagen-Schätzwerten **und** echt gemessen über einen externen Zähler (z. B. Shelly 3EM auf der WP-Phase — oder ein direkt an der Platine angeschlossener S0-Zähler, ganz ohne Zusatzaktor)
- Tages-Arbeitszahl aus Wärmemenge und kWh-Zählerstand, mit Mitternachts- und Zählertausch-Logik
- Thermische Gesamtleistung als eigene Variable — zusammen mit der elektrischen Leistung die Basis für Monitoring-Charts

[SCREENSHOT B: Energiespar-Prüfung mit Befunden]

**Erweiterte Hardware-Unterstützung**
- **1-Wire-Temperatursensoren** (z. B. DS18B20 am Puffer): automatische Erkennung beim ersten Messwert, Benennung im Formular
- **S0-Zähler** an der Platine: Leistung + Gesamtenergie (kWh) je Port
- **Relais der großen Platine** und **SmartGrid-Modus** als digitaler SG-Ready-Ersatz (die vier Betriebsstufen Normal/Überhöhung 1+2/WP+Heizstab aus per MQTT statt Trockenkontakt)

**Komfort & Betrieb**
- Automatische **Archivierung** aller Monitoring-Datenpunkte (einmalig, Nutzer-Abwahl wird respektiert) — Zeitreihen-Kacheln funktionieren ohne Handarbeit
- Ausführliche **Dokumentation & Hilfe** direkt im Formular, ?-Hilfen an allen erklärungsbedürftigen Feldern
- Vollständig deutsch übersetzt; über 260 automatisierte Tests sichern jede Version ab

[SCREENSHOT C: WP-Monitor-Kachel mit Tagesverlauf (elektrisch/thermisch/Temperaturen) — optional, eigenes NRG-Stack-Modul]
[SCREENSHOT D: Anlagenschema-Kachel mit Live-Zuständen — optional, eigenes NRG-Stack-Modul]

## Voraussetzungen

- IP-Symcon ab Version 9.0
- HeishaMon-Platine (Original oder Nachbau) am CN-CNT-Port der Wärmepumpe, mit MQTT verbunden
- Ein MQTT-Server/-Broker in Symcon (Kern-Instanz oder extern)

## Installation & Einrichtung

1. **Modulverwaltung → Symcon Store** → „HeishaMon" suchen und hinzufügen, dabei den **Beta-Kanal** wählen (führt den aktuellen Funktionsstand; der Stable-Kanal folgt zu gegebener Zeit). Wer stattdessen manuell per GitHub-URL installieren möchte: `https://github.com/DG65/NRGHeishaMon`, Zweig `beta`.
2. HeishaMon-Instanz unter dem MQTT-Server anlegen
3. **MQTT-Basistopic** eintragen (muss exakt dem in der HeishaMon-Weboberfläche entsprechen, Standard `panasonic_heat_pump`) — fertig, die Variablen entstehen von selbst

Alles Weitere (Zähler, Verknüpfungsstruktur, Regelwerke, Watchdog) ist optional und im Formular ausführlich dokumentiert.

## Lizenz

Wie alle Module des NRG-Stack:

> Lizenz: PolyForm Noncommercial 1.0.0 — private/nicht-kommerzielle Nutzung
> ist frei, gewerbliche Nutzung erfordert eine gesonderte Lizenz vom
> Rechteinhaber (DG65). Der Wechsel wirkt nur nach vorn: bereits unter MIT
> veröffentlichte Altversionen (bis Version 1.2.0) bleiben MIT. Der
> vollständige Lizenztext liegt im Repo (LICENSE). Spenden sind willkommen:
> [paypal.me/DietmarGureth](https://paypal.me/DietmarGureth).

Für die Symcon-Hobby-Community ändert sich damit nichts — private Nutzung bleibt komplett frei. Gewerbliche Interessenten (Integratoren/Dienstleister) melden sich einfach per PN.

## Haftung

Das Modul steuert eine reale Heizungsanlage. Alle Schreibbefehle nutzen die offiziellen HeishaMon-Kommandos, dennoch gilt: Nutzung auf eigene Verantwortung, keine Gewähr für Schäden an der Anlage. Service-Einstellungen (Heizkurven, Heizstab-Freigaben etc.) bitte bewusst ändern — im Zweifel mit dem Fachbetrieb.

## Dank & Feedback

Ein großer Teil der letzten Versionen geht direkt auf Rückmeldungen aus diesem Thread zurück (1-Wire-Erkennung, Relais, SmartGrid-Modus, Mobile-App-Fix — danke, Wuwu!). Fehler, Wünsche und Anlagen-Erfahrungen gerne hier in den Thread.

Vollständige Versionshistorie: [CHANGELOG im Repo](https://github.com/DG65/NRGHeishaMon/blob/beta/CHANGELOG.md)
