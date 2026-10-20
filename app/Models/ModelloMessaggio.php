<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelloMessaggio extends Model
{
    protected $table = 'modelli_messaggio';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['invia_email' => 'boolean', 'invia_sms' => 'boolean'];
    }

    /** Sostituisce i segnaposto [OPERATORE], [SEDE], [DATA TURNO], [ORA TURNO] come nel gestionale originale. */
    public function render(string $testo, Turno $turno): string
    {
        return strtr($testo, [
            '[OPERATORE]' => $turno->operatore->nome_completo,
            '[SEDE]' => $turno->sede->descrizione,
            '[DATA TURNO]' => $turno->inizio->format('d/m/Y'),
            '[ORA TURNO]' => $turno->inizio->format('H:i').' - '.$turno->fine->format('H:i'),
            '[INDIRIZZO]' => trim("{$turno->sede->indirizzo} {$turno->sede->citta}"),
        ]);
    }
}
