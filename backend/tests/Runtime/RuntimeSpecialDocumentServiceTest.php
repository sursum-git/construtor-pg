<?php

namespace App\Tests\Runtime;

use App\Entity\ScreenDefinition;
use App\Repository\ScreenDefinitionRepository;
use App\Runtime\PermissionResolver;
use App\Runtime\ProgramCustomizationResolver;
use App\Runtime\RuntimeAnalyticsService;
use App\Runtime\RuntimeEntityDefinitionResolver;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeSpecialDocumentService;
use App\Runtime\StructuralIntegrityService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

class RuntimeSpecialDocumentServiceTest extends TestCase
{
    public function testRenderReturnsStructuredDocumentFromOperationalSource(): void
    {
        $service = $this->createService();

        $result = $service->render('documentos.especiais-base', [
            'parameters' => ['status' => 'ATIVO'],
        ]);

        self::assertSame('special_document', $service->schema('documentos.especiais-base')['pageType']);
        self::assertSame('danfe', $result['documentKind']);
        self::assertSame('native', $result['renderEngine']);
        self::assertSame(2, $result['table']['rowCount']);
        self::assertNotEmpty($result['table']['columns']);
        self::assertNotEmpty($result['totals']);
        self::assertNotEmpty($result['sections']);
    }

    public function testExportReturnsPdfPayload(): void
    {
        $service = $this->createService();

        $result = $service->export('documentos.especiais-base', ['format' => 'pdf']);

        self::assertSame('pdf', $result['format']);
        self::assertSame('download', $result['deliveryMode']);
        self::assertStringStartsWith('%PDF', (string) base64_decode((string) $result['contentBase64']));
    }

    public function testExportReturnsHtmlPayload(): void
    {
        $service = $this->createService();

        $result = $service->export('documentos.especiais-base', ['format' => 'html']);

        self::assertSame('html', $result['format']);
        self::assertSame('download', $result['deliveryMode']);
        self::assertStringContainsString('<table>', (string) base64_decode((string) $result['contentBase64']));
    }

    public function testRenderSupportsAnalyticSource(): void
    {
        $service = $this->createService([
            'specialDocument' => [
                'source' => [
                    'type' => 'analytic',
                    'analyticsScreenId' => 'analytics.clientes',
                    'analyticsDatasetId' => 'clientes-uf-status',
                ],
            ],
        ]);

        $result = $service->render('documentos.especiais-base', [
            'parameters' => ['uf' => 'CE'],
        ]);

        self::assertSame('analytic', $result['sourceType']);
        self::assertSame(1, $result['table']['rowCount']);
        self::assertSame('CE', $result['table']['rows'][0]['uf']);
    }

    public function testRenderSupportsLabelProfileWithoutRows(): void
    {
        $service = $this->createService([
            'specialDocument' => [
                'classification' => [
                    'documentProfile' => 'special',
                    'documentKind' => 'label',
                ],
            ],
        ]);

        $result = $service->render('documentos.especiais-base', [
            'parameters' => ['status' => 'BLOQUEADO'],
        ]);

        self::assertSame('label', $result['profileType']);
        self::assertSame(0, $result['table']['rowCount']);
        self::assertIsArray($result['documentModel']['labels']);
    }

    public function testUnsafeMetadataIsRejected(): void
    {
        $service = $this->createService([
            'specialDocument' => [
                'layout' => [
                    'notes' => '<script>alert(1)</script>',
                ],
            ],
        ]);

        $this->expectException(RuntimeHttpException::class);
        $service->render('documentos.especiais-base', []);
    }

    private function createService(array $definitionPatch = []): RuntimeSpecialDocumentService
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $this->seedDatabase($connection);

