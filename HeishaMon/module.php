<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/HeishaMonTopics.php';

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
    //Einheitliche Formular-Optik (NRG-Stack-Konvention, siehe SUITE.md): Neu-in-Version-Panel
    //je Release hochzaehlen und die Highlights seit dem letzten Store-Stand eintragen.
    private const NEWS_VERSION = '1.28.0';
    private const NEWS_ITEMS = [
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
    ];
    //Der eigene Vorstellungs-Thread, live im Forum bestaetigt (14.09.2026) - vorher stand hier
    //ein Platzhalter auf die allgemeine Modul-Kategorie.
    private const FORUM_URL = 'https://community.symcon.de/t/modul-heishamon-panasonic-aquarea-waermepumpe-in-ip-symcon/143993';

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
        $this->RegisterPropertyBoolean('ArchiveMonitoring', true);
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
        $this->RegisterPropertyInteger('EnergyVariable', 0);
        $this->RegisterPropertyFloat('COPMinPower', 100);

        //Persistente Zwischenstaende der Tagesberechnung (ueberleben einen IPS-Neustart)
        $this->RegisterAttributeString('CurrentDay', '');
        $this->RegisterAttributeFloat('EnergyCounterBase', -1);
        $this->RegisterAttributeFloat('HeatWhToday', 0);
        $this->RegisterAttributeInteger('LastIntegration', 0);

        $this->RegisterTimer('COPUpdate', 0, 'HEISHA_UpdateCOPCalculation($_IPS[\'TARGET\']);');

        $this->RegisterVariableBoolean('Reachable', $this->Translate('Reachable'), $this->reachablePresentation(), 0);
    }

    private function reachablePresentation(): array
    {
        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'OPTIONS'      => json_encode([
                [
                    'Value'       => true,
                    'Caption'     => 'Online',
                    'IconActive'  => false,
                    'Icon'        => '',
                    'Color'       => 65280
                ],
                [
                    'Value'       => false,
                    'Caption'     => 'Offline',
                    'IconActive'  => false,
                    'Icon'        => '',
                    'Color'       => 16711680
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

        //Zeilen in der gespeicherten (per Drag & Drop sortierten) Reihenfolge, Versionsnummer im Doku-Panel
        $this->patchFormElement($form['elements'], 'VariableList', function (&$element) {
            $element['values'] = $this->buildVariableListRows($this->getOrderedTopics(), $this->getSelectionMap());
        });
        $this->patchFormElement($form['elements'], 'OneWireList', function (&$element) {
            $element['values'] = $this->buildOneWireListRows();
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

        //MeterHub-Vorschlag: nur solange noch nichts verknuepft ist (0/0) - wer schon manuell
        //oder per Uebernahme verknuepft hat, soll nicht bei jedem Formularaufruf erneut
        //beworben werden (1:1 nach WPHubs Vorbild, meterHubHeatpumpAssignment()).
        $assignment = $this->meterHubHeatpumpAssignment();
        if ($assignment !== null
            && $this->ReadPropertyInteger('PowerVariable') <= 0
            && $this->ReadPropertyInteger('EnergyVariable') <= 0) {
            $this->patchFormElement($form['elements'], 'MeterHubSuggestion', function (&$element) use ($assignment) {
                $element['caption'] = sprintf($this->Translate('ℹ️ MeterHub found a meter "%s" assigned to function "heat pump".'), $assignment['label']);
                $element['visible'] = true;
            });
            $this->patchFormElement($form['elements'], 'MeterHubAdoptButton', function (&$element) {
                $element['visible'] = true;
            });
        }

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
     * Uebernimmt eine per meterHubHeatpumpAssignment() gefundene Zuordnung in
     * PowerVariable/EnergyVariable - ausschliesslich auf Klick der Formular-Schaltflaeche,
     * nie automatisch im Hintergrund (gleiches Prinzip wie AckNews/DismissForumHint).
     */
    public function AdoptMeterHubAssignment()
    {
        $found = $this->meterHubHeatpumpAssignment();
        if ($found === null) {
            $this->UpdateFormField('MeterHubResult', 'caption', $this->Translate('❌ No MeterHub assignment "heat pump" found (anymore).'));
            $this->UpdateFormField('MeterHubResult', 'visible', true);
            return;
        }
        if ($found['powerID'] > 0) {
            IPS_SetProperty($this->InstanceID, 'PowerVariable', $found['powerID']);
        }
        if ($found['energyID'] > 0) {
            IPS_SetProperty($this->InstanceID, 'EnergyVariable', $found['energyID']);
        }
        IPS_ApplyChanges($this->InstanceID);
        $this->UpdateFormField('PowerVariable', 'value', $found['powerID']);
        $this->UpdateFormField('EnergyVariable', 'value', $found['energyID']);
        $this->UpdateFormField('MeterHubResult', 'caption', sprintf($this->Translate('✅ Adopted from MeterHub "%s".'), $found['label']));
        $this->UpdateFormField('MeterHubResult', 'visible', true);
        $this->UpdateFormField('MeterHubSuggestion', 'visible', false);
        $this->UpdateFormField('MeterHubAdoptButton', 'visible', false);
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
        $library = json_decode(file_get_contents(__DIR__ . '/../library.json'), true);
        return 'v' . ($library['version'] ?? '?');
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
        if ($this->ReadAttributeString('SeenNews') === self::NEWS_VERSION) {
            return null;
        }
        $items = [];
        foreach (self::NEWS_ITEMS as $line) {
            $items[] = ['type' => 'Label', 'caption' => $this->Translate($line)];
        }
        $items[] = [
            'type'    => 'Button',
            'caption' => $this->Translate('Understood - do not show again'),
            'onClick' => 'HEISHA_AckNews($id);'
        ];
        return [
            'type'     => 'ExpansionPanel',
            'name'     => 'NewsPanel',
            'caption'  => $this->Translate('🆕 New in version') . ' ' . self::NEWS_VERSION,
            'expanded' => true,
            'items'    => $items
        ];
    }

    public function AckNews()
    {
        $this->WriteAttributeString('SeenNews', self::NEWS_VERSION);
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

        //Variablen der COP-Berechnung anlegen bzw. entfernen, je nach Konfiguration
        $powerID = $this->ReadPropertyInteger('PowerVariable');
        $energyID = $this->ReadPropertyInteger('EnergyVariable');
        $copPresentation = [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'DIGITS'       => 2
        ];
        $kwhPresentation = [
            'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX'       => ' kWh',
            'DIGITS'       => 2
        ];
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
        $this->maintainCalculationVariable('COP_Measured', $this->Translate('COP (measured)'), $copPresentation, 201, $powerID > 0);
        $this->maintainCalculationVariable('Heat_Energy_Today', $this->Translate('Heat energy today'), $kwhPresentation, 202, $energyID > 0);
        $this->maintainCalculationVariable('Power_Energy_Today', $this->Translate('Energy consumption today'), $kwhPresentation, 203, $energyID > 0);
        $this->maintainCalculationVariable('COP_Today', $this->Translate('Performance factor today'), $copPresentation, 204, $energyID > 0);
        $this->updateTotalPower();

        $this->SetTimerInterval('COPUpdate', $energyID > 0 ? 60000 : 0);

        //Neustart-Watchdog nur mit aktivierter Option und eingetragener Platinen-IP
        $watchdogActive = $this->ReadPropertyBoolean('RebootWatchdog') && trim((string) $this->ReadPropertyString('DeviceIP')) != '';
        $this->SetTimerInterval('RebootWatchdog', $watchdogActive ? 60000 : 0);

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

        //Reachable-Darstellung bestehender Installationen auffrischen (Migration des frueheren
        //falschen Options-Schluessels "ColorValue" -> "Color"; die Variable entsteht in Create(),
        //dort greift die Korrektur fuer Bestandsanlagen nie)
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

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->registerExternalMessages();
                $this->maintainLinkTree();
                break;
            case VM_UPDATE:
                if ($SenderID == $this->ReadPropertyInteger('PowerVariable')) {
                    $this->updateMeasuredCOP(floatval($Data[0]));
                    $this->updateTotalPower();
                } elseif ($SenderID == $this->ReadPropertyInteger('EnergyVariable')) {
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
            $energyID = $this->ReadPropertyInteger('EnergyVariable');
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
        foreach (['PowerVariable', 'EnergyVariable'] as $property) {
            $variableID = $this->ReadPropertyInteger($property);
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
        $sum = 0.0;
        foreach (['Heat_Power_Production', 'Cool_Power_Production', 'DHW_Power_Production'] as $ident) {
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
        $changed = false;
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
                $changed = true;
            }
            $done[] = $ident;
        }
        if ($changed) {
            IPS_ApplyChanges($archiveID);
        }
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
        $sum = 0.0;
        foreach (['Heat_Power_Consumption', 'Cool_Power_Consumption', 'DHW_Power_Consumption'] as $ident) {
            $variableID = @$this->GetIDForIdent($ident);
            if ($variableID !== false) {
                $sum += floatval(GetValue($variableID));
            }
        }
        return $sum;
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
        $energyID = $this->ReadPropertyInteger('EnergyVariable');
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
            'main/DHW_Power_Production', 'main/DHW_Power_Consumption'
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
    private function buildOneWireListRows(): array
    {
        $seen = json_decode((string) $this->ReadAttributeString('SeenOneWire'), true) ?: [];
        $config = $this->getOneWireConfigMap();
        $rows = [];
        foreach ($this->getOrderedOneWireAddresses() as $address) {
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
     *   contractVersion 'Major.Minor' des Vertrags (Suite-Konvention, SUITE.md im EMS-Repo).
     *            Major nur bei Bruch; Kompatibilitaet nur innerhalb derselben Major. Fehlt = '1.0'.
     */
    public function GetFunctions(): array
    {
        $powerID = @$this->GetIDForIdent('Power_Total');
        $energyID = $this->ReadPropertyInteger('EnergyVariable');
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
                'contractVersion'      => '1.12'
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
        $powerID = $this->ReadPropertyInteger('PowerVariable');
        return $powerID > 0 && IPS_VariableExists($powerID);
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
            $this->SetValue('Power_Total', floatval(GetValue((int) $this->ReadPropertyInteger('PowerVariable'))));
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
                'Icon'        => '',
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
                'Icon'        => '',
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
                            'Icon'        => '',
                            'Color'       => -1
                        ],
                        [
                            'Value'       => false,
                            'Caption'     => $this->Translate($definition['off'] ?? 'Off'),
                            'IconActive'  => false,
                            'Icon'        => '',
                            'Color'       => -1
                        ]
                    ])
                ];

            case 'enum':
                //Zustands-Topics koennen laut HeishaMon-Doku -1 (unbekannt) liefern
                $options = [[
                    'Value'       => -1,
                    'Caption'     => $this->Translate('Unknown'),
                    'IconActive'  => false,
                    'Icon'        => '',
                    'Color'       => -1
                ]];
                foreach ($definition['options'] as $value => $optionCaption) {
                    $options[] = [
                        'Value'       => $value,
                        'Caption'     => $this->Translate($optionCaption),
                        'IconActive'  => false,
                        'Icon'        => '',
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
