<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Accesso extends Model
{
    protected $table = 'accessi';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'nome_completo',
        'email',
        'ip_address',
        'accesso_at',
    ];

    protected function casts(): array
    {
        return [
            'accesso_at' => 'datetime',
        ];
    }
}
