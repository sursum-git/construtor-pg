<?php

namespace App\Runtime;

use App\Auth\AuthenticatedSessionResolver;
use App\Entity\RuntimeUserSession;
use App\Repository\AuthRememberTokenRepository;
use App\Repository\RuntimeUserSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class RuntimeSessionGuard
{
    public function __construct(
        private readonly RuntimeUserSessionRepository $sessions,
        private readonly EntityManagerInterface $entityManager,
        private readonly PermissionResolver $permissions,
        private readonly RequestStack $requestStack,
        private readonly ?AuthRememberTokenRepository $rememberTokens = null,
        private readonly ?AuthenticatedSessionResolver $authenticatedSessions = null,
        private readonly bool $authRequired = false,
    ) {
    }

    public function ensureActive(bool $touch = true): RuntimeUserSession
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($this->authenticatedSessions?->hadInvalidToken($request)) {
            throw new RuntimeHttpException('INVALID_AUTH_TOKEN', 'Token de autenticacao invalido.', 401);
        }
        if ($this->authRequired && !$this->authenticatedSessions?->resolve($request)) {
            throw new RuntimeHttpException('AUTH_REQUIRED', 'Autenticacao obrigatoria.', 401);
        }

        $session = $this->getOrCreateCurrentSession();
        if (!$touch && $session->getId() !== null) {
            $this->entityManager->refresh($session);
        }
        if ($session->getStatus() === 'revoked') {
            $phpSessionInvalidated = $this->invalidateCurrentPhpSessionIfRequested($session);
            if ($phpSessionInvalidated) {
                $this->entityManager->flush();
            }
            throw new RuntimeHttpException('SESSION_REVOKED', 'Sua sessao foi encerrada.', 401, [
                'reason' => $session->getRevokeReason() ?: 'Sessao encerrada pelo administrador.',
                'phpSessionInvalidated' => $phpSessionInvalidated,
            ]);
        }
        if ($session->getStatus() === 'expired') {
            throw new RuntimeHttpException('SESSION_EXPIRED', 'Sua sessao expirou.', 401);
        }
        $this->expireImpersonatedSessionIfNeeded($session);

        if ($touch) {
            $this->refreshSessionSnapshot($session);
            $session->setStatus('active')->touch();
            $this->entityManager->flush();
        }

        return $session;
    }

    public function revokeTarget(string $targetUserId, ?string $targetSessionId, string $reason): array
    {
        $tenantId = $this->permissions->getTenantId();
        $items = [];

        if ($targetSessionId) {
            $session = $this->sessions->findByTenantAndSession($tenantId, $targetSessionId);
            if ($session) {
                $items[] = $session;
            }
        } else {
            $items = $this->sessions->findBy([
                'tenantId' => $tenantId,
                'userId' => $targetUserId,
                'status' => 'active',
            ]);
        }

        foreach ($items as $session) {
            $session
                ->revoke($this->permissions->getUserId(), $reason)
                ->markPhpSessionKillRequested();
        }

        return $items;
    }

    public function revokeRememberTokensForTarget(string $targetUserId, ?string $targetSessionId, string $reason): int
    {
        if (!$this->rememberTokens) {
            return 0;
        }

        $tenantId = $this->permissions->getTenantId();
        $userIds = [];
        if ($targetSessionId) {
            $session = $this->sessions->findByTenantAndSession($tenantId, $targetSessionId);
            if ($session) {
                $userIds[] = $session->getUserId();
            }
        }
        if (!$userIds && $targetUserId !== '') {
            $userIds[] = $targetUserId;
        }

        $count = 0;
        foreach (array_unique($userIds) as $userId) {
            foreach ($this->rememberTokens->findActiveByTenantAndUser($tenantId, $userId) as $token) {
                $token->revoke($reason);
                $count++;
            }
        }

        return $count;
    }

    private function getOrCreateCurrentSession(): RuntimeUserSession
    {
        $tenantId = $this->permissions->getTenantId();
        $sessionId = $this->permissions->getSessionId();
        $session = $this->sessions->findByTenantAndSession($tenantId, $sessionId);
        if ($session) {
            if ($session->getStatus() === 'active') {
                $this->refreshSessionSnapshot($session);
            }
            return $session;
        }

        $user = $this->permissions->getCurrentUserPayload();
        $session = (new RuntimeUserSession())
            ->setTenantId($tenantId)
            ->setUserId($this->permissions->getUserId())
            ->setUserName($user['name'] ?? null)
            ->setSessionId($sessionId);
        $this->refreshSessionSnapshot($session, true);
        $this->entityManager->persist($session);

        return $session;
    }

    private function refreshSessionSnapshot(RuntimeUserSession $session, bool $newSession = false): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $device = $this->detectDevice($request);
        $user = $this->permissions->getCurrentUserPayload();

        if ($newSession) {
            $session->setEnteredAt(new \DateTimeImmutable());
        }

        $session
            ->setUserId($this->permissions->getUserId())
            ->setUserName($user['name'] ?? null)
            ->setPhpSessionId($this->getCurrentPhpSessionId($request))
            ->setDeviceName($device['deviceName'])
            ->setUserAgent($device['userAgent'])
            ->setOperatingSystem($device['operatingSystem'])
            ->setBrowser($device['browser'])
            ->setIsMobile($device['isMobile'])
            ->setSessionProperties(array_replace($session->getSessionProperties(), [
                'ipAddress' => $request?->getClientIp(),
                'acceptLanguage' => $request?->headers->get('Accept-Language'),
                'runtimeSessionId' => $session->getSessionId(),
                'lastSnapshotAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ]));

        if ($newSession || !$session->getPermissionSnapshot()) {
            $session->setPermissionSnapshot([
                'capturedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'user' => $user,
                'tenantId' => $this->permissions->getTenantId(),
                'groups' => is_array($user['groups'] ?? null) ? $user['groups'] : [],
                'permissions' => is_array($user['permissions'] ?? null) ? $user['permissions'] : [],
                'runtime' => [
                    'canReadScreens' => true,
                    'canExecuteEnabledEndpoints' => true,
                    'source' => 'technical_demo_permission_resolver',
                ],
            ]);
        }
    }

    /**
     * @return array{deviceName: string|null, userAgent: string|null, operatingSystem: string|null, browser: string|null, isMobile: bool}
     */
    private function detectDevice(?Request $request): array
    {
        $userAgent = $request ? mb_substr((string) $request->headers->get('User-Agent', ''), 0, 1000) : '';
        $lower = mb_strtolower($userAgent);
        $isMobile = (bool) preg_match('/mobile|android|iphone|ipad|ipod|windows phone|opera mini/i', $userAgent);
        $operatingSystem = match (true) {
            str_contains($lower, 'windows') => 'Windows',
            str_contains($lower, 'android') => 'Android',
            str_contains($lower, 'iphone'), str_contains($lower, 'ipad'), str_contains($lower, 'ios') => 'iOS',
            str_contains($lower, 'mac os'), str_contains($lower, 'macintosh') => 'macOS',
            str_contains($lower, 'linux') => 'Linux',
            default => null,
        };
        $browser = match (true) {
            str_contains($lower, 'edg/') => 'Edge',
            str_contains($lower, 'opr/'), str_contains($lower, 'opera') => 'Opera',
            str_contains($lower, 'firefox/') => 'Firefox',
            str_contains($lower, 'chrome/') => 'Chrome',
            str_contains($lower, 'safari/') => 'Safari',
            default => null,
        };
        $deviceName = trim((string) ($request?->headers->get('X-Runtime-Device-Name', '') ?: $request?->headers->get('X-Device-Name', '')));
        if ($deviceName === '') {
            $parts = array_filter([$isMobile ? 'Mobile' : 'Desktop', $browser, $operatingSystem]);
            $deviceName = $parts ? implode(' ', $parts) : null;
        }

        return [
            'deviceName' => $deviceName,
            'userAgent' => $userAgent !== '' ? $userAgent : null,
            'operatingSystem' => $operatingSystem,
            'browser' => $browser,
            'isMobile' => $isMobile,
        ];
    }

    private function getCurrentPhpSessionId(?Request $request): ?string
    {
        if (!$request || !$request->hasSession()) {
            return null;
        }

        $phpSessionId = $request->getSession()->getId();

        return $phpSessionId !== '' ? $phpSessionId : null;
    }

    private function invalidateCurrentPhpSessionIfRequested(RuntimeUserSession $session): bool
    {
        $properties = $session->getSessionProperties();
        if (($properties['phpSessionKillRequested'] ?? false) !== true) {
            return false;
        }

        $request = $this->requestStack->getCurrentRequest();
        $currentPhpSessionId = $this->getCurrentPhpSessionId($request);
        if (!$request || !$request->hasSession() || !$currentPhpSessionId || !$session->getPhpSessionId()) {
            return false;
        }
        if (!hash_equals($session->getPhpSessionId(), $currentPhpSessionId)) {
            return false;
        }

        $request->getSession()->invalidate();
        $session->markPhpSessionInvalidated();

        return true;
    }

    private function expireImpersonatedSessionIfNeeded(RuntimeUserSession $session): void
    {
        $properties = $session->getSessionProperties();
        $impersonation = is_array($properties['impersonation'] ?? null) ? $properties['impersonation'] : [];
        if (($impersonation['enabled'] ?? false) !== true) {
            return;
        }
        $expiresAt = trim((string) ($impersonation['expiresAt'] ?? ''));
        if ($expiresAt === '') {
            return;
        }
        try {
            $expires = new \DateTimeImmutable($expiresAt);
        } catch (\Throwable) {
            return;
        }
        if ($expires >= new \DateTimeImmutable()) {
            return;
        }

        $session->setStatus('expired');
        $this->entityManager->flush();
        throw new RuntimeHttpException('SESSION_EXPIRED', 'Sua sessao de simulacao expirou.', 401, [
            'impersonation' => $impersonation,
        ]);
    }
}
