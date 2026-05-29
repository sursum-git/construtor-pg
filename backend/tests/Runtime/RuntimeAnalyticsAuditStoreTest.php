<?php

namespace App\Tests\Runtime;

use App\Runtime\RuntimeAnalyticsAuditStore;
use PHPUnit\Framework\TestCase;

class RuntimeAnalyticsAuditStoreTest extends TestCase
{
    public function testQueryFiltersAndCollectOptions(): void
    {
        $store = new RuntimeAnalyticsAuditStore('sqlite:///:memory:', true, 50, true);
        $store->initializeSchema();
        $store->record([
            'tenantId' => 'tenant-a',
            'userId' => 'admin',
            'sessionId' => 'sess-1',
            'screenId' => 'analytics.clientes',
            'datasetId' => 'clientes',
            'executionMode' => 'live',
            'resultSource' => 'live',
            'rowCount' => 2,
            'totalCount' => 2,
            'metadata' => ['auditContext' => 'analytics'],
            'consultedAt' => '2026-05-29 03:00:00',
        ]);
        $store->record([
            'tenantId' => 'tenant-b',
            'userId' => 'analista',
            'sessionId' => 'sess-2',
            'screenId' => 'analytics.faturamento',
            'datasetId' => 'faturamento',
            'executionMode' => 'cached',
            'resultSource' => 'cache_hit',
            'rowCount' => 1,
            'totalCount' => 1,
            'metadata' => ['auditContext' => 'analytics'],
            'consultedAt' => '2026-05-29 04:00:00',
        ]);
        $store->record([
            'tenantId' => 'tenant-a',
            'userId' => 'admin',
            'sessionId' => 'sess-3',
            'screenId' => 'relatorios.clientes-operacional',
            'datasetId' => 'relatorio-clientes-operacional',
            'viewId' => 'excel',
            'executionMode' => 'operational',
            'resultSource' => 'report_run',
            'rowCount' => 4,
            'totalCount' => 4,
            'metadata' => ['auditContext' => 'report', 'reportId' => 'relatorio-clientes-operacional'],
            'consultedAt' => '2026-05-29 05:00:00',
        ]);

        $filtered = $store->query([
            'tenantId' => 'tenant-a',
            'resultSource' => 'live',
            'limit' => 20,
        ]);

        self::assertSame(1, $filtered['total']);
        self::assertCount(1, $filtered['items']);
        self::assertSame('analytics.clientes', $filtered['items'][0]['screenId']);

        $options = $store->collectFilterOptions();
        self::assertContains('tenant-a', $options['tenantIds']);
        self::assertContains('tenant-b', $options['tenantIds']);
        self::assertContains('analytics.clientes', $options['screenIds']);
        self::assertContains('cache_hit', $options['resultSources']);

        $reportOnly = $store->query(['reportId' => 'relatorio-clientes-operacional'], 'report');
        self::assertSame(1, $reportOnly['total']);
        self::assertSame('relatorios.clientes-operacional', $reportOnly['items'][0]['screenId']);

        $reportOptions = $store->collectFilterOptions(40, 'report');
        self::assertContains('relatorios.clientes-operacional', $reportOptions['screenIds']);
    }
}
