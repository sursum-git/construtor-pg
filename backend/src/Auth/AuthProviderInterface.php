<?php

namespace App\Auth;

use App\Entity\AuthProviderConfig;

interface AuthProviderInterface
{
    public function supports(AuthProviderConfig $config): bool;

    public function authenticate(AuthProviderConfig $config, array $credentials): AuthenticatedUser;
}
