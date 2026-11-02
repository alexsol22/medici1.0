<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ritardo extends LegacyModel
{
    protected $table = 'tc_ritardi';

    protected function casts(): array
    {
        return [
            'data_ritardo_inizio' => 'datetime',
            'data_ritardo_fine' => 'datetime',
        ];
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'id_turno');
    }

    public function operatore(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'id_operatore');
    }
}
