<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SendSmsDelivery extends LegacyModel
{
    protected $table = 'tc_send_sms_delivery';

    public function sms(): BelongsTo
    {
        return $this->belongsTo(SendSms::class, 'send_id');
    }
}
