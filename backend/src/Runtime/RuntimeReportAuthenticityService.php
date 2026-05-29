<?php

namespace App\Runtime;

class RuntimeReportAuthenticityService
{
    private const HASH_PATTERN = '/^sha256:[a-f0-9]{64}$/';

    public function __construct(
        private readonly RuntimeAnalyticsAuditStore $auditStore,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(string $hash): array
    {
        $hash = strtolower(trim($hash));
        if ($hash === '') {
            throw new RuntimeHttpException('REPORT_AUTHENTICITY_HASH_REQUIRED', 'Informe o hash de autenticidade do relatorio.', 422);
        }
        if (preg_match(self::HASH_PATTERN, $hash) !== 1) {
            throw new RuntimeHttpException('REPORT_AUTHENTICITY_HASH_INVALID', 'Hash de autenticidade invalido.', 422, [
                'hash' => $hash,
            ]);
        }

        if (!$this->auditStore->isEnabled()) {
            return [
                'enabled' => false,
                'found' => false,
                'hash' => $hash,
                'message' => 'A conferencia publica depende do banco separado de auditoria habilitado no ambiente.',
            ];
        }

        $entry = $this->auditStore->findLatestByMetadataValue('report', ['authenticity', 'hash'], $hash);
        if (!$entry) {
            return [
                'enabled' => true,
                'found' => false,
                'hash' => $hash,
                'message' => 'Nenhum relatorio autenticado foi encontrado para este hash.',
            ];
        }

        $metadata = is_array($entry['metadata'] ?? null) ? $entry['metadata'] : [];
        $authenticity = is_array($metadata['authenticity'] ?? null) ? $metadata['authenticity'] : [];
        $artifact = is_array($metadata['artifact'] ?? null) ? $metadata['artifact'] : [];

        return [
            'enabled' => true,
            'found' => true,
            'hash' => $hash,
            'message' => 'Relatorio localizado na trilha de auditoria.',
            'report' => [
                'screenId' => (string) ($entry['screenId'] ?? ''),
                'reportId' => (string) ($metadata['reportId'] ?? $entry['datasetId'] ?? ''),
                'title' => (string) ($metadata['reportTitle'] ?? ''),
                'sourceType' => (string) ($metadata['sourceType'] ?? $entry['executionMode'] ?? ''),
                'format' => (string) ($entry['viewId'] ?? ''),
                'rowCount' => (int) ($entry['rowCount'] ?? 0),
                'totalCount' => (int) ($entry['totalCount'] ?? 0),
                'generatedAt' => (string) ($entry['consultedAt'] ?? ''),
                'tenantId' => (string) ($entry['tenantId'] ?? ''),
            ],
            'authenticity' => [
                'algorithm' => (string) ($authenticity['algorithm'] ?? 'sha256'),
                'hash' => (string) ($authenticity['hash'] ?? $hash),
                'recorded' => ($authenticity['recorded'] ?? true) !== false,
                'footerLabel' => (string) ($authenticity['footerLabel'] ?? 'Codigo de autenticidade'),
                'verificationPath' => (string) ($authenticity['verificationPath'] ?? ''),
                'storage' => is_array($authenticity['storage'] ?? null) ? $authenticity['storage'] : [],
            ],
            'artifact' => [
                'stored' => ($artifact['stored'] ?? false) === true,
                'format' => (string) ($artifact['format'] ?? ''),
                'fileName' => (string) ($artifact['fileName'] ?? ''),
                'contentType' => (string) ($artifact['contentType'] ?? ''),
            ],
        ];
    }
}
