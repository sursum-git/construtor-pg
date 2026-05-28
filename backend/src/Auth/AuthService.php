<?php

namespace App\Auth;

use App\Entity\RuntimeUserSession;
use App\Entity\AuthLoginChallenge;
use App\Entity\AuthPasswordResetToken;
use App\Entity\AuthRememberToken;
use App\Entity\AuthSubscriber;
use App\Entity\AuthUser;
use App\Repository\AuthLoginChallengeRepository;
use App\Repository\AuthPasswordResetTokenRepository;
use App\Repository\AuthSubscriberRepository;
use App\Repository\AuthUserSubscriberRepository;
use App\Repository\AuthUserRepository;
use App\Repository\AuthProviderConfigRepository;
use App\Repository\AuthRememberTokenRepository;
use App\Repository\RuntimeUserSessionRepository;
use App\Runtime\RuntimeTransactionService;
use App\Runtime\RuntimeHttpException;
use App\System\SystemParameterResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\HttpFoundation\Request;

class AuthService
{
    public function __construct(
        private readonly AuthProviderConfigRepository $providerConfigs,
        private readonly AuthUserRepository $users,
        private readonly AuthRememberTokenRepository $rememberTokens,
        private readonly RuntimeUserSessionRepository $sessions,
        private readonly AuthSubscriberRepository $subscribers,
        private readonly AuthUserSubscriberRepository $userSubscribers,
        private readonly AuthLoginChallengeRepository $loginChallenges,
        private readonly AuthPasswordResetTokenRepository $passwordResetTokens,
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthProviderRegistry $providers,
        private readonly AuthenticatedSessionResolver $authenticatedSessions,
        private readonly SystemParameterResolver $parameters,
        private readonly MailerInterface $mailer,
        private readonly ?RuntimeTransactionService $transactions = null,
    ) {
    }

    public function listProviders(): array
    {
        $externalProviders = [];
        foreach ($this->providerConfigs->findEnabledOrdered() as $provider) {
            if (!in_array($provider->getType(), ['oauth', 'oidc'], true)) {
                continue;
            }
            $externalProviders[] = [
                'code' => $provider->getCode(),
                'type' => $provider->getType(),
                'name' => $provider->getName(),
                'redirect' => true,
            ];
        }

        return [
            'credentialLogin' => [
                'enabled' => true,
                'providerResolvedByUser' => true,
            ],
            'subscriberSelection' => [
                'enabled' => $this->isSubscriberConceptEnabled(),
                'resolvedAfterLogin' => true,
            ],
            'externalProviders' => $externalProviders,
            'providers' => $externalProviders,
            'supportedTypes' => ['local', 'ldap', 'sso', 'oauth', 'oidc'],
        ];
    }

    public function login(array $payload, Request $request): array
    {
        $providerCode = $this->resolveCredentialProviderCode($payload);
        $providerConfig = $this->providerConfigs->findEnabledByCode($providerCode);
        if (!$providerConfig) {
            throw new RuntimeHttpException('AUTH_PROVIDER_NOT_FOUND', 'Provedor de autenticacao nao encontrado ou inativo.', 404, [
                'provider' => $providerCode,
            ]);
        }
        if (in_array($providerConfig->getType(), ['oauth', 'oidc'], true)) {
            throw new RuntimeHttpException('USE_EXTERNAL_PROVIDER', 'Use o botao de acesso externo configurado para este usuario.', 422, [
                'provider' => $providerCode,
                'type' => $providerConfig->getType(),
            ]);
        }

        $credentials = array_replace($payload, [
            '_headers' => $this->requestHeaders($request),
        ]);
        $provider = $this->providers->getProvider($providerConfig);
        $user = $provider->authenticate($providerConfig, $credentials);
        if (!((bool) ($payload['remember'] ?? false)) && trim((string) ($payload['rememberToken'] ?? '')) !== '') {
            $this->revokeRememberTokenValue((string) $payload['rememberToken'], 'Manter logado desativado no novo login.');
        }

        return $this->completeAuthenticatedLogin(
            $user,
            $providerConfig->getCode(),
            $providerConfig->getType(),
            $request,
            (bool) ($payload['remember'] ?? false),
        );
    }

    private function resolveCredentialProviderCode(array $payload): string
    {
        $tenantId = $this->clean((string) ($payload['tenantId'] ?? 'default'), 80) ?: 'default';
        $username = trim((string) ($payload['username'] ?? ''));
        if ($username !== '') {
            $user = $this->users->findOneByTenantAndUsername($tenantId, $username);
            if ($user && $user->getAuthSource() !== '') {
                return $user->getAuthSource();
            }
        }

        return 'local';
    }

    public function logout(Request $request): array
    {
        $this->revokeRememberTokenFromRequest($request, 'Logout solicitado pelo usuario.');
        $session = $this->authenticatedSessions->resolve($request);
        if (!$session) {
            $this->entityManager->flush();
            return ['ok' => true, 'authenticated' => false];
        }

        $session
            ->revoke($session->getUserId(), 'Logout solicitado pelo usuario.')
            ->setSessionProperties($this->withoutAuthToken($session->getSessionProperties()));

        if ($request->hasSession()) {
            $request->getSession()->invalidate();
        }

        $this->entityManager->flush();

        return ['ok' => true, 'authenticated' => false];
    }

