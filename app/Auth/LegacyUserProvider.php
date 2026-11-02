<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Login come Yii: colonna `user`, password confrontata in modo esatto (BINARY), solo attivo='Y'.
 */
class LegacyUserProvider extends EloquentUserProvider
{
    public function retrieveByCredentials(array $credentials)
    {
        if (empty($credentials['user']) || ! is_string($credentials['user'])) {
            return null;
        }

        return $this->newModelQuery()
            ->where('user', $credentials['user'])
            ->where('attivo', 'Y')
            ->first();
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $stored = (string) $user->getAuthPassword();
        $plain = (string) ($credentials['password'] ?? '');

        return $stored !== '' && $plain !== '' && hash_equals($stored, $plain);
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void {}

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token)
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token): void {}
}
