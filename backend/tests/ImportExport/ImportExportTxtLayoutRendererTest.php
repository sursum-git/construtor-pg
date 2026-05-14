<?php

namespace App\Tests\ImportExport;

use App\ImportExport\ImportExportEncodingHelper;
use App\ImportExport\ImportExportTxtLayoutRenderer;
use App\ImportExport\ImportExportValueMapper;
use PHPUnit\Framework\TestCase;

class ImportExportTxtLayoutRendererTest extends TestCase
{
    public function testRendersFixedWidthInByteModeWithDestinationEncodingDefault(): void
    {
        $renderer = new ImportExportTxtLayoutRenderer(new ImportExportValueMapper(), new ImportExportEncodingHelper());

        $result = $renderer->build([
            'destination' => [
                'fileNamePattern' => 'clientes.txt',
                'lineBreak' => "\n",
                'encodingLabel' => 'ISO-8859-1',
                'recordLayouts' => [[
                    'sourceAlias' => 'cliente',
                    'lineMode' => 'fixed',
                    'widthMode' => 'bytes',
                    'fields' => [
                        ['constant' => 'CLI', 'length' => 3],
                        ['sourcePath' => 'nome', 'length' => 20, 'align' => 'left', 'padChar' => ' '],
                        ['sourcePath' => 'status', 'length' => 5, 'align' => 'left', 'padChar' => ' '],
                    ],
                ]],
            ],
            'options' => ['previewLimit' => 20],
        ], [
            ['alias' => 'cliente', 'records' => [[
                'nome' => json_decode('"Ana Com\u00E9rcio LTDA"', true),
                'status' => 'X',
            ]]],
        ], true);

        $line = substr($result['content'], 0, -1);
        self::assertSame('text/plain; charset=ISO-8859-1', $result['mimeType']);
        self::assertSame("CLIAna Comércio LTDA   X    \n", $result['previewText']);
        self::assertSame(28, strlen($line));
        self::assertSame('CLIAna Comércio LTDA   X    ', mb_convert_encoding($line, 'UTF-8', 'ISO-8859-1'));
    }
}
