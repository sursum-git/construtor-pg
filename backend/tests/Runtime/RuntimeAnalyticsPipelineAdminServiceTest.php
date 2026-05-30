<?php

namespace App\Tests\Runtime;

use App\Entity\ScreenDefinition;
use App\Repository\ScreenDefinitionRepository;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeAnalyticsPipelineAdminService;
use App\Runtime\RuntimeAnalyticsPipelineService;
use App\Runtime\RuntimeAnalyticsPipelineStore;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

class RuntimeAnalyticsPipelineAdminServiceTest extends TestCase
{
    public function testImpactListsViewsAndReports(): void
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $connection->executeStatement("CREATE TABLE runtime_analytics_pipeline_version (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT NOT NULL, screen_id TEXT NOT NULL, pipeline_id TEXT NOT NULL, version_no INTEGER NOT NULL, definition_hash TEXT NOT NULL, definition_json TEXT NOT NULL, status TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");
        $connection->executeStatement("CREATE TABLE runtime_analytics_pipeline_execution (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT NOT NULL, screen_id TEXT NOT NULL, pipeline_id TEXT NOT NULL, pipeline_version_id INTEGER NOT NULL, execution_code TEXT NOT NULL, mode TEXT NOT NULL, status TEXT NOT NULL, working_dataset_json TEXT DEFAULT NULL, metadata_json TEXT DEFAULT NULL, row_count INTEGER NOT NULL DEFAULT 0, error_message TEXT DEFAULT NULL, started_at TEXT NOT NULL, finished_at TEXT DEFAULT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");
        $connection->executeStatement("CREATE TABLE runtime_analytics_pipeline_execution_step (id INTEGER PRIMARY KEY AUTOINCREMENT, execution_code TEXT NOT NULL, step_id TEXT NOT NULL, step_type TEXT NOT NULL, position INTEGER NOT NULL, status TEXT NOT NULL, row_count INTEGER NOT NULL DEFAULT 0, output_columns_json TEXT DEFAULT NULL, metadata_json TEXT DEFAULT NULL, error_message TEXT DEFAULT NULL, started_at TEXT NOT NULL, finished_at TEXT DEFAULT NULL, created_at TEXT NOT NULL)");
        $connection->executeStatement("CREATE TABLE runtime_analytics_published_dataset (id INTEGER PRIMARY KEY AUTOINCREMENT, tenant_id TEXT NOT NULL, screen_id TEXT NOT NULL, pipeline_id TEXT NOT NULL, dataset_id TEXT NOT NULL, active_version_no INTEGER DEFAULT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");
        $connection->executeStatement("CREATE TABLE runtime_analytics_published_dataset_version (id INTEGER PRIMARY KEY AUTOINCREMENT, published_dataset_id INTEGER NOT NULL, tenant_id TEXT NOT NULL, screen_id TEXT NOT NULL, pipeline_id TEXT NOT NULL, dataset_id TEXT NOT NULL, version_no INTEGER NOT NULL, status TEXT NOT NULL, execution_code TEXT NOT NULL, schema_json TEXT NOT NULL, data_json TEXT NOT NULL, row_count INTEGER NOT NULL DEFAULT 0, fingerprint TEXT NOT NULL, metadata_json TEXT DEFAULT NULL, published_at TEXT NOT NULL, superseded_at TEXT DEFAULT NULL, rolled_back_from_version_no INTEGER DEFAULT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");

        $analyticsScreen = (new ScreenDefinition())
            ->setScreenId('analytics.clientes')
            ->setPageType('analytics')
            ->setStatus('published')
            ->setDefinition([
                'screenId' => 'analytics.clientes',
                'analytics' => [
                    'datasets' => [
                        [
                            'id' => 'clientes-uf-status',
                            'title' => 'Clientes por UF e status',
                            'source' => [
                                'type' => 'pipeline_published',
                                'pipelineId' => 'clientes_uf_status',
                                'publishedDatasetId' => 'clientes_uf_status_published',
                            ],
                        ],
                    ],
                    'views' => [
                        ['id' => 'grid', 'title' => 'Grid', 'type' => 'grid', 'datasetId' => 'clientes-uf-status'],
                    ],
                    'semanticPipelines' => [
                        [
                            'id' => 'clientes_uf_status',
                            'title' => 'Clientes por UF e status',
                            'publishConfig' => [
                                'publishedDatasetId' => 'clientes_uf_status_published',
                            ],
                        ],
                    ],
                ],
            ]);
        $reportScreen = (new ScreenDefinition())
            ->setScreenId('relatorios.clientes-analitico')
            ->setPageType('report')
            ->setStatus('published')
            ->setDefinition([
                'report' => [
                    'id' => 'relatorio-clientes-analitico',
                    'title' => 'Relatorio analitico de clientes',
                    'source' => [
                        'type' => 'analytic',
                        'analyticsScreenId' => 'analytics.clientes',
                        'analyticsDatasetId' => 'clientes-uf-status',
                    ],
                ],
            ]);

        $screens = $this->createMock(ScreenDefinitionRepository::class);
        $screens->method('findPublishedByScreenId')->with('analytics.clientes')->willReturn($analyticsScreen);
        $screens->method('findBy')->willReturnCallback(function (array $criteria) use ($analyticsScreen, $reportScreen): array {
            if (($criteria['pageType'] ?? null) === 'report') {
                return [$reportScreen];
            }
            if (($criteria['pageType'] ?? null) === 'analytics') {
                return [$analyticsScreen];
            }

            return [];
        });

        $permissions = $this->createStub(PermissionResolver::class);
        $store = new RuntimeAnalyticsPipelineStore($connection);
        $pipelines = $this->createStub(RuntimeAnalyticsPipelineService::class);

        $service = new RuntimeAnalyticsPipelineAdminService($screens, $pipelines, $store, $permissions);
        $impact = $service->impact('analytics.clientes', 'clientes_uf_status');

        self::assertSame('clientes_uf_status_published', $impact['publishedDatasetId']);
        self::assertCount(1, $impact['consumingDatasets']);
        self::assertCount(1, $impact['affectedViews']);
        self::assertCount(1, $impact['affectedReports']);
        self::assertSame(1, $impact['summary']['reports']);
    }
}
