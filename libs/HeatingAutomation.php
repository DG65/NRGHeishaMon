<?php

declare(strict_types=1);

/**
 * Entscheidungslogik der Heizungs-Automatik (reine Funktionen, keine IPS-Aufrufe - dadurch
 * einzeln testbar). Das Modul sammelt die Messwerte, ruft decide() auf und setzt das Ergebnis
 * um (HeishaMon::heatingAutomationTick()).
 *
 * Zustaende: 'heating' (Heizen+Warmwasser), 'cooling' (Kuehlen+Warmwasser), 'off' (nur
 * Warmwasser). Geschaltet wird NIE die ganze Waermepumpe, nur der Raumbetrieb.
 */
final class HeishaHeatingAutomation
{
    /** Ein Raum unter diesem Wert erzwingt Heizen (Frostschutz, nicht einstellbar). */
    public const ROOM_FROST_C = 10.0;

    /** Kuehlen endet, wenn das Aussen-Mittel um diesen Wert unter die Kuehl-Schwelle faellt. */
    public const COOL_OUTSIDE_HYSTERESIS_K = 2.0;

    public const STATES = ['heating', 'cooling', 'off', 'blocked'];

    /** Ohne Raumfuehler: Preis-Sperre nur, wenn das Aussenmittel mindestens so weit ueber der Frostschwelle liegt (K). */
    public const PRICE_OUTSIDE_RESERVE_K = 5.0;

    /** Mindestabdeckung der Referenz (Stunden), damit eine Preislage beurteilt wird. */
    public const PRICE_MIN_SPAN_HOURS = 12;

    /**
     * Mittelwert der Stuetzstellen [[ts, wert], ...] im Fenster der letzten $hours Stunden.
     * coverage = Anteil des Fensters, den die vorhandenen Stuetzstellen tatsaechlich abdecken
     * (0..1) - kurz nach der Inbetriebnahme ist das lange Mittel noch nicht belastbar.
     *
     * @return array{mean: ?float, coverage: float}
     */
    public static function mean(array $samples, int $now, float $hours): array
    {
        $from = $now - (int) round($hours * 3600);
        $sum = 0.0;
        $count = 0;
        $first = null;
        foreach ($samples as $sample) {
            $ts = (int) $sample[0];
            if ($ts < $from || $ts > $now) {
                continue;
            }
            $sum += (float) $sample[1];
            $count++;
            if ($first === null || $ts < $first) {
                $first = $ts;
            }
        }
        if ($count === 0 || $hours <= 0) {
            return ['mean' => null, 'coverage' => 0.0];
        }
        $coverage = min(1.0, ($now - $first) / ($hours * 3600));
        return ['mean' => $sum / $count, 'coverage' => $coverage];
    }

    /** Mindestanzahl gueltiger Raeume, damit eine AUTOMATISCH gefundene Raumquelle verwendet wird. */
    public const AUTO_ROOMS_MIN = 3;

    /** Annahme fuer die Raumlufttemperatur beim Taupunkt, wenn keine Raumtemperatur bekannt ist (bewusst hoch = sicher). */
    public const DEW_ASSUMED_ROOM_C = 26.0;

    /**
     * Standard-Ausschlussliste (kommagetrennt, ohne Gross-/Kleinschreibung, Teilstring-Treffer): Namen von
     * Variablen, Geraeten oder Raeumen, die auf Aussen-, Boden- oder unbeheizte Bereiche hindeuten. Der Nutzer
     * kann sie im Formular anpassen (z. B. Wintergarten, Werkstatt).
     */
    public const DEFAULT_EXCLUDE_WORDS = 'aussen, outdoor, outside, boden, soil, rasen, garten, garden, regen, carport, garage, schuppen, terrasse, balkon, keller, dachboden, spitzboden, pool, gewaechshaus, wetter';

    private const HUMIDITY_PATTERN = '/(luftfeucht|feuchte|humidity)/iu';

    private const TEMPERATURE_EXCLUDE_PATTERN = '/(soll|ziel|target|set\s?point|offset|ger(ä|ae)te|modul|tau|gef(ü|ue)hlt|vorlauf|r(ü|ue)cklauf|warmwasser|boden|au(ß|ss)en|outdoor|outside|vpd|limit|schutz|wasser|water)/iu';

