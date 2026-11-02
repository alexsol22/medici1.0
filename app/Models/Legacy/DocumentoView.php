<?php

namespace App\Models\Legacy;

use App\Casts\YesNo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoView extends LegacyModel
{
    protected $table = 'tc_documenti_view';

    protected function casts(): array
    {
        return [
            'visto' => YesNo::class,
            'data_vista' => 'datetime',
        ];
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class, 'id_documento');
    }

    public function operatore(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'id_operatore');
    }
}
