<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperatoreEscludi extends LegacyModel
{
    protected $table = 'tc_operatori_escludi';

    public function operatore(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'id_operatore');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }
}
