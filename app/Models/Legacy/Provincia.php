<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Provincia extends LegacyModel
{
    protected $table = '_provincia';

    public function comuni(): HasMany
    {
        return $this->hasMany(Comune::class, 'id_provincia');
    }
}
