<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Checklist e supertest: un solo account, confronto esatto dell'email. */
class AccountCollaudo
{
    public const EMAIL = 'alessiosolari22@gmail.com';

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->email === self::EMAIL, 403);

        return $next($request);
    }
}
