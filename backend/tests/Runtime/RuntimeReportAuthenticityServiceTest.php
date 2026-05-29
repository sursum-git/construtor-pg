<?php

namespace App\Tests\Runtime;

use App\Runtime\RuntimeAnalyticsAuditStore;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeReportAuthenticityService;
use PHPUnit\Framework\TestCase;

class RuntimeReportAuthenticityServiceTest extends TestCase
{
    public function testVerifyFindsRecordedHash(): void
    {
        $store = new RuntimeAnalyticsAuditStore('sqlite:///:memory:', true, 50, true);
        $store->initializeSchema();
        $store->record([
            'tenantId' => 'tenant-a',
            'userId' => 'admin',
            'sessionId' => 'sess-1',
            'screenId' => 'relatorios.clientes-operacional',
            'datasetId' => 'relatorio-clientes-operacional',
            'viewId' => 'pdf',
            'executionMode' => 'operational',
            'resultSource' => 'report_run',
            'rowCount' => 4,
            'totalCount' => 4,
            'metadata' => [
                'auditContext' => 'report',
                'reportId' => 'relatorio-clientes-operacional',
                'reportTitle' => 'Relatorio operacional de clientes',
                'sourceType' => 'operational',
                'authenticity' => [
                    'algorithm' => 'sha256',
                    'hash' => 'sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                    'footerLabel' => 'Codigo de autenticidade',
                    'verificationPath' => 'report-authenticity.html',
                    'recorded' => true,
                    'storage' => [
                        'storeCanonicalPayload' => true,
                        'storeExportArtifact' => true,
                    ],
                ],
                'artifact' => [
                    'stored' => true,
                    'format' => 'pdf',
                    'fileName' => 'relatorio-clientes-operacional.pdf',
                    'contentType' => 'application/pdf',
                ],
            ],
            'consultedAt' => '2026-05-29 06:00:00',
        ]);

        $service = new RuntimeReportAuthenticityService($store);
        $result = $service->verify('sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');

        self::assertTrue($result['found']);
        self::assertSame('Relatorio operacional de clientes', $result['report']['title']);
        self::assertSame('pdf', $result['report']['format']);
        self::assertTrue($result['artifact']['stored']);
    }

    public function testVerifyRejectsInvalidHash(): void
    {
        $service = new RuntimeReportAuthenticityService(new RuntimeAnalyticsAuditStore('sqlite:///:memory:', true, 50, true));

        $this->expectException(RuntimeHttpException::class);
        $this->expectExceptionMessage('Hash de autenticidade invalido.');

        $service->verify('abc');
    }
}