    /** Ausschlussliste "a, b; c" -> ['a','b','c'] (klein geschrieben, ohne Leereintraege, ss statt ß). */
    public static function parseWords(string $list): array
    {
        $words = [];
        foreach (preg_split('/[,;\n]+/u', $list) ?: [] as $word) {
            $word = self::normalizeText($word);
            if ($word !== '') {
                $words[] = $word;
            }
        }
        return array_values(array_unique($words));
    }

    private static function normalizeText(string $text): string
    {
        $text = mb_strtolower(trim($text));
        return str_replace(['ß', 'ä', 'ö', 'ü'], ['ss', 'ae', 'oe', 'ue'], $text);
    }

    /** true, wenn der Name eines der Ausschlussworte enthaelt (Aussen-, Boden- oder unbeheizter Bereich). */
    public static function looksOutdoorOrUnheated(string $name, ?array $words = null): bool
    {
        $words ??= self::parseWords(self::DEFAULT_EXCLUDE_WORDS);
        $name = self::normalizeText($name);
        foreach ($words as $word) {
            if ($word !== '' && strpos($name, $word) !== false) {
                return true;
            }
        }
        return false;
    }

    /** true, wenn der Name ausdruecklich einen Innenraum nennt (hebt eine Aussen-Einstufung des UEBERGEORDNETEN Geraets auf). */
    public static function looksIndoor(string $name): bool
    {
        return (bool) preg_match('/(innen|indoor|inside)/iu', $name);
    }

    /** Humidity-Kandidat: Standardprofil ~Humidity* ODER Name mit "Luftfeuchte"/"Humidity". */
    public static function looksLikeHumidity(string $name, string $profile): bool
    {
        return stripos($profile, '~Humidity') === 0 || (bool) preg_match(self::HUMIDITY_PATTERN, $name);
    }

    /**
     * Eignung einer Variable als Raumtemperatur: 1 = Wandfuehler/Raumprofil (bevorzugt),
     * 2 = allgemeiner Ist-Wert (z. B. Thermostat am Heizkoerper), null = ungeeignet.
     */
    public static function roomTemperatureRank(string $name, string $profile): ?int
    {
        if (preg_match(self::TEMPERATURE_EXCLUDE_PATTERN, $name)) {
            return null;
        }
        if (stripos($profile, '~Temperature.Room') === 0 || preg_match('/(lufttemperatur|raumtemperatur|room\s?temp|air\s?temp|innentemperatur)/iu', $name)) {
            return 1;
        }
        if (preg_match('/(isttemperatur|temperatur|temperature)/iu', $name) || stripos($profile, '~Temperature') === 0) {
            return 2;
        }
        return null;
    }

    /** Median (robust gegen einzelne Ausreisser, z. B. einen Fuehler neben einer Waermequelle). */
    public static function median(array $values): ?float
    {
        $values = array_values(array_map('floatval', $values));
        if ($values === []) {
            return null;
        }
        sort($values);
        $n = count($values);
        return $n % 2 === 1 ? $values[intdiv($n, 2)] : ($values[$n / 2 - 1] + $values[$n / 2]) / 2.0;
    }

