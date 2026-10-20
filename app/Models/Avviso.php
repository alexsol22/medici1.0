<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Avviso extends Model
{
    protected $table = 'avvisi';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['per_tutti' => 'boolean'];
    }

    public function autore(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autore_id');
    }

    public function destinatari(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'avviso_user')->withPivot('letto_at');
    }
}
