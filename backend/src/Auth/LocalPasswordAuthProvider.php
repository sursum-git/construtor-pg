<?php

namespace App\Auth;

use App\Entity\AuthProviderConfig;
use App\Repository\AuthUserRepository;
use App\Runtime\RuntimeHttpException;

class LocalPasswordAuthProvider implements AuthProviderInterface
{
    public function __construct(
        private readonly AuthUserRepository $users,
    ) {
    }

    public function supports(AuthProviderConfig $config): bool
    {
        return $config->getType() === 'local';
    }

    public function authenticate(AuthProviderConfig $config, array $credentials): AuthenticatedUser
    {
        $tenantId = $this->clean((string) ($credentials['tenantId'] ?? 'default'), 80) ?: 'default';
        $username = trim((string) ($credentials['username'] ?? ''));
        $password = (string) ($credentials['password'] ?? '');
        if ($username === '' || $password === '') {
            throw new RuntimeHttpException('INVALID_CREDENTIALS', 'Usuario ou senha invalidos.', 401);
        }

        $user = $this->users->findOneByTenantAndUsername($tenantId, $username);
        if (!$user || $user->getStatus() !== 'active' || !$user->getPasswordHash()) {
            throw new RuntimeHttpException('INVALID_CREDENTIALS', 'Usuario ou senha invalidos.', 401);
        }
        if (!password_verify($password, $user->getPasswordHash())) {
            throw new RuntimeHttpException('INVALID_CREDENTIALS', 'Usuario ou senha invalidos.', 401);
        }

        $user->markLogin();

        return new AuthenticatedUser(
            tenantId: $user->getTenantId(),
            userId: $user->getUsername(),
            username: $user->getUsername(),
            displayName: $user->getDisplayName(),
            email: $user->getEmail(),
            groups: $user->getGroups(),
            permissions: $user->getPermissions(),
            source: $config->getCode(),
            forcePasswordChange: $user->mustChangePassword(),
        );
    }

    private function clean(string $value, int $length): string
    {
        return mb_substr(preg_replace('/[^A-Za-z0-9_.:@ -]+/', '', trim($value)) ?: '', 0, $length);
    }
}
