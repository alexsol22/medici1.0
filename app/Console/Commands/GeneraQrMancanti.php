<?php

namespace App\Console\Commands;

use App\Models\Legacy\Codice;
use App\Services\QrSede;
use Illuminate\Console\Command;

class GeneraQrMancanti extends Command
{
    protected $signature = 'app:genera-qr-mancanti';

    protected $description = 'Crea l\'immagine dei QR che non hanno ancora il file';

    public function handle(QrSede $qr): int
    {
        $codici = Codice::query()
            ->whereNotNull('codice')
            ->where('codice', '!=', '')
            ->orderBy('codice')
            ->pluck('codice')
            ->unique()
            ->values();

        $creati = 0;

        foreach ($codici as $codice) {
            if (! $qr->generaMancante((string) $codice)) {
                continue;
            }

            $creati++;
            $this->line((string) $codice);
        }

        $this->info("Creati {$creati}. Già presenti ".($codici->count() - $creati).'.');

        return self::SUCCESS;
    }
}