    /**
     * Preislage aus Zeit-Slots [['start'=>ts,'end'=>ts,'price'=>ct/kWh], ...] (Vertrag TIBBERGR_/SPOT_GetPriceCurve).
     * Referenz = alle Slots, die in den naechsten 24 h noch laufen (Kurve) bzw. der gesamte uebergebene Verlauf
     * (Historie einer Preisvariable). "Teuer" ab max(Median + $minSpreadCt, 85. Perzentil), beide nach Dauer
     * gewichtet. Liefert expensive=null, wenn die Referenz kuerzer als PRICE_MIN_SPAN_HOURS ist oder der
     * aktuelle Slot fehlt - dann wird nie gesperrt.
     *
     * @return array{expensive: ?bool, price: ?float, threshold: ?float, median: ?float, until: int}
     */
    public static function priceState(array $slots, int $now, float $minSpreadCt, bool $futureOnly = true): array
    {
        $result = ['expensive' => null, 'price' => null, 'threshold' => null, 'median' => null, 'until' => 0];
        $reference = [];
        $span = 0;
        foreach ($slots as $slot) {
            $start = (int) ($slot['start'] ?? 0);
            $end = (int) ($slot['end'] ?? 0);
            if ($end <= $start || !isset($slot['price']) || !is_numeric($slot['price'])) {
                continue;
            }
            if ($futureOnly && ($end <= $now || $start >= $now + 86400)) {
                continue;
            }
            $reference[] = ['start' => $start, 'end' => $end, 'price' => (float) $slot['price']];
            $span += $end - $start;
        }
        if ($reference === [] || $span < self::PRICE_MIN_SPAN_HOURS * 3600) {
            return $result;
        }
        usort($reference, static fn ($a, $b) => $a['price'] <=> $b['price']);
        $quantile = static function (float $q) use ($reference, $span): float {
            $target = $span * $q;
            $sum = 0;
            foreach ($reference as $slot) {
                $sum += $slot['end'] - $slot['start'];
                if ($sum >= $target) {
                    return $slot['price'];
                }
            }
            return $reference[count($reference) - 1]['price'];
        };
        $median = $quantile(0.5);
        $threshold = max($median + $minSpreadCt, $quantile(0.85));
        $result['median'] = $median;
        $result['threshold'] = $threshold;
        $current = null;
        foreach ($reference as $slot) {
            if ($slot['start'] <= $now && $slot['end'] > $now) {
                $current = $slot;
                break;
            }
        }
        if ($current === null) {
            return $result;
        }
        $result['price'] = $current['price'];
        $result['expensive'] = $current['price'] >= $threshold;
        if ($result['expensive']) {
            //Ende des zusammenhaengenden teuren Bereichs (Slots lueckenlos aneinander)
            $byStart = [];
            foreach ($reference as $slot) {
                $byStart[$slot['start']] = $slot;
            }
            $until = $current['end'];
            while (isset($byStart[$until]) && $byStart[$until]['price'] >= $threshold) {
                $until = $byStart[$until]['end'];
            }
            $result['until'] = $until;
        }
        return $result;
    }

    /** Verlauf einer Preisvariable [[ts, preis], ...] als Slots (jeder Wert gilt bis zum naechsten, letzter 15 min). */
    public static function slotsFromHistory(array $samples): array
    {
        usort($samples, static fn ($a, $b) => (int) $a[0] <=> (int) $b[0]);
        $slots = [];
        $count = count($samples);
        for ($i = 0; $i < $count; $i++) {
            $start = (int) $samples[$i][0];
            $end = $i + 1 < $count ? min((int) $samples[$i + 1][0], $start + 3600) : $start + 900;
            if ($end > $start) {
                $slots[] = ['start' => $start, 'end' => $end, 'price' => (float) $samples[$i][1]];
            }
        }
        return $slots;
    }

    /** Reicht die nutzbare Batterieenergie fuer die erwartete Waermepumpen-Energie? (5 % Wandlungsverlust, 30 % Sicherheit) */
    public static function batteryCovers(?float $socPercent, float $reservePercent, float $capacityKwh, float $expectedKwh): bool
    {
        if ($socPercent === null || $capacityKwh <= 0) {
            return false;
        }
        $usable = max(0.0, ($socPercent - $reservePercent) / 100.0) * $capacityKwh * 0.95;
        return $usable >= $expectedKwh * 1.3;
    }

    /** Alte Stuetzstellen verwerfen (aelter als $keepHours) und aufsteigend halten. */
    public static function trim(array $samples, int $now, float $keepHours): array
    {
        $from = $now - (int) round($keepHours * 3600);
        $kept = array_values(array_filter($samples, static fn ($s) => (int) $s[0] >= $from && (int) $s[0] <= $now));
        usort($kept, static fn ($a, $b) => (int) $a[0] <=> (int) $b[0]);
        return $kept;
    }

    /** Taupunkt in °C aus Temperatur und relativer Feuchte (Magnus-Formel). */
    public static function dewPoint(float $tempC, float $humidityPercent): ?float
    {
        if ($humidityPercent <= 0 || $humidityPercent > 100) {
            return null;
        }
        $a = 17.62;
        $b = 243.12;
        $gamma = log($humidityPercent / 100.0) + ($a * $tempC) / ($b + $tempC);
        return ($b * $gamma) / ($a - $gamma);
    }

