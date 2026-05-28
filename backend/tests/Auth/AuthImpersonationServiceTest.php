<?php

namespace App\Tests\Auth;

use App\Auth\AuthenticatedSessionResolver;
use App\Auth\AuthProviderRegistry;
use App\Auth\AuthService;
use App\Entity\AuthUser;
use App\Entity\RuntimeUserSession;
use App\Repository\AuthLoginChallengeRepository;
use App\Repository\AuthPasswordResetTokenRepository;
use App\Repository\AuthProviderConfigRepository;
use App\Repository\AuthRememberTokenRepository;
use App\Repository\AuthSubscriberRepository;
use App\Repository\AuthUserRepository;
use App\Repository\AuthUserSubscriberRepository;
use App\Repository\RuntimeUserSessionRepository;
use App\Runtime\RuntimeHttpException;
use App\System\SystemParameterResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;

class AuthImpersonationServiceTest extends TestCase
{
    public function testAdminCanStartImpersonationWithoutRememberToken(): void
    {
        $service = $this->service($this->actorSession(['admin.impersonate']), $this->targetUser());

        $response = $service->startImpersonation([
            'targetTenantId' => 'default',
            'targetUsername' => 'joao',
            'reason' => 'Simular problema informado no chamado 123.',
            'remember' => true,
        ], Request::create('/api/auth/impersonate/start', 'POST'), false);

        self::assertTrue($response['authenticated']);
        self::assertArrayNotHasKey('rememberToken', $response);
        self::assertSame('joao', $response['user']['id']);
        self::assertTrue($response['session']['impersonation']['enabled']);
        self::assertSame('admin', $response['session']['impersonation']['actorUserId']);
        self::assertSame('joao', $response['session']['impersonation']['targetUserId']);
    }

    public function testStartImpersonationRequiresPermission(): void
    {
        $service = $this->service($this->actorSession([]), $this->targetUser());

        $this->expectException(RuntimeHttpException::class);
        $this->expectExceptionMessage('Voce nao possui permissao');

        $service->startImpersonation([
            'targetTenantId' => 'default',
            'targetUsername' => 'joao',
            'reason' => 'Suporte.',
        ], Request::create('/api/auth/impersonate/start', 'POST'), false);
    }

    public function testStartImpersonationRequiresReason(): void
    {
        $service = $this->service($this->actorSession(['admin.impersonate']), $this->targetUser());

        try {
            $service->startImpersonation([
                'targetTenantId' => 'default',
                'targetUsername' => 'joao',
            ], Request::create('/api/auth/impersonate/start', 'POST'), false);
            self::fail('Expected exception.');
        } catch (RuntimeHttpException $error) {
            self::assertSame('IMPERSONATION_REASON_REQUIRED', $error->getErrorCode());
            self::assertSame(422, $error->getStatusCode());
        }
    }

    public function testInactiveTargetIsBlocked(): void
    {
        $service = $this->service($this->actorSession(['admin.impersonate']), $this->targetUser('inactive'));

        try {
            $service->startImpersonation([
                'targetTenantId' => 'default',
                'targetUsername' => 'joao',
                'reason' => 'Suporte.',
            ], Request::create('/api/auth/impersonate/start', 'POST'), false);
            self::fail('Expected exception.');
        } catch (RuntimeHttpException $error) {
            self::assertSame('IMPERSONATION_TARGET_INACTIVE', $error->getErrorCode());
        }
    }

    public function testAdminTargetRequiresAdditionalPermission(): void
    {
        $target = $this->targetUser(groups: ['admin']);
        $service = $this->service($this->actorSession(['admin.impersonate']), $target);

        try {
            $service->startImpersonation([
                'targetTenantId' => 'default',
                'targetUsername' => 'joao',
                'reason' => 'Suporte.',
            ], Request::create('/api/auth/impersonate/start', 'POST'), false);
            self::fail('Expected exception.');
        } catch (RuntimeHttpException $error) {
            self::assertSame('IMPERSONATION_ADMIN_TARGET_FORBIDDEN', $error->getErrorCode());
        }
    }

    public function testStopRevokesOnlyCurrentImpersonatedSession(): void
    {
        $session = $this->actorSession(['clientes.read']);
        $session->setSessionProperties([
            'authTokenHash' => hash('sha256', 'token'),
            'impersonation' => [
                'enabled' => true,
                'actorUserId' => 'admin',
                'actorUserName' => 'Administrador',
                'actorSessionId' => 'sess-admin',
                'targetUserId' => 'joao',
                'targetUserName' => 'Joao',
                'reason' => 'Suporte.',
                'startedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'expiresAt' => (new \DateTimeImmutable('+60 minutes'))->format(DATE_ATOM),
            ],
        ]);
        $service = $this->service($session, $this->targetUser());

        $response = $service->stopImpersonation(Request::create('/api/auth/impersonate/stop', 'POST'), false);

        self::assertTrue($response['impersonationStopped']);
        self::assertSame('revoked', $session->getStatus());
        self::assertSame('admin', $session->getRevokedBy());
        self::assertArrayNotHasKey('authTokenHash', $session->getSessionProperties());
    }

    /**
     * @param string[] $permissions
     */
    private function actorSession(array $permissions): RuntimeUserSession
    {
        return (new RuntimeUserSession())
            ->setTenantId('default')
            ->setUserId('admin')
            ->setUserName('Administrador')
            ->setSessionId('sess-admin')
            ->setStatus('active')
            ->setSessionProperties(['authTokenHash' => hash('sha256', 'admin-token')])
            ->setPermissionSnapshot([
                'groups' => [],
                'permissions' => $permissions,
                'user' => [
                    'id' => 'admin',
                    'username' => 'admin',
                    'name' => 'Administrador',
                ],
            ]);
    }

    /**
     * @param string[] $groups
     */
    private function targetUser(string $status = 'active', array $groups = []): AuthUser
    {
        return (new AuthUser())
            ->setTenantId('default')
            ->setUsername('joao')
            ->setDisplayName('Joao')
            ->setEmail('joao@example.com')
            ->setStatus($status)
            ->setGroups($groups)
            ->setPermissions(['clientes.read'])
            ->setAuthSource('local');
    }

    private function service(RuntimeUserSession $actorSession, AuthUser $targetUser): AuthService
    {
        $users = $this->createStub(AuthUserRepository::class);
        $users->method('findOneByTenantAndUsername')->willReturn($targetUser);

        $sessions = $this->createStub(RuntimeUserSessionRepository::class);
        $sessions->method('findByTenantAndSession')->willReturn(null);

        $authenticated = $this->createStub(AuthenticatedSessionResolver::class);
        $authenticated->method('resolve')->willReturn($actorSession);

        $parameters = $this->createStub(SystemParameterResolver::class);
        $parameters->method('getBoolean')->willReturn(false);

        $entityManager = $this->createStub(EntityManagerInterface::class);

        return new AuthService(
            $this->createStub(AuthProviderConfigRepository::class),
            $users,
            $this->createStub(AuthRememberTokenRepository::class),
            $sessions,
            $this->createStub(AuthSubscriberRepository::class),
            $this->createStub(AuthUserSubscriberRepository::class),
            $this->createStub(AuthLoginChallengeRepository::class),
            $this->createStub(AuthPasswordResetTokenRepository::class),
            $entityManager,
            $this->createStub(AuthProviderRegistry::class),
            $authenticated,
            $parameters,
            $this->createStub(MailerInterface::class),
        );
    }
}
