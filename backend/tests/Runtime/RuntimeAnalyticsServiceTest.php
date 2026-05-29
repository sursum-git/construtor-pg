<?php

namespace App\Tests\Runtime;

use App\Entity\ScreenDefinition;
use App\Repository\ScreenDefinitionRepository;
use App\Runtime\PermissionResolver;
use App\Runtime\ProgramCustomizationResolver;
use App\Runtime\RuntimeAnalyticsAuditStore;
use App\Runtime\RuntimeAnalyticsPipelineService;
use App\Runtime\RuntimeAnalyticsService;
use App\Runtime\RuntimeEntityDefinitionResolver;
use App\Runtime\RuntimeHttpException;
use App\Runtime\StructuralIntegrityService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

class RuntimeAnalyticsServiceTest extends TestCase
{
    public function testRunAppliesTenantSoftDeleteFiltersAndAggregates(): void
    {
        $service = $this->createService('tenant-a');

        $result = $service->run('analytics.clientes', [
            'datasetId' => 'clientes',
            'parameters' => ['status' => 'ATIVO'],
        ]);

        self::assertSame(2, $result['total']);
        self::assertSame('CE', $result['data'][0]['uf']);
        self::assertSame(2, $result['data'][0]['clientes']);
        self::assertSame(300.0, $result['data'][0]['valor_total_sum']);
        self::assertSame('SP', $result['data'][1]['uf']);
        self::assertSame(1, $result['data'][1]['clientes']);
    }

    public function testFreeSqlMetadataIsRejected(): void
    {
        $service = $this->createService('tenant-a', [
            'sql' => 'SELECT * FROM cliente',
        ]);

        $this->expectException(RuntimeHttpException::class);
        $this->expectExceptionMessage('Analytics nao aceita SQL, JS ou template livre nos metadados.');

        $service->run('analytics.clientes', ['datasetId' => 'clientes']);
    }

    public function testMaterializeStoresCacheAndRunCanReadHit(): void
    {
        $connection = null;
        $service = $this->createService('tenant-a', [], $connection);

        $materialized = $service->materialize('analytics.clientes', [
            'datasetId' => 'clientes',
            'parameters' => ['status' => 'ATIVO'],
        ]);

        self::assertTrue($materialized['ok']);

        $result = $service->run('analytics.clientes', [
            'datasetId' => 'clientes',
            'parameters' => ['status' => 'ATIVO'],
        ]);

        self::assertSame('hit', $result['_runtime']['analyticsCache']['status']);

        $connection->executeStatement("UPDATE runtime_analytics_cache SET expires_at = '2020-01-01 00:00:00'");

        $expired = $service->run('analytics.clientes', [
            'datasetId' => 'clientes',
            'parameters' => ['status' => 'ATIVO'],
        ]);

        self::assertSame('miss_live', $expired['_runtime']['analyticsCache']['status']);
    }

    public function testRunStoresAuditEntryInSeparateConnection(): void
    {
        $connection = null;
        $auditConnection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $auditStore = new RuntimeAnalyticsAuditStore('sqlite:///:memory:', true, 50, true);
        $auditStoreReflection = new \ReflectionProperty($auditStore, 'connection');
        $auditStoreReflection->setValue($auditStore, $auditConnection);

        $service = $this->createService('tenant-a', [], $connection, $auditStore);
        $service->run('analytics.clientes', [
            'datasetId' => 'clientes',
            'parameters' => ['status' => 'ATIVO'],
        ]);

        $rows = $auditStore->fetchRecent();
        self::assertCount(1, $rows);
        self::assertSame('tenant-a', $rows[0]['tenant_id']);
        self::assertSame('clientes', $rows[0]['dataset_id']);
        self::assertSame('live', $rows[0]['result_source']);
    }

    public function testRunSkipsAuditWhenDatasetDisablesIt(): void
    {
        $connection = null;
        $auditConnection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $auditStore = new RuntimeAnalyticsAuditStore('sqlite:///:memory:', true, 50, true);
        $auditStoreReflection = new \ReflectionProperty($auditStore, 'connection');
        $auditStoreReflection->setValue($auditStore, $auditConnection);

        $service = $this->createService('tenant-a', [
            'audit' => [
                'enabled' => false,
            ],
        ], $connection, $auditStore);
        $service->run('analytics.clientes', [
            'datasetId' => 'clientes',
            'parameters' => ['status' => 'ATIVO'],
        ]);

        self::assertCount(0, $auditStore->fetchRecent());
    }

