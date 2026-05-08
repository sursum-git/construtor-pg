<?php

namespace App\Auth;

use App\Entity\AuthProviderConfig;
use App\Runtime\RuntimeHttpException;

class AuthProviderRegistry
{
    /**
     * @param iterable<AuthProviderInterface> $providers
     */
    public function __construct(
        private readonly iterable $providers,
    ) {
    }

    public function getProvider(AuthProviderConfig $config): AuthProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($config)) {
                return $provider;
            }
        }

        throw new RuntimeHttpException('AUTH_PROVIDER_UNSUPPORTED', 'Provedor de autenticacao nao suportado.', 422, [
            'provider' => $config->getCode(),
            'type' => $config->getType(),
        ]);
    }
}
