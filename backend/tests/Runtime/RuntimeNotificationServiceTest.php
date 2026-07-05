<?php

namespace App\Tests\Runtime;

use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeNotificationService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;

class RuntimeNotificationServiceTest extends TestCase
{
    public function testAdministrativeNotificationPersistsBooleanActionRequiredWithExplicitDbalType(): void
    {
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['fetchOne', 'insert', 'lastInsertId', 'fetchAssociative'])
            ->getMock();
        $connection->method('fetchOne')->willReturn(false);
        $connection->expects(self::once())
            ->method('insert')
            ->with(
                'runtime_notification',
                self::callback(static fn (array $values): bool => ($values['action_required'] ?? null) === false),
                self::callback(static fn (array $types): bool => ($types['action_required'] ?? null) === Types::BOOLEAN),
            );
        $connection->method('lastInsertId')->willReturn(10);
        $connection->method('fetchAssociative')->willReturn(false);

        $permissions = $this->createStub(PermissionResolver::class);
        $permissions->method('getTenantId')->willReturn('default');
        $permissions->method('getUserId')->willReturn('codex');

        $service = new RuntimeNotificationService($connection, $permissions);

        self::assertSame(10, $service->createAdministrativeNotification(
            'Aprovacao final registrada',
            'A publicacao recebeu aprovacao final.',
            ['code' => 'governanca.approval.test']
        ));
    }
}
