<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class OperatorePreferito extends LegacyModel
{
    protected $table = 'tc_operatori_preferiti';

    /**
     * Clienti per cui ogni operatore è preferito, e le coppie cliente-operatore.
     *
     * @return array{perOperatore: array<int, array<int, int>>, coppie: array<string, true>}
     */
    public static function indice(): array
    {
        $perOperatore = [];
        $coppie = [];

        if (! Schema::hasTable((new static)->getTable())) {
            return ['perOperatore' => $perOperatore, 'coppie' => $coppie];
        }

        foreach (static::query()->get(['id_operatore', 'id_cliente']) as $riga) {
            $operatore = (int) $riga->id_operatore;
            $cliente = (int) $riga->id_cliente;
            $perOperatore[$operatore][] = $cliente;
            $coppie[$cliente.'-'.$operatore] = true;
        }

        return ['perOperatore' => $perOperatore, 'coppie' => $coppie];
    }

    public function operatore(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'id_operatore');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }
}