    public function startImpersonation(array $payload, Request $request, bool $audit = true): array
    {
        $actorSession = $this->authenticatedSessions->resolve($request);
        if (!$actorSession || $actorSession->getStatus() !== 'active') {
            throw new RuntimeHttpException('AUTH_REQUIRED', 'Autenticacao obrigatoria.', 401);
        }

        $actorSnapshot = $actorSession->getPermissionSnapshot();
        if (!$this->sessionHasPermission($actorSession, 'admin.impersonate')) {
            throw new RuntimeHttpException('IMPERSONATION_FORBIDDEN', 'Voce nao possui permissao para entrar como outro usuario.', 403);
        }

        $targetTenantId = $this->clean((string) ($payload['targetTenantId'] ?? $payload['tenantId'] ?? $payload['record']['tenant_id'] ?? $payload['values']['tenant_id'] ?? ''), 80);
        $targetUsername = $this->clean((string) ($payload['targetUsername'] ?? $payload['username'] ?? $payload['record']['username'] ?? $payload['values']['username'] ?? ''), 160);
        $reason = mb_substr(trim((string) ($payload['reason'] ?? $payload['justification'] ?? '')), 0, 1000);
        if ($targetTenantId === '' || $targetUsername === '') {
            throw new RuntimeHttpException('IMPERSONATION_TARGET_REQUIRED', 'Informe o tenant e o usuario alvo.', 422);
        }
        if ($reason === '') {
            throw new RuntimeHttpException('IMPERSONATION_REASON_REQUIRED', 'Informe a justificativa da simulacao.', 422);
        }

        $targetUser = $this->users->findOneByTenantAndUsername($targetTenantId, $targetUsername);
        if (!$targetUser) {
            throw new RuntimeHttpException('IMPERSONATION_TARGET_NOT_FOUND', 'Usuario alvo nao encontrado.', 404);
        }
        if ($targetUser->getStatus() !== 'active') {
            throw new RuntimeHttpException('IMPERSONATION_TARGET_INACTIVE', 'Usuario alvo esta inativo e nao pode ser simulado.', 422);
        }
        if ($this->authUserIsAdmin($targetUser) && !$this->sessionHasPermission($actorSession, 'admin.impersonate.admin')) {
            throw new RuntimeHttpException('IMPERSONATION_ADMIN_TARGET_FORBIDDEN', 'Entrar como administrador exige permissao adicional.', 403);
        }

        $now = new \DateTimeImmutable();
        $expiresAt = $now->modify('+60 minutes');
        $actorUser = is_array($actorSnapshot['user'] ?? null) ? $actorSnapshot['user'] : [];
        $target = $this->authenticatedUserFromPayload([
            'id' => $targetUser->getUsername(),
            'userId' => $targetUser->getUsername(),
            'username' => $targetUser->getUsername(),
            'name' => $targetUser->getDisplayName(),
            'email' => $targetUser->getEmail(),
            'groups' => $targetUser->getGroups(),
            'permissions' => $targetUser->getPermissions(),
            'tenantId' => $targetTenantId,
            'source' => 'impersonation',
            'forcePasswordChange' => $targetUser->mustChangePassword(),
        ], $targetTenantId);
        if ($this->isSubscriberConceptEnabled()) {
            $target = $this->resolveUserPermissionsForSubscriber($target, $targetTenantId);
        }

        $impersonation = [
            'enabled' => true,
            'actorUserId' => $actorSession->getUserId(),
            'actorUserName' => $actorSession->getUserName() ?: ($actorUser['name'] ?? $actorSession->getUserId()),
            'actorSessionId' => $actorSession->getSessionId(),
            'targetUserId' => $targetUser->getUsername(),
            'targetUserName' => $targetUser->getDisplayName() ?: $targetUser->getUsername(),
            'reason' => $reason,
            'startedAt' => $now->format(DATE_ATOM),
            'expiresAt' => $expiresAt->format(DATE_ATOM),
            'sourceIp' => $request->getClientIp(),
            'userAgent' => mb_substr((string) $request->headers->get('User-Agent', ''), 0, 1000),
        ];

        if ($audit) {
            $this->beginAuthAudit('auth.impersonation.start', $actorSession, $impersonation);
        }
        $response = $this->createRuntimeSession(
            $target,
            'impersonation',
            'impersonation',
            $request,
            false,
            false,
            [
                'enabled' => $this->isSubscriberConceptEnabled(),
                'selected' => [
                    'id' => $targetTenantId,
                    'code' => $targetTenantId,
                    'name' => $targetTenantId,
                    'displayName' => $targetTenantId,
                    'principal' => false,
                    'default' => true,
                    'label' => 'Assinante',
                ],
                'available' => [],
            ],
            [
                'sessionProperties' => ['impersonation' => $impersonation],
                'permissionSnapshot' => ['impersonation' => $impersonation],
            ],
        );
        $response['impersonation'] = $impersonation;
        $response['effects'] = [[
            'action' => 'switchSession',
            'message' => 'Simulacao iniciada.',
        ]];

        if ($audit) {
            $this->transactions?->log('auth.impersonation.started', 'Sessao impersonada iniciada.', metadata: $impersonation + [
                'sessionId' => $response['session']['sessionId'] ?? null,
            ]);
            $this->transactions?->success();
            $this->transactions?->clear();
        }

        return $response;
    }

    public function stopImpersonation(Request $request, bool $audit = true): array
    {
        $session = $this->authenticatedSessions->resolve($request);
        if (!$session || $session->getStatus() !== 'active') {
            throw new RuntimeHttpException('AUTH_REQUIRED', 'Autenticacao obrigatoria.', 401);
        }

        $impersonation = $this->impersonationFromSession($session);
        if (($impersonation['enabled'] ?? false) !== true) {
            throw new RuntimeHttpException('IMPERSONATION_SESSION_REQUIRED', 'A sessao atual nao e uma simulacao.', 422);
        }

        if ($audit) {
            $this->beginAuthAudit('auth.impersonation.stop', $session, $impersonation);
        }
        $session
            ->revoke((string) ($impersonation['actorUserId'] ?? $session->getUserId()), 'Simulacao encerrada.')
            ->setSessionProperties($this->withoutAuthToken($session->getSessionProperties()));

        $this->entityManager->flush();

        if ($audit) {
            $this->transactions?->log('auth.impersonation.stopped', 'Sessao impersonada encerrada.', metadata: $impersonation + [
                'sessionId' => $session->getSessionId(),
            ]);
            $this->transactions?->success();
            $this->transactions?->clear();
        }

        return [
            'ok' => true,
            'authenticated' => false,
            'impersonationStopped' => true,
            'impersonation' => $impersonation,
            'effects' => [[
                'action' => 'restoreOriginalSession',
                'message' => 'Simulacao encerrada.',
            ]],
        ];
    }

