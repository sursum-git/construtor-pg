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

    public function publish(string $screenId, string $pipelineId, string $executionId, bool $strictCompatibility = false): array
    {
        return $this->pipelines->publish($screenId, [
            'pipelineId' => $pipelineId,
            'executionId' => $executionId,
            'strictCompatibility' => $strictCompatibility,
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

    public function impact(string $screenId, string $pipelineId): array
    {
        $screen = $this->screens->findPublishedByScreenId($screenId);
        $definition = $screen?->getDefinition() ?? [];
        $analytics = is_array($definition['analytics'] ?? null) ? $definition['analytics'] : [];
        $pipelines = is_array($analytics['semanticPipelines'] ?? null) ? $analytics['semanticPipelines'] : [];
        $datasets = is_array($analytics['datasets'] ?? null) ? $analytics['datasets'] : [];
        $views = is_array($analytics['views'] ?? null) ? $analytics['views'] : [];

        $selectedPipeline = null;
        foreach ($pipelines as $pipeline) {
            if (is_array($pipeline) && (string) ($pipeline['id'] ?? '') === $pipelineId) {
                $selectedPipeline = $pipeline;
                break;
            }
        }

        $publishedDatasetId = trim((string) ($selectedPipeline['publishConfig']['publishedDatasetId'] ?? $selectedPipeline['publishedDatasetId'] ?? ''));
        $consumingDatasets = [];
        $consumingDatasetIds = [];
        foreach ($datasets as $dataset) {
            if (!is_array($dataset)) {
                continue;
            }
            $source = is_array($dataset['source'] ?? null) ? $dataset['source'] : [];
            if (($source['type'] ?? '') !== 'pipeline_published') {
                continue;
            }
            $matchesPipeline = (string) ($source['pipelineId'] ?? '') === $pipelineId;
            $matchesPublishedDataset = $publishedDatasetId !== '' && (string) ($source['publishedDatasetId'] ?? '') === $publishedDatasetId;
            if (!$matchesPipeline && !$matchesPublishedDataset) {
                continue;
            }
            $consumingDatasets[] = [
                'datasetId' => (string) ($dataset['id'] ?? ''),
                'title' => (string) ($dataset['title'] ?? $dataset['id'] ?? ''),
                'publishedDatasetId' => (string) ($source['publishedDatasetId'] ?? ''),
            ];
            $consumingDatasetIds[] = (string) ($dataset['id'] ?? '');
        }

        $affectedViews = [];
        foreach ($views as $view) {
            if (!is_array($view)) {
                continue;
            }
            $datasetId = (string) ($view['datasetId'] ?? '');
            if ($datasetId === '' || !in_array($datasetId, $consumingDatasetIds, true)) {
                continue;
            }
            $affectedViews[] = [
                'viewId' => (string) ($view['id'] ?? ''),
                'title' => (string) ($view['title'] ?? $view['id'] ?? ''),
                'type' => (string) ($view['type'] ?? ''),
                'datasetId' => $datasetId,
            ];
        }

        $affectedReports = [];
        foreach ($this->screens->findBy(['pageType' => 'report', 'status' => 'published'], ['screenId' => 'ASC']) as $reportScreen) {
            $reportDefinition = $reportScreen->getDefinition();
            $report = is_array($reportDefinition['report'] ?? null) ? $reportDefinition['report'] : [];
            $source = is_array($report['source'] ?? null) ? $report['source'] : [];
            if (($source['type'] ?? '') !== 'analytic') {
                continue;
            }
            if ((string) ($source['analyticsScreenId'] ?? '') !== $screenId) {
                continue;
            }
            if (!in_array((string) ($source['analyticsDatasetId'] ?? ''), $consumingDatasetIds, true)) {
                continue;
            }
            $affectedReports[] = [
                'screenId' => $reportScreen->getScreenId(),
                'reportId' => (string) ($report['id'] ?? $reportScreen->getScreenId()),
                'title' => (string) ($report['title'] ?? $reportScreen->getScreenId()),
                'datasetId' => (string) ($source['analyticsDatasetId'] ?? ''),
            ];
        }

        return [
            'screenId' => $screenId,
            'pipelineId' => $pipelineId,
            'publishedDatasetId' => $publishedDatasetId,
            'consumingDatasets' => $consumingDatasets,
            'affectedViews' => $affectedViews,
            'affectedReports' => $affectedReports,
            'summary' => [
                'datasets' => count($consumingDatasets),
                'views' => count($affectedViews),
                'reports' => count($affectedReports),
            ],
        ];
    }

    public function rollback(string $screenId, string $pipelineId, int $versionNo): array
    {
        return $this->pipelines->rollback($screenId, [
            'pipelineId' => $pipelineId,
            'versionNo' => $versionNo,
        ]);
    }
}
