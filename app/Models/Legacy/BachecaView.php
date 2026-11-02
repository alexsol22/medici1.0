<?php

namespace App\Models\Legacy;

use App\Casts\YesNo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BachecaView extends LegacyModel
{
    protected $table = 'tc_bacheca_view';

    protected function casts(): array
    {
        return [
            'visto' => YesNo::class,
            'data_vista' => 'datetime',
        ];
    }

    public function bacheca(): BelongsTo
    {
        return $this->belongsTo(Bacheca::class, 'id_bacheca');
    }

    public function operatore(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'id_operatore');
    }
}
