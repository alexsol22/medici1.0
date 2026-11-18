<?php

namespace App\Console\Commands;

use App\SuperTest\Copiatore;
use App\SuperTest\SessioneReale;
use Illuminate\Console\Command;
use Throwable;

class SupertestCopia extends Command
{
    protected $signature = 'supertest:copia {id : Identificativo della sessione}';

    protected $description = 'Copia il database applicativo in una sessione temporanea';

    public function handle(Copiatore $copiatore): int
    {
        $id = (string) $this->argument('id');

        if (! SessioneReale::validaId($id) || SessioneReale::fermata($id)) {
            return self::SUCCESS;
        }

        set_time_limit(0);

        try {
            $copiatore->copia(SessioneReale::database($id), $id);
            SessioneReale::segnaPronta($id);
        } catch (Throwable $errore) {
            SessioneReale::segnaErrore($id, Copiatore::messaggio($errore));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
