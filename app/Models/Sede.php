<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sede extends Model
{
    protected $table = 'sedi';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['ore_richieste' => 'array'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function postazioni(): HasMany
    {
        return $this->hasMany(Postazione::class);
    }

    public function codiceQr(): BelongsTo
    {
        return $this->belongsTo(CodiceQr::class, 'codice_qr_id');
    }

    public function turni(): HasMany
    {
        return $this->hasMany(Turno::class);
    }

    /** Ore richieste per giorno ISO (1 = lunedì ... 7 = domenica). */
    public function oreRichieste(int $giornoIso): float
    {
        return (float) ($this->ore_richieste[$giornoIso] ?? 0);
    }
}
