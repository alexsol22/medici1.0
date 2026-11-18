<?php

namespace App\SuperTest;

/** Passi visibili nell'interfaccia mentre un caso è in esecuzione. */
class Diario
{
    public static function registra(string $caso, string $testo): void
    {
        $file = $_SERVER['SUPERTTEST_DIARIO'] ?? $_ENV['SUPERTTEST_DIARIO'] ?? getenv('SUPERTTEST_DIARIO');

        if (! is_string($file) || $file === '') {
            return;
        }

        $riga = json_encode([
            't' => date('H:i:s'),
            'caso' => $caso,
            'testo' => $testo,
        ], JSON_UNESCAPED_UNICODE);

        if ($riga === false) {
            return;
        }

        file_put_contents($file, $riga."\n", FILE_APPEND | LOCK_EX);
    }
}
