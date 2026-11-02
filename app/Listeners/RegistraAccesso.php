<?php

namespace App\Listeners;

use App\Models\Legacy\Tracking;
use App\Models\User;
use Illuminate\Auth\Events\Login;

class RegistraAccesso
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        Tracking::create([
            'id_user' => $user->id,
            'addr' => (string) request()->ip(),
            'host' => '',
            'agent' => substr((string) request()->userAgent(), 0, 255),
            'pagina' => '',
            'data_view' => now()->format('Y-m-d H:i'),
            'action' => 'Accesso utente '.$user->user,
        ]);
    }
}
