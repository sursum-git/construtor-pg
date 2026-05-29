<?php

namespace App\Tests\Runtime;

use App\Entity\ScreenDefinition;
use App\Repository\ScreenDefinitionRepository;
use App\Runtime\PermissionResolver;
use App\Runtime\ProgramCustomizationResolver;
use App\Runtime\RuntimeAnalyticsPipelineService;
use App\Runtime\RuntimeAnalyticsPipelineStore;
use App\Runtime\RuntimeAnalyticsService;
use App\Runtime\RuntimeEntityDefinitionResolver;
use App\Runtime\RuntimeHttpException;
use App\Runtime\StructuralIntegrityService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

class RuntimeAnalyticsPipelineServiceTest extends TestCase
{
    public function testRunPublishConsumeAndRollbackPublishedDataset(): void
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $this->seedDatabase($connection);
        $this->seedPipelineTables($connection);

        $definition = $this->definition();
        $screen = (new ScreenDefinition())
            ->setScreenId('analytics.clientes')
            ->setPageType('analytics')
            ->setStatus('published')
            ->setDefinition($definition);

        $screens = $this->createStub(ScreenDefinitionRepository::class);
        $screens->method('findPublishedByScreenId')->willReturn($screen);

        $entities = $this->createStub(RuntimeEntityDefinitionResolver::class);
        $entities->method('resolve')->willReturn($this->entityDefinition());

        $permissions = $this->createStub(PermissionResolver::class);
        $permissions->method('getTenantId')->willReturn('tenant-a');
        $permissions->method('getUserId')->willReturn('tester');
        $permissions->method('getSessionId')->willReturn('sess-pipeline');

        $integrity = $this->createStub(StructuralIntegrityService::class);
        $customizations = $this->createStub(ProgramCustomizationResolver::class);
        $customizations->method('resolve')->willReturn(null);

        $store = new RuntimeAnalyticsPipelineStore($connection);
        $pipelines = new RuntimeAnalyticsPipelineService(
            $screens,
            $entities,
            $connection,
            $permissions,
            $integrity,
            $customizations,
            $store,
            null,
        );
        $analytics = new RuntimeAnalyticsService(
            $screens,
            $entities,
            $connection,
            $permissions,
            $integrity,
            $customizations,
            $pipelines,
            null,
        );

        $preview = $pipelines->preview('analytics.clientes', ['pipelineId' => 'clientes_resumo']);
        self::assertTrue($preview['ok']);
        self::assertSame('clientes_resumo', $preview['pipelineId']);
        self::assertCount(2, $preview['workingDataset']['rows']);

        $run = $pipelines->run('analytics.clientes', ['pipelineId' => 'clientes_resumo']);
        self::assertTrue($run['ok']);
        $published = $pipelines->publish('analytics.clientes', [
            'pipelineId' => 'clientes_resumo',
            'executionId' => $run['executionId'],
        ]);
        self::assertSame(1, $published['publishedVersion']['versionNo']);

        $consumed = $analytics->run('analytics.clientes', ['datasetId' => 'clientes_publicados']);
        self::assertSame('published', $consumed['_runtime']['analytics']['executionMode']);
        self::assertSame(2, $consumed['total']);
        self::assertSame('CE', $consumed['data'][0]['uf']);

        $connection->insert('cliente', [
            'subscriber_id' => 'tenant-a',
            'uf' => 'CE',
            'status' => 'ATIVO',
            'valor_total' => 400,
            'qtde_pedidos' => 1,
        ]);

        $run2 = $pipelines->run('analytics.clientes', ['pipelineId' => 'clientes_resumo']);
        $published2 = $pipelines->publish('analytics.clientes', [
            'pipelineId' => 'clientes_resumo',
            'executionId' => $run2['executionId'],
        ]);
        self::assertSame(2, $published2['publishedVersion']['versionNo']);

        $consumed2 = $analytics->run('analytics.clientes', ['datasetId' => 'clientes_publicados']);
        self::assertSame(2, $consumed2['total']);
        self::assertSame(700.0, $consumed2['data'][0]['valor_total_sum']);

        $rollback = $pipelines->rollback('analytics.clientes', [
            'pipelineId' => 'clientes_resumo',
            'versionNo' => 1,
        ]);
        self::assertTrue($rollback['ok']);
        self::assertSame(1, $rollback['activeVersion']['versionNo']);

