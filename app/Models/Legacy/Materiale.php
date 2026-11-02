<?php

namespace App\Models\Legacy;

use App\Casts\YesNo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Materiale extends LegacyModel
{
    protected $table = 'tc_materiali';

    public $incrementing = false;

    /** Nel dump la colonna id non ha AUTO_INCREMENT: l'id viene assegnato qui, senza modificare lo schema. */
    protected static function booted(): void
    {
        static::creating(function (self $materiale) {
            if (! $materiale->getKey()) {
                $materiale->id = (int) static::query()->max('id') + 1;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'riconsegna' => 'date',
            'consegnato' => YesNo::class,
        ];
    }

    public function operatore(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'id_operatore');
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoMateriale::class, 'id_materiale');
    }
}
