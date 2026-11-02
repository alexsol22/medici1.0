<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Converte le colonne legacy 'Y'/'N' in bool senza alterare il valore salvato nel DB.
 */
class YesNo implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?bool
    {
        return $value === null ? null : $value === 'Y';
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && in_array($value, ['Y', 'N'], true)) {
            return $value;
        }

        return $value ? 'Y' : 'N';
    }
}