    public function remember(array $payload, Request $request): array
    {
        $tokenValue = trim((string) ($payload['rememberToken'] ?? ''));
        if ($tokenValue === '') {
            throw new RuntimeHttpException('REMEMBER_TOKEN_REQUIRED', 'Token persistente nao informado.', 401);
        }

        $rememberToken = $this->rememberTokens->findActiveByToken($tokenValue);
        if (!$rememberToken) {
            throw new RuntimeHttpException('REMEMBER_TOKEN_INVALID', 'Token persistente invalido.', 401);
        }
        if ($rememberToken->getExpiresAt() < new \DateTimeImmutable()) {
            $rememberToken->revoke('Token persistente expirado.');
            $this->entityManager->flush();
            throw new RuntimeHttpException('REMEMBER_TOKEN_EXPIRED', 'Token persistente expirado.', 401);
        }

        $user = $this->users->findOneByTenantAndUsername($rememberToken->getTenantId(), $rememberToken->getUsername());
        if (!$user || $user->getStatus() !== 'active') {
            $rememberToken->revoke('Usuario do token persistente inativo ou inexistente.');
            $this->entityManager->flush();
            throw new RuntimeHttpException('REMEMBER_TOKEN_INVALID', 'Token persistente invalido.', 401);
        }

        $rememberToken->markUsed();

        $authenticated = $this->authenticatedUserFromPayload([
            'id' => $user->getUsername(),
            'userId' => $user->getUsername(),
            'name' => $user->getDisplayName(),
            'email' => $user->getEmail(),
            'groups' => $user->getGroups(),
            'permissions' => $user->getPermissions(),
            'tenantId' => $user->getTenantId(),
            'source' => $user->getAuthSource(),
            'forcePasswordChange' => $user->mustChangePassword(),
        ], $user->getTenantId());
        $subscriberContext = $this->resolveSubscriberContext($authenticated);
        if (($subscriberContext['requiresSelection'] ?? false) === true) {
            return $this->createSubscriberSelectionChallenge(
                $authenticated,
                $user->getAuthSource(),
                $user->getAuthSource(),
                true,
                $subscriberContext,
            );
        }
        if (($subscriberContext['enabled'] ?? false) === true && !empty($subscriberContext['selected']['id'])) {
            $authenticated = $this->resolveUserPermissionsForSubscriber(
                $authenticated,
                (string) $subscriberContext['selected']['id'],
            );
        }

        $response = $this->createRuntimeSession(
            $authenticated,
            $user->getAuthSource(),
            $user->getAuthSource(),
            $request,
            true,
            false,
            $subscriberContext,
        );
        $response['rememberToken'] = $tokenValue;
        $response['rememberTokenExpiresAt'] = $rememberToken->getExpiresAt()->format(DATE_ATOM);

        return $response;
    }

    public function currentSession(Request $request): array
    {
        $session = $this->authenticatedSessions->resolve($request);
        if (!$session) {
            throw new RuntimeHttpException('AUTH_REQUIRED', 'Autenticacao obrigatoria.', 401);
        }
        $this->ensureSessionNotExpired($session);

        return [
            'authenticated' => true,
            'tenantId' => $session->getTenantId(),
            'session' => $this->formatSession($session),
            'user' => $this->userPayloadFromSession($session),
        ];
    }

    public function startOAuth(string $providerCode, Request $request): array
    {
        $providerConfig = $this->providerConfigs->findEnabledByCode($providerCode);
        if (!$providerConfig) {
            throw new RuntimeHttpException('AUTH_PROVIDER_NOT_FOUND', 'Provedor de autenticacao nao encontrado ou inativo.', 404);
        }

        $provider = $this->providers->getProvider($providerConfig);
        if (!$provider instanceof OAuthAuthProvider) {
            throw new RuntimeHttpException('AUTH_PROVIDER_UNSUPPORTED', 'Provedor nao e OAuth/OIDC.', 422);
        }

        $state = bin2hex(random_bytes(24));
        $redirectUri = $this->oauthRedirectUri($request, $providerCode);
        if (!$request->hasSession()) {
            throw new RuntimeHttpException('OAUTH_SESSION_REQUIRED', 'Sessao PHP obrigatoria para iniciar OAuth.', 500);
        }
        $session = $request->getSession();
        $states = $session->get('runtime_oauth_states', []);
        $states[$state] = [
            'provider' => $providerCode,
            'createdAt' => time(),
        ];
        $session->set('runtime_oauth_states', $states);

        return [
            'provider' => $providerCode,
            'state' => $state,
            'authorizationUrl' => $provider->buildAuthorizationUrl($providerConfig, $state, $redirectUri),
        ];
    }

    public function completeOAuth(string $providerCode, Request $request): array
    {
        $state = trim((string) $request->query->get('state', ''));
        $code = trim((string) $request->query->get('code', ''));
        if ($state === '' || $code === '') {
            throw new RuntimeHttpException('OAUTH_CALLBACK_INVALID', 'Callback OAuth invalido.', 400);
        }

        if (!$request->hasSession()) {
            throw new RuntimeHttpException('OAUTH_STATE_INVALID', 'Estado OAuth invalido ou expirado.', 401);
        }
        $states = $request->getSession()->get('runtime_oauth_states', []);
        $stateInfo = is_array($states[$state] ?? null) ? $states[$state] : null;
        if (!$stateInfo || ($stateInfo['provider'] ?? '') !== $providerCode || (time() - (int) ($stateInfo['createdAt'] ?? 0)) > 600) {
            throw new RuntimeHttpException('OAUTH_STATE_INVALID', 'Estado OAuth invalido ou expirado.', 401);
        }
        unset($states[$state]);
        $request->getSession()->set('runtime_oauth_states', $states);

        $providerConfig = $this->providerConfigs->findEnabledByCode($providerCode);
        if (!$providerConfig) {
            throw new RuntimeHttpException('AUTH_PROVIDER_NOT_FOUND', 'Provedor de autenticacao nao encontrado ou inativo.', 404);
        }
        $provider = $this->providers->getProvider($providerConfig);
        if (!$provider instanceof OAuthAuthProvider) {
            throw new RuntimeHttpException('AUTH_PROVIDER_UNSUPPORTED', 'Provedor nao e OAuth/OIDC.', 422);
        }

        $user = $provider->exchangeCode($providerConfig, $code, $this->oauthRedirectUri($request, $providerCode));

        return $this->completeAuthenticatedLogin($user, $providerConfig->getCode(), $providerConfig->getType(), $request, true);
    }

