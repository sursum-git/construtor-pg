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

    public function testSupportsHavingSetOperationsAndDerivedFields(): void
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
                'id' => 'clientes_base',
                'title' => 'Base',
                'sourceEntityCode' => 'cliente',
                'steps' => [
                    [
                        'id' => 'select_base',
                        'type' => 'select',
                        'fields' => [
                            ['from' => 'id', 'as' => 'id', 'type' => 'integer'],
                            ['from' => 'uf', 'as' => 'uf', 'type' => 'string'],
                            ['from' => 'status', 'as' => 'status', 'type' => 'string'],
                            ['from' => 'valorTotal', 'as' => 'valor_total', 'type' => 'decimal'],
                        ],
                    ],
                    ['id' => 'publish_base', 'type' => 'publish', 'publishedDatasetId' => 'clientes_base_pub'],
                ],
                'publishConfig' => ['publishedDatasetId' => 'clientes_base_pub'],
            ],
            [
                'id' => 'clientes_ce',
                'title' => 'CE',
                'sourcePipelineId' => 'clientes_base',
                'steps' => [
                    ['id' => 'filter_ce', 'type' => 'filter', 'filters' => [['field' => 'uf', 'operator' => 'eq', 'value' => 'CE']]],
                    ['id' => 'publish_ce', 'type' => 'publish', 'publishedDatasetId' => 'clientes_ce_pub'],
                ],
                'publishConfig' => ['publishedDatasetId' => 'clientes_ce_pub'],
            ],
            [
                'id' => 'clientes_sp',
                'title' => 'SP',
                'sourcePipelineId' => 'clientes_base',
                'steps' => [
                    ['id' => 'filter_sp', 'type' => 'filter', 'filters' => [['field' => 'uf', 'operator' => 'eq', 'value' => 'SP']]],
                    ['id' => 'publish_sp', 'type' => 'publish', 'publishedDatasetId' => 'clientes_sp_pub'],
                ],
                'publishConfig' => ['publishedDatasetId' => 'clientes_sp_pub'],
            ],
            [
                'id' => 'clientes_union',
                'title' => 'CE + SP',
                'sourcePipelineId' => 'clientes_ce',
                'steps' => [
                    ['id' => 'union_sp', 'type' => 'union', 'sourcePipelineId' => 'clientes_sp'],
                    ['id' => 'status_upper', 'type' => 'derive', 'operation' => 'upper', 'targetField' => 'status_upper', 'sourceField' => 'status'],
                    ['id' => 'valor_duplo', 'type' => 'derive', 'operation' => 'add', 'targetField' => 'valor_duplo', 'fields' => ['valor_total', 'valor_total']],
                    ['id' => 'publish_union', 'type' => 'publish', 'publishedDatasetId' => 'clientes_union_pub'],
                ],
                'publishConfig' => ['publishedDatasetId' => 'clientes_union_pub'],
            ],
            [
                'id' => 'clientes_having',
                'title' => 'Having',
                'sourceEntityCode' => 'cliente',
                'steps' => [
                    [
                        'id' => 'group_uf',
                        'type' => 'group',
                        'dimensions' => ['uf'],
                        'measures' => [
                            ['id' => 'clientes', 'field' => 'id', 'aggregate' => 'count', 'label' => 'Clientes'],
                            ['id' => 'valor_total_sum', 'field' => 'valorTotal', 'aggregate' => 'sum', 'label' => 'Valor total'],
                        ],
                    ],
                    ['id' => 'having_clientes', 'type' => 'having', 'filters' => [['field' => 'clientes', 'operator' => 'gte', 'value' => 2]]],
                    ['id' => 'publish_having', 'type' => 'publish', 'publishedDatasetId' => 'clientes_having_pub'],
                ],
                'publishConfig' => ['publishedDatasetId' => 'clientes_having_pub'],
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

        foreach (['clientes_base', 'clientes_ce', 'clientes_sp'] as $pipelineId) {
            $run = $service->run('analytics.clientes', ['pipelineId' => $pipelineId]);
            $service->publish('analytics.clientes', ['pipelineId' => $pipelineId, 'executionId' => $run['executionId']]);
        }

        $union = $service->preview('analytics.clientes', ['pipelineId' => 'clientes_union']);
        self::assertSame(3, count($union['workingDataset']['rows']));
        self::assertTrue(in_array('status_upper', array_map(fn (array $column): string => (string) $column['field'], $union['workingDataset']['columns']), true));
        self::assertSame(200.0, $union['workingDataset']['rows'][0]['valor_duplo']);

        $having = $service->preview('analytics.clientes', ['pipelineId' => 'clientes_having']);
        self::assertCount(1, $having['workingDataset']['rows']);
        self::assertSame('CE', $having['workingDataset']['rows'][0]['uf']);
    }

    public function testBlocksBreakingPublishWhenStrictCompatibility(): void
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

        $screens = $this->createMock(ScreenDefinitionRepository::class);
        $screens->method('findPublishedByScreenId')->willReturn($screen);
        $screens->method('findBy')->willReturnCallback(function (array $criteria) use ($screen): array {
            if (($criteria['pageType'] ?? null) === 'report') {
                return [];
            }
            if (($criteria['pageType'] ?? null) === 'analytics') {
                return [$screen];
            }

            return [];
        });

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

        $firstRun = $service->run('analytics.clientes', ['pipelineId' => 'clientes_resumo']);
        $service->publish('analytics.clientes', [
            'pipelineId' => 'clientes_resumo',
            'executionId' => $firstRun['executionId'],
            'strictCompatibility' => true,
        ]);

        $changedDefinition = $this->definition();
        $changedDefinition['analytics']['semanticPipelines'][0]['steps'][1]['group']['measures'] = [
            ['id' => 'clientes', 'field' => 'id', 'aggregate' => 'count', 'label' => 'Clientes'],
        ];
        $screen->setDefinition($changedDefinition);

        $secondRun = $service->run('analytics.clientes', ['pipelineId' => 'clientes_resumo']);

        $this->expectException(RuntimeHttpException::class);
        $this->expectExceptionMessage('A publicacao foi bloqueada por quebra de compatibilidade com a versao ativa.');

        $service->publish('analytics.clientes', [
            'pipelineId' => 'clientes_resumo',
            'executionId' => $secondRun['executionId'],
            'strictCompatibility' => true,
        ]);
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
