<?php

namespace App\Runtime;

class RuntimeRegulatedDocumentEndpointHandler
{
    public function __construct(
        private readonly RuntimeRegulatedDocumentService $documents,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function handle(string $screenId, string $endpointId, array $config, array $payload): array
    {
        $merged = $this->mergeConfigPayload($config, $payload);

        return match ($endpointId) {
            'regulatedDocuments.schema' => $this->documents->schema($screenId),
            'regulatedDocuments.prepare' => $this->documents->prepare($screenId, $merged),
            'regulatedDocuments.render' => $this->documents->render($screenId, $merged),
            'regulatedDocuments.issue' => $this->documents->issue($screenId, $merged),
            'regulatedDocuments.verify' => $this->documents->verify($screenId, $merged),
            'regulatedDocuments.artifact' => $this->documents->artifact($screenId, $merged),
            default => throw new RuntimeHttpException('REGULATED_DOCUMENT_ENDPOINT_NOT_SUPPORTED', 'Endpoint de documento regulado nao suportado.', 404, [
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
