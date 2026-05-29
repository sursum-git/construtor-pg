<?php

namespace App\Runtime;

class RuntimeReportEndpointHandler
{
    public function __construct(
        private readonly RuntimeReportService $reports,
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
            'reports.schema' => $this->reports->schema($screenId),
            'reports.run' => $this->reports->run($screenId, $this->mergeConfigPayload($config, $payload)),
            'reports.export' => $this->reports->export($screenId, $this->mergeConfigPayload($config, $payload)),
            default => throw new RuntimeHttpException('REPORT_ENDPOINT_NOT_SUPPORTED', 'Endpoint de relatorio nao suportado.', 404, [
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
