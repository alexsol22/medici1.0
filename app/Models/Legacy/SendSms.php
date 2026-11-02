<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SendSms extends LegacyModel
{
    protected $table = 'tc_send_sms';

    protected function casts(): array
    {
        return [
            'data_invio' => 'datetime',
        ];
    }

    public function operatore(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'id_operatore');
    }

    public function consegne(): HasMany
    {
        return $this->hasMany(SendSmsDelivery::class, 'send_id');
    }

    public function risultati(): HasMany
    {
        return $this->hasMany(SendSmsResult::class, 'send_id');
    }
}
