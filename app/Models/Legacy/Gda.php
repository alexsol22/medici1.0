<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gda extends LegacyModel
{
    public const INIZIO = 'I';

    public const FINE = 'F';

    public const PASSAGGIO = 'P';

    protected $table = 'tc_gda';

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'lat' => 'float',
            'lng' => 'float',
            'distanza' => 'float',
        ];
    }

    public function operatore(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'id_operatore');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'id_sede');
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'id_turno');
    }

    public function postazione(): BelongsTo
    {
        return $this->belongsTo(Postazione::class, 'id_postazione');
    }

    public function codice(): BelongsTo
    {
        return $this->belongsTo(Codice::class, 'id_codice');
    }

    protected function rilevataAt(): Attribute
    {
        return Attribute::get(fn () => $this->data?->copy()->setTimeFromTimeString($this->orario ?: '00:00'));
    }

    protected function ritardoMinuti(): Attribute
    {
        return Attribute::get(fn () => $this->ritardo);
    }
}
