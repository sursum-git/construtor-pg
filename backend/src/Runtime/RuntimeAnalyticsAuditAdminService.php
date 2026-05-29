<?php

namespace App\Runtime;

class RuntimeAnalyticsAuditAdminService
{
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
        $options = $enabled ? $this->auditStore->collectFilterOptions() : [
            'tenantIds' => [],
            'userIds' => [],
            'screenIds' => [],
            'datasetIds' => [],
            'resultSources' => [],
        ];
        $recent = $enabled ? $this->auditStore->query(['limit' => 30]) : ['items' => [], 'total' => 0];

        return [
            'enabled' => $enabled,
            'filterOptions' => $options,
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

        $result = $this->auditStore->query($filters);

        return [
            'enabled' => true,
            'items' => $result['items'],
            'total' => $result['total'],
            'summary' => $this->buildSummary($result['items'], (int) $result['total']),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private function buildSummary(array $items, int $total): array
    {
        $bySource = [];
        $byScreen = [];
        foreach ($items as $item) {
            $source = (string) ($item['resultSource'] ?? '');
            $screen = (string) ($item['screenId'] ?? '');
            if ($source !== '') {
                $bySource[$source] = (int) ($bySource[$source] ?? 0) + 1;
            }
            if ($screen !== '') {
                $byScreen[$screen] = (int) ($byScreen[$screen] ?? 0) + 1;
            }
        }

        return [
            'total' => $total,
            'loaded' => count($items),
            'bySource' => $bySource,
            'byScreen' => $byScreen,
        ];
    }
}
