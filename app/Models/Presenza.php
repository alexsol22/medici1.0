<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presenza extends Model
{
    protected $table = 'presenze';

    protected $guarded = [];

    public const INIZIO = 'I';

    public const FINE = 'F';

    public const PASSAGGIO = 'P';

    protected function casts(): array
    {
        return ['rilevata_at' => 'datetime'];
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }

    public function operatore(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operatore_id');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function postazione(): BelongsTo
    {
        return $this->belongsTo(Postazione::class);
    }
}
