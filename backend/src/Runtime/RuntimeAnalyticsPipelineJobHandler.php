<?php

namespace App\Runtime;

use App\Entity\RuntimeAsyncJob;

class RuntimeAnalyticsPipelineJobHandler implements RuntimeJobHandlerInterface
{
    public function __construct(
        private readonly RuntimeAnalyticsPipelineService $pipelines,
    ) {
    }

    public function supports(string $jobType): bool
    {
        return $jobType === 'analytics.pipeline.run';
    }

    public function handle(RuntimeAsyncJob $job): array
    {
        $payload = $job->getPayload();
        $screenId = trim((string) ($payload['screenId'] ?? $job->getScreenId()));
        if ($screenId === '') {
            throw new RuntimeHttpException('ANALYTICS_PIPELINE_JOB_SCREEN_REQUIRED', 'Job de pipeline analytics sem screenId.', 422);
        }

        return $this->pipelines->run($screenId, $payload, $job->getTenantId());
    }
}
