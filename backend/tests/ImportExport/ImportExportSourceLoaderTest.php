<?php

namespace App\Tests\ImportExport;

use App\ImportExport\ImportExportSourceLoader;
use App\ImportExport\ImportExportValueMapper;
use App\Repository\BuilderEntityRepository;
use App\Runtime\RuntimeApiEntityActionService;
use App\Runtime\RuntimeEntityActionService;
use App\Runtime\RuntimeOdooEntityActionService;
use PHPUnit\Framework\TestCase;

class ImportExportSourceLoaderTest extends TestCase
{
    public function testLoadsXmlFileSourceFromParameters(): void
    {
        $loader = new ImportExportSourceLoader(
            $this->createStub(BuilderEntityRepository::class),
            $this->createStub(RuntimeEntityActionService::class),
            $this->createStub(RuntimeApiEntityActionService::class),
            $this->createStub(RuntimeOdooEntityActionService::class),
            new ImportExportValueMapper(),
        );

        $result = $loader->loadSources([
            'sources' => [[
                'type' => 'file',
                'fileFormat' => 'xml',
                'alias' => 'cliente_xml',
                'contentParameter' => 'xmlContent',
                'recordPath' => '/clientes/cliente',
                'fields' => [
                    ['targetField' => 'id', 'xpath' => '@id'],
                    ['targetField' => 'nome', 'xpath' => 'nome/text()'],
                ],
            ]],
        ], [
            'xmlContent' => '<clientes><cliente id="1"><nome>Ana</nome></cliente><cliente id="2"><nome>Beta</nome></cliente></clientes>',
        ], true);

        self::assertCount(1, $result);
        self::assertSame('cliente_xml', $result[0]['alias']);
        self::assertSame('1', $result[0]['records'][0]['id']);
        self::assertSame('Ana', $result[0]['records'][0]['nome']);
        self::assertSame('2', $result[0]['records'][1]['id']);
    }
}
