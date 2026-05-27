<?php

namespace App\Tests\Builder;

use App\Builder\BuilderAiSessionService;
use App\Builder\ExternalBuilderContextService;
use App\Entity\RuntimeAiSession;
use App\Repository\RuntimeAiMessageRepository;
use App\Repository\RuntimeAiSessionRepository;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeHttpException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class BuilderAiSessionServiceTest extends TestCase
{
    public function testRequireOwnedActiveRejectsSessionFromAnotherUser(): void
    {
        $session = (new RuntimeAiSession())
            ->setSessionId('builder-ai-test')
            ->setTenantId('default')
            ->setUserId('outro')
            ->setPurpose('program_builder')
            ->setExpiresAt(new \DateTimeImmutable('+1 hour'));

        $service = $this->service($session, 'default', 'demo');

        $this->expectException(RuntimeHttpException::class);
        $service->requireOwnedActive('builder-ai-test');
    }

    public function testRequireOwnedActiveExpiresSession(): void
    {
        $session = (new RuntimeAiSession())
            ->setSessionId('builder-ai-test')
            ->setTenantId('default')
            ->setUserId('demo')
            ->setPurpose('program_builder')
            ->setExpiresAt(new \DateTimeImmutable('-1 minute'));

        $service = $this->service($session, 'default', 'demo');

        try {
            $service->requireOwnedActive('builder-ai-test');
            self::fail('Expected expired session exception.');
        } catch (RuntimeHttpException) {
            self::assertSame('expired', $session->getStatus());
        }
    }

    public function testForceNewClosesPreviousOwnedSession(): void
    {
        $session = (new RuntimeAiSession())
            ->setSessionId('builder-ai-test')
            ->setTenantId('default')
            ->setUserId('demo')
            ->setPurpose('program_builder')
            ->setExpiresAt(new \DateTimeImmutable('+1 hour'));

        $service = $this->service($session, 'default', 'demo');

        $newSession = $service->startOrResume([
            'sessionId' => 'builder-ai-test',
            'forceNew' => true,
        ]);

        self::assertSame('closed', $session->getStatus());
        self::assertNotSame('builder-ai-test', $newSession->getSessionId());
    }

    private function service(RuntimeAiSession $session, string $tenantId, string $userId): BuilderAiSessionService
    {
        $sessions = $this->createStub(RuntimeAiSessionRepository::class);
        $sessions->method('findOneBySessionId')->willReturn($session);

        $permissions = $this->createStub(PermissionResolver::class);
        $permissions->method('getTenantId')->willReturn($tenantId);
        $permissions->method('getUserId')->willReturn($userId);
        $permissions->method('getCurrentUserPayload')->willReturn(['id' => $userId]);

        $externalContext = $this->createStub(ExternalBuilderContextService::class);
        $externalContext->method('buildContextPayload')->willReturn([
            'catalog' => ['version' => 'test', 'capabilities' => []],
        ]);

        return new BuilderAiSessionService(
            $sessions,
            $this->createStub(RuntimeAiMessageRepository::class),
            $this->createStub(EntityManagerInterface::class),
            $permissions,
            $externalContext,
        );
    }
}