        $consumedRollback = $analytics->run('analytics.clientes', ['datasetId' => 'clientes_publicados']);
        self::assertSame(300.0, $consumedRollback['data'][0]['valor_total_sum']);
    }

    public function testRejectsPipelineCycle(): void
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $this->seedDatabase($connection);
        $this->seedPipelineTables($connection);

        $definition = $this->definition();
        $definition['analytics']['semanticPipelines'] = [
            [
                'id' => 'pipe_a',
                'title' => 'A',
                'sourcePipelineId' => 'pipe_b',
                'steps' => [],
                'publishConfig' => ['publishedDatasetId' => 'pipe_a_pub'],
            ],
            [
                'id' => 'pipe_b',
                'title' => 'B',
                'sourcePipelineId' => 'pipe_a',
                'steps' => [],
                'publishConfig' => ['publishedDatasetId' => 'pipe_b_pub'],
            ],
        ];

        $screen = (new ScreenDefinition())
            ->setScreenId('analytics.clientes')
            ->setPageType('analytics')
            ->setStatus('published')
            ->setDefinition($definition);
        $screens = $this->createStub(ScreenDefinitionRepository::class);
        $screens->method('findPublishedByScreenId')->willReturn($screen);

        $entities = $this->createStub(RuntimeEntityDefinitionResolver::class);
        $entities->method('resolve')->willReturn($this->entityDefinition());

        $permissions = $this->createStub(PermissionResolver::class);
        $permissions->method('getTenantId')->willReturn('tenant-a');
        $permissions->method('getUserId')->willReturn('tester');
        $permissions->method('getSessionId')->willReturn('sess-pipeline');

        $integrity = $this->createStub(StructuralIntegrityService::class);
        $customizations = $this->createStub(ProgramCustomizationResolver::class);
        $customizations->method('resolve')->willReturn(null);

        $service = new RuntimeAnalyticsPipelineService(
            $screens,
            $entities,
            $connection,
            $permissions,
            $integrity,
            $customizations,
            new RuntimeAnalyticsPipelineStore($connection),
            null,
        );

        $this->expectException(RuntimeHttpException::class);
        $this->expectExceptionMessage('Pipeline analytics possui dependencia ciclica.');

        $service->schema('analytics.clientes');
    }

    private function seedDatabase(Connection $connection): void
    {
        $connection->executeStatement('CREATE TABLE cliente (id INTEGER PRIMARY KEY AUTOINCREMENT, subscriber_id TEXT NOT NULL, uf TEXT NOT NULL, status TEXT NOT NULL, valor_total REAL NOT NULL, qtde_pedidos INTEGER NOT NULL, deleted_at TEXT DEFAULT NULL)');
        $connection->insert('cliente', ['subscriber_id' => 'tenant-a', 'uf' => 'CE', 'status' => 'ATIVO', 'valor_total' => 100, 'qtde_pedidos' => 2]);
        $connection->insert('cliente', ['subscriber_id' => 'tenant-a', 'uf' => 'CE', 'status' => 'ATIVO', 'valor_total' => 200, 'qtde_pedidos' => 3]);
        $connection->insert('cliente', ['subscriber_id' => 'tenant-a', 'uf' => 'SP', 'status' => 'INATIVO', 'valor_total' => 50, 'qtde_pedidos' => 1]);
        $connection->insert('cliente', ['subscriber_id' => 'tenant-b', 'uf' => 'RJ', 'status' => 'ATIVO', 'valor_total' => 777, 'qtde_pedidos' => 8]);
    }

    private function seedPipelineTables(Connection $connection): void
    {
        $connection->executeStatement("CREATE TABLE runtime_analytics_pipeline_version (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT NOT NULL, screen_id TEXT NOT NULL, pipeline_id TEXT NOT NULL, version_no INTEGER NOT NULL, definition_hash TEXT NOT NULL, definition_json TEXT NOT NULL, status TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");
        $connection->executeStatement("CREATE TABLE runtime_analytics_pipeline_execution (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT NOT NULL, screen_id TEXT NOT NULL, pipeline_id TEXT NOT NULL, pipeline_version_id INTEGER NOT NULL, execution_code TEXT NOT NULL, mode TEXT NOT NULL, status TEXT NOT NULL, working_dataset_json TEXT DEFAULT NULL, metadata_json TEXT DEFAULT NULL, row_count INTEGER NOT NULL DEFAULT 0, error_message TEXT DEFAULT NULL, started_at TEXT NOT NULL, finished_at TEXT DEFAULT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");
        $connection->executeStatement("CREATE TABLE runtime_analytics_pipeline_execution_step (id INTEGER PRIMARY KEY AUTOINCREMENT, execution_code TEXT NOT NULL, step_id TEXT NOT NULL, step_type TEXT NOT NULL, position INTEGER NOT NULL, status TEXT NOT NULL, row_count INTEGER NOT NULL DEFAULT 0, output_columns_json TEXT DEFAULT NULL, metadata_json TEXT DEFAULT NULL, error_message TEXT DEFAULT NULL, started_at TEXT NOT NULL, finished_at TEXT DEFAULT NULL, created_at TEXT NOT NULL)");
        $connection->executeStatement("CREATE TABLE runtime_analytics_published_dataset (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT NOT NULL, screen_id TEXT NOT NULL, pipeline_id TEXT NOT NULL, dataset_id TEXT NOT NULL, active_version_no INTEGER DEFAULT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");
        $connection->executeStatement("CREATE TABLE runtime_analytics_published_dataset_version (id INTEGER PRIMARY KEY AUTOINCREMENT, published_dataset_id INTEGER NOT NULL, tenant_id TEXT NOT NULL, screen_id TEXT NOT NULL, pipeline_id TEXT NOT NULL, dataset_id TEXT NOT NULL, version_no INTEGER NOT NULL, status TEXT NOT NULL, execution_code TEXT NOT NULL, schema_json TEXT NOT NULL, data_json TEXT NOT NULL, row_count INTEGER NOT NULL DEFAULT 0, fingerprint TEXT NOT NULL, metadata_json TEXT DEFAULT NULL, published_at TEXT NOT NULL, superseded_at TEXT DEFAULT NULL, rolled_back_from_version_no INTEGER DEFAULT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(): array
    {
        return [
            'schemaVersion' => '1.0',
            'pageType' => 'analytics',
            'screenId' => 'analytics.clientes',
            'program' => [
                'id' => 'analytics-clientes',
                'title' => 'Analytics Clientes',
            ],
            'analytics' => [
                'semanticPipelines' => [
                    [
                        'id' => 'clientes_resumo',
                        'title' => 'Resumo por UF',
                        'sourceEntityCode' => 'cliente',
                        'steps' => [
                            [
                                'id' => 'filtra_status',
                                'type' => 'filter',
                                'filters' => [
                                    [
                                        'field' => 'status',
                                        'operator' => 'in',
                                        'value' => ['ATIVO', 'INATIVO'],
                                    ],
                                ],
                            ],
                            [
                                'id' => 'agrupa_uf',
                                'type' => 'group',
                                'group' => [
                                    'dimensions' => [
                                        ['id' => 'uf', 'field' => 'uf', 'label' => 'UF', 'type' => 'string'],
                                    ],
                                    'measures' => [
                                        ['id' => 'clientes', 'field' => 'id', 'aggregate' => 'count', 'label' => 'Clientes'],
                                        ['id' => 'valor_total_sum', 'field' => 'valorTotal', 'aggregate' => 'sum', 'label' => 'Valor total'],
                                    ],
                                ],
                            ],
                            [
                                'id' => 'ordena_uf',
                                'type' => 'sort',
                                'sort' => [
                                    ['field' => 'uf', 'dir' => 'asc'],
                                ],
                            ],
                            [
                                'id' => 'publica',
                                'type' => 'publish',
                                'publishedDatasetId' => 'clientes_publicados',
                            ],
                        ],
                        'publishConfig' => [
                            'publishedDatasetId' => 'clientes_publicados',
                            'title' => 'Clientes publicados',
                        ],
                    ],
                ],
                'datasets' => [
                    [
                        'id' => 'clientes_publicados',
                        'title' => 'Clientes publicados',
                        'source' => [
                            'type' => 'pipeline_published',
                            'pipelineId' => 'clientes_resumo',
                            'publishedDatasetId' => 'clientes_publicados',
                        ],
                        'limit' => 1000,
                        'executionMode' => 'live',
                    ],
                ],
                'views' => [
                    ['id' => 'grid', 'type' => 'grid', 'datasetId' => 'clientes_publicados'],
                ],
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
                'qtdePedidos' => ['column' => 'qtde_pedidos', 'label' => 'Pedidos', 'dataType' => 'integer', 'readable' => true, 'virtual' => false],
            ],
        ];
    }
}
