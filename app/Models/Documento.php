<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Documento extends Model
{
    protected $table = 'documenti';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['scadenza' => 'date', 'visto_at' => 'datetime', 'formazione' => 'boolean'];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inScadenza(int $giorni = 30): bool
    {
        return $this->scadenza && $this->scadenza->lte(now()->addDays($giorni));
    }
}
