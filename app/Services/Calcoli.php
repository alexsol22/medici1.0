<?php

namespace App\Services;

use Carbon\CarbonInterface;

/** Calcoli identici a quelli di MyUtils (Yii): getDistance, getOre/getOreLavorate, getRitardo. */
class Calcoli
{
    /** Distanza in km tra due coordinate (formula sferica di MyUtils::getDistance). */
    public static function distanzaKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $theta = $lng1 - $lng2;
        $d = (sin(deg2rad($lat1)) * sin(deg2rad($lat2))) + (cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta)));
        $d = rad2deg(acos(max(-1, min(1, $d)))) * 60 * 1.1515 * 1.609344;

        return round($d, 2);
    }

    /** Ore tra due istanti: parte intera più 0, 0.25, 0.5 o 0.75 per difetto. */
    public static function ore(CarbonInterface $da, CarbonInterface $a): float
    {
        $hours = ($a->getTimestamp() - $da->getTimestamp()) / 3600;
        $tmp = $hours - intval($hours);
        $min = match (true) {
            $tmp >= 0.75 => 0.75,
            $tmp >= 0.5 => 0.50,
            $tmp >= 0.25 => 0.25,
            default => 0,
        };

        return intval($hours) + $min;
    }

    /** Minuti (troncati) di scarto: positivo se $effettivo precede $previsto, come MyUtils::getRitardo($from, $to). */
    public static function ritardoMinuti(CarbonInterface $previsto, CarbonInterface $effettivo): int
    {
        $minuti = intdiv(abs($previsto->getTimestamp() - $effettivo->getTimestamp()), 60);

        return $previsto->getTimestamp() > $effettivo->getTimestamp() ? -$minuti : $minuti;
    }
}
