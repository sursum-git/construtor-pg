<?php

namespace App\Runtime;

class DeploymentModeResolver
{
    public function __construct(
        private readonly RuntimeEnvironmentIdentityResolver $environmentIdentity,
    ) {
    }

    public function resolve(): string
    {
        $explicit = trim((string) ($_SERVER['APP_DEPLOYMENT_MODE'] ?? $_ENV['APP_DEPLOYMENT_MODE'] ?? getenv('APP_DEPLOYMENT_MODE') ?: ''));
        if (in_array($explicit, ['saas', 'onprem', 'shared'], true)) {
            return $explicit;
        }

        $identity = strtolower(trim((string) ($this->environmentIdentity->resolve()['databaseIdentity'] ?? '')));
        if (str_starts_with($identity, 'saas:')) {
            return 'saas';
        }
        if (str_starts_with($identity, 'onprem:')) {
            return 'onprem';
        }

        return 'shared';
    }
}
