<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Support\Str;

class UniqueEmailEloquentUserProvider extends EloquentUserProvider
{
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials)
    {
        $email = $credentials['email'] ?? null;

        if (is_string($email) && $email !== '') {
            $emailMatches = $this->createModel()
                ->newQuery()
                ->whereRaw('LOWER(email) = ?', [Str::lower(trim($email))])
                ->limit(2)
                ->count();

            if ($emailMatches > 1) {
                return null;
            }
        }

        return parent::retrieveByCredentials($credentials);
    }
}
