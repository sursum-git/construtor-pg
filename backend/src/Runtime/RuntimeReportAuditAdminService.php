<?php

namespace App\Runtime;

class RuntimeReportAuditAdminService
{
    private const AUDIT_CONTEXT = 'report';

    public function __construct(
        private readonly RuntimeAnalyticsAuditStore $auditStore,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function bootstrap(): array
    {
        $enabled = $this->auditStore->isEnabled();
        $options = $enabled ? $this->auditStore->collectFilterOptions(40, self::AUDIT_CONTEXT) : [
            'tenantIds' => [],
            'userIds' => [],
            'screenIds' => [],
            'datasetIds' => [],
            'resultSources' => [],
            'reportIds' => [],
        ];
        $recent = $enabled ? $this->auditStore->query(['limit' => 30], self::AUDIT_CONTEXT) : ['items' => [], 'total' => 0];

        return [
            'enabled' => $enabled,
            'filterOptions' => $this->injectReportIds($options, $recent['items'] ?? []),
            'summary' => $this->buildSummary($recent['items'] ?? [], (int) ($recent['total'] ?? 0)),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function listEntries(array $filters = []): array
    {
        if (!$this->auditStore->isEnabled()) {
            return [
                'enabled' => false,
                'items' => [],
                'total' => 0,
                'summary' => $this->buildSummary([], 0),
            ];
        }

        $result = $this->auditStore->query($filters, self::AUDIT_CONTEXT);

        return [
            'enabled' => true,
            'items' => $result['items'],
            'total' => $result['total'],
            'summary' => $this->buildSummary($result['items'], (int) $result['total']),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, array<int, string>> $options
     * @return array<string, array<int, string>>
     */
    private function injectReportIds(array $options, array $items): array
    {
        $reportIds = [];
        foreach ($items as $item) {
            $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];
            $reportId = trim((string) ($metadata['reportId'] ?? ''));
            if ($reportId === '' || in_array($reportId, $reportIds, true)) {
                continue;
            }
            $reportIds[] = $reportId;
        }
        $options['reportIds'] = $reportIds;

        return $options;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private function buildSummary(array $items, int $total): array
    {
        $bySource = [];
        $byScreen = [];
        $byReport = [];
        foreach ($items as $item) {
            $source = (string) ($item['resultSource'] ?? '');
            $screen = (string) ($item['screenId'] ?? '');
            $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];
            $reportId = trim((string) ($metadata['reportId'] ?? ''));
            if ($source !== '') {
                $bySource[$source] = (int) ($bySource[$source] ?? 0) + 1;
            }
            if ($screen !== '') {
                $byScreen[$screen] = (int) ($byScreen[$screen] ?? 0) + 1;
            }
            if ($reportId !== '') {
                $byReport[$reportId] = (int) ($byReport[$reportId] ?? 0) + 1;
            }
        }

        return [
            'total' => $total,
            'loaded' => count($items),
            'bySource' => $bySource,
            'byScreen' => $byScreen,
            'byReport' => $byReport,
        ];
    }
}
