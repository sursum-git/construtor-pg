<?php

namespace App\Tests\Runtime;

use App\Builder\BuilderAiSettingsService;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeBusinessRuleRegistry;
use App\Runtime\RuntimeConcurrencyGuard;
use App\Runtime\RuntimeConfiguredRuleExecutor;
use App\Runtime\RuntimeCustomCodeService;
use App\Runtime\RuntimeEntityActionService;
use App\Runtime\RuntimeEventService;
use App\Runtime\RuntimeEntityDefinitionResolver;
use App\Runtime\RuntimeEntityVersionService;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeLockService;
use App\Runtime\RuntimeNotificationService;
use App\Runtime\RuntimeSituationService;
use App\Runtime\RuntimeTransactionService;
use App\Runtime\StructuralIntegrityService;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

class RuntimeEntityActionServiceSubscriberIsolationTest extends TestCase
{
    public function testReadReturnsOnlyCurrentSubscriberRecords(): void
    {
        $service = $this->createService('tenant-a');

        $response = $service->handle('cadastros.pedidos', 'read', [
            'entityCode' => 'pedido',
            'operation' => 'read',
        ], []);

        self::assertSame(2, $response['total']);
        self::assertSame(['Pedido A', 'Pedido B'], array_column($response['data'], 'nome'));
    }

    public function testCreateInjectsCurrentSubscriberIntoRow(): void
    {
        $service = $this->createService('tenant-a');

        $response = $service->handle('cadastros.pedidos', 'create', [
            'entityCode' => 'pedido',
            'operation' => 'create',
            'actionId' => 'create',
        ], [
            'values' => [
                'nome' => 'Pedido Novo',
                'subscriberId' => 'tenant-b',
            ],
        ]);

        self::assertSame('tenant-a', $response['subscriberId']);
        self::assertSame('Pedido Novo', $response['nome']);
    }

    public function testUpdateDoesNotCrossSubscriberBoundary(): void
    {
        $service = $this->createService('tenant-a');

        $this->expectException(RuntimeHttpException::class);
        $this->expectExceptionMessage('Registro nao encontrado.');

        $service->handle('cadastros.pedidos', 'update', [
            'entityCode' => 'pedido',
            'operation' => 'update',
            'actionId' => 'update',
        ], [
            'id' => 3,
            'values' => [
                'nome' => 'Pedido Invadido',
            ],
        ]);
    }

    public function testDeleteDoesNotCrossSubscriberBoundary(): void
    {
        $service = $this->createService('tenant-a');

        $this->expectException(RuntimeHttpException::class);
        $this->expectExceptionMessage('Registro nao encontrado.');

        $service->handle('cadastros.pedidos', 'delete', [
            'entityCode' => 'pedido',
            'operation' => 'delete',
            'actionId' => 'delete',
        ], [
            'id' => 3,
        ]);
    }

    public function testSoftDeleteMarksRowAndReadHidesDeletedRecords(): void
    {
        $connection = null;
        $service = $this->createService('tenant-a', true, $connection);

        $initial = $service->handle('cadastros.pedidos', 'read', [
            'entityCode' => 'pedido',
            'operation' => 'read',
        ], []);

        self::assertSame(2, $initial['total']);

        $service->handle('cadastros.pedidos', 'delete', [
            'entityCode' => 'pedido',
            'operation' => 'delete',
            'actionId' => 'delete',
        ], [
            'id' => 1,
            'deleteReason' => 'Solicitacao LGPD',
        ]);

        $row = $connection->fetchAssociative('SELECT deleted_at, deleted_by, delete_reason FROM pedido WHERE id = 1');
        self::assertNotEmpty($row['deleted_at']);
        self::assertSame('user-a', $row['deleted_by']);
        self::assertSame('Solicitacao LGPD', $row['delete_reason']);

        $after = $service->handle('cadastros.pedidos', 'read', [
            'entityCode' => 'pedido',
            'operation' => 'read',
        ], []);

        self::assertSame(1, $after['total']);
        self::assertSame(['Pedido B'], array_column($after['data'], 'nome'));
    }

    private function createService(string $tenantId, bool $softDelete = false, mixed &$connectionOut = null): RuntimeEntityActionService
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $connection->executeStatement('CREATE TABLE pedido (id INTEGER PRIMARY KEY AUTOINCREMENT, subscriber_id TEXT NOT NULL, nome TEXT NOT NULL, deleted_at TEXT DEFAULT NULL, deleted_by TEXT DEFAULT NULL, delete_reason TEXT DEFAULT NULL)');
        $connection->insert('pedido', ['subscriber_id' => 'tenant-a', 'nome' => 'Pedido A']);
        $connection->insert('pedido', ['subscriber_id' => 'tenant-a', 'nome' => 'Pedido B']);
        $connection->insert('pedido', ['subscriber_id' => 'tenant-b', 'nome' => 'Pedido C']);
        if ($softDelete) {
            $connection->insert('pedido', ['subscriber_id' => 'tenant-a', 'nome' => 'Pedido Excluido', 'deleted_at' => '2026-01-01 10:00:00']);
        }
        $connectionOut = $connection;

