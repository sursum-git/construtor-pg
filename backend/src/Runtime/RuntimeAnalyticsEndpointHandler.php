<?php

namespace App\Runtime;

class RuntimeAnalyticsEndpointHandler
{
    public function __construct(
        private readonly RuntimeAnalyticsService $analytics,
        private readonly RuntimeAnalyticsPipelineService $pipelines,
        private readonly RuntimeAsyncJobService $asyncJobs,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function handle(string $screenId, string $endpointId, array $config, array $payload): array
    {
        $operation = (string) ($config['operation'] ?? $endpointId);

        return match ($operation) {
            'analytics.schema' => $this->analytics->schema($screenId),
            'analytics.query.run' => $this->analytics->run($screenId, $this->mergeConfigPayload($config, $payload)),
            'analytics.cache.status' => $this->analytics->cacheStatus($screenId, $this->mergeConfigPayload($config, $payload)),
            'analytics.materialize' => $this->materialize($screenId, $endpointId, $config, $payload),
            'analytics.pipeline.schema' => $this->pipelines->schema($screenId),
            'analytics.pipeline.preview' => $this->pipelines->preview($screenId, $this->mergeConfigPayload($config, $payload)),
            'analytics.pipeline.run' => $this->runPipeline($screenId, $endpointId, $config, $payload),
            'analytics.pipeline.publish' => $this->pipelines->publish($screenId, $this->mergeConfigPayload($config, $payload)),
            'analytics.pipeline.status' => $this->pipelines->status($screenId, $this->mergeConfigPayload($config, $payload)),
            'analytics.pipeline.logs' => $this->pipelines->logs($screenId, $this->mergeConfigPayload($config, $payload)),
            'analytics.pipeline.versions' => $this->pipelines->versions($screenId, $this->mergeConfigPayload($config, $payload)),
            'analytics.pipeline.rollback' => $this->pipelines->rollback($screenId, $this->mergeConfigPayload($config, $payload)),
            default => throw new RuntimeHttpException('ANALYTICS_ENDPOINT_NOT_FOUND', 'Endpoint analytics nao encontrado.', 404, [
                'screenId' => $screenId,
                'endpointId' => $endpointId,
                'operation' => $operation,
            ]),
        };
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function materialize(string $screenId, string $endpointId, array $config, array $payload): array
    {
        $jobPayload = $this->mergeConfigPayload($config, $payload);
        $jobPayload['screenId'] = $screenId;

        if (($payload['sync'] ?? false) === true) {
            return $this->analytics->materialize($screenId, $jobPayload);
        }

        $reference = $this->asyncJobs->scheduleWithReference('analytics.materialize', $jobPayload, [
            'screenId' => $screenId,
            'programId' => $config['programId'] ?? null,
            'entityCode' => $config['entityCode'] ?? null,
            'actionId' => $endpointId,
            'message' => 'Atualizacao de cache BI agendada.',
        ]);

        return [
            'ok' => true,
            'queued' => true,
            'runtimePendingRef' => $reference,
            'message' => 'Atualizacao de cache BI agendada.',
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function runPipeline(string $screenId, string $endpointId, array $config, array $payload): array
    {
        $jobPayload = $this->mergeConfigPayload($config, $payload);
        $jobPayload['screenId'] = $screenId;

        if (($payload['sync'] ?? false) === true) {
            return $this->pipelines->run($screenId, $jobPayload);
        }

        $reference = $this->asyncJobs->scheduleWithReference('analytics.pipeline.run', $jobPayload, [
            'screenId' => $screenId,
            'programId' => $config['programId'] ?? null,
            'entityCode' => $config['entityCode'] ?? null,
            'actionId' => $endpointId,
            'message' => 'Execucao do pipeline BI agendada.',
        ]);

        return [
            'ok' => true,
            'queued' => true,
            'runtimePendingRef' => $reference,
            'message' => 'Execucao do pipeline BI agendada.',
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function mergeConfigPayload(array $config, array $payload): array
    {
        foreach (['datasetId', 'viewId', 'executionMode', 'pipelineId', 'versionNo', 'executionId', 'stepId'] as $key) {
            if (!array_key_exists($key, $payload) && array_key_exists($key, $config)) {
                $payload[$key] = $config[$key];
            }
        }

        return $payload;
    }
}
