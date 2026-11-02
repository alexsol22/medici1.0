<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Email extends LegacyModel
{
    protected $table = 'tc_email';

    protected function casts(): array
    {
        return [
            'data_invio' => 'datetime',
        ];
    }

    public function operatore(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'id_operatore');
    }
}
