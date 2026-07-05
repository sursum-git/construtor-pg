<?php

namespace App\Tests\Auth;

use App\Auth\AuthenticatedSessionResolver;
use App\Auth\AuthProviderRegistry;
use App\Auth\AuthService;
use App\Command\CreateSubscriberCommand;
use App\Entity\AuthPasswordResetToken;
use App\Entity\AuthSubscriber;
use App\Entity\AuthUser;
use App\Entity\AuthUserSubscriber;
use App\Install\InstallationActivationService;
use App\Install\SystemInstallService;
use App\Repository\AuthLoginChallengeRepository;
use App\Repository\AuthPasswordResetTokenRepository;
use App\Repository\AuthProviderConfigRepository;
use App\Repository\AuthRememberTokenRepository;
use App\Repository\AuthSubscriberRepository;
use App\Repository\AuthUserRepository;
use App\Repository\AuthUserSubscriberRepository;
use App\Repository\RuntimeUserSessionRepository;
use App\Runtime\RuntimeHttpException;
use App\Runtime\StructuralIntegrityService;
use App\System\SystemParameterResolver;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

class AuthSecurityHardeningTest extends TestCase
{
    private array $originalEnv = [];

    protected function setUp(): void
    {
        foreach (['APP_ENV', 'APP_AUTH_EXPOSE_RESET_TOKEN'] as $name) {
            $this->originalEnv[$name] = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name) ?: null;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $name => $value) {
            if ($value === null) {
                unset($_SERVER[$name], $_ENV[$name]);
                putenv($name);
                continue;
            }
            $_SERVER[$name] = $value;
            $_ENV[$name] = $value;
            putenv($name . '=' . $value);
        }
    }

    public function testPasswordResetRequiresStrongPasswordPolicy(): void
    {
        $token = (new AuthPasswordResetToken())
            ->setUserTenantId('default')
            ->setUsername('admin')
            ->setTokenHash(hash('sha256', 'reset-token'))
            ->setExpiresAt(new \DateTimeImmutable('+30 minutes'));
        $user = $this->localUser();
        $service = $this->authService(user: $user, activeToken: $token);

        try {
            $service->resetPassword([
                'resetToken' => 'reset-token',
                'password' => 'Abc12345',
            ]);
            self::fail('Senha fraca deveria ser rejeitada.');
        } catch (RuntimeHttpException $error) {
            self::assertSame(422, $error->getStatusCode());
            self::assertSame('PASSWORD_POLICY_WEAK', $error->getErrorCode());
        }
    }

    public function testPasswordResetTokenIsNotExposedWithoutExplicitFlag(): void
    {
        $this->setEnv('APP_ENV', 'dev');
        $this->setEnv('APP_AUTH_EXPOSE_RESET_TOKEN', '');
        $service = $this->authService(user: $this->localUser());

        $response = $service->requestPasswordReset([
            'identity' => 'admin@example.com',
        ], Request::create('https://app.test/api/auth/password/request-reset', 'POST'));

        self::assertTrue($response['ok']);
        self::assertArrayNotHasKey('resetToken', $response);
    }

    public function testPasswordResetTokenCanBeExposedWhenExplicitlyEnabled(): void
    {
        $this->setEnv('APP_ENV', 'dev');
        $this->setEnv('APP_AUTH_EXPOSE_RESET_TOKEN', '1');
        $service = $this->authService(user: $this->localUser());

        $response = $service->requestPasswordReset([
            'identity' => 'admin@example.com',
        ], Request::create('https://app.test/api/auth/password/request-reset', 'POST'));

        self::assertTrue($response['ok']);
        self::assertNotEmpty($response['resetToken'] ?? '');
    }

    public function testPasswordResetEmailDoesNotPutTokenInUrl(): void
    {
        $mailer = new class implements MailerInterface {
            public ?Email $email = null;

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                if ($message instanceof Email) {
                    $this->email = $message;
                }
            }
        };
        $service = $this->authService(user: $this->localUser(), mailer: $mailer);

        $response = $service->requestPasswordReset([
            'identity' => 'admin@example.com',
        ], Request::create('https://app.test/api/auth/password/request-reset', 'POST'));

        $body = $mailer->email?->getTextBody() ?? '';
        self::assertTrue($response['ok']);
        self::assertNotNull($mailer->email);
        self::assertStringContainsString('/production/login.html?reset=1', $body);
        self::assertStringContainsString('Codigo de recuperacao:', $body);
        self::assertStringNotContainsString('resetToken=', $body);
    }

    public function testSubscriberCreatePreservesExistingForcePasswordChangeWhenOptionIsOmitted(): void
    {
        $subscriber = (new AuthSubscriber())->setCode('cliente-a')->setName('Cliente A');
        $user = $this->localUser()->setForcePasswordChange(true);

        $command = $this->subscriberCommand($subscriber, $user);
        $tester = new CommandTester($command);
        $tester->execute([
            '--code' => 'cliente-a',
            '--name' => 'Cliente A',
            '--admin-username' => 'admin',
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertTrue($user->mustChangePassword());
    }

    public function testSystemInstallSubscriberCommandDoesNotExposeAdminPasswordInArguments(): void
    {
        $service = new SystemInstallService(
            $this->createStub(KernelInterface::class),
            $this->createStub(Connection::class),
            $this->createStub(InstallationActivationService::class),
        );
        $method = new \ReflectionMethod($service, 'subscriberCommand');
        $method->setAccessible(true);

        $command = $method->invoke($service, [
            'subscriberCode' => 'cliente-a',
            'subscriberName' => 'Cliente A',
            'userTenantId' => 'default',
            'adminUsername' => 'admin',
            'adminPassword' => 'Admin!123456789A',
            'adminDisplayName' => 'Administrador',
            'subscriberDocument' => '',
            'adminEmail' => '',
            'principal' => true,
            'forcePasswordChange' => true,
        ]);

        self::assertNotContains('--admin-password=Admin!123456789A', $command);
        self::assertContains('--admin-password-env=CONSTRUTOR_PG_ADMIN_PASSWORD', $command);
    }

    private function authService(?AuthUser $user = null, ?AuthPasswordResetToken $activeToken = null, ?MailerInterface $mailer = null): AuthService
    {
        $users = $this->createStub(AuthUserRepository::class);
        $users->method('findOneForPasswordReset')->willReturn($user);
        $users->method('findOneByTenantAndUsername')->willReturn($user);

        $tokens = $this->createStub(AuthPasswordResetTokenRepository::class);
        $tokens->method('findActiveForUser')->willReturn([]);
        $tokens->method('findActiveByToken')->willReturn($activeToken);

        $parameters = $this->createStub(SystemParameterResolver::class);
        $parameters->method('getBoolean')->willReturn(false);

        return new AuthService(
            $this->createStub(AuthProviderConfigRepository::class),
            $users,
            $this->createStub(AuthRememberTokenRepository::class),
            $this->createStub(RuntimeUserSessionRepository::class),
            $this->createStub(AuthSubscriberRepository::class),
            $this->createStub(AuthUserSubscriberRepository::class),
            $this->createStub(AuthLoginChallengeRepository::class),
            $tokens,
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(AuthProviderRegistry::class),
            $this->createStub(AuthenticatedSessionResolver::class),
            $parameters,
            $mailer ?? $this->createStub(MailerInterface::class),
        );
    }

    private function subscriberCommand(AuthSubscriber $subscriber, AuthUser $user): CreateSubscriberCommand
    {
        $subscribers = $this->createStub(AuthSubscriberRepository::class);
        $subscribers->method('findOneBy')->willReturn($subscriber);
        $subscribers->method('findAll')->willReturn([$subscriber]);

        $users = $this->createStub(AuthUserRepository::class);
        $users->method('findOneByTenantAndUsername')->willReturn($user);

        $userSubscribers = $this->createStub(AuthUserSubscriberRepository::class);
        $userSubscribers->method('findOneBy')->willReturn(new AuthUserSubscriber());
        $userSubscribers->method('findEnabledForUser')->willReturn([]);

        return new CreateSubscriberCommand(
            $this->createStub(EntityManagerInterface::class),
            $subscribers,
            $users,
            $userSubscribers,
            $this->createStub(StructuralIntegrityService::class),
        );
    }

    private function localUser(): AuthUser
    {
        return (new AuthUser())
            ->setTenantId('default')
            ->setUsername('admin')
            ->setDisplayName('Administrador')
            ->setEmail('admin@example.com')
            ->setStatus('active')
            ->setGroups(['admin'])
            ->setPermissions(['*'])
            ->setAuthSource('local');
    }

    private function setEnv(string $name, string $value): void
    {
        $_SERVER[$name] = $value;
        $_ENV[$name] = $value;
        putenv($value === '' ? $name : $name . '=' . $value);
    }
}
