<?php

namespace App\Tests\ImportExport;

use App\ImportExport\ImportExportDestinationWriter;
use App\ImportExport\ImportExportEncodingHelper;
use App\ImportExport\ImportExportMappingService;
use App\ImportExport\ImportExportSourceLoader;
use App\ImportExport\ImportExportTxtLayoutRenderer;
use App\ImportExport\ImportExportValueMapper;
use App\Repository\BuilderEntityRepository;
use App\Repository\ImportExportMappingRepository;
use App\Runtime\RuntimeApiEntityActionService;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeEntityActionService;
use App\Runtime\RuntimeOdooEntityActionService;
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
        $sourceLoader = new ImportExportSourceLoader(
            $this->createStub(BuilderEntityRepository::class),
            $this->createStub(RuntimeEntityActionService::class),
            $this->createStub(RuntimeApiEntityActionService::class),
            $this->createStub(RuntimeOdooEntityActionService::class),
        );
        $destinationWriter = new ImportExportDestinationWriter(
            $sourceLoader,
            $valueMapper,
            $this->createStub(RuntimeEntityActionService::class),
            $this->createStub(RuntimeApiEntityActionService::class),
        );

        return new ImportExportMappingService(
            $this->createStub(ImportExportMappingRepository::class),
            $this->createStub(EntityManagerInterface::class),
            $encodingHelper,
            $valueMapper,
            $txtRenderer,
            $sourceLoader,
            $destinationWriter,
            $this->createStub(RuntimeTransactionService::class),
            $permissions,
        );
    }
}
