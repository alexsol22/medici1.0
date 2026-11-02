<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comune extends LegacyModel
{
    protected $table = '_comuni';

    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class, 'id_provincia');
    }
}
