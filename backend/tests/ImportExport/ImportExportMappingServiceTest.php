<?php

namespace App\Tests\ImportExport;

use App\ImportExport\ImportExportDestinationWriter;
use App\ImportExport\ImportExportEncodingHelper;
use App\ImportExport\ImportExportMappingService;
use App\ImportExport\ImportExportSourceLoader;
use App\ImportExport\ImportExportTxtLayoutRenderer;
use App\ImportExport\ImportExportValueMapper;
use App\ImportExport\ImportExportXmlRenderer;
use App\Repository\ImportExportExecutionRepository;
use App\Repository\BuilderEntityRepository;
use App\Repository\ImportExportMappingRepository;
use App\Repository\ImportExportMappingVersionRepository;
use App\Repository\ImportExportScheduleRepository;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeApiEntityActionService;
use App\Runtime\RuntimeEntityActionService;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeOdooEntityActionService;
use App\Runtime\StructuralIntegrityService;
use App\Runtime\RuntimeTransactionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ImportExportMappingServiceTest extends TestCase
{
    public function testRejectsInvalidWidthMode(): void
    {
        $service = $this->service();

        $this->expectException(RuntimeHttpException::class);
        $this->expectExceptionMessage('widthMode do TXT deve ser characters ou bytes.');

        $service->save([
            'code' => 'mapa.txt',
            'name' => 'Mapa TXT',
            'direction' => 'export',
            'targetType' => 'file',
            'format' => 'txt_layout',
            'mapping' => [
                'source' => ['type' => 'entity', 'entityCode' => 'cliente', 'mode' => 'list'],
                'destination' => [
                    'type' => 'file',
                    'recordLayouts' => [[
                        'sourceAlias' => 'cliente',
                        'lineMode' => 'fixed',
                        'widthMode' => 'invalid',
                        'fields' => [['constant' => 'A', 'length' => 1]],
                    ]],
                ],
            ],
        ]);
    }

    public function testRejectsInvalidEncodingLabel(): void
    {
        $service = $this->service();

        $this->expectException(RuntimeHttpException::class);
        $this->expectExceptionMessage('Encoding informado nao e suportado.');

        $service->save([
            'code' => 'mapa.csv',
            'name' => 'Mapa CSV',
            'direction' => 'export',
            'targetType' => 'file',
            'format' => 'csv',
            'mapping' => [
                'source' => ['type' => 'entity', 'entityCode' => 'cliente', 'mode' => 'list'],
                'destination' => [
                    'type' => 'file',
                    'encodingLabel' => 'INVALID-XYZ',
                    'columns' => [['sourcePath' => 'nome', 'header' => 'Nome']],
                ],
            ],
        ]);
    }

    public function testBuildCsvUsesConfiguredEncoding(): void
    {
        $service = $this->service();
        $result = (function (array $mapping, array $sources, bool $preview): array {
            return $this->buildCsv($mapping, $sources, $preview);
        })->call($service, [
            'destination' => [
                'fileNamePattern' => 'clientes.csv',
                'delimiter' => ';',
                'quote' => '"',
                'includeHeader' => true,
                'encodingLabel' => 'ISO-8859-1',
                'columns' => [
                    ['header' => 'Nome', 'sourcePath' => 'nome'],
                ],
            ],
        ], [
            ['records' => [['nome' => json_decode('"Ana Com\u00E9rcio"', true)]]],
        ], false);

        self::assertSame('text/csv; charset=ISO-8859-1', $result['mimeType']);
        self::assertSame("\"Nome\"\r\n\"Ana Comércio\"\r\n", $result['previewText']);
        self::assertSame("\"Nome\"\r\n\"Ana Comércio\"\r\n", mb_convert_encoding($result['content'], 'UTF-8', 'ISO-8859-1'));
    }

    public function testBuildXmlUsesConfiguredColumns(): void
    {
        $service = $this->service();
        $result = (function (array $mapping, array $sources, bool $preview): array {
            return $this->buildXml($mapping, $sources, $preview);
        })->call($service, [
            'destination' => [
                'fileNamePattern' => 'clientes.xml',
                'encodingLabel' => 'UTF-8',
                'rootName' => 'clientes',
                'itemName' => 'cliente',
                'prettyPrint' => true,
                'columns' => [
                    ['targetName' => 'id', 'sourcePath' => 'id'],
                    ['targetName' => 'nome', 'sourcePath' => 'nome'],
                ],
            ],
            'options' => ['previewLimit' => 20],
        ], [
            ['records' => [
                ['id' => 1, 'nome' => 'Ana Comercio'],
                ['id' => 2, 'nome' => 'Beta Ltda'],
            ]],
        ], false);

        self::assertSame('application/xml; charset=UTF-8', $result['mimeType']);
        self::assertStringContainsString('<clientes>', $result['previewText']);
        self::assertStringContainsString('<cliente>', $result['previewText']);
        self::assertStringContainsString('<nome>Ana Comercio</nome>', $result['previewText']);
    }

    public function testBuildXmlSupportsNamespacesAttributesAndHierarchy(): void
    {
        $service = $this->service();
        $result = (function (array $mapping, array $sources, bool $preview): array {
            return $this->buildXml($mapping, $sources, $preview);
        })->call($service, [
            'destination' => [
                'fileNamePattern' => 'sped.xml',
                'encodingLabel' => 'UTF-8',
                'rootName' => 'doc:arquivo',
                'prettyPrint' => true,
                'namespaces' => [
                    ['prefix' => 'doc', 'uri' => 'urn:test:doc'],
                ],
                'rootAttributes' => [
                    ['name' => 'versao', 'constant' => '1.0'],
                ],
                'xmlLayouts' => [
                    [
                        'name' => 'doc:cliente',
                        'sourceAlias' => 'cliente',
                        'attributes' => [
                            ['name' => 'id', 'sourcePath' => 'id'],
                        ],
                        'fields' => [
                            ['name' => 'nome', 'sourcePath' => 'nome'],
                        ],
                        'children' => [
                            [
                                'name' => 'doc:cidade',
                                'sourceAlias' => 'cidade',
                                'linkBy' => [
                                    ['parentPath' => 'id', 'childField' => 'cliente_id'],
                                ],
                                'textSourcePath' => 'nome',
                            ],
                        ],
                    ],
                ],
            ],
            'options' => ['previewLimit' => 20],
        ], [
            ['alias' => 'cliente', 'records' => [
                ['id' => 1, 'nome' => 'Ana Comercio'],
            ]],
            ['alias' => 'cidade', 'records' => [
                ['cliente_id' => 1, 'nome' => 'Fortaleza'],
            ]],
        ], false);

        self::assertStringContainsString('<doc:arquivo xmlns:doc="urn:test:doc" versao="1.0">', $result['previewText']);
        self::assertStringContainsString('<doc:cliente id="1">', $result['previewText']);
        self::assertStringContainsString('<nome>Ana Comercio</nome>', $result['previewText']);
        self::assertStringContainsString('<doc:cidade>Fortaleza</doc:cidade>', $result['previewText']);
    }

    private function service(): ImportExportMappingService
    {
        $request = Request::create('/api/admin/import-export-mappings', 'POST', [
            'runtimePermissions' => 'admin.read,admin.write',
        ]);
        $stack = new RequestStack();
        $stack->push($request);
        $permissions = new PermissionResolver($stack);
        $encodingHelper = new ImportExportEncodingHelper();
        $valueMapper = new ImportExportValueMapper();
        $txtRenderer = new ImportExportTxtLayoutRenderer($valueMapper, $encodingHelper);
        $xmlRenderer = new ImportExportXmlRenderer($valueMapper, $encodingHelper);
        $sourceLoader = new ImportExportSourceLoader(
            $this->createStub(BuilderEntityRepository::class),
            $this->createStub(RuntimeEntityActionService::class),
            $this->createStub(RuntimeApiEntityActionService::class),
            $this->createStub(RuntimeOdooEntityActionService::class),
            $valueMapper,
        );
        $destinationWriter = new ImportExportDestinationWriter(
            $sourceLoader,
            $valueMapper,
            $this->createStub(RuntimeEntityActionService::class),
            $this->createStub(RuntimeApiEntityActionService::class),
        );

        return new ImportExportMappingService(
            $this->createStub(ImportExportMappingRepository::class),
            $this->createStub(ImportExportMappingVersionRepository::class),
            $this->createStub(ImportExportExecutionRepository::class),
            $this->createStub(ImportExportScheduleRepository::class),
            $this->createStub(EntityManagerInterface::class),
            $encodingHelper,
            $valueMapper,
            $txtRenderer,
            $xmlRenderer,
            $sourceLoader,
            $destinationWriter,
            $this->createStub(RuntimeTransactionService::class),
            $permissions,
            $this->createStub(StructuralIntegrityService::class),
        );
    }
}
