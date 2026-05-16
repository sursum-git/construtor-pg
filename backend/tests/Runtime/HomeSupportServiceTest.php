<?php

namespace App\Tests\Runtime;

use App\Entity\AuthUser;
use App\Entity\RuntimeUserMessage;
use App\Entity\RuntimeUserSession;
use App\Repository\AuthUserRepository;
use App\Repository\RuntimeUserMessageRepository;
use App\Repository\RuntimeUserSessionRepository;
use App\Runtime\HomeSupportService;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeTransactionService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class HomeSupportServiceTest extends TestCase
{
    public function testChatEventsReturnsNewMessagesAndMarksPendingAsDelivered(): void
    {
        $request = Request::create('/api/runtime/screens/home/endpoints/home.chat.events', 'GET', [
            'runtimeTenantId' => 'default',
            'runtimeUserId' => 'alice',
            'runtimeUserName' => 'Alice',
            'runtimePermissions' => 'admin.read',
        ]);
        $permissions = new PermissionResolver((function () use ($request) {
            $stack = new RequestStack();
            $stack->push($request);
            return $stack;
        })());

        $message = (new RuntimeUserMessage())
            ->setTenantId('default')
            ->setSenderUserId('bob')
            ->setSenderUserName('Bob')
            ->setTargetUserId('alice')
            ->setType('chat')
            ->setSeverity('info')
            ->setTitle('Chat')
            ->setMessage('Oi Alice')
            ->setStatus('pending');
        $this->setEntityId($message, 9);

        $messages = $this->createMock(RuntimeUserMessageRepository::class);
        $messages->expects(self::once())
            ->method('findConversationAfterId')
            ->with('default', 'alice', 'bob', ['chat'], 3)
            ->willReturn([$message]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $service = new HomeSupportService(
            $this->createStub(Connection::class),
            $entityManager,
            $permissions,
            $this->createStub(RuntimeTransactionService::class),
            $messages,
            $this->createStub(RuntimeUserSessionRepository::class),
            $this->createStub(AuthUserRepository::class),
        );

        $response = $service->chatEvents('bob', 3);

        self::assertSame('bob', $response['recipientId']);
        self::assertCount(1, $response['messages']);
        self::assertSame(9, $response['messages'][0]['messageId']);
        self::assertSame('Oi Alice', $response['messages'][0]['text']);
        self::assertSame('delivered', $message->getStatus());
    }

    public function testSupportEventsReturnsPresenceAndProtocolStatus(): void
    {
        $request = Request::create('/api/runtime/screens/home/endpoints/home.support.events', 'GET', [
            'runtimeTenantId' => 'default',
            'runtimeUserId' => 'alice',
            'runtimeUserName' => 'Alice',
            'runtimePermissions' => 'admin.read',
        ]);
        $permissions = new PermissionResolver((function () use ($request) {
            $stack = new RequestStack();
            $stack->push($request);
            return $stack;
        })());

        $session = (new RuntimeUserSession())
            ->setTenantId('default')
            ->setUserId('support.user')
            ->setUserName('Suporte Financeiro')
            ->setStatus('active');

        $supportUser = (new AuthUser())
            ->setTenantId('default')
            ->setUsername('support.user')
            ->setDisplayName('Suporte Financeiro')
            ->setEmail('support@example.com')
            ->setGroups(['support.financeiro']);

        $sessions = $this->createMock(RuntimeUserSessionRepository::class);
        $sessions->expects(self::once())
            ->method('findActiveByTenant')
            ->with('default', 'alice')
            ->willReturn([$session]);

        $users = $this->createMock(AuthUserRepository::class);
        $users->expects(self::once())
            ->method('findActiveByTenantAndUsernames')
            ->with('default', ['support.user'])
            ->willReturn([$supportUser]);

        $messages = $this->createMock(RuntimeUserMessageRepository::class);
        $messages->expects(self::once())
            ->method('findConversationAfterId')
            ->with('default', 'alice', 'support.user', ['support_chat'], 0)
            ->willReturn([]);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('fetchAssociative')
            ->willReturn([
                'protocol' => 'ATD-1',
                'status' => 'open',
                'assigned_user_id' => 'support.user',
                'assigned_user_name' => 'Suporte Financeiro',
                'updated_at' => '2026-05-14 22:10:00',
            ]);

        $service = new HomeSupportService(
            $connection,
            $this->createStub(EntityManagerInterface::class),
            $permissions,
            $this->createStub(RuntimeTransactionService::class),
            $messages,
            $sessions,
            $users,
        );

        $response = $service->supportEvents('support.user', 'financeiro', 'ATD-1', 0);

        self::assertSame('support.user', $response['attendantId']);
        self::assertCount(1, $response['onlineUsers']);
        self::assertSame('financeiro', $response['onlineUsers'][0]['sectorId']);
        self::assertSame('ATD-1', $response['requestStatus']['protocol']);
        self::assertSame('open', $response['requestStatus']['status']);
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionObject($entity);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }
}
