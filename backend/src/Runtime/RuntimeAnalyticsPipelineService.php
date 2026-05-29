<?php

namespace App\Runtime;

use App\Repository\ScreenDefinitionRepository;
use Doctrine\DBAL\Connection;

class RuntimeAnalyticsPipelineService
{
    private const STEP_TYPES = ['source', 'select', 'filter', 'join', 'derive', 'group', 'sort', 'limit', 'publish'];
    private const AGGREGATES = ['count', 'sum', 'avg', 'min', 'max', 'distinct_count'];

    public function __construct(
        private readonly ScreenDefinitionRepository $screens,
        private readonly RuntimeEntityDefinitionResolver $entities,
        private readonly Connection $connection,
        private readonly PermissionResolver $permissions,
        private readonly StructuralIntegrityService $integrity,
        private readonly ProgramCustomizationResolver $customizations,
        private readonly RuntimeAnalyticsPipelineStore $store,
        private readonly ?RuntimeAnalyticsAuditStore $auditStore = null,
    ) {
    }

    public function schema(string $screenId): array
    {
        $definition = $this->loadDefinition($screenId);
        $this->assertNoPipelineCycles($definition);
        $pipelines = $this->pipelines($definition);
        $summaries = [];
        foreach ($pipelines as $pipeline) {
            $latest = $this->store->listExecutions($this->tenantId(), $screenId, (string) $pipeline['id'], 1);
            $publishedDatasetId = $this->publishedDatasetId($pipeline);
            $active = $publishedDatasetId !== '' ? $this->store->activePublishedDatasetVersion($this->tenantId(), $screenId, $publishedDatasetId) : null;
            $summaries[] = [
                'pipelineId' => (string) $pipeline['id'],
                'title' => (string) ($pipeline['title'] ?? $pipeline['id']),
                'enabled' => ($pipeline['enabled'] ?? true) !== false,
                'publishedDatasetId' => $publishedDatasetId,
                'latestExecution' => $latest[0] ?? null,
                'activeVersion' => $active ? [
                    'versionNo' => $active['versionNo'] ?? 0,
                    'status' => $active['status'] ?? '',
                    'publishedAt' => $active['publishedAt'] ?? null,
                ] : null,
            ];
        }

        return [
            'screenId' => $screenId,
            'pipelines' => $pipelines,
            'ingestionPipelines' => is_array($definition['analytics']['ingestionPipelines'] ?? null) ? $definition['analytics']['ingestionPipelines'] : [],
            'runtime' => [
                'pipelines' => [
                    'storageReady' => $this->store->storageReady(),
                    'summaries' => $summaries,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function preview(string $screenId, array $payload, ?string $tenantId = null): array
    {
        $tenantId = $tenantId ?: $this->tenantId();
        [$definition, $pipeline, $pipelineVersion] = $this->resolvePipelineVersion($screenId, $payload, $tenantId);
        $mode = 'preview';
        $execution = $this->store->createExecution([
            'tenantId' => $tenantId,
            'screenId' => $screenId,
            'pipelineId' => $pipeline['id'],
            'pipelineVersionId' => $pipelineVersion['id'] ?? 0,
            'mode' => $mode,
            'status' => 'running',
            'startedAt' => new \DateTimeImmutable(),
            'metadata' => [
                'auditContext' => 'analytics_pipeline',
            ],
        ]);

        try {
            $result = $this->executePipeline($definition, $pipeline, $payload, $tenantId, (string) ($execution['executionId'] ?? ''), true);
            $stepId = trim((string) ($payload['stepId'] ?? ''));
            $working = $stepId !== '' ? ($result['steps'][$stepId] ?? $result['final']) : $result['final'];
            $this->store->updateExecution((string) $execution['executionId'], [
                'status' => 'succeeded',
                'workingDataset' => $working,
                'rowCount' => count((array) ($working['rows'] ?? [])),
                'metadata' => [
                    'pipelineId' => $pipeline['id'],
                    'previewStepId' => $stepId !== '' ? $stepId : null,
                ],
                'finishedAt' => new \DateTimeImmutable(),
            ]);
            $this->recordAudit($definition, $pipeline, [
                'pipelineId' => $pipeline['id'],
                'pipelineExecutionId' => $execution['executionId'],
                'publishedDatasetId' => $this->publishedDatasetId($pipeline),
                'previewStepId' => $stepId !== '' ? $stepId : null,
            ], $working, 'working_preview', $tenantId);

            return [
                'ok' => true,
                'pipelineId' => $pipeline['id'],
                'executionId' => $execution['executionId'],
                'stepId' => $stepId !== '' ? $stepId : null,
                'workingDataset' => $working,
            ];
        } catch (\Throwable $error) {
            $this->store->updateExecution((string) $execution['executionId'], [
                'status' => 'failed',
                'errorMessage' => $error->getMessage(),
                'finishedAt' => new \DateTimeImmutable(),
            ]);
            throw $error;
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function run(string $screenId, array $payload, ?string $tenantId = null): array
    {
        $tenantId = $tenantId ?: $this->tenantId();
        [$definition, $pipeline, $pipelineVersion] = $this->resolvePipelineVersion($screenId, $payload, $tenantId);
        $execution = $this->store->createExecution([
            'tenantId' => $tenantId,
            'screenId' => $screenId,
            'pipelineId' => $pipeline['id'],
            'pipelineVersionId' => $pipelineVersion['id'] ?? 0,
            'mode' => 'run',
            'status' => 'running',
            'startedAt' => new \DateTimeImmutable(),
        ]);

        try {
            $result = $this->executePipeline($definition, $pipeline, $payload, $tenantId, (string) ($execution['executionId'] ?? ''), false);
            $final = $result['final'];
            $this->store->updateExecution((string) $execution['executionId'], [
                'status' => 'succeeded',
                'workingDataset' => $final,
                'rowCount' => count((array) ($final['rows'] ?? [])),
                'metadata' => [
                    'pipelineId' => $pipeline['id'],
                    'publishedDatasetId' => $this->publishedDatasetId($pipeline),
                ],
                'finishedAt' => new \DateTimeImmutable(),
            ]);
            $this->recordAudit($definition, $pipeline, [
                'pipelineId' => $pipeline['id'],
                'pipelineExecutionId' => $execution['executionId'],
                'publishedDatasetId' => $this->publishedDatasetId($pipeline),
            ], $final, 'pipeline_run', $tenantId);

            return [
                'ok' => true,
                'pipelineId' => $pipeline['id'],
                'executionId' => $execution['executionId'],
                'workingDataset' => $final,
            ];
        } catch (\Throwable $error) {
            $this->store->updateExecution((string) $execution['executionId'], [
                'status' => 'failed',
                'errorMessage' => $error->getMessage(),
                'finishedAt' => new \DateTimeImmutable(),
            ]);
            throw $error;
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function publish(string $screenId, array $payload, ?string $tenantId = null): array
    {
        $tenantId = $tenantId ?: $this->tenantId();
        [$definition, $pipeline] = $this->resolvePipeline($screenId, $payload);
        $executionId = trim((string) ($payload['executionId'] ?? ''));
        if ($executionId === '') {
            throw new RuntimeHttpException('ANALYTICS_PIPELINE_EXECUTION_REQUIRED', 'Informe a execucao a publicar.', 422);
        }
        $execution = $this->store->findExecution($executionId);
        if ($execution === null || (string) ($execution['pipelineId'] ?? '') !== (string) $pipeline['id']) {
            throw new RuntimeHttpException('ANALYTICS_PIPELINE_EXECUTION_NOT_FOUND', 'Execucao do pipeline nao encontrada.', 404, [
                'executionId' => $executionId,
                'pipelineId' => $pipeline['id'] ?? null,
            ]);
        }
        if ((string) ($execution['status'] ?? '') !== 'succeeded') {
            throw new RuntimeHttpException('ANALYTICS_PIPELINE_EXECUTION_INVALID', 'A execucao precisa estar concluida com sucesso para publicar.', 422, [
                'executionId' => $executionId,
                'status' => $execution['status'] ?? null,
            ]);
        }
        $working = is_array($execution['workingDataset'] ?? null) ? $execution['workingDataset'] : [];
        $publishedDatasetId = $this->publishedDatasetId($pipeline);
        if ($publishedDatasetId === '') {
            throw new RuntimeHttpException('ANALYTICS_PIPELINE_PUBLISHED_DATASET_REQUIRED', 'Pipeline sem publishedDatasetId configurado.', 422, [
                'pipelineId' => $pipeline['id'] ?? null,
            ]);
        }
        $version = $this->store->publishDatasetVersion([
            'tenantId' => $tenantId,
            'screenId' => $screenId,
            'pipelineId' => $pipeline['id'],
            'publishedDatasetId' => $publishedDatasetId,
            'executionId' => $executionId,
            'schema' => [
                'columns' => $working['columns'] ?? [],
            ],
            'data' => $working,
            'metadata' => [
                'pipelineId' => $pipeline['id'],
                'title' => $pipeline['title'] ?? $pipeline['id'],
            ],
        ]);
        $this->recordAudit($definition, $pipeline, [
            'pipelineId' => $pipeline['id'],
            'pipelineExecutionId' => $executionId,
            'publishedDatasetId' => $publishedDatasetId,
            'publishedDatasetVersionId' => $version['versionNo'] ?? null,
        ], $working, 'pipeline_publish', $tenantId);

        return [
            'ok' => true,
            'pipelineId' => $pipeline['id'],
            'executionId' => $executionId,
            'publishedDatasetId' => $publishedDatasetId,
            'publishedVersion' => $version,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function status(string $screenId, array $payload, ?string $tenantId = null): array
    {
        $tenantId = $tenantId ?: $this->tenantId();
        [, $pipeline] = $this->resolvePipeline($screenId, $payload);
        $executions = $this->store->listExecutions($tenantId, $screenId, (string) $pipeline['id'], 20);

        return [
            'pipelineId' => $pipeline['id'],
            'latestExecution' => $executions[0] ?? null,
            'executions' => $executions,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function logs(string $screenId, array $payload, ?string $tenantId = null): array
    {
        $tenantId = $tenantId ?: $this->tenantId();
        [, $pipeline] = $this->resolvePipeline($screenId, $payload);
        $executionId = trim((string) ($payload['executionId'] ?? ''));
        if ($executionId !== '') {
            return [
                'pipelineId' => $pipeline['id'],
                'executionId' => $executionId,
                'steps' => $this->store->listExecutionSteps($executionId),
            ];
        }
        $executions = $this->store->listExecutions($tenantId, $screenId, (string) $pipeline['id'], 20);
        $logs = [];
        foreach ($executions as $execution) {
            $logs[] = [
                'execution' => $execution,
                'steps' => $this->store->listExecutionSteps((string) ($execution['executionId'] ?? '')),
            ];
        }

        return [
            'pipelineId' => $pipeline['id'],
            'logs' => $logs,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function versions(string $screenId, array $payload, ?string $tenantId = null): array
    {
        $tenantId = $tenantId ?: $this->tenantId();
        [, $pipeline] = $this->resolvePipeline($screenId, $payload);
        $datasetId = $this->publishedDatasetId($pipeline);

        return [
            'pipelineId' => $pipeline['id'],
            'publishedDatasetId' => $datasetId,
            'activeVersion' => $datasetId !== '' ? $this->store->activePublishedDatasetVersion($tenantId, $screenId, $datasetId) : null,
            'versions' => $datasetId !== '' ? $this->store->listPublishedDatasetVersions($tenantId, $screenId, $datasetId) : [],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function rollback(string $screenId, array $payload, ?string $tenantId = null): array
    {
        $tenantId = $tenantId ?: $this->tenantId();
        [$definition, $pipeline] = $this->resolvePipeline($screenId, $payload);
        $versionNo = max(1, (int) ($payload['versionNo'] ?? 0));
        $datasetId = $this->publishedDatasetId($pipeline);
        if ($datasetId === '') {
            throw new RuntimeHttpException('ANALYTICS_PIPELINE_PUBLISHED_DATASET_REQUIRED', 'Pipeline sem dataset publicado configurado.', 422);
        }
        $version = $this->store->rollbackPublishedDatasetVersion($tenantId, $screenId, $datasetId, $versionNo);
        if ($version === null) {
            throw new RuntimeHttpException('ANALYTICS_PIPELINE_VERSION_NOT_FOUND', 'Versao publicada nao encontrada.', 404, [
                'datasetId' => $datasetId,
                'versionNo' => $versionNo,
            ]);
        }
        $data = is_array($version['data'] ?? null) ? $version['data'] : [];
        $this->recordAudit($definition, $pipeline, [
            'pipelineId' => $pipeline['id'],
            'publishedDatasetId' => $datasetId,
            'publishedDatasetVersionId' => $versionNo,
        ], $data, 'pipeline_rollback', $tenantId);

        return [
            'ok' => true,
            'pipelineId' => $pipeline['id'],
            'publishedDatasetId' => $datasetId,
            'activeVersion' => $version,
        ];
    }

    /**
     * @param array<string, mixed> $dataset
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function consumePublishedDataset(string $screenId, array $dataset, array $payload, ?string $tenantId = null): array
    {
        $tenantId = $tenantId ?: $this->tenantId();
        $publishedDatasetId = trim((string) ($dataset['source']['publishedDatasetId'] ?? $dataset['publishedDatasetId'] ?? $dataset['id'] ?? ''));
        if ($publishedDatasetId === '') {
            throw new RuntimeHttpException('ANALYTICS_PUBLISHED_DATASET_ID_REQUIRED', 'Dataset analytics publicado sem identificador.', 422);
        }
        $active = $this->store->activePublishedDatasetVersion($tenantId, $screenId, $publishedDatasetId);
        if ($active === null) {
            throw new RuntimeHttpException('ANALYTICS_PUBLISHED_DATASET_NOT_READY', 'Dataset publicado ainda nao possui versao ativa.', 422, [
                'screenId' => $screenId,
                'datasetId' => $publishedDatasetId,
            ]);
        }
        $data = is_array($active['data'] ?? null) ? $active['data'] : [];
        $rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
        $columns = is_array($data['columns'] ?? null) ? $data['columns'] : [];
        $filtered = $this->applyWorkingFilters($rows, $payload['parameters'] ?? null, $columns);
        $sorted = $this->applyWorkingSort($filtered, $payload['sort'] ?? null);
        $take = max(1, min(5000, (int) ($payload['take'] ?? count($sorted) ?: 1000)));
        $skip = max(0, (int) ($payload['skip'] ?? 0));
        $paged = array_slice($sorted, $skip, $take);

        return [
            'data' => $paged,
            'total' => count($sorted),
            'columns' => $columns,
            'datasetId' => (string) ($dataset['id'] ?? $publishedDatasetId),
            'generatedAt' => $active['publishedAt'] ?? (new \DateTimeImmutable())->format(DATE_ATOM),
            '_runtime' => [
                'analytics' => [
                    'screenId' => $screenId,
                    'datasetId' => (string) ($dataset['id'] ?? $publishedDatasetId),
                    'executionMode' => 'published',
                    'aggregated' => true,
                    'limit' => $take,
                    'skip' => $skip,
                    'pipelineId' => $active['pipelineId'] ?? (string) ($dataset['source']['pipelineId'] ?? ''),
                    'publishedDatasetId' => $publishedDatasetId,
                    'publishedDatasetVersionId' => $active['versionNo'] ?? null,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $pipeline
     * @param array<string, mixed> $payload
     * @return array{final: array<string, mixed>, steps: array<string, array<string, mixed>>}
     */
    private function executePipeline(array $definition, array $pipeline, array $payload, string $tenantId, string $executionId, bool $previewMode): array
    {
        $steps = $this->normalizeSteps($pipeline['steps'] ?? []);
        $working = $this->resolveInitialWorkingDataset($definition, $pipeline, $tenantId, $payload);
        $results = [];

        foreach ($steps as $index => $step) {
            $startedAt = new \DateTimeImmutable();
            $working = $this->applyStep($definition, $working, $pipeline, $step, $tenantId, $payload);
            $results[(string) $step['id']] = $working;
            $this->store->appendStepExecution([
                'executionId' => $executionId,
                'stepId' => $step['id'],
                'stepType' => $step['type'],
                'position' => $index + 1,
                'status' => 'succeeded',
                'rowCount' => count((array) ($working['rows'] ?? [])),
                'outputColumns' => $working['columns'] ?? [],
                'metadata' => [
                    'previewMode' => $previewMode,
                ],
                'startedAt' => $startedAt,
                'finishedAt' => new \DateTimeImmutable(),
            ]);
        }

        return [
            'final' => $working,
            'steps' => $results,
        ];
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $pipeline
     * @param array<string, mixed> $payload
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function resolvePipeline(string $screenId, array $payload): array
    {
        $definition = $this->loadDefinition($screenId);
        $this->assertNoPipelineCycles($definition);
        $pipelineId = trim((string) ($payload['pipelineId'] ?? ''));
        foreach ($this->pipelines($definition) as $pipeline) {
            if ($pipelineId === '' || (string) ($pipeline['id'] ?? '') === $pipelineId) {
                return [$definition, $pipeline];
            }
        }

        throw new RuntimeHttpException('ANALYTICS_PIPELINE_NOT_FOUND', 'Pipeline semantico analytics nao encontrado.', 404, [
            'screenId' => $screenId,
            'pipelineId' => $pipelineId,
        ]);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    private function resolvePipelineVersion(string $screenId, array $payload, string $tenantId): array
    {
        [$definition, $pipeline] = $this->resolvePipeline($screenId, $payload);
        $version = $this->store->ensurePipelineVersion($tenantId, $screenId, (string) $pipeline['id'], $pipeline);

        return [$definition, $pipeline, $version];
    }

    /**
     * @param array<string, mixed> $definition
     * @return list<array<string, mixed>>
     */
    private function pipelines(array $definition): array
    {
        $items = is_array($definition['analytics']['semanticPipelines'] ?? null) ? $definition['analytics']['semanticPipelines'] : [];

        return array_values(array_filter(array_map(function (mixed $item): ?array {
            if (!is_array($item)) {
                return null;
            }
            $id = trim((string) ($item['id'] ?? ''));
            if ($id === '') {
                return null;
            }
            $item['id'] = $id;
            $item['enabled'] = ($item['enabled'] ?? true) !== false;
            $item['steps'] = is_array($item['steps'] ?? null) ? $item['steps'] : [];
            $item['publishConfig'] = is_array($item['publishConfig'] ?? null) ? $item['publishConfig'] : [];
            $item['publishConfig']['publishedDatasetId'] = trim((string) ($item['publishConfig']['publishedDatasetId'] ?? ($id . '_published')));
            $item['publishConfig']['title'] = trim((string) ($item['publishConfig']['title'] ?? ($item['title'] ?? $id))) ?: ($item['title'] ?? $id);

            return $item;
        }, $items)));
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $pipeline
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function resolveInitialWorkingDataset(array $definition, array $pipeline, string $tenantId, array $payload): array
    {
        $sourceEntityCode = trim((string) ($pipeline['sourceEntityCode'] ?? ''));
        $sourceDatasetId = trim((string) ($pipeline['sourceDatasetId'] ?? ''));
        $sourcePipelineId = trim((string) ($pipeline['sourcePipelineId'] ?? ''));
        if ($sourceEntityCode !== '') {
            return $this->loadEntityWorkingDataset($sourceEntityCode, $tenantId, $payload);
        }
        if ($sourceDatasetId !== '') {
            return $this->loadDatasetWorkingDataset($definition, $sourceDatasetId, $tenantId, $payload);
        }
        if ($sourcePipelineId !== '') {
            return $this->loadPipelinePublishedWorkingDataset($definition, $sourcePipelineId, $tenantId);
        }

        throw new RuntimeHttpException('ANALYTICS_PIPELINE_SOURCE_REQUIRED', 'Pipeline semantico precisa de fonte interna.', 422, [
            'pipelineId' => $pipeline['id'] ?? null,
        ]);
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $working
     * @param array<string, mixed> $pipeline
     * @param array<string, mixed> $step
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function applyStep(array $definition, array $working, array $pipeline, array $step, string $tenantId, array $payload): array
    {
        return match ((string) $step['type']) {
            'source' => $this->applySourceStep($definition, $working, $step, $tenantId, $payload),
            'select' => $this->applySelectStep($working, $step),
            'filter' => $this->applyFilterStep($working, $step),
            'join' => $this->applyJoinStep($definition, $working, $step, $tenantId, $payload),
            'derive' => $this->applyDeriveStep($working, $step),
            'group' => $this->applyGroupStep($working, $step),
            'sort' => $this->applySortStep($working, $step),
            'limit' => $this->applyLimitStep($working, $step),
            'publish' => $this->applyPublishStep($working, $pipeline, $step),
            default => throw new RuntimeHttpException('ANALYTICS_PIPELINE_STEP_INVALID', 'Tipo de etapa do pipeline nao suportado.', 422, [
                'stepId' => $step['id'] ?? null,
                'stepType' => $step['type'] ?? null,
            ]),
        };
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $working
     * @param array<string, mixed> $step
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function applySourceStep(array $definition, array $working, array $step, string $tenantId, array $payload): array
    {
        $entityCode = trim((string) ($step['entityCode'] ?? $step['sourceEntityCode'] ?? ''));
        $datasetId = trim((string) ($step['datasetId'] ?? $step['sourceDatasetId'] ?? ''));
        $pipelineId = trim((string) ($step['sourcePipelineId'] ?? ''));
        if ($entityCode !== '') {
            return $this->loadEntityWorkingDataset($entityCode, $tenantId, $payload);
        }
        if ($datasetId !== '') {
            return $this->loadDatasetWorkingDataset($definition, $datasetId, $tenantId, $payload);
        }
        if ($pipelineId !== '') {
            return $this->loadPipelinePublishedWorkingDataset($definition, $pipelineId, $tenantId);
        }

        return $working;
    }

    /**
     * @param array<string, mixed> $working
     * @param array<string, mixed> $step
     * @return array<string, mixed>
     */
    private function applySelectStep(array $working, array $step): array
    {
        $fields = is_array($step['fields'] ?? null) ? $step['fields'] : [];
        if (!$fields) {
            return $working;
        }
        $columns = [];
        $rows = [];
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $from = trim((string) ($field['from'] ?? $field['field'] ?? ''));
            $target = trim((string) ($field['as'] ?? $field['id'] ?? $from));
            if ($from === '' || $target === '') {
                continue;
            }
            $columns[] = [
                'field' => $target,
                'id' => $target,
                'title' => (string) ($field['label'] ?? $target),
                'label' => (string) ($field['label'] ?? $target),
                'type' => (string) ($field['type'] ?? $this->columnType($working, $from)),
                'role' => (string) ($field['role'] ?? 'field'),
                'format' => $field['format'] ?? null,
            ];
        }
        foreach ((array) ($working['rows'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $selected = [];
            foreach ($fields as $field) {
                if (!is_array($field)) {
                    continue;
                }
                $from = trim((string) ($field['from'] ?? $field['field'] ?? ''));
                $target = trim((string) ($field['as'] ?? $field['id'] ?? $from));
                if ($from === '' || $target === '') {
                    continue;
                }
                $selected[$target] = $row[$from] ?? null;
            }
            $rows[] = $selected;
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $working
     * @param array<string, mixed> $step
     * @return array<string, mixed>
     */
    private function applyFilterStep(array $working, array $step): array
    {
        $filter = is_array($step['filter'] ?? null) ? $step['filter'] : (is_array($step['filters'] ?? null) ? ['logic' => 'and', 'filters' => $step['filters']] : []);
        if (!$filter) {
            return $working;
        }
        $rows = array_values(array_filter((array) ($working['rows'] ?? []), function (mixed $row) use ($filter): bool {
            return is_array($row) ? $this->matchesWorkingFilter($row, $filter) : false;
        }));

        return [
            'columns' => $working['columns'] ?? [],
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $working
     * @param array<string, mixed> $step
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function applyJoinStep(array $definition, array $working, array $step, string $tenantId, array $payload): array
    {
        $joinType = strtolower(trim((string) ($step['joinType'] ?? $step['typeMode'] ?? 'left')));
        $localField = trim((string) ($step['localField'] ?? ''));
        $foreignField = trim((string) ($step['foreignField'] ?? ''));
        $select = is_array($step['fields'] ?? null) ? $step['fields'] : [];
        $targetDatasetId = trim((string) ($step['datasetId'] ?? ''));
        $targetEntityCode = trim((string) ($step['entityCode'] ?? ''));

        if ($localField === '' || $foreignField === '') {
            return $working;
        }
        $joinDataset = $targetEntityCode !== ''
            ? $this->loadEntityWorkingDataset($targetEntityCode, $tenantId, $payload)
            : ($targetDatasetId !== '' ? $this->loadDatasetWorkingDataset($definition, $targetDatasetId, $tenantId, $payload) : null);
        if (!is_array($joinDataset)) {
            return $working;
        }
        $index = [];
        foreach ((array) ($joinDataset['rows'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = $this->scalarKey($row[$foreignField] ?? null);
            $index[$key][] = $row;
        }

        $columns = is_array($working['columns'] ?? null) ? $working['columns'] : [];
        foreach ($select as $field) {
            if (!is_array($field)) {
                continue;
            }
            $from = trim((string) ($field['from'] ?? $field['field'] ?? ''));
            $target = trim((string) ($field['as'] ?? $field['id'] ?? $from));
            if ($from === '' || $target === '') {
                continue;
            }
            $columns[] = [
                'field' => $target,
                'id' => $target,
                'title' => (string) ($field['label'] ?? $target),
                'label' => (string) ($field['label'] ?? $target),
                'type' => (string) ($field['type'] ?? $this->columnType($joinDataset, $from)),
                'role' => 'field',
                'format' => $field['format'] ?? null,
            ];
        }

        $rows = [];
        foreach ((array) ($working['rows'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $matches = $index[$this->scalarKey($row[$localField] ?? null)] ?? [];
            if (!$matches && $joinType === 'inner') {
                continue;
            }
            if (!$matches) {
                $joined = $row;
                foreach ($select as $field) {
                    if (!is_array($field)) {
                        continue;
                    }
                    $target = trim((string) ($field['as'] ?? $field['id'] ?? ($field['from'] ?? $field['field'] ?? '')));
                    if ($target !== '') {
                        $joined[$target] = null;
                    }
                }
                $rows[] = $joined;
                continue;
            }
            foreach ($matches as $match) {
                $joined = $row;
                foreach ($select as $field) {
                    if (!is_array($field)) {
                        continue;
                    }
                    $from = trim((string) ($field['from'] ?? $field['field'] ?? ''));
                    $target = trim((string) ($field['as'] ?? $field['id'] ?? $from));
                    if ($from !== '' && $target !== '') {
                        $joined[$target] = $match[$from] ?? null;
                    }
                }
                $rows[] = $joined;
            }
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $working
     * @param array<string, mixed> $step
     * @return array<string, mixed>
     */
    private function applyDeriveStep(array $working, array $step): array
    {
        $operation = strtolower(trim((string) ($step['operation'] ?? 'concat')));
        $targetField = trim((string) ($step['targetField'] ?? $step['field'] ?? ''));
        if ($targetField === '') {
            return $working;
        }
        $type = match ($operation) {
            'year', 'month', 'day', 'bucket_number', 'map_value' => 'string',
            default => 'string',
        };
        $columns = is_array($working['columns'] ?? null) ? $working['columns'] : [];
        $columns[] = [
            'field' => $targetField,
            'id' => $targetField,
            'title' => (string) ($step['label'] ?? $targetField),
            'label' => (string) ($step['label'] ?? $targetField),
            'type' => $type,
            'role' => 'field',
            'format' => null,
        ];
        $rows = array_map(function (mixed $row) use ($step, $operation, $targetField) {
            if (!is_array($row)) {
                return $row;
            }
            $result = $row;
            if ($operation === 'concat') {
                $parts = [];
                foreach ((array) ($step['fields'] ?? []) as $field) {
                    $parts[] = isset($row[$field]) ? (string) $row[$field] : '';
                }
                $result[$targetField] = implode((string) ($step['separator'] ?? ' '), array_filter($parts, static fn ($item): bool => $item !== ''));
            } elseif (in_array($operation, ['year', 'month', 'day'], true)) {
                $sourceField = (string) ($step['sourceField'] ?? '');
                $raw = trim((string) ($row[$sourceField] ?? ''));
                $date = $raw !== '' ? strtotime($raw) : false;
                $result[$targetField] = $date ? date($operation === 'year' ? 'Y' : ($operation === 'month' ? 'm' : 'd'), $date) : null;
            } elseif ($operation === 'coalesce') {
                $value = null;
                foreach ((array) ($step['fields'] ?? []) as $field) {
                    if (isset($row[$field]) && $row[$field] !== null && $row[$field] !== '') {
                        $value = $row[$field];
                        break;
                    }
                }
                $result[$targetField] = $value;
            } elseif ($operation === 'bucket_number') {
                $sourceField = (string) ($step['sourceField'] ?? '');
                $numeric = is_numeric($row[$sourceField] ?? null) ? (float) $row[$sourceField] : null;
                $label = $step['defaultLabel'] ?? null;
                foreach ((array) ($step['ranges'] ?? []) as $range) {
                    if (!is_array($range)) {
                        continue;
                    }
                    $from = array_key_exists('from', $range) ? (float) $range['from'] : null;
                    $to = array_key_exists('to', $range) ? (float) $range['to'] : null;
                    if ($numeric === null) {
                        continue;
                    }
                    if (($from === null || $numeric >= $from) && ($to === null || $numeric <= $to)) {
                        $label = $range['label'] ?? $label;
                        break;
                    }
                }
                $result[$targetField] = $label;
            } elseif ($operation === 'map_value') {
                $sourceField = (string) ($step['sourceField'] ?? '');
                $value = $row[$sourceField] ?? null;
                $mapped = $step['defaultLabel'] ?? $value;
                foreach ((array) ($step['cases'] ?? []) as $case) {
                    if (is_array($case) && (string) ($case['value'] ?? '') === (string) $value) {
                        $mapped = $case['label'] ?? $mapped;
                        break;
                    }
                }
                $result[$targetField] = $mapped;
            }

            return $result;
        }, (array) ($working['rows'] ?? []));

        return [
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $working
     * @param array<string, mixed> $step
     * @return array<string, mixed>
     */
    private function applyGroupStep(array $working, array $step): array
    {
        $group = is_array($step['group'] ?? null) ? $step['group'] : [];
        $rawDimensions = $step['dimensions'] ?? ($group['dimensions'] ?? []);
        $rawMeasures = $step['measures'] ?? ($group['measures'] ?? []);
        $dimensions = array_values(array_filter(array_map(static function (mixed $item): string {
            if (is_array($item)) {
                return trim((string) ($item['field'] ?? $item['id'] ?? ''));
            }

            return trim((string) $item);
        }, (array) $rawDimensions)));
        $measures = is_array($rawMeasures) ? $rawMeasures : [];
        if (!$dimensions && !$measures) {
            return $working;
        }
        $buckets = [];
        foreach ((array) ($working['rows'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $keyParts = [];
            foreach ($dimensions as $dimension) {
                $keyParts[] = $this->scalarKey($row[$dimension] ?? null);
            }
            $key = implode('|', $keyParts);
            if (!isset($buckets[$key])) {
                $base = [];
                foreach ($dimensions as $dimension) {
                    $base[$dimension] = $row[$dimension] ?? null;
                }
                foreach ($measures as $measure) {
                    if (!is_array($measure)) {
                        continue;
                    }
                    $id = trim((string) ($measure['id'] ?? $measure['field'] ?? 'measure'));
                    $aggregate = strtolower(trim((string) ($measure['aggregate'] ?? 'count')));
                    $base[$id] = in_array($aggregate, ['min', 'max'], true) ? null : 0;
                }
                $buckets[$key] = $base;
            }
            foreach ($measures as $measure) {
                if (!is_array($measure)) {
                    continue;
                }
                $id = trim((string) ($measure['id'] ?? $measure['field'] ?? 'measure'));
                $field = trim((string) ($measure['field'] ?? ''));
                $aggregate = strtolower(trim((string) ($measure['aggregate'] ?? 'count')));
                $value = $field !== '' ? ($row[$field] ?? null) : null;
                $buckets[$key][$id] = $this->aggregateValue($aggregate, $buckets[$key][$id], $value, $row);
            }
        }

        $columns = [];
        foreach ($dimensions as $dimension) {
            $columns[] = [
                'field' => $dimension,
                'id' => $dimension,
                'title' => $dimension,
                'label' => $dimension,
                'type' => $this->columnType($working, $dimension),
                'role' => 'dimension',
            ];
        }
        foreach ($measures as $measure) {
            if (!is_array($measure)) {
                continue;
            }
            $id = trim((string) ($measure['id'] ?? $measure['field'] ?? 'measure'));
            $aggregate = strtolower(trim((string) ($measure['aggregate'] ?? 'count')));
            if (!in_array($aggregate, self::AGGREGATES, true)) {
                throw new RuntimeHttpException('ANALYTICS_PIPELINE_AGGREGATE_INVALID', 'Agregacao nao permitida no pipeline analytics.', 422, [
                    'aggregate' => $aggregate,
                    'stepId' => $step['id'] ?? null,
                ]);
            }
            $columns[] = [
                'field' => $id,
                'id' => $id,
                'title' => (string) ($measure['label'] ?? $id),
                'label' => (string) ($measure['label'] ?? $id),
                'type' => in_array($aggregate, ['count', 'distinct_count'], true) ? 'integer' : 'decimal',
                'role' => 'measure',
                'aggregate' => $aggregate,
                'format' => $measure['format'] ?? null,
            ];
        }

        return [
            'columns' => $columns,
            'rows' => array_values($buckets),
        ];
    }

    /**
     * @param array<string, mixed> $working
     * @param array<string, mixed> $step
     * @return array<string, mixed>
     */
    private function applySortStep(array $working, array $step): array
    {
        $sort = is_array($step['sort'] ?? null) ? $step['sort'] : [];
        $rows = $this->applyWorkingSort((array) ($working['rows'] ?? []), $sort ?: [[
            'field' => (string) ($step['field'] ?? ''),
            'dir' => (string) ($step['dir'] ?? 'asc'),
        ]]);

        return [
            'columns' => $working['columns'] ?? [],
            'rows' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $working
     * @param array<string, mixed> $step
     * @return array<string, mixed>
     */
    private function applyLimitStep(array $working, array $step): array
    {
        $take = max(1, min(5000, (int) ($step['take'] ?? $step['limit'] ?? 1000)));
        $skip = max(0, (int) ($step['skip'] ?? 0));

        return [
            'columns' => $working['columns'] ?? [],
            'rows' => array_slice((array) ($working['rows'] ?? []), $skip, $take),
        ];
    }

    /**
     * @param array<string, mixed> $working
     * @param array<string, mixed> $pipeline
     * @param array<string, mixed> $step
     * @return array<string, mixed>
     */
    private function applyPublishStep(array $working, array $pipeline, array $step): array
    {
        $meta = $working;
        $meta['_publish'] = [
            'publishedDatasetId' => trim((string) ($step['publishedDatasetId'] ?? $this->publishedDatasetId($pipeline))),
            'title' => (string) ($step['title'] ?? $pipeline['title'] ?? $pipeline['id'] ?? ''),
        ];

        return $meta;
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function loadDatasetWorkingDataset(array $definition, string $datasetId, string $tenantId, array $payload): array
    {
        $datasets = is_array($definition['analytics']['datasets'] ?? null) ? $definition['analytics']['datasets'] : [];
        foreach ($datasets as $dataset) {
            if (!is_array($dataset) || (string) ($dataset['id'] ?? '') !== $datasetId) {
                continue;
            }
            $sourceType = strtolower((string) ($dataset['source']['type'] ?? 'entity'));
            if ($sourceType === 'pipeline_published') {
                $result = $this->consumePublishedDataset((string) ($definition['screenId'] ?? ''), $dataset, $payload, $tenantId);
            } else {
                $entityCode = trim((string) ($dataset['source']['entityCode'] ?? ''));
                $result = $this->loadEntityWorkingDataset($entityCode, $tenantId, $payload);
            }

            return [
                'columns' => $result['columns'] ?? [],
                'rows' => $result['data'] ?? $result['rows'] ?? [],
            ];
        }

        throw new RuntimeHttpException('ANALYTICS_PIPELINE_SOURCE_DATASET_NOT_FOUND', 'Dataset fonte do pipeline nao encontrado.', 404, [
            'datasetId' => $datasetId,
        ]);
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function loadPipelinePublishedWorkingDataset(array $definition, string $pipelineId, string $tenantId): array
    {
        foreach ($this->pipelines($definition) as $pipeline) {
            if ((string) ($pipeline['id'] ?? '') !== $pipelineId) {
                continue;
            }
            $publishedDatasetId = $this->publishedDatasetId($pipeline);
            if ($publishedDatasetId === '') {
                break;
            }
            $active = $this->store->activePublishedDatasetVersion($tenantId, (string) ($definition['screenId'] ?? ''), $publishedDatasetId);
            if ($active === null) {
                throw new RuntimeHttpException('ANALYTICS_PIPELINE_DEPENDENCY_NOT_READY', 'Pipeline dependente ainda nao publicou dataset ativo.', 422, [
                    'pipelineId' => $pipelineId,
                    'publishedDatasetId' => $publishedDatasetId,
                ]);
            }
            $data = is_array($active['data'] ?? null) ? $active['data'] : [];

            return [
                'columns' => is_array($data['columns'] ?? null) ? $data['columns'] : [],
                'rows' => is_array($data['rows'] ?? null) ? $data['rows'] : [],
            ];
        }

        throw new RuntimeHttpException('ANALYTICS_PIPELINE_DEPENDENCY_NOT_FOUND', 'Pipeline dependente nao encontrado.', 404, [
            'pipelineId' => $pipelineId,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function loadEntityWorkingDataset(string $entityCode, string $tenantId, array $payload): array
    {
        $entity = $this->entities->resolve($entityCode);
        $rows = [];
        $fieldDefs = is_array($entity['fields'] ?? null) ? $entity['fields'] : [];
        $columns = [];
        foreach ($fieldDefs as $code => $field) {
            if (!is_array($field) || ($field['readable'] ?? true) !== true || ($field['virtual'] ?? false) === true || empty($field['column'])) {
                continue;
            }
            $columns[] = [
                'field' => (string) $code,
                'id' => (string) $code,
                'title' => (string) ($field['label'] ?? $code),
                'label' => (string) ($field['label'] ?? $code),
                'type' => (string) ($field['dataType'] ?? 'string'),
                'role' => 'field',
            ];
        }

        if (!$columns) {
            return ['columns' => [], 'rows' => []];
        }

        $table = (string) ($entity['quotedTableName'] ?? '');
        $qb = $this->entitiesQueryBuilder($table);
        foreach ($columns as $column) {
            $field = $fieldDefs[$column['field']] ?? [];
            $qb->addSelect('t."' . $field['column'] . '" AS "' . $column['field'] . '"');
        }
        $this->applySubscriberIsolation($qb, $entity, $tenantId);
        $this->applySoftDelete($qb, $entity);
        $limit = max(1, min(5000, (int) ($payload['take'] ?? $payload['limit'] ?? 1000)));
        $qb->setMaxResults($limit);
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return [
            'columns' => $columns,
            'rows' => array_map(fn (array $row): array => $this->normalizeNumericRow($row, $columns), $rows),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param mixed $sortConfig
     * @return list<array<string, mixed>>
     */
    private function applyWorkingSort(array $rows, mixed $sortConfig): array
    {
        $sort = is_array($sortConfig) ? $sortConfig : [];
        if (!$sort) {
            return $rows;
        }
        usort($rows, function (array $left, array $right) use ($sort): int {
            foreach ($sort as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $field = (string) ($item['field'] ?? '');
                if ($field === '') {
                    continue;
                }
                $dir = strtolower((string) ($item['dir'] ?? 'asc')) === 'desc' ? -1 : 1;
                $cmp = $this->compareValues($left[$field] ?? null, $right[$field] ?? null);
                if ($cmp !== 0) {
                    return $cmp * $dir;
                }
            }

            return 0;
        });

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param mixed $parameters
     * @param list<array<string, mixed>> $columns
     * @return list<array<string, mixed>>
     */
    private function applyWorkingFilters(array $rows, mixed $parameters, array $columns): array
    {
        $parameters = is_array($parameters) ? $parameters : [];
        if (!$parameters) {
            return $rows;
        }
        $byField = [];
        foreach ($columns as $column) {
            if (is_array($column) && !empty($column['field'])) {
                $byField[(string) $column['field']] = $column;
            }
        }

        return array_values(array_filter($rows, function (array $row) use ($parameters, $byField): bool {
            foreach ($parameters as $field => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $column = $byField[(string) $field] ?? null;
                $type = is_array($column) ? (string) ($column['type'] ?? 'string') : 'string';
                $current = $row[(string) $field] ?? null;
                if ($type === 'string' || $type === 'enum') {
                    if (stripos((string) $current, (string) $value) === false) {
                        return false;
                    }
                    continue;
                }
                if ((string) $current !== (string) $value) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $filter
     */
    private function matchesWorkingFilter(array $row, array $filter): bool
    {
        if (isset($filter['filters']) && is_array($filter['filters'])) {
            $logic = strtolower((string) ($filter['logic'] ?? 'and')) === 'or' ? 'or' : 'and';
            $results = [];
            foreach ($filter['filters'] as $item) {
                if (is_array($item)) {
                    $results[] = $this->matchesWorkingFilter($row, $item);
                }
            }

            return $logic === 'or'
                ? in_array(true, $results, true)
                : !in_array(false, $results, true);
        }

        $field = (string) ($filter['field'] ?? '');
        $operator = strtolower((string) ($filter['operator'] ?? 'eq'));
        $value = $filter['value'] ?? null;
        $current = $row[$field] ?? null;

        return match ($operator) {
            'eq' => (string) $current === (string) $value,
            'neq' => (string) $current !== (string) $value,
            'contains' => stripos((string) $current, (string) $value) !== false,
            'notcontains' => stripos((string) $current, (string) $value) === false,
            'startswith' => str_starts_with(mb_strtolower((string) $current), mb_strtolower((string) $value)),
            'endswith' => str_ends_with(mb_strtolower((string) $current), mb_strtolower((string) $value)),
            'in' => in_array((string) $current, array_map('strval', is_array($value) ? $value : [$value]), true),
            'between', 'range' => is_array($value) && count($value) >= 2 && $this->compareValues($current, $value[0]) >= 0 && $this->compareValues($current, $value[1]) <= 0,
            'gte' => $this->compareValues($current, $value) >= 0,
            'lte' => $this->compareValues($current, $value) <= 0,
            'gt' => $this->compareValues($current, $value) > 0,
            'lt' => $this->compareValues($current, $value) < 0,
            'isnull' => $current === null || $current === '',
            'isnotnull' => $current !== null && $current !== '',
            default => true,
        };
    }

    private function publishedDatasetId(array $pipeline): string
    {
        return trim((string) ($pipeline['publishConfig']['publishedDatasetId'] ?? $pipeline['publishedDatasetId'] ?? ''));
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function assertNoPipelineCycles(array $definition): void
    {
        $pipelines = $this->pipelines($definition);
        if ($pipelines === []) {
            return;
        }
        $graph = [];
        foreach ($pipelines as $pipeline) {
            $id = (string) ($pipeline['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $deps = [];
            $rootDependency = trim((string) ($pipeline['sourcePipelineId'] ?? ''));
            if ($rootDependency !== '') {
                $deps[] = $rootDependency;
            }
            foreach ((array) ($pipeline['steps'] ?? []) as $step) {
                if (!is_array($step)) {
                    continue;
                }
                $dependency = trim((string) ($step['sourcePipelineId'] ?? ''));
                if ($dependency !== '') {
                    $deps[] = $dependency;
                }
            }
            $graph[$id] = array_values(array_unique(array_filter($deps)));
        }

        $visiting = [];
        $visited = [];
        $stack = [];
        $visit = function (string $node) use (&$visit, &$graph, &$visiting, &$visited, &$stack): void {
            if (isset($visited[$node])) {
                return;
            }
            if (isset($visiting[$node])) {
                $cycle = array_values(array_slice($stack, array_search($node, $stack, true) ?: 0));
                $cycle[] = $node;
                throw new RuntimeHttpException('ANALYTICS_PIPELINE_CYCLE_DETECTED', 'Pipeline analytics possui dependencia ciclica.', 422, [
                    'cycle' => $cycle,
                ]);
            }
            $visiting[$node] = true;
            $stack[] = $node;
            foreach ((array) ($graph[$node] ?? []) as $dependency) {
                if (isset($graph[$dependency])) {
                    $visit((string) $dependency);
                }
            }
            array_pop($stack);
            unset($visiting[$node]);
            $visited[$node] = true;
        };

        foreach (array_keys($graph) as $node) {
            $visit((string) $node);
        }
    }

    private function columnType(array $working, string $field): string
    {
        foreach ((array) ($working['columns'] ?? []) as $column) {
            if (is_array($column) && (string) ($column['field'] ?? '') === $field) {
                return (string) ($column['type'] ?? 'string');
            }
        }

        return 'string';
    }

    private function tenantId(): string
    {
        return $this->permissions->getTenantId();
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $pipeline
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $result
     */
    private function recordAudit(array $definition, array $pipeline, array $payload, array $result, string $resultSource, string $tenantId): void
    {
        if (!$this->auditStore instanceof RuntimeAnalyticsAuditStore || !$this->auditStore->isEnabled()) {
            return;
        }

        $columns = is_array($result['columns'] ?? null) ? $result['columns'] : [];
        $rows = is_array($result['rows'] ?? null) ? $result['rows'] : [];
        $this->auditStore->record([
            'tenantId' => $tenantId,
            'userId' => $this->permissions->getUserId(),
            'sessionId' => $this->permissions->getSessionId(),
            'screenId' => (string) ($definition['screenId'] ?? ''),
            'datasetId' => (string) ($payload['publishedDatasetId'] ?? $payload['publishedDatasetId'] ?? $this->publishedDatasetId($pipeline) ?: $pipeline['id']),
            'viewId' => null,
            'executionMode' => 'pipeline',
            'resultSource' => $resultSource,
            'filterFingerprint' => hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'filters' => null,
            'parameters' => $payload,
            'sort' => null,
            'requestPayload' => $payload,
            'resultColumns' => $columns,
            'resultRows' => $rows,
            'rowCount' => count($rows),
            'totalCount' => count($rows),
            'metadata' => [
                'auditContext' => 'analytics_pipeline',
                'pipelineId' => $payload['pipelineId'] ?? $pipeline['id'] ?? null,
                'pipelineExecutionId' => $payload['pipelineExecutionId'] ?? null,
                'publishedDatasetId' => $payload['publishedDatasetId'] ?? $this->publishedDatasetId($pipeline),
                'publishedDatasetVersionId' => $payload['publishedDatasetVersionId'] ?? null,
            ],
            'consultedAt' => new \DateTimeImmutable(),
        ]);
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function loadDefinition(string $screenId): array
    {
        $screen = $this->screens->findPublishedByScreenId($screenId);
        if (!$screen) {
            throw new RuntimeHttpException('ANALYTICS_SCREEN_NOT_FOUND', 'Tela analytics nao encontrada.', 404, [
                'screenId' => $screenId,
            ]);
        }
        $this->integrity->assertScreen($screen);
        $definition = $screen->getDefinition();
        $customized = $this->customizations->resolve($screenId, $definition);
        if (is_array($customized) && $customized) {
            $definition = $customized;
        }
        $definition['screenId'] = $screen->getScreenId();
        if (($definition['pageType'] ?? '') !== 'analytics') {
            throw new RuntimeHttpException('ANALYTICS_PAGE_TYPE_INVALID', 'A tela informada nao e analytics.', 422);
        }
        if (!is_array($definition['analytics'] ?? null)) {
            throw new RuntimeHttpException('ANALYTICS_DEFINITION_MISSING', 'Definicao analytics nao configurada.', 422);
        }

        return $definition;
    }

    /**
     * @param mixed $steps
     * @return list<array<string, mixed>>
     */
    private function normalizeSteps(mixed $steps): array
    {
        $steps = is_array($steps) ? $steps : [];
        $normalized = [];
        foreach ($steps as $index => $step) {
            if (!is_array($step)) {
                continue;
            }
            $id = trim((string) ($step['id'] ?? 'step' . ($index + 1)));
            $type = strtolower(trim((string) ($step['type'] ?? '')));
            if ($id === '' || !in_array($type, self::STEP_TYPES, true)) {
                continue;
            }
            $this->assertNoFreeCode($step, 'analytics.semanticPipelines.' . $id);
            $step['id'] = $id;
            $step['type'] = $type;
            $normalized[] = $step;
        }

        return $normalized;
    }

    private function assertNoFreeCode(mixed $value, string $path): void
    {
        if (!is_array($value)) {
            return;
        }
        foreach ($value as $key => $item) {
            $normalizedKey = strtolower((string) $key);
            if (in_array($normalizedKey, ['sql', 'rawsql', 'expression', 'template', 'eval', 'script', 'javascript', 'function'], true)) {
                throw new RuntimeHttpException('ANALYTICS_FREE_CODE_BLOCKED', 'Analytics nao aceita SQL, JS ou template livre nos metadados.', 422, [
                    'path' => $path . '.' . (string) $key,
                ]);
            }
            $this->assertNoFreeCode($item, $path . '.' . (string) $key);
        }
    }

    private function entitiesQueryBuilder(string $table): \Doctrine\DBAL\Query\QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->from($table, 't');

        return $qb;
    }

    private function applySubscriberIsolation(\Doctrine\DBAL\Query\QueryBuilder $qb, array $entity, string $tenantId): void
    {
        $config = is_array($entity['subscriberIsolation'] ?? null) ? $entity['subscriberIsolation'] : [];
        if (($config['enabled'] ?? false) !== true || empty($config['column'])) {
            return;
        }
        $qb->andWhere('t."' . $config['column'] . '" = :tenantId')->setParameter('tenantId', $tenantId);
    }

    private function applySoftDelete(\Doctrine\DBAL\Query\QueryBuilder $qb, array $entity): void
    {
        $config = is_array($entity['softDelete'] ?? null) ? $entity['softDelete'] : [];
        if (($config['enabled'] ?? false) !== true || empty($config['deletedAtColumn'])) {
            return;
        }
        $qb->andWhere('t."' . $config['deletedAtColumn'] . '" IS NULL');
    }

    /**
     * @param list<array<string, mixed>> $columns
     * @return array<string, mixed>
     */
    private function normalizeNumericRow(array $row, array $columns): array
    {
        foreach ($columns as $column) {
            $field = (string) ($column['field'] ?? '');
            $type = (string) ($column['type'] ?? 'string');
            if ($field === '' || !array_key_exists($field, $row)) {
                continue;
            }
            if (in_array($type, ['integer'], true) && $row[$field] !== null) {
                $row[$field] = (int) $row[$field];
            }
            if (in_array($type, ['decimal', 'number', 'currency', 'float'], true) && $row[$field] !== null) {
                $row[$field] = (float) $row[$field];
            }
        }

        return $row;
    }

    private function compareValues(mixed $left, mixed $right): int
    {
        if (is_numeric($left) && is_numeric($right)) {
            return (float) $left <=> (float) $right;
        }

        return strcmp(mb_strtolower((string) $left), mb_strtolower((string) $right));
    }

    private function scalarKey(mixed $value): string
    {
        return is_scalar($value) || $value === null ? (string) $value : md5((string) json_encode($value));
    }

    private function aggregateValue(string $aggregate, mixed $current, mixed $value, array $row): mixed
    {
        return match ($aggregate) {
            'count' => (int) $current + 1,
            'sum' => (float) $current + (float) ($value ?? 0),
            'avg' => (float) $current + (float) ($value ?? 0),
            'min' => $current === null ? $value : min($current, $value),
            'max' => $current === null ? $value : max($current, $value),
            'distinct_count' => is_array($current) ? array_values(array_unique(array_merge($current, [(string) $value]))) : [(string) $value],
            default => $current,
        };
    }
}
