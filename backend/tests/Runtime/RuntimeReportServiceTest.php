<?php

namespace App\Tests\Runtime;

use App\Entity\ScreenDefinition;
use App\Repository\ScreenDefinitionRepository;
use App\Runtime\PermissionResolver;
use App\Runtime\ProgramCustomizationResolver;
use App\Runtime\RuntimeAnalyticsAuditStore;
use App\Runtime\RuntimeAnalyticsService;
use App\Runtime\RuntimeEntityDefinitionResolver;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeReportService;
use App\Runtime\StructuralIntegrityService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

class RuntimeReportServiceTest extends TestCase
{
    public function testOperationalRunAppliesTenantSoftDeleteAndTotals(): void
    {
        $service = $this->createService('tenant-a');

        $result = $service->run('relatorios.clientes-operacional', [
            'parameters' => ['status' => 'ATIVO'],
        ]);

        self::assertSame(2, $result['total']);
        self::assertCount(2, $result['rows']);
        self::assertSame('Acme Comercio', $result['rows'][0]['nome']);
        self::assertSame(300.0, $result['totals']['valorTotal']);
    }

    public function testSpecialDocumentIsRejected(): void
    {
        $service = $this->createService('tenant-a', [
            'report' => [
                'classification' => [
                    'documentProfile' => 'special',
                    'documentKind' => 'danfe',
                ],
            ],
        ]);

        $this->expectException(RuntimeHttpException::class);
        $this->expectExceptionMessage('Documento especial fica fora da camada reports v1.');

        $service->run('relatorios.clientes-operacional', []);
    }

    public function testExportReturnsCsvPayload(): void
    {
        $service = $this->createService('tenant-a');

        $result = $service->export('relatorios.clientes-operacional', [
            'parameters' => ['status' => 'ATIVO'],
            'format' => 'csv',
        ]);

        self::assertSame('csv', $result['format']);
        self::assertSame('text/csv; charset=utf-8', $result['contentType']);
        self::assertSame('download', $result['deliveryMode']);
        self::assertNotSame('', $result['contentBase64']);
        self::assertStringContainsString('relatorio-clientes-operacional', $result['fileName']);
        self::assertStringContainsString('"Nome"', base64_decode((string) $result['contentBase64']));
    }

    public function testExportReturnsXlsxPayload(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ZipArchive nao disponivel neste ambiente.');
        }

        $service = $this->createService('tenant-a');

        $result = $service->export('relatorios.clientes-operacional', [
            'parameters' => ['status' => 'ATIVO'],
            'format' => 'excel',
        ]);

