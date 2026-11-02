<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SendSmsResult extends LegacyModel
{
    protected $table = 'tc_send_sms_result';

    protected function casts(): array
    {
        return [
            'credits' => 'float',
            'costo' => 'float',
        ];
    }

    public function sms(): BelongsTo
    {
        return $this->belongsTo(SendSms::class, 'send_id');
    }

    public function consegne(): HasMany
    {
        return $this->hasMany(SendSmsDelivery::class, 'refer_id', 'adl_id');
    }
}
