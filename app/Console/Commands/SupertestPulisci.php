<?php

namespace App\Console\Commands;

use App\SuperTest\SessioneReale;
use Illuminate\Console\Command;

class SupertestPulisci extends Command
{
    protected $signature = 'supertest:pulisci';

    protected $description = 'Elimina le sessioni di supertest scadute';

    public function handle(): int
    {
        $rimosse = SessioneReale::pulisciScadute();
        $this->info("Sessioni eliminate: {$rimosse}.");

        return self::SUCCESS;
    }
}
