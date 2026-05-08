<?php

namespace App\Auth;

use App\Entity\AuthProviderConfig;
use App\Runtime\RuntimeHttpException;

class SsoAuthProvider implements AuthProviderInterface
{
    public function supports(AuthProviderConfig $config): bool
    {
        return $config->getType() === 'sso';
    }

    public function authenticate(AuthProviderConfig $config, array $credentials): AuthenticatedUser
    {
        $settings = $config->getConfig();
        $headers = is_array($credentials['_headers'] ?? null) ? $credentials['_headers'] : [];
        $userHeader = (string) ($settings['userHeader'] ?? 'X-Forwarded-User');
        $username = $this->header($headers, $userHeader);
        if ($username === '') {
            throw new RuntimeHttpException('SSO_IDENTITY_NOT_FOUND', 'Identidade SSO nao encontrada na requisicao.', 401, [
                'provider' => $config->getCode(),
                'expectedHeader' => $userHeader,
            ]);
        }

        $tenantHeader = trim((string) ($settings['tenantHeader'] ?? ''));
        $tenantId = $tenantHeader !== '' ? $this->header($headers, $tenantHeader) : '';
        if ($tenantId === '') {
            $tenantId = (string) ($credentials['tenantId'] ?? $settings['tenantId'] ?? 'default');
        }

        $nameHeader = trim((string) ($settings['nameHeader'] ?? 'X-Forwarded-Name'));
        $emailHeader = trim((string) ($settings['emailHeader'] ?? 'X-Forwarded-Email'));
        $groupsHeader = trim((string) ($settings['groupsHeader'] ?? 'X-Forwarded-Groups'));
        $groups = $groupsHeader !== '' ? $this->splitList($this->header($headers, $groupsHeader)) : [];

        return new AuthenticatedUser(
            tenantId: $this->clean($tenantId, 80) ?: 'default',
            userId: $this->clean($username, 120),
            username: $this->clean($username, 160),
            displayName: $this->header($headers, $nameHeader) ?: $username,
            email: $this->header($headers, $emailHeader) ?: null,
            groups: $groups,
            permissions: is_array($settings['permissions'] ?? null) ? $settings['permissions'] : [],
            source: $config->getCode(),
        );
    }

    private function header(array $headers, string $name): string
    {
        if ($name === '') {
            return '';
        }
        $normalized = mb_strtolower($name);
        foreach ($headers as $key => $value) {
            if (mb_strtolower((string) $key) === $normalized) {
                return trim(is_array($value) ? (string) ($value[0] ?? '') : (string) $value);
            }
        }

        return '';
    }

    private function splitList(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/[,;]+/', $value) ?: [])));
    }

    private function clean(string $value, int $length): string
    {
        return mb_substr(preg_replace('/[^A-Za-z0-9_.:@ -]+/', '', trim($value)) ?: '', 0, $length);
    }
}
