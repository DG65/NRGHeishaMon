<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/HeishaMonTopics.php';
require_once __DIR__ . '/../libs/HeatingAutomation.php';
require_once __DIR__ . '/../libs/Schedule.php';

/**
 * HeishaMon
 *
 * Bindet einen HeishaMon (https://github.com/heishamon/HeishaMon) an IP-Symcon an.
 * Das Modul wird unter einem MQTT Server / MQTT Client (Splitter) angelegt und
 * erzeugt fuer jedes empfangene Topic automatisch eine passende Statusvariable.
 * Schreibbare Werte werden ueber <Basistopic>/commands/SetXxx an den HeishaMon gesendet.
 *
 * Wichtig: Der Klassenname muss exakt dem "name" in der module.json entsprechen
 * und ein gueltiger PHP-Klassenname sein (keine Bindestriche).
 */
class HeishaMon extends IPSModule
{
    //Einheitliche Formular-Optik (NRG-Stack-Konvention, siehe SUITE.md "Funktionspaare..."/
    //"Einheitliche Formular-Optik" Punkt 1): NEWS_VERSIONS-Array statt einzelnem NEWS_VERSION/
    //NEWS_ITEMS (verbundweite Umstellung, Dashboard/Dietmar 23.09.2026, Referenz NRGDashboard
    //Build 201) - Schluessel = Versionsnummer, newsBanner() zeigt gesammelt alles Neuere als
    //SeenNews. Die alte, ueber viele Releases gewachsene Liste bleibt als EIN Eintrag unter
    //ihrer letzten bisherigen NEWS_VERSION ('1.31.0') erhalten, neue Eintraege ab jetzt
    //bekommen ihre eigene Versionsnummer als Schluessel.
    private const NEWS_VERSIONS = [
        '1.31.0' => [
            'New: heat pumps of the K/L series and newer report invalid values in the standard power topics (e.g. -200 W). The module now automatically uses the precise power values of the firmware\'s extra data block (extra/ topics) for electrical power, thermal power and the estimated COP as soon as they arrive - previously these values were wrong on such units unless an external meter was set.',
            'New: a "🧡 About this module" panel at the very bottom of the form documents the license (PolyForm Noncommercial) and offers an optional PayPal donation link - always visible, never dismissible. The Symcon forum hint above it was restyled to match the rest of the module (dismissible panel instead of a plain row) and now links to the module\'s own discussion thread instead of the generic PHP module category.',
            'New: a "What is this module for?" panel now appears at the very top of the form, explaining in a couple of sentences what the module does and why - helpful when setting it up for the first time. Shown once, then dismissible for good.',
            'New: if MeterHub already has a meter assigned to function "heat pump", the "External energy meter" panel now suggests it automatically with a one-click "Adopt from MeterHub" button - no more manual variable search.',
            'New: the "?" help buttons next to individual fields now show the actual question they answer (e.g. "How does the short-cycle guard work?") instead of a bare "?" - you can see what a button explains before clicking it.',
            'New: the configuration form now warns when an active WPHub instance also exists - if both control the same physical heat pump (one locally via MQTT, one via the Panasonic Comfort Cloud), using both to send commands at the same time can produce contradicting settings. Display only, nothing is blocked or changed automatically.',
            'New: the short-cycle guard can now also protect cooling mode - the original guard only suppressed the heat request, which has no effect while the unit is cooling. Enable "Also protect cooling mode" in the "Energy saving rulesets" panel.',
            'New: board diagnostics - WiFi quality, uptime, MQTT reconnects, bus read quality, active rules and firmware version from the HeishaMon stats topic, as variables in the new "Board diagnostics" group.',
            'New: S0 meters connected directly to the HeishaMon board (power + energy total in kWh) are now available as datapoints - usable as source for the measured COP.',
            'New: reboot watchdog - after a prolonged MQTT outage the module can restart the HeishaMon board via its /reboot endpoint. See "Reboot watchdog" panel.',
            'New: energy saving rulesets - the module can deploy a parameterized short-cycle guard directly to the HeishaMon board, where it keeps running even without WiFi/IP-Symcon. See "Energy saving rulesets" panel.',
            'New: energy saving check - the module assesses the received unit settings (DHW target, backup heater enables, heating curve, cycling) against reference values. See "Energy saving check" panel.',
            'New: monitoring datapoints (power, temperatures, COP, defrost, compressor starts) are now archived automatically for time-series tiles - see "Archiving" panel to opt out.',
            'New: optional extra commands (large board relays, SmartGrid mode as a digital SG ready replacement) - see "Extra commands" panel.'
        ],
        '1.32.0' => [
            'New: the manually linked external power meter (for measured COP / performance factor) now has a selectable unit (Watt or Kilowatt) next to it. A meter reporting kilowatts was previously read as watts, making the reported electrical power, measured COP and daily performance factor wrong by a factor of 1000. Default stays Watt, matching prior behavior.'
        ],
        '1.33.0' => [
            'Change: automatic archiving of the monitoring datapoints is now off by default - previously it logged right away, a user should decide this deliberately instead of having to opt out afterwards. Enable "Archive monitoring datapoints automatically" in the "Archiving" panel if you want it.',
            'Fix: the datapoint list in the configuration form now always shows the order the variables actually have - if you reorder them from outside this module (e.g. by dragging in the console object tree), the form used to overwrite that on the next Apply even without touching the list yourself. It now keeps whatever order is really there.',
            'Fix: "Adopt from MeterHub" now only fills in the open form (as with "Reset order and selection") instead of also saving the properties directly - the previous combination could silently lose the filled-in values, since applying properties reloads the form.'
        ],
        '1.34.0' => [
            'Change: a meter MeterHub already has assigned to function "heat pump" is now used automatically for the measured COP - no click needed, and the module keeps following later changes to that assignment instead of a one-time snapshot. A status line in the "External energy meter" panel shows what is actually in effect; your own meter selection (now in a collapsed "Use your own meter instead" section) always takes priority over MeterHub.'
        ],
        '1.35.0' => [
            'New: heating automation (optional, panel "Heating automation"). Switches only the room heating on and off - never the whole heat pump, hot water keeps running - from the outside temperature (short and long mean, hysteresis, lock time) and optionally your room temperatures. Frost protection always wins, missing data keeps the heating on. Off by default; it only acts once you switch the new variable "Heating automation" on, and switching it off restores the heating.',
            'New: optional cooling in the automation, only with a dew point (or humidity) source: it starts only if the lowest cooling target of the heating curve stays above the dew point plus a safety margin and stops at once when the flow temperature falls below it.',
            'New: the automation finds its sources on its own - humidity sensors of the rooms (outdoor and soil moisture excluded, highest value counts) and one temperature sensor per room of the Raumstruktur module (outdoor and unheated rooms excluded, at least three rooms, median). Your own selection always overrides; the exclusion list of room and sensor names can be edited in the form, and status lines show what applies.',
            'New: after every operating mode command the module reads the mode back and retries up to three times - the unit never acknowledges commands itself. If someone changes the operating mode from outside (e.g. in the dashboard), the automation accepts that and waits for the lock time; only frost protection acts immediately.'
        ],
        '1.35.1' => [
            'Fix: after a restart of Symcon the measured-COP variables (COP measured, heat energy today, energy consumption today, performance factor today) could be deleted when MeterHub was not instantiated yet at that moment. The module now waits until Symcon is fully started before deciding about them and only then picks up the MeterHub meter.',
            'Fix: the on/off displays of boolean values (e.g. Online/Offline) used a color key that only enumerations know. Symcon 9.1 refused to create the instance because of it. Existing variables are converted automatically.'
        ],
        '1.36.0' => [
            'New: price block for dynamic electricity tariffs (optional, in the "Heating automation" panel). In the most expensive hours of the day the automation can pause the room heating (hot water is not touched), so the heat pump does not run exactly then. The price curve is found automatically (Tibber Grid Rewards, Börsenpreis) or taken from your own price variable. A block only starts when the rooms have a reserve, the daily number of blocks is not reached and frost protection is not active; it ends when the price drops, after the maximum duration or when the rooms get too cold.',
            'New: if you enter the usable capacity of your battery, no block happens while the battery (state of charge from InverterHub or your own variable, above its reserve) can carry the heat pump through the expensive hours anyway.'
        ],
        '1.37.0' => [
            'New: the price block now asks EMS (if installed) whether grid import would arise without the heat pump block - EMS knows PV forecast, battery plan, prices and reserve. If the PV system or the battery carries the load anyway, there is no block. Nothing to enter; the simple battery check from 1.36.0 only remains as fallback when EMS is missing or has no answer. The reason text shows what EMS expects (avoided grid import and price).'
        ],
        '1.38.0' => [
            'New: the shift of the heating and cooling request (the "-5 to +5 K" setting, i.e. ECO and comfort) now reports its limits and presets - ECO -5, normal 0, comfort +5 - so a dashboard can offer quick buttons and a slider without numbers of its own. The module version reports interface version 1.15.'
        ],
        '1.39.0' => [
            'New: weekly time schedule for ECO / normal / comfort (the shift of the heating and cooling request, zone 1 and zone 2, heating and cooling) and for the quiet mode level (optional, panel "Time schedule for ECO / comfort and quiet mode"). The heat pump cannot store such a schedule, so the module runs it: it sends a value only when the planned value changes, reads it back, repeats it up to three times, and leaves a value you changed by hand alone until the next switching time. The graphical editor is in the heat pump monitor of the NRGDashboard. Off by default; a channel takes part only when you enable it in the schedule.'
        ]
    ];
    //Der eigene Vorstellungs-Thread, live im Forum bestaetigt (14.09.2026) - vorher stand hier
    //ein Platzhalter auf die allgemeine Modul-Kategorie.
    private const FORUM_URL = 'https://community.symcon.de/t/modul-nrg-stack-heishamon-panasonic-aquarea-waermepumpe-in-ip-symcon/143993';

    //Wortlaut/Struktur verbundweit identisch ("Variante A"), SUITE.md "Einheitliche
    //Formular-Optik" Punkt 5. LICENSE_URL zeigt auf `beta`, nicht `main` - main traegt noch
    //die alte MIT-Lizenz (Store-Uebernahme steht noch aus), beta bereits PolyForm (geprueft
    //14.09.2026: git show origin/beta:LICENSE).
    private const LICENSE_URL = 'https://github.com/DG65/NRGHeishaMon/blob/beta/LICENSE';
    private const PAYPAL_URL = 'https://paypal.me/DietmarGureth';

    //Verbund-Fund 13.09.2026 (ChargerHub/WPHub): WPHub kann dieselbe Panasonic-Waermepumpe
    //parallel ueber die Comfort Cloud ansteuern. Rein informative, gegenseitige Warnung
    //(WPHub spiegelt denselben Check auf uns) - kein Blockieren, da die Geraeteidentitaet
    //nicht zuverlaessig automatisch beweisbar ist (kein gemeinsames Seriennummernfeld).
    private const WPHUB_MODULE_GUID = '{5BE429EA-3AAD-4A8B-85DE-5778CCA2E6BC}';

    //MeterHub-Modul-GUID (DG65/NRGMeterHub) - fuer die optionale Autozuordnung des externen
    //Stromzaehlers, 1:1 nach WPHubs Vorbild (meterHubHeatpumpAssignment()), von EMS bestaetigt.
    private const METERHUB_MODULE_GUID = '{BAB8E05C-9150-43B9-9F2B-E5215FA54F0A}';

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString('MQTTTopic', 'panasonic_heat_pump');
        $this->RegisterPropertyBoolean('DebugUnknownTopics', false);

        //Formular-Hinweise: "Wozu dieses Modul?" (einmalig, ganz oben) + "Neu in Version"
        //(pro Version bestaetigt) + Forum-Hinweis (einmalig)
        $this->RegisterAttributeBoolean('PurposeIntroGone', false);
        $this->RegisterAttributeString('SeenNews', '');
        $this->RegisterAttributeBoolean('ForumHintDismissed', false);

        //Auswahl der gewuenschten Datenpunkte (leer = alle aktiv)
        $this->RegisterPropertyString('VariableList', '[]');
        $this->RegisterAttributeString('SeenTopics', '[]');

        //Optionale, gruppierte Linkstruktur (nach Vorbild des Tessie-Moduls)
        $this->RegisterPropertyBoolean('CreateLinks', false);
        $this->RegisterPropertyInteger('LinksLocation', 0);

        //1-Wire-Temperatursensoren (z.B. DS18B20) am HeishaMon: dynamisches Topic pro
        //Sensoradresse, daher eigene Erkennungs-/Benennungsliste statt der festen TopicMap
        $this->RegisterPropertyString('OneWireSensors', '[]');
        $this->RegisterAttributeString('SeenOneWire', '[]');

        //Zusaetzliche, reine Schreibbefehle ohne Rueckmeldung von der Waermepumpe (SmartGrid-
        //Modus, Relais der grossen Platine) - optional, da nicht jede Anlage das unterstuetzt
        $this->RegisterPropertyBoolean('EnableExtraCommands', false);

        //Automatische Archivierung der Monitoring-Datenpunkte (fuer Zeitreihen-Kacheln wie
        //NRGDashboardWPMonitor). Attribut merkt sich einmal aktivierte Variablen, damit
        //eine spaetere Nutzer-Abwahl im Archiv-Handler nicht wieder ueberschrieben wird.
        //Store-Review-Fund (28.09.2026): Standard war 'automatisch an' - ein Nutzer sollte
        //selbst entscheiden, ob geloggt wird, nicht nachtraeglich abwaehlen muessen.
        $this->RegisterPropertyBoolean('ArchiveMonitoring', false);
        $this->RegisterAttributeString('ArchivedIdents', '[]');

        //Energiespar-Regelwerke: Upload kuratierter Vorlagen auf die HeishaMon-Platine
        //(laufen dort autonom weiter, auch wenn WLAN/IPS ausfallen)
        $this->RegisterPropertyString('DeviceIP', '');
        $this->RegisterPropertyInteger('GuardOffMinutes', 45);
        $this->RegisterPropertyInteger('GuardMinOutsideTemp', 2);
        $this->RegisterPropertyBoolean('GuardCoolingEnabled', false);
        $this->RegisterPropertyInteger('GuardMaxOutsideTempCool', 30);

        //Neustart-Watchdog: stoesst nach laengerem MQTT-Ausfall einen Platinen-Neustart
        //ueber den /reboot-Endpunkt der Firmware an (hilft, wenn MQTT haengt, aber der
        //Webserver noch antwortet - bei komplettem Netzverlust greift die Firmware-eigene
        //Reconnect-Logik). Cooldown gegen Neustart-Schleifen.
        $this->RegisterPropertyBoolean('RebootWatchdog', false);
        $this->RegisterPropertyInteger('RebootWatchdogMinutes', 10);
        $this->RegisterAttributeInteger('OfflineSince', 0);
        $this->RegisterAttributeInteger('LastRebootAttempt', 0);
        $this->RegisterTimer('RebootWatchdog', 0, 'HEISHA_CheckRebootWatchdog($_IPS[\'TARGET\']);');

        //COP / Arbeitszahl: externe Messung ueber Stromzaehler (z.B. Shelly 3EM, Phase der Waermepumpe)
        $this->RegisterPropertyInteger('PowerVariable', 0);
        //Einheit von PowerVariable (26.09.2026, Dashboard-Fund analog NRGDashboard-Forum-Fund
        //somm: eine frei gewaehlte Variable kann Kilowatt statt Watt liefern, das Modul kannte
        //nur Watt). Vorgabe "W" aendert am bisherigen Verhalten nichts.
        $this->RegisterPropertyString('PowerVariableUnit', 'W');
        $this->RegisterPropertyInteger('EnergyVariable', 0);
        $this->RegisterPropertyFloat('COPMinPower', 100);

        //Persistente Zwischenstaende der Tagesberechnung (ueberleben einen IPS-Neustart)
        $this->RegisterAttributeString('CurrentDay', '');
        $this->RegisterAttributeFloat('EnergyCounterBase', -1);
        $this->RegisterAttributeFloat('HeatWhToday', 0);
        $this->RegisterAttributeInteger('LastIntegration', 0);

        $this->RegisterTimer('COPUpdate', 0, 'HEISHA_UpdateCOPCalculation($_IPS[\'TARGET\']);');

        //Heizungs-Automatik (nur Heizbetrieb ein/aus, optional Kuehlen mit Taupunkt-Schutz)
        $this->registerHeatingAutomation();
        $this->registerSchedule();

