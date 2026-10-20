<?php

namespace App\Models;

use App\Enums\Ruolo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'ruolo' => Ruolo::class,
            'attivo' => 'boolean',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function turni(): HasMany
    {
        return $this->hasMany(Turno::class, 'operatore_id');
    }

    public function getNomeCompletoAttribute(): string
    {
        return trim("{$this->name} {$this->cognome}");
    }

    public function isStaff(): bool
    {
        return in_array($this->ruolo, [Ruolo::SuperAdmin, Ruolo::Admin], true);
    }

    public function isCliente(): bool
    {
        return $this->ruolo === Ruolo::Cliente;
    }

    public function isOperatore(): bool
    {
        return $this->ruolo === Ruolo::Operatore;
    }
}
