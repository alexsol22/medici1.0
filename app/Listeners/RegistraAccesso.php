<?php

namespace App\Listeners;

use App\Models\Accesso;
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

        Accesso::create([
            'user_id' => $user->id,
            'nome_completo' => $user->nome_completo,
            'email' => $user->email,
            'ip_address' => request()->ip(),
            'accesso_at' => now(),
        ]);
    }
}
