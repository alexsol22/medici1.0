<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Impostazione extends Model
{
    protected $table = 'impostazioni';

    protected $fillable = ['chiave', 'valore'];

    protected function casts(): array
    {
        return [
            'valore' => 'encrypted',
        ];
    }
}