        self::assertSame('excel', $result['format']);
        self::assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $result['contentType']);
        self::assertSame('download', $result['deliveryMode']);
        self::assertStringEndsWith('.xlsx', $result['fileName']);
        $binary = base64_decode((string) $result['contentBase64']);
        self::assertStringStartsWith('PK', $binary);
        $tempFile = tempnam(sys_get_temp_dir(), 'report-test-xlsx-');
        file_put_contents($tempFile, $binary);
        $zip = new \ZipArchive();
        $zip->open($tempFile);
        $workbook = (string) $zip->getFromName('xl/workbook.xml');
        $worksheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($tempFile);
        self::assertStringContainsString('Relatorio operacional de client', $workbook);
        self::assertStringContainsString('autoFilter', $worksheet);
    }

    public function testExportReturnsPdfPayload(): void
    {
        $service = $this->createService('tenant-a');

        $result = $service->export('relatorios.clientes-operacional', [
            'parameters' => ['status' => 'ATIVO'],
            'format' => 'pdf',
        ]);

        self::assertSame('pdf', $result['format']);
        self::assertSame('application/pdf', $result['contentType']);
        self::assertSame('download', $result['deliveryMode']);
        self::assertStringEndsWith('.pdf', $result['fileName']);
        $binary = (string) base64_decode((string) $result['contentBase64']);
        self::assertStringStartsWith('%PDF', $binary);
        self::assertStringContainsString('Pagina 1 de 1', $binary);
    }

    public function testExportReturnsQzTrayPayloadWhenEnabled(): void
    {
        $service = $this->createService('tenant-a', [
            'report' => [
                'printing' => [
                    'qzTray' => [
                        'enabled' => true,
                        'printerName' => 'IMP-LOCAL-01',
                        'jobName' => 'Relatorio operacional de clientes',
                        'copies' => 2,
                    ],
                ],
            ],
        ]);

        $result = $service->export('relatorios.clientes-operacional', [
            'parameters' => ['status' => 'ATIVO'],
            'format' => 'pdf',
            'deliveryMode' => 'qz_tray',
        ]);

        self::assertSame('qz_tray', $result['deliveryMode']);
        self::assertSame('qz_tray', $result['printer']['transport'] ?? '');
        self::assertSame('IMP-LOCAL-01', $result['printer']['printerName'] ?? '');
        self::assertSame(2, $result['printer']['copies'] ?? 0);
    }

    public function testOperationalRunSupportsNestedGroups(): void
    {
        $service = $this->createService('tenant-a', [
            'report' => [
                'layout' => [
                    'groups' => [
                        ['field' => 'uf', 'label' => 'UF', 'showSubtotal' => true],
                        ['field' => 'status', 'label' => 'Status', 'showSubtotal' => true],
                        ['field' => 'nome', 'label' => 'Cliente', 'showSubtotal' => false],
                    ],
                ],
            ],
        ]);

        $result = $service->run('relatorios.clientes-operacional', []);

        self::assertNotEmpty($result['groups']);
        self::assertSame('UF: CE', $result['groups'][0]['label']);
        self::assertArrayHasKey('children', $result['groups'][0]);
        self::assertSame(3, $result['_runtime']['report']['groupCount']);
        self::assertArrayHasKey('children', $result['groups'][0]['children'][0]);
    }

    public function testRunIncludesAuthenticityAndRecordsHash(): void
    {
        $auditStore = new RuntimeAnalyticsAuditStore('sqlite:///:memory:', true, 50, true);
        $service = $this->createService('tenant-a', [
            'report' => [
                'authenticity' => [
                    'enabled' => true,
                    'algorithm' => 'sha256',
                    'footerLabel' => 'Codigo de autenticidade',
                    'verificationPath' => 'report-authenticity.html',
                ],
            ],
        ], $auditStore);

        $result = $service->run('relatorios.clientes-operacional', [
            'parameters' => ['status' => 'ATIVO'],
            'format' => 'pdf',
        ]);

        self::assertSame('sha256', $result['authenticity']['algorithm']);
        self::assertMatchesRegularExpression('/^sha256:[a-f0-9]{64}$/', (string) $result['authenticity']['hash']);
        self::assertStringContainsString('report-authenticity.html?hash=', (string) $result['authenticity']['verificationUrl']);

        $audit = $auditStore->findLatestByMetadataValue('report', ['authenticity', 'hash'], (string) $result['authenticity']['hash']);
        self::assertNotNull($audit);
        self::assertSame('relatorios.clientes-operacional', $audit['screenId']);
        self::assertArrayHasKey('canonicalPayload', $audit['metadata']);
    }

    public function testExportCanStoreArtifactInAudit(): void
    {
        $auditStore = new RuntimeAnalyticsAuditStore('sqlite:///:memory:', true, 50, true);
        $service = $this->createService('tenant-a', [
            'report' => [
                'authenticity' => [
                    'enabled' => true,
                    'algorithm' => 'sha256',
                    'footerLabel' => 'Codigo de autenticidade',
                    'verificationPath' => 'report-authenticity.html',
                    'storage' => [
                        'storeCanonicalPayload' => true,
                        'storeExportArtifact' => true,
                    ],
                ],
            ],
        ], $auditStore);

        $service->export('relatorios.clientes-operacional', [
            'parameters' => ['status' => 'ATIVO'],
            'format' => 'pdf',
        ]);

        $rows = $auditStore->query(['resultSource' => 'report_export'], 'report');
        self::assertSame(1, $rows['total']);
        self::assertTrue(($rows['items'][0]['metadata']['artifact']['stored'] ?? false) === true);
        self::assertSame('pdf', $rows['items'][0]['metadata']['artifact']['format'] ?? '');
    }

    /**
     * @param array<string, mixed> $definitionPatch
     */
    private function createService(string $tenantId, array $definitionPatch = [], ?RuntimeAnalyticsAuditStore $auditStore = null): RuntimeReportService
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $this->seedDatabase($connection);

        $screen = (new ScreenDefinition())
            ->setScreenId('relatorios.clientes-operacional')
            ->setPageType('report')
            ->setStatus('published')
            ->setDefinition(array_replace_recursive($this->definition(), $definitionPatch));

        $screens = $this->createStub(ScreenDefinitionRepository::class);
        $screens->method('findPublishedByScreenId')->willReturn($screen);

        $entities = $this->createStub(RuntimeEntityDefinitionResolver::class);
        $entities->method('resolve')->willReturn($this->entityDefinition());

        $permissions = $this->createStub(PermissionResolver::class);
        $permissions->method('getTenantId')->willReturn($tenantId);
        $permissions->method('getUserId')->willReturn('tester');
        $permissions->method('getSessionId')->willReturn('sess-report');

        $integrity = $this->createStub(StructuralIntegrityService::class);
        $customizations = $this->createStub(ProgramCustomizationResolver::class);
        $customizations->method('resolve')->willReturn(null);

        $analytics = $this->createStub(RuntimeAnalyticsService::class);
        $analytics->method('run')->willReturn([
            'data' => [],
            'columns' => [],
        ]);

        return new RuntimeReportService(
            $screens,
            $entities,
            $connection,
            $permissions,
            $integrity,
            $customizations,
            $analytics,
            $auditStore,
        );
    }

    private function seedDatabase(Connection $connection): void
    {
        $connection->executeStatement('CREATE TABLE cliente (id INTEGER PRIMARY KEY AUTOINCREMENT, subscriber_id TEXT NOT NULL, nome TEXT NOT NULL, uf TEXT NOT NULL, status TEXT NOT NULL, valor_total REAL NOT NULL, qtde_pedidos INTEGER NOT NULL, deleted_at TEXT DEFAULT NULL)');
        $connection->insert('cliente', ['subscriber_id' => 'tenant-a', 'nome' => 'Acme Comercio', 'uf' => 'CE', 'status' => 'ATIVO', 'valor_total' => 100, 'qtde_pedidos' => 2]);
        $connection->insert('cliente', ['subscriber_id' => 'tenant-a', 'nome' => 'Beta Servicos', 'uf' => 'SP', 'status' => 'ATIVO', 'valor_total' => 200, 'qtde_pedidos' => 3]);
        $connection->insert('cliente', ['subscriber_id' => 'tenant-a', 'nome' => 'Casa Norte', 'uf' => 'CE', 'status' => 'INATIVO', 'valor_total' => 50, 'qtde_pedidos' => 1]);
        $connection->insert('cliente', ['subscriber_id' => 'tenant-a', 'nome' => 'Cliente Excluido', 'uf' => 'CE', 'status' => 'ATIVO', 'valor_total' => 999, 'qtde_pedidos' => 4, 'deleted_at' => '2026-01-01 00:00:00']);
        $connection->insert('cliente', ['subscriber_id' => 'tenant-b', 'nome' => 'Outro Tenant', 'uf' => 'RJ', 'status' => 'ATIVO', 'valor_total' => 777, 'qtde_pedidos' => 8]);
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(): array
    {
        return [
            'schemaVersion' => '1.0',
            'pageType' => 'report',
            'screenId' => 'relatorios.clientes-operacional',
            'program' => [
                'id' => 'relatorio-clientes-operacional',
                'title' => 'Relatorio operacional de clientes',
            ],
            'report' => [
                'classification' => [
                    'documentProfile' => 'general',
                    'documentKind' => 'purchase_order',
                ],
                'source' => [
                    'type' => 'operational',
                    'entityCode' => 'cliente',
                ],
                'query' => [
                    'fields' => [
                        ['field' => 'nome', 'label' => 'Nome'],
                        ['field' => 'uf', 'label' => 'UF'],
                        ['field' => 'status', 'label' => 'Status'],
                        ['field' => 'valorTotal', 'label' => 'Valor total', 'type' => 'currency', 'format' => 'c2', 'align' => 'right', 'totalable' => true],
                    ],
                    'parameters' => [
                        ['id' => 'status', 'field' => 'status', 'label' => 'Status', 'type' => 'enum', 'operator' => 'eq'],
                    ],
                    'sort' => [
                        ['field' => 'nome', 'dir' => 'asc'],
                    ],
                    'limit' => 200,
                ],
                'layout' => [
                    'title' => 'Relatorio operacional de clientes',
                    'blocks' => [
                        ['id' => 'header', 'type' => 'header'],
                        ['id' => 'summary', 'type' => 'summary'],
                        ['id' => 'table', 'type' => 'table'],
                        ['id' => 'totals', 'type' => 'totals'],
                        ['id' => 'footer', 'type' => 'footer'],
                    ],
                ],
                'outputs' => [
                    'html' => true,
                    'print' => true,
                    'pdfBrowser' => true,
                    'excel' => true,
                    'csv' => true,
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
            'subscriberIsolation' => [
                'enabled' => true,
                'column' => 'subscriber_id',
            ],
            'softDelete' => [
                'enabled' => true,
                'deletedAtColumn' => 'deleted_at',
            ],
            'fields' => [
                'nome' => ['column' => 'nome', 'readable' => true, 'virtual' => false, 'dataType' => 'string', 'label' => 'Nome'],
                'uf' => ['column' => 'uf', 'readable' => true, 'virtual' => false, 'dataType' => 'string', 'label' => 'UF'],
                'status' => ['column' => 'status', 'readable' => true, 'virtual' => false, 'dataType' => 'string', 'label' => 'Status'],
                'valorTotal' => ['column' => 'valor_total', 'readable' => true, 'virtual' => false, 'dataType' => 'currency', 'label' => 'Valor total'],
                'qtdePedidos' => ['column' => 'qtde_pedidos', 'readable' => true, 'virtual' => false, 'dataType' => 'integer', 'label' => 'Pedidos'],
            ],
        ];
    }
}
