<?php

namespace App\Auth;

use App\Entity\RuntimeUserSession;
use App\Repository\RuntimeUserSessionRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class AuthenticatedSessionResolver
{
    private bool $invalidToken = false;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly RuntimeUserSessionRepository $sessions,
    ) {
    }

    public function resolve(?Request $request = null): ?RuntimeUserSession
    {
        $request ??= $this->requestStack->getCurrentRequest();
        $token = $this->readToken($request);
        $this->invalidToken = false;

        if ($token === '') {
            return null;
        }

        $tenantId = $this->clean($this->readRequestValue($request, 'X-Runtime-Tenant-Id', ['runtimeTenantId', 'tenantId']), 80) ?: 'default';
        $sessionId = $this->clean($this->readRequestValue($request, 'X-Runtime-Session-Id', ['runtimeSessionId', 'sessionId']), 160);
        if ($sessionId === '') {
            $this->invalidToken = true;
            return null;
        }

        $session = $this->sessions->findByTenantAndSession($tenantId, $sessionId);
        if (!$session || !$this->tokenMatches($session, $token)) {
            $this->invalidToken = true;
            return null;
        }

        return $session;
    }

    public function hasToken(?Request $request = null): bool
    {
        return $this->readToken($request ?? $this->requestStack->getCurrentRequest()) !== '';
    }

    public function hadInvalidToken(?Request $request = null): bool
    {
        $this->resolve($request);

        return $this->invalidToken;
    }

    public function readToken(?Request $request = null): string
    {
        if (!$request) {
            return '';
        }

        $authorization = trim((string) $request->headers->get('Authorization', ''));
        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $match)) {
            return mb_substr(trim($match[1]), 0, 512);
        }

        $headerToken = trim((string) $request->headers->get('X-Runtime-Auth-Token', ''));
        if ($headerToken !== '') {
            return mb_substr($headerToken, 0, 512);
        }

        return mb_substr(trim((string) $request->query->get('runtimeAuthToken', '')), 0, 512);
    }

    private function tokenMatches(RuntimeUserSession $session, string $token): bool
    {
        $properties = $session->getSessionProperties();
        $expected = (string) ($properties['authTokenHash'] ?? $properties['authentication']['tokenHash'] ?? '');
        if ($expected === '') {
            return false;
        }

        return hash_equals($expected, hash('sha256', $token));
    }

    /**
     * @param string[] $queryNames
     */
    private function readRequestValue(?Request $request, string $headerName, array $queryNames): string
    {
        if (!$request) {
            return '';
        }

        $value = trim((string) $request->headers->get($headerName, ''));
        if ($value !== '') {
            return $value;
        }

        foreach ($queryNames as $queryName) {
            $value = trim((string) $request->query->get($queryName, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function clean(string $value, int $length): string
    {
        return mb_substr(preg_replace('/[^A-Za-z0-9_.:@ -]+/', '', trim($value)) ?: '', 0, $length);
    }
}
