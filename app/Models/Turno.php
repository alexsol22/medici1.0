<?php

namespace App\Models;

use App\Enums\StatoTurno;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Turno extends Model
{
    protected $table = 'turni';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'inizio' => 'datetime',
            'fine' => 'datetime',
            'stato' => StatoTurno::class,
            'ore' => 'float',
            'ore_lavorate' => 'float',
        ];
    }

    public function operatore(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operatore_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function presenze(): HasMany
    {
        return $this->hasMany(Presenza::class);
    }

    /** Stato "derivato": non timbrato, non completato o non eseguito. */
    public function statoEffettivo(): string
    {
        $now = now();

        return match (true) {
            $this->stato === StatoTurno::Completato => 'completato',
            $this->stato === StatoTurno::Iniziato && $this->fine->lt($now) => 'non_completato',
            $this->stato === StatoTurno::Iniziato => 'in_corso',
            $this->inizio->lt($now) && $this->fine->lt($now) => 'non_eseguito',
            $this->inizio->lt($now) => 'non_timbrato',
            default => 'programmato',
        };
    }

    public static function etichette(): array
    {
        return [
            'programmato' => ['Programmato', 'bg-sky-100 text-sky-700'],
            'in_corso' => ['In corso', 'bg-emerald-100 text-emerald-700'],
            'completato' => ['Completato', 'bg-teal-100 text-teal-700'],
            'non_timbrato' => ['Inizio non timbrato', 'bg-amber-100 text-amber-700'],
            'non_completato' => ['Non completato', 'bg-orange-100 text-orange-700'],
            'non_eseguito' => ['Non eseguito', 'bg-rose-100 text-rose-700'],
        ];
    }
}
