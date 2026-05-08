<?php

namespace App\Tests\Runtime;

use App\Entity\RuntimeUserSession;
use App\Repository\RuntimeUserSessionRepository;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeSessionGuard;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class RuntimeSessionGuardTest extends TestCase
{
    public function testCreatesSessionWithDeviceAndPermissionSnapshot(): void
    {
        [$stack, $phpSession] = $this->requestStack(
            userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile Safari/604.1',
            deviceName: 'iPhone Ana',
        );

        $repository = $this->createMock(RuntimeUserSessionRepository::class);
        $repository->expects(self::once())
            ->method('findByTenantAndSession')
            ->with('default', 'sessao-1')
            ->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(RuntimeUserSession::class));
        $entityManager->expects(self::once())->method('flush');

        $guard = new RuntimeSessionGuard(
            $repository,
            $entityManager,
            new PermissionResolver($stack),
            $stack,
        );

        $session = $guard->ensureActive();

        self::assertSame('ana', $session->getUserId());
        self::assertSame('ana', $session->getUserName());
        self::assertSame('sessao-1', $session->getSessionId());
        self::assertSame($phpSession->getId(), $session->getPhpSessionId());
        self::assertSame('iPhone Ana', $session->getDeviceName());
        self::assertSame('iOS', $session->getOperatingSystem());
        self::assertSame('Safari', $session->getBrowser());
        self::assertTrue($session->isMobile());
        self::assertSame('sessao-1', $session->getSessionProperties()['runtimeSessionId']);
        self::assertSame('ana', $session->getPermissionSnapshot()['user']['id']);
        self::assertSame(['admin', 'vendas'], $session->getPermissionSnapshot()['groups']);
    }

    public function testRevokedSessionInvalidatesCurrentPhpSessionWhenRequested(): void
    {
        [$stack, $phpSession] = $this->requestStack();
        $session = (new RuntimeUserSession())
            ->setTenantId('default')
            ->setUserId('ana')
            ->setSessionId('sessao-1')
            ->setPhpSessionId($phpSession->getId())
            ->revoke('admin', 'Sessao encerrada pelo administrador.')
            ->markPhpSessionKillRequested();

        $repository = $this->createMock(RuntimeUserSessionRepository::class);
        $repository->expects(self::once())
            ->method('findByTenantAndSession')
            ->with('default', 'sessao-1')
            ->willReturn($session);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $guard = new RuntimeSessionGuard(
            $repository,
            $entityManager,
            new PermissionResolver($stack),
            $stack,
        );

        try {
            $guard->ensureActive();
            self::fail('Expected revoked session exception.');
        } catch (RuntimeHttpException $error) {
            self::assertSame('SESSION_REVOKED', $error->getErrorCode());
            self::assertTrue($error->getDetails()['phpSessionInvalidated']);
            self::assertTrue($session->getSessionProperties()['phpSessionInvalidated']);
        }
    }

    /**
     * @return array{0: RequestStack, 1: Session}
     */
    private function requestStack(string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0', string $deviceName = 'Desktop Ana'): array
    {
        $request = Request::create('/api/runtime/screens/home', 'GET', [
            'runtimeUserId' => 'ana',
            'runtimeSessionId' => 'sessao-1',
        ]);
        $request->headers->set('User-Agent', $userAgent);
        $request->headers->set('X-Runtime-Device-Name', $deviceName);

        $phpSession = new Session(new MockArraySessionStorage());
        $phpSession->start();
        $request->setSession($phpSession);

        $stack = new RequestStack();
        $stack->push($request);

        return [$stack, $phpSession];
    }
}
