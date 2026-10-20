<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RuoloRichiesto
{
    /** Uso: ->middleware('ruolo:admin,super_admin'). */
    public function handle(Request $request, Closure $next, string ...$ruoli)
    {
        $user = $request->user();

        abort_unless($user && $user->attivo && in_array($user->ruolo->value, $ruoli, true), 403);

        return $next($request);
    }
}
