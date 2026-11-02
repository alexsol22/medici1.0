<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
