<?php

namespace App\Runtime;

use App\Repository\ScreenDefinitionRepository;

class RuntimeAnalyticsPipelineAdminService
{
    public function __construct(
        private readonly ScreenDefinitionRepository $screens,
        private readonly RuntimeAnalyticsPipelineService $pipelines,
        private readonly RuntimeAnalyticsPipelineStore $store,
        private readonly PermissionResolver $permissions,
    ) {
    }

    public function bootstrap(): array
    {
        return [
            'enabled' => $this->store->storageReady(),
            'pipelines' => $this->listRows()['items'],
        ];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function listRows(): array
    {
        $items = [];
        foreach ($this->screens->findBy(['pageType' => 'analytics', 'status' => 'published'], ['screenId' => 'ASC']) as $screen) {
            $definition = $screen->getDefinition();
            $pipelines = is_array($definition['analytics']['semanticPipelines'] ?? null) ? $definition['analytics']['semanticPipelines'] : [];
            foreach ($pipelines as $pipeline) {
                if (!is_array($pipeline) || empty($pipeline['id'])) {
                    continue;
                }
                $status = $this->pipelines->status($screen->getScreenId(), ['pipelineId' => $pipeline['id']]);
                $versions = $this->pipelines->versions($screen->getScreenId(), ['pipelineId' => $pipeline['id']]);
                $items[] = [
                    'screenId' => $screen->getScreenId(),
                    'pipelineId' => (string) $pipeline['id'],
                    'title' => (string) ($pipeline['title'] ?? $pipeline['id']),
                    'enabled' => ($pipeline['enabled'] ?? true) !== false,
                    'publishedDatasetId' => (string) ($pipeline['publishConfig']['publishedDatasetId'] ?? ''),
                    'latestExecution' => $status['latestExecution'] ?? null,
                    'activeVersion' => $versions['activeVersion'] ?? null,
                ];
            }
        }

        return [
            'items' => $items,
            'total' => count($items),
        ];
    }

    public function run(string $screenId, string $pipelineId, bool $sync = true): array
    {
        if ($sync) {
            return $this->pipelines->run($screenId, ['pipelineId' => $pipelineId]);
        }

        return [
            'ok' => true,
            'queued' => true,
            'message' => 'Execucao assincrona deve ser disparada pelo endpoint runtime.',
        ];
    }

    public function publish(string $screenId, string $pipelineId, string $executionId): array
    {
        return $this->pipelines->publish($screenId, [
            'pipelineId' => $pipelineId,
            'executionId' => $executionId,
        ]);
    }

    public function status(string $screenId, string $pipelineId): array
    {
        return $this->pipelines->status($screenId, [
            'pipelineId' => $pipelineId,
        ]);
    }

    public function logs(string $screenId, string $pipelineId): array
    {
        return $this->pipelines->logs($screenId, [
            'pipelineId' => $pipelineId,
        ]);
    }

    public function versions(string $screenId, string $pipelineId): array
    {
        return $this->pipelines->versions($screenId, [
            'pipelineId' => $pipelineId,
        ]);
    }

    public function rollback(string $screenId, string $pipelineId, int $versionNo): array
    {
        return $this->pipelines->rollback($screenId, [
            'pipelineId' => $pipelineId,
            'versionNo' => $versionNo,
        ]);
    }
}
