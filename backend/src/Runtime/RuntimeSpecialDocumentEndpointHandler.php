<?php

namespace App\Runtime;

class RuntimeSpecialDocumentEndpointHandler
{
    public function __construct(
        private readonly RuntimeSpecialDocumentService $documents,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function handle(string $screenId, string $endpointId, array $config, array $payload): array
    {
        return match ($endpointId) {
            'specialDocuments.schema' => $this->documents->schema($screenId),
            'specialDocuments.render' => $this->documents->render($screenId, $this->mergeConfigPayload($config, $payload)),
            'specialDocuments.export' => $this->documents->export($screenId, $this->mergeConfigPayload($config, $payload)),
            default => throw new RuntimeHttpException('SPECIAL_DOCUMENT_ENDPOINT_NOT_SUPPORTED', 'Endpoint de documento especial nao suportado.', 404, [
                'screenId' => $screenId,
                'endpointId' => $endpointId,
            ]),
        };
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function mergeConfigPayload(array $config, array $payload): array
    {
        $merged = $payload;
        foreach (['entityCode', 'programId', 'actionId', 'operation'] as $key) {
            if (!isset($merged[$key]) && isset($config[$key])) {
                $merged[$key] = $config[$key];
            }
        }

        return $merged;
    }
}
