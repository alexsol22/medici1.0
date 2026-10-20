<?php

namespace App\Models;

use App\Enums\Ruolo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $table = 'clienti';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_test' => 'boolean',
            'inizio_contratto' => 'date',
            'fine_contratto' => 'date',
        ];
    }

    public function sedi(): HasMany
    {
        return $this->hasMany(Sede::class);
    }

    public function turni(): HasMany
    {
        return $this->hasMany(Turno::class);
    }

    public function operatori(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'cliente_operatore')->withPivot('tipo');
    }

    /** Il super admin vede solo i clienti di test, l'admin tutti gli altri, il cliente solo se stesso. */
    public function scopeVisibiliA(Builder $q, User $user): Builder
    {
        return match (true) {
            $user->isCliente() => $q->whereKey($user->cliente_id),
            $user->ruolo === Ruolo::SuperAdmin => $q->where('is_test', true),
            $user->isStaff() => $q->where('is_test', false),
            default => $q->whereRaw('1 = 0'),
        };
    }
}
