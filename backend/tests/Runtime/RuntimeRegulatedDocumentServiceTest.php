<?php

namespace App\Tests\Runtime;

use App\Entity\ScreenDefinition;
use App\Repository\ScreenDefinitionRepository;
use App\Runtime\PermissionResolver;
use App\Runtime\ProgramCustomizationResolver;
use App\Runtime\RuntimeAnalyticsService;
use App\Runtime\RuntimeEntityDefinitionResolver;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeRegulatedDocumentService;
use App\Runtime\RuntimeRegulatedDocumentStore;
use App\Runtime\StructuralIntegrityService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

class RuntimeRegulatedDocumentServiceTest extends TestCase
{
    public function testPrepareIssueVerifyAndArtifactOperational(): void
    {
        $service = $this->createService();

        $prepared = $service->prepare('documentos.regulados-fiscal-base', [
            'parameters' => ['status' => 'ATIVO'],
        ]);
        self::assertTrue($prepared['ok']);
        self::assertSame('prepared', $prepared['state']);

        $preview = $service->render('documentos.regulados-fiscal-base', [
            'issueId' => $prepared['issueId'],
        ]);
        self::assertSame('rendered', $preview['state']);
        self::assertSame(2, $preview['table']['rowCount']);

        $issued = $service->issue('documentos.regulados-fiscal-base', [
            'issueId' => $prepared['issueId'],
            'format' => 'pdf',
        ]);
        self::assertSame('issued', $issued['state']);
        self::assertStringStartsWith('sha256:', $issued['hash']);
        self::assertSame('pdf', $issued['format']);
        self::assertSame('download', $issued['deliveryMode']);

        $verified = $service->verify('documentos.regulados-fiscal-base', [
            'issueId' => $prepared['issueId'],
            'hash' => $issued['hash'],
        ]);
        self::assertTrue($verified['ok']);
        self::assertSame('verified', $verified['state']);

        $artifact = $service->artifact('documentos.regulados-fiscal-base', [
            'issueId' => $prepared['issueId'],
        ]);
        self::assertSame('download', $artifact['deliveryMode']);
        self::assertSame('application/pdf', $artifact['contentType']);
        self::assertStringStartsWith('%PDF', (string) base64_decode((string) $artifact['contentBase64']));
    }

    public function testIssueReturnsQzTrayPayloadWhenEnabled(): void
    {
        $service = $this->createService([
            'regulatedDocument' => [
                'printing' => [
                    'qzTray' => [
                        'enabled' => true,
                        'printerName' => 'IMP-LOCAL-01',
                        'jobName' => 'Documento regulado fiscal base',
                        'copies' => 1,
                    ],
                ],
            ],
        ]);

        $prepared = $service->prepare('documentos.regulados-fiscal-base', [
            'parameters' => ['status' => 'ATIVO'],
        ]);
        $issued = $service->issue('documentos.regulados-fiscal-base', [
            'issueId' => $prepared['issueId'],
            'format' => 'pdf',
            'deliveryMode' => 'qz_tray',
        ]);

        self::assertSame('qz_tray', $issued['deliveryMode']);
        self::assertSame('IMP-LOCAL-01', $issued['printer']['printerName'] ?? '');
    }

    public function testPrepareSupportsAnalyticSource(): void
    {
        $service = $this->createService([
            'regulatedDocument' => [
                'track' => 'logistics',
                'documentType' => 'logistics_base',
                'source' => [
                    'type' => 'analytic',
                    'analyticsScreenId' => 'analytics.clientes',
                    'analyticsDatasetId' => 'clientes-uf-status',
                ],
            ],
        ]);

        $preview = $service->render('documentos.regulados-fiscal-base', [
            'parameters' => ['status' => 'ATIVO'],
        ]);

        self::assertSame(1, $preview['table']['rowCount']);
        self::assertSame('CE', $preview['table']['rows'][0]['uf']);
    }

    public function testVerifyFailsWhenProvidedHashDoesNotMatchIssuedHash(): void
    {
        $service = $this->createService();
        $prepared = $service->prepare('documentos.regulados-fiscal-base', [
            'parameters' => ['status' => 'ATIVO'],
        ]);
        $issued = $service->issue('documentos.regulados-fiscal-base', [
            'issueId' => $prepared['issueId'],
            'format' => 'html',
        ]);

        $verified = $service->verify('documentos.regulados-fiscal-base', [
            'issueId' => $prepared['issueId'],
            'hash' => 'sha256:' . str_repeat('0', 64),
        ]);

        self::assertFalse($verified['ok']);
        self::assertFalse($verified['verification']['providedHashMatches']);
        self::assertSame($issued['hash'], $verified['verification']['recordedHash']);
    }

    public function testUnsafeMetadataIsRejected(): void
    {
        $service = $this->createService([
            'regulatedDocument' => [
                'layout' => [
                    'notes' => '<script>alert(1)</script>',
                ],
            ],
        ]);

        $this->expectException(RuntimeHttpException::class);
        $service->schema('documentos.regulados-fiscal-base');
    }

