<?php

namespace App\Services;

use Carbon\CarbonInterface;

class Calcoli
{
    /** Distanza in km tra due coordinate (formula di haversine). */
    public static function distanzaKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return round($r * 2 * atan2(sqrt($a), sqrt(1 - $a)), 3);
    }

    /** Ore tra due istanti, arrotondate per difetto al quarto d'ora (come nel gestionale originale). */
    public static function ore(CarbonInterface $da, CarbonInterface $a): float
    {
        $ore = max(0, $da->diffInSeconds($a, false)) / 3600;

        return floor($ore * 4) / 4;
    }

    /** Minuti di scarto: positivo se $effettivo è dopo $previsto (ritardo), negativo se in anticipo. */
    public static function ritardoMinuti(CarbonInterface $previsto, CarbonInterface $effettivo): int
    {
        return (int) round($previsto->diffInSeconds($effettivo, false) / 60);
    }
}
