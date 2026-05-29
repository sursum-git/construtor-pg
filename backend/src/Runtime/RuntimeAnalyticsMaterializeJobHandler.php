<?php

namespace App\Runtime;

use App\Entity\RuntimeAsyncJob;

class RuntimeAnalyticsMaterializeJobHandler implements RuntimeJobHandlerInterface
{
    public function __construct(
        private readonly RuntimeAnalyticsService $analytics,
    ) {
    }

    public function supports(string $jobType): bool
    {
        return $jobType === 'analytics.materialize';
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(RuntimeAsyncJob $job): array
    {
        $payload = $job->getPayload();
        $screenId = trim((string) ($payload['screenId'] ?? $job->getScreenId()));
        if ($screenId === '') {
            throw new RuntimeHttpException('ANALYTICS_JOB_SCREEN_REQUIRED', 'Job analytics sem screenId.', 422);
        }

        return $this->analytics->materialize($screenId, $payload, $job->getTenantId());
    }
}
