<?php

namespace App\Models\Legacy;

use App\Casts\YesNo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SendEmail extends LegacyModel
{
    protected $table = 'tc_send_email';

    protected function casts(): array
    {
        return [
            'sent' => YesNo::class,
            'data_sent' => 'datetime',
        ];
    }

    public function operatore(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'id_operatore');
    }
}
