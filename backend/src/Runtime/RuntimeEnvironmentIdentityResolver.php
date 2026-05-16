<?php

namespace App\Runtime;

class RuntimeEnvironmentIdentityResolver
{
    public function resolve(): array
    {
        $environment = trim((string) ($_SERVER['APP_DATABASE_ENVIRONMENT'] ?? $_ENV['APP_DATABASE_ENVIRONMENT'] ?? getenv('APP_DATABASE_ENVIRONMENT') ?: ''));
        $identity = trim((string) ($_SERVER['APP_DATABASE_IDENTITY'] ?? $_ENV['APP_DATABASE_IDENTITY'] ?? getenv('APP_DATABASE_IDENTITY') ?: ''));
        $appEnv = trim((string) ($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'dev'));

        return [
            'databaseEnvironment' => $environment !== '' ? $environment : $appEnv,
            'databaseIdentity' => $identity !== '' ? $identity : ('db:' . ($environment !== '' ? $environment : $appEnv)),
        ];
    }
}