    /**
     * @param array<string, mixed> $datasetPatch
     */
    private function createService(string $tenantId, array $datasetPatch = [], ?Connection &$connectionOut = null, ?RuntimeAnalyticsAuditStore $auditStore = null): RuntimeAnalyticsService
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $connection->executeStatement('CREATE TABLE cliente (id INTEGER PRIMARY KEY AUTOINCREMENT, subscriber_id TEXT NOT NULL, uf TEXT NOT NULL, status TEXT NOT NULL, valor_total REAL NOT NULL, deleted_at TEXT DEFAULT NULL)');
        $connection->executeStatement('CREATE TABLE runtime_analytics_cache (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT NOT NULL, screen_id TEXT NOT NULL, dataset_id TEXT NOT NULL, view_id TEXT NOT NULL DEFAULT \'\', filter_fingerprint TEXT NOT NULL, status TEXT NOT NULL, row_count INTEGER NOT NULL, payload TEXT NOT NULL, metadata TEXT NOT NULL, last_error TEXT DEFAULT NULL, expires_at TEXT DEFAULT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, refreshed_at TEXT DEFAULT NULL)');
        $connection->insert('cliente', ['subscriber_id' => 'tenant-a', 'uf' => 'CE', 'status' => 'ATIVO', 'valor_total' => 100]);
        $connection->insert('cliente', ['subscriber_id' => 'tenant-a', 'uf' => 'CE', 'status' => 'ATIVO', 'valor_total' => 200]);
        $connection->insert('cliente', ['subscriber_id' => 'tenant-a', 'uf' => 'SP', 'status' => 'ATIVO', 'valor_total' => 300]);
        $connection->insert('cliente', ['subscriber_id' => 'tenant-a', 'uf' => 'RJ', 'status' => 'INATIVO', 'valor_total' => 400]);
        $connection->insert('cliente', ['subscriber_id' => 'tenant-a', 'uf' => 'CE', 'status' => 'ATIVO', 'valor_total' => 500, 'deleted_at' => '2026-01-01 00:00:00']);
        $connection->insert('cliente', ['subscriber_id' => 'tenant-b', 'uf' => 'CE', 'status' => 'ATIVO', 'valor_total' => 999]);
        $connectionOut = $connection;

        $screen = (new ScreenDefinition())
            ->setScreenId('analytics.clientes')
            ->setPageType('analytics')
            ->setStatus('published')
            ->setDefinition($this->definition($datasetPatch));

        $screens = $this->createStub(ScreenDefinitionRepository::class);
        $screens->method('findPublishedByScreenId')->willReturn($screen);

        $entities = $this->createStub(RuntimeEntityDefinitionResolver::class);
        $entities->method('resolve')->willReturn($this->entityDefinition());

        $permissions = $this->createStub(PermissionResolver::class);
        $permissions->method('getTenantId')->willReturn($tenantId);

        $integrity = $this->createStub(StructuralIntegrityService::class);
        $customizations = $this->createStub(ProgramCustomizationResolver::class);
        $customizations->method('resolve')->willReturn(null);

        return new RuntimeAnalyticsService(
            $screens,
            $entities,
            $connection,
            $permissions,
            $integrity,
            $customizations,
            null,
            $auditStore,
        );
    }

    /**
     * @param array<string, mixed> $datasetPatch
     * @return array<string, mixed>
     */
    private function definition(array $datasetPatch): array
    {
        $dataset = array_replace_recursive([
            'id' => 'clientes',
            'title' => 'Clientes',
            'source' => ['type' => 'entity', 'entityCode' => 'cliente'],
            'dimensions' => [
                ['id' => 'uf', 'field' => 'uf', 'label' => 'UF'],
            ],
            'measures' => [
                ['id' => 'clientes', 'aggregate' => 'count', 'label' => 'Clientes'],
                ['id' => 'valor_total_sum', 'field' => 'valorTotal', 'aggregate' => 'sum', 'label' => 'Valor total'],
            ],
            'parameters' => [
                ['id' => 'status', 'field' => 'status', 'operator' => 'eq'],
            ],
            'executionMode' => 'auto',
            'cache' => ['ttlSeconds' => 60],
        ], $datasetPatch);

        return [
            'schemaVersion' => '1.0',
            'pageType' => 'analytics',
            'screenId' => 'analytics.clientes',
            'program' => ['id' => 'analytics-clientes', 'title' => 'Analytics Clientes'],
            'analytics' => [
                'datasets' => [$dataset],
                'views' => [['id' => 'grid', 'type' => 'grid', 'datasetId' => 'clientes']],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function entityDefinition(): array
    {
        return [
            'entityCode' => 'cliente',
            'tableName' => 'cliente',
            'quotedTableName' => '"cliente"',
            'primaryKey' => 'id',
            'primaryColumn' => 'id',
            'subscriberIsolation' => [
                'enabled' => true,
                'column' => 'subscriber_id',
            ],
            'softDelete' => [
                'enabled' => true,
                'deletedAtColumn' => 'deleted_at',
            ],
            'fields' => [
                'id' => ['column' => 'id', 'label' => 'ID', 'dataType' => 'integer', 'readable' => true, 'virtual' => false],
                'uf' => ['column' => 'uf', 'label' => 'UF', 'dataType' => 'string', 'readable' => true, 'virtual' => false],
                'status' => ['column' => 'status', 'label' => 'Status', 'dataType' => 'string', 'readable' => true, 'virtual' => false],
                'valorTotal' => ['column' => 'valor_total', 'label' => 'Valor total', 'dataType' => 'decimal', 'readable' => true, 'virtual' => false],
            ],
        ];
    }
}
