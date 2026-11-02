<?php

namespace App\Models\Legacy;

use App\Casts\YesNo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bacheca extends LegacyModel
{
    protected $table = 'tc_bacheca';

    protected function casts(): array
    {
        return [
            'data_invio' => 'datetime',
            'tutti' => YesNo::class,
        ];
    }

    public function autore(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'id_operatore');
    }

    public function visualizzazioni(): HasMany
    {
        return $this->hasMany(BachecaView::class, 'id_bacheca');
    }
}