        $this->RegisterVariableBoolean('Reachable', $this->Translate('Reachable'), $this->reachablePresentation(), 0);
    }

    /**
     * Options-Schluessel haengen von der Darstellung ab (Symcon-Doku, geprueft 18.10.2026,
     * Store-Review): Wertanzeige (VALUE_PRESENTATION, auch bool) kennt ColorActive + ColorValue,
     * Aufzaehlung (ENUMERATION) kennt NUR Color. Ein falscher Schluessel bricht das Anlegen der
     * Instanz ab (Symcon 9.1) bzw. stuerzt die Mobile-App ab.
     */
    private function reachablePresentation(): array
    {
        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'OPTIONS'      => json_encode([
                [
                    'Value'       => true,
                    'Caption'     => 'Online',
                    'IconActive'  => false,
                    'IconValue'   => '',
                    'ColorActive' => true,
                    'ColorValue'  => 65280
                ],
                [
                    'Value'       => false,
                    'Caption'     => 'Offline',
                    'IconActive'  => false,
                    'IconValue'   => '',
                    'ColorActive' => true,
                    'ColorValue'  => 16711680
                ]
            ])
        ];
    }

    /**
     * Frischt die Reachable-Darstellung bestehender Installationen auf (nur bei Abweichung).
     */
    private function maintainReachablePresentation()
    {
        $variableID = @$this->GetIDForIdent('Reachable');
        if ($variableID === false) {
            return;
        }
        $presentation = $this->reachablePresentation();
        $current = @IPS_GetVariablePresentation($variableID);
        if (is_array($current) && $this->presentationMatches($current, $presentation)) {
            return;
        }
        $this->MaintainVariable('Reachable', $this->Translate('Reachable'), VARIABLETYPE_BOOLEAN, $presentation, 0, true);
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function GetConfigurationForm()
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        //Zeilen in der Reihenfolge, die die Variablen TATSAECHLICH im Objektbaum haben (Store-
        //Review-Fund 28.09.2026, siehe reconcileOrderWithLivePositions()), nicht blind in der
        //zuletzt gespeicherten - Versionsnummer im Doku-Panel
        $this->patchFormElement($form['elements'], 'VariableList', function (&$element) {
            $order = $this->reconcileOrderWithLivePositions(
                $this->getOrderedTopics(),
                fn ($topic) => HeishaMonTopics::identFromTopic($topic)
            );
            $element['values'] = $this->buildVariableListRows($order, $this->getSelectionMap());
        });
        $this->patchFormElement($form['elements'], 'OneWireList', function (&$element) {
            $order = $this->reconcileOrderWithLivePositions(
                $this->getOrderedOneWireAddresses(),
                fn ($address) => 'OneWire_' . $address
            );
            $element['values'] = $this->buildOneWireListRows($order);
        });
        $this->patchFormElement($form['elements'], 'DocsPanel', function (&$element) {
            $element['caption'] = $this->Translate('📖  Documentation & help') . ' (' . $this->moduleVersion() . ')';
        });
        $this->patchFormElement($form['elements'], 'EnergyCheckPanel', function (&$element) {
            $items = [[
                'type'    => 'Label',
                'caption' => $this->Translate('Assessment of the received unit settings against reference values from the documented HeishaMon example rulesets and the Panasonic service manual. Display only - nothing is changed. These are service settings of the heat pump: adjust them deliberately, when in doubt with your installer.')
            ]];
            foreach ($this->buildEnergySavingFindings() as $finding) {
                $items[] = ['type' => 'Label', 'caption' => $finding[0] . ' ' . $finding[1]];
            }
            $element['items'] = $items;
        });

        //Forum-Hinweis am Ende, solange nicht bestaetigt
        $forumHint = $this->buildForumHint();
        if ($forumHint !== null) {
            $form['elements'][] = $forumHint;
        }

        //"Ueber dieses Modul" ganz unten, NACH dem Forum-Hinweis - anders als die anderen
        //Hinweis-Panels bewusst NICHT dismissible (SUITE.md "Einheitliche Formular-Optik" Punkt 5).
        $form['elements'][] = $this->buildLicenseHint();

        //"Neu in Version" ganz oben, solange die aktuelle Version nicht bestaetigt wurde
        $newsPanel = $this->buildNewsPanel();
        if ($newsPanel !== null) {
            array_unshift($form['elements'], $newsPanel);
        }

        //"Wozu dieses Modul?" noch davor - Zweck-Einfuehrung, einmalig dismissible, nicht
        //versioniert (SUITE.md "Einheitliche Formular-Optik" Punkt 0, Referenz MeterHub).
        $purposeIntro = $this->buildPurposeIntro();
        if ($purposeIntro !== null) {
            array_unshift($form['elements'], $purposeIntro);
        }

        //Warnung vor moeglicher Doppelsteuerung ganz oben (noch vor "Neu in Version") -
        //Sicherheitshinweis, kein Release-Hinweis, daher ausserhalb der ueblichen Reihenfolge.
        $crossModuleWarning = $this->buildWPHubOverlapWarning();
        if ($crossModuleWarning !== null) {
            array_unshift($form['elements'], $crossModuleWarning);
        }

        //MeterHub-Verbindung live sichtbar machen (SUITE.md "Verbund-Verbindungen im Formular
        //sichtbar machen", 21.09.2026, EMS-Fund 29.09.2026): frisch berechnet bei jedem
        //Formular-Aufbau, kein einmaliges Uebernehmen-in-Property mehr - das Modul folgt damit
        //spaeteren Aenderungen der MeterHub-Zuordnung, statt eine veraltete Kopie zu behalten.
        [$heatAutoLine, $heatAutoColor] = $this->heatAutoStatusLine();
        $this->patchFormElement($form['elements'], 'HeatAutoStatus', function (&$element) use ($heatAutoLine, $heatAutoColor) {
            $element['caption'] = $heatAutoLine;
            $element['color'] = $heatAutoColor;
        });

        $schedLine = $this->ReadPropertyBoolean('SchedEnabled')
            ? '🗓️ ' . (string) ($this->GetIDForIdent('Sched_Status') !== false ? GetValue(@$this->GetIDForIdent('Sched_Status')) : '')
            : $this->Translate('ℹ️ The time schedule is not used. Enable it above.');
        $this->patchFormElement($form['elements'], 'SchedStatus', function (&$element) use ($schedLine) {
            $element['caption'] = $schedLine;
        });
        $priceLine = $this->heatAutoPriceStatusLine();
        $batteryLine = $this->heatAutoBatteryStatusLine();
        $this->patchFormElement($form['elements'], 'HeatAutoPriceStatus', function (&$element) use ($priceLine) {
            $element['caption'] = $priceLine;
        });
        $this->patchFormElement($form['elements'], 'HeatAutoBatteryStatus', function (&$element) use ($batteryLine) {
            $element['caption'] = $batteryLine;
        });
        [$roomsLine, $humidityLine] = $this->heatAutoSourceStatusLines();
        $this->patchFormElement($form['elements'], 'HeatAutoRoomsStatus', function (&$element) use ($roomsLine) {
            $element['caption'] = $roomsLine;
        });
        $this->patchFormElement($form['elements'], 'HeatAutoHumidityStatus', function (&$element) use ($humidityLine) {
            $element['caption'] = $humidityLine;
        });

        [$statusLine, $statusColor] = $this->meterHubLinkStatusLine();
        $this->patchFormElement($form['elements'], 'MeterHubLinkStatus', function (&$element) use ($statusLine, $statusColor) {
            $element['caption'] = $statusLine;
            $element['color'] = $statusColor;
        });
        $this->patchFormElement($form['elements'], 'MeterHubOverridePanel', function (&$element) {
            $element['expanded'] = !$this->meterHubAutomaticActive();
        });

        return json_encode($form);
    }

    /**
     * Sucht ueber alle installierten MeterHub-Instanzen nach einer Funktionszuordnung
     * "Waermepumpe" (Vertrag MHUB_GetFunctions($id), Feld 'function' === 'heatpump' -
     * MeterHub fuehrt dafuer bereits ein festes Vokabular, siehe dessen eigene Doku). Rein
     * lesend, MeterHub ist optional (function_exists-Wache) und HeishaMon aendert an dessen
     * Zuordnung nichts. Liefert die erste gefundene Zuordnung mit mindestens einer Groesse
     * (Leistung oder Energie) als ['powerID','energyID','label'], oder null, wenn kein
     * MeterHub installiert ist oder keine Waermepumpe zugeordnet wurde. 1:1 nach WPHubs
     * Vorbild (meterHubHeatpumpAssignment()), von EMS bestaetigt (13.09.2026).
     */
    private function meterHubHeatpumpAssignment(): ?array
    {
        if (!function_exists('MHUB_GetFunctions') || !function_exists('IPS_GetInstanceListByModuleID')) {
            return null;
        }
        try {
            $instances = @IPS_GetInstanceListByModuleID(self::METERHUB_MODULE_GUID);
            if (!is_array($instances)) {
                return null;
            }
            foreach ($instances as $instanceID) {
                $raw = @MHUB_GetFunctions((int) $instanceID);
                $data = is_string($raw) ? json_decode($raw, true) : null;
                if (!is_array($data) || !isset($data['assignments']) || !is_array($data['assignments'])) {
                    continue;
                }
                foreach ($data['assignments'] as $a) {
                    if (!is_array($a) || ($a['function'] ?? '') !== 'heatpump') {
                        continue;
                    }
                    $powerID = (int) ($a['powerID'] ?? 0);
                    $energyID = (int) ($a['energyImportID'] ?? 0);
                    if ($powerID <= 0 && $energyID <= 0) {
                        continue;
                    }
                    return [
                        'powerID'  => $powerID,
                        'energyID' => $energyID,
                        'label'    => (string) ($a['label'] ?? $this->Translate('Heat pump'))
                    ];
                }
            }
        } catch (\Throwable $e) {
            $this->SendDebug('MeterHub-Erkennung', $e->getMessage(), 0);
        }
        return null;
    }

    /**
     * Uebernimmt eine per meterHubHeatpumpAssignment() gefundene Zuordnung in die OFFENE
     * Konfigurationsmaske - ausschliesslich auf Klick der Formular-Schaltflaeche, nie
     * automatisch im Hintergrund (gleiches Prinzip wie AckNews/DismissForumHint). Setzt
     * bewusst KEINE Properties und ruft KEIN ApplyChanges auf (Store-Review-Fund 28.09.2026):
     * IPS_ApplyChanges laedt das Formular neu, wodurch die direkt danach abgesetzten
     * UpdateFormField-Aufrufe ins Leere liefen. Stattdessen nur die offene Maske
     * vorausfuellen (wie ResetVariableList()) - der Nutzer prueft und klickt selbst
     * "Aenderungen uebernehmen".
     */
    /**
     * Wer liefert Leistung/Energiezaehler: eigene Wahl (PowerVariable/EnergyVariable) hat
     * Vorrang, sonst automatisch die MeterHub-Zuordnung "Waermepumpe" - bei jedem
     * ApplyChanges/Formular-Aufbau FRISCH aufgeloest, nie einmalig in eine Property kopiert
     * (SUITE.md "Verbund-Verbindungen im Formular sichtbar machen", 21.09.2026: eine Kopie
     * wuerde spaeteren Aenderungen der MeterHub-Zuordnung nicht mehr folgen). Ersetzt die
     * frueheren AdoptMeterHubAssignment()/MeterHubSuggestion-Buttons - es gibt nichts mehr zu
     * "uebernehmen", die Verbindung wirkt automatisch.
     */

    /**
     * Entfernter Formular-Knopf (v1.34.0) - MeterHub wird seither automatisch genutzt, ohne
     * Uebernehmen. Bleibt als wirkungsloser Deprecation-Hinweis stehen (Migrationsvergleich:
     * eine oeffentliche Funktion darf nicht ersatzlos verschwinden, auch wenn sie nie als
     * Skript-Befehl dokumentiert war - siehe CLAUDE.md "Idents sind API").
     */
    public function AdoptMeterHubAssignment()
    {
        echo $this->Translate('This action is no longer needed since v1.34.0 - a MeterHub meter assigned to function "heat pump" is now used automatically, without adopting it first.');
    }

    private function resolvedPowerID(): int
    {
        $manual = $this->ReadPropertyInteger('PowerVariable');
        if ($manual > 0) {
            return $manual;
        }
        return (int) ($this->meterHubHeatpumpAssignment()['powerID'] ?? 0);
    }

    private function resolvedEnergyID(): int
    {
        $manual = $this->ReadPropertyInteger('EnergyVariable');
        if ($manual > 0) {
            return $manual;
        }
        return (int) ($this->meterHubHeatpumpAssignment()['energyID'] ?? 0);
    }

    /**
     * true, solange kein eigener Zaehler gewaehlt ist UND MeterHub tatsaechlich eine
     * Waermepumpen-Zuordnung liefert - steuert, ob das Override-Panel eingeklappt bleibt.
     */
    private function meterHubAutomaticActive(): bool
    {
        return $this->ReadPropertyInteger('PowerVariable') <= 0
            && $this->ReadPropertyInteger('EnergyVariable') <= 0
            && $this->meterHubHeatpumpAssignment() !== null;
    }

    /**
     * Statuszeile fuer die MeterHub-Verbindung (SUITE.md "Verbund-Verbindungen im Formular
     * sichtbar machen"): [Text, Farbe]. 🔗 automatisch uebernommen (gruen, 0x2E8B3D), ✏️ eigene
     * Wahl (Standardfarbe), ℹ️ nichts verfuegbar (Standardfarbe). Werte + Quelle werden genannt,
     * nicht nur "verbunden".
     */
    private function meterHubLinkStatusLine(): array
    {
        if ($this->ReadPropertyInteger('PowerVariable') > 0 || $this->ReadPropertyInteger('EnergyVariable') > 0) {
            return [$this->Translate('✏️ Using your own meter selection below instead of MeterHub.'), -1];
        }
        if (!function_exists('MHUB_GetFunctions')) {
            return [$this->Translate('ℹ️ MeterHub not installed - no automatic meter, select your own below if you have one.'), -1];
        }
        $found = $this->meterHubHeatpumpAssignment();
        if ($found === null) {
            return [$this->Translate('ℹ️ MeterHub installed, but no meter assigned to function "heat pump" - select your own below if you have one.'), -1];
        }
        $powerNote = $found['powerID'] > 0 ? round(floatval(@GetValue($found['powerID']))) . ' W' : null;
        $energyNote = $found['energyID'] > 0 ? round(floatval(@GetValue($found['energyID'])), 2) . ' kWh' : null;
        if ($powerNote !== null && $energyNote !== null) {
            $text = sprintf($this->Translate('🔗 Power and energy meter from MeterHub "%s" taken over automatically: %s, %s.'), $found['label'], $powerNote, $energyNote);
        } elseif ($powerNote !== null) {
            $text = sprintf($this->Translate('🔗 Power meter from MeterHub "%s" taken over automatically: %s (no energy counter assigned there).'), $found['label'], $powerNote);
        } else {
            $text = sprintf($this->Translate('🔗 Energy meter from MeterHub "%s" taken over automatically: %s (no power meter assigned there).'), $found['label'], $energyNote);
        }
        return [$text, 0x2E8B3D];
    }

    /**
     * Prueft, ob eine aktive WPHub-Instanz existiert (dieselbe Panasonic-Waermepumpe koennte
     * dann parallel ueber die Comfort Cloud angesteuert werden). Rein informativ, kein
     * Blockieren - die Geraeteidentitaet laesst sich nicht automatisch/zuverlaessig pruefen
     * (kein gemeinsames Identitaetsfeld, main/Heat_Pump_Model liefert nur einen rohen
     * Hex-Bytestring). WPHub spiegelt denselben Check auf HeishaMon-Instanzen.
     */
    private function buildWPHubOverlapWarning(): ?array
    {
        $activeInstanceIDs = array_values(array_filter(
            @IPS_GetInstanceListByModuleID(self::WPHUB_MODULE_GUID) ?: [],
            function ($id) {
                return IPS_GetInstance($id)['InstanceStatus'] === 102;
            }
        ));
        if (count($activeInstanceIDs) === 0) {
            return null;
        }
        return [
            'type'    => 'Label',
            'name'    => 'WPHubOverlapWarning',
            'caption' => sprintf(
                $this->Translate('⚠️ An active WPHub instance also exists (#%s). If both control the same physical heat pump - one locally via MQTT (HeishaMon), one via the Panasonic Comfort Cloud (WPHub) - do not use both to send commands at the same time, or their settings can contradict each other.'),
                implode(', #', $activeInstanceIDs)
            )
        ];
    }

    /**
     * Sucht rekursiv (auch innerhalb von Panels/RowLayouts) das Formularelement mit dem
     * uebergebenen Namen und wendet den Callback per Referenz darauf an.
     */
    private function patchFormElement(array &$elements, string $name, callable $callback)
    {
        foreach ($elements as &$element) {
            if (($element['name'] ?? '') === $name) {
                $callback($element);
            }
            if (isset($element['items']) && is_array($element['items'])) {
                $this->patchFormElement($element['items'], $name, $callback);
            }
        }
        unset($element);
    }

    private function moduleVersion(): string
    {
        return 'v' . $this->libraryVersion();
    }

    /**
     * Rohe Versionsnummer aus library.json, ungecastet (z.B. "1.32.0" oder waehrend einer
     * Beta-Auslieferung "1.32.0-beta.1"). Direkter Dateizugriff statt IPS_GetLibrary() - genau
     * das, was moduleVersion() schon immer so gemacht hat, hier fuer AckNews() wiederverwendet.
     */
    private function libraryVersion(): string
    {
        $library = json_decode(file_get_contents(__DIR__ . '/../library.json'), true);
        return (string) ($library['version'] ?? '');
    }

    /**
     * Reine Versionszahl ohne Beta-/Build-Zusatz ("1.32.0-beta.1" -> "1.32.0") - Vergleichsbasis
     * fuer NEWS_VERSIONS (SUITE.md "Einheitliche Formular-Optik" Punkt 1).
     */
    private function baseVersion(string $version): string
    {
        return preg_replace('/-.*$/', '', $version) ?? $version;
    }

    /**
     * "Wozu dieses Modul?"-Panel: aufgeklappt, einmalig dismissible (nicht versioniert wie
     * das News-Panel - der Zweck eines Moduls aendert sich nicht mit jedem Release). SUITE.md
     * "Einheitliche Formular-Optik" Punkt 0, Referenzimplementierung MeterHub.
     */
    private function buildPurposeIntro(): ?array
    {
        if ($this->ReadAttributeBoolean('PurposeIntroGone')) {
            return null;
        }
        return [
            'type'     => 'ExpansionPanel',
            'name'     => 'PurposeIntroPanel',
            'expanded' => true,
            'caption'  => $this->Translate('👋  What is this module for?'),
            'items'    => [
                [
                    'type'    => 'Label',
                    'caption' => $this->Translate('This module connects a Panasonic heat pump to IP-Symcon locally via MQTT through the HeishaMon board - all readings, operating states and control options become available as Symcon variables automatically, without any cloud dependency.')
                ],
                [
                    'type'    => 'Label',
                    'caption' => $this->Translate('That lets you evaluate and automate the heat pump like any other Symcon device, and connect it to the rest of your home energy system through the NRG-Stack (e.g. energy management, system schematic). No HeishaMon board? WPHub offers a cloud alternative via the Panasonic Comfort Cloud.')
                ],
                [
                    'type'    => 'Button',
                    'caption' => $this->Translate('Understood - do not show again'),
                    'onClick' => 'HEISHA_AckPurposeIntro($id);'
                ]
            ]
        ];
    }

    public function AckPurposeIntro()
    {
        $this->WriteAttributeBoolean('PurposeIntroGone', true);
        $this->UpdateFormField('PurposeIntroPanel', 'visible', false);
    }

    /**
     * "Neu in Version X.Y"-Panel: aufgeklappt, bis der Nutzer "Verstanden" klickt.
     * Danach (auch nach einem IPS-Neustart) bleibt es fuer diese Version verborgen.
     */
    private function buildNewsPanel(): ?array
    {
        $seen = (string) $this->ReadAttributeString('SeenNews');
        $pending = [];
        foreach (self::NEWS_VERSIONS as $version => $lines) {
            if ($seen === '' || version_compare($version, $seen, '>')) {
                $pending[$version] = $lines;
            }
        }
        if (count($pending) === 0) {
            return null;
        }
        uksort($pending, 'version_compare');
        $items = [];
        $multipleVersions = count($pending) > 1;
        foreach ($pending as $version => $lines) {
            if ($multipleVersions) {
                $items[] = ['type' => 'Label', 'caption' => $this->Translate('Version') . ' ' . $version . ':'];
            }
            foreach ($lines as $line) {
                $items[] = ['type' => 'Label', 'caption' => $this->Translate($line)];
            }
        }
        $items[] = [
            'type'    => 'Button',
            'caption' => $this->Translate('Understood - do not show again'),
            'onClick' => 'HEISHA_AckNews($id);'
        ];
        return [
            'type'     => 'ExpansionPanel',
            'name'     => 'NewsPanel',
            'caption'  => $this->Translate('🆕 New up to version') . ' ' . array_key_last($pending),
            'expanded' => true,
            'items'    => $items
        ];
    }

    /**
     * Merkt sich nicht mehr den zuletzt gezeigten NEWS_VERSIONS-Schluessel, sondern die
     * tatsaechlich installierte Bibliotheksversion - ein spaeteres Update mit neuem
     * NEWS_VERSIONS-Eintrag zeigt den Banner damit sicher wieder, auch wenn zwischen den
     * beiden Versionen kein Eintrag lag (SUITE.md "Einheitliche Formular-Optik" Punkt 1).
     */
    public function AckNews()
    {
        $version = $this->baseVersion($this->libraryVersion());
        $this->WriteAttributeString('SeenNews', $version !== '' ? $version : (string) array_key_last(self::NEWS_VERSIONS));
        $this->UpdateFormField('NewsPanel', 'visible', false);
    }

    /**
     * Einmaliger Hinweis auf den Symcon-Forum-Thread, solange nicht ausgeblendet. Dismissible
     * ExpansionPanel (SUITE.md "Einheitliche Formular-Optik" Punkt 4, Referenz MeterHub) -
     * abgeloest die alte RowLayout-Fassung mit dem generischen Kategorie-Link.
     */
    private function buildForumHint(): ?array
    {
        if ($this->ReadAttributeBoolean('ForumHintDismissed')) {
            return null;
        }
        return [
            'type'     => 'ExpansionPanel',
            'name'     => 'ForumHintPanel',
            'expanded' => true,
            'caption'  => $this->Translate('💬  Feedback in the Symcon forum'),
            'items'    => [
                ['type' => 'Label', 'caption' => $this->Translate('Feedback and suggestions are welcome in the module thread on the Symcon forum.')],
                ['type' => 'Button', 'caption' => $this->Translate('Go to the forum thread'), 'onClick' => "echo '" . self::FORUM_URL . "';", 'link' => true],
                ['type' => 'Button', 'caption' => $this->Translate('Understood - do not show again'), 'onClick' => 'HEISHA_DismissForumHint($id);']
            ]
        ];
    }

    public function DismissForumHint()
    {
        $this->WriteAttributeBoolean('ForumHintDismissed', true);
        $this->UpdateFormField('ForumHintPanel', 'visible', false);
    }

    /**
     * "Ueber dieses Modul": Lizenz + Spenden, ganz unten, NACH dem Forum-Hinweis. Bewusst NICHT
     * dismissible (kein Attribut, kein AckX()) - eine Lizenz ist kein einmaliger Hinweis.
     * Wortlaut verbundweit identisch ("Variante A"), SUITE.md "Einheitliche Formular-Optik"
     * Punkt 5, Referenz MeterHub. LICENSE_URL zeigt auf `beta`, NICHT `main` - der main-Stand
     * ist noch vor dem Lizenzwechsel (v1.2.1), main traegt dort noch MIT (Store-Uebernahme
     * durch Dietmar noch offen, siehe CHANGELOG).
     */
    private function buildLicenseHint(): array
    {
        return [
            'type'     => 'ExpansionPanel',
            'expanded' => false,
            'caption'  => $this->Translate('🧡  About this module'),
            'items'    => [
                ['type' => 'Label', 'caption' => $this->Translate('Born out of genuine enthusiasm for my own system - and quite a few late-night typing sessions. Still: software hobby or not, this is intellectual property and real work went into it.')],
                ['type' => 'Label', 'caption' => $this->Translate('License: PolyForm Noncommercial 1.0.0 - free for private and non-commercial use, commercial use requires a separate license from the rights holder.')],
                ['type' => 'Button', 'caption' => $this->Translate('View license text'), 'onClick' => "echo '" . self::LICENSE_URL . "';", 'link' => true],
                ['type' => 'Label', 'caption' => $this->Translate('Commercial use or license questions? Just get in touch: dietmar@gureth.eu')],
                ['type' => 'Label', 'caption' => $this->Translate('Do you like the module and still want to leave something behind? A small donation is very welcome - entirely optional, no strings attached.')],
                ['type' => 'Button', 'caption' => $this->Translate('☕  Donate via PayPal'), 'onClick' => "echo '" . self::PAYPAL_URL . "';", 'link' => true]
            ]
        ];
    }

    /**
     * Baut die Zeilen der Datenpunkt-Liste in der uebergebenen Reihenfolge.
     * $selection liefert den Aktiv-Zustand; nicht gelistete Topics gelten als aktiv.
     */
    private function buildVariableListRows(array $orderedTopics, array $selection): array
    {
        $seenTopics = json_decode((string) $this->ReadAttributeString('SeenTopics'), true) ?: [];
        $topics = HeishaMonTopics::topics();
        $rows = [];
        foreach ($orderedTopics as $topic) {
            $rows[] = [
                'Selected' => $selection[$topic] ?? true,
                'Caption'  => $this->Translate($topics[$topic]['cap']),
                'Group'    => $this->Translate(HeishaMonTopics::groupForTopic($topic)),
                'Topic'    => $topic,
                'Received' => in_array($topic, $seenTopics) ? $this->Translate('Yes') : ''
            ];
        }
        return $rows;
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $baseTopic = $this->ReadPropertyString('MQTTTopic');
        if ($baseTopic == '') {
            //Nichts empfangen, solange kein Topic konfiguriert ist
            $this->SetReceiveDataFilter('(?!)');
            $this->SetStatus(IS_INACTIVE);
            return;
        }

        //Slashes muessen escaped werden, da der Topic im JSON-Datenpaket escaped ankommt
        $filterTopic = str_replace('/', '\\\\/', $baseTopic);
        $this->SetReceiveDataFilter('.*' . $filterTopic . '.*');
        $this->SetStatus(IS_ACTIVE);

        //Elektrische Gesamtleistung: gemessen, wenn ein Zaehler konfiguriert ist, sonst HeishaMon-Schaetzung.
        //Wird immer gefuehrt und ist der Anknuepfungspunkt fuer andere Module (siehe GetFunctions).
        $this->maintainCalculationVariable('Power_Total', $this->Translate('Electrical power (total)'), [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX'       => ' W',
            'DIGITS'       => 0
        ], 199, true);
        //Thermische Gesamtleistung (Heizen+Kuehlen+WW-Erzeugung) - fuer Monitoring-Seiten,
        //die elektrische und thermische Leistung uebereinanderlegen (siehe GetFunctions)
        $this->maintainCalculationVariable('Heat_Output_Total', $this->Translate('Thermal output (total)'), [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX'       => ' W',
            'DIGITS'       => 0
        ], 198, true);
        $this->updateHeatOutputTotal();
        //Die Messwert-Variablen haengen von der MeterHub-Zuordnung ab. Beim Symcon-Start ist MeterHub
        //evtl. noch nicht instanziiert und wuerde nicht gefunden - dann wuerden vier Variablen
        //geloescht (Store-Review 18.10.2026). Deshalb erst bei KR_READY; sonst uebernimmt das
        //MessageSink bei IPS_KERNELSTARTED (siehe unten).
        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->maintainMeasuredVariables();
        }

        //Neustart-Watchdog nur mit aktivierter Option und eingetragener Platinen-IP
        $watchdogActive = $this->ReadPropertyBoolean('RebootWatchdog') && trim((string) $this->ReadPropertyString('DeviceIP')) != '';
        $this->SetTimerInterval('RebootWatchdog', $watchdogActive ? 60000 : 0);

        $this->maintainHeatingAutomation();
        $this->maintainSchedule();

        $this->SendDebug('VariableList', (string) $this->ReadPropertyString('VariableList'), 0);

        //Praesentationen bestehender Variablen auffrischen (z.B. neue Enum-Optionen nach Modul-Update);
        //geschrieben wird nur bei tatsaechlicher Aenderung, sonst loest jedes Uebernehmen einen
        //Update-Sturm fuer alle Variablen aus, der die Konsole zum Absturz bringen kann
        $topics = HeishaMonTopics::topics();
        foreach ($topics as $topic => $definition) {
            $this->maintainTopicVariable(HeishaMonTopics::identFromTopic($topic), $topic, $definition, true);
        }

        //Abgewaehlte Datenpunkte ausblenden statt loeschen (Objekt-ID und Archivdaten bleiben
        //erhalten) und Positionen gemaess der Listen-Reihenfolge nachfuehren
        $selection = $this->getSelectionMap();
        $positions = $this->getPositionMap();
        foreach ($topics as $topic => $definition) {
            $variableID = @$this->GetIDForIdent(HeishaMonTopics::identFromTopic($topic));
            if ($variableID === false) {
                continue;
            }
            $object = IPS_GetObject($variableID);
            $hidden = !($selection[$topic] ?? true);
            if ($object['ObjectIsHidden'] != $hidden) {
                IPS_SetHidden($variableID, $hidden);
            }
            if ($object['ObjectPosition'] != $positions[$topic]) {
                IPS_SetPosition($variableID, $positions[$topic]);
            }
        }

        //1-Wire-Sensoren: Namen/Darstellung bestehender Variablen auffrischen, abgewaehlte
        //ausblenden statt loeschen, Positionen gemaess der Listen-Reihenfolge nachfuehren
        $oneWireSelection = [];
        foreach ($this->getOneWireConfigMap() as $address => $row) {
            $oneWireSelection[$address] = boolval($row['Selected'] ?? true);
        }
        $oneWirePositions = $this->getOneWirePositionMap();
        foreach ($this->getOrderedOneWireAddresses() as $address) {
            $this->maintainOneWireVariable($address, true);
            $variableID = @$this->GetIDForIdent('OneWire_' . $address);
            if ($variableID === false) {
                continue;
            }
            $object = IPS_GetObject($variableID);
            $hidden = !($oneWireSelection[$address] ?? true);
            if ($object['ObjectIsHidden'] != $hidden) {
                IPS_SetHidden($variableID, $hidden);
            }
            if ($object['ObjectPosition'] != $oneWirePositions[$address]) {
                IPS_SetPosition($variableID, $oneWirePositions[$address]);
            }
        }

        //Reachable-Darstellung bestehender Installationen auffrischen (Migration auf die laut
        //Symcon-Doku gueltigen Schluessel, siehe reachablePresentation(); die Variable entsteht
        //in Create(), dort greift die Korrektur fuer Bestandsanlagen nie)
        $this->maintainReachablePresentation();

        //Vereinheitlichte Betriebsart (Verbund-Enum, siehe SUITE.md) aus der Panasonic-
        //Betriebsart ableiten - immer gefuehrt, analog Power_Total
        $this->maintainOperatingModeNormVariable();
        $rawModeID = @$this->GetIDForIdent('Operating_Mode_State');
        if ($rawModeID !== false) {
            $this->SetValue('Operating_Mode_Norm', $this->normalizeOperatingMode(intval(GetValue($rawModeID))));
        }

        //Archivierung der Monitoring-Datenpunkte nachziehen (nur einmal je Variable)
        $this->maintainMonitoringArchive();

        //Zusaetzliche Schreibbefehle: nur anlegen, wenn ausdruecklich aktiviert
        $extraEnabled = $this->ReadPropertyBoolean('EnableExtraCommands');
        $this->maintainSmartGridModeVariable($extraEnabled);
        $this->maintainRelayVariable('GpioRelay1', $this->Translate('Relay 1 (large board)'), 260, $extraEnabled);
        $this->maintainRelayVariable('GpioRelay2', $this->Translate('Relay 2 (large board)'), 261, $extraEnabled);

        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->registerExternalMessages();
            $this->maintainLinkTree();
        } else {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
        }
    }

    /**
     * Variablen der COP-Berechnung anlegen bzw. entfernen, je nach Konfiguration (eigene Wahl ODER
     * automatische MeterHub-Zuordnung - beide zaehlen fuer "ein Zaehler ist da"). Nur bei
     * Kernel-Runlevel KR_READY aufrufen: vorher kann MeterHub noch nicht instanziiert sein.
     */
    private function maintainMeasuredVariables()
    {
        $powerID = $this->resolvedPowerID();
        $energyID = $this->resolvedEnergyID();
        $copPresentation = [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'DIGITS'       => 2
        ];
        $kwhPresentation = [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX'       => ' kWh',
            'DIGITS'       => 2
        ];
        $this->maintainCalculationVariable('COP_Measured', $this->Translate('COP (measured)'), $copPresentation, 201, $powerID > 0);
        $this->maintainCalculationVariable('Heat_Energy_Today', $this->Translate('Heat energy today'), $kwhPresentation, 202, $energyID > 0);
        $this->maintainCalculationVariable('Power_Energy_Today', $this->Translate('Energy consumption today'), $kwhPresentation, 203, $energyID > 0);
        $this->maintainCalculationVariable('COP_Today', $this->Translate('Performance factor today'), $copPresentation, 204, $energyID > 0);
        $this->updateTotalPower();

        $this->SetTimerInterval('COPUpdate', $energyID > 0 ? 60000 : 0);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        switch ($Message) {
            case IPS_KERNELSTARTED:
                //jetzt ist MeterHub sicher instanziiert: Messwert-Variablen nachziehen, dann abonnieren
                $this->maintainMeasuredVariables();
                $this->registerExternalMessages();
                $this->maintainLinkTree();
                break;
            case VM_UPDATE:
                if ($SenderID == $this->resolvedPowerID()) {
                    $scale = $this->ReadPropertyInteger('PowerVariable') > 0 ? $this->powerVariableScale() : 1.0;
                    $this->updateMeasuredCOP(floatval($Data[0]) * $scale);
                    $this->updateTotalPower();
                } elseif ($SenderID == $this->resolvedEnergyID()) {
                    $this->updateDailyValues();
                }
                break;
        }
    }

    /**
     * Timer-Funktion: integriert die thermische Leistung zur Tageswaermemenge
     * und aktualisiert die Tages-Arbeitszahl.
     */
    public function UpdateCOPCalculation()
    {
        $now = time();
        $today = date('Y-m-d', $now);

        //Tageswechsel: Zaehlerbasis neu setzen, Waermemenge zuruecksetzen
        if ($this->ReadAttributeString('CurrentDay') != $today) {
            $this->WriteAttributeString('CurrentDay', $today);
            $this->WriteAttributeFloat('HeatWhToday', 0);
            $this->WriteAttributeInteger('LastIntegration', $now);
            $energyID = $this->resolvedEnergyID();
            if ($energyID > 0 && IPS_VariableExists($energyID)) {
                $this->WriteAttributeFloat('EnergyCounterBase', floatval(GetValue($energyID)));
            }
        }

        //Thermische Leistung ueber die Zeit integrieren (Trapez waere uebertrieben, Rechteck reicht)
        $last = $this->ReadAttributeInteger('LastIntegration');
        $dt = $now - $last;
        if ($last > 0 && $dt > 0 && $dt <= 600) {
            $heatWh = $this->ReadAttributeFloat('HeatWhToday') + $this->getThermalPower() * $dt / 3600;
            $this->WriteAttributeFloat('HeatWhToday', $heatWh);
        }
        $this->WriteAttributeInteger('LastIntegration', $now);

        $this->updateDailyValues();
    }

    /**
     * Auswahl aus der Konfigurationsliste: Topic => aktiv. Nicht gelistete Topics gelten als aktiv.
     */
    private function getSelectionMap(): array
    {
        $map = [];
        $rows = json_decode((string) $this->ReadPropertyString('VariableList'), true);
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (isset($row['Topic'])) {
                    $map[$row['Topic']] = boolval($row['Selected'] ?? true);
                }
            }
        }
        return $map;
    }

    private function isTopicSelected(string $topic): bool
    {
        $map = $this->getSelectionMap();
        return $map[$topic] ?? true;
    }

    /**
     * Alle Topics in Anzeige-Reihenfolge: zuerst die gespeicherte (per Drag & Drop
     * sortierte) Liste, danach noch unbekannte Topics in TopicMap-Reihenfolge.
     */
    /**
     * Gleicht eine gespeicherte Reihenfolge mit der tatsaechlichen Position der Variablen im
     * Objektbaum ab (Store-Review-Fund 28.09.2026): ApplyChanges() persistiert beim naechsten
     * Uebernehmen JEDES Listenfeld der offenen Maske, unabhaengig davon, ob der Nutzer gerade
     * DIESES Feld angefasst hat - zeigte die Maske also weiterhin nur die zuletzt gespeicherte
     * Reihenfolge, wurde eine Umsortierung von aussen (z.B. Ziehen im Objektbaum der Konsole)
     * beim naechsten Uebernehmen automatisch wieder ueberschrieben. Jetzt zeigt die offene
     * Maske die WIRKLICHE Reihenfolge; nur eine tatsaechliche Umsortierung IN der offenen
     * Maske selbst fuehrt danach noch zu einer aktiven IPS_SetPosition() in ApplyChanges().
     * Eintraege ohne existierende Variable (noch nie empfangen) haben keine echte Position und
     * behalten daher ihren bisherigen relativen Platz, gebuendelt ans Ende.
     */
    private function reconcileOrderWithLivePositions(array $items, callable $identFor): array
    {
        $sortKeys = [];
        foreach ($items as $item) {
            $variableID = @$this->GetIDForIdent($identFor($item));
            $sortKeys[$item] = $variableID === false ? [1, 0] : [0, IPS_GetObject($variableID)['ObjectPosition']];
        }
        $ordered = $items;
        usort($ordered, fn ($a, $b) => $sortKeys[$a] <=> $sortKeys[$b]);
        return $ordered;
    }

    private function getOrderedTopics(): array
    {
        $all = HeishaMonTopics::defaultOrder();
        $saved = [];
        $rows = json_decode((string) $this->ReadPropertyString('VariableList'), true);
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (isset($row['Topic']) && in_array($row['Topic'], $all, true)) {
                    $saved[] = $row['Topic'];
                }
            }
        }
        foreach ($all as $topic) {
            if (!in_array($topic, $saved, true)) {
                $saved[] = $topic;
            }
        }
        return $saved;
    }

    /**
     * Topic => Variablen-Position, abgeleitet aus der Listen-Reihenfolge.
     */
    private function getPositionMap(): array
    {
        $map = [];
        $position = 10;
        foreach ($this->getOrderedTopics() as $topic) {
            $map[$topic] = $position;
            $position += 10;
        }
        return $map;
    }

    /**
     * Setzt Reihenfolge und Auswahl der Datenpunkte in der offenen Konfiguration
     * auf den Standard zurueck (Standard-Reihenfolge, alle aktiv). Persistiert wird
     * erst, wenn der Nutzer selbst "Aenderungen uebernehmen" klickt.
     */
    public function ResetVariableList()
    {
        $rows = $this->buildVariableListRows(HeishaMonTopics::defaultOrder(), []);
        $this->UpdateFormField('VariableList', 'values', json_encode($rows));
        echo $this->Translate('Order and selection reset in the open form - click "Apply changes" to save.');
    }

    /**
     * Frueher verwendete Standard-Uebersetzungen => Caption-Key.
     * Wird von UpdateVariableNames genutzt, um nur unveraenderte Standardnamen umzubenennen.
     */
    private const RENAMED_CAPTIONS = [
        'Rohrtemperatur außen'                        => 'Outside pipe temperature',
        'Rohrtemperatur innen'                        => 'Inside pipe temperature',
        'Pumpenleistung'                              => 'Pump duty',
        'Maximale Pumpenleistung'                     => 'Maximum pump duty',
        'Heizen Delta'                                => 'Heat delta',
        'Kühlen Delta'                                => 'Cool delta',
        'Warmwasser Heizdelta'                        => 'DHW heating delta',
        'Zone 1 Heizanforderung'                      => 'Zone 1 heat request temperature',
        'Zone 1 Kühlanforderung'                      => 'Zone 1 cool request temperature',
        'Zone 2 Heizanforderung'                      => 'Zone 2 heat request temperature',
        'Zone 2 Kühlanforderung'                      => 'Zone 2 cool request temperature',
        'Raum Urlaubsverschiebung'                    => 'Room holiday shift temperature',
        'Warmwasser Urlaubsverschiebung'              => 'DHW holiday shift temperature',
        'Solar Ein-Delta'                             => 'Solar on delta',
        'Solar Aus-Delta'                             => 'Solar off delta',
        'Einschaltzyklen'                             => 'Heatpump starts',
        'Heizstab Warmwasser erlaubt'                 => 'DHW heater allowed',
        'Heizstab Heizung erlaubt'                    => 'Room heater allowed',
        'Bivalent erweitert: Heizungs-Steuerung'      => 'Bivalent advanced heat control',
        'Bivalent erweitert: Warmwasser-Steuerung'    => 'Bivalent advanced DHW control',
        'Bivalent erweitert: Warmwasser-Verzögerung'  => 'Bivalent advanced DHW delay',
        'Bivalent erweitert: Startverzögerung'        => 'Bivalent advanced start delay',
        'Bivalent erweitert: Starttemperatur'         => 'Bivalent advanced start temperature',
        'Bivalent erweitert: Stoppverzögerung'        => 'Bivalent advanced stop delay',
        'Bivalent erweitert: Stopptemperatur'         => 'Bivalent advanced stop temperature',
        //Cap-Umbenennung v1.13.2 ("valve PID" -> "mixing valve position"): sowohl der alte
        //deutsche als auch der alte englische Standardname muessen erkannt werden
        'Zone 1 Ventil PID'                           => 'Zone 1 mixing valve position',
        'Zone 2 Ventil PID'                           => 'Zone 2 mixing valve position',
        'Zone 1 valve PID'                            => 'Zone 1 mixing valve position',
        'Zone 2 valve PID'                            => 'Zone 2 mixing valve position'
    ];

    /**
     * Benennt Variablen mit altem Standardnamen auf die aktuelle Uebersetzung um.
     * Selbst vergebene Namen werden nicht angefasst.
     */
    public function UpdateVariableNames()
    {
        $count = 0;
        foreach (HeishaMonTopics::topics() as $topic => $definition) {
            $variableID = @$this->GetIDForIdent(HeishaMonTopics::identFromTopic($topic));
            if ($variableID === false) {
                continue;
            }
            $target = $this->Translate($definition['cap']);
            $current = IPS_GetName($variableID);
            if ($current == $target) {
                continue;
            }
            //nur Standardnamen umbenennen: englischer Caption-Key oder bekannte alte Uebersetzung
            $isOldDefault = ($current == $definition['cap'])
                || (isset(self::RENAMED_CAPTIONS[$current]) && self::RENAMED_CAPTIONS[$current] == $definition['cap']);
            if ($isOldDefault) {
                IPS_SetName($variableID, $target);
                $count++;
            }
        }
        //Linknamen in der Linkstruktur nachziehen
        $this->maintainLinkTree();
        echo sprintf($this->Translate('%d variable names updated'), $count);
    }

    /**
     * Merkt sich, welche Topics die Anlage tatsaechlich sendet (Spalte "Empfangen" in der Konfiguration).
     */
    private function rememberSeenTopic(string $topic)
    {
        $seen = json_decode((string) $this->ReadAttributeString('SeenTopics'), true) ?: [];
        if (!in_array($topic, $seen)) {
            $seen[] = $topic;
            $this->WriteAttributeString('SeenTopics', json_encode($seen));
        }
    }

    /**
     * Pflegt die optionale Linkstruktur: <Zielort>/<Instanzname>/<Gruppe>/<Link auf Variable>.
     * Nur aktivierte, vorhandene Datenpunkte werden verlinkt; Links abgewaehlter
     * Datenpunkte werden entfernt, leere Gruppen geloescht.
     */
    private function maintainLinkTree()
    {
        if (!$this->ReadPropertyBoolean('CreateLinks')) {
            return;
        }
        $parentID = $this->ReadPropertyInteger('LinksLocation');
        if ($parentID <= 0 || !IPS_ObjectExists($parentID)) {
            return;
        }

        //Wurzelkategorie pro Instanz
        $rootIdent = 'HEISHA_LINKROOT_' . $this->InstanceID;
        $rootID = @IPS_GetObjectIDByIdent($rootIdent, $parentID);
        if ($rootID === false) {
            $rootID = IPS_CreateCategory();
            IPS_SetParent($rootID, $parentID);
            IPS_SetIdent($rootID, $rootIdent);
            IPS_SetName($rootID, IPS_GetName($this->InstanceID));
        }

        //Gewuenschte Links je Gruppe zusammenstellen, in der Reihenfolge der Datenpunkt-Liste
        $selection = $this->getSelectionMap();
        $desired = [];
        foreach ($this->getOrderedTopics() as $topic) {
            if (!($selection[$topic] ?? true)) {
                continue;
            }
            $variableID = @$this->GetIDForIdent(HeishaMonTopics::identFromTopic($topic));
            if ($variableID === false) {
                continue;
            }
            $desired[HeishaMonTopics::groupForTopic($topic)]['HEISHA_LNK_' . HeishaMonTopics::identFromTopic($topic)] = $variableID;
        }
        //Modul-eigene Variablen ausserhalb der TopicMap
        $extraIdents = [
            'Reachable'             => 'Operation',
            'Operating_Mode_Norm'   => 'Operation',
            'Heat_Output_Total'     => 'Power & COP',
            'Stats_WifiQuality'     => 'Board diagnostics',
            'Stats_Uptime'          => 'Board diagnostics',
            'Stats_MqttReconnects'  => 'Board diagnostics',
            'Stats_ReadQuality'     => 'Board diagnostics',
            'Stats_RulesActive'     => 'Board diagnostics',
            'Stats_FirmwareVersion' => 'Board diagnostics',
            'Power_Total'        => 'Power & COP',
            'COP_Internal'       => 'Power & COP',
            'COP_Measured'       => 'Power & COP',
            'Heat_Energy_Today'  => 'Power & COP',
            'Power_Energy_Today' => 'Power & COP',
            'COP_Today'          => 'Power & COP'
        ];
        foreach ($extraIdents as $ident => $group) {
            $variableID = @$this->GetIDForIdent($ident);
            if ($variableID !== false) {
                $desired[$group]['HEISHA_LNK_' . $ident] = $variableID;
            }
        }
        //1-Wire-Sensoren, ebenfalls nur aktivierte
        foreach ($this->getOrderedOneWireAddresses() as $address) {
            if (!$this->isOneWireSelected($address)) {
                continue;
            }
            $variableID = @$this->GetIDForIdent('OneWire_' . $address);
            if ($variableID !== false) {
                $desired['1-Wire sensors']['HEISHA_LNK_OneWire_' . $address] = $variableID;
            }
        }

        foreach (HeishaMonTopics::groupOrder() as $index => $group) {
            $groupIdent = 'HEISHA_GRP_' . preg_replace('/[^A-Za-z0-9]/', '', $group);
            $categoryID = @IPS_GetObjectIDByIdent($groupIdent, $rootID);
            $links = $desired[$group] ?? [];

            if ($categoryID === false) {
                if (count($links) == 0) {
                    continue;
                }
                $categoryID = IPS_CreateCategory();
                IPS_SetParent($categoryID, $rootID);
                IPS_SetIdent($categoryID, $groupIdent);
                IPS_SetName($categoryID, $this->Translate($group));
                IPS_SetPosition($categoryID, $index);
            }

            //verwaltete Links entfernen, deren Datenpunkt abgewaehlt oder verschwunden ist
            foreach (IPS_GetChildrenIDs($categoryID) as $childID) {
                $child = IPS_GetObject($childID);
                if ($child['ObjectType'] == OBJECTTYPE_LINK && strpos($child['ObjectIdent'], 'HEISHA_LNK_') === 0 && !isset($links[$child['ObjectIdent']])) {
                    IPS_DeleteLink($childID);
                }
            }

            $position = 0;
            foreach ($links as $linkIdent => $variableID) {
                $linkID = @IPS_GetObjectIDByIdent($linkIdent, $categoryID);
                if ($linkID === false) {
                    $linkID = IPS_CreateLink();
                    IPS_SetParent($linkID, $categoryID);
                    IPS_SetIdent($linkID, $linkIdent);
                }
                //nur bei Abweichung schreiben, um keine unnoetigen Objekt-Updates auszuloesen
                if (IPS_GetLink($linkID)['TargetID'] != $variableID) {
                    IPS_SetLinkTargetID($linkID, $variableID);
                }
                if (IPS_GetName($linkID) != IPS_GetName($variableID)) {
                    IPS_SetName($linkID, IPS_GetName($variableID));
                }
                if (IPS_GetObject($linkID)['ObjectPosition'] != $position) {
                    IPS_SetPosition($linkID, $position);
                }
                $position++;
            }

            //leere Gruppe aufraeumen
            if (count($links) == 0 && count(IPS_GetChildrenIDs($categoryID)) == 0) {
                IPS_DeleteCategory($categoryID);
            }
        }
    }

    private function registerExternalMessages()
    {
        foreach ($this->GetMessageList() as $senderID => $messages) {
            if (in_array(VM_UPDATE, $messages)) {
                $this->UnregisterMessage($senderID, VM_UPDATE);
            }
        }
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }
        foreach ([$this->resolvedPowerID(), $this->resolvedEnergyID()] as $variableID) {
            if ($variableID > 0 && IPS_VariableExists($variableID)) {
                $this->RegisterMessage($variableID, VM_UPDATE);
                $this->RegisterReference($variableID);
            }
        }
    }

    /**
     * Summe der thermischen Leistung (Heizen + Kuehlen + Warmwasser) in Watt.
     */
    private function getThermalPower(): float
    {
        return $this->sumPowerIdents(
            ['Heat_Power_Production_Extra', 'Cool_Power_Production_Extra', 'DHW_Power_Production_Extra'],
            ['Heat_Power_Production', 'Cool_Power_Production', 'DHW_Power_Production']
        );
    }

    /**
     * Summe der Leistungswerte. Meldet die Anlage den Extra-Datenblock (K/L-Serie und neuer),
     * zaehlen ausschliesslich dessen Werte: die alten main/*_Power_*-Topics liefern dort laut
     * Firmware-Doku ungueltige Werte (z.B. -200). Sonst die alten Topics wie bisher.
     */
    private function sumPowerIdents(array $extraIdents, array $standardIdents): float
    {
        $idents = $standardIdents;
        foreach ($extraIdents as $ident) {
            if (@$this->GetIDForIdent($ident) !== false) {
                $idents = $extraIdents;
                break;
            }
        }
        $sum = 0.0;
        foreach ($idents as $ident) {
            $variableID = @$this->GetIDForIdent($ident);
            if ($variableID !== false) {
                $sum += floatval(GetValue($variableID));
            }
        }
        return $sum;
    }

    /**
     * Erzeugt das parametrisierte Taktschutz-Regelwerk (Vorlage: Compressor-Short-Cycle-Guard
     * aus dem HeishaMon-Repo, Examples/Rules/). Nach einem Verdichterstopp wird der
     * Wiederanlauf fuer die konfigurierte Zeit unterdrueckt (Heizanforderung -5 als Sentinel),
     * nur bei mildem Wetter und nicht waehrend einer Abtauung; faellt die Aussentemperatur
     * unter die Schwelle, endet die Sperre sofort. Ein einziger Restore-Pfad (timer=1).
     *
     * Optional ein zweiter, unabhaengiger Zweig fuer den Kuehlbetrieb (timer=2, eigener
     * Sentinel auf @Z1_Cool_Request_Temp): die Heizanforderung wird beim Kuehlen von der
     * Anlage nicht angesteuert, daher greift der Heiz-Zweig dort nicht - siehe Dietmars
     * Nachfrage vom 21.08.2026 (Taktung trat waehrend Kuehlbetrieb auf, Heiz-Guard wirkungslos).
     * Bei Kaelte hat der Heiz-Zweig Vorrang vor Wiederanlauf-Sperre (Schwelle als Untergrenze);
     * bei Kuehlung ist es spiegelbildlich Hitze, die Vorrang hat (Schwelle als Obergrenze).
     */
    private function buildShortCycleGuardRules(int $offMinutes, int $minOutsideTemp, bool $coolingEnabled, int $maxOutsideTempCool): string
    {
        $seconds = max(60, $offMinutes * 60);
        $rules = "on System#Boot then\n" .
            "   setTimer(1, 60);\n" .
            ($coolingEnabled ? "   setTimer(2, 60);\n" : '') .
            "end\n\n" .
            "on @Compressor_Freq then\n" .
            "   if @Compressor_Freq == 0 && @Defrosting_State == 0 && @Outside_Temp >= $minOutsideTemp && @Z1_Heat_Request_Temp == 0 then\n" .
            "      @SetZ1HeatRequestTemperature = -5;\n" .
            "      setTimer(1, $seconds);\n" .
            "   end\n" .
            ($coolingEnabled
                ? "   if @Compressor_Freq == 0 && @Outside_Temp <= $maxOutsideTempCool && @Z1_Cool_Request_Temp == 0 then\n" .
                  "      @SetZ1CoolRequestTemperature = -5;\n" .
                  "      setTimer(2, $seconds);\n" .
                  "   end\n"
                : '') .
            "end\n\n" .
            "on timer=1 then\n" .
            "   if @Z1_Heat_Request_Temp == -5 then\n" .
            "      @SetZ1HeatRequestTemperature = 0;\n" .
            "   end\n" .
            "end\n\n" .
            "on @Outside_Temp then\n" .
            "   if @Outside_Temp < $minOutsideTemp && @Z1_Heat_Request_Temp == -5 then\n" .
            "      setTimer(1, 1);\n" .
            "   end\n";
        if ($coolingEnabled) {
            $rules .= "   if @Outside_Temp > $maxOutsideTempCool && @Z1_Cool_Request_Temp == -5 then\n" .
                "      setTimer(2, 1);\n" .
                "   end\n";
        }
        $rules .= "end\n";
        if ($coolingEnabled) {
            $rules .= "\non timer=2 then\n" .
                "   if @Z1_Cool_Request_Temp == -5 then\n" .
                "      @SetZ1CoolRequestTemperature = 0;\n" .
                "   end\n" .
                "end\n";
        }
        return $rules;
    }

    /**
     * Spielt das parametrisierte Taktschutz-Regelwerk auf die HeishaMon-Platine
     * (POST /saverules, gleiches Format wie deren eigene Weboberflaeche). ACHTUNG:
     * ersetzt das komplette dort gespeicherte Regelwerk - die Firmware validiert den
     * Upload selbst und behaelt bei Fehlern das alte Regelwerk.
     */
    public function DeployShortCycleGuard()
    {
        $deviceIP = trim((string) $this->ReadPropertyString('DeviceIP'));
        if ($deviceIP == '') {
            echo $this->Translate('Please enter the HeishaMon IP address first.');
            return;
        }
        $rules = $this->buildShortCycleGuardRules(
            (int) $this->ReadPropertyInteger('GuardOffMinutes'),
            (int) $this->ReadPropertyInteger('GuardMinOutsideTemp'),
            $this->ReadPropertyBoolean('GuardCoolingEnabled'),
            (int) $this->ReadPropertyInteger('GuardMaxOutsideTempCool')
        );
        $result = $this->sendHttpPost('http://' . $deviceIP . '/saverules', http_build_query(['rules' => $rules]));
        if ($result === false) {
            echo sprintf($this->Translate('Upload failed - HeishaMon at %s is not reachable via HTTP.'), $deviceIP);
            return;
        }
        echo $this->Translate('Ruleset uploaded. HeishaMon validates it itself - an invalid ruleset is discarded and the previous one stays active (see the HeishaMon console for details).');
    }

    /**
     * Neustart-Watchdog (Timer, minuetlich): Ist die Waermepumpe laenger als konfiguriert
     * per MQTT offline, wird ueber den /reboot-Endpunkt der Firmware ein Platinen-Neustart
     * angestossen. Hilft im haeufigsten Haenger-Fall (MQTT tot, Webserver antwortet noch);
     * ist die Platine komplett vom Netz, greift die Firmware-eigene Reconnect-Logik.
     * 30 Minuten Cooldown zwischen Versuchen, gegen Neustart-Schleifen.
     */
    public function CheckRebootWatchdog()
    {
        $reachableID = @$this->GetIDForIdent('Reachable');
        if ($reachableID !== false && (bool) GetValue($reachableID)) {
            $this->WriteAttributeInteger('OfflineSince', 0);
            return;
        }
        $offlineSince = $this->ReadAttributeInteger('OfflineSince');
        if ($offlineSince == 0) {
            //offline ohne bekannten Beginn (z.B. IPS-Neustart wahrend Offline-Phase)
            $this->WriteAttributeInteger('OfflineSince', time());
            return;
        }
        $threshold = max(2, $this->ReadPropertyInteger('RebootWatchdogMinutes')) * 60;
        if (time() - $offlineSince < $threshold) {
            return;
        }
        if (time() - $this->ReadAttributeInteger('LastRebootAttempt') < 1800) {
            return;
        }
        $this->WriteAttributeInteger('LastRebootAttempt', time());
        $deviceIP = trim((string) $this->ReadPropertyString('DeviceIP'));
        $result = $this->sendHttpGet('http://' . $deviceIP . '/reboot');
        $this->LogMessage(
            $result === false
                ? sprintf($this->Translate('Reboot watchdog: heat pump offline for %d minutes, but HeishaMon at %s does not respond to HTTP either - a power cycle may be required.'), intdiv(time() - $offlineSince, 60), $deviceIP)
                : sprintf($this->Translate('Reboot watchdog: heat pump offline for %d minutes - restart of the HeishaMon board triggered.'), intdiv(time() - $offlineSince, 60)),
            KL_WARNING
        );
    }

    /**
     * HTTP-GET, gleiche Stream-Basis wie sendHttpPost. Rueckgabe: Antwort-Body oder false.
     */
    protected function sendHttpGet(string $url)
    {
        $context = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 5]]);
        return @file_get_contents($url, false, $context);
    }

    /**
     * HTTP-POST als Formulardaten. Bewusst ueber PHP-Streams statt Sys_GetURLContentEx -
     * letzteres kennt laut IPS-Doku nur Timeout/Auth/SSL-Optionen und ignoriert
     * Method/Content stillschweigend (es ginge ein GET raus, den der HeishaMon-Webserver
     * ablehnt). Rueckgabe: Antwort-Body oder false.
     */
    protected function sendHttpPost(string $url, string $body)
    {
        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $body,
                'timeout' => 5
            ]
        ]);
        return @file_get_contents($url, false, $context);
    }

    /**
     * Energiespar-Pruefung: bewertet die von der Anlage empfangenen Einstellwerte gegen
     * Richtwerte aus den dokumentierten HeishaMon-Beispielregelwerken (Examples/Rules/)
     * und dem Panasonic-Servicehandbuch. Reine Anzeige - es wird nichts veraendert.
     * Liefert Zeilen als [Symbol, Text]; Pruefungen ohne Datenbasis entfallen still.
     */
    private function buildEnergySavingFindings(): array
    {
        $value = function (string $ident) {
            $variableID = @$this->GetIDForIdent($ident);
            return $variableID === false ? null : GetValue($variableID);
        };
        $findings = [];

        //Takt-Analyse aus den echten Zaehlern - der aussagekraeftigste Einzelbefund
        $starts = $value('Operations_Counter');
        $hours = $value('Operations_Hours');
        if ($starts !== null && $hours !== null && $starts > 100 && $hours > 100) {
            $hoursPerStart = $hours / $starts;
            if ($hoursPerStart < 0.5) {
                $findings[] = ['⚠️', sprintf($this->Translate('The unit short-cycles: on average %.1f minutes of runtime per compressor start. Frequent starts cost efficiency and compressor lifetime - consider the short-cycle guard ruleset and a flatter heating curve.'), $hoursPerStart * 60)];
            } elseif ($hoursPerStart < 1.0) {
                $findings[] = ['💡', sprintf($this->Translate('On average %.1f minutes of runtime per compressor start - acceptable, but longer runtimes would improve efficiency.'), $hoursPerStart * 60)];
            } else {
                $findings[] = ['✅', sprintf($this->Translate('Good runtime per compressor start (%.1f hours on average).'), $hoursPerStart)];
            }
        }

        //Warmwasser-Sollwert
        $dhwTarget = $value('DHW_Target_Temp');
        if ($dhwTarget !== null && $dhwTarget > 0) {
            if ($dhwTarget > 52) {
                $findings[] = ['⚠️', sprintf($this->Translate('DHW target temperature %d °C is high - each degree less improves the COP noticeably. The reference ruleset uses 43-50 °C depending on outside temperature; legionella protection is covered by the sterilization program.'), $dhwTarget)];
            } elseif ($dhwTarget > 50) {
                $findings[] = ['💡', sprintf($this->Translate('DHW target temperature %d °C - a small reduction usually goes unnoticed and saves energy.'), $dhwTarget)];
            } else {
                $findings[] = ['✅', sprintf($this->Translate('DHW target temperature %d °C is in the efficient range.'), $dhwTarget)];
            }
        }

        //Warmwasser-Nachheiz-Hysterese (negativ; kleiner Betrag = springt oft an)
        $dhwDelta = $value('DHW_Heat_Delta');
        if ($dhwDelta !== null && $dhwDelta < 0 && $dhwDelta > -5) {
            $findings[] = ['💡', sprintf($this->Translate('DHW reheat delta %d K is narrow - the tank reheats often. A wider delta (e.g. -8 K) means fewer, longer DHW runs.'), $dhwDelta)];
        }

        //Heizstab-Freigaben
        $heaterOutdoor = $value('Heater_On_Outdoor_Temp');
        if ($heaterOutdoor !== null && $heaterOutdoor > -5) {
            $findings[] = ['💡', sprintf($this->Translate('Backup heater is allowed from %d °C outside temperature - the electric heater is the most expensive heat source. If the heat pump covers the load on its own, a lower enable temperature saves money.'), $heaterOutdoor)];
        }
        $heaterDelay = $value('Heater_Delay_Time');
        if ($heaterDelay !== null && $heaterDelay > 0 && $heaterDelay < 60) {
            $findings[] = ['💡', sprintf($this->Translate('Backup heater delay is only %d minutes - a longer delay gives the compressor more time before the expensive heater kicks in.'), $heaterDelay)];
        }

        //Heizgrenze
        $heatingOff = $value('Heating_Off_Outdoor_Temp');
        if ($heatingOff !== null && $heatingOff > 17) {
            $findings[] = ['💡', sprintf($this->Translate('Heating stays enabled up to %d °C outside temperature - lowering the heating-off threshold shortens the heating season.'), $heatingOff)];
        }

        //Vorlauf-Solltemperatur der Heizkurve
        $curveHigh = $value('Z1_Heat_Curve_Target_High_Temp');
        if ($curveHigh !== null && $curveHigh > 50) {
            $findings[] = ['⚠️', sprintf($this->Translate('Heating curve target of %d °C flow temperature is very high - every degree of flow temperature costs roughly 2-3%% efficiency. Check whether a flatter curve still keeps the rooms warm.'), $curveHigh)];
        }

        //Pumpenansteuerung
        $maxDuty = $value('Max_Pump_Duty');
        if ($maxDuty !== null && $maxDuty > 200) {
            $findings[] = ['💡', sprintf($this->Translate('Maximum pump duty %d is close to the limit - the reference ruleset caps it at 112 (quiet) / 170 (boost). A lower cap saves pump energy and noise; make sure the flow stays sufficient.'), $maxDuty)];
        }

        if (count($findings) == 0) {
            $findings[] = ['✅', $this->Translate('No findings - either the settings look efficient or the relevant datapoints have not been received yet.')];
        }
        return $findings;
    }

    /**
     * Monitoring-Datenpunkte, die fuer Zeitreihen-Kacheln archiviert werden sollen.
     * true = kumulativer Zaehler (Archiv-Aggregation "Zaehler": liefert den Zuwachs je
     * Periode, z.B. Starts pro Tag), false = Momentanwert (Standard-Aggregation).
     */
    private const ARCHIVE_IDENTS = [
        'Power_Total'        => false,
        'Heat_Output_Total'  => false,
        'Main_Inlet_Temp'    => false,
        'Main_Outlet_Temp'   => false,
        'Outside_Temp'       => false,
        'Defrosting_State'   => false,
        'COP_Measured'       => false,
        'COP_Internal'       => false,
        'COP_Today'          => false,
        'Operations_Counter' => true,
        'Operations_Hours'   => true,
        //Platinen-Diagnose: Verlauf von WLAN-Qualitaet und MQTT-Neuverbindungen ist die
        //Datenbasis fuer die Aussetzer-Analyse
        'Stats_WifiQuality'     => false,
        'Stats_MqttReconnects'  => true
    ];

    /**
     * Aktiviert die Archivierung der Monitoring-Datenpunkte - je Variable nur EINMAL
     * (Attribut ArchivedIdents), damit eine spaetere manuelle Abwahl des Nutzers im
     * Archiv-Handler erhalten bleibt (gleiche Nutzer-Hoheits-Logik wie bei Profilen).
     * Das Abwaehlen der Formular-Option stoppt nur kuenftige Aktivierungen, bereits
     * aktives Logging bleibt unangetastet.
     */
    private function maintainMonitoringArchive()
    {
        if (!$this->ReadPropertyBoolean('ArchiveMonitoring')) {
            return;
        }
        $archives = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
        if (count($archives) == 0) {
            return;
        }
        $archiveID = $archives[0];
        $done = json_decode((string) $this->ReadAttributeString('ArchivedIdents'), true) ?: [];
        foreach (self::ARCHIVE_IDENTS as $ident => $isCounter) {
            if (in_array($ident, $done)) {
                continue;
            }
            $variableID = @$this->GetIDForIdent($ident);
            if ($variableID === false) {
                continue; //Variable existiert noch nicht - naechster Versuch bei Neuanlage
            }
            if (!AC_GetLoggingStatus($archiveID, $variableID)) {
                AC_SetLoggingStatus($archiveID, $variableID, true);
                if ($isCounter) {
                    AC_SetAggregationType($archiveID, $variableID, 1);
                }
            }
            $done[] = $ident;
        }
        //Store-Review-Fund (28.09.2026): AC_Set* wirkt seit IPS 5.5 sofort (Archiv arbeitet
        //intern ueber Attribute statt Properties) - IPS_ApplyChanges auf die Archiv-Instanz
        //ist seither ueberfluessig.
        $this->WriteAttributeString('ArchivedIdents', json_encode($done));
    }

    /**
     * Fuehrt die thermische Gesamtleistung (Summe der Erzeugungswerte) als Variable nach.
     */
    private function updateHeatOutputTotal()
    {
        if (@$this->GetIDForIdent('Heat_Output_Total') !== false) {
            $this->SetValue('Heat_Output_Total', $this->getThermalPower());
        }
    }

    private function getElectricalPower(): float
    {
        return $this->sumPowerIdents(
            ['Heat_Power_Consumption_Extra', 'Cool_Power_Consumption_Extra', 'DHW_Power_Consumption_Extra'],
            ['Heat_Power_Consumption', 'Cool_Power_Consumption', 'DHW_Power_Consumption']
        );
    }

    /**
     * COP aus den HeishaMon-eigenen Schaetzwerten, bei jedem Empfang der Leistungs-Topics.
     */
    private function updateInternalCOP()
    {
        $ident = 'COP_Internal';
        if (@$this->GetIDForIdent($ident) === false) {
            $this->MaintainVariable($ident, $this->Translate('COP (HeishaMon estimate)'), VARIABLETYPE_FLOAT, [
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                'DIGITS'       => 2
            ], 200, true);
        }
        $consumption = $this->getElectricalPower();
        $this->SetValue($ident, $consumption > 0 ? round($this->getThermalPower() / $consumption, 2) : 0.0);
    }

    /**
     * COP aus der gemessenen elektrischen Leistung des Stromzaehlers.
     */
    private function updateMeasuredCOP(float $electricalPower)
    {
        if (@$this->GetIDForIdent('COP_Measured') === false) {
            return;
        }
        $minPower = $this->ReadPropertyFloat('COPMinPower');
        $cop = 0.0;
        if ($electricalPower >= $minPower) {
            //Obergrenze gegen Ausreisser beim Anlaufen/Takten
            $cop = round(min($this->getThermalPower() / $electricalPower, 15), 2);
        }
        $this->SetValue('COP_Measured', $cop);
    }

    /**
     * Tageswerte: Stromverbrauch aus dem Energiezaehler, Waermemenge aus der Integration,
     * daraus die Tages-Arbeitszahl.
     */
    private function updateDailyValues()
    {
        $energyID = $this->resolvedEnergyID();
        if ($energyID <= 0 || !IPS_VariableExists($energyID) || @$this->GetIDForIdent('COP_Today') === false) {
            return;
        }

        $counter = floatval(GetValue($energyID));
        $base = $this->ReadAttributeFloat('EnergyCounterBase');
        //Erststart oder Zaehler wurde zurueckgesetzt/getauscht
        if ($base < 0 || $counter < $base) {
            $base = $counter;
            $this->WriteAttributeFloat('EnergyCounterBase', $base);
        }

        $electricalKwh = $counter - $base;
        $heatKwh = $this->ReadAttributeFloat('HeatWhToday') / 1000;

        $this->SetValue('Power_Energy_Today', round($electricalKwh, 3));
        $this->SetValue('Heat_Energy_Today', round($heatKwh, 3));
        //Erst ab einer Mindestenergie rechnen, sonst dominiert das Rauschen
        $this->SetValue('COP_Today', $electricalKwh >= 0.05 ? round($heatKwh / $electricalKwh, 2) : 0.0);
    }

    public function ReceiveData($JSONString)
    {
        $buffer = json_decode($JSONString, true);
        if (!is_array($buffer) || !array_key_exists('Topic', $buffer)) {
            return '';
        }

        $baseTopic = $this->ReadPropertyString('MQTTTopic');
        $topic = $buffer['Topic'];
        $payload = strval($buffer['Payload']);

        if (strpos($topic, $baseTopic . '/') !== 0) {
            return '';
        }
        $subTopic = substr($topic, strlen($baseTopic) + 1);

        //Verfuegbarkeit (Last Will Topic)
        if ($subTopic == 'LWT') {
            $online = $payload == 'Online';
            $this->SetValue('Reachable', $online);
            //Offline-Beginn fuer den Neustart-Watchdog festhalten
            if ($online) {
                $this->WriteAttributeInteger('OfflineSince', 0);
            } elseif ($this->ReadAttributeInteger('OfflineSince') == 0) {
                $this->WriteAttributeInteger('OfflineSince', time());
            }
            return '';
        }

        //1-Wire-Sensoren (z.B. DS18B20) senden auf einem dynamischen, adressabhaengigen
        //Topic statt der festen TopicMap - eigene Erkennung und Variablenpflege.
        if (preg_match('/^1wire\/([0-9a-fA-F]+)$/', $subTopic, $matches)) {
            $this->receiveOneWire(strtoupper($matches[1]), $payload);
            return '';
        }

        //Platinen-Diagnose: HeishaMon publiziert zyklisch ein Status-JSON auf <base>/stats
        if ($subTopic == 'stats') {
            $this->receiveStats($payload);
            return '';
        }

        $topics = HeishaMonTopics::topics();
        if (!array_key_exists($subTopic, $topics)) {
            if ($this->ReadPropertyBoolean('DebugUnknownTopics')) {
                $this->SendDebug('Unknown Topic', $subTopic . ' = ' . $payload, 0);
            }
            return '';
        }

        $this->rememberSeenTopic($subTopic);

        $definition = $topics[$subTopic];
        $ident = HeishaMonTopics::identFromTopic($subTopic);

        //Bestehende (auch ausgeblendete) Variablen werden weiter aktualisiert,
        //neue entstehen nur fuer aktivierte Datenpunkte
        if (@$this->GetIDForIdent($ident) === false) {
            if (!$this->isTopicSelected($subTopic)) {
                return '';
            }
            $this->maintainTopicVariable($ident, $subTopic, $definition);
            //neue Variable sofort in die Linkstruktur aufnehmen
            $this->maintainLinkTree();
            //Monitoring-Datenpunkte direkt bei Neuanlage archivieren
            if (array_key_exists($ident, self::ARCHIVE_IDENTS)) {
                $this->maintainMonitoringArchive();
            }
        }

        switch ($definition['kind']) {
            case 'bool':
                $this->SetValue($ident, intval($payload) == 1);
                break;
            case 'int':
            case 'enum':
                $this->SetValue($ident, intval($payload));
                break;
            case 'float':
                //optionaler Skalierungsfaktor (z.B. S0-WatthourTotal: Wh -> kWh)
                $this->SetValue($ident, floatval($payload) * ($definition['scale'] ?? 1));
                break;
            default:
                $this->SetValue($ident, $payload);
                break;
        }

        //Vereinheitlichte Betriebsart (Verbund-Enum) nachfuehren
        if ($subTopic == 'main/Operating_Mode_State' && @$this->GetIDForIdent('Operating_Mode_Norm') !== false) {
            $this->SetValue('Operating_Mode_Norm', $this->normalizeOperatingMode(intval($payload)));
        }

        //COP aus den HeishaMon-Schaetzwerten nachfuehren
        if (in_array($subTopic, [
            'main/Heat_Power_Production', 'main/Heat_Power_Consumption',
            'main/Cool_Power_Production', 'main/Cool_Power_Consumption',
            'main/DHW_Power_Production', 'main/DHW_Power_Consumption',
            'extra/Heat_Power_Production_Extra', 'extra/Heat_Power_Consumption_Extra',
            'extra/Cool_Power_Production_Extra', 'extra/Cool_Power_Consumption_Extra',
            'extra/DHW_Power_Production_Extra', 'extra/DHW_Power_Consumption_Extra'
        ])) {
            $this->updateInternalCOP();
            $this->updateTotalPower();
            $this->updateHeatOutputTotal();
        }
        return '';
    }

    /**
     * Platinen-Diagnose aus dem stats-JSON der Firmware: WLAN-Qualitaet, Laufzeit,
     * MQTT-Neuverbindungen, Bus-Lesequalitaet (Anteil fehlerfreier Datagramme),
     * aktive Regeln und Firmware-Version. Genau die Werte, die bei Verbindungs-
     * abbruechen die Ursache eingrenzen (schwaches WLAN? MQTT-Haenger? Neustarts?
     * Kabel-/CRC-Fehler?).
     */
    private function receiveStats(string $payload)
    {
        $data = json_decode($payload, true);
        if (!is_array($data)) {
            return;
        }
        $percentPresentation = ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'SUFFIX' => ' %', 'DIGITS' => 0];
        if (array_key_exists('wifi', $data)) {
            $this->maintainStatsVariable('Stats_WifiQuality', $this->Translate('WiFi quality'), VARIABLETYPE_INTEGER, $percentPresentation, 3000);
            $this->SetValue('Stats_WifiQuality', intval($data['wifi']));
        }
        if (array_key_exists('uptime', $data)) {
            $this->maintainStatsVariable('Stats_Uptime', $this->Translate('Uptime since board restart'), VARIABLETYPE_FLOAT, [
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'SUFFIX' => ' h', 'DIGITS' => 1
            ], 3001);
            $this->SetValue('Stats_Uptime', round(floatval($data['uptime']) / 3600000, 1));
        }
        if (array_key_exists('mqtt reconnects', $data)) {
            $this->maintainStatsVariable('Stats_MqttReconnects', $this->Translate('MQTT reconnects'), VARIABLETYPE_INTEGER, [
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'DIGITS' => 0
            ], 3002);
            $this->SetValue('Stats_MqttReconnects', intval($data['mqtt reconnects']));
        }
        if (array_key_exists('total reads', $data) && intval($data['total reads']) > 0) {
            $this->maintainStatsVariable('Stats_ReadQuality', $this->Translate('Bus read quality'), VARIABLETYPE_FLOAT, [
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'SUFFIX' => ' %', 'DIGITS' => 1
            ], 3003);
            $this->SetValue('Stats_ReadQuality', round(intval($data['good reads'] ?? 0) / intval($data['total reads']) * 100, 1));
        }
        if (array_key_exists('rules active', $data)) {
            $this->maintainStatsVariable('Stats_RulesActive', $this->Translate('Active rules'), VARIABLETYPE_INTEGER, [
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'DIGITS' => 0
            ], 3004);
            $this->SetValue('Stats_RulesActive', intval($data['rules active']));
        }
        if (array_key_exists('version', $data)) {
            $this->maintainStatsVariable('Stats_FirmwareVersion', $this->Translate('Firmware version'), VARIABLETYPE_STRING, [
                'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
            ], 3005);
            $this->SetValue('Stats_FirmwareVersion', strval($data['version']));
        }
        //Diagnose-Variablen ggf. nachtraeglich archivieren (WifiQuality/MqttReconnects)
        $this->maintainMonitoringArchive();
    }

    /**
     * Legt eine Diagnose-Variable an bzw. aktualisiert die Darstellung nur bei Abweichung.
     */
    private function maintainStatsVariable(string $ident, string $caption, int $type, array $presentation, int $position)
    {
        $variableID = @$this->GetIDForIdent($ident);
        if ($variableID !== false) {
            $current = @IPS_GetVariablePresentation($variableID);
            if (is_array($current) && $this->presentationMatches($current, $presentation)) {
                return;
            }
        }
        $this->MaintainVariable($ident, $caption, $type, $presentation, $position, true);
        if ($variableID === false) {
            //neue Diagnose-Variable sofort in die Linkstruktur aufnehmen
            $this->maintainLinkTree();
        }
    }

    /**
     * Verarbeitet den Messwert eines 1-Wire-Sensors (z.B. DS18B20). Neue Adressen werden
     * automatisch in der Sensorliste vermerkt (Formular zeigt sie beim naechsten Oeffnen
     * zur Benennung an); die Variable entsteht erst, wenn der Sensor aktiv geschaltet ist.
     */
    private function receiveOneWire(string $address, string $payload)
    {
        $this->rememberSeenOneWire($address);

        $ident = 'OneWire_' . $address;
        if (@$this->GetIDForIdent($ident) === false) {
            if (!$this->isOneWireSelected($address)) {
                return;
            }
            $this->maintainOneWireVariable($address);
            $this->maintainLinkTree();
        }
        $this->SetValue($ident, floatval($payload));
    }

    private function rememberSeenOneWire(string $address)
    {
        $seen = json_decode((string) $this->ReadAttributeString('SeenOneWire'), true) ?: [];
        if (!in_array($address, $seen)) {
            $seen[] = $address;
            $this->WriteAttributeString('SeenOneWire', json_encode($seen));
        }
    }

    /**
     * Adresse => Konfigurationszeile (Name/Auswahl), aus der vom Nutzer gepflegten Liste.
     */
    private function getOneWireConfigMap(): array
    {
        $map = [];
        $rows = json_decode((string) $this->ReadPropertyString('OneWireSensors'), true);
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (isset($row['Address'])) {
                    $map[$row['Address']] = $row;
                }
            }
        }
        return $map;
    }

    private function isOneWireSelected(string $address): bool
    {
        $config = $this->getOneWireConfigMap();
        return boolval($config[$address]['Selected'] ?? true);
    }

    /**
     * Anzeigename: vom Nutzer vergebener Name, sonst ein generischer Standardname mit den
     * letzten vier Adressstellen zur Unterscheidung mehrerer Sensoren.
     */
    private function oneWireCaption(string $address): string
    {
        $config = $this->getOneWireConfigMap();
        $name = trim($config[$address]['Name'] ?? '');
        if ($name !== '') {
            return $name;
        }
        return $this->Translate('1-Wire sensor') . ' ' . substr($address, -4);
    }

    /**
     * Alle bekannten Adressen in Anzeige-Reihenfolge: zuerst die gespeicherte (per Drag & Drop
     * sortierte) Liste, danach noch unbekannte, aber bereits empfangene Adressen.
     */
    private function getOrderedOneWireAddresses(): array
    {
        $seen = json_decode((string) $this->ReadAttributeString('SeenOneWire'), true) ?: [];
        $saved = [];
        $rows = json_decode((string) $this->ReadPropertyString('OneWireSensors'), true);
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (isset($row['Address']) && in_array($row['Address'], $seen, true)) {
                    $saved[] = $row['Address'];
                }
            }
        }
        foreach ($seen as $address) {
            if (!in_array($address, $saved, true)) {
                $saved[] = $address;
            }
        }
        return $saved;
    }

    private function getOneWirePositionMap(): array
    {
        $map = [];
        //Bewusst oberhalb des Wertebereichs der festen TopicMap-Positionen (max. rund 1510
        //bei 151 Topics), damit 1-Wire-Sensoren nicht mit deren Reihenfolge kollidieren.
        $position = 2000;
        foreach ($this->getOrderedOneWireAddresses() as $address) {
            $map[$address] = $position;
            $position += 10;
        }
        return $map;
    }

    /**
     * Legt die Variable eines 1-Wire-Sensors an bzw. aktualisiert Name/Darstellung.
     */
    private function maintainOneWireVariable(string $address, bool $refreshOnly = false)
    {
        $ident = 'OneWire_' . $address;
        $variableID = @$this->GetIDForIdent($ident);
        if (($variableID !== false) != $refreshOnly) {
            return;
        }

        $presentation = [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX'       => ' °C',
            'DIGITS'       => 1
        ];
        if ($refreshOnly) {
            $current = @IPS_GetVariablePresentation($variableID);
            if (!is_array($current) || !$this->presentationMatches($current, $presentation)) {
                $this->MaintainVariable($ident, $this->oneWireCaption($address), VARIABLETYPE_FLOAT, $presentation, $this->getOneWirePositionMap()[$address] ?? 2000, true);
            }
            //Vom Nutzer gepflegten Namen nachfuehren, unabhaengig von der Darstellung
            if (IPS_GetName($variableID) !== $this->oneWireCaption($address)) {
                IPS_SetName($variableID, $this->oneWireCaption($address));
            }
            return;
        }

        $this->MaintainVariable($ident, $this->oneWireCaption($address), VARIABLETYPE_FLOAT, $presentation, $this->getOneWirePositionMap()[$address] ?? 2000, true);
    }

    /**
     * Baut die Zeilen der 1-Wire-Sensorliste im Formular: alle jemals empfangenen Adressen,
     * inklusive Name/Auswahl aus der gespeicherten Konfiguration.
     */
    private function buildOneWireListRows(?array $orderedAddresses = null): array
    {
        $seen = json_decode((string) $this->ReadAttributeString('SeenOneWire'), true) ?: [];
        $config = $this->getOneWireConfigMap();
        $rows = [];
        foreach ($orderedAddresses ?? $this->getOrderedOneWireAddresses() as $address) {
            $rows[] = [
                'Selected' => boolval($config[$address]['Selected'] ?? true),
                'Name'     => $config[$address]['Name'] ?? '',
                'Address'  => $address,
                'Received' => in_array($address, $seen) ? $this->Translate('Yes') : ''
            ];
        }
        return $rows;
    }

    /**
     * Meldet die Funktionen dieser Instanz an andere Module (z.B. Energiefluss-Visualisierungen),
     * damit die Waermepumpe dort ohne manuelle Zuweisung auftaucht.
     *
     * Teil des gemeinsamen, herstellerneutralen 'heatpump'-Vertragstyps (NRG-Stack-Konvention,
     * von EMS final abgestimmt): jedes Waermepumpen-Modul (z.B. auch WPHub fuer die Panasonic-
     * Comfort-Cloud) implementiert denselben Feldsatz additiv, fuellt aber nur, was seine
     * eigene Datenquelle tatsaechlich hergibt - fehlende Felder bleiben 0. Konsumenten (z.B.
     * NRGDashboard) sollten generisch je nach vorhandenem Feld rendern, nicht HeishaMon-
     * spezifisch wissen, welche Quelle welche Felder liefert.
     *
     * Rueckgabe je Eintrag:
     *   Type     'heatpump'
     *   Caption  Name der Instanz
     *   PowerID  Variable mit der elektrischen Leistung in W (0, falls nicht vorhanden)
     *   EnergyID Variable mit dem Energiezaehlerstand in kWh, 0 wenn kein externer Zaehler
     *            konfiguriert ist. Bewusst NICHT "Stromverbrauch heute", da dieser Wert
     *            taeglich zurueckgesetzt wird und als kumulativer Zaehler ungeeignet ist.
     *   Measured true  = PowerID stammt aus einer echten Messung
     *            false = HeishaMon-Schaetzung der Waermepumpe, grob in ~200-W-Stufen.
     *                    Konsumenten sollten den Wert dann nicht mit Nachkommastellen
     *                    darstellen, das waere Scheingenauigkeit.
     *            Nicht aus EnergyID ableitbar: Leistungs- und Energiezaehler lassen sich
     *            unabhaengig voneinander konfigurieren.
     *   unit     Physikalische Einheit von PowerID, informativ (z.B. fuer Konsumenten ohne
     *            eigenes Variablenprofil auf der Presentation-basierten Power_Total-Variable).
     *   *ID      Ab contractVersion 1.3: ausgewaehlte Heizkreislauf-Datenpunkte fuer eine
     *            Anlagenschema-Visualisierung (z.B. NRGDashboard) - pumpFlowID, pumpSpeedID,
     *            pumpDutyID, threeWayValveStateID, twoWayValveStateID, mainInletTempID,
     *            mainOutletTempID, z1WaterTempID, z2WaterTempID, dhwTempID, bufferTempID,
     *            compressorFreqID, dischargeTempID, defrostingStateID. Jeweils 0, wenn der
     *            Datenpunkt (noch) nicht empfangen wurde oder abgewaehlt/versteckt ist -
     *            NICHT abhaengig vom Sichtbarkeitsstatus (analog PowerID/EnergyID).
     *            WICHTIG: pumpFlowID/pumpSpeedID/pumpDutyID/twoWayValveStateID betreffen die
     *            INTERNE Pumpe/das interne Ventil im Innengeraet (main/...-Kernprotokoll).
     *            Fuer eine externe, von der optionalen 2. Steuerplatine angesteuerte
     *            Heizkreis-Pumpe/-Mischventil siehe z1PumpID/z1MixingValveID unten - das ist
     *            physikalisch etwas anderes, nicht dieselbe Pumpe an anderer Stelle im Vertrag.
     *   z1PumpID/z2PumpID Ab contractVersion 1.4: externe Zone-1/2-Heizkreispumpe (An/Aus,
     *            Boolean-Variable). Quelle seit contractVersion 1.7 priorisiert: bevorzugt
     *            main/Z1_Pump_State bzw. main/Z2_Pump_State (Kernprotokoll - meldet auch den
     *            Zustand einer ECHTEN, physisch verbauten CZ-NS4P), Fallback auf die
     *            optional/...-Emulations-Variablen, falls nur diese existieren. Semantik
     *            unveraendert. 0, wenn keine der Quellen Daten liefert.
     *   z1MixingValveID/z2MixingValveID Ab contractVersion 1.4: externes Zone-1/2-Mischventil
     *            an der optionalen 2. Steuerplatine. Integer-Variable mit STELLRICHTUNG, KEINER
     *            absoluten Position: 0=Aus, 1=Zu (Decrease), 2=Auf (Increase). 0-Wert also
     *            zweideutig zwischen "kein Datenpunkt vorhanden" und "Ventil steht gerade still"
     *            - Konsumenten, die das unterscheiden muessen, sollten zusaetzlich pruefen, ob
     *            die Variable ueberhaupt existiert (IPS_VariableExists), nicht nur den Wert.
     *   fan1SpeedID/fan2SpeedID Ab contractVersion 1.5: Luefterdrehzahl des Aussengeraets in
     *            U/min (main/Fan1_Motor_Speed bzw. main/Fan2_Motor_Speed). 0, wenn nicht
     *            empfangen. ACHTUNG (Korrektur 24.08.2026, Dashboard-Fund bei Dietmars
     *            Ein-Luefter-Geraet): main/Fan2_Motor_Speed liegt an einer fixen Byte-Position
     *            im CN-CNT-Protokoll und wird von der Firmware IMMER dekodiert/gesendet,
     *            unabhaengig davon, ob das konkrete Modell ueberhaupt zwei Luefter hat - die
     *            reine Existenz von fan2SpeedID ist daher KEIN verlaesslicher Beleg fuer einen
     *            zweiten Luefter. Konsumenten, die das brauchen, sollten pruefen, ob der Wert
     *            jemals > 0 war, nicht nur ob die Variable existiert.
     *   suctionTempID Ab contractVersion 1.6: Sauggas-/Kaltgastemperatur in °C, Gegenstueck
     *            zur Heissgastemperatur (dischargeTempID). Funktional-herstellerneutral
     *            benannt: jedes heatpump-Modul liefert seine beste verfuegbare Messstelle
     *            der Sauggasseite - bei HeishaMon/Panasonic ist das main/Eva_Outlet_Temp
     *            (Verdampferaustritt, direkt vor dem Verdichter-Saugstutzen; einen explizit
     *            "Suction" benannten Sensor gibt es im Panasonic-Protokoll nicht).
     *            0, wenn nicht empfangen. Achtung: gilt fuer den HEIZbetrieb - im
     *            Kuehlbetrieb kehrt sich der Kaeltekreis um, dann ist indoorPipeTempID die
     *            tatsaechlich kalte Seite.
     *   operatingModeID Ab contractVersion 1.7: konfigurierte Betriebsart
     *            (main/Operating_Mode_State), Integer/Enum: 0=Nur Heizen, 1=Nur Kuehlen,
     *            2=Auto(Heizen), 3=Nur Warmwasser, 4=Heizen+WW, 5=Kuehlen+WW,
     *            6=Auto(Heizen)+WW, 7=Auto(Kuehlen), 8=Auto(Kuehlen)+WW, -1=unbekannt.
     *            ROHER Panasonic-Enum, nur fuer Diagnose - Konsumenten sollten stattdessen
     *            operatingModeNormID ablesen (herstellerneutral).
     *            Das ist die KONFIGURIERTE Betriebsart - ob gerade tatsaechlich gelaufen
     *            wird, zeigen compressorFreqID/PowerID; ob gerade Warmwasser bereitet wird,
     *            threeWayValveStateID.
     *   operatingModeNormID Ab contractVersion 1.8: VEREINHEITLICHTE Betriebsart mit dem
     *            Verbund-Enum des heatpump-Vertrags (SUITE.md): 0=standby, 1=heating,
     *            2=cooling, 3=dhw, 4=heating+dhw, 5=cooling+dhw, -1=unbekannt. Zeigt auf
     *            eine modul-gepflegte, abgeleitete Variable (Praezedenzfall Power_Total) -
     *            jedes heatpump-Modul mappt seinen Hersteller-Enum selbst auf diese Werte,
     *            Konsumenten muessen keine Herstellersemantik nachbauen. Auto-Modi werden
     *            auf die aktuell aktive Richtung abgebildet; standby (0) liefert HeishaMon
     *            nie (Panasonic kennt keine leere Betriebsart), andere Hersteller schon.
     *   z1MixingValvePositionID/z2MixingValvePositionID Ab contractVersion 1.7: absolute
     *            Position des Zone-1/2-Mischventils in Prozent (0-100, Float-Variable,
     *            main/Z1_Valve_PID bzw. Z2) - aussagekraeftiger als die Stellrichtungs-Felder
     *            z1/z2MixingValveID, die unveraendert bestehen bleiben.
     *   indoorPipeTempID Ab contractVersion 1.7: Rohrtemperatur der Inneneinheit in °C
     *            (main/Inside_Pipe_Temp) - im Kuehlbetrieb die tatsaechlich kalte
     *            Kaeltemittelseite am Waermetauscher (suctionTempID liegt dann auf der
     *            warmen Verfluessigerseite).
     *   copEstimateID Ab contractVersion 1.9: momentaner COP aus den WP-eigenen
     *            Leistungs-Schaetzwerten (~200-W-Stufen, entsprechend grob - nicht mit
     *            Nachkommastellen-Genauigkeit interpretieren). 0, wenn noch keine
     *            Leistungswerte empfangen wurden.
     *   copMeasuredID Ab contractVersion 1.9: momentaner COP aus Waermeleistung geteilt
     *            durch den EXTERN gemessenen Stromverbrauch (konfigurierter Zaehler,
     *            z.B. Shelly). 0, wenn kein externer Leistungszaehler konfiguriert ist.
     *   dailyPerformanceFactorID Ab contractVersion 1.9: Tages-Arbeitszahl (Waermemenge
     *            heute / Stromverbrauch heute, ab Mitternacht). 0, wenn kein externer
     *            Energiezaehler konfiguriert ist. Monats-/Jahres-Arbeitszahlen liefert der
     *            Vertrag BEWUSST nicht (EMS-Entscheid): Zeitraum-Aggregation ueber die
     *            kumulativen Werte ist Sache der Konsumenten (Archiv/GleitenderMittelwert).
     *   heatOutputPowerID Ab contractVersion 1.10: thermische Gesamtleistung in W
     *            (Heizen + Kuehlen + WW-Erzeugung, modul-gepflegte Summenvariable aus den
     *            WP-eigenen Schaetzwerten, ~200-W-Stufen). Fuer Monitoring-Seiten, die
     *            elektrische (PowerID) und thermische Leistung uebereinanderlegen.
     *   outsideTempID Ab contractVersion 1.10: Aussentemperatur in °C (main/Outside_Temp,
     *            Fuehler der Waermepumpe).
     *   compressorStartsID Ab contractVersion 1.10: kumulierter Starts-Zaehler des
     *            Verdichters (main/Operations_Counter) - fuer Takt-Analysen (Starts/Tag).
     *   operationsHoursID Ab contractVersion 1.10: kumulierte Betriebsstunden
     *            (main/Operations_Hours) - zusammen mit compressorStartsID ergibt sich die
     *            mittlere Laufzeit je Start.
     *   internalHeaterStateID/externalHeaterStateID Ab contractVersion 1.12: Backup-/
     *            Zusatzheizstab aktiv (main/Internal_Heater_State, main/External_Heater_State,
     *            je Boolean). WICHTIG: Der Split ist Intern/Extern nach Hardware-Lage (Heizstab
     *            im Innengeraet vs. externer Zusatz-/Booster-Heizer) - NICHT WW/Raum. Die
     *            gleichnamig wirkenden main/DHW_Heater_State/main/Room_Heater_State sind reine
     *            Freigabe-Flags (Gegenstueck zu SetDHWHeaterState/SetRoomHeaterState), keine
     *            Laufzeit-Statuswerte, und werden deshalb hier nicht abgebildet (Klaerung mit
     *            Dashboard/EMS, 24.08.2026 - siehe SUITE.md-Feldregister).
     *   forceHeaterStateID Ab contractVersion 1.12: Notheizstab-Taste aktiv
     *            (main/Force_Heater_State, Boolean) - entspricht dem physischen Heizstab-Knopf
     *            an der Fernbedienung (SetForceHeater).
     *   Heizkurven Ab contractVersion 1.13: kein Feld, sondern das Funktionspaar
     *            HEISHA_GetHeatingCurve()/HEISHA_SetHeatingCurve() (Zwei-Punkt-Modell je Zone
     *            und Modus, siehe dort; SUITE.md "Funktionspaare oberhalb des Feldregisters").
     *            Ab contractVersion 1.14 additiv: Verschiebung (zones.zX.shift, boardRulesActive,
     *            HEISHA_SetHeatingCurveShift) und Bedienfunktionen (HEISHA_GetOperations/
     *            HEISHA_SetOperation).
     *            Ab contractVersion 1.15 additiv: HEISHA_GetHeatingAutomation/HEISHA_SetHeatingAutomation
     *            (Heizungs-Automatik) sowie in zones.zX.shift.heat|cool die Felder minC, maxC und
     *            presets (eco/normal/comfort) fuer Regler und Schnellwahl.
     *   contractVersion 'Major.Minor' des Vertrags (Suite-Konvention, SUITE.md im EMS-Repo).
     *            Major nur bei Bruch; Kompatibilitaet nur innerhalb derselben Major. Fehlt = '1.0'.
     */
    public function GetFunctions(): array
    {
        $powerID = @$this->GetIDForIdent('Power_Total');
        $energyID = $this->resolvedEnergyID();
        $reachableID = @$this->GetIDForIdent('Reachable');

        return [
            [
                'Type'            => 'heatpump',
                'Caption'         => IPS_GetName($this->InstanceID),
                'PowerID'         => $powerID === false ? 0 : $powerID,
                'EnergyID'        => ($energyID > 0 && IPS_VariableExists($energyID)) ? $energyID : 0,
                'Measured'        => $this->hasMeasuredPower(),
                'unit'            => 'W',
                //Wenn die WP nicht erreichbar ist, friert PowerID beim letzten bekannten Wert ein
                //(kein aktiver Reset auf 0, um keinen falschen "Anlage aus"-Zustand vorzutaeuschen).
                //Konsumenten sollten bei reachable=false den Wert als potenziell veraltet behandeln.
                'reachable'            => $reachableID === false ? true : (bool) GetValue($reachableID),
                'pumpFlowID'           => $this->idForIdent('Pump_Flow'),
                'pumpSpeedID'          => $this->idForIdent('Pump_Speed'),
                'pumpDutyID'           => $this->idForIdent('Pump_Duty'),
                'threeWayValveStateID' => $this->idForIdent('ThreeWay_Valve_State'),
                'twoWayValveStateID'   => $this->idForIdent('TwoWay_Valve_State'),
                'mainInletTempID'      => $this->idForIdent('Main_Inlet_Temp'),
                'mainOutletTempID'     => $this->idForIdent('Main_Outlet_Temp'),
                'z1WaterTempID'        => $this->idForIdent('Z1_Water_Temp'),
                'z2WaterTempID'        => $this->idForIdent('Z2_Water_Temp'),
                'dhwTempID'            => $this->idForIdent('DHW_Temp'),
                'bufferTempID'         => $this->idForIdent('Buffer_Temp'),
                'compressorFreqID'     => $this->idForIdent('Compressor_Freq'),
                'dischargeTempID'      => $this->idForIdent('Discharge_Temp'),
                'defrostingStateID'    => $this->idForIdent('Defrosting_State'),
                //Kernprotokoll bevorzugt (meldet auch eine echte CZ-NS4P), Emulation als Fallback
                'z1PumpID'             => $this->firstIdForIdents(['Z1_Pump_State', 'Optional_Z1_Water_Pump']),
                'z1MixingValveID'      => $this->idForIdent('Optional_Z1_Mixing_Valve'),
                'z2PumpID'             => $this->firstIdForIdents(['Z2_Pump_State', 'Optional_Z2_Water_Pump']),
                'z2MixingValveID'      => $this->idForIdent('Optional_Z2_Mixing_Valve'),
                'fan1SpeedID'          => $this->idForIdent('Fan1_Motor_Speed'),
                'fan2SpeedID'          => $this->idForIdent('Fan2_Motor_Speed'),
                'suctionTempID'        => $this->idForIdent('Eva_Outlet_Temp'),
                'operatingModeID'      => $this->idForIdent('Operating_Mode_State'),
                'operatingModeNormID'  => $this->idForIdent('Operating_Mode_Norm'),
                'z1MixingValvePositionID' => $this->idForIdent('Z1_Valve_PID'),
                'z2MixingValvePositionID' => $this->idForIdent('Z2_Valve_PID'),
                'indoorPipeTempID'     => $this->idForIdent('Inside_Pipe_Temp'),
                'copEstimateID'        => $this->idForIdent('COP_Internal'),
                'copMeasuredID'        => $this->idForIdent('COP_Measured'),
                'dailyPerformanceFactorID' => $this->idForIdent('COP_Today'),
                'heatOutputPowerID'    => $this->idForIdent('Heat_Output_Total'),
                'outsideTempID'        => $this->idForIdent('Outside_Temp'),
                'compressorStartsID'   => $this->idForIdent('Operations_Counter'),
                'operationsHoursID'    => $this->idForIdent('Operations_Hours'),
                'internalHeaterStateID' => $this->idForIdent('Internal_Heater_State'),
                'externalHeaterStateID' => $this->idForIdent('External_Heater_State'),
                'forceHeaterStateID'   => $this->idForIdent('Force_Heater_State'),
                'contractVersion'      => '1.15'
            ]
        ];
    }

    /**
     * Variablen-ID zu einem Ident, 0 wenn (noch) nicht vorhanden - unabhaengig vom
     * Sichtbarkeitsstatus (siehe GetFunctions-Dokumentation).
     */
    private function idForIdent(string $ident): int
    {
        $variableID = @$this->GetIDForIdent($ident);
        return $variableID === false ? 0 : $variableID;
    }

    /**
     * Erste existierende Variable aus einer Prioritaetenliste von Idents, 0 wenn keine
     * vorhanden ist (z.B. Kernprotokoll bevorzugt, Emulations-Topic als Fallback).
     */
    private function firstIdForIdents(array $idents): int
    {
        foreach ($idents as $ident) {
            $variableID = $this->idForIdent($ident);
            if ($variableID !== 0) {
                return $variableID;
            }
        }
        return 0;
    }

    /**
     * Liegt eine echte Leistungsmessung vor? Einzige Quelle der Wahrheit fuer die
     * Wertermittlung in updateTotalPower und das Measured-Flag in GetFunctions.
     */
    private function hasMeasuredPower(): bool
    {
        $powerID = $this->resolvedPowerID();
        return $powerID > 0 && IPS_VariableExists($powerID);
    }

    /**
     * Skalierungsfaktor fuer PowerVariable: die Variable ist frei vom Nutzer gewaehlt, das Modul
     * kennt ihr Herkunftsprofil nicht (anders als die eigenen Hub-Vertragsfelder, die immer Watt
     * liefern). Vorgabe "W" (Faktor 1) aendert nichts am bisherigen Verhalten.
     */
    private function powerVariableScale(): float
    {
        return (string) $this->ReadPropertyString('PowerVariableUnit') === 'kW' ? 1000.0 : 1.0;
    }

    /**
     * Fuehrt die elektrische Gesamtleistung nach: gemessener Wert des externen Zaehlers,
     * andernfalls die Summe der HeishaMon-Schaetzwerte (Heizen + Kuehlen + Warmwasser).
     */
    private function updateTotalPower()
    {
        if (@$this->GetIDForIdent('Power_Total') === false) {
            return;
        }
        if ($this->hasMeasuredPower()) {
            //Die Einheiten-Skalierung gilt nur fuer die eigene Variablenwahl - MeterHubs
            //Vertrag liefert immer Watt, keine unbekannte Einheit.
            $scale = $this->ReadPropertyInteger('PowerVariable') > 0 ? $this->powerVariableScale() : 1.0;
            $this->SetValue('Power_Total', floatval(GetValue($this->resolvedPowerID())) * $scale);
            return;
        }
        $this->SetValue('Power_Total', $this->getElectricalPower());
    }

    public function RequestAction($Ident, $Value)
    {
        //Zusaetzliche Schreibbefehle liegen ausserhalb der TopicMap (keine Rueckmeldung von
        //der Waermepumpe, daher kein passender Eintrag in getDefinitionByIdent)
        if ($Ident === 'SmartGridMode') {
            $this->SetValue($Ident, intval($Value));
            $this->SendSetCommand('SetSmartGridMode', strval(intval($Value)));
            return;
        }
        if ($Ident === 'Sched_Active') {
            $this->SetValue($Ident, boolval($Value));
            //Einschalten wendet den Sollwert fuer jetzt sofort an, Ausschalten laesst die Anlage unveraendert
            $this->WriteAttributeString('SchedRuntime', '{}');
            $this->ScheduleTick();
            return;
        }
        if ($Ident === 'HeatAuto_Active') {
            $this->SetValue($Ident, boolval($Value));
            if (boolval($Value)) {
                $this->WriteAttributeString('HeatAutoState', '');
                $this->WriteAttributeInteger('HeatAutoTries', 0);
                $this->WriteAttributeBoolean('HeatAutoFault', false);
                $this->HeatingAutomationTick();
            } else {
                $this->heatAutoRestore();
                $this->heatAutoPublish('', 'inactive', [], []);
            }
            return;
        }
        if ($Ident === 'GpioRelay1' || $Ident === 'GpioRelay2') {
            $this->SetValue($Ident, boolval($Value));
            $baseTopic = $this->ReadPropertyString('MQTTTopic');
            if ($baseTopic !== '') {
                $relayNumber = $Ident === 'GpioRelay1' ? 'one' : 'two';
                $this->sendMQTT($baseTopic . '/gpio/relay/' . $relayNumber, boolval($Value) ? '1' : '0');
            }
            return;
        }

        $definition = $this->getDefinitionByIdent($Ident);
        if ($definition === null) {
            throw new Exception($this->Translate('Unknown Ident: ') . $Ident);
        }
        if (!array_key_exists('set', $definition)) {
            throw new Exception($this->Translate('Variable is read-only: ') . $Ident);
        }

        switch ($definition['kind']) {
            case 'bool':
                $payload = boolval($Value) ? '1' : '0';
                $this->SetValue($Ident, boolval($Value));
                break;
            case 'float':
                $payload = strval(floatval($Value));
                $this->SetValue($Ident, floatval($Value));
                break;
            default:
                $payload = strval(intval($Value));
                $this->SetValue($Ident, intval($Value));
                break;
        }

        $this->SendSetCommand($definition['set'], $payload);
    }

    /**
     * Sendet einen beliebigen HeishaMon-Befehl, z.B. HEISHA_SendSetCommand(12345, 'SetQuietMode', '2');
     */
    public function SendSetCommand(string $Command, string $Value)
    {
        $baseTopic = $this->ReadPropertyString('MQTTTopic');
        if ($baseTopic == '') {
            return;
        }
        $this->sendMQTT($baseTopic . '/commands/' . $Command, $Value);
    }

    /**
     * Setzt die Heiz-/Kuehlkurven, erwartet das JSON-Dokument laut HeishaMon-Doku (SET16).
     */
    public function SetCurves(string $CurvesJSON)
    {
        json_decode($CurvesJSON);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo $this->Translate('Invalid JSON document');
            return;
        }
        $this->SendSetCommand('SetCurves', $CurvesJSON);
    }

    /**
     * Heizkurven lesen (Vertrag mit NRGDashboard/EMS, SUITE.md "Funktionspaare oberhalb des
     * Feldregisters", contractVersion 1.13). Zwei-Punkt-Modell: je Zone und Modus zwei
     * Ankerpunkte Aussentemperatur -> Vorlauf-Solltemperatur, alles ganzzahlig in °C (echte
     * Firmware-Aufloesung). Alles auf einmal, ohne MQTT-Roundtrip - gelesen werden die bereits
     * empfangenen Variablen. Ein Modus ist null, solange nicht alle vier Werte empfangen sind;
     * z2 ist null, wenn keine Zone 2 aktiv ist. curveWritable meldet nur die Faehigkeit des
     * Moduls (immer true), nicht die Erreichbarkeit - dafuer gibt es reachable in GetFunctions().
     */
    public function GetHeatingCurve(): array
    {
        $zones = [];
        foreach (['z1' => 'Z1', 'z2' => 'Z2'] as $zone => $prefix) {
            if (!$this->isCurveZoneActive($zone)) {
                $zones[$zone] = null;
                continue;
            }
            $zones[$zone] = [
                'heat'  => $this->readCurve($prefix, 'Heat'),
                'cool'  => $this->readCurve($prefix, 'Cool'),
                'shift' => [
                    'heat' => $this->readShift($prefix, 'Heat'),
                    'cool' => $this->readShift($prefix, 'Cool')
                ]
            ];
        }
        $rulesID = $this->idForIdent('Stats_RulesActive');
        return [
            'curveModel'      => 'twoPoint',
            'curveWritable'   => true,
            //Regeln auf der Platine (z.B. der Taktschutz) koennen die Verschiebung ueberschreiben
            'boardRulesActive' => $rulesID === 0 ? null : ((int) GetValue($rulesID)) > 0,
            'zones'           => $zones
        ];
    }

    /**
     * Verschiebung der Heiz-/Kuehlanforderung einer Zone setzen (Vertrag contractVersion 1.14).
     * Der Wert der Anlage ist je nach Reglermodus entweder eine Verschiebung (-5..+5) oder direkt
     * die Vorlauf-Solltemperatur; die Firmware klemmt nichts, ein gesendetes "+2" kaeme im
     * Direktmodus als 2 °C Vorlauf an. Deshalb false, solange die Anlage nicht nachweislich im
     * Verschiebungsmodus meldet (mode 'shift'). true heisst nur "abgeschickt".
     */
    public function SetHeatingCurveShift(string $Zone, string $Mode, int $ShiftC): bool
    {
        if (!in_array($Zone, ['z1', 'z2'], true) || !in_array($Mode, ['heat', 'cool'], true) || $ShiftC < self::SHIFT_MIN_C || $ShiftC > self::SHIFT_MAX_C) {
            return false;
        }
        $prefix = $Zone === 'z1' ? 'Z1' : 'Z2';
        $shift = $this->readShift($prefix, $Mode === 'heat' ? 'Heat' : 'Cool');
        if (!$this->isCurveZoneActive($Zone) || $shift['mode'] !== 'shift'
            || (string) $this->ReadPropertyString('MQTTTopic') === '' || !$this->HasActiveParent()) {
            return false;
        }
        $this->SendSetCommand('Set' . $prefix . ($Mode === 'heat' ? 'Heat' : 'Cool') . 'RequestTemperature', (string) $ShiftC);
        return true;
    }

    /**
     * Bedienfunktionen lesen (Vertrag contractVersion 1.14). value ist null, solange das Topic
     * nie empfangen wurde; min/max ist der SCHREIBbereich (holiday liest 0/1/2 = aus/geplant/
     * aktiv, schreibt aber nur 0/1). Die Werte stammen aus den Variablen, die HeishaMon zuletzt
     * gemeldet hat - nach einem SetOperation() aus diesem Vertrag bleibt der alte Wert stehen,
     * bis die Anlage die Aenderung meldet (kein optimistisches Vorschreiben).
     */
    public function GetOperations(): array
    {
        $operations = [];
        foreach (self::OPERATIONS as $name => $definition) {
            $variableID = $this->idForIdent($definition['ident']);
            $value = null;
            if ($variableID !== 0) {
                $raw = GetValue($variableID);
                $value = is_float($raw) ? (int) round($raw) : (int) $raw;
                if ($value < 0) {
                    //-1 = unbekannt laut HeishaMon-Doku
                    $value = null;
                }
            }
            $operations[$name] = ['value' => $value, 'min' => $definition['min'], 'max' => $definition['max']];
        }
        return $operations;
    }

    /**
     * Bedienfunktion setzen (name quiet|powerful|holiday|emergencyHeater|dhwTargetC). true heisst
     * NUR: Name und Wert gueltig, Gateway aktiv, MQTT-Befehl abgeschickt - die Anlage quittiert
     * nicht (und im HeishaMon-Modus "nur mithoeren" verwirft die Platine jeden Befehl, ohne dass
     * es hier erkennbar waere). Bestaetigung nur durch Zuruecklesen ueber GetOperations().
     */
    public function SetOperation(string $Name, int $Value): bool
    {
        $definition = self::OPERATIONS[$Name] ?? null;
        if ($definition === null || $Value < $definition['min'] || $Value > $definition['max']
            || (string) $this->ReadPropertyString('MQTTTopic') === '' || !$this->HasActiveParent()) {
            return false;
        }
        $this->SendSetCommand($definition['command'], (string) $Value);
        return true;
    }

    private const OPERATIONS = [
        'quiet'           => ['ident' => 'Quiet_Mode_Level',   'command' => 'SetQuietMode',    'min' => 0,  'max' => 3],
        'powerful'        => ['ident' => 'Powerful_Mode_Time', 'command' => 'SetPowerfulMode', 'min' => 0,  'max' => 3],
        'holiday'         => ['ident' => 'Holiday_Mode_State', 'command' => 'SetHolidayMode',  'min' => 0,  'max' => 1],
        'emergencyHeater' => ['ident' => 'Force_Heater_State', 'command' => 'SetForceHeater',  'min' => 0,  'max' => 1],
        'dhwTargetC'      => ['ident' => 'DHW_Target_Temp',    'command' => 'SetDHWTemp',      'min' => 40, 'max' => 75]
    ];

    // ---------------------------------------------------------------------------------------
    // Heizungs-Automatik: schaltet NUR den Raumbetrieb (Heizen+WW / Kuehlen+WW / nur WW) ueber
    // SetOperationMode, nie die ganze Waermepumpe. Entscheidungslogik: libs/HeatingAutomation.php
    // ---------------------------------------------------------------------------------------

    private const HEATAUTO_TICK_MS = 900000;
    private const HEATAUTO_RING_KEEP_HOURS = 200;
    private const HEATAUTO_MAX_AGE_SECONDS = 10800;
    private const HEATAUTO_MAX_TRIES = 3;
    private const HEATAUTO_MODE_OFF = 3;
    private const HEATAUTO_MODE_COOLING = 5;

    /** API-Feld => [Property, Typ, Minimum, Maximum]; gemeinsame Quelle fuer Get-/SetHeatingAutomation. */
    private const HEATAUTO_CONFIG = [
        'heatMode'                 => ['HeatAutoHeatMode', 'int', 4, 6],
        'outsideSourceVariableID'  => ['HeatAutoOutsideSource', 'int', 0, null],
        'onBelowC'                 => ['HeatAutoOnBelowC', 'float', -10, 30],
        'offAboveC'                => ['HeatAutoOffAboveC', 'float', -5, 35],
        'frostC'                   => ['HeatAutoFrostC', 'float', -20, 15],
        'meanShortHours'           => ['HeatAutoMeanShortHours', 'int', 6, 72],
        'meanLongHours'            => ['HeatAutoMeanLongHours', 'int', 12, 168],
        'minHoldHours'             => ['HeatAutoMinHoldHours', 'int', 1, 72],
        'roomMinC'                 => ['HeatAutoRoomMinC', 'float', 10, 30],
        'roomHystK'                => ['HeatAutoRoomHystK', 'float', 0.5, 5],
        'useRoom'                  => ['HeatAutoUseRoom', 'bool', null, null],
        'roomDelayHours'           => ['HeatAutoRoomDelayHours', 'float', 0, 12],
        'excludeWords'             => ['HeatAutoExcludeWords', 'string', null, null],
        'priceBlockEnabled'        => ['HeatAutoPriceBlock', 'bool', null, null],
        'priceBlockMaxHours'       => ['HeatAutoPriceBlockMaxHours', 'int', 1, 4],
        'priceBlocksPerDay'        => ['HeatAutoPriceBlocksPerDay', 'int', 1, 3],
        'priceMinSpreadCt'         => ['HeatAutoPriceMinSpreadCt', 'float', 1, 30],
        'priceRoomReserveK'        => ['HeatAutoPriceRoomReserveK', 'float', 0, 5],
        'priceVariableID'          => ['HeatAutoPriceVariable', 'int', 0, null],
        'batteryCapacityKwh'       => ['HeatAutoBatteryCapacityKwh', 'float', 0, 200],
        'batteryReservePct'        => ['HeatAutoBatteryReservePct', 'float', 0, 90],
        'batterySocVariableID'     => ['HeatAutoBatterySocVariable', 'int', 0, null],
        'batteryLoadKw'            => ['HeatAutoBatteryLoadKw', 'float', 0.2, 10],
        'coolingEnabled'           => ['HeatAutoCooling', 'bool', null, null],
        'coolRoomOnC'              => ['HeatAutoCoolRoomOnC', 'float', 20, 32],
        'coolOutsideMinC'          => ['HeatAutoCoolOutsideMinC', 'float', 10, 35],
        'dewPointVariableID'       => ['HeatAutoDewVariable', 'int', 0, null],
        'dewMarginK'               => ['HeatAutoDewMarginK', 'float', 0.5, 10]
    ];

    private function registerHeatingAutomation()
    {
        $this->RegisterPropertyBoolean('HeatAutoEnabled', false);
        $this->RegisterPropertyInteger('HeatAutoHeatMode', 4);
        $this->RegisterPropertyInteger('HeatAutoOutsideSource', 0);
        $this->RegisterPropertyFloat('HeatAutoOnBelowC', 12);
        $this->RegisterPropertyFloat('HeatAutoOffAboveC', 16);
        $this->RegisterPropertyFloat('HeatAutoFrostC', 5);
        $this->RegisterPropertyInteger('HeatAutoMeanShortHours', 24);
        $this->RegisterPropertyInteger('HeatAutoMeanLongHours', 48);
        $this->RegisterPropertyInteger('HeatAutoMinHoldHours', 12);
        $this->RegisterPropertyString('HeatAutoRooms', '[]');
        $this->RegisterPropertyFloat('HeatAutoRoomMinC', 19.5);
        $this->RegisterPropertyFloat('HeatAutoRoomHystK', 1.5);
        $this->RegisterPropertyBoolean('HeatAutoUseRoom', true);
        $this->RegisterPropertyFloat('HeatAutoRoomDelayHours', 2);
        $this->RegisterPropertyString('HeatAutoExcludeWords', HeishaHeatingAutomation::DEFAULT_EXCLUDE_WORDS);
        $this->RegisterPropertyBoolean('HeatAutoPriceBlock', false);
        $this->RegisterPropertyInteger('HeatAutoPriceBlockMaxHours', 2);
        $this->RegisterPropertyInteger('HeatAutoPriceBlocksPerDay', 2);
        $this->RegisterPropertyFloat('HeatAutoPriceMinSpreadCt', 5);
        $this->RegisterPropertyFloat('HeatAutoPriceRoomReserveK', 1);
        $this->RegisterPropertyInteger('HeatAutoPriceVariable', 0);
        $this->RegisterPropertyFloat('HeatAutoBatteryCapacityKwh', 0);
        $this->RegisterPropertyFloat('HeatAutoBatteryReservePct', 20);
        $this->RegisterPropertyInteger('HeatAutoBatterySocVariable', 0);
        $this->RegisterPropertyFloat('HeatAutoBatteryLoadKw', 1);
        $this->RegisterPropertyBoolean('HeatAutoCooling', false);
        $this->RegisterPropertyFloat('HeatAutoCoolRoomOnC', 25);
        $this->RegisterPropertyFloat('HeatAutoCoolOutsideMinC', 20);
        $this->RegisterPropertyInteger('HeatAutoDewVariable', 0);
        $this->RegisterPropertyString('HeatAutoHumidityVariables', '[]');
        $this->RegisterPropertyFloat('HeatAutoDewMarginK', 2);

        //Zustand ueberlebt Neustarts: Aussentemperatur-Stuetzstellen, letzter Zustand, Sperrzeit-
        //Bezug, Zaehler fuer die Raumverzoegerung, Sende-Versuche
        $this->RegisterAttributeString('HeatAutoRing', '[]');
        $this->RegisterAttributeInteger('HeatAutoRingSource', -1);
        $this->RegisterAttributeString('HeatAutoState', '');
        $this->RegisterAttributeInteger('HeatAutoSince', 0);
        $this->RegisterAttributeInteger('HeatAutoColdSince', 0);
        $this->RegisterAttributeInteger('HeatAutoWarmSince', 0);
        $this->RegisterAttributeInteger('HeatAutoHotSince', 0);
        $this->RegisterAttributeInteger('HeatAutoTries', 0);
        $this->RegisterAttributeInteger('HeatAutoLastSend', 0);
        $this->RegisterAttributeBoolean('HeatAutoFault', false);
        $this->RegisterAttributeString('HeatAutoLast', '{}');
        $this->RegisterAttributeString('HeatAutoLastError', '');
        $this->RegisterAttributeString('HeatAutoPriceRing', '[]');
        $this->RegisterAttributeInteger('HeatAutoBlockStart', 0);
        $this->RegisterAttributeString('HeatAutoBlockLog', '[]');
        $this->RegisterAttributeString('HeatAutoBatteryCache', '{}');
        $this->RegisterAttributeInteger('HeatAutoPreBlockSince', 0);
        $this->RegisterAttributeString('HeatAutoBlocksDay', '');
        $this->RegisterAttributeInteger('HeatAutoBlocksToday', 0);
        $this->RegisterAttributeString('HeatAutoHumidityCache', '{}');
        $this->RegisterAttributeString('HeatAutoRoomCache', '{}');
        $this->RegisterTimer('HeatAutoTick', 0, 'HEISHA_HeatingAutomationTick($_IPS[\'TARGET\']);');
        $this->RegisterTimer('HeatAutoVerify', 0, 'HEISHA_HeatingAutomationVerify($_IPS[\'TARGET\']);');
    }

    /** Variablen/Timer je nach Option anlegen bzw. entfernen; ein abgeschaltetes Modul stellt den Heizbetrieb wieder her. */
    private function maintainHeatingAutomation()
    {
        $enabled = $this->ReadPropertyBoolean('HeatAutoEnabled');
        $stateVariableID = @$this->GetIDForIdent('HeatAuto_State');

        if (!$enabled) {
            if ($stateVariableID !== false || (string) $this->ReadAttributeString('HeatAutoState') !== '') {
                $this->heatAutoRestore();
            }
            $this->SetTimerInterval('HeatAutoTick', 0);
            $this->SetTimerInterval('HeatAutoVerify', 0);
            $this->MaintainVariable('HeatAuto_Active', $this->Translate('Heating automation'), VARIABLETYPE_BOOLEAN, ['PRESENTATION' => VARIABLE_PRESENTATION_SWITCH], 270, false);
            $this->MaintainVariable('HeatAuto_State', $this->Translate('Heating automation: state'), VARIABLETYPE_INTEGER, [], 271, false);
            $this->MaintainVariable('HeatAuto_Reason', $this->Translate('Heating automation: reason'), VARIABLETYPE_STRING, [], 272, false);
            return;
        }

        $this->MaintainVariable('HeatAuto_Active', $this->Translate('Heating automation'), VARIABLETYPE_BOOLEAN, ['PRESENTATION' => VARIABLE_PRESENTATION_SWITCH], 270, true);
        $this->EnableAction('HeatAuto_Active');
        $options = [];
        foreach ([0 => 'Heating off (hot water only)', 1 => 'Heating', 2 => 'Cooling', 3 => 'Heating blocked (high price)'] as $value => $caption) {
            $options[] = ['Value' => $value, 'Caption' => $this->Translate($caption), 'IconActive' => false, 'IconValue' => '', 'Color' => -1];
        }
        $this->MaintainVariable('HeatAuto_State', $this->Translate('Heating automation: state'), VARIABLETYPE_INTEGER, [
            'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
            'OPTIONS'      => json_encode($options)
        ], 271, true);
        $this->MaintainVariable('HeatAuto_Reason', $this->Translate('Heating automation: reason'), VARIABLETYPE_STRING, [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
        ], 272, true);
        $this->SetTimerInterval('HeatAutoTick', self::HEATAUTO_TICK_MS);
        //Quellen nach jeder Aenderung der Einstellungen neu suchen
        $this->WriteAttributeString('HeatAutoHumidityCache', '{}');
        $this->WriteAttributeString('HeatAutoRoomCache', '{}');
        //Wurde die Aussentemperatur-Quelle geaendert, taugen die alten Stuetzstellen nicht mehr
        if ((int) $this->ReadAttributeInteger('HeatAutoRingSource') !== $this->heatAutoOutsideID()) {
            $this->WriteAttributeString('HeatAutoRing', '[]');
            $this->WriteAttributeInteger('HeatAutoRingSource', $this->heatAutoOutsideID());
        }
    }

    private function heatAutoIsActive(): bool
    {
        $id = @$this->GetIDForIdent('HeatAuto_Active');
        return $id !== false && (bool) GetValue($id);
    }

    private function heatAutoConfig(): array
    {
        return [
            'onBelowC'        => (float) $this->ReadPropertyFloat('HeatAutoOnBelowC'),
            'offAboveC'       => (float) $this->ReadPropertyFloat('HeatAutoOffAboveC'),
            'frostC'          => (float) $this->ReadPropertyFloat('HeatAutoFrostC'),
            'minHoldHours'    => (float) $this->ReadPropertyInteger('HeatAutoMinHoldHours'),
            'roomMinC'        => (float) $this->ReadPropertyFloat('HeatAutoRoomMinC'),
            'roomHystK'       => (float) $this->ReadPropertyFloat('HeatAutoRoomHystK'),
            'roomDelayMin'    => (int) round(((float) $this->ReadPropertyFloat('HeatAutoRoomDelayHours')) * 60),
            //Kuehlen nur im Modus "Heizen+WW": Auto+WW entscheidet die Anlage selbst
            'coolEnabled'     => $this->ReadPropertyBoolean('HeatAutoCooling') && (int) $this->ReadPropertyInteger('HeatAutoHeatMode') === 4,
            'coolRoomOnC'     => (float) $this->ReadPropertyFloat('HeatAutoCoolRoomOnC'),
            'coolOutsideMinC' => (float) $this->ReadPropertyFloat('HeatAutoCoolOutsideMinC'),
            'priceBlock'         => $this->ReadPropertyBoolean('HeatAutoPriceBlock'),
            'priceBlockMaxMin'   => (int) $this->ReadPropertyInteger('HeatAutoPriceBlockMaxHours') * 60,
            'priceBlocksPerDay'  => (int) $this->ReadPropertyInteger('HeatAutoPriceBlocksPerDay'),
            'priceRoomReserveK'  => (float) $this->ReadPropertyFloat('HeatAutoPriceRoomReserveK')
        ];
    }

    private function heatAutoModeFor(string $state): int
    {
        if ($state === 'cooling') {
            return self::HEATAUTO_MODE_COOLING;
        }
        if ($state === 'off' || $state === 'blocked') {
            return self::HEATAUTO_MODE_OFF;
        }
        return (int) $this->ReadPropertyInteger('HeatAutoHeatMode');
    }

    /** Aktuell gemeldete Betriebsart der Anlage (Operating_Mode_State) oder null, wenn unbekannt. */
    private function heatAutoActualMode(): ?int
    {
        $id = $this->idForIdent('Operating_Mode_State');
        if ($id === 0) {
            return null;
        }
        $value = (int) GetValue($id);
        return $value >= 0 ? $value : null;
    }

    /** Die Preis-Sperre schaltet dieselbe Betriebsart wie "aus" (nur Warmwasser) - fuer Vergleiche mit der gemeldeten Betriebsart. */
    private function heatAutoModeState(string $state): string
    {
        return $state === 'blocked' ? 'off' : $state;
    }

    private function heatAutoStateFromMode(?int $mode): string
    {
        if ($mode === self::HEATAUTO_MODE_OFF) {
            return 'off';
        }
        if ($mode === self::HEATAUTO_MODE_COOLING || $mode === 1) {
            return 'cooling';
        }
        return 'heating';
    }

    private function heatAutoReadNumber(int $variableID, float $min, float $max): ?float
    {
        if ($variableID <= 0 || !@IPS_VariableExists($variableID)) {
            return null;
        }
        if (time() - (int) IPS_GetVariable($variableID)['VariableUpdated'] > self::HEATAUTO_MAX_AGE_SECONDS) {
            return null;
        }
        $raw = GetValue($variableID);
        if (!is_numeric($raw)) {
            return null;
        }
        $value = (float) $raw;
        return ($value < $min || $value > $max) ? null : $value;
    }

    private function heatAutoOutsideID(): int
    {
        $own = (int) $this->ReadPropertyInteger('HeatAutoOutsideSource');
        return $own > 0 ? $own : $this->idForIdent('Outside_Temp');
    }

    private function heatAutoOutside(): ?float
    {
        return $this->heatAutoReadNumber($this->heatAutoOutsideID(), -50, 60);
    }

    private const RAUMSTRUKTUR_MODULE_GUID = '{CA700334-0982-F356-0617-6952868137E9}';
    private const HEATAUTO_DISCOVERY_TTL = 21600;

    /** @return string[] Ausschlussworte aus dem Formular (Standardliste, wenn leer) */
    private function heatAutoExcludeWords(): array
    {
        $words = HeishaHeatingAutomation::parseWords((string) $this->ReadPropertyString('HeatAutoExcludeWords'));
        return $words === [] ? HeishaHeatingAutomation::parseWords(HeishaHeatingAutomation::DEFAULT_EXCLUDE_WORDS) : $words;
    }

    private function heatAutoVariableProfile(int $variableID): string
    {
        $variable = IPS_GetVariable($variableID);
        return (string) (($variable['VariableCustomProfile'] ?? '') !== '' ? $variable['VariableCustomProfile'] : ($variable['VariableProfile'] ?? ''));
    }

    private function heatAutoIsNumeric(int $variableID): bool
    {
        return in_array((int) (IPS_GetVariable($variableID)['VariableType'] ?? -1), [VARIABLETYPE_INTEGER, VARIABLETYPE_FLOAT], true);
    }

    /** Aussen-/Bodenfeuchte u. ae. ausschliessen: eigener Name, sonst Namen der uebergeordneten Geraete (bis 3 Ebenen). */
    private function heatAutoLooksOutdoor(int $variableID, string $name): bool
    {
        $words = $this->heatAutoExcludeWords();
        if (HeishaHeatingAutomation::looksOutdoorOrUnheated($name, $words)) {
            return true;
        }
        if (HeishaHeatingAutomation::looksIndoor($name)) {
            return false;
        }
        $parent = $variableID;
        for ($level = 0; $level < 3; $level++) {
            $parent = (int) @IPS_GetParent($parent);
            if ($parent <= 0) {
                break;
            }
            if ($parent === $this->InstanceID) {
                return true;
            }
            if (HeishaHeatingAutomation::looksOutdoorOrUnheated((string) @IPS_GetName($parent), $words)) {
                return true;
            }
        }
        return false;
    }

    /** @return int[] Variablen mit Feuchte-Charakter (Profil ~Humidity* oder Name), ohne Aussen/Boden; 6 h gecacht */
    private function heatAutoDiscoverHumidity(): array
    {
        $cache = json_decode((string) $this->ReadAttributeString('HeatAutoHumidityCache'), true);
        if (is_array($cache) && isset($cache['at']) && time() - (int) $cache['at'] < self::HEATAUTO_DISCOVERY_TTL) {
            return array_map('intval', (array) ($cache['ids'] ?? []));
        }
        $ids = [];
        foreach (@IPS_GetVariableList() ?: [] as $id) {
            if (!$this->heatAutoIsNumeric($id)) {
                continue;
            }
            $name = (string) IPS_GetName($id);
            if (!HeishaHeatingAutomation::looksLikeHumidity($name, $this->heatAutoVariableProfile($id)) || $this->heatAutoLooksOutdoor($id, $name)) {
                continue;
            }
            $ids[] = (int) $id;
        }
        $this->WriteAttributeString('HeatAutoHumidityCache', json_encode(['at' => time(), 'ids' => $ids]));
        return $ids;
    }

    /** @return int[] ein Temperaturfuehler je Raum der Raumstruktur (Wandfuehler vor Thermostat), ohne Aussen-/unbeheizte Raeume; 6 h gecacht */
    private function heatAutoDiscoverRooms(): array
    {
        $cache = json_decode((string) $this->ReadAttributeString('HeatAutoRoomCache'), true);
        if (is_array($cache) && isset($cache['at']) && time() - (int) $cache['at'] < self::HEATAUTO_DISCOVERY_TTL) {
            return array_map('intval', (array) ($cache['ids'] ?? []));
        }
        $words = $this->heatAutoExcludeWords();
        $function = function_exists('RAUM_GetStructure') ? 'RAUM_GetStructure' : (function_exists('STRUKT_GetStructure') ? 'STRUKT_GetStructure' : '');
        $ids = [];
        if ($function !== '') {
            foreach (@IPS_GetInstanceListByModuleID(self::RAUMSTRUKTUR_MODULE_GUID) ?: [] as $structureID) {
                $structure = json_decode((string) @$function($structureID), true);
                foreach (is_array($structure) ? ($structure['rooms'] ?? []) : [] as $room) {
                    if (HeishaHeatingAutomation::looksOutdoorOrUnheated((string) ($room['label'] ?? ''), $words)) {
                        continue;
                    }
                    $best = null;
                    foreach ((array) ($room['deviceInstanceIDs'] ?? []) as $deviceID) {
                        foreach (@IPS_GetChildrenIDs((int) $deviceID) ?: [] as $childID) {
                            if (!@IPS_VariableExists($childID) || !$this->heatAutoIsNumeric($childID)) {
                                continue;
                            }
                            $rank = HeishaHeatingAutomation::roomTemperatureRank((string) IPS_GetName($childID), $this->heatAutoVariableProfile($childID));
                            if ($rank !== null && ($best === null || $rank < $best[0])) {
                                $best = [$rank, (int) $childID];
                            }
                        }
                    }
                    if ($best !== null) {
                        $ids[] = $best[1];
                    }
                }
            }
        }
        $this->WriteAttributeString('HeatAutoRoomCache', json_encode(['at' => time(), 'ids' => $ids]));
        return $ids;
    }

    /**
     * Raumquelle: eigene Auswahl > automatisch aus der Raumstruktur (mind. 3 Raeume mit gueltigem Wert,
     * Median statt Mittelwert gegen Ausreisser) > keine. mode: own|auto|none|off
     *
     * @return array{mode: string, ids: int[]}
     */
    private function heatAutoRoomSource(): array
    {
        if (!$this->ReadPropertyBoolean('HeatAutoUseRoom')) {
            return ['mode' => 'off', 'ids' => []];
        }
        $own = $this->heatAutoRoomIDs();
        if ($own !== []) {
            return ['mode' => 'own', 'ids' => $own];
        }
        $auto = $this->heatAutoDiscoverRooms();
        $valid = array_filter($auto, fn ($id) => $this->heatAutoReadNumber((int) $id, 5, 45) !== null);
        if (count($valid) >= HeishaHeatingAutomation::AUTO_ROOMS_MIN) {
            return ['mode' => 'auto', 'ids' => array_values($auto)];
        }
        return ['mode' => 'none', 'ids' => []];
    }

    /** @return array{mean: ?float, min: ?float, max: ?float, count: int, mode: string} */
    private function heatAutoRooms(): array
    {
        $source = $this->heatAutoRoomSource();
        $values = [];
        foreach ($source['ids'] as $id) {
            $value = $this->heatAutoReadNumber((int) $id, 5, 45);
            if ($value !== null) {
                $values[] = $value;
            }
        }
        if ($values === []) {
            return ['mean' => null, 'min' => null, 'max' => null, 'count' => 0, 'mode' => $source['mode']];
        }
        //Automatisch gefundene Raeume: Median (ein Fuehler neben einer Waermequelle darf nicht entscheiden)
        $mean = $source['mode'] === 'auto' ? HeishaHeatingAutomation::median($values) : array_sum($values) / count($values);
        return ['mean' => $mean, 'min' => min($values), 'max' => max($values), 'count' => count($values), 'mode' => $source['mode']];
    }

    /**
     * Feuchtequelle: Taupunkt-Variable > eigene Feuchte-Liste > automatisch gefundene Feuchtesensoren > keine.
     *
     * @return array{mode: string, ids: int[]}
     */
    private function heatAutoHumiditySource(): array
    {
        $dewID = (int) $this->ReadPropertyInteger('HeatAutoDewVariable');
        if ($dewID > 0) {
            return ['mode' => 'own', 'ids' => [$dewID], 'isDewPoint' => true];
        }
        $own = $this->heatAutoListIDs('HeatAutoHumidityVariables');
        if ($own !== []) {
            return ['mode' => 'own', 'ids' => $own, 'isDewPoint' => false];
        }
        $auto = $this->heatAutoDiscoverHumidity();
        return $auto === [] ? ['mode' => 'none', 'ids' => [], 'isDewPoint' => false] : ['mode' => 'auto', 'ids' => $auto, 'isDewPoint' => false];
    }

    /** Hoechste gueltige Feuchte aller Quellen (konservativ) bzw. der Taupunkt der Taupunkt-Variable. */
    private function heatAutoHumidity(?float $roomMaxC): array
    {
        $source = $this->heatAutoHumiditySource();
        $result = ['mode' => $source['mode'], 'count' => 0, 'rh' => null, 'dewC' => null];
        if ($source['isDewPoint']) {
            $dew = $this->heatAutoReadNumber($source['ids'][0], -40, 40);
            return ['count' => $dew === null ? 0 : 1, 'dewC' => $dew] + $result;
        }
        $values = [];
        foreach ($source['ids'] as $id) {
            $value = $this->heatAutoReadNumber((int) $id, 1, 100);
            if ($value !== null) {
                $values[] = $value;
            }
        }
        if ($values === []) {
            return $result;
        }
        $rh = max($values);
        //Ohne bekannte Raumtemperatur bewusst hoch angenommen (hoeherer Taupunkt = sicherer)
        $temperature = $roomMaxC ?? HeishaHeatingAutomation::DEW_ASSUMED_ROOM_C;
        return ['count' => count($values), 'rh' => $rh, 'dewC' => HeishaHeatingAutomation::dewPoint($temperature, $rh)] + $result;
    }

    /** Beim ersten Start die Stuetzstellen aus dem Archiv vorbefuellen (falls die Quelle geloggt wird). */
    private function heatAutoSeedFromArchive(int $now): array
    {
        $variableID = $this->heatAutoOutsideID();
        $archives = @IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
        if ($variableID <= 0 || !is_array($archives) || $archives === [] || !function_exists('AC_GetAggregatedValues')) {
            return [];
        }
        try {
            if (!AC_GetLoggingStatus($archives[0], $variableID)) {
                return [];
            }
            $from = $now - (int) ($this->ReadPropertyInteger('HeatAutoMeanLongHours') * 3600);
            $rows = AC_GetAggregatedValues($archives[0], $variableID, 0, $from, $now, 0);
        } catch (Throwable $e) {
            return [];
        }
        $samples = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (isset($row['TimeStamp'], $row['Avg']) && is_numeric($row['Avg'])) {
                $samples[] = [(int) $row['TimeStamp'] + 1800, round((float) $row['Avg'], 2)];
            }
        }
        return HeishaHeatingAutomation::trim($samples, $now, self::HEATAUTO_RING_KEEP_HOURS);
    }

    private function heatAutoSample(int $now)
    {
        $ring = json_decode((string) $this->ReadAttributeString('HeatAutoRing'), true);
        $ring = is_array($ring) ? $ring : [];
        if ($ring === []) {
            $ring = $this->heatAutoSeedFromArchive($now);
        }
        $value = $this->heatAutoOutside();
        $last = $ring === [] ? 0 : (int) end($ring)[0];
        if ($value !== null && $now - $last >= 300) {
            $ring[] = [$now, round($value, 2)];
        }
        $ring = HeishaHeatingAutomation::trim($ring, $now, self::HEATAUTO_RING_KEEP_HOURS);
        $this->WriteAttributeString('HeatAutoRing', json_encode($ring));
    }

    /** Taupunkt der Raumluft und Freigabe: tiefster Kuehl-Sollwert muss ueber Taupunkt + Sicherheitsabstand liegen. */
    private function heatAutoDew(?float $roomMaxC, string $state): array
    {
        $humidity = $this->heatAutoHumidity($roomMaxC);
        $dew = $humidity['dewC'];
        if ($dew === null) {
            return ['dewC' => null, 'ok' => null, 'rh' => $humidity['rh'], 'mode' => $humidity['mode'], 'count' => $humidity['count']];
        }
        $lowest = null;
        foreach (['z1' => 'Z1', 'z2' => 'Z2'] as $zone => $prefix) {
            if (!$this->isCurveZoneActive($zone)) {
                continue;
            }
            $curve = $this->readCurve($prefix, 'Cool');
            if ($curve !== null) {
                $zoneLow = min($curve['targetHighC'], $curve['targetLowC']);
                $lowest = $lowest === null ? $zoneLow : min($lowest, $zoneLow);
            }
        }
        $info = ['rh' => $humidity['rh'], 'mode' => $humidity['mode'], 'count' => $humidity['count']];
        if ($lowest === null) {
            return ['dewC' => $dew, 'ok' => null] + $info;
        }
        $margin = (float) $this->ReadPropertyFloat('HeatAutoDewMarginK');
        $ok = $lowest >= $dew + $margin;
        if ($ok && $state === 'cooling') {
            $outletID = $this->idForIdent('Main_Outlet_Temp');
            if ($outletID !== 0 && (float) GetValue($outletID) < $dew + $margin) {
                $ok = false;
            }
        }
        return ['dewC' => $dew, 'ok' => $ok] + $info;
    }

    private static function heatAutoMinutes(int $since, int $now): int
    {
        return $since > 0 ? intdiv(max(0, $now - $since), 60) : 0;
    }

    // ---------------------------------------------------------------------------------------
    // Wochen-Zeitplan fuer Verschiebung (ECO/Normal/Komfort) und Fluesterstufe. Die Anlage kann keinen solchen Plan
    // aufnehmen, das Modul fuehrt ihn aus. Pruefung und Sollwerte: libs/Schedule.php
    // ---------------------------------------------------------------------------------------

    private const SCHED_TICK_MS = 60000;
    private const SCHED_MAX_TRIES = 3;

    private function registerSchedule()
    {
        $this->RegisterPropertyBoolean('SchedEnabled', false);
        $this->RegisterPropertyString('SchedData', json_encode(HeishaSchedule::emptyPlan()));
        //Laufzeitzustand je Kanal: letzter Sollwert, offener Befehl, Versuche, Status
        $this->RegisterAttributeString('SchedRuntime', '{}');
        $this->RegisterAttributeString('SchedLastError', '');
        $this->RegisterTimer('SchedTick', 0, 'HEISHA_ScheduleTick($_IPS[\'TARGET\']);');
    }

    private function maintainSchedule()
    {
        $enabled = $this->ReadPropertyBoolean('SchedEnabled');
        if (!$enabled) {
            $this->SetTimerInterval('SchedTick', 0);
            $this->MaintainVariable('Sched_Active', $this->Translate('Time schedule'), VARIABLETYPE_BOOLEAN, ['PRESENTATION' => VARIABLE_PRESENTATION_SWITCH], 275, false);
            $this->MaintainVariable('Sched_Status', $this->Translate('Time schedule: status'), VARIABLETYPE_STRING, [], 276, false);
            return;
        }
        $this->MaintainVariable('Sched_Active', $this->Translate('Time schedule'), VARIABLETYPE_BOOLEAN, ['PRESENTATION' => VARIABLE_PRESENTATION_SWITCH], 275, true);
        $this->EnableAction('Sched_Active');
        $this->MaintainVariable('Sched_Status', $this->Translate('Time schedule: status'), VARIABLETYPE_STRING, ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION], 276, true);
        $this->SetTimerInterval('SchedTick', self::SCHED_TICK_MS);
    }

    private function scheduleIsActive(): bool
    {
        $id = @$this->GetIDForIdent('Sched_Active');
        return $id !== false && (bool) GetValue($id);
    }

    /** Gespeicherter Plan, fehlende Kanaele/Felder aufgefuellt. */
    private function schedulePlan(): array
    {
        $stored = json_decode((string) $this->ReadPropertyString('SchedData'), true);
        $plan = HeishaSchedule::emptyPlan();
        foreach (is_array($stored) ? ($stored['channels'] ?? []) : [] as $key => $channel) {
            if (isset($plan['channels'][$key]) && is_array($channel)) {
                [$normalized] = HeishaSchedule::validateChannel((string) $key, $channel);
                if ($normalized !== null) {
                    $plan['channels'][$key] = $normalized + $plan['channels'][$key];
                }
            }
        }
        return $plan;
    }

    /** Verfuegbarkeit, Istwert und Befehl eines Kanals. */
    private function scheduleChannelInfo(string $key): array
    {
        $definition = HeishaSchedule::CHANNELS[$key];
        if ($definition['kind'] === 'level') {
            $id = $this->idForIdent('Quiet_Mode_Level');
            $value = $id === 0 ? null : (int) GetValue($id);
            return ['available' => $id !== 0, 'actual' => ($value !== null && $value >= 0) ? $value : null, 'command' => 'SetQuietMode'];
        }
        $prefix = $definition['zone'] === 'z1' ? 'Z1' : 'Z2';
        $modeName = $definition['mode'] === 'heat' ? 'Heat' : 'Cool';
        $shift = $this->readShift($prefix, $modeName);
        $available = $this->isCurveZoneActive($definition['zone']) && $shift['mode'] === 'shift';
        return ['available' => $available, 'actual' => $available ? $shift['valueC'] : null, 'command' => 'Set' . $prefix . $modeName . 'RequestTemperature'];
    }

    private function scheduleCanSend(): bool
    {
        $reachableID = $this->idForIdent('Reachable');
        return (string) $this->ReadPropertyString('MQTTTopic') !== '' && $this->HasActiveParent() && ($reachableID === 0 || (bool) GetValue($reachableID));
    }

    public function ScheduleTick()
    {
        if (!$this->ReadPropertyBoolean('SchedEnabled')) {
            return;
        }
        $now = time();
        $plan = $this->schedulePlan();
        $runtime = json_decode((string) $this->ReadAttributeString('SchedRuntime'), true);
        $runtime = is_array($runtime) ? $runtime : [];
        $active = $this->scheduleIsActive();
        $sent = false;
        foreach (HeishaSchedule::CHANNELS as $key => $definition) {
            $channel = $plan['channels'][$key];
            $rec = $runtime[$key] ?? ['lastPlanned' => null, 'pending' => false, 'tries' => 0, 'lastSend' => 0, 'state' => 'off'];
            if (!$active || !$channel['enabled']) {
                $runtime[$key] = ['lastPlanned' => null, 'pending' => false, 'tries' => 0, 'lastSend' => 0, 'state' => 'off'];
                continue;
            }
            $info = $this->scheduleChannelInfo($key);
            if (!$info['available']) {
                $runtime[$key] = ['lastPlanned' => null, 'pending' => false, 'tries' => 0, 'lastSend' => 0, 'state' => 'unavailable'];
                continue;
            }
            $planned = HeishaSchedule::plannedValue($channel, $now);
            if ($rec['lastPlanned'] !== $planned) {
                //neue Schaltzeit (oder erster Lauf): Sollwert gilt ab jetzt, ein vorheriger manueller Eingriff endet
                $rec = ['lastPlanned' => $planned, 'pending' => true, 'tries' => 0, 'lastSend' => 0, 'state' => 'pending'];
            }
            if ($rec['pending']) {
                if ($info['actual'] === $planned) {
                    $rec['pending'] = false;
                    $rec['tries'] = 0;
                    $rec['state'] = 'ok';
                } elseif ($rec['tries'] >= self::SCHED_MAX_TRIES) {
                    //die Anlage uebernimmt den Wert nicht: Stoerung bis zur naechsten Schaltzeit, kein Dauerfeuer
                    $rec['pending'] = false;
                    $rec['state'] = 'fault';
                } elseif ($this->scheduleCanSend() && $now - (int) $rec['lastSend'] >= 30) {
                    $this->SendSetCommand($info['command'], (string) $planned);
                    $rec['tries']++;
                    $rec['lastSend'] = $now;
                    $rec['state'] = 'pending';
                    $sent = true;
                    if ($info['actual'] === null) {
                        //Istwert nie empfangen: nicht pruefbar, einmal senden genuegt
                        $rec['pending'] = false;
                        $rec['state'] = 'ok';
                    }
                }
            } elseif ($rec['state'] !== 'fault') {
                $rec['state'] = $info['actual'] === $planned || $info['actual'] === null ? 'ok' : 'manual';
            }
            $runtime[$key] = $rec;
        }
        $this->WriteAttributeString('SchedRuntime', json_encode($runtime));
        if ($sent) {
            //Kontrolle ueber den gemeldeten Wert (die Anlage quittiert nie), im Takt der Pruefung
            $this->SetTimerInterval('SchedTick', 45000);
        } else {
            $this->SetTimerInterval('SchedTick', self::SCHED_TICK_MS);
        }
        $statusID = @$this->GetIDForIdent('Sched_Status');
        if ($statusID !== false) {
            $text = $this->scheduleStatusText($plan, $runtime, $active, $now);
            if (GetValue($statusID) !== $text) {
                $this->SetValue('Sched_Status', $text);
            }
        }
    }

    private function scheduleValueText(string $key, int $value): string
    {
        if (HeishaSchedule::CHANNELS[$key]['kind'] === 'level') {
            return $value === 0 ? $this->Translate('Off') : sprintf($this->Translate('Level %d'), $value);
        }
        if ($value === -5) {
            return $this->Translate('ECO') . ' (-5 K)';
        }
        if ($value === 5) {
            return $this->Translate('Comfort') . ' (+5 K)';
        }
        return $value === 0 ? $this->Translate('Normal') . ' (0 K)' : sprintf('%+d K', $value);
    }

    private function scheduleStatusText(array $plan, array $runtime, bool $active, int $now): string
    {
        if (!$active) {
            return $this->Translate('The time schedule is switched off - the heat pump keeps its current settings.');
        }
        $parts = [];
        foreach (HeishaSchedule::CHANNELS as $key => $definition) {
            if (!$plan['channels'][$key]['enabled']) {
                continue;
            }
            $state = (string) ($runtime[$key]['state'] ?? 'off');
            if ($state === 'unavailable') {
                $parts[] = $this->Translate($definition['label']) . ': ' . $this->Translate('not available');
                continue;
            }
            $text = $this->Translate($definition['label']) . ': ' . $this->scheduleValueText($key, HeishaSchedule::plannedValue($plan['channels'][$key], $now));
            if ($state === 'manual') {
                $text .= ' (' . $this->Translate('changed manually, until the next switching time') . ')';
            } elseif ($state === 'fault') {
                $text .= ' ⚠️ ' . $this->Translate('the heat pump does not take over the value');
            }
            $parts[] = $text;
        }
        return $parts === [] ? $this->Translate('No channel takes part in the time schedule yet.') : implode(' | ', $parts);
    }

    /**
     * Wochen-Zeitplan lesen (Vertrag heatpump, ab contractVersion 1.16). Zeiten sind die lokale Zeit des IPS-Servers.
     * Je Kanal: Verfuegbarkeit, Grenzen, Plan (Abschnitte), Sollwert/Istwert jetzt, naechste Schaltzeit, Status.
     */
    public function GetSchedule(): array
    {
        $now = time();
        $plan = $this->schedulePlan();
        $runtime = json_decode((string) $this->ReadAttributeString('SchedRuntime'), true);
        $runtime = is_array($runtime) ? $runtime : [];
        $enabled = $this->ReadPropertyBoolean('SchedEnabled');
        $active = $enabled && $this->scheduleIsActive();
        $channels = [];
        foreach (HeishaSchedule::CHANNELS as $key => $definition) {
            $channel = $plan['channels'][$key];
            $info = $this->scheduleChannelInfo($key);
            $entry = [
                'label'         => $this->Translate($definition['label']),
                'kind'          => $definition['kind'],
                'available'     => $info['available'],
                'min'           => $definition['min'],
                'max'           => $definition['max'],
                'step'          => 1,
                'unit'          => $definition['kind'] === 'shift' ? 'K' : '',
                'enabled'       => $channel['enabled'],
                'default'       => $channel['default'],
                'segments'      => $channel['segments'],
                'plannedNow'    => $info['available'] ? HeishaSchedule::plannedValue($channel, $now) : null,
                'actualNow'     => $info['actual'],
                'nextChangeAt'  => $info['available'] ? HeishaSchedule::nextChange($channel, $now) : 0,
                'state'         => $active && $channel['enabled'] ? (string) ($runtime[$key]['state'] ?? 'pending') : 'off'
            ];
            if ($definition['kind'] === 'shift') {
                $entry['presets'] = ['eco' => -5, 'normal' => 0, 'comfort' => 5];
            } else {
                $entry['labels'] = [0 => $this->Translate('Off'), 1 => sprintf($this->Translate('Level %d'), 1), 2 => sprintf($this->Translate('Level %d'), 2), 3 => sprintf($this->Translate('Level %d'), 3)];
            }
            $channels[$key] = $entry;
        }
        $rulesID = $this->idForIdent('Stats_RulesActive');
        $quietScheduleID = $this->idForIdent('Quiet_Mode_Schedule');
        $lastError = (string) $this->ReadAttributeString('SchedLastError');
        return [
            'featureEnabled'          => $enabled,
            'enabled'                 => $active,
            'now'                     => $now,
            'granularityMin'          => HeishaSchedule::GRANULARITY_MIN,
            'revision'                => HeishaSchedule::revision($plan),
            'lastError'               => $lastError === '' ? null : $lastError,
            'boardRulesActive'        => $rulesID === 0 ? null : ((int) GetValue($rulesID)) > 0,
            'unitQuietScheduleActive' => $quietScheduleID === 0 ? null : (bool) GetValue($quietScheduleID),
            'channels'                => $channels
        ];
    }

    /**
     * Wochen-Zeitplan aendern. $Json: {"revision": int?, "enabled": bool?, "featureEnabled": bool?, "channels": {kanal:
     * {"enabled": bool?, "default": int?, "segments": [{"day","from","to","value"}]?}}}. Teil-Update je Kanal (segments
     * ersetzt die ganze Liste des Kanals); bei jedem Fehler oder abweichender revision wird nichts geaendert.
     * Rueckgabe: ['ok', 'errors' => [['field','message']], 'schedule' => GetSchedule(), 'revision'].
     */
    public function SetSchedule(string $Json): array
    {
        $errors = [];
        $fail = function (string $field, string $message) use (&$errors) {
            $errors[] = ['field' => $field, 'message' => $this->Translate($message)];
        };
        $input = json_decode($Json, true);
        if (!is_array($input) || $input === []) {
            $fail('', 'Expected a JSON object with at least one field');
            return $this->scheduleSetResult($errors);
        }
        $plan = $this->schedulePlan();
        if (array_key_exists('revision', $input)) {
            if (!is_int($input['revision']) || $input['revision'] !== HeishaSchedule::revision($plan)) {
                $fail('revision', 'The time schedule was changed in the meantime - please reload it');
            }
            unset($input['revision']);
        }
        foreach (['enabled', 'featureEnabled'] as $flag) {
            if (array_key_exists($flag, $input) && !is_bool($input[$flag])) {
                $fail($flag, 'Value must be true or false');
            }
        }
        foreach ((array) ($input['channels'] ?? []) as $key => $data) {
            if (!is_array($data)) {
                $fail((string) $key, 'Value must be an object');
                continue;
            }
            $current = $plan['channels'][$key] ?? null;
            if ($current === null) {
                $fail((string) $key, 'Unknown channel');
                continue;
            }
            [$normalized, $channelErrors] = HeishaSchedule::validateChannel((string) $key, array_merge($current, $data));
            foreach ($channelErrors as $error) {
                $fail($error['field'], $error['message']);
            }
            if ($normalized !== null) {
                $plan['channels'][$key] = $normalized;
            }
        }
        foreach (array_diff(array_keys($input), ['enabled', 'featureEnabled', 'channels']) as $unknown) {
            $fail((string) $unknown, 'Unknown field');
        }
        if ($errors !== []) {
            return $this->scheduleSetResult($errors);
        }

        $changed = false;
        if (json_encode($plan) !== json_encode($this->schedulePlan())) {
            IPS_SetProperty($this->InstanceID, 'SchedData', json_encode($plan));
            $changed = true;
        }
        if (array_key_exists('featureEnabled', $input) && $input['featureEnabled'] !== $this->ReadPropertyBoolean('SchedEnabled')) {
            IPS_SetProperty($this->InstanceID, 'SchedEnabled', $input['featureEnabled']);
            $changed = true;
        }
        if ($changed) {
            IPS_ApplyChanges($this->InstanceID);
        }
        $featureNow = array_key_exists('featureEnabled', $input) ? $input['featureEnabled'] : $this->ReadPropertyBoolean('SchedEnabled');
        if (array_key_exists('enabled', $input) && $featureNow) {
            $this->RequestAction('Sched_Active', $input['enabled']);
        } elseif ($changed && $featureNow && $this->scheduleIsActive()) {
            //geaenderter Plan gilt sofort fuer jetzt
            $this->ScheduleTick();
        }
        return $this->scheduleSetResult([]);
    }

    private function scheduleSetResult(array $errors): array
    {
        $this->WriteAttributeString('SchedLastError', implode('; ', array_map(
            fn ($e) => ($e['field'] !== '' ? $e['field'] . ': ' : '') . $e['message'],
            $errors
        )));
        $schedule = $this->GetSchedule();
        return ['ok' => $errors === [], 'errors' => $errors, 'schedule' => $schedule, 'revision' => $schedule['revision']];
    }

    private const TIBBERGR_MODULE_GUID = '{E92F62F4-88A6-4C6E-9F0D-E76C3B1C9A01}';
    private const BOERSENPREIS_MODULE_GUID = '{11BBF147-16A1-4332-82A3-29BB31154D03}';
    private const INVERTERHUB_MODULE_GUID = '{BBE2C593-1A91-426D-A714-29A9C7E87589}';

    /**
     * Preiskurve aus dem Verbund (Vertrag TIBBERGR_/SPOT_GetPriceCurve: Slots mit start, end, price in ct/kWh):
     * Tibber Grid Rewards (Endkundenpreis) vor Boersenpreis. Nur lesend; die Module cachen selbst.
     *
     * @return array{mode: string, slots: array}
     */
    private function heatAutoPriceCurve(): array
    {
        foreach ([['tibber', 'TIBBERGR_GetPriceCurve', self::TIBBERGR_MODULE_GUID], ['spot', 'SPOT_GetPriceCurve', self::BOERSENPREIS_MODULE_GUID]] as [$mode, $function, $guid]) {
            if (!function_exists($function)) {
                continue;
            }
            foreach (@IPS_GetInstanceListByModuleID($guid) ?: [] as $instanceID) {
                try {
                    $curve = $function($instanceID);
                } catch (Throwable $e) {
                    continue;
                }
                if (is_array($curve) && $curve !== []) {
                    return ['mode' => $mode, 'slots' => $curve];
                }
            }
        }
        return ['mode' => 'none', 'slots' => []];
    }

    /** Eigene Preisvariable (ct/kWh, aktueller Preis): Verlauf fuer die Preislage mitschreiben (7 Tage). */
    private function heatAutoSamplePrice(int $now)
    {
        $variableID = (int) $this->ReadPropertyInteger('HeatAutoPriceVariable');
        if ($variableID <= 0 || !$this->ReadPropertyBoolean('HeatAutoPriceBlock')) {
            return;
        }
        $ring = json_decode((string) $this->ReadAttributeString('HeatAutoPriceRing'), true);
        $ring = is_array($ring) ? $ring : [];
        $value = $this->heatAutoReadNumber($variableID, -1000, 5000);
        $last = $ring === [] ? 0 : (int) end($ring)[0];
        if ($value !== null && $now - $last >= 600) {
            $ring[] = [$now, round($value, 3)];
        }
        $this->WriteAttributeString('HeatAutoPriceRing', json_encode(HeishaHeatingAutomation::trim($ring, $now, 7 * 24)));
    }

    /** @return array{mode: string, expensive: ?bool, price: ?float, threshold: ?float, median: ?float, until: int} */
    private function heatAutoPrice(int $now): array
    {
        $spread = (float) $this->ReadPropertyFloat('HeatAutoPriceMinSpreadCt');
        if ((int) $this->ReadPropertyInteger('HeatAutoPriceVariable') > 0) {
            $ring = json_decode((string) $this->ReadAttributeString('HeatAutoPriceRing'), true);
            $ring = is_array($ring) ? $ring : [];
            //Ohne mindestens einen Tag Verlauf ist die Preislage nicht beurteilbar
            if ($ring === [] || $now - (int) $ring[0][0] < 86400) {
                return ['mode' => 'variable', 'expensive' => null, 'price' => null, 'threshold' => null, 'median' => null, 'until' => 0];
            }
            return ['mode' => 'variable'] + HeishaHeatingAutomation::priceState(HeishaHeatingAutomation::slotsFromHistory($ring), $now, $spread, false);
        }
        $curve = $this->heatAutoPriceCurve();
        if ($curve['mode'] === 'none') {
            return ['mode' => 'none', 'expensive' => null, 'price' => null, 'threshold' => null, 'median' => null, 'until' => 0];
        }
        return ['mode' => $curve['mode']] + HeishaHeatingAutomation::priceState($curve['slots'], $now, $spread);
    }

    private function heatAutoBatterySoc(): ?float
    {
        $own = (int) $this->ReadPropertyInteger('HeatAutoBatterySocVariable');
        if ($own > 0) {
            return $this->heatAutoReadNumber($own, 0, 100);
        }
        if (!function_exists('IHUB_GetFunctions')) {
            return null;
        }
        foreach (@IPS_GetInstanceListByModuleID(self::INVERTERHUB_MODULE_GUID) ?: [] as $instanceID) {
            try {
                $functions = IHUB_GetFunctions($instanceID);
            } catch (Throwable $e) {
                continue;
            }
            $entry = is_array($functions) && isset($functions['socID']) ? $functions : (is_array($functions) ? ($functions[0] ?? []) : []);
            $soc = $this->heatAutoReadNumber((int) ($entry['socID'] ?? 0), 0, 100);
            if ($soc !== null) {
                return $soc;
            }
        }
        return null;
    }

    /** Traegt die Batterie die Waermepumpe waehrend einer Sperre ohnehin? Nur mit eingetragener Kapazitaet. */
    private const EMS_MODULE_GUID = '{31C61A7B-28C4-4F97-9651-1A64B3469E3C}';

    /**
     * Batterie-/Netzbezug-Pruefung fuer die Preis-Sperre. Bevorzugt EMS (EMS_GetGridImportOutlook, Vertrag 1.0, reiner
     * Berater: PV-Prognose, Batterieplan, Preise, Reserve), sonst die einfache Ersatzregel aus Ladestand und eingetragener
     * Kapazitaet. Mit $fresh=false (Formular, Get) gilt ein bis zu 5 Minuten altes Ergebnis, damit eine oft abfragende
     * Oberflaeche EMS nicht belastet. covers = true heisst: kein Netzbezug zu erwarten, also nicht sperren.
     *
     * @return array{covers: bool, source: string, avoidedKwh: ?float, avgPriceCt: ?float}
     */
    private function heatAutoBatteryCheck(bool $fresh = true): array
    {
        $cache = json_decode((string) $this->ReadAttributeString('HeatAutoBatteryCache'), true);
        if (!$fresh && is_array($cache) && isset($cache['at'], $cache['result']) && time() - (int) $cache['at'] < 300) {
            $cached = $cache['result'];
            //nach dem JSON-Rundlauf wieder sauber typisieren
            return [
                'covers'     => (bool) ($cached['covers'] ?? false),
                'source'     => (string) ($cached['source'] ?? 'none'),
                'avoidedKwh' => isset($cached['avoidedKwh']) ? (float) $cached['avoidedKwh'] : null,
                'avgPriceCt' => isset($cached['avgPriceCt']) ? (float) $cached['avgPriceCt'] : null
            ];
        }
        $result = $this->heatAutoBatteryViaEms();
        if ($result === null) {
            $simple = (float) $this->ReadPropertyFloat('HeatAutoBatteryCapacityKwh') > 0;
            $result = ['covers' => $simple && $this->heatAutoBatteryCoversSimple(), 'source' => $simple ? 'simple' : 'none', 'avoidedKwh' => null, 'avgPriceCt' => null];
        }
        $this->WriteAttributeString('HeatAutoBatteryCache', json_encode(['at' => time(), 'result' => $result]));
        return $result;
    }

    /**
     * Fragt EMS, ob in der Sperrdauer ohne die Waermepumpe-Last Netzbezug entstanden waere. Ausgewertet wird das
     * BASELINE-Szenario (Plan inklusive Waermepumpe): nur wenn dort Batterie/PV die Last ohnehin tragen und die Reserve
     * eingehalten bleibt, ist eine Sperre sinnlos. null = EMS nicht erreichbar oder ohne Antwort (dann Ersatzregel).
     */
    private function heatAutoBatteryViaEms(): ?array
    {
        if (!function_exists('EMS_GetGridImportOutlook')) {
            return null;
        }
        $instances = @IPS_GetInstanceListByModuleID(self::EMS_MODULE_GUID);
        if (!is_array($instances) || $instances === []) {
            return null;
        }
        $powerID = $this->idForIdent('Power_Total');
        $measuredW = $powerID === 0 ? 0.0 : max(0.0, (float) GetValue($powerID));
        $loadW = max((float) $this->ReadPropertyFloat('HeatAutoBatteryLoadKw') * 1000.0, $measuredW);
        $hours = min(12, max(1, (int) $this->ReadPropertyInteger('HeatAutoPriceBlockMaxHours')));
        $now = time();
        try {
            //alle fuenf Argumente sind Pflicht (feste Arität des Kernel-Wrappers); konservativ = PV p10
            $outlook = EMS_GetGridImportOutlook($instances[0], $now, $now + $hours * 3600, -$loadW, true);
        } catch (Throwable $e) {
            return null;
        }
        if (!is_array($outlook) || empty($outlook['ok'])) {
            return null;
        }
        $baseline = (array) ($outlook['baseline'] ?? []);
        $price = $baseline['avgPriceCt'] ?? ($outlook['avgPriceCt'] ?? null);
        return [
            'covers'     => !empty($baseline['batteryCoversLoad']) && !empty($baseline['reserveRespected']),
            'source'     => 'ems',
            'avoidedKwh' => isset($outlook['avoidedImportKwh']) && is_numeric($outlook['avoidedImportKwh']) ? (float) $outlook['avoidedImportKwh'] : null,
            'avgPriceCt' => is_numeric($price) ? (float) $price : null
        ];
    }

    private function heatAutoBatteryCoversSimple(): bool
    {
        $capacity = (float) $this->ReadPropertyFloat('HeatAutoBatteryCapacityKwh');
        if ($capacity <= 0) {
            return false;
        }
        $powerID = $this->idForIdent('Power_Total');
        $measuredKw = $powerID === 0 ? 0.0 : max(0.0, (float) GetValue($powerID)) / 1000.0;
        $loadKw = max((float) $this->ReadPropertyFloat('HeatAutoBatteryLoadKw'), $measuredKw);
        $hours = (int) $this->ReadPropertyInteger('HeatAutoPriceBlockMaxHours');
        return HeishaHeatingAutomation::batteryCovers($this->heatAutoBatterySoc(), (float) $this->ReadPropertyFloat('HeatAutoBatteryReservePct'), $capacity, $loadKw * $hours);
    }

    /**
     * Protokoll der Preis-Sperren (14 Tage, hoechstens 60 Fenster) fuer GetHeatingAutomation()['price']['blockWindows']:
     * EMS fuehrt sie als Sondereffekt (affects=load), damit die Lastprognose die gesperrte Last nicht als Normalfall lernt.
     */
    private function heatAutoBlockLog(int $now, ?string $action = null): array
    {
        $log = json_decode((string) $this->ReadAttributeString('HeatAutoBlockLog'), true);
        $log = is_array($log) ? $log : [];
        if ($action === 'open') {
            $log[] = ['from' => $now, 'to' => 0];
        } elseif ($action === 'close') {
            for ($i = count($log) - 1; $i >= 0; $i--) {
                if ((int) $log[$i]['to'] === 0) {
                    $log[$i]['to'] = $now;
                    break;
                }
            }
        }
        $log = array_values(array_filter($log, static fn ($w) => (int) $w['to'] === 0 || (int) $w['to'] >= $now - 14 * 86400));
        $log = array_slice($log, -60);
        if ($action !== null) {
            $this->WriteAttributeString('HeatAutoBlockLog', json_encode($log));
        }
        return $log;
    }

    /** Anzahl der heute begonnenen Preis-Sperren (setzt sich mit dem Tageswechsel zurueck). */
    private function heatAutoBlocksToday(int $now): int
    {
        $day = date('Y-m-d', $now);
        if ((string) $this->ReadAttributeString('HeatAutoBlocksDay') !== $day) {
            $this->WriteAttributeString('HeatAutoBlocksDay', $day);
            $this->WriteAttributeInteger('HeatAutoBlocksToday', 0);
            return 0;
        }
        return (int) $this->ReadAttributeInteger('HeatAutoBlocksToday');
    }

    public function HeatingAutomationTick()
    {
        if (!$this->ReadPropertyBoolean('HeatAutoEnabled')) {
            return;
        }
        $now = time();
        $this->heatAutoSample($now);
        $this->heatAutoSamplePrice($now);
        if (!$this->heatAutoIsActive()) {
            $this->heatAutoPublish('', 'inactive', [], []);
            return;
        }

        $cfg = $this->heatAutoConfig();
        $state = (string) $this->ReadAttributeString('HeatAutoState');
        if (!in_array($state, HeishaHeatingAutomation::STATES, true)) {
            //Erste Auswertung: aktuellen Betriebsmodus uebernehmen, Sperrzeit gilt noch nicht
            $state = $this->heatAutoStateFromMode($this->heatAutoActualMode());
            $this->WriteAttributeString('HeatAutoState', $state);
            $this->WriteAttributeInteger('HeatAutoSince', $now - (int) ($cfg['minHoldHours'] * 3600));
        }
        $since = (int) $this->ReadAttributeInteger('HeatAutoSince');

        //Rangfolge Frostschutz > Nutzer > Automatik: wurde die Betriebsart von aussen geaendert (und
        //es laeuft kein eigener Befehl mehr), gilt der neue Zustand und die Sperrzeit beginnt neu
        $manual = false;
        $actualMode = $this->heatAutoActualMode();
        if ($actualMode !== null && $this->heatAutoStateFromMode($actualMode) !== $this->heatAutoModeState($state)
            && (int) $this->ReadAttributeInteger('HeatAutoTries') === 0 && !$this->ReadAttributeBoolean('HeatAutoFault')) {
            if ($state === 'blocked') {
                $this->heatAutoBlockLog($now, 'close');
            }
            $this->LogMessage(sprintf('Heating automation: operating mode changed from outside (%s -> %s), waiting for the lock time', $state, $this->heatAutoStateFromMode($actualMode)), KL_MESSAGE);
            $state = $this->heatAutoStateFromMode($actualMode);
            $since = $now;
            $manual = true;
            $this->WriteAttributeString('HeatAutoState', $state);
            $this->WriteAttributeInteger('HeatAutoSince', $since);
            $this->WriteAttributeInteger('HeatAutoLastSend', 0);
        }

        $ring = json_decode((string) $this->ReadAttributeString('HeatAutoRing'), true);
        $ring = is_array($ring) ? $ring : [];
        $short = HeishaHeatingAutomation::mean($ring, $now, (float) $this->ReadPropertyInteger('HeatAutoMeanShortHours'));
        $long = HeishaHeatingAutomation::mean($ring, $now, (float) $this->ReadPropertyInteger('HeatAutoMeanLongHours'));

        $rooms = $this->heatAutoRooms();
        $roomMean = $rooms['mean'];
        $roomMin = $rooms['min'];
        $roomCount = $rooms['count'];
        $coldSince = $this->heatAutoCounter('HeatAutoColdSince', $roomMean !== null && $roomMean < $cfg['roomMinC'], $now);
        $warmSince = $this->heatAutoCounter('HeatAutoWarmSince', $roomMean !== null && $roomMean >= $cfg['roomMinC'] + $cfg['roomHystK'], $now);
        $hotSince = $this->heatAutoCounter('HeatAutoHotSince', $roomMean !== null && $roomMean > $cfg['coolRoomOnC'], $now);
        $dew = $cfg['coolEnabled'] ? $this->heatAutoDew($rooms['max'], $state) : ['dewC' => null, 'ok' => null];

        $price = $cfg['priceBlock'] ? $this->heatAutoPrice($now) : ['mode' => 'off', 'expensive' => null, 'price' => null, 'threshold' => null, 'median' => null, 'until' => 0];
        $blocksToday = $this->heatAutoBlocksToday($now);
        $blockStart = (int) $this->ReadAttributeInteger('HeatAutoBlockStart');
        $battery = $cfg['priceBlock'] && $price['expensive'] === true ? $this->heatAutoBatteryCheck(true) : ['covers' => false, 'source' => 'none', 'avoidedKwh' => null, 'avgPriceCt' => null];
        $batteryCovers = $battery['covers'];

        $decision = HeishaHeatingAutomation::decide([
            'state'        => $state,
            'hoursInState' => max(0, $now - $since) / 3600,
            'outsideNowC'  => $this->heatAutoOutside(),
            'meanShortC'   => $short['mean'],
            'meanLongC'    => $long['mean'],
            'coverShort'   => $short['coverage'],
            'coverLong'    => $long['coverage'],
            'roomMeanC'    => $roomMean,
            'roomMinC'     => $roomMin,
            'roomColdMin'  => self::heatAutoMinutes($coldSince, $now),
            'roomWarmMin'  => self::heatAutoMinutes($warmSince, $now),
            'roomHotMin'   => self::heatAutoMinutes($hotSince, $now),
            'dewOk'        => $dew['ok'],
            'priceExpensive' => $price['expensive'],
            'priceBlockedMin' => $state === 'blocked' ? self::heatAutoMinutes($blockStart, $now) : 0,
            'priceBlocksToday' => $blocksToday,
            'batteryCovers' => $batteryCovers,
            'cfg'          => $cfg
        ]);
        //Zahlen fuer die Begruendung der Preis-Sperre
        $blockUntil = $price['until'] > 0 ? $price['until'] : $now + $cfg['priceBlockMaxMin'] * 60;
        if ($decision['reason'] === 'price_block') {
            $blockUntil = min($blockUntil, $blockStart + $cfg['priceBlockMaxMin'] * 60);
        } else {
            $blockUntil = min($blockUntil, $now + $cfg['priceBlockMaxMin'] * 60);
        }
        $decision['params'] += ['price' => $price['price'], 'threshold' => $price['threshold'], 'until' => $blockUntil,
            'avoidedKwh' => $battery['avoidedKwh'], 'avgPriceCt' => $battery['avgPriceCt'], 'batterySource' => $battery['source']];

        //Kuehlen gewuenscht, aber nicht taupunktsicher belegbar: im Status sichtbar machen
        if ($cfg['coolEnabled'] && $decision['state'] === 'off' && $dew['ok'] !== true && $roomMean !== null
            && $roomMean > $cfg['coolRoomOnC'] && self::heatAutoMinutes($hotSince, $now) >= $cfg['roomDelayMin']) {
            $decision['reason'] = 'cool_blocked';
        }

        if ($manual && $decision['state'] === $state && in_array($decision['reason'], ['hold', 'keep_heating', 'keep_off', 'keep_cooling', 'cold', 'warm', 'room_cold', 'room_warm', 'cooling'], true)) {
            $decision['params']['remaining'] = $decision['params']['remaining'] ?? (float) $cfg['minHoldHours'];
            $decision['reason'] = 'manual';
        }
        if ($decision['state'] !== $state) {
            $this->WriteAttributeString('HeatAutoState', $decision['state']);
            $this->WriteAttributeInteger('HeatAutoSince', $now);
            $this->WriteAttributeInteger('HeatAutoTries', 0);
            $this->WriteAttributeInteger('HeatAutoLastSend', 0);
            $this->WriteAttributeBoolean('HeatAutoFault', false);
            $this->LogMessage(sprintf('Heating automation: %s -> %s (%s)', $state, $decision['state'], $decision['reason']), KL_MESSAGE);
            $sinceBefore = $since;
            $since = $now;
            if ($decision['state'] === 'blocked') {
                //Preis-Sperre beginnt: Zeit merken, damit die 12-h-Sperrzeit danach dort weiterlaeuft, wo sie stand
                $this->WriteAttributeInteger('HeatAutoBlockStart', $now);
                $this->WriteAttributeInteger('HeatAutoPreBlockSince', $sinceBefore);
                $this->WriteAttributeInteger('HeatAutoBlocksToday', $blocksToday + 1);
                $this->heatAutoBlockLog($now, 'open');
            } elseif ($state === 'blocked') {
                $this->heatAutoBlockLog($now, 'close');
                $pre = (int) $this->ReadAttributeInteger('HeatAutoPreBlockSince');
                if ($pre > 0) {
                    $since = $pre;
                    $this->WriteAttributeInteger('HeatAutoSince', $pre);
                }
            }
        }
        $this->heatAutoEnforce($decision['state'], $now);
        $this->heatAutoPublish($decision['state'], $decision['reason'], $decision['params'], [
            'outsideNowC' => $this->heatAutoOutside(),
            'meanShortC'  => $short['mean'],
            'meanLongC'   => $long['mean'],
            'coverShort'  => $short['coverage'],
            'coverLong'   => $long['coverage'],
            'roomMeanC'   => $roomMean,
            'roomMinC'    => $roomMin,
            'roomCount'   => $roomCount,
            'roomSource'  => $rooms['mode'],
            'humidityRH'  => $dew['rh'] ?? null,
            'humiditySource' => $dew['mode'] ?? null,
            'humidityCount' => $dew['count'] ?? 0,
            'dewPointC'   => $dew['dewC'],
            'dewOk'       => $dew['ok'],
            'priceMode'   => $price['mode'],
            'priceNowCt'  => $price['price'],
            'priceThresholdCt' => $price['threshold'],
            'priceMedianCt' => $price['median'],
            'priceExpensive' => $price['expensive'],
            'priceUntil'  => $price['until'],
            'blocksToday' => $blocksToday + ($decision['state'] === 'blocked' && $state !== 'blocked' ? 1 : 0),
            'since'       => $since
        ]);
    }

    /** Beginn eines Zustands (z.B. "Raum zu kalt") als Zeitstempel fuehren; 0 = Bedingung nicht erfuellt. */
    private function heatAutoCounter(string $attribute, bool $condition, int $now): int
    {
        if (!$condition) {
            $this->WriteAttributeInteger($attribute, 0);
            return 0;
        }
        $since = (int) $this->ReadAttributeInteger($attribute);
        if ($since === 0) {
            $since = $now;
            $this->WriteAttributeInteger($attribute, $since);
        }
        return $since;
    }

    /** Folgekontrolle ~45 s nach dem Senden: uebernimmt die Anlage die Betriebsart? (Befehle werden nie quittiert.) */
    public function HeatingAutomationVerify()
    {
        $this->SetTimerInterval('HeatAutoVerify', 0);
        if (!$this->ReadPropertyBoolean('HeatAutoEnabled') || !$this->heatAutoIsActive()) {
            return;
        }
        $state = (string) $this->ReadAttributeString('HeatAutoState');
        if (!in_array($state, HeishaHeatingAutomation::STATES, true)) {
            return;
        }
        $this->heatAutoEnforce($state, time());
        $last = json_decode((string) $this->ReadAttributeString('HeatAutoLast'), true);
        $this->heatAutoPublish($state, (string) ($last['reasonCode'] ?? 'keep_' . $state), (array) ($last['params'] ?? []), (array) ($last['values'] ?? []));
    }

    private function heatAutoEnforce(string $state, int $now)
    {
        $desired = $this->heatAutoModeFor($state);
        $actual = $this->heatAutoActualMode();
        //Auch eine Variante desselben Zustands (z.B. Auto+WW statt Heizen+WW) gilt als erfuellt
        if ($actual === $desired || ($actual !== null && $this->heatAutoStateFromMode($actual) === $this->heatAutoModeState($state))) {
            $this->WriteAttributeInteger('HeatAutoTries', 0);
            $this->WriteAttributeBoolean('HeatAutoFault', false);
            return;
        }
        $reachableID = $this->idForIdent('Reachable');
        if ((string) $this->ReadPropertyString('MQTTTopic') === '' || !$this->HasActiveParent()
            || ($reachableID !== 0 && !GetValue($reachableID))) {
            return;
        }
        $lastSend = (int) $this->ReadAttributeInteger('HeatAutoLastSend');
        $tries = (int) $this->ReadAttributeInteger('HeatAutoTries');
        if ($tries >= self::HEATAUTO_MAX_TRIES) {
            $this->WriteAttributeBoolean('HeatAutoFault', true);
            //Nach zwei Stunden einen neuen Versuchszyklus erlauben (Anlage war z.B. nur kurz unerreichbar)
            if ($now - $lastSend >= 7200) {
                $this->WriteAttributeInteger('HeatAutoTries', 0);
            }
            return;
        }
        if ($now - $lastSend < 30) {
            return;
        }
        if ($actual === null && $lastSend >= (int) $this->ReadAttributeInteger('HeatAutoSince') && $lastSend > 0) {
            //Betriebsart unbekannt (nie empfangen): nicht pruefbar, daher je Zustand nur einmal senden
            return;
        }
        $this->SendSetCommand('SetOperationMode', (string) $desired);
        $this->WriteAttributeInteger('HeatAutoLastSend', $now);
        $this->WriteAttributeInteger('HeatAutoTries', $tries + 1);
        $this->SendDebug('HeatAuto', 'SetOperationMode ' . $desired . ' (state ' . $state . ', try ' . ($tries + 1) . ')', 0);
        $this->SetTimerInterval('HeatAutoVerify', 45000);
    }

    /** Beim Ausschalten der Automatik: war der Heizbetrieb abgeschaltet, wird er sicherheitshalber wiederhergestellt. */
    private function heatAutoRestore()
    {
        $state = (string) $this->ReadAttributeString('HeatAutoState');
        if ($state === 'blocked') {
            $this->heatAutoBlockLog(time(), 'close');
        }
        $this->WriteAttributeString('HeatAutoState', '');
        $this->WriteAttributeInteger('HeatAutoTries', 0);
        $this->WriteAttributeBoolean('HeatAutoFault', false);
        $this->SetTimerInterval('HeatAutoVerify', 0);
        if (($state === 'off' || $state === 'cooling' || $state === 'blocked') && (string) $this->ReadPropertyString('MQTTTopic') !== '' && $this->HasActiveParent()) {
            $this->SendSetCommand('SetOperationMode', (string) $this->heatAutoModeFor('heating'));
            $this->LogMessage('Heating automation switched off: heating mode restored', KL_MESSAGE);
        }
    }

    private function heatAutoPublish(string $state, string $reason, array $params, array $values)
    {
        $fault = (bool) $this->ReadAttributeBoolean('HeatAutoFault');
        $text = $this->heatAutoReasonText($fault ? 'fault' : $reason, $params);
        $this->WriteAttributeString('HeatAutoLast', json_encode([
            'reasonCode' => $reason,
            'params'     => $params,
            'values'     => $values,
            'text'       => $text,
            'at'         => time()
        ]));
        $stateID = @$this->GetIDForIdent('HeatAuto_State');
        if ($stateID !== false && $state !== '') {
            $this->SetValue('HeatAuto_State', ['off' => 0, 'heating' => 1, 'cooling' => 2, 'blocked' => 3][$state]);
        }
        $reasonID = @$this->GetIDForIdent('HeatAuto_Reason');
        if ($reasonID !== false && GetValue($reasonID) !== $text) {
            $this->SetValue('HeatAuto_Reason', $text);
        }
    }

    private function heatAutoNumber($value): string
    {
        $separator = $this->Translate('decimal-separator');
        return number_format((float) $value, 1, strlen($separator) === 1 ? $separator : '.', '');
    }

    private function heatAutoReasonText(string $code, array $params): string
    {
        $n = fn ($key) => $this->heatAutoNumber($params[$key] ?? 0);
        $shortHours = (int) $this->ReadPropertyInteger('HeatAutoMeanShortHours');
        $longHours = (int) $this->ReadPropertyInteger('HeatAutoMeanLongHours');
        switch ($code) {
            case 'price_start':
                $text = sprintf($this->Translate('Price block: the current price %s ct/kWh is among the most expensive (from %s ct/kWh) - heating paused until %s at the latest.'), $n('price'), $n('threshold'), date('H:i', (int) ($params['until'] ?? 0)));
                if (($params['batterySource'] ?? '') === 'ems' && isset($params['avoidedKwh'])) {
                    $text .= ' ' . (isset($params['avgPriceCt'])
                        ? sprintf($this->Translate('According to EMS this avoids about %s kWh grid import at around %s ct/kWh.'), $n('avoidedKwh'), $n('avgPriceCt'))
                        : sprintf($this->Translate('According to EMS this avoids about %s kWh grid import.'), $n('avoidedKwh')));
                }
                return $text;
            case 'price_block':
                return sprintf($this->Translate('Price block active until %s at the latest (price %s ct/kWh).'), date('H:i', (int) ($params['until'] ?? 0)), $n('price'));
            case 'price_end':
                return $this->Translate('Price block ended: the price is no longer high - heating on.');
            case 'price_limit':
                return $this->Translate('Price block ended: maximum duration reached - heating on.');
            case 'price_comfort':
                return $this->Translate('Price block ended: the rooms are getting too cold - heating on.');
            case 'price_battery':
                return ($params['batterySource'] ?? '') === 'ems'
                    ? $this->Translate('Price block ended: according to EMS no grid import is expected any more - heating on.')
                    : $this->Translate('Price block ended: the battery now covers the load - heating on.');
            case 'manual':
                return sprintf($this->Translate('Operating mode was changed from outside - the automation waits for the lock time (up to %s h).'), $n('remaining'));
            case 'inactive':
                return $this->Translate('Automation is switched off - the operating mode is left unchanged.');
            case 'fault':
                return $this->Translate('The heat pump does not take over the operating mode - please check the unit and the MQTT connection.');
            case 'no_data':
                return $this->Translate('No current outside temperature available - heating stays on for safety.');
            case 'frost':
                return $this->Translate('Frost protection active - heating on.');
            case 'cold':
                return sprintf($this->Translate('Outside mean %s °C (%d h) is below the switch-on threshold %s °C - heating on.'), $n('meanShort'), $shortHours, $n('on'));
            case 'warm':
                return sprintf($this->Translate('Outside means %s °C (%d h) and %s °C (%d h) are above the switch-off threshold %s °C - heating off.'), $n('meanShort'), $shortHours, $n('meanLong'), $longHours, $n('off'));
            case 'room_cold':
                return sprintf($this->Translate('Rooms too cold (mean %s °C, minimum %s °C) - heating on.'), $n('room'), $n('roomMin'));
            case 'room_warm':
                return sprintf($this->Translate('Rooms warm enough (mean %s °C, minimum %s °C) - heating off.'), $n('room'), $n('roomMin'));
            case 'keep_heating':
                return sprintf($this->Translate('Outside mean %s °C is between the thresholds - heating stays on.'), $n('meanShort'));
            case 'keep_off':
                return sprintf($this->Translate('Outside mean %s °C is between the thresholds - heating stays off.'), $n('meanShort'));
            case 'keep_cooling':
            case 'cooling':
                return $this->Translate('Cooling continues.');
            case 'cool_start':
                return sprintf($this->Translate('Rooms too warm (mean %s °C, limit %s °C), dew point safe - cooling on.'), $n('room'), $n('coolRoom'));
            case 'cool_end':
                return $this->Translate('Cooling ended.');
            case 'cool_blocked':
                return $this->Translate('Rooms are too warm, but cooling is not allowed: dew point or cooling curve unknown, or the cooling target would fall below the dew point.');
            case 'dew_stop':
                return $this->Translate('Cooling stopped: the flow temperature would fall below the dew point.');
            case 'hold':
                $names = ['heating' => 'heating', 'off' => 'heating off', 'cooling' => 'cooling'];
                return sprintf($this->Translate('Lock time active: switching to "%s" is possible in %s h.'), $this->Translate($names[$params['wanted'] ?? 'heating'] ?? 'heating'), $n('remaining'));
        }
        return $code;
    }

    /** Zeilen "Raeume" und "Feuchte" fuer das Konfigurationsformular: was gilt tatsaechlich (🔗 automatisch / ✏️ eigen / ℹ️ nichts). */
    private function heatAutoSourceStatusLines(): array
    {
        if (!$this->ReadPropertyBoolean('HeatAutoEnabled')) {
            return ['', ''];
        }
        $rooms = $this->heatAutoRooms();
        switch ($rooms['mode']) {
            case 'own':
                $roomsLine = sprintf($this->Translate('✏️ Your own room selection: %d rooms with a valid value (mean %s °C).'), $rooms['count'], $this->heatAutoNumber($rooms['mean'] ?? 0));
                break;
            case 'auto':
                $roomsLine = sprintf($this->Translate('🔗 Rooms found automatically via Raumstruktur: %d rooms with a valid value (median %s °C).'), $rooms['count'], $this->heatAutoNumber($rooms['mean'] ?? 0));
                break;
            case 'off':
                $roomsLine = $this->Translate('ℹ️ Rooms are switched off - only the outside temperature decides.');
                break;
            default:
                $roomsLine = $this->Translate('ℹ️ No rooms: without your own selection and without Raumstruktur (at least three rooms with a temperature sensor) only the outside temperature decides.');
        }
        $humidity = $this->heatAutoHumidity($rooms['max']);
        $dew = $humidity['dewC'] === null ? '' : ' → ' . sprintf($this->Translate('dew point %s °C'), $this->heatAutoNumber($humidity['dewC']));
        if ($humidity['count'] === 0) {
            $humidityLine = $humidity['mode'] === 'none'
                ? $this->Translate('ℹ️ No humidity or dew point source found - select your own below, otherwise cooling stays off.')
                : $this->Translate('⚠️ The humidity or dew point sources currently have no valid value - cooling stays off.');
        } elseif ($humidity['mode'] === 'auto') {
            $humidityLine = sprintf($this->Translate('🔗 Humidity found automatically: %d sensors, highest value %s %%'), $humidity['count'], $this->heatAutoNumber($humidity['rh'] ?? 0)) . $dew . '.';
        } else {
            $humidityLine = $humidity['rh'] === null
                ? $this->Translate('✏️ Your own dew point variable') . $dew . '.'
                : sprintf($this->Translate('✏️ Your own humidity variables: highest value %s %%'), $this->heatAutoNumber($humidity['rh'])) . $dew . '.';
        }
        return [$roomsLine, $humidityLine];
    }

    /** Preislage und Sperr-Zaehler fuer GetHeatingAutomation() (immer vorhanden, 'enabled' = Preis-Sperre eingeschaltet). */
    private function heatAutoPriceInfo(): array
    {
        $enabled = $this->ReadPropertyBoolean('HeatAutoPriceBlock');
        $price = $enabled ? $this->heatAutoPrice(time()) : ['mode' => 'off', 'expensive' => null, 'price' => null, 'threshold' => null, 'median' => null, 'until' => 0];
        $battery = $enabled && $price['expensive'] === true ? $this->heatAutoBatteryCheck(false) : ['covers' => false, 'source' => 'none', 'avoidedKwh' => null, 'avgPriceCt' => null];
        $state = (string) $this->ReadAttributeString('HeatAutoState');
        return [
            'enabled'          => $enabled,
            'source'           => $price['mode'],
            'nowCtKwh'         => $price['price'],
            'thresholdCtKwh'   => $price['threshold'],
            'medianCtKwh'      => $price['median'],
            'expensive'        => $price['expensive'],
            'blocksToday'      => (string) $this->ReadAttributeString('HeatAutoBlocksDay') === date('Y-m-d') ? (int) $this->ReadAttributeInteger('HeatAutoBlocksToday') : 0,
            'blockedSince'     => $state === 'blocked' ? (int) $this->ReadAttributeInteger('HeatAutoBlockStart') : 0,
            'blockWindows'     => $this->heatAutoBlockLog(time()),
            'batteryCoversLoad' => $battery['covers'],
            'batterySource'    => $battery['source'],
            'avoidedImportKwh' => $battery['avoidedKwh'],
            'importPriceCt'    => $battery['avgPriceCt']
        ];
    }

    /** Zeile "Preis" fuer das Konfigurationsformular. */
    private function heatAutoPriceStatusLine(): string
    {
        if (!$this->ReadPropertyBoolean('HeatAutoEnabled') || !$this->ReadPropertyBoolean('HeatAutoPriceBlock')) {
            return '';
        }
        $price = $this->heatAutoPrice(time());
        switch ($price['mode']) {
            case 'tibber':
                $source = $this->Translate('🔗 Price curve from Tibber Grid Rewards');
                break;
            case 'spot':
                $source = $this->Translate('🔗 Price curve from the Börsenpreis module');
                break;
            case 'variable':
                $source = $this->Translate('✏️ Your own price variable');
                break;
            default:
                return $this->Translate('ℹ️ No price source found (Tibber Grid Rewards, Börsenpreis) - select your own price variable below, otherwise there is no price block.');
        }
        if ($price['price'] === null) {
            return $source . ': ' . $this->Translate('the price situation cannot be judged yet (too little price data).');
        }
        return $source . sprintf($this->Translate(': now %s ct/kWh, high from %s ct/kWh (median %s ct/kWh) - %s.'),
            $this->heatAutoNumber($price['price']), $this->heatAutoNumber($price['threshold']), $this->heatAutoNumber($price['median']),
            $price['expensive'] ? $this->Translate('expensive now') : $this->Translate('not expensive now'));
    }

    /** Zeile "Batterie" fuer das Konfigurationsformular. */
    private function heatAutoBatteryStatusLine(): string
    {
        if (!$this->ReadPropertyBoolean('HeatAutoEnabled') || !$this->ReadPropertyBoolean('HeatAutoPriceBlock')) {
            return '';
        }
        $check = $this->heatAutoBatteryCheck(false);
        if ($check['source'] === 'ems') {
            return $this->Translate('🔗 Checked via EMS (PV forecast, battery plan, prices): ') . ($check['covers']
                ? $this->Translate('no grid import expected during a block, no price block.')
                : $this->Translate('grid import would arise, the price block applies.'));
        }
        if ((float) $this->ReadPropertyFloat('HeatAutoBatteryCapacityKwh') <= 0) {
            return $this->Translate('ℹ️ No battery capacity entered - the price block applies even if a battery could carry the load.');
        }
        $soc = $this->heatAutoBatterySoc();
        if ($soc === null) {
            return $this->Translate('⚠️ Battery capacity entered, but no state of charge found (InverterHub or own variable) - the price block applies.');
        }
        return sprintf($this->Translate('✅ Battery at %s %%: %s.'), $this->heatAutoNumber($soc), $check['covers'] ? $this->Translate('it carries the heat pump during a block, no price block') : $this->Translate('not enough reserve, the price block applies'));
    }

    /** Zeile fuer das Konfigurationsformular. */
    private function heatAutoStatusLine(): array
    {
        if (!$this->ReadPropertyBoolean('HeatAutoEnabled')) {
            return [$this->Translate('ℹ️ Heating automation is not used. Enable it below.'), -1];
        }
        $last = json_decode((string) $this->ReadAttributeString('HeatAutoLast'), true);
        if (!is_array($last) || !isset($last['text'])) {
            return [$this->Translate('ℹ️ Heating automation enabled, waiting for the first evaluation (every 15 minutes).'), -1];
        }
        $icon = $this->ReadAttributeBoolean('HeatAutoFault') ? '⚠️ ' : '✅ ';
        return [$icon . (string) $last['text'], $this->ReadAttributeBoolean('HeatAutoFault') ? 0xC0392B : 0x2E8B3D];
    }

    /** API-Feld => [Schrittweite, Einheit, Standardwert]; Standardwerte wie bei RegisterProperty in registerHeatingAutomation(). */
    private const HEATAUTO_META = [
        'heatMode'                => [1, '', 4],
        'outsideSourceVariableID' => [1, '', 0],
        'onBelowC'                => [0.5, '°C', 12.0],
        'offAboveC'               => [0.5, '°C', 16.0],
        'frostC'                  => [0.5, '°C', 5.0],
        'meanShortHours'          => [1, 'h', 24],
        'meanLongHours'           => [1, 'h', 48],
        'minHoldHours'            => [1, 'h', 12],
        'roomMinC'                => [0.5, '°C', 19.5],
        'roomHystK'               => [0.5, 'K', 1.5],
        'useRoom'                 => [null, '', true],
        'roomDelayHours'          => [0.25, 'h', 2.0],
        'excludeWords'            => [null, '', HeishaHeatingAutomation::DEFAULT_EXCLUDE_WORDS],
        'priceBlockEnabled'       => [null, '', false],
        'priceBlockMaxHours'      => [1, 'h', 2],
        'priceBlocksPerDay'       => [1, '', 2],
        'priceMinSpreadCt'        => [0.5, 'ct/kWh', 5.0],
        'priceRoomReserveK'       => [0.5, 'K', 1.0],
        'priceVariableID'         => [1, '', 0],
        'batteryCapacityKwh'      => [0.5, 'kWh', 0.0],
        'batteryReservePct'       => [1, '%', 20.0],
        'batterySocVariableID'    => [1, '', 0],
        'batteryLoadKw'           => [0.1, 'kW', 1.0],
        'coolingEnabled'          => [null, '', false],
        'coolRoomOnC'             => [0.5, '°C', 25.0],
        'coolOutsideMinC'         => [0.5, '°C', 20.0],
        'dewPointVariableID'      => [1, '', 0],
        'dewMarginK'              => [0.5, 'K', 2.0]
    ];

    private function heatAutoCurrentConfig(): array
    {
        $config = [];
        foreach (self::HEATAUTO_CONFIG as $field => [$property, $type]) {
            switch ($type) {
                case 'bool':
                    $config[$field] = (bool) $this->ReadPropertyBoolean($property);
                    break;
                case 'float':
                    $config[$field] = (float) $this->ReadPropertyFloat($property);
                    break;
                case 'string':
                    $config[$field] = (string) $this->ReadPropertyString($property);
                    break;
                default:
                    $config[$field] = (int) $this->ReadPropertyInteger($property);
            }
        }
        $config['roomVariableIDs'] = $this->heatAutoRoomIDs();
        $config['humidityVariableIDs'] = $this->heatAutoListIDs('HeatAutoHumidityVariables');
        return $config;
    }

    private function heatAutoRoomIDs(): array
    {
        return $this->heatAutoListIDs('HeatAutoRooms');
    }

    /** Variablen-IDs einer Listen-Eigenschaft (Spalte "Variable"). */
    private function heatAutoListIDs(string $property): array
    {
        $ids = [];
        $rows = json_decode((string) $this->ReadPropertyString($property), true);
        foreach (is_array($rows) ? $rows : [] as $row) {
            if ((int) ($row['Variable'] ?? 0) > 0) {
                $ids[] = (int) $row['Variable'];
            }
        }
        return $ids;
    }

    /** Pruefsumme der geltenden Einstellungen: aendert sich bei JEDER Aenderung, auch ueber das Formular. */
    private function heatAutoRevision(): int
    {
        return crc32(json_encode($this->heatAutoCurrentConfig()));
    }

    /**
     * Zustand und Einstellungen der Heizungs-Automatik (Vertrag heatpump, ab contractVersion 1.15).
     * Immer vorhanden. 'featureEnabled' sagt, ob die Funktion in dieser Instanz verwendet wird,
     * 'enabled' ist der Nutzerschalter der Automatik (Variable HeatAuto_Active), 'state' was sie
     * gerade eingestellt hat (heating|cooling|off|fault, null = laeuft nicht). Zeitstempel sind
     * Unix-Sekunden, 0 = nie bzw. sofort.
     * 'limits' nennt je Einstellung Grenzen, Schrittweite, Einheit und Standardwert (der Aufrufer
     * baut seine Eingabefelder daraus); die Pruefung geschieht trotzdem hier. 'configRevision'
     * gehoert zu SetHeatingAutomation (optimistische Sperre). 'rooms' zeigt die gewaehlten Raeume
     * mit Wert - sie werden im HeishaMon-Formular gepflegt.
     */
    public function GetHeatingAutomation(): array
    {
        $config = $this->heatAutoCurrentConfig();
        $limits = [];
        foreach (self::HEATAUTO_CONFIG as $field => [, $type, $min, $max]) {
            [$step, $unit, $default] = self::HEATAUTO_META[$field];
            $limits[$field] = ['type' => $type, 'min' => $min, 'max' => $max, 'step' => $step, 'unit' => $unit, 'default' => $default];
        }

        $roomSource = $this->heatAutoRoomSource();
        $humiditySource = $this->heatAutoHumiditySource();
        $roomStats = $this->heatAutoRooms();
        $humidity = $this->heatAutoHumidity($roomStats['max']);
        $rooms = [];
        foreach ($roomSource['ids'] as $id) {
            $parent = @IPS_GetParent($id);
            $rooms[] = [
                'id'     => $id,
                'label'  => trim(($parent ? (string) @IPS_GetName($parent) . ' – ' : '') . (string) @IPS_GetName($id)),
                'valueC' => $this->heatAutoReadNumber($id, 5, 45),
                'source' => $roomSource['mode']
            ];
        }
        $sources = [
            'rooms'    => ['mode' => $roomSource['mode'], 'ids' => $roomSource['ids']],
            'humidity' => ['mode' => $humiditySource['mode'], 'ids' => $humiditySource['ids'], 'valueRH' => $humidity['rh'], 'dewPointC' => $humidity['dewC']]
        ];

        $last = json_decode((string) $this->ReadAttributeString('HeatAutoLast'), true);
        $last = is_array($last) ? $last : [];
        $values = (array) ($last['values'] ?? []);
        $enabled = $this->ReadPropertyBoolean('HeatAutoEnabled');
        $state = (string) $this->ReadAttributeString('HeatAutoState');
        $since = (int) $this->ReadAttributeInteger('HeatAutoSince');
        $hold = (float) $this->ReadPropertyInteger('HeatAutoMinHoldHours');
        $holdHours = $state === 'heating' ? $hold : max(min(1.0, $hold), $hold / 4.0);
        $running = $enabled && $this->heatAutoIsActive() && in_array($state, HeishaHeatingAutomation::STATES, true);
        $lastError = (string) $this->ReadAttributeString('HeatAutoLastError');
        return [
            'featureEnabled'      => $enabled,
            'enabled'             => $enabled && $this->heatAutoIsActive(),
            'state'               => $running ? ($this->ReadAttributeBoolean('HeatAutoFault') ? 'fault' : $state) : null,
            'reasonCode'          => $last['reasonCode'] ?? null,
            'reason'              => $last['text'] ?? null,
            'outsideNowC'         => $values['outsideNowC'] ?? null,
            'outsideMeanShortC'   => $values['meanShortC'] ?? null,
            'outsideMeanLongC'    => $values['meanLongC'] ?? null,
            'roomMeanC'           => $values['roomMeanC'] ?? null,
            'roomMinC'            => $values['roomMinC'] ?? null,
            'dewPointC'           => $values['dewPointC'] ?? null,
            'lastSwitchAt'        => $running && $since > 0 ? $since : 0,
            'nextSwitchAllowedAt' => $running && $since > 0 ? ($state === 'blocked' ? $since + (int) $this->ReadPropertyInteger('HeatAutoPriceBlockMaxHours') * 3600 : $since + (int) round($holdHours * 3600)) : 0,
            'evaluatedAt'         => $last['at'] ?? 0,
            'lastError'           => $lastError === '' ? null : $lastError,
            'config'            => $config,
            'configRevision'    => $this->heatAutoRevision(),
            'limits'            => $limits,
            'rooms'             => $rooms,
            'sources'           => $sources,
            'price'             => $this->heatAutoPriceInfo()
        ];
    }

    /**
     * Einstellungen der Heizungs-Automatik aendern. $ConfigJson enthaelt beliebige Felder aus
     * GetHeatingAutomation()['config'] (Teil-Update: nur die genannten aendern sich) und optional
     * "enabled": true|false (Nutzerschalter der Automatik), "featureEnabled": true|false
     * (Funktion in dieser Instanz nutzen) sowie
     * "configRevision" (Wert aus Get; weicht er ab, wurde zwischenzeitlich anderswo geaendert und
     * es passiert nichts). Rueckgabe: ['ok' => bool, 'errors' => [['field','message'],...],
     * 'config' => geltende Einstellungen danach, 'configRevision' => neue Pruefsumme]. Bei jedem
     * Fehler wird NICHTS geaendert (kein Teil-Ergebnis), alle Fehler kommen gesammelt zurueck.
     */
    public function SetHeatingAutomation(string $ConfigJson): array
    {
        $errors = [];
        $fail = function (string $field, string $message) use (&$errors) {
            $errors[] = ['field' => $field, 'message' => $this->Translate($message)];
        };
        $input = json_decode($ConfigJson, true);
        if (!is_array($input) || $input === []) {
            $fail('', 'Expected a JSON object with at least one field');
            return $this->heatAutoSetResult($errors);
        }
        if (array_key_exists('configRevision', $input)) {
            if (!is_int($input['configRevision']) || $input['configRevision'] !== $this->heatAutoRevision()) {
                $fail('configRevision', 'The configuration was changed in the meantime - please reload it');
            }
            unset($input['configRevision']);
        }

        $changes = [];
        foreach ($input as $field => $value) {
            if ($field === 'enabled' || $field === 'featureEnabled') {
                if (!is_bool($value)) {
                    $fail($field, 'Value must be true or false');
                }
                continue;
            }
            if ($field === 'roomVariableIDs' || $field === 'humidityVariableIDs') {
                if (!is_array($value)) {
                    $fail($field, 'Value must be a list of variable IDs');
                    continue;
                }
                $rows = [];
                foreach ($value as $id) {
                    if (!is_int($id) || !@IPS_VariableExists($id)) {
                        $fail($field, 'A variable in the list does not exist');
                        continue 2;
                    }
                    $rows[] = ['Variable' => $id];
                }
                $changes[$field === 'roomVariableIDs' ? 'HeatAutoRooms' : 'HeatAutoHumidityVariables'] = ['string', json_encode($rows)];
                continue;
            }
            $definition = self::HEATAUTO_CONFIG[$field] ?? null;
            if ($definition === null) {
                $fail((string) $field, 'Unknown field');
                continue;
            }
            [$property, $type, $min, $max] = $definition;
            if ($type === 'bool') {
                if (!is_bool($value)) {
                    $fail($field, 'Value must be true or false');
                    continue;
                }
                $changes[$property] = ['bool', $value];
                continue;
            }
            if ($type === 'string') {
                if (!is_string($value) || strlen($value) > 500) {
                    $fail($field, 'Value must be text of at most 500 characters');
                    continue;
                }
                $changes[$property] = ['string', trim($value)];
                continue;
            }
            if (!is_int($value) && !($type === 'float' && is_float($value))) {
                $fail($field, $type === 'float' ? 'Value must be a number' : 'Value must be a whole number');
                continue;
            }
            if (($min !== null && $value < $min) || ($max !== null && $value > $max)) {
                $fail($field, 'Value is outside the allowed range');
                continue;
            }
            if ($field === 'heatMode' && !in_array($value, [4, 6], true)) {
                $fail($field, 'Value must be 4 or 6');
                continue;
            }
            if (substr($field, -10) === 'VariableID' && $value > 0 && !@IPS_VariableExists((int) $value)) {
                $fail($field, 'The variable does not exist');
                continue;
            }
            $changes[$property] = [$type, $type === 'float' ? (float) $value : $value];
        }

        //Zusammenhaengende Grenzen gegen die kuenftigen Gesamtwerte pruefen
        $future = fn ($property, $type) => array_key_exists($property, $changes) ? $changes[$property][1]
            : ($type === 'float' ? $this->ReadPropertyFloat($property) : $this->ReadPropertyInteger($property));
        if ($future('HeatAutoOffAboveC', 'float') < $future('HeatAutoOnBelowC', 'float') + 1) {
            $fail('offAboveC', 'The switch-off threshold must be at least 1 K above the switch-on threshold');
        }
        if ($future('HeatAutoFrostC', 'float') >= $future('HeatAutoOnBelowC', 'float')) {
            $fail('frostC', 'The frost threshold must be below the switch-on threshold');
        }
        if ($future('HeatAutoMeanLongHours', 'int') < $future('HeatAutoMeanShortHours', 'int')) {
            $fail('meanLongHours', 'The long mean must not be shorter than the short mean');
        }
        if ($errors !== []) {
            return $this->heatAutoSetResult($errors);
        }

        $applyChanges = false;
        foreach ($changes as $property => [$type, $value]) {
            switch ($type) {
                case 'bool':
                    $current = $this->ReadPropertyBoolean($property);
                    break;
                case 'float':
                    $current = $this->ReadPropertyFloat($property);
                    break;
                case 'string':
                    $current = $this->ReadPropertyString($property);
                    break;
                default:
                    $current = $this->ReadPropertyInteger($property);
            }
            if ($current !== $value) {
                IPS_SetProperty($this->InstanceID, $property, $value);
                $applyChanges = true;
            }
        }
        if (array_key_exists('featureEnabled', $input) && $input['featureEnabled'] !== $this->ReadPropertyBoolean('HeatAutoEnabled')) {
            IPS_SetProperty($this->InstanceID, 'HeatAutoEnabled', $input['featureEnabled']);
            $applyChanges = true;
        }
        if ($applyChanges) {
            IPS_ApplyChanges($this->InstanceID);
        }
        $featureNow = array_key_exists('featureEnabled', $input) ? $input['featureEnabled'] : $this->ReadPropertyBoolean('HeatAutoEnabled');
        if (array_key_exists('enabled', $input) && $featureNow) {
            $this->RequestAction('HeatAuto_Active', $input['enabled']);
        }
        return $this->heatAutoSetResult([]);
    }

    private function heatAutoSetResult(array $errors): array
    {
        //Grund der letzten verworfenen Aenderung (GetHeatingAutomation()['lastError']), leer nach Erfolg
        $this->WriteAttributeString('HeatAutoLastError', implode('; ', array_map(
            fn ($e) => ($e['field'] !== '' ? $e['field'] . ': ' : '') . $e['message'],
            $errors
        )));
        return [
            'ok'             => $errors === [],
            'errors'         => $errors,
            'config'         => $this->heatAutoCurrentConfig(),
            'configRevision' => $this->heatAutoRevision()
        ];
    }

    /**
     * Wert der Heiz-/Kuehlanforderung: -5..+5 = Verschiebung, ab 20 = direkte Vorlauf-Solltemperatur,
     * alles andere (oder nie empfangen) = unbekannt.
     */
    private function readShift(string $prefix, string $mode): array
    {
        //Grenzen und Voreinstellungen gehoeren zum Vertrag: Aufrufer bauen Regler und Schnellwahl daraus, ohne eigene
        //Zahlen (ECO = -5, Normal = 0, Komfort = +5 - die Verschiebung der Heiz-/Kuehlanforderung in K)
        $limits = ['minC' => self::SHIFT_MIN_C, 'maxC' => self::SHIFT_MAX_C, 'presets' => ['eco' => self::SHIFT_MIN_C, 'normal' => 0, 'comfort' => self::SHIFT_MAX_C]];
        $variableID = $this->idForIdent($prefix . '_' . $mode . '_Request_Temp');
        if ($variableID === 0) {
            return ['mode' => null, 'valueC' => null] + $limits;
        }
        $value = (int) GetValue($variableID);
        if ($value >= self::SHIFT_MIN_C && $value <= self::SHIFT_MAX_C) {
            return ['mode' => 'shift', 'valueC' => $value] + $limits;
        }
        if ($value >= 20) {
            return ['mode' => 'direct', 'valueC' => $value] + $limits;
        }
        return ['mode' => null, 'valueC' => null] + $limits;
    }

    private const SHIFT_MIN_C = -5;
    private const SHIFT_MAX_C = 5;

    /**
     * Heizkurve einer Zone/eines Modus setzen (zone 'z1'|'z2', mode 'heat'|'cool'). Immer alle
     * vier Punkte zusammen. true heisst NUR: Zone vorhanden, Werte plausibel, MQTT-Befehl
     * abgeschickt - HeishaMon quittiert Set-Befehle nicht, ob die Anlage die Kurve uebernommen
     * hat, zeigt erst das spaetere Zuruecklesen (GetHeatingCurve). Ungueltige Eingabe oder nicht
     * vorhandene Zone 2 liefert false, keine Exception. Die Bereichsgrenze -50..100 °C schuetzt
     * nur vor Unsinn/Byte-Ueberlauf in der Firmware (Wert+128), es sind NICHT die echten
     * Panasonic-Grenzen.
     */
    public function SetHeatingCurve(string $Zone, string $Mode, int $TargetHighC, int $TargetLowC, int $OutsideHighC, int $OutsideLowC): bool
    {
        if (!in_array($Zone, ['z1', 'z2'], true) || !in_array($Mode, ['heat', 'cool'], true)) {
            return false;
        }
        foreach ([$TargetHighC, $TargetLowC, $OutsideHighC, $OutsideLowC] as $value) {
            if ($value < -50 || $value > 100) {
                return false;
            }
        }
        if (!$this->isCurveZoneActive($Zone) || (string) $this->ReadPropertyString('MQTTTopic') === '' || !$this->HasActiveParent()) {
            return false;
        }
        $zoneKey = $Zone === 'z1' ? 'zone1' : 'zone2';
        $this->SendSetCommand('SetCurves', json_encode([
            $zoneKey => [$Mode => [
                'target'  => ['high' => $TargetHighC, 'low' => $TargetLowC],
                'outside' => ['high' => $OutsideHighC, 'low' => $OutsideLowC]
            ]]
        ]));
        return true;
    }

    /**
     * Zone 2 gilt als aktiv, wenn Zones_State sie meldet (1 = nur Zone 2, 2 = beide); ohne
     * Zones_State-Meldung, wenn mindestens eine ihrer Kurvenvariablen empfangen wurde. Zone 1
     * ist aktiv, ausser Zones_State meldet ausdruecklich "nur Zone 2".
     */
    private function isCurveZoneActive(string $zone): bool
    {
        $stateID = $this->idForIdent('Zones_State');
        $state = $stateID === 0 ? -1 : (int) GetValue($stateID);
        if ($zone === 'z1') {
            return $state !== 1;
        }
        if ($state === 1 || $state === 2) {
            return true;
        }
        if ($state === 0) {
            return false;
        }
        foreach (['Heat', 'Cool'] as $mode) {
            foreach (['Target_High', 'Target_Low', 'Outside_High', 'Outside_Low'] as $point) {
                if ($this->idForIdent('Z2_' . $mode . '_Curve_' . $point . '_Temp') !== 0) {
                    return true;
                }
            }
        }
        return false;
    }

    private function readCurve(string $prefix, string $mode): ?array
    {
        $curve = [];
        foreach (['targetHighC' => 'Target_High', 'targetLowC' => 'Target_Low', 'outsideHighC' => 'Outside_High', 'outsideLowC' => 'Outside_Low'] as $field => $point) {
            $variableID = $this->idForIdent($prefix . '_' . $mode . '_Curve_' . $point . '_Temp');
            if ($variableID === 0) {
                return null;
            }
            $curve[$field] = (int) GetValue($variableID);
        }
        return $curve;
    }

    private function maintainTopicVariable(string $ident, string $subTopic, array $definition, bool $refreshOnly = false)
    {
        $variableID = @$this->GetIDForIdent($ident);
        //Im Normalfall (Empfang) nur einmal anlegen, nicht bei jeder Nachricht;
        //beim Auffrischen (ApplyChanges) nur bestehende Variablen aktualisieren
        if (($variableID !== false) != $refreshOnly) {
            return;
        }

        $presentation = $this->buildPresentation($definition);

        //Nur schreiben, wenn sich die Darstellung wirklich unterscheidet
        if ($refreshOnly) {
            $current = @IPS_GetVariablePresentation($variableID);
            if (is_array($current) && $this->presentationMatches($current, $presentation)) {
                return;
            }
        }

        $caption = $this->Translate($definition['cap']);
        $position = $this->getPositionMap()[$subTopic] ?? 0;

        switch ($definition['kind']) {
            case 'bool':
                $this->MaintainVariable($ident, $caption, VARIABLETYPE_BOOLEAN, $presentation, $position, true);
                break;
            case 'int':
            case 'enum':
                $this->MaintainVariable($ident, $caption, VARIABLETYPE_INTEGER, $presentation, $position, true);
                break;
            case 'float':
                $this->MaintainVariable($ident, $caption, VARIABLETYPE_FLOAT, $presentation, $position, true);
                break;
            default:
                $this->MaintainVariable($ident, $caption, VARIABLETYPE_STRING, $presentation, $position, true);
                break;
        }

        if (array_key_exists('set', $definition)) {
            $this->EnableAction($ident);
        }
    }

    /**
     * Wie MaintainVariable fuer die Berechnungs-Variablen (Float), aber ohne unnoetige
     * Schreibvorgaenge, wenn die Variable samt Darstellung bereits passt.
     */
    private function maintainCalculationVariable(string $ident, string $caption, array $presentation, int $position, bool $keep)
    {
        $variableID = @$this->GetIDForIdent($ident);
        if (!$keep && $variableID === false) {
            return;
        }
        if ($keep && $variableID !== false) {
            $current = @IPS_GetVariablePresentation($variableID);
            if (is_array($current) && $this->presentationMatches($current, $presentation)) {
                return;
            }
        }
        $this->MaintainVariable($ident, $caption, VARIABLETYPE_FLOAT, $presentation, $position, $keep);
    }

    /**
     * SmartGrid-Modus (HeishaMon-Befehl SetSmartGridMode) - reiner Schreibbefehl ohne
     * Rueckmeldung von der Waermepumpe: setzt digital dieselben vier Betriebsarten, die
     * sonst ueber physische Trockenkontakte am Aussengeraet (natives "SG ready") geschaltet
     * werden. Voraussetzung an der Waermepumpe selbst: Service-Einstellung "Optional PCB" auf
     * Ja. Angezeigter Wert spiegelt nur den zuletzt gesendeten Befehl, keine Bestaetigung.
     */
    /**
     * Vereinheitlichte Betriebsart mit dem VERBUND-Enum des heatpump-Vertrags (SUITE.md):
     * 0=standby, 1=heating, 2=cooling, 3=dhw, 4=heating+dhw, 5=cooling+dhw, -1=unbekannt.
     * Modul-gepflegte, abgeleitete Variable (Praezedenzfall Power_Total) - jedes
     * Waermepumpen-Modul mappt seinen Hersteller-Enum selbst auf diese Werte, damit
     * Konsumenten (z.B. NRGDashboard) keine Herstellersemantik nachbauen muessen.
     */
    private function maintainOperatingModeNormVariable()
    {
        $options = [];
        foreach ([
            -1 => 'Unknown',
            0  => 'Standby',
            1  => 'Heating',
            2  => 'Cooling',
            3  => 'Hot water',
            4  => 'Heating + hot water',
            5  => 'Cooling + hot water'
        ] as $value => $optionCaption) {
            $options[] = [
                'Value'       => $value,
                'Caption'     => $this->Translate($optionCaption),
                'IconActive'  => false,
                'IconValue'   => '',
                'Color'       => -1
            ];
        }
        $presentation = ['PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION, 'OPTIONS' => json_encode($options)];
        $variableID = @$this->GetIDForIdent('Operating_Mode_Norm');
        if ($variableID !== false) {
            $current = @IPS_GetVariablePresentation($variableID);
            if (is_array($current) && $this->presentationMatches($current, $presentation)) {
                return;
            }
        }
        $this->MaintainVariable('Operating_Mode_Norm', $this->Translate('Operating mode (standardized)'), VARIABLETYPE_INTEGER, $presentation, 1, true);
    }

    /**
     * Uebersetzt die Panasonic-Betriebsart (Operating_Mode_State, 0-8) in den Verbund-Enum.
     * Auto-Modi werden auf ihre aktuell aktive Richtung (Heizen/Kuehlen) abgebildet.
     */
    private function normalizeOperatingMode(int $raw): int
    {
        switch ($raw) {
            case 0: //Heat only
            case 2: //Auto (Heat)
                return 1;
            case 1: //Cool only
            case 7: //Auto (Cool)
                return 2;
            case 3: //DHW only
                return 3;
            case 4: //Heat + DHW
            case 6: //Auto (Heat) + DHW
                return 4;
            case 5: //Cool + DHW
            case 8: //Auto (Cool) + DHW
                return 5;
            default:
                return -1;
        }
    }

    private function maintainSmartGridModeVariable(bool $enabled)
    {
        $ident = 'SmartGridMode';
        $variableID = @$this->GetIDForIdent($ident);
        $options = [];
        foreach ([
            0 => 'Normal',
            1 => 'Increased capacity 1',
            2 => 'Heat pump and heater off',
            3 => 'Increased capacity 2'
        ] as $value => $optionCaption) {
            $options[] = [
                'Value'       => $value,
                'Caption'     => $this->Translate($optionCaption),
                'IconActive'  => false,
                'IconValue'   => '',
                'Color'       => -1
            ];
        }
        $presentation = ['PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION, 'OPTIONS' => json_encode($options)];
        if ($enabled && $variableID !== false) {
            $current = @IPS_GetVariablePresentation($variableID);
            if (is_array($current) && $this->presentationMatches($current, $presentation)) {
                return;
            }
        }
        if (!$enabled && $variableID === false) {
            return;
        }
        $this->MaintainVariable($ident, $this->Translate('SmartGrid mode'), VARIABLETYPE_INTEGER, $presentation, 259, $enabled);
        if ($enabled) {
            $this->EnableAction($ident);
        }
    }

    /**
     * Relais der grossen HeishaMon-Platine (gpio/relay/one bzw. .../two) - ebenfalls ein
     * reiner Schreibbefehl ohne Rueckmeldung, unabhaengig vom SmartGrid-Modus.
     */
    private function maintainRelayVariable(string $ident, string $caption, int $position, bool $enabled)
    {
        $variableID = @$this->GetIDForIdent($ident);
        //CAPTION_ON/CAPTION_OFF existieren laut SDK-Doku fuer VARIABLE_PRESENTATION_SWITCH nicht
        //(nur Icon-/Glow-Parameter) - bisher stillschweigend ignoriert, eine kommende strengere
        //Presentation-Validierung wuerde hart abbrechen (Tessie-Fund, per Doku gegengeprueft
        //16.09.2026). Eigene Ein-/Aus-Beschriftung ist mit SWITCH ohnehin nie darstellbar
        //gewesen, kein sichtbarer Verhaltensunterschied durch das Entfernen.
        $presentation = [
            'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH
        ];
        if ($enabled && $variableID !== false) {
            $current = @IPS_GetVariablePresentation($variableID);
            if (is_array($current) && $this->presentationMatches($current, $presentation)) {
                return;
            }
        }
        if (!$enabled && $variableID === false) {
            return;
        }
        $this->MaintainVariable($ident, $caption, VARIABLETYPE_BOOLEAN, $presentation, $position, $enabled);
        if ($enabled) {
            $this->EnableAction($ident);
        }
    }

    /**
     * Vergleicht die Soll-Darstellung mit der vorhandenen. Der Kernel ergaenzt beim Speichern
     * Default-Parameter, daher werden nur die von uns gesetzten Schluessel verglichen.
     */
    private function presentationMatches(array $current, array $target): bool
    {
        foreach ($target as $key => $value) {
            if (!array_key_exists($key, $current)) {
                return false;
            }
            $currentValue = $current[$key];
            //OPTIONS u.ae. sind JSON-Strings, die der Kernel umformatieren kann
            if (is_string($currentValue) && is_string($value)) {
                $decodedCurrent = json_decode($currentValue, true);
                $decodedTarget = json_decode($value, true);
                if ($decodedCurrent !== null && $decodedTarget !== null) {
                    if ($decodedCurrent != $decodedTarget) {
                        return false;
                    }
                    continue;
                }
            }
            if ($currentValue != $value) {
                return false;
            }
        }
        return true;
    }

    private function buildPresentation(array $definition): array
    {
        switch ($definition['kind']) {
            case 'bool':
                if (array_key_exists('set', $definition)) {
                    //CAPTION_ON/CAPTION_OFF existieren bei VARIABLE_PRESENTATION_SWITCH laut
                    //SDK-Doku nicht (Tessie-Fund, gegengeprueft 16.09.2026) - eigene Ein-/Aus-
                    //Beschriftung fuer schaltbare bool-Werte geht nur ueber den nicht-settable
                    //Zweig unten (VALUE_PRESENTATION mit OPTIONS), dafuer gibt es bei SWITCH
                    //keine Entsprechung.
                    return [
                        'PRESENTATION' => VARIABLE_PRESENTATION_SWITCH
                    ];
                }
                return [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'OPTIONS'      => json_encode([
                        [
                            'Value'       => true,
                            'Caption'     => $this->Translate($definition['on'] ?? 'On'),
                            'IconActive'  => false,
                            'IconValue'   => '',
                            'ColorActive' => false,
                            'ColorValue'  => -1
                        ],
                        [
                            'Value'       => false,
                            'Caption'     => $this->Translate($definition['off'] ?? 'Off'),
                            'IconActive'  => false,
                            'IconValue'   => '',
                            'ColorActive' => false,
                            'ColorValue'  => -1
                        ]
                    ])
                ];

            case 'enum':
                //Zustands-Topics koennen laut HeishaMon-Doku -1 (unbekannt) liefern
                $options = [[
                    'Value'       => -1,
                    'Caption'     => $this->Translate('Unknown'),
                    'IconActive'  => false,
                    'IconValue'   => '',
                    'Color'       => -1
                ]];
                foreach ($definition['options'] as $value => $optionCaption) {
                    $options[] = [
                        'Value'       => $value,
                        'Caption'     => $this->Translate($optionCaption),
                        'IconActive'  => false,
                        'IconValue'   => '',
                        'Color'       => -1
                    ];
                }
                return [
                    'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
                    'OPTIONS'      => json_encode($options)
                ];

            case 'int':
            case 'float':
                if (array_key_exists('set', $definition) && array_key_exists('min', $definition)) {
                    return [
                        'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER,
                        'MIN'          => $definition['min'],
                        'MAX'          => $definition['max'],
                        'STEP_SIZE'    => $definition['step'] ?? 1,
                        'SUFFIX'       => $definition['suffix'] ?? '',
                        'DIGITS'       => $definition['digits'] ?? 0
                    ];
                }
                if (array_key_exists('set', $definition)) {
                    //Schaltbare Werte ohne festen Bereich brauchen die Eingabe-Darstellung;
                    //die reine Wertedarstellung kann keine Eingabe und laesst die Konsole
                    //mit "Unexpected presentation when trying to determine minimum" abstuerzen.
                    //DIGITS existiert bei VARIABLE_PRESENTATION_VALUE_INPUT laut SDK-Doku nicht
                    //(nur PREFIX/SUFFIX/MULTILINE) - bisher stillschweigend ignoriert, Tessie-Fund
                    //gegengeprueft 16.09.2026. Betrifft hier ausschliesslich kind=int-Felder ohne
                    //eigenes 'digits' (Default 0), also kein sichtbarer Unterschied durchs Entfernen.
                    return [
                        'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_INPUT,
                        'SUFFIX'       => $definition['suffix'] ?? ''
                    ];
                }
                return [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
                    'SUFFIX'       => $definition['suffix'] ?? '',
                    'DIGITS'       => $definition['digits'] ?? 0
                ];

            default:
                return [
                    'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION
                ];
        }
    }

    private function getDefinitionByIdent(string $ident): ?array
    {
        foreach (HeishaMonTopics::topics() as $subTopic => $definition) {
            if (HeishaMonTopics::identFromTopic($subTopic) == $ident) {
                return $definition;
            }
        }
        return null;
    }

    private function sendMQTT(string $topic, string $payload)
    {
        $data = [
            'DataID'           => '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}',
            'PacketType'       => 3,
            'QualityOfService' => 0,
            'Retain'           => false,
            'Topic'            => $topic,
            'Payload'          => $payload
        ];
        $dataJSON = json_encode($data, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__, $dataJSON, 0);
        $result = @$this->SendDataToParent($dataJSON);
        if ($result === false) {
            $lastError = error_get_last();
            $this->SendDebug(__FUNCTION__ . ' Error', $lastError['message'] ?? 'unknown', 0);
        }
    }
}