        $screen = (new ScreenDefinition())
            ->setScreenId('documentos.especiais-base')
            ->setPageType('special_document')
            ->setStatus('published')
            ->setDefinition(array_replace_recursive([
                'pageType' => 'special_document',
                'screenId' => 'documentos.especiais-base',
                'program' => [
                    'id' => 'documento-especial-base',
                    'title' => 'Documento especial base',
                    'subtitle' => 'Contrato separado',
                ],
                'specialDocument' => [
                    'classification' => [
                        'documentProfile' => 'special',
                        'documentKind' => 'danfe',
                    ],
                    'renderEngine' => 'native',
                    'source' => [
                        'type' => 'operational',
                        'entityCode' => 'cliente',
                    ],
                    'parameters' => [
                        ['id' => 'status', 'field' => 'status', 'label' => 'Status', 'type' => 'enum', 'operator' => 'eq'],
                    ],
                    'layout' => [
                        'title' => 'Documento especial base',
                        'subtitle' => 'Placeholder',
                        'notes' => 'Sem layout livre',
                    ],
                    'outputs' => [
                        'html' => true,
                        'pdf' => true,
                    ],
                ],
            ], $definitionPatch));

        $screens = $this->createStub(ScreenDefinitionRepository::class);
        $screens->method('findPublishedByScreenId')->willReturn($screen);

        $entities = $this->createStub(RuntimeEntityDefinitionResolver::class);
        $entities->method('resolve')->willReturn($this->entityDefinition());

        $permissions = $this->createStub(PermissionResolver::class);
        $permissions->method('getTenantId')->willReturn('tenant-a');
        $permissions->method('getUserId')->willReturn('tester');
        $permissions->method('getSessionId')->willReturn('sess-special');

        $integrity = $this->createStub(StructuralIntegrityService::class);
        $customizations = $this->createStub(ProgramCustomizationResolver::class);
        $customizations->method('resolve')->willReturn(null);
        $analytics = $this->createStub(RuntimeAnalyticsService::class);
        $analytics->method('run')->willReturn([
            'data' => [
                ['uf' => 'CE', 'clientes' => 2, 'valor_total_sum' => 300, 'qtde_pedidos_sum' => 5],
            ],
            'columns' => [
                ['field' => 'uf', 'title' => 'UF', 'type' => 'string', 'role' => 'dimension'],
                ['field' => 'clientes', 'title' => 'Clientes', 'type' => 'integer', 'role' => 'measure'],
                ['field' => 'valor_total_sum', 'title' => 'Valor total', 'type' => 'currency', 'role' => 'measure', 'format' => 'c2'],
                ['field' => 'qtde_pedidos_sum', 'title' => 'Pedidos', 'type' => 'integer', 'role' => 'measure'],
            ],
        ]);

        return new RuntimeSpecialDocumentService(
            $screens,
            $entities,
            $connection,
            $permissions,
            $integrity,
            $customizations,
            $analytics,
            null,
        );
    }

    private function seedDatabase(Connection $connection): void
    {
        $connection->executeStatement('CREATE TABLE cliente (id INTEGER PRIMARY KEY AUTOINCREMENT, subscriber_id TEXT NOT NULL, nome TEXT NOT NULL, uf TEXT NOT NULL, status TEXT NOT NULL, valor_total REAL NOT NULL, qtde_pedidos INTEGER NOT NULL, data_cadastro TEXT NOT NULL, deleted_at TEXT DEFAULT NULL)');
        $connection->insert('cliente', ['subscriber_id' => 'tenant-a', 'nome' => 'Acme Comercio', 'uf' => 'CE', 'status' => 'ATIVO', 'valor_total' => 100, 'qtde_pedidos' => 2, 'data_cadastro' => '2026-01-01']);
        $connection->insert('cliente', ['subscriber_id' => 'tenant-a', 'nome' => 'Beta Servicos', 'uf' => 'SP', 'status' => 'ATIVO', 'valor_total' => 200, 'qtde_pedidos' => 3, 'data_cadastro' => '2026-01-02']);
        $connection->insert('cliente', ['subscriber_id' => 'tenant-a', 'nome' => 'Casa Norte', 'uf' => 'CE', 'status' => 'INATIVO', 'valor_total' => 50, 'qtde_pedidos' => 1, 'data_cadastro' => '2026-01-03']);
        $connection->insert('cliente', ['subscriber_id' => 'tenant-b', 'nome' => 'Outro Tenant', 'uf' => 'RJ', 'status' => 'ATIVO', 'valor_total' => 777, 'qtde_pedidos' => 8, 'data_cadastro' => '2026-01-04']);
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
                'dataCadastro' => ['column' => 'data_cadastro', 'readable' => true, 'virtual' => false, 'dataType' => 'date', 'label' => 'Cadastro'],
            ],
        ];
    }
}
