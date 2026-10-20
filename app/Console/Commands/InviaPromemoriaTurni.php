<?php

namespace App\Console\Commands;

use App\Models\ModelloMessaggio;
use App\Models\Turno;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class InviaPromemoriaTurni extends Command
{
    protected $signature = 'turni:promemoria';

    protected $description = 'Invia agli operatori le email di promemoria dei turni, secondo i modelli di messaggio';

    public function handle(): int
    {
        $inviati = 0;

        foreach (ModelloMessaggio::where('invia_email', true)->get() as $modello) {
            $turni = Turno::with(['operatore', 'sede'])
                ->whereDate('inizio', today()->addDays($modello->giorni_prima))
                ->where('stato', 'P')->get();

            foreach ($turni as $turno) {
                if (! $turno->operatore->email) {
                    continue;
                }
                $testo = $modello->render((string) $modello->testo_email, $turno);
                $oggetto = $modello->render((string) $modello->oggetto, $turno);
                Mail::raw($testo, fn ($m) => $m->to($turno->operatore->email)->subject($oggetto));
                $inviati++;
            }
        }

        $this->info("Promemoria inviati: $inviati");

        return self::SUCCESS;
    }
}
