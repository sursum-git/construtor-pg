<?php

namespace App\Auth;

use App\Entity\AuthProviderConfig;
use App\Runtime\RuntimeHttpException;

class OAuthAuthProvider implements AuthProviderInterface
{
    public function supports(AuthProviderConfig $config): bool
    {
        return in_array($config->getType(), ['oauth', 'oidc'], true);
    }

    public function authenticate(AuthProviderConfig $config, array $credentials): AuthenticatedUser
    {
        $claims = is_array($credentials['_claims'] ?? null) ? $credentials['_claims'] : [];
        if (!$claims) {
            throw new RuntimeHttpException('AUTH_PROVIDER_NOT_CONFIGURED', 'Fluxo OAuth precisa usar o callback configurado.', 422, [
                'provider' => $config->getCode(),
            ]);
        }

        return $this->userFromClaims($config, $claims, (string) ($credentials['tenantId'] ?? 'default'));
    }

    public function buildAuthorizationUrl(AuthProviderConfig $config, string $state, string $redirectUri): string
    {
        $settings = $config->getConfig();
        $authorizationUrl = trim((string) ($settings['authorizationUrl'] ?? ''));
        $clientId = trim((string) ($settings['clientId'] ?? ''));
        if ($authorizationUrl === '' || $clientId === '') {
            throw new RuntimeHttpException('AUTH_PROVIDER_NOT_CONFIGURED', 'Provedor OAuth nao configurado.', 422, [
                'provider' => $config->getCode(),
            ]);
        }

        $params = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => trim((string) ($settings['scope'] ?? 'openid profile email')),
            'state' => $state,
        ];

        return $authorizationUrl . (str_contains($authorizationUrl, '?') ? '&' : '?') . http_build_query($params);
    }

    public function exchangeCode(AuthProviderConfig $config, string $code, string $redirectUri): AuthenticatedUser
    {
        $settings = $config->getConfig();
        $tokenUrl = trim((string) ($settings['tokenUrl'] ?? ''));
        $userInfoUrl = trim((string) ($settings['userInfoUrl'] ?? ''));
        $clientId = trim((string) ($settings['clientId'] ?? ''));
        $clientSecret = (string) ($settings['clientSecret'] ?? '');
        if ($tokenUrl === '' || $userInfoUrl === '' || $clientId === '' || $clientSecret === '') {
            throw new RuntimeHttpException('AUTH_PROVIDER_NOT_CONFIGURED', 'Provedor OAuth nao configurado.', 422, [
                'provider' => $config->getCode(),
            ]);
        }

        $tokenPayload = $this->postForm($tokenUrl, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);
        $accessToken = (string) ($tokenPayload['access_token'] ?? '');
        if ($accessToken === '') {
            throw new RuntimeHttpException('OAUTH_TOKEN_EXCHANGE_FAILED', 'Nao foi possivel obter token OAuth.', 502);
        }

        $claims = $this->getJson($userInfoUrl, [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
        ]);

        return $this->userFromClaims($config, $claims, (string) ($settings['tenantId'] ?? 'default'));
    }

    private function userFromClaims(AuthProviderConfig $config, array $claims, string $tenantId): AuthenticatedUser
    {
        $settings = $config->getConfig();
        $userId = $this->claim($claims, (string) ($settings['userIdClaim'] ?? 'sub'));
        $username = $this->claim($claims, (string) ($settings['usernameClaim'] ?? 'preferred_username')) ?: $this->claim($claims, 'email') ?: $userId;
        if ($userId === '' || $username === '') {
            throw new RuntimeHttpException('OAUTH_IDENTITY_NOT_FOUND', 'Identidade OAuth nao encontrada.', 401);
        }

        $groupsClaim = (string) ($settings['groupsClaim'] ?? 'groups');
        $groups = $claims[$groupsClaim] ?? [];
        if (is_string($groups)) {
            $groups = array_values(array_filter(array_map('trim', preg_split('/[,;]+/', $groups) ?: [])));
        }
        if (!is_array($groups)) {
            $groups = [];
        }

        return new AuthenticatedUser(
            tenantId: $this->clean($tenantId, 80) ?: 'default',
            userId: $this->clean($userId, 120),
            username: $this->clean($username, 160),
            displayName: $this->claim($claims, (string) ($settings['nameClaim'] ?? 'name')) ?: $username,
            email: $this->claim($claims, (string) ($settings['emailClaim'] ?? 'email')) ?: null,
            groups: array_values(array_filter(array_map('strval', $groups))),
            permissions: is_array($settings['permissions'] ?? null) ? $settings['permissions'] : [],
            source: $config->getCode(),
        );
    }

    private function postForm(string $url, array $form): array
    {
        $response = @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json",
                'content' => http_build_query($form),
                'timeout' => 15,
            ],
        ]));

        return $this->decodeJson($response, 'OAUTH_TOKEN_EXCHANGE_FAILED');
    }

    private function getJson(string $url, array $headers): array
    {
        $response = @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 15,
            ],
        ]));

        return $this->decodeJson($response, 'OAUTH_USERINFO_FAILED');
    }

    private function decodeJson(string|false $response, string $code): array
    {
        if ($response === false || $response === '') {
            throw new RuntimeHttpException($code, 'Falha na comunicacao OAuth.', 502);
        }

        $payload = json_decode($response, true);
        if (!is_array($payload)) {
            throw new RuntimeHttpException($code, 'Resposta OAuth invalida.', 502);
        }

        return $payload;
    }

    private function claim(array $claims, string $name): string
    {
        $value = $claims[$name] ?? '';
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function clean(string $value, int $length): string
    {
        return mb_substr(preg_replace('/[^A-Za-z0-9_.:@ -]+/', '', trim($value)) ?: '', 0, $length);
    }
}
