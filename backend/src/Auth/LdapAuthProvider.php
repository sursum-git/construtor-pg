<?php

namespace App\Auth;

use App\Entity\AuthProviderConfig;
use App\Runtime\RuntimeHttpException;

class LdapAuthProvider implements AuthProviderInterface
{
    public function supports(AuthProviderConfig $config): bool
    {
        return $config->getType() === 'ldap';
    }

    public function authenticate(AuthProviderConfig $config, array $credentials): AuthenticatedUser
    {
        if (!extension_loaded('ldap')) {
            throw new RuntimeHttpException('AUTH_PROVIDER_UNAVAILABLE', 'Extensao LDAP nao esta instalada no PHP.', 503, [
                'provider' => $config->getCode(),
            ]);
        }

        $settings = $config->getConfig();
        $host = trim((string) ($settings['host'] ?? ''));
        $baseDn = trim((string) ($settings['baseDn'] ?? ''));
        $username = trim((string) ($credentials['username'] ?? ''));
        $password = (string) ($credentials['password'] ?? '');
        if ($host === '' || $baseDn === '' || $username === '' || $password === '') {
            throw new RuntimeHttpException('AUTH_PROVIDER_NOT_CONFIGURED', 'Provedor LDAP nao configurado.', 422, [
                'provider' => $config->getCode(),
            ]);
        }

        $connection = @ldap_connect($host, (int) ($settings['port'] ?? 389));
        if (!$connection) {
            throw new RuntimeHttpException('AUTH_PROVIDER_UNAVAILABLE', 'Nao foi possivel conectar ao LDAP.', 503);
        }
        @ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        @ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
        if (($settings['encryption'] ?? '') === 'tls') {
            @ldap_start_tls($connection);
        }

        $bindDn = $this->resolveUserDn($connection, $settings, $baseDn, $username);
        if (!@ldap_bind($connection, $bindDn, $password)) {
            throw new RuntimeHttpException('INVALID_CREDENTIALS', 'Usuario ou senha invalidos.', 401);
        }

        $attributes = $this->readUserAttributes($connection, $baseDn, $settings, $username);
        @ldap_unbind($connection);

        $tenantId = $this->clean((string) ($credentials['tenantId'] ?? $settings['tenantId'] ?? 'default'), 80) ?: 'default';
        $displayName = $attributes['displayName'] ?? $attributes['cn'] ?? $username;
        $email = $attributes['mail'] ?? null;
        $groups = $this->normalizeList($attributes['memberOf'] ?? []);

        return new AuthenticatedUser(
            tenantId: $tenantId,
            userId: $username,
            username: $username,
            displayName: is_string($displayName) ? $displayName : $username,
            email: is_string($email) ? $email : null,
            groups: $groups,
            permissions: is_array($settings['permissions'] ?? null) ? $settings['permissions'] : [],
            source: $config->getCode(),
        );
    }

    private function resolveUserDn(mixed $connection, array $settings, string $baseDn, string $username): string
    {
        $pattern = trim((string) ($settings['userDnPattern'] ?? ''));
        if ($pattern !== '') {
            return str_replace('{username}', $this->escapeDn($username), $pattern);
        }

        $serviceDn = trim((string) ($settings['bindDn'] ?? ''));
        $servicePassword = (string) ($settings['bindPassword'] ?? '');
        if ($serviceDn !== '') {
            @ldap_bind($connection, $serviceDn, $servicePassword);
        }

        $attribute = trim((string) ($settings['usernameAttribute'] ?? 'uid')) ?: 'uid';
        $filter = sprintf('(%s=%s)', $attribute, ldap_escape($username, '', LDAP_ESCAPE_FILTER));
        $search = @ldap_search($connection, $baseDn, $filter, ['dn']);
        $entries = $search ? @ldap_get_entries($connection, $search) : false;
        if (!$entries || (int) ($entries['count'] ?? 0) < 1) {
            throw new RuntimeHttpException('INVALID_CREDENTIALS', 'Usuario ou senha invalidos.', 401);
        }

        return (string) $entries[0]['dn'];
    }

    private function readUserAttributes(mixed $connection, string $baseDn, array $settings, string $username): array
    {
        $attribute = trim((string) ($settings['usernameAttribute'] ?? 'uid')) ?: 'uid';
        $filter = sprintf('(%s=%s)', $attribute, ldap_escape($username, '', LDAP_ESCAPE_FILTER));
        $wanted = [
            (string) ($settings['displayNameAttribute'] ?? 'displayName'),
            (string) ($settings['emailAttribute'] ?? 'mail'),
            (string) ($settings['groupsAttribute'] ?? 'memberOf'),
            'cn',
        ];
        $search = @ldap_search($connection, $baseDn, $filter, array_values(array_unique($wanted)));
        $entries = $search ? @ldap_get_entries($connection, $search) : false;
        if (!$entries || (int) ($entries['count'] ?? 0) < 1) {
            return [];
        }

        $result = [];
        foreach ($wanted as $name) {
            $key = mb_strtolower($name);
            if (!isset($entries[0][$key])) {
                continue;
            }
            $values = $entries[0][$key];
            $count = (int) ($values['count'] ?? 0);
            if ($count === 1) {
                $result[$name] = $values[0];
            } elseif ($count > 1) {
                $result[$name] = array_values(array_filter(array_slice($values, 0, $count), 'is_string'));
            }
        }

        return $result;
    }

    private function normalizeList(mixed $value): array
    {
        if (!is_array($value)) {
            return is_string($value) && $value !== '' ? [$value] : [];
        }

        return array_values(array_filter(array_map('strval', $value)));
    }

    private function clean(string $value, int $length): string
    {
        return mb_substr(preg_replace('/[^A-Za-z0-9_.:@ -]+/', '', trim($value)) ?: '', 0, $length);
    }

    private function escapeDn(string $value): string
    {
        return function_exists('ldap_escape')
            ? ldap_escape($value, '', LDAP_ESCAPE_DN)
            : addcslashes($value, ',=+<>#;"\\');
    }
}
