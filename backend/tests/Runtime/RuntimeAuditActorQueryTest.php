<?php
namespace App\Tests\Runtime;

use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeEntityActionService;
use App\Runtime\RuntimeEntityDefinitionResolver;
use App\Runtime\RuntimeSituationService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class RuntimeAuditActorQueryTest extends TestCase
{
    public function testReadAndGetExposeStoredActorWithoutInventingHistoricalUsers(): void
    {
        $service = $this->service();
        $rows = $service->handle('admin.transacoes', 'read', ['entityCode' => 'runtime_transaction', 'operation' => 'read'], ['sort' => [['field' => 'id', 'dir' => 'asc']]])['data'];
        self::assertCount(2, $rows);
        self::assertSame('admin', $rows[0]['actor_user_id']);
        self::assertSame('Administrador', $rows[0]['actor_user_name']);
        self::assertNull($rows[1]['actor_user_id']);
        self::assertNull($rows[1]['actor_user_name']);
        $row = $service->handle('admin.transacoes', 'get', ['entityCode' => 'runtime_transaction', 'operation' => 'get'], ['id' => 1]);
        self::assertSame('Administrador', $row['actor_user_name']);
    }

    public function testLogsUseOwnActorThenParentOnlyWithinTheCurrentTenant(): void
    {
        $service = $this->service();
        $rows = $service->handle('admin.logs-transacoes', 'read', ['entityCode' => 'runtime_transaction_log', 'operation' => 'read'], ['sort' => [['field' => 'id', 'dir' => 'asc']]])['data'];
        self::assertSame(['operator', 'admin', null], array_column($rows, 'actor_user_id'));
        self::assertSame('Operador', $rows[0]['actor_user_name']);
        self::assertSame('Administrador', $rows[1]['actor_user_name']);
        self::assertNull($rows[2]['actor_user_name']);
        $row = $service->handle('admin.logs-transacoes', 'get', ['entityCode' => 'runtime_transaction_log', 'operation' => 'get'], ['id' => 2]);
        self::assertSame('admin', $row['actor_user_id']);
        $this->expectException(\App\Runtime\RuntimeHttpException::class);
        $service->handle('admin.logs-transacoes', 'get', ['entityCode' => 'runtime_transaction_log', 'operation' => 'get'], ['id' => 3]);
    }

    private function service(): RuntimeEntityActionService
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE runtime_transaction (id INTEGER PRIMARY KEY, tenant_id TEXT, request_context TEXT)');
        $connection->executeStatement('CREATE TABLE runtime_transaction_log (id INTEGER PRIMARY KEY, transaction_id INTEGER, metadata TEXT)');
        $connection->insert('runtime_transaction', ['id' => 1, 'tenant_id' => 'tenant-a', 'request_context' => json_encode(['actor' => ['source' => 'authenticated-session', 'version' => 1, 'userId' => 'admin', 'displayName' => 'Administrador']])]);
        $connection->insert('runtime_transaction', ['id' => 2, 'tenant_id' => 'tenant-b', 'request_context' => json_encode(['actor' => ['source' => 'authenticated-session', 'version' => 1, 'userId' => 'private', 'displayName' => 'Outro assinante']])]);
        $connection->insert('runtime_transaction', ['id' => 3, 'tenant_id' => 'tenant-a', 'request_context' => json_encode(['actor' => ['userId' => 'forged-history', 'displayName' => 'Forjado']])]);
        foreach ([1 => [1, ['actor' => ['source' => 'authenticated-session', 'version' => 1, 'userId' => 'operator', 'displayName' => 'Operador']]], 2 => [1, []], 3 => [2, ['actor' => ['userId' => 'forged-history']]], 4 => [3, []]] as $id => [$parent, $metadata]) {
            $connection->insert('runtime_transaction_log', ['id' => $id, 'transaction_id' => $parent, 'metadata' => json_encode($metadata)]);
        }
        $definitions = $this->createStub(RuntimeEntityDefinitionResolver::class);
        $definitions->method('resolve')->willReturnCallback(static function (string $entity): array {
            $columns = $entity === 'runtime_transaction' ? ['id' => 'integer', 'tenant_id' => 'string', 'request_context' => 'json']
                : ['id' => 'integer', 'transaction_id' => 'integer', 'metadata' => 'json'];
            $fields = [];
            foreach ($columns as $code => $type) {
                $fields[$code] = ['code' => $code, 'column' => $code, 'dataType' => $type, 'readable' => true, 'writable' => false];
            }
            return ['entityCode' => $entity, 'tableName' => $entity, 'quotedTableName' => '"' . $entity . '"',
                'primaryKey' => 'id', 'primaryColumn' => 'id', 'dbColumns' => array_fill_keys(array_keys($columns), true), 'fields' => $fields,
                'subscriberIsolation' => ['enabled' => $entity === 'runtime_transaction', 'column' => 'tenant_id']];
        });
        $permissions = $this->createStub(PermissionResolver::class); $permissions->method('getTenantId')->willReturn('tenant-a');
        $situations = $this->createStub(RuntimeSituationService::class);
        $situations->method('decorateRow')->willReturnCallback(static fn ($definition, $row) => $row);
        $arguments = [];
        foreach ((new \ReflectionClass(RuntimeEntityActionService::class))->getConstructor()->getParameters() as $parameter) {
            $type = $parameter->getType()->getName();
            $arguments[] = match ($type) {
                Connection::class => $connection, RuntimeEntityDefinitionResolver::class => $definitions,
                PermissionResolver::class => $permissions, RuntimeSituationService::class => $situations,
                \App\Runtime\RuntimeConfiguredRuleExecutor::class => new \App\Runtime\RuntimeConfiguredRuleExecutor($this->createStub(\App\Runtime\RuntimeTransactionService::class)),
                default => $this->createStub($type),
            };
        }
        return new RuntimeEntityActionService(...$arguments);
    }
}
