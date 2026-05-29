<?php

namespace App\Runtime;

class RuntimeRegulatedDocumentAuthenticityService
{
    private const HASH_PATTERN = '/^sha256:[a-f0-9]{64}$/';

    public function __construct(
        private readonly RuntimeRegulatedDocumentStore $store,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(string $hash): array
    {
        $hash = strtolower(trim($hash));
        if ($hash === '') {
            throw new RuntimeHttpException('REGULATED_DOCUMENT_HASH_REQUIRED', 'Informe o hash do documento regulado.', 422);
        }
        if (preg_match(self::HASH_PATTERN, $hash) !== 1) {
            throw new RuntimeHttpException('REGULATED_DOCUMENT_HASH_INVALID', 'Hash do documento regulado invalido.', 422, [
                'hash' => $hash,
            ]);
        }

        if (!$this->store->isEnabled()) {
            return [
                'enabled' => false,
                'found' => false,
                'hash' => $hash,
                'message' => 'A conferencia depende do banco separado do modulo regulado habilitado no ambiente.',
            ];
        }

        $entry = $this->store->findByHash($hash);
        if (!$entry) {
            return [
                'enabled' => true,
                'found' => false,
                'hash' => $hash,
                'message' => 'Nenhum documento regulado foi encontrado para este hash.',
            ];
        }

        return [
            'enabled' => true,
            'found' => true,
            'hash' => $hash,
            'message' => 'Documento regulado localizado.',
            'document' => [
                'issueId' => (string) ($entry['issueId'] ?? ''),
                'screenId' => (string) ($entry['screenId'] ?? ''),
                'documentId' => (string) ($entry['documentId'] ?? ''),
                'track' => (string) ($entry['track'] ?? ''),
                'documentType' => (string) ($entry['documentType'] ?? ''),
                'complianceProfile' => (string) ($entry['complianceProfile'] ?? ''),
                'state' => (string) ($entry['state'] ?? ''),
                'format' => (string) ($entry['format'] ?? ''),
                'generatedAt' => (string) ($entry['issuedAt'] ?? $entry['updatedAt'] ?? ''),
                'tenantId' => (string) ($entry['tenantId'] ?? ''),
            ],
            'verification' => is_array($entry['verification'] ?? null) ? $entry['verification'] : [],
            'artifact' => [
                'stored' => is_array($entry['artifact'] ?? null) && !empty($entry['artifact']['contentBase64']),
                'format' => (string) (($entry['artifact']['format'] ?? '')),
                'fileName' => (string) (($entry['artifact']['fileName'] ?? '')),
                'contentType' => (string) (($entry['artifact']['contentType'] ?? '')),
            ],
        ];
    }
}