    /**
     * Entscheidung. Eingabe:
     *  state            aktueller Zustand ('heating'|'cooling'|'off')
     *  hoursInState     Stunden seit dem letzten Umschalten
     *  outsideNowC      aktuelle Aussentemperatur (null = unbekannt)
     *  meanShortC/meanLongC  Aussen-Mittel (kurzes/langes Fenster), coverShort/coverLong = Abdeckung 0..1 (null = keine Daten)
     *  roomMeanC        Mittel der gewaehlten Raumfuehler, roomMinC = kaeltester Raum (null = keine)
     *  roomColdMin      Minuten, seit denen das Raum-Mittel unter dem Raum-Mindestwert liegt
     *  roomWarmMin      Minuten, seit denen es ueber Mindestwert + Hysterese liegt
     *  roomHotMin       Minuten, seit denen es ueber der Kuehl-Raumschwelle liegt
     *  dewOk            Kuehlen taupunktsicher? (true/false, null = unbekannt -> Kuehlen verboten)
     *  cfg              onBelowC, offAboveC, frostC, minHoldHours, roomMinC, roomHystK,
     *                   roomDelayMin, coolEnabled, coolRoomOnC, coolOutsideMinC
     *
     * Rueckgabe: state (Zielzustand), reason (Schluessel), params (Zahlen fuer den Text),
     * forced (true = Sperrzeit wurde bewusst uebergangen: Frostschutz/Taupunkt/fehlende Daten).
     *
     * @return array{state: string, reason: string, params: array, forced: bool}
     */
    public static function decide(array $in): array
    {
        $cfg = $in['cfg'];
        $cur = in_array($in['state'], self::STATES, true) ? $in['state'] : 'heating';
        $hours = (float) $in['hoursInState'];
        $outsideNow = $in['outsideNowC'];
        $meanShort = $in['meanShortC'];
        $meanLong = $in['meanLongC'];
        $coverShort = (float) ($in['coverShort'] ?? 0.0);
        $coverLong = (float) ($in['coverLong'] ?? 0.0);
        $roomMean = $in['roomMeanC'];
        $roomMin = $in['roomMinC'];
        $minHold = max(0.0, (float) $cfg['minHoldHours']);
        $onHold = max(min(1.0, $minHold), $minHold / 4.0);

        //1) Fehlende Aussendaten: sicherer Zustand ist Heizen
        $mShort = $meanShort ?? $outsideNow;
        if ($mShort === null) {
            return self::result('heating', 'no_data', [], true);
        }

        //2) Frostschutz hat immer Vorrang und ignoriert die Sperrzeit
        $frostC = (float) $cfg['frostC'];
        if (($meanShort !== null && $meanShort < $frostC)
            || ($outsideNow !== null && $outsideNow < $frostC - 2.0)
            || ($roomMin !== null && $roomMin < self::ROOM_FROST_C)) {
            return self::result('heating', 'frost', ['meanShort' => $meanShort ?? $outsideNow, 'frost' => $frostC], true);
        }

        //3) Kuehlen sofort beenden, wenn es nicht mehr taupunktsicher ist
        $coolAllowed = !empty($cfg['coolEnabled']);
        if ($cur === 'cooling' && $coolAllowed && ($in['dewOk'] ?? null) !== true) {
            return self::result('off', 'dew_stop', [], true);
        }

        //3b) Preis-Sperre (dynamischer Tarif): laufende Sperre beenden oder fortsetzen. Frostschutz stand
        //schon oben davor und beendet jede Sperre.
        $priceOn = !empty($cfg['priceBlock']);
        $comfortOk = $roomMean !== null
            ? $roomMean >= (float) $cfg['roomMinC'] + (float) ($cfg['priceRoomReserveK'] ?? 1.0)
            : $mShort >= $frostC + self::PRICE_OUTSIDE_RESERVE_K;
        if ($cur === 'blocked') {
            $endReason = null;
            if (!$priceOn || ($in['priceExpensive'] ?? null) !== true) {
                $endReason = 'price_end';
            } elseif ((int) ($in['priceBlockedMin'] ?? 0) >= (int) ($cfg['priceBlockMaxMin'] ?? 120)) {
                $endReason = 'price_limit';
            } elseif (!$comfortOk) {
                $endReason = 'price_comfort';
            } elseif (!empty($in['batteryCovers'])) {
                $endReason = 'price_battery';
            }
            if ($endReason === null) {
                return self::result('blocked', 'price_block', ['blockedMin' => (int) ($in['priceBlockedMin'] ?? 0)], false);
            }
            //Sperre endet: zurueck auf Heizen, die Sperrzeit gilt dafuer nicht
            return self::result('heating', $endReason, [], true);
        }

        //4) Heizentscheidung (Kuehlen zaehlt hier als "aus")
        $base = $cur === 'heating' ? 'heating' : 'off';
        $onBelow = (float) $cfg['onBelowC'];
        $offAbove = (float) $cfg['offAboveC'];
        $want = $base;
        $reason = 'hysteresis';
        $params = ['meanShort' => $mShort, 'meanLong' => $meanLong, 'on' => $onBelow, 'off' => $offAbove];

        $warm = $meanShort !== null && $meanLong !== null && $coverShort >= 0.9 && $coverLong >= 0.9
            && $meanShort > $offAbove && $meanLong > $offAbove;
        if ($mShort < $onBelow) {
            $want = 'heating';
            $reason = 'cold';
        } elseif ($warm) {
            $want = 'off';
            $reason = 'warm';
        } elseif ($roomMean !== null) {
            $delay = (int) $cfg['roomDelayMin'];
            $roomMinC = (float) $cfg['roomMinC'];
            if ((int) $in['roomColdMin'] >= $delay && $roomMean < $roomMinC) {
                $want = 'heating';
                $reason = 'room_cold';
                $params['room'] = $roomMean;
                $params['roomMin'] = $roomMinC;
            } elseif ((int) $in['roomWarmMin'] >= $delay && $roomMean >= $roomMinC + (float) $cfg['roomHystK']) {
                $want = 'off';
                $reason = 'room_warm';
                $params['room'] = $roomMean;
                $params['roomMin'] = $roomMinC;
            }
        }

        //5) Kuehlen, nur wenn die Heizlogik "aus" will
        if ($want === 'off' && $coolAllowed) {
            $coolRoomOn = (float) $cfg['coolRoomOnC'];
            $coolOutsideMin = (float) $cfg['coolOutsideMinC'];
            $dewOk = ($in['dewOk'] ?? null) === true;
            $outsideWarm = $mShort > $coolOutsideMin;
            if ($cur === 'cooling') {
                $keep = $roomMean !== null && $roomMean > $coolRoomOn - (float) $cfg['roomHystK']
                    && $mShort > $coolOutsideMin - self::COOL_OUTSIDE_HYSTERESIS_K && $dewOk;
                if ($keep) {
                    $want = 'cooling';
                    $reason = 'cooling';
                }
            } elseif ($roomMean !== null && $outsideWarm && $dewOk
                && (int) $in['roomHotMin'] >= (int) $cfg['roomDelayMin'] && $roomMean > $coolRoomOn) {
                $want = 'cooling';
                $reason = 'cool_start';
                $params['room'] = $roomMean;
                $params['coolRoom'] = $coolRoomOn;
            }
        }

        //Kuehlbetrieb endet (Raum kuehl genug, Aussen zu kalt, Kuehlen abgeschaltet)
        if ($cur === 'cooling' && $want === 'off') {
            $reason = 'cool_end';
        }

        //6) Sperrzeit: Aenderungen erst nach der Mindestdauer des aktuellen Zustands
        $holdResult = null;
        if ($want !== $cur) {
            $needed = $cur === 'heating' ? $minHold : $onHold;
            if ($hours < $needed) {
                $holdResult = self::result($cur, 'hold', [
                    'wanted' => $want,
                    'remaining' => $needed - $hours,
                ], false);
            }
        }
        $finalState = $holdResult['state'] ?? $want;
        if ($reason === 'hysteresis') {
            $reason = 'keep_' . $cur;
        }

        //7) Preis-Sperre starten: nur aus laufendem Heizbetrieb, nur in den teuersten Stunden, wenn die Raeume
        //Reserve haben, die Tageszahl nicht erreicht ist und die Batterie die Last nicht ohnehin traegt
        if ($priceOn && $cur === 'heating' && $finalState === 'heating' && ($in['priceExpensive'] ?? null) === true
            && (int) ($in['priceBlocksToday'] ?? 0) < (int) ($cfg['priceBlocksPerDay'] ?? 2) && $comfortOk && empty($in['batteryCovers'])) {
            return self::result('blocked', 'price_start', [], false);
        }
        return $holdResult ?? self::result($want, $reason, $params, false);
    }

    private static function result(string $state, string $reason, array $params, bool $forced): array
    {
        return ['state' => $state, 'reason' => $reason, 'params' => $params, 'forced' => $forced];
    }
}