        $definitions = $this->createStub(RuntimeEntityDefinitionResolver::class);
        $definitions->method('resolve')->willReturn([
            'entityCode' => 'pedido',
            'tableName' => 'pedido',
            'quotedTableName' => '"pedido"',
            'primaryKey' => 'id',
            'primaryColumn' => 'id',
            'dbColumns' => [
                'id' => true,
                'subscriber_id' => true,
                'nome' => true,
                'deleted_at' => true,
                'deleted_by' => true,
                'delete_reason' => true,
            ],
            'subscriberIsolation' => [
                'enabled' => true,
                'mode' => 'subscriber_column',
                'column' => 'subscriber_id',
                'globalTable' => false,
            ],
            'fields' => [
                'id' => [
                    'code' => 'id',
                    'column' => 'id',
                    'label' => 'ID',
                    'dataType' => 'integer',
                    'writable' => false,
                    'readable' => true,
                    'required' => false,
                    'virtual' => false,
                ],
                'subscriberId' => [
                    'code' => 'subscriberId',
                    'column' => 'subscriber_id',
                    'label' => 'Assinante',
                    'dataType' => 'string',
                    'writable' => true,
                    'readable' => true,
                    'required' => true,
                    'virtual' => false,
                ],
                'nome' => [
                    'code' => 'nome',
                    'column' => 'nome',
                    'label' => 'Nome',
                    'dataType' => 'string',
                    'writable' => true,
                    'readable' => true,
                    'required' => true,
                    'virtual' => false,
                ],
                'deletedAt' => [
                    'code' => 'deletedAt',
                    'column' => 'deleted_at',
                    'label' => 'Excluido em',
                    'dataType' => 'datetime',
                    'writable' => false,
                    'readable' => true,
                    'required' => false,
                    'virtual' => false,
                ],
                'deletedBy' => [
                    'code' => 'deletedBy',
                    'column' => 'deleted_by',
                    'label' => 'Excluido por',
                    'dataType' => 'string',
                    'writable' => false,
                    'readable' => true,
                    'required' => false,
                    'virtual' => false,
                ],
                'deleteReason' => [
                    'code' => 'deleteReason',
                    'column' => 'delete_reason',
                    'label' => 'Motivo da exclusao',
                    'dataType' => 'string',
                    'writable' => false,
                    'readable' => true,
                    'required' => false,
                    'virtual' => false,
                ],
            ],
            'softDelete' => $softDelete ? [
                'enabled' => true,
                'deletedAtField' => 'deletedAt',
                'deletedAtColumn' => 'deleted_at',
                'deletedByField' => 'deletedBy',
                'deletedByColumn' => 'deleted_by',
                'reasonField' => 'deleteReason',
                'reasonColumn' => 'delete_reason',
            ] : ['enabled' => false],
        ]);

        $entityVersions = $this->createStub(RuntimeEntityVersionService::class);
        $entityVersions->method('snapshot')->willReturn(null);

        $customCodes = $this->createStub(RuntimeCustomCodeService::class);
        $customCodes->method('applyCreateValues')->willReturnCallback(static fn (array $definition, array $values, array $payload): array => $values);

        $locks = $this->createStub(RuntimeLockService::class);
        $concurrency = $this->createStub(RuntimeConcurrencyGuard::class);

        $transactions = $this->createStub(RuntimeTransactionService::class);

        $rules = $this->createStub(RuntimeBusinessRuleRegistry::class);
        $configuredRules = new RuntimeConfiguredRuleExecutor($transactions);

        $situations = $this->createMock(RuntimeSituationService::class);
        $situations->method('applyCreateDefaults')->willReturnCallback(static fn (array $definition, array $values): array => $values);
        $situations->method('validateCreate')->willReturn(null);
        $situations->method('validateUpdate')->willReturn(null);
        $situations->method('applyTransitionEffects')->willReturnCallback(static fn (array $after, ?array $transition): array => $after);
        $situations->method('decorateRow')->willReturnCallback(static fn (array $definition, array $row): array => $row);

        $builderAiSettings = $this->createStub(BuilderAiSettingsService::class);
        $notifications = $this->createStub(RuntimeNotificationService::class);
        $integrity = $this->createStub(StructuralIntegrityService::class);

        $permissions = $this->createStub(PermissionResolver::class);
        $permissions->method('getTenantId')->willReturn($tenantId);
        $permissions->method('getUserId')->willReturn('user-a');
        $events = $this->createStub(RuntimeEventService::class);

        return new RuntimeEntityActionService(
            $definitions,
            $connection,
            $entityVersions,
            $customCodes,
            $locks,
            $concurrency,
            $transactions,
            $rules,
            $configuredRules,
            $situations,
            $builderAiSettings,
            $notifications,
            $integrity,
            $permissions,
            $events,
        );
    }
}
