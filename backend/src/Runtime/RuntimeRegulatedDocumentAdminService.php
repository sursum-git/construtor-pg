<?php

namespace App\Runtime;

class RuntimeRegulatedDocumentAdminService
{
    public function __construct(
        private readonly RuntimeRegulatedDocumentStore $store,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function bootstrap(): array
    {
        $enabled = $this->store->isEnabled();
        $options = $enabled ? $this->store->collectFilterOptions() : [
            'tenantIds' => [],
            'userIds' => [],
            'screenIds' => [],
            'tracks' => [],
            'documentTypes' => [],
            'states' => [],
        ];
        $recent = $enabled ? $this->store->query(['limit' => 30]) : ['items' => [], 'total' => 0];

        return [
            'enabled' => $enabled,
            'filterOptions' => $options,
            'summary' => $this->buildSummary($recent['items'] ?? [], (int) ($recent['total'] ?? 0)),
            'observability' => $enabled ? $this->store->collectObservabilitySummary() : [],
            'roadmap' => [
                'primaryTrack' => 'fiscal',
                'trackStatus' => [
                    'fiscal' => 'primeira_trilha_concreta',
                    'banking' => 'base_geral',
                    'logistics' => 'base_geral',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function listEntries(array $filters = []): array
    {
        if (!$this->store->isEnabled()) {
            return [
                'enabled' => false,
                'items' => [],
                'total' => 0,
                'summary' => $this->buildSummary([], 0),
            ];
        }

        $result = $this->store->query($filters);

        return [
            'enabled' => true,
            'items' => $result['items'],
            'total' => $result['total'],
            'summary' => $this->buildSummary($result['items'], (int) $result['total']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function artifact(string $issueId): array
    {
        if (!$this->store->isEnabled()) {
            throw new RuntimeHttpException('REGULATED_DOCUMENT_STORE_DISABLED', 'Storage do modulo regulado desabilitado.', 422);
        }

        $record = $this->store->findByIssueId($issueId);
        if (!$record) {
            throw new RuntimeHttpException('REGULATED_DOCUMENT_NOT_FOUND', 'Documento regulado nao encontrado.', 404, [
                'issueId' => $issueId,
            ]);
        }
        $artifact = is_array($record['artifact'] ?? null) ? $record['artifact'] : [];
        if (empty($artifact['contentBase64'])) {
            throw new RuntimeHttpException('REGULATED_DOCUMENT_ARTIFACT_NOT_AVAILABLE', 'Artefato nao esta disponivel para este documento.', 404, [
                'issueId' => $issueId,
            ]);
        }

        return [
            'ok' => true,
            'issueId' => $issueId,
            'format' => (string) ($artifact['format'] ?? ''),
            'fileName' => (string) ($artifact['fileName'] ?? ''),
            'contentType' => (string) ($artifact['contentType'] ?? 'application/octet-stream'),
            'contentBase64' => (string) ($artifact['contentBase64'] ?? ''),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private function buildSummary(array $items, int $total): array
    {
        $byState = [];
        $byTrack = [];
        foreach ($items as $item) {
            $state = (string) ($item['state'] ?? '');
            $track = (string) ($item['track'] ?? '');
            if ($state !== '') {
                $byState[$state] = (int) ($byState[$state] ?? 0) + 1;
            }
            if ($track !== '') {
                $byTrack[$track] = (int) ($byTrack[$track] ?? 0) + 1;
            }
        }

        return [
            'total' => $total,
            'loaded' => count($items),
            'byState' => $byState,
            'byTrack' => $byTrack,
        ];
    }
}