    public function selectSubscriber(array $payload, Request $request): array
    {
        $token = trim((string) ($payload['selectionToken'] ?? $payload['subscriberSelectionToken'] ?? ''));
        $subscriberCode = $this->clean((string) ($payload['subscriberId'] ?? $payload['subscriberCode'] ?? ''), 80);
        if ($token === '' || $subscriberCode === '') {
            throw new RuntimeHttpException('SUBSCRIBER_SELECTION_REQUIRED', 'Informe o token de selecao e o assinante.', 422);
        }

        $challenge = $this->loginChallenges->findActiveByToken($token);
        if (!$challenge || $challenge->getExpiresAt() < new \DateTimeImmutable()) {
            throw new RuntimeHttpException('SUBSCRIBER_SELECTION_EXPIRED', 'Selecao de assinante expirada. Faca login novamente.', 401);
        }

        $available = $challenge->getAvailableSubscribers();
        $selected = $this->findSubscriberInPayload($available, $subscriberCode);
        if (!$selected) {
            throw new RuntimeHttpException('SUBSCRIBER_FORBIDDEN', 'Usuario nao possui acesso ao assinante informado.', 403, [
                'subscriberId' => $subscriberCode,
            ]);
        }

        $challenge->markUsed();
        $user = $this->authenticatedUserFromPayload($challenge->getUserPayload(), $subscriberCode);
        $user = $this->resolveUserPermissionsForSubscriber($user, $subscriberCode);

        return $this->createRuntimeSession(
            $user,
            $challenge->getProviderCode(),
            $challenge->getProviderType(),
            $request,
            $challenge->shouldRemember(),
            true,
            [
                'enabled' => true,
                'selected' => $selected,
                'available' => $available,
            ],
        );
    }

