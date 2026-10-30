<?php
namespace App\Tests\Runtime;

use App\Auth\AuthenticatedSessionResolver;
use App\Entity\RuntimeTransactionLog;
use App\Entity\RuntimeUserSession;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeEntityDefinitionResolver;
use App\Runtime\RuntimeEnvironmentIdentityResolver;
use App\Runtime\RuntimeExecutionContext;
use App\Runtime\RuntimeTransactionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class RuntimeAuditActorTest extends TestCase
{
    public function testBeginAndLogsUseAuthenticatedActorAndRejectPayloadAndMetadataSpoofing(): void
    {
        [$service, $logs] = $this->service(true);
        $transaction = $service->begin('screen', 'update', 'entity.api.crud', ['actor' => ['source' => 'authenticated-session', 'version' => 1, 'userId' => 'forged'], 'context' => ['actor' => ['userId' => 'forged']]]);
        $service->log('test', metadata: ['actor' => ['userId' => 'forged', 'password' => 'private']]);
        $expected = ['source' => 'authenticated-session', 'version' => 1, 'userId' => 'admin', 'username' => 'admin.login', 'displayName' => 'Administrador'];
        self::assertSame($expected, $transaction->getRequestContext()['actor']);
        self::assertSame($expected, $logs->items[1]->getMetadata()['actor']);
        self::assertStringNotContainsString('private', json_encode($logs->items[1]->getMetadata()));
    }

    public function testOperationalBeginCapturesTheSameAuthenticatedActor(): void
    {
        [$service, $logs] = $this->service(true);
        $transaction = $service->beginOperational(['actor' => ['userId' => 'forged'], 'traceability' => ['actor' => ['source' => 'authenticated-session', 'version' => 1, 'userId' => 'forged-trace']]]);
        self::assertSame('admin', $transaction->getRequestContext()['actor']['userId']);
        self::assertSame($transaction->getRequestContext()['actor'], $logs->items[0]->getMetadata()['actor']);
    }

    public function testUnauthenticatedHeaderFallbackDoesNotBecomeAnAuditActor(): void
    {
        [$service, $logs] = $this->service(false);
        $transaction = $service->beginOperational(['actor' => ['userId' => 'forged'], 'traceability' => ['actor' => ['source' => 'authenticated-session', 'version' => 1, 'userId' => 'forged-trace']]]);
        $service->log('test', metadata: ['actor' => ['userId' => 'forged']]);
        self::assertArrayNotHasKey('actor', $transaction->getRequestContext());
        self::assertArrayNotHasKey('actor', $logs->items[1]->getMetadata());
    }

    private function service(bool $authenticated): array
    {
        $session = (new RuntimeUserSession())->setUserId('admin')->setUserName('Administrador')->setStatus('active')
            ->setPermissionSnapshot(['user' => ['id' => 'admin', 'username' => 'admin.login', 'name' => 'Administrador', 'password' => 'private']]);
        $resolver = $this->createStub(AuthenticatedSessionResolver::class);
        $resolver->method('resolve')->willReturn($authenticated ? $session : null);
        $stack = new RequestStack();
        $request = Request::create('/');
        $request->headers->set('X-Runtime-User-Id', 'forged-header');
        $stack->push($request);
        $logs = new \stdClass(); $logs->items = [];
        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('persist')->willReturnCallback(static function ($item) use ($logs): void {
            if ($item instanceof RuntimeTransactionLog) { $logs->items[] = $item; }
        });
        return [new RuntimeTransactionService($manager, new PermissionResolver($stack, $resolver), new RuntimeExecutionContext(),
            $this->createStub(RuntimeEntityDefinitionResolver::class), $this->createStub(RuntimeEnvironmentIdentityResolver::class), $resolver), $logs];
    }
}
