<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class QrAziendale extends Model
{
    protected $table = 'qr_aziendali';

    protected $guarded = [];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function postazione(): BelongsTo
    {
        return $this->belongsTo(Postazione::class);
    }

    public function logoDataUri(): ?string
    {
        if (! $this->logo_path || ! Storage::disk('local')->exists($this->logo_path)) {
            return null;
        }
        $mime = Storage::disk('local')->mimeType($this->logo_path);

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('local')->get($this->logo_path));
    }
}
