<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Postazione extends Model
{
    protected $table = 'postazioni';

    protected $guarded = [];

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function codiceQr(): BelongsTo
    {
        return $this->belongsTo(CodiceQr::class, 'codice_qr_id');
    }
}