    public function requestPasswordReset(array $payload, Request $request): array
    {
        $identity = trim((string) ($payload['username'] ?? $payload['email'] ?? $payload['identity'] ?? ''));
        if ($identity === '') {
            throw new RuntimeHttpException('PASSWORD_RESET_IDENTITY_REQUIRED', 'Informe usuario ou e-mail.', 422);
        }

        $user = $this->users->findOneForPasswordReset($identity);
        $tokenValue = null;
        $expiresAt = null;
        if ($user && $user->getAuthSource() === 'local' && $user->getEmail()) {
            foreach ($this->passwordResetTokens->findActiveForUser($user->getTenantId(), $user->getUsername()) as $activeToken) {
                $activeToken->revoke();
            }

            $tokenValue = bin2hex(random_bytes(32));
            $expiresAt = (new \DateTimeImmutable())->modify('+30 minutes');
            $token = (new AuthPasswordResetToken())
                ->setUserTenantId($user->getTenantId())
                ->setUsername($user->getUsername())
                ->setTokenHash(hash('sha256', $tokenValue))
                ->setExpiresAt($expiresAt)
                ->setMetadata([
                    'requestIp' => $request->getClientIp(),
                    'userAgent' => mb_substr((string) $request->headers->get('User-Agent', ''), 0, 1000),
                    'requestedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                ]);
            $this->entityManager->persist($token);
            $this->sendPasswordResetEmail($user, $tokenValue, $expiresAt, $request);
        }

        $this->entityManager->flush();

        $response = [
            'ok' => true,
            'message' => 'Se o usuario existir e permitir recuperacao por senha local, enviaremos as instrucoes.',
        ];
        if ($tokenValue && $this->shouldExposeDevToken()) {
            $response['resetToken'] = $tokenValue;
            $response['expiresAt'] = $expiresAt?->format(DATE_ATOM);
        }

        return $response;
    }

    public function resetPassword(array $payload): array
    {
        $tokenValue = trim((string) ($payload['resetToken'] ?? $payload['token'] ?? ''));
        $password = (string) ($payload['password'] ?? $payload['newPassword'] ?? '');
        if ($tokenValue === '' || $password === '') {
            throw new RuntimeHttpException('PASSWORD_RESET_TOKEN_REQUIRED', 'Informe token e nova senha.', 422);
        }
        if (mb_strlen($password) < 8) {
            throw new RuntimeHttpException('PASSWORD_TOO_SHORT', 'A senha deve ter pelo menos 8 caracteres.', 422);
        }

        $token = $this->passwordResetTokens->findActiveByToken($tokenValue);
        if (!$token || $token->getExpiresAt() < new \DateTimeImmutable()) {
            throw new RuntimeHttpException('PASSWORD_RESET_TOKEN_INVALID', 'Token de recuperacao invalido ou expirado.', 401);
        }

        $user = $this->users->findOneByTenantAndUsername($token->getUserTenantId(), $token->getUsername());
        if (!$user || $user->getStatus() !== 'active' || $user->getAuthSource() !== 'local') {
            $token->revoke();
            $this->entityManager->flush();
            throw new RuntimeHttpException('PASSWORD_RESET_TOKEN_INVALID', 'Token de recuperacao invalido ou expirado.', 401);
        }

        $user
            ->setPasswordHash(password_hash($password, PASSWORD_DEFAULT))
            ->setForcePasswordChange(false);
        $token->markUsed();
        $this->entityManager->flush();

        return [
            'ok' => true,
            'message' => 'Senha alterada com sucesso.',
        ];
    }

    private function createRuntimeSession(
        AuthenticatedUser $user,
        string $providerCode,
        string $providerType,
        Request $request,
        bool $remember,
        bool $issueRememberToken = true,
        array $subscriberContext = [],
        array $sessionOptions = [],
    ): array
    {
        $token = bin2hex(random_bytes(32));
        $sessionId = $this->newRuntimeSessionId($user->getTenantId());
        $device = $this->detectDevice($request);
        $now = new \DateTimeImmutable();
        $userPayload = $user->toPayload();
        if (!empty($subscriberContext['selected']) || !empty($subscriberContext['available'])) {
            $userPayload['currentSubscriber'] = $subscriberContext['selected'] ?? null;
            $userPayload['availableSubscribers'] = $subscriberContext['available'] ?? [];
        }

        $sessionProperties = [
            'ipAddress' => $request->getClientIp(),
            'acceptLanguage' => $request->headers->get('Accept-Language'),
            'runtimeSessionId' => $sessionId,
            'authTokenHash' => hash('sha256', $token),
            'authentication' => [
                'provider' => $providerCode,
                'type' => $providerType,
                'issuedAt' => $now->format(DATE_ATOM),
                'remember' => $remember,
            ],
            'subscriber' => [
                'enabled' => (bool) ($subscriberContext['enabled'] ?? false),
                'current' => $subscriberContext['selected'] ?? null,
                'available' => $subscriberContext['available'] ?? [],
            ],
        ];
        $permissionSnapshot = [
            'capturedAt' => $now->format(DATE_ATOM),
            'tenantId' => $user->getTenantId(),
            'subscriber' => [
                'enabled' => (bool) ($subscriberContext['enabled'] ?? false),
                'current' => $subscriberContext['selected'] ?? null,
                'available' => $subscriberContext['available'] ?? [],
            ],
            'user' => $userPayload,
            'groups' => $user->getGroups(),
            'permissions' => $user->getPermissions(),
            'runtime' => [
                'canReadScreens' => true,
                'canExecuteEnabledEndpoints' => true,
                'source' => $providerCode,
            ],
        ];
        $sessionProperties = array_replace_recursive($sessionProperties, is_array($sessionOptions['sessionProperties'] ?? null) ? $sessionOptions['sessionProperties'] : []);
        $permissionSnapshot = array_replace_recursive($permissionSnapshot, is_array($sessionOptions['permissionSnapshot'] ?? null) ? $sessionOptions['permissionSnapshot'] : []);

        $session = (new RuntimeUserSession())
            ->setTenantId($user->getTenantId())
            ->setUserId($user->getUserId())
            ->setUserName($user->getDisplayName())
            ->setSessionId($sessionId)
            ->setEnteredAt($now)
            ->setPhpSessionId($this->getPhpSessionId($request))
            ->setDeviceName($device['deviceName'])
            ->setUserAgent($device['userAgent'])
            ->setOperatingSystem($device['operatingSystem'])
            ->setBrowser($device['browser'])
            ->setIsMobile($device['isMobile'])
            ->setSessionProperties($sessionProperties)
            ->setPermissionSnapshot($permissionSnapshot);

        $this->entityManager->persist($session);
        $rememberToken = $remember && $issueRememberToken ? $this->createRememberToken($user, $request, $device, $providerCode) : null;
        $this->entityManager->flush();

        if ($request->hasSession()) {
            $request->getSession()->set('runtimeSessionId', $sessionId);
        }

        $response = [
            'ok' => true,
            'authenticated' => true,
            'tokenType' => 'Bearer',
            'token' => $token,
            'tenantId' => $user->getTenantId(),
            'session' => $this->formatSession($session),
            'user' => $userPayload,
        ];
        if (!empty($subscriberContext['selected'])) {
            $response['currentSubscriber'] = $subscriberContext['selected'];
            $response['availableSubscribers'] = $subscriberContext['available'] ?? [];
        }
        if ($rememberToken) {
            $response['rememberToken'] = $rememberToken['token'];
            $response['rememberTokenExpiresAt'] = $rememberToken['expiresAt']->format(DATE_ATOM);
        }

        return $response;
    }

    private function completeAuthenticatedLogin(
        AuthenticatedUser $user,
        string $providerCode,
        string $providerType,
        Request $request,
        bool $remember,
    ): array {
        $subscriberContext = $this->resolveSubscriberContext($user);
        if (($subscriberContext['requiresSelection'] ?? false) === true) {
            $token = bin2hex(random_bytes(32));
            $challenge = (new AuthLoginChallenge())
                ->setTokenHash(hash('sha256', $token))
                ->setUserPayload($user->toPayload() + ['tenantId' => $user->getTenantId()])
                ->setProviderCode($providerCode)
                ->setProviderType($providerType)
                ->setRemember($remember)
                ->setDefaultSubscriberCode($subscriberContext['defaultSubscriberCode'] ?? null)
                ->setAvailableSubscribers($subscriberContext['available'] ?? []);
            $this->entityManager->persist($challenge);
            $this->entityManager->flush();

            return [
                'ok' => true,
                'authenticated' => false,
                'requiresSubscriberSelection' => true,
                'selectionToken' => $token,
                'subscriberSelectionToken' => $token,
                'defaultSubscriberId' => $subscriberContext['defaultSubscriberCode'] ?? null,
                'subscribers' => $subscriberContext['available'] ?? [],
                'user' => $user->toPayload(),
                'remember' => $remember,
            ];
        }

        if (($subscriberContext['enabled'] ?? false) === true && !empty($subscriberContext['selected']['id'])) {
            $user = $this->resolveUserPermissionsForSubscriber(
                $user,
                (string) $subscriberContext['selected']['id'],
            );
        }

        return $this->createRuntimeSession(
            $user,
            $providerCode,
            $providerType,
            $request,
            $remember,
            true,
            $subscriberContext,
        );
    }

    private function resolveSubscriberContext(AuthenticatedUser $user): array
    {
        if (!$this->isSubscriberConceptEnabled()) {
            return ['enabled' => false];
        }

        $isAdmin = $this->isAdminUser($user);
        $items = $isAdmin ? $this->subscribers->findEnabledOrdered() : $this->subscribersForUser($user);
        if (!$items) {
            throw new RuntimeHttpException('SUBSCRIBER_ACCESS_NOT_CONFIGURED', 'Usuario nao possui assinante habilitado.', 403);
        }

        $available = array_map(fn (AuthSubscriber $subscriber): array => $this->formatSubscriber($subscriber, $this->isDefaultSubscriber($user, $subscriber)), $items);
        $default = $this->findDefaultSubscriber($available, $user);

        return [
            'enabled' => true,
            'requiresSelection' => $isAdmin || count($available) > 1,
            'selected' => count($available) === 1 && !$isAdmin ? $available[0] : null,
            'available' => $available,
            'defaultSubscriberCode' => $default['id'] ?? null,
            'isAdmin' => $isAdmin,
        ];
    }

    /**
     * @return AuthSubscriber[]
     */
    private function subscribersForUser(AuthenticatedUser $user): array
    {
        $items = [];
        foreach ($this->userSubscribers->findEnabledForUser($user->getTenantId(), $user->getUsername()) as $access) {
            $subscriber = $this->subscribers->findEnabledByCode($access->getSubscriberCode());
            if ($subscriber) {
                $items[$subscriber->getCode()] = $subscriber;
            }
        }

        if (!$items) {
            $subscriber = $this->subscribers->findEnabledByCode($user->getTenantId());
            if ($subscriber) {
                $items[$subscriber->getCode()] = $subscriber;
            }
        }

        return array_values($items);
    }

    private function isDefaultSubscriber(AuthenticatedUser $user, AuthSubscriber $subscriber): bool
    {
        foreach ($this->userSubscribers->findEnabledForUser($user->getTenantId(), $user->getUsername()) as $access) {
            if ($access->getSubscriberCode() === $subscriber->getCode()) {
                return $access->isDefaultSubscriber();
            }
        }

        return $subscriber->getCode() === $user->getTenantId() || $subscriber->isPrincipal();
    }

    private function findDefaultSubscriber(array $subscribers, AuthenticatedUser $user): ?array
    {
        foreach ($subscribers as $subscriber) {
            if (($subscriber['default'] ?? false) === true) {
                return $subscriber;
            }
        }
        foreach ($subscribers as $subscriber) {
            if (($subscriber['id'] ?? '') === $user->getTenantId()) {
                return $subscriber;
            }
        }
        foreach ($subscribers as $subscriber) {
            if (($subscriber['principal'] ?? false) === true) {
                return $subscriber;
            }
        }

        return $subscribers[0] ?? null;
    }

    private function formatSubscriber(AuthSubscriber $subscriber, bool $default): array
    {
        return [
            'id' => $subscriber->getCode(),
            'code' => $subscriber->getCode(),
            'name' => $subscriber->getName(),
            'displayName' => $subscriber->getName(),
            'document' => $subscriber->getDocument(),
            'principal' => $subscriber->isPrincipal(),
            'default' => $default,
            'label' => $subscriber->isPrincipal() ? 'Principal' : 'Assinante',
        ];
    }

    private function findSubscriberInPayload(array $subscribers, string $subscriberCode): ?array
    {
        foreach ($subscribers as $subscriber) {
            if (is_array($subscriber) && (string) ($subscriber['id'] ?? $subscriber['code'] ?? '') === $subscriberCode) {
                return $subscriber;
            }
        }

        return null;
    }

    private function authenticatedUserFromPayload(array $payload, string $tenantId): AuthenticatedUser
    {
        return new AuthenticatedUser(
            tenantId: $tenantId,
            userId: (string) ($payload['id'] ?? $payload['userId'] ?? $payload['username'] ?? ''),
            username: (string) ($payload['username'] ?? $payload['id'] ?? ''),
            displayName: isset($payload['name']) ? (string) $payload['name'] : null,
            email: isset($payload['email']) ? (string) $payload['email'] : null,
            groups: is_array($payload['groups'] ?? null) ? $payload['groups'] : [],
            permissions: is_array($payload['permissions'] ?? null) ? $payload['permissions'] : [],
            source: (string) ($payload['source'] ?? 'local'),
            forcePasswordChange: (bool) ($payload['forcePasswordChange'] ?? false),
        );
    }

    private function isAdminUser(AuthenticatedUser $user): bool
    {
        if (in_array('admin', array_map('strval', $user->getGroups()), true)) {
            return true;
        }

        return $this->hasResolvedPermission($user->getPermissions(), 'admin')
            || $this->hasResolvedPermission($user->getPermissions(), 'admin.*')
            || $this->hasResolvedPermission($user->getPermissions(), '*');
    }

    private function resolveUserPermissionsForSubscriber(AuthenticatedUser $user, string $subscriberCode): AuthenticatedUser
    {
        $access = $this->userSubscribers->findOneEnabledForUserAndSubscriber(
            $user->getTenantId(),
            $user->getUsername(),
            $subscriberCode,
        );

        if (!$access) {
            return $user;
        }

        $overrides = is_array($access->getPermissionOverrides()) ? $access->getPermissionOverrides() : [];
        if (!$overrides) {
            return $user;
        }

        $merged = $this->mergePermissions($user->getPermissions(), $overrides);
        if ($merged === $user->getPermissions()) {
            return $user;
        }

        return new AuthenticatedUser(
            tenantId: $user->getTenantId(),
            userId: $user->getUserId(),
            username: $user->getUsername(),
            displayName: $user->getDisplayName(),
            email: $user->getEmail(),
            groups: $user->getGroups(),
            permissions: $merged,
            source: $user->getSource(),
            forcePasswordChange: $user->mustChangePassword(),
        );
    }

    /**
     * @param mixed $value
     */
    private function isPermissionValueDenied(mixed $value): bool
    {
        if ($value === false || $value === 0 || $value === '0') {
            return true;
        }
        if (is_string($value)) {
            return in_array(mb_strtolower(trim($value)), ['false', 'nao', 'no'], true);
        }

        return false;
    }

    /**
     * @param array<int|string, mixed> $permissions
     *
     * @return array<string, bool>
     */
    private function normalizePermissionMap(array $permissions): array
    {
        $map = [];
        foreach ($permissions as $key => $value) {
            if (is_int($key)) {
                $this->collectPermissionEntry((string) $value, true, $map);
                continue;
            }

            $this->collectPermissionEntry((string) $key, $value, $map);
        }

        return $map;
    }

    /**
     * @param array<int|string, mixed> $basePermissions
     * @param array<int|string, mixed> $overrides
     *
     * @return array<string, bool>
     */
    private function mergePermissions(array $basePermissions, array $overrides): array
    {
        $base = $this->normalizePermissionMap($basePermissions);
        $override = $this->normalizePermissionMap($overrides);
        if (!$override) {
            return $base;
        }

        foreach ($override as $permission => $allow) {
            $base[$permission] = (bool) $allow;
        }

        if (!$base) {
            return [];
        }

        ksort($base);
        return $base;
    }

    /**
     * @param array<string, bool> $map
     */
    private function collectPermissionEntry(string $permission, mixed $value, array &$map): void
    {
        $permission = $this->normalizePermission($permission);
        if ($permission === '') {
            return;
        }

        if (is_array($value)) {
            $isAssoc = $this->isAssociativeArray($value);
            if (!$isAssoc) {
                foreach ($value as $item) {
                    $this->collectPermissionEntry((string) $item, true, $map);
                }

                return;
            }

            foreach ($value as $nestedKey => $nestedValue) {
                $nestedPermission = $permission . '.' . (string) $nestedKey;
                $this->collectPermissionEntry($nestedPermission, $nestedValue, $map);
            }
            return;
        }

        if (is_string($value)) {
            $normalizedValue = mb_strtolower(trim($value));
            if ($normalizedValue === 'true') {
                $map[$permission] = true;
                return;
            }
            if ($normalizedValue === 'false' || $normalizedValue === 'nao' || $normalizedValue === 'no' || $normalizedValue === '0') {
                $map[$permission] = false;
                return;
            }
        }

        if (is_bool($value) || $value === 0 || $value === 1 || $value === '0' || $value === '1') {
            $map[$permission] = (bool) $value;
            return;
        }

        $map[$permission] = !$this->isPermissionValueDenied($value);
    }

    private function isAssociativeArray(array $value): bool
    {
        return array_values($value) !== $value;
    }

    /**
     * @param array<int|string, mixed> $rawPermissions
     */
    private function hasResolvedPermission(array $rawPermissions, string $permission): bool
    {
        $permission = $this->normalizePermission($permission);
        if ($permission === '') {
            return true;
        }

        $permissionSets = $this->extractPermissionSets($this->normalizePermissionMap($rawPermissions));
        foreach ($permissionSets['deny'] as $denied) {
            if ($this->permissionMatches($denied, $permission)) {
                return false;
            }
        }

        foreach ($permissionSets['allow'] as $allowed) {
            if ($this->permissionMatches($allowed, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, bool> $map
     *
     * @return array{allow:string[], deny:string[]}
     */
    private function extractPermissionSets(array $map): array
    {
        $allow = [];
        $deny = [];
        foreach ($map as $permission => $allowed) {
            $permission = $this->normalizePermission((string) $permission);
            if ($permission === '') {
                continue;
            }
            if ($allowed) {
                $allow[] = $permission;
            } else {
                $deny[] = $permission;
            }
        }

        return [
            'allow' => array_values(array_unique($allow)),
            'deny' => array_values(array_unique($deny)),
        ];
    }

    private function permissionMatches(string $pattern, string $permission): bool
    {
        if ($pattern === '*' || $pattern === $permission) {
            return true;
        }
        if (str_ends_with($pattern, '.*')) {
            return str_starts_with($permission, substr($pattern, 0, -1));
        }
        if (str_contains($pattern, '*')) {
            $escaped = preg_quote($pattern, '/');
            $regex = '/^' . str_replace('\\*', '.*', $escaped) . '$/';
            return (bool) preg_match($regex, $permission);
        }

        return false;
    }

    private function normalizePermission(string $permission): string
    {
        return mb_strtolower(trim($permission));
    }

    private function isSubscriberConceptEnabled(): bool
    {
        try {
            return $this->parameters->getBoolean('subscriber.enabled');
        } catch (\Throwable) {
            return false;
        }
    }

    private function sendPasswordResetEmail(AuthUser $user, string $token, \DateTimeImmutable $expiresAt, Request $request): void
    {
        $resetUrl = rtrim($request->getSchemeAndHttpHost(), '/') . '/production/login.html?resetToken=' . rawurlencode($token);
        $email = (new Email())
            ->from('nao-responda@construtor.local')
            ->to((string) $user->getEmail())
            ->subject('Recuperacao de senha')
            ->text(
                "Foi solicitada a recuperacao de senha.\n\n"
                . "Acesse: " . $resetUrl . "\n"
                . "Validade: " . $expiresAt->format('d/m/Y H:i') . "\n\n"
                . "Se voce nao solicitou, ignore esta mensagem."
            );

        $this->mailer->send($email);
    }

    private function shouldExposeDevToken(): bool
    {
        $env = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'dev';

        return $env !== 'prod';
    }

    private function createRememberToken(AuthenticatedUser $user, Request $request, array $device, string $providerCode): array
    {
        $token = bin2hex(random_bytes(40));
        $expiresAt = (new \DateTimeImmutable())->modify('+30 days');
        $rememberToken = (new AuthRememberToken())
            ->setTenantId($user->getTenantId())
            ->setUserId($user->getUserId())
            ->setUsername($user->getUsername())
            ->setTokenHash(hash('sha256', $token))
            ->setStatus('active')
            ->setDeviceName($device['deviceName'])
            ->setUserAgent($device['userAgent'])
            ->setExpiresAt($expiresAt)
            ->setMetadata([
                'provider' => $providerCode,
                'ipAddress' => $request->getClientIp(),
                'issuedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ]);

        $this->entityManager->persist($rememberToken);

        return [
            'token' => $token,
            'expiresAt' => $expiresAt,
        ];
    }

    private function newRuntimeSessionId(string $tenantId): string
    {
        do {
            $sessionId = 'sess-' . bin2hex(random_bytes(18));
        } while ($this->sessions->findByTenantAndSession($tenantId, $sessionId) !== null);

        return $sessionId;
    }

    private function userPayloadFromSession(RuntimeUserSession $session): array
    {
        $snapshot = $session->getPermissionSnapshot();
        if (is_array($snapshot['user'] ?? null)) {
            return $snapshot['user'];
        }

        return [
            'id' => $session->getUserId(),
            'username' => $session->getUserId(),
            'name' => $session->getUserName() ?: $session->getUserId(),
            'groups' => [],
            'permissions' => [],
        ];
    }

    private function formatSession(RuntimeUserSession $session): array
    {
        $payload = [
            'sessionId' => $session->getSessionId(),
            'status' => $session->getStatus(),
            'enteredAt' => $session->getEnteredAt()->format(DATE_ATOM),
            'lastSeenAt' => $session->getLastSeenAt()->format(DATE_ATOM),
            'phpSessionId' => $session->getPhpSessionId(),
            'deviceName' => $session->getDeviceName(),
            'operatingSystem' => $session->getOperatingSystem(),
            'browser' => $session->getBrowser(),
            'isMobile' => $session->isMobile(),
        ];
        $impersonation = $this->impersonationFromSession($session);
        if (($impersonation['enabled'] ?? false) === true) {
            $payload['impersonation'] = $impersonation;
        } else {
            $payload['impersonation'] = ['enabled' => false];
        }

        return $payload;
    }

    private function beginAuthAudit(string $operation, RuntimeUserSession $session, array $impersonation): void
    {
        $this->transactions?->beginOperational([
            'tenantId' => $session->getTenantId(),
            'sessionId' => $session->getSessionId(),
            'screenId' => 'auth.impersonation',
            'endpointId' => $operation,
            'operation' => $operation,
            'source' => 'auth',
            'impersonation' => $impersonation,
        ]);
    }

    private function ensureSessionNotExpired(RuntimeUserSession $session): void
    {
        $impersonation = $this->impersonationFromSession($session);
        $expiresAt = trim((string) ($impersonation['expiresAt'] ?? ''));
        if (($impersonation['enabled'] ?? false) !== true || $expiresAt === '') {
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
        $this->transactions?->beginOperational([
            'tenantId' => $session->getTenantId(),
            'sessionId' => $session->getSessionId(),
            'screenId' => 'auth.impersonation',
            'endpointId' => 'auth.impersonation.expired',
            'operation' => 'auth.impersonation.expired',
            'source' => 'auth',
            'impersonation' => $impersonation,
        ]);
        $this->transactions?->log('auth.impersonation.expired', 'Sessao impersonada expirada.', metadata: $impersonation + [
            'sessionId' => $session->getSessionId(),
        ]);
        $this->transactions?->success();
        $this->transactions?->clear();
        throw new RuntimeHttpException('SESSION_EXPIRED', 'Sua sessao de simulacao expirou.', 401, [
            'impersonation' => $impersonation,
        ]);
    }

    private function impersonationFromSession(RuntimeUserSession $session): array
    {
        $properties = $session->getSessionProperties();
        if (is_array($properties['impersonation'] ?? null)) {
            return $properties['impersonation'];
        }
        $snapshot = $session->getPermissionSnapshot();
        return is_array($snapshot['impersonation'] ?? null) ? $snapshot['impersonation'] : ['enabled' => false];
    }

    private function sessionHasPermission(RuntimeUserSession $session, string $permission): bool
    {
        $snapshot = $session->getPermissionSnapshot();
        $groups = array_map('strval', is_array($snapshot['groups'] ?? null) ? $snapshot['groups'] : []);
        $permissions = is_array($snapshot['permissions'] ?? null) ? $snapshot['permissions'] : [];
        if (in_array('admin', $groups, true)) {
            return true;
        }
        return $this->hasResolvedPermission($permissions, $permission)
            || $this->hasResolvedPermission($permissions, 'admin.*')
            || $this->hasResolvedPermission($permissions, '*');
    }

    private function authUserIsAdmin(AuthUser $user): bool
    {
        if (in_array('admin', array_map('strval', $user->getGroups()), true)) {
            return true;
        }
        return $this->hasResolvedPermission($user->getPermissions(), 'admin')
            || $this->hasResolvedPermission($user->getPermissions(), 'admin.*')
            || $this->hasResolvedPermission($user->getPermissions(), '*');
    }

    private function withoutAuthToken(array $properties): array
    {
        unset($properties['authTokenHash']);
        if (is_array($properties['authentication'] ?? null)) {
            unset($properties['authentication']['tokenHash']);
        }

        return $properties;
    }

    private function revokeRememberTokenFromRequest(Request $request, string $reason): void
    {
        $tokenValue = '';
        try {
            $payload = json_decode($request->getContent() ?: '{}', true);
            if (is_array($payload)) {
                $tokenValue = trim((string) ($payload['rememberToken'] ?? ''));
            }
        } catch (\Throwable) {
            $tokenValue = '';
        }
        $tokenValue = $tokenValue ?: trim((string) $request->headers->get('X-Remember-Token', ''));
        if ($tokenValue === '') {
            return;
        }

        $this->revokeRememberTokenValue($tokenValue, $reason);
    }

    private function revokeRememberTokenValue(string $tokenValue, string $reason): void
    {
        $tokenValue = trim($tokenValue);
        if ($tokenValue === '') {
            return;
        }

        $token = $this->rememberTokens->findActiveByToken($tokenValue);
        if ($token) {
            $token->revoke($reason);
        }
    }

    private function oauthRedirectUri(Request $request, string $providerCode): string
    {
        return rtrim($request->getSchemeAndHttpHost(), '/') . '/api/auth/oauth/' . rawurlencode($providerCode) . '/callback';
    }

    private function requestHeaders(Request $request): array
    {
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = $values[0] ?? '';
        }

        return $headers;
    }

    private function clean(string $value, int $length): string
    {
        return mb_substr(preg_replace('/[^A-Za-z0-9_.:@ -]+/', '', trim($value)) ?: '', 0, $length);
    }

    private function detectDevice(Request $request): array
    {
        $userAgent = mb_substr((string) $request->headers->get('User-Agent', ''), 0, 1000);
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
        $deviceName = trim((string) ($request->headers->get('X-Runtime-Device-Name', '') ?: $request->headers->get('X-Device-Name', '')));
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

    private function getPhpSessionId(Request $request): ?string
    {
        if (!$request->hasSession()) {
            return null;
        }

        $session = $request->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }
        $phpSessionId = $session->getId();

        return $phpSessionId !== '' ? $phpSessionId : null;
    }
}
