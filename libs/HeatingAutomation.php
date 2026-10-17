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

    public const STATES = ['heating', 'cooling', 'off'];

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
        if ($want !== $cur) {
            $needed = $cur === 'heating' ? $minHold : $onHold;
            if ($hours < $needed) {
                return self::result($cur, 'hold', [
                    'wanted' => $want,
                    'remaining' => $needed - $hours,
                ], false);
            }
        }
        if ($reason === 'hysteresis') {
            $reason = 'keep_' . $cur;
        }
        return self::result($want, $reason, $params, false);
    }

    private static function result(string $state, string $reason, array $params, bool $forced): array
    {
        return ['state' => $state, 'reason' => $reason, 'params' => $params, 'forced' => $forced];
    }
}