    private function createService(array $definitionPatch = []): RuntimeRegulatedDocumentService
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $this->seedDatabase($connection);

        $screen = (new ScreenDefinition())
            ->setScreenId('documentos.regulados-fiscal-base')
            ->setPageType('regulated_document')
            ->setStatus('published')
            ->setDefinition(array_replace_recursive($this->definition(), $definitionPatch));

        $screens = $this->createStub(ScreenDefinitionRepository::class);
        $screens->method('findPublishedByScreenId')->willReturn($screen);

        $entities = $this->createStub(RuntimeEntityDefinitionResolver::class);
        $entities->method('resolve')->willReturn($this->entityDefinition());

        $permissions = $this->createStub(PermissionResolver::class);
        $permissions->method('getTenantId')->willReturn('tenant-a');
        $permissions->method('getUserId')->willReturn('tester');
        $permissions->method('getSessionId')->willReturn('sess-regulated');

        $integrity = $this->createStub(StructuralIntegrityService::class);
        $customizations = $this->createStub(ProgramCustomizationResolver::class);
        $customizations->method('resolve')->willReturn(null);
        $analytics = $this->createStub(RuntimeAnalyticsService::class);
        $analytics->method('run')->willReturn([
            'data' => [
                ['uf' => 'CE', 'status' => 'ATIVO', 'clientes' => 2, 'valor_total_sum' => 300],
            ],
            'columns' => [
                ['field' => 'uf', 'title' => 'UF', 'type' => 'string'],
                ['field' => 'status', 'title' => 'Status', 'type' => 'string'],
                ['field' => 'clientes', 'title' => 'Clientes', 'type' => 'integer'],
                ['field' => 'valor_total_sum', 'title' => 'Valor total', 'type' => 'currency'],
            ],
        ]);

        $store = new RuntimeRegulatedDocumentStore('sqlite:///:memory:', true, true);

        return new RuntimeRegulatedDocumentService(
            $screens,
            $entities,
            $connection,
            $permissions,
            $integrity,
            $customizations,
            $analytics,
            $store,
        );
    }

    private function seedDatabase(Connection $connection): void
    {
        $connection->executeStatement('CREATE TABLE cliente (id INTEGER PRIMARY KEY AUTOINCREMENT, subscriber_id TEXT NOT NULL, nome TEXT NOT NULL, uf TEXT NOT NULL, status TEXT NOT NULL, valor_total REAL NOT NULL, qtde_pedidos INTEGER NOT NULL, deleted_at TEXT DEFAULT NULL)');
        $connection->insert('cliente', ['subscriber_id' => 'tenant-a', 'nome' => 'Acme Comercio', 'uf' => 'CE', 'status' => 'ATIVO', 'valor_total' => 100, 'qtde_pedidos' => 2]);
        $connection->insert('cliente', ['subscriber_id' => 'tenant-a', 'nome' => 'Beta Servicos', 'uf' => 'SP', 'status' => 'ATIVO', 'valor_total' => 200, 'qtde_pedidos' => 3]);
        $connection->insert('cliente', ['subscriber_id' => 'tenant-a', 'nome' => 'Casa Norte', 'uf' => 'CE', 'status' => 'INATIVO', 'valor_total' => 50, 'qtde_pedidos' => 1]);
        $connection->insert('cliente', ['subscriber_id' => 'tenant-b', 'nome' => 'Outro Tenant', 'uf' => 'RJ', 'status' => 'ATIVO', 'valor_total' => 777, 'qtde_pedidos' => 8]);
    }

    private function definition(): array
    {
        return [
            'schemaVersion' => '1.0',
            'pageType' => 'regulated_document',
            'screenId' => 'documentos.regulados-fiscal-base',
            'program' => [
                'id' => 'documento-regulado-base',
                'title' => 'Documento regulado fiscal base',
            ],
            'regulatedDocument' => [
                'track' => 'fiscal',
                'documentType' => 'fiscal_base',
                'complianceProfile' => 'near_homologated',
                'renderEngine' => 'internal',
                'source' => [
                    'type' => 'operational',
                    'entityCode' => 'cliente',
                ],
                'parameters' => [
                    ['id' => 'status', 'field' => 'status', 'label' => 'Status', 'type' => 'enum', 'operator' => 'eq', 'required' => true],
                ],
                'artifactPolicy' => [
                    'storeCanonicalPayload' => true,
                    'storeArtifact' => true,
                    'defaultFormat' => 'pdf',
                ],
                'verification' => [
                    'enabled' => true,
                    'algorithm' => 'sha256',
                    'publicPath' => 'regulated-document-authenticity.html',
                    'label' => 'Codigo de conferencia',
                ],
                'retention' => [
                    'keepPayload' => true,
                    'keepArtifact' => true,
                    'storeDays' => 365,
                ],
                'layout' => [
                    'title' => 'Documento regulado fiscal base',
                    'subtitle' => 'Base interna',
                    'notes' => 'Sem template livre',
                ],
            ],
        ];
    }

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
