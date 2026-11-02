<?php

namespace App\Models\Legacy;

class ConfigChiave extends LegacyModel
{
    protected $table = '_config';

    protected function casts(): array
    {
        return [
            'valore' => 'float',
        ];
    }
}
