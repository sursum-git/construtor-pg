<?php

declare(strict_types=1);

namespace App\Tests\Printing;

use App\Printing\Delivery\DownloadArtifactDelivery;
use App\Printing\Delivery\QzTrayArtifactDelivery;
use App\Printing\DTO\PrintJob;
use App\Printing\DTO\PrinterConfig;
use App\Printing\DTO\ReportRequest;
use App\Printing\Enum\PrinterLanguage;
use App\Printing\Exception\InvalidTemplateException;
use App\Printing\Exception\PrintingException;
use App\Printing\Report\InternalReportPdfGenerator;
use App\Printing\Template\SimpleTemplateRenderer;
use App\Printing\Transport\NullPrinterTransport;
use PHPUnit\Framework\TestCase;

class InternalPrintingBridgeTest extends TestCase
{
    public function testDownloadDeliveryEncodesGeneratedReportContent(): void
    {
        $generator = new InternalReportPdfGenerator(
            fn (ReportRequest $request): string => '%PDF-test-' . $request->title
        );
        $delivery = new DownloadArtifactDelivery();

        $payload = $delivery->deliverReport($generator->generate(new ReportRequest(
            'relatorio.pdf',
            'Clientes',
            'pdf'
        )));

        self::assertSame('download', $payload['deliveryMode']);
        self::assertSame('application/pdf', $payload['contentType']);
        self::assertStringStartsWith('%PDF-test-Clientes', (string) base64_decode((string) $payload['contentBase64']));
    }

    public function testQzTrayDeliveryMarksLocalPrinterMetadata(): void
    {
        $generator = new InternalReportPdfGenerator(
            fn (ReportRequest $request): string => '%PDF-test-' . $request->title
        );
        $delivery = new QzTrayArtifactDelivery();

        $payload = $delivery->deliverReport($generator->generate(new ReportRequest(
            'relatorio.pdf',
            'Clientes',
            'pdf'
        )), [
            'printerName' => 'IMP-LOCAL-01',
            'jobName' => 'Relatorio de clientes',
            'copies' => 2,
        ]);

        self::assertSame('qz_tray', $payload['deliveryMode']);
        self::assertSame('IMP-LOCAL-01', $payload['printer']['printerName']);
        self::assertSame(2, $payload['printer']['copies']);
    }

    public function testTemplateRendererRejectsUnsafeTemplate(): void
    {
        $renderer = new SimpleTemplateRenderer();

        $this->expectException(InvalidTemplateException::class);
        $renderer->render('<script>alert(1)</script>', []);
    }

    public function testTemplateRendererReplacesKnownTokens(): void
    {
        $renderer = new SimpleTemplateRenderer();

        $result = $renderer->render('Pedido {{codigo}} de {{cliente}}', [
            'codigo' => 'P-100',
            'cliente' => 'ACME',
        ]);

        self::assertSame('Pedido P-100 de ACME', $result);
    }

    public function testNullPrinterTransportFailsWithClearMessage(): void
    {
        $transport = new NullPrinterTransport();

        $this->expectException(PrintingException::class);
        $this->expectExceptionMessage('Transporte fisico de impressao ainda nao implementado');

        $transport->send(
            new PrintJob('RAW', 'application/octet-stream', PrinterLanguage::Raw),
            new PrinterConfig('raw_tcp_9100', '127.0.0.1', 9100, null, PrinterLanguage::Raw)
        );
    }
}
