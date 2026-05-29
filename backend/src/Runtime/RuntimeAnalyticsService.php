<?php

namespace App\Runtime;

use App\Repository\ScreenDefinitionRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Types;

class RuntimeAnalyticsService
{
    private const CACHE_TABLE = 'runtime_analytics_cache';
    private const AGGREGATES = ['count', 'sum', 'avg', 'min', 'max', 'distinct_count'];
    private const MAX_LIMIT = 5000;

    public function __construct(
        private readonly ScreenDefinitionRepository $screens,
        private readonly RuntimeEntityDefinitionResolver $entities,
        private readonly Connection $connection,
        private readonly PermissionResolver $permissions,
        private readonly StructuralIntegrityService $integrity,
        private readonly ProgramCustomizationResolver $customizations,
        private readonly ?RuntimeAnalyticsPipelineService $pipelines = null,
        private readonly ?RuntimeAnalyticsAuditStore $auditStore = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(string $screenId): array
    {
        $definition = $this->loadDefinition($screenId);

        return [
            'screenId' => $screenId,
            'pageType' => 'analytics',
            'program' => is_array($definition['program'] ?? null) ? $definition['program'] : [],
            'analytics' => $definition['analytics'],
            'dataSource' => is_array($definition['dataSource'] ?? null) ? $definition['dataSource'] : [],
            'runtime' => [
                'analytics' => [
                    'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function run(string $screenId, array $payload, ?string $tenantId = null): array
    {
        $definition = $this->loadDefinition($screenId);
        $dataset = $this->resolveDataset($definition, $payload['datasetId'] ?? null);
        $viewId = $this->normalizeOptionalCode($payload['viewId'] ?? null);
        $executionMode = $this->resolveExecutionMode($dataset, $payload);
        $fingerprint = $this->filterFingerprint($payload);

        if ($executionMode !== 'live' && ($payload['forceLive'] ?? false) !== true) {
            $cache = $this->findCache($screenId, (string) $dataset['id'], $viewId, $fingerprint, $tenantId);
            if ($cache !== null) {
                $result = is_array($cache['payload'] ?? null) ? $cache['payload'] : [];
                $runtime = is_array($result['_runtime'] ?? null) ? $result['_runtime'] : [];
                $runtime['analyticsCache'] = [
                    'status' => 'hit',
                    'screenId' => $screenId,
                    'datasetId' => (string) $dataset['id'],
                    'viewId' => $viewId,
                    'fingerprint' => $fingerprint,
                    'refreshedAt' => $cache['refreshedAt'] ?? null,
                    'expiresAt' => $cache['expiresAt'] ?? null,
                ];
                $result['_runtime'] = $runtime;
                $this->recordAudit($definition, $dataset, $payload, $result, 'cache_hit', $fingerprint, $tenantId);

                return $result;
            }

            if ($executionMode === 'cached' && ($dataset['allowLiveFallback'] ?? false) !== true) {
                return [
                    'data' => [],
                    'total' => 0,
                    'columns' => [],
                    'datasetId' => (string) $dataset['id'],
                    '_runtime' => [
                        'analyticsCache' => [
                            'status' => 'miss',
                            'screenId' => $screenId,
                            'datasetId' => (string) $dataset['id'],
                            'viewId' => $viewId,
                            'fingerprint' => $fingerprint,
                        ],
                    ],
                ];
            }
        }

        $result = $this->executeDataset($definition, $dataset, $payload, $tenantId);
        $runtime = is_array($result['_runtime'] ?? null) ? $result['_runtime'] : [];
        $runtime['analyticsCache'] = [
            'status' => $executionMode === 'live' ? 'bypassed' : 'miss_live',
            'screenId' => $screenId,
            'datasetId' => (string) $dataset['id'],
            'viewId' => $viewId,
            'fingerprint' => $fingerprint,
        ];
        $result['_runtime'] = $runtime;
        $this->recordAudit($definition, $dataset, $payload, $result, 'live', $fingerprint, $tenantId);

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function materialize(string $screenId, array $payload, ?string $tenantId = null): array
    {
        $definition = $this->loadDefinition($screenId);
        $dataset = $this->resolveDataset($definition, $payload['datasetId'] ?? null);
        $datasetId = (string) $dataset['id'];
        $viewId = $this->normalizeOptionalCode($payload['viewId'] ?? null);
        $fingerprint = $this->filterFingerprint($payload);

        try {
            $livePayload = $payload;
            $livePayload['forceLive'] = true;
            $result = $this->executeDataset($definition, $dataset, $livePayload, $tenantId);
            $expiresAt = $this->resolveCacheExpiration($dataset);
            $this->storeCache($screenId, $datasetId, $viewId, $fingerprint, 'ready', $result, null, $expiresAt, $tenantId);
            $this->recordAudit($definition, $dataset, $payload, $result, 'materialize', $fingerprint, $tenantId, [
                'expiresAt' => $expiresAt?->format(DATE_ATOM),
            ]);

            return [
                'ok' => true,
                'screenId' => $screenId,
                'datasetId' => $datasetId,
                'viewId' => $viewId,
                'fingerprint' => $fingerprint,
                'rowCount' => count($result['data'] ?? []),
                'expiresAt' => $expiresAt?->format(DATE_ATOM),
            ];
        } catch (\Throwable $error) {
            $this->storeCache($screenId, $datasetId, $viewId, $fingerprint, 'error', [
                'data' => [],
                'total' => 0,
                'columns' => [],
                'datasetId' => $datasetId,
            ], $error->getMessage(), null, $tenantId);
            $this->recordAudit($definition, $dataset, $payload, [
                'data' => [],
                'total' => 0,
                'columns' => [],
                'datasetId' => $datasetId,
            ], 'error', $fingerprint, $tenantId, [
                'errorMessage' => $error->getMessage(),
            ]);
            throw $error;
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function cacheStatus(string $screenId, array $payload, ?string $tenantId = null): array
    {
        $definition = $this->loadDefinition($screenId);
        $dataset = $this->resolveDataset($definition, $payload['datasetId'] ?? null);
        $viewId = $this->normalizeOptionalCode($payload['viewId'] ?? null);
        $fingerprint = $this->filterFingerprint($payload);
        $cache = $this->findCache($screenId, (string) $dataset['id'], $viewId, $fingerprint, $tenantId, includeExpired: true);

        if ($cache === null) {
            return [
                'status' => 'miss',
                'screenId' => $screenId,
                'datasetId' => (string) $dataset['id'],
                'viewId' => $viewId,
                'fingerprint' => $fingerprint,
            ];
        }

        return [
            'status' => $cache['status'] ?? 'unknown',
            'screenId' => $screenId,
            'datasetId' => (string) $dataset['id'],
            'viewId' => $viewId,
            'fingerprint' => $fingerprint,
            'rowCount' => $cache['rowCount'] ?? 0,
            'refreshedAt' => $cache['refreshedAt'] ?? null,
            'expiresAt' => $cache['expiresAt'] ?? null,
            'expired' => $this->isCacheExpired($cache),
            'lastError' => $cache['lastError'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function filterFingerprint(array $payload): string
    {
        $source = [
            'datasetId' => $payload['datasetId'] ?? null,
            'viewId' => $payload['viewId'] ?? null,
            'filter' => $payload['filter'] ?? null,
            'filters' => $payload['filters'] ?? null,
            'parameters' => $payload['parameters'] ?? null,
            'sort' => $payload['sort'] ?? null,
            'take' => $payload['take'] ?? null,
            'skip' => $payload['skip'] ?? null,
        ];

        return hash('sha256', (string) json_encode($this->canonicalize($source), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
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
        $definition['pageType'] = $definition['pageType'] ?? $screen->getPageType();

        if (($definition['pageType'] ?? '') !== 'analytics') {
            throw new RuntimeHttpException('ANALYTICS_PAGE_TYPE_INVALID', 'A tela informada nao e analytics.', 422, [
                'screenId' => $screenId,
                'pageType' => $definition['pageType'] ?? null,
            ]);
        }
        if (!is_array($definition['analytics'] ?? null)) {
            throw new RuntimeHttpException('ANALYTICS_DEFINITION_MISSING', 'Definicao analytics nao configurada.', 422, [
                'screenId' => $screenId,
            ]);
        }
        if (!is_array($definition['analytics']['datasets'] ?? null) || !$definition['analytics']['datasets']) {
            throw new RuntimeHttpException('ANALYTICS_DATASET_MISSING', 'Definicao analytics precisa de ao menos um dataset.', 422, [
                'screenId' => $screenId,
            ]);
        }

        return $definition;
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function resolveDataset(array $definition, mixed $datasetId): array
    {
        $requested = $this->normalizeOptionalCode($datasetId);
        $datasets = $definition['analytics']['datasets'] ?? [];
        foreach ($datasets as $index => $dataset) {
            if (!is_array($dataset)) {
                continue;
            }
            $id = $this->normalizeOptionalCode($dataset['id'] ?? null);
            if ($id === '') {
                $id = 'dataset' . ((int) $index + 1);
            }
            $dataset['id'] = $id;
            if ($requested === '' || $requested === $id) {
                $this->assertNoFreeCode($dataset, 'analytics.datasets.' . $id);
                return $dataset;
            }
        }

        throw new RuntimeHttpException('ANALYTICS_DATASET_NOT_FOUND', 'Dataset analytics nao encontrado.', 404, [
            'datasetId' => $requested,
        ]);
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $dataset
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function executeDataset(array $definition, array $dataset, array $payload, ?string $tenantId): array
    {
        $source = is_array($dataset['source'] ?? null) ? $dataset['source'] : [];
        $sourceType = strtolower((string) ($source['type'] ?? 'entity'));
        if ($sourceType === 'pipeline_published') {
            if (!$this->pipelines instanceof RuntimeAnalyticsPipelineService) {
                throw new RuntimeHttpException('ANALYTICS_PIPELINE_SERVICE_MISSING', 'Servico de pipeline analytics nao configurado.', 500);
            }

            return $this->pipelines->consumePublishedDataset((string) ($definition['screenId'] ?? ''), $dataset, $payload, $tenantId);
        }

        [$qb, $contexts] = $this->createBaseQuery($dataset, $payload, $tenantId);
        $columns = [];
        $dimensions = $this->normalizeFieldSpecs($dataset['dimensions'] ?? []);
        $measures = $this->normalizeFieldSpecs($dataset['measures'] ?? []);
        $aggregated = $measures !== [];

        if ($aggregated) {
            $selects = [];
            foreach ($dimensions as $dimension) {
                $field = $this->resolveFieldRef($contexts, $dimension);
                $id = $this->safeAlias((string) ($dimension['id'] ?? $field['id']));
                $selects[] = $field['columnExpr'] . ' AS ' . $this->quote($id);
                $qb->addGroupBy($field['columnExpr']);
                $columns[] = $this->columnMetadata($id, $dimension['label'] ?? $field['label'], $field['dataType'], 'dimension', null, $dimension['format'] ?? $field['format'] ?? null);
            }
            foreach ($measures as $measure) {
                $aggregate = strtolower((string) ($measure['aggregate'] ?? $measure['defaultAggregate'] ?? 'count'));
                if (!in_array($aggregate, self::AGGREGATES, true)) {
                    throw new RuntimeHttpException('ANALYTICS_AGGREGATE_NOT_ALLOWED', 'Agregacao analytics nao permitida.', 422, [
                        'aggregate' => $aggregate,
                    ]);
                }
                $measureField = $this->resolveMeasureField($contexts, $measure, $aggregate);
                $id = $this->safeAlias((string) ($measure['id'] ?? ($measureField['id'] . '_' . $aggregate)));
                $selects[] = $this->aggregateExpression($aggregate, $measureField) . ' AS ' . $this->quote($id);
                $columns[] = $this->columnMetadata($id, $measure['label'] ?? $this->measureLabel($aggregate, $measureField), in_array($aggregate, ['count', 'distinct_count'], true) ? 'integer' : 'decimal', 'measure', $aggregate, $measure['format'] ?? $measureField['format'] ?? null);
            }
            if (!$selects) {
                throw new RuntimeHttpException('ANALYTICS_SELECT_EMPTY', 'Dataset analytics nao possui dimensoes ou medidas validas.', 422);
            }
            $qb->select(...$selects);
        } else {
            $fields = $this->normalizeFieldSpecs($dataset['fields'] ?? []);
            if (!$fields) {
                $fields = $this->defaultDetailFields($contexts);
            }
            $selects = [];
            foreach ($fields as $fieldSpec) {
                $field = $this->resolveFieldRef($contexts, $fieldSpec);
                $id = $this->safeAlias((string) ($fieldSpec['id'] ?? $field['id']));
                $selects[] = $field['columnExpr'] . ' AS ' . $this->quote($id);
                $columns[] = $this->columnMetadata($id, $fieldSpec['label'] ?? $field['label'], $field['dataType'], 'field', null, $fieldSpec['format'] ?? $field['format'] ?? null);
            }
            $qb->select(...$selects);
        }

        $this->applySort($qb, $contexts, $columns, $payload['sort'] ?? $dataset['defaultSort'] ?? $dataset['sort'] ?? [], $aggregated);
        $limit = $this->resolveLimit($dataset, $payload);
        $skip = max(0, (int) ($payload['skip'] ?? 0));
        $qb->setMaxResults($limit)->setFirstResult($skip);

        $rows = array_map(
            fn (array $row): array => $this->formatRow($row, $columns),
            $qb->executeQuery()->fetchAllAssociative()
        );

        $total = count($rows);
        if (!$aggregated) {
            [$countQb] = $this->createBaseQuery($dataset, $payload, $tenantId);
            $countQb->select('COUNT(*) AS total');
            $total = (int) $countQb->executeQuery()->fetchOne();
        }

        return [
            'data' => $rows,
            'total' => $total,
            'columns' => $columns,
            'datasetId' => (string) $dataset['id'],
            'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            '_runtime' => [
                'analytics' => [
                    'screenId' => (string) ($definition['screenId'] ?? ''),
                    'datasetId' => (string) $dataset['id'],
                    'executionMode' => $this->resolveExecutionMode($dataset, $payload),
                    'aggregated' => $aggregated,
                    'limit' => $limit,
                    'skip' => $skip,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $dataset
     * @param array<string, mixed> $payload
     * @return array{0: QueryBuilder, 1: array<string, array<string, mixed>>}
     */
    private function createBaseQuery(array $dataset, array $payload, ?string $tenantId): array
    {
        $source = is_array($dataset['source'] ?? null) ? $dataset['source'] : [];
        $entityCode = $this->safeCode((string) ($source['entityCode'] ?? $dataset['entityCode'] ?? ''));
        if ($entityCode === '') {
            throw new RuntimeHttpException('ANALYTICS_SOURCE_ENTITY_REQUIRED', 'Dataset analytics precisa informar source.entityCode.', 422, [
                'datasetId' => $dataset['id'] ?? null,
            ]);
        }
        $sourceType = strtolower((string) ($source['type'] ?? 'entity'));
        if (!in_array($sourceType, ['entity', 'persistence'], true)) {
            throw new RuntimeHttpException('ANALYTICS_SOURCE_TYPE_NOT_SUPPORTED', 'Analytics v1 aceita apenas fontes internas persistentes.', 422, [
                'sourceType' => $sourceType,
            ]);
        }

        $baseDefinition = $this->entities->resolve($entityCode);
        $contexts = [
            'base' => [
                'id' => 'base',
                'definition' => $baseDefinition,
                'sqlAlias' => 't',
            ],
        ];

        $qb = $this->connection->createQueryBuilder();
        $qb->from($baseDefinition['quotedTableName'], 't');
        $this->applySubscriberIsolation($qb, $baseDefinition, 't', $tenantId, 'baseTenantId');
        $this->applySoftDeleteFilter($qb, $baseDefinition, 't');

        foreach ($this->normalizeJoins($dataset['joins'] ?? []) as $index => $join) {
            $joinId = $this->safeCode((string) ($join['id'] ?? $join['alias'] ?? ('join' . ((int) $index + 1))));
            $joinEntityCode = $this->safeCode((string) ($join['entityCode'] ?? ''));
            $sourceId = $this->safeCode((string) ($join['source'] ?? 'base')) ?: 'base';
            $localField = $this->safeCode((string) ($join['localField'] ?? ''));
            $foreignField = $this->safeCode((string) ($join['foreignField'] ?? ''));
            if ($joinId === '' || $joinEntityCode === '' || $localField === '' || $foreignField === '' || !isset($contexts[$sourceId])) {
                throw new RuntimeHttpException('ANALYTICS_JOIN_INVALID', 'Join analytics invalido.', 422, [
                    'join' => $join,
                ]);
            }

            $leftContext = $contexts[$sourceId];
            $rightDefinition = $this->entities->resolve($joinEntityCode);
            $leftField = $this->resolveFieldRef($contexts, ['source' => $sourceId, 'field' => $localField]);
            $rightField = $this->resolveFieldFromDefinition($rightDefinition, $foreignField, $joinId);
            $sqlAlias = 'j' . ((int) $index + 1);
            $conditions = [
                $leftField['columnExpr'] . ' = ' . $sqlAlias . '.' . $this->quote($rightField['column']),
            ];
            $tenantCondition = $this->joinSubscriberIsolationCondition($rightDefinition, $sqlAlias, $tenantId, 'joinTenantId' . ((int) $index + 1), $qb);
            if ($tenantCondition !== null) {
                $conditions[] = $tenantCondition;
            }
            $softDeleteCondition = $this->joinSoftDeleteCondition($rightDefinition, $sqlAlias);
            if ($softDeleteCondition !== null) {
                $conditions[] = $softDeleteCondition;
            }

            $joinType = strtolower((string) ($join['type'] ?? 'left'));
            if ($joinType === 'inner') {
                $qb->innerJoin($leftContext['sqlAlias'], $rightDefinition['quotedTableName'], $sqlAlias, implode(' AND ', $conditions));
            } else {
                $qb->leftJoin($leftContext['sqlAlias'], $rightDefinition['quotedTableName'], $sqlAlias, implode(' AND ', $conditions));
            }
            $contexts[$joinId] = [
                'id' => $joinId,
                'definition' => $rightDefinition,
                'sqlAlias' => $sqlAlias,
            ];
        }

        $counter = 0;
        $datasetFilter = $dataset['filters'] ?? $dataset['filter'] ?? null;
        $payloadFilter = $payload['filter'] ?? $payload['filters'] ?? null;
        $this->applyFilter($qb, $contexts, $datasetFilter, $counter, 'datasetFilter');
        $this->applyFilter($qb, $contexts, $payloadFilter, $counter, 'payloadFilter');
        $this->applyParameters($qb, $contexts, $dataset, $payload, $counter);

        return [$qb, $contexts];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeJoins(mixed $joins): array
    {
        if (!is_array($joins)) {
            return [];
        }
        return array_values(array_filter($joins, 'is_array'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeFieldSpecs(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }
        $result = [];
        foreach ($items as $key => $item) {
            if (is_string($item)) {
                $result[] = ['id' => $this->safeAlias(str_replace('.', '_', $item)), 'field' => $item];
                continue;
            }
            if (is_array($item)) {
                if (is_string($key) && !isset($item['id'])) {
                    $item['id'] = $key;
                }
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * @param array<string, array<string, mixed>> $contexts
     * @return list<array<string, mixed>>
     */
    private function defaultDetailFields(array $contexts): array
    {
        $fields = [];
        foreach ($contexts['base']['definition']['fields'] as $fieldCode => $field) {
            if (($field['readable'] ?? false) !== true || ($field['virtual'] ?? false) === true || empty($field['column'])) {
                continue;
            }
            if (($field['dataType'] ?? '') === 'json') {
                continue;
            }
            $fields[] = ['id' => $fieldCode, 'field' => $fieldCode];
            if (count($fields) >= 12) {
                break;
            }
        }

        return $fields;
    }

    /**
     * @param array<string, array<string, mixed>> $contexts
     * @param array<string, mixed> $spec
     * @return array<string, mixed>
     */
    private function resolveMeasureField(array $contexts, array $spec, string $aggregate): array
    {
        if ($aggregate === 'count' && empty($spec['field'])) {
            return [
                'id' => 'rows',
                'label' => 'Registros',
                'columnExpr' => '*',
                'dataType' => 'integer',
                'column' => '*',
            ];
        }

        $field = $this->resolveFieldRef($contexts, $spec);
        if (!in_array($aggregate, ['count', 'distinct_count'], true) && !$this->isNumericType((string) $field['dataType'])) {
            throw new RuntimeHttpException('ANALYTICS_MEASURE_TYPE_INVALID', 'Medida analytics exige campo numerico para esta agregacao.', 422, [
                'field' => $field['id'],
                'aggregate' => $aggregate,
                'dataType' => $field['dataType'],
            ]);
        }

        return $field;
    }

    /**
     * @param array<string, array<string, mixed>> $contexts
     * @param array<string, mixed> $spec
     * @return array<string, mixed>
     */
    private function resolveFieldRef(array $contexts, array $spec): array
    {
        $field = (string) ($spec['field'] ?? $spec['fieldCode'] ?? '');
        $source = (string) ($spec['source'] ?? $spec['entityAlias'] ?? 'base');
        if (str_contains($field, '.')) {
            [$source, $field] = explode('.', $field, 2);
        }
        $source = $this->safeCode($source) ?: 'base';
        $field = $this->safeCode($field);
        if (!isset($contexts[$source])) {
            throw new RuntimeHttpException('ANALYTICS_SOURCE_ALIAS_INVALID', 'Fonte analytics nao encontrada no dataset.', 422, [
                'source' => $source,
            ]);
        }
        $resolved = $this->resolveFieldFromDefinition($contexts[$source]['definition'], $field, $source);
        $resolved['id'] = $source === 'base' ? $field : $source . '_' . $field;
        $resolved['columnExpr'] = $contexts[$source]['sqlAlias'] . '.' . $this->quote($resolved['column']);

        return $resolved;
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function resolveFieldFromDefinition(array $definition, string $fieldCode, string $source): array
    {
        if ($fieldCode === '' || !isset($definition['fields'][$fieldCode])) {
            throw new RuntimeHttpException('ANALYTICS_FIELD_NOT_FOUND', 'Campo analytics nao encontrado na entidade.', 422, [
                'source' => $source,
                'field' => $fieldCode,
            ]);
        }
        $field = $definition['fields'][$fieldCode];
        if (($field['readable'] ?? false) !== true || ($field['virtual'] ?? false) === true || empty($field['column'])) {
            throw new RuntimeHttpException('ANALYTICS_FIELD_NOT_READABLE', 'Campo analytics nao pode ser lido pelo runtime.', 422, [
                'source' => $source,
                'field' => $fieldCode,
            ]);
        }

        return [
            'id' => $fieldCode,
            'code' => $fieldCode,
            'column' => (string) $field['column'],
            'label' => (string) ($field['label'] ?? $fieldCode),
            'dataType' => (string) ($field['dataType'] ?? 'string'),
            'format' => is_array($field['options']['analytics'] ?? null) ? ($field['options']['analytics']['format'] ?? null) : null,
            'field' => $field,
        ];
    }

    private function aggregateExpression(string $aggregate, array $field): string
    {
        $column = $field['columnExpr'];

        return match ($aggregate) {
            'count' => $column === '*' ? 'COUNT(*)' : 'COUNT(' . $column . ')',
            'sum' => 'SUM(' . $column . ')',
            'avg' => 'AVG(' . $column . ')',
            'min' => 'MIN(' . $column . ')',
            'max' => 'MAX(' . $column . ')',
            'distinct_count' => 'COUNT(DISTINCT ' . $column . ')',
            default => throw new RuntimeHttpException('ANALYTICS_AGGREGATE_NOT_ALLOWED', 'Agregacao analytics nao permitida.', 422, [
                'aggregate' => $aggregate,
            ]),
        };
    }

    private function measureLabel(string $aggregate, array $field): string
    {
        $label = (string) ($field['label'] ?? $field['id']);

        return match ($aggregate) {
            'count' => 'Qtde. ' . $label,
            'sum' => 'Soma de ' . $label,
            'avg' => 'Media de ' . $label,
            'min' => 'Menor ' . $label,
            'max' => 'Maior ' . $label,
            'distinct_count' => 'Distintos de ' . $label,
            default => $label,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function columnMetadata(string $id, mixed $label, string $type, string $role, ?string $aggregate = null, mixed $format = null): array
    {
        $column = [
            'field' => $id,
            'id' => $id,
            'title' => (string) ($label ?: $id),
            'label' => (string) ($label ?: $id),
            'type' => $type,
            'role' => $role,
        ];
        if ($aggregate !== null) {
            $column['aggregate'] = $aggregate;
        }
        if (is_string($format) && trim($format) !== '') {
            $column['format'] = trim($format);
        }

        return $column;
    }

    /**
     * @param array<string, array<string, mixed>> $contexts
     * @param list<array<string, mixed>> $columns
     */
    private function applySort(QueryBuilder $qb, array $contexts, array $columns, mixed $sort, bool $aggregated): void
    {
        $items = is_array($sort) ? $sort : [];
        if (!$items) {
            return;
        }
        $columnIds = array_fill_keys(array_map(static fn (array $column): string => (string) $column['field'], $columns), true);

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $dir = strtolower((string) ($item['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
            $fieldId = $this->safeAlias((string) ($item['field'] ?? $item['id'] ?? ''));
            if ($fieldId !== '' && isset($columnIds[$fieldId])) {
                $qb->addOrderBy($this->quote($fieldId), $dir);
                continue;
            }
            if (!$aggregated) {
                try {
                    $field = $this->resolveFieldRef($contexts, $item);
                    $qb->addOrderBy($field['columnExpr'], $dir);
                } catch (RuntimeHttpException) {
                    continue;
                }
            }
        }
    }

    /**
     * @param array<string, array<string, mixed>> $contexts
     */
    private function applyParameters(QueryBuilder $qb, array $contexts, array $dataset, array $payload, int &$counter): void
    {
        $parameterValues = is_array($payload['parameters'] ?? null) ? $payload['parameters'] : [];
        if (!$parameterValues) {
            return;
        }

        $parameters = $this->normalizeFieldSpecs($dataset['parameters'] ?? []);
        foreach ($parameters as $parameter) {
            $id = (string) ($parameter['id'] ?? $parameter['field'] ?? '');
            if ($id === '' || !array_key_exists($id, $parameterValues)) {
                continue;
            }
            $value = $parameterValues[$id];
            if ($value === null || $value === '') {
                continue;
            }
            $filter = [
                'field' => $parameter['field'] ?? $id,
                'source' => $parameter['source'] ?? 'base',
                'operator' => $parameter['operator'] ?? 'eq',
                'value' => $value,
            ];
            $expression = $this->buildFilterExpression($qb, $contexts, $filter, $counter, 'parameter');
            if ($expression !== null) {
                $qb->andWhere($expression);
            }
        }
    }

    /**
     * @param array<string, array<string, mixed>> $contexts
     */
    private function applyFilter(QueryBuilder $qb, array $contexts, mixed $filter, int &$counter, string $prefix): void
    {
        if (!is_array($filter) || $filter === []) {
            return;
        }
        if (array_is_list($filter)) {
            $filter = ['logic' => 'and', 'filters' => $filter];
        }
        $expression = $this->buildFilterExpression($qb, $contexts, $filter, $counter, $prefix);
        if ($expression !== null) {
            $qb->andWhere($expression);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $contexts
     */
    private function buildFilterExpression(QueryBuilder $qb, array $contexts, array $filter, int &$counter, string $prefix): ?string
    {
        if (isset($filter['filters']) && is_array($filter['filters'])) {
            $parts = [];
            foreach ($filter['filters'] as $child) {
                if (!is_array($child)) {
                    continue;
                }
                $part = $this->buildFilterExpression($qb, $contexts, $child, $counter, $prefix);
                if ($part !== null) {
                    $parts[] = $part;
                }
            }
            if (!$parts) {
                return null;
            }
            $logic = strtolower((string) ($filter['logic'] ?? 'and')) === 'or' ? ' OR ' : ' AND ';
            return '(' . implode($logic, $parts) . ')';
        }

        $field = $this->resolveFieldRef($contexts, $filter);
        $operator = strtolower((string) ($filter['operator'] ?? 'eq'));
        $value = $filter['value'] ?? null;
        $column = $field['columnExpr'];
        $param = $prefix . (++$counter);

        if (in_array($operator, ['isnull', 'is_null'], true)) {
            return $column . ' IS NULL';
        }
        if (in_array($operator, ['isnotnull', 'is_not_null'], true)) {
            return $column . ' IS NOT NULL';
        }
        if (in_array($operator, ['contains', 'startswith', 'endswith', 'notcontains'], true)) {
            $needle = mb_strtolower((string) $value);
            $pattern = match ($operator) {
                'startswith' => $needle . '%',
                'endswith' => '%' . $needle,
                default => '%' . $needle . '%',
            };
            $qb->setParameter($param, $pattern);
            $condition = 'LOWER(CAST(' . $column . ' AS TEXT)) LIKE :' . $param;
            return $operator === 'notcontains' ? 'NOT (' . $condition . ')' : $condition;
        }
        if (in_array($operator, ['eq', 'equals'], true)) {
            $qb->setParameter($param, $this->normalizeValue($field['dataType'], $value));
            return $column . ' = :' . $param;
        }
        if (in_array($operator, ['neq', 'noteq', 'not_equals'], true)) {
            $qb->setParameter($param, $this->normalizeValue($field['dataType'], $value));
            return $column . ' <> :' . $param;
        }
        if (in_array($operator, ['gte', 'lte', 'gt', 'lt'], true)) {
            $qb->setParameter($param, $this->normalizeValue($field['dataType'], $value));
            $sqlOperator = ['gte' => '>=', 'lte' => '<=', 'gt' => '>', 'lt' => '<'][$operator];
            return $column . ' ' . $sqlOperator . ' :' . $param;
        }
        if (in_array($operator, ['between', 'range'], true)) {
            $values = is_array($value) ? array_values($value) : [];
            if (count($values) < 2) {
                return null;
            }
            $from = $param . '_from';
            $to = $param . '_to';
            $qb->setParameter($from, $this->normalizeValue($field['dataType'], $values[0]));
            $qb->setParameter($to, $this->normalizeValue($field['dataType'], $values[1]));
            return '(' . $column . ' >= :' . $from . ' AND ' . $column . ' <= :' . $to . ')';
        }
        if (in_array($operator, ['in', 'list'], true)) {
            $values = is_array($value) ? array_values($value) : [$value];
            $placeholders = [];
            foreach ($values as $index => $item) {
                $itemParam = $param . '_' . $index;
                $qb->setParameter($itemParam, $this->normalizeValue($field['dataType'], $item));
                $placeholders[] = ':' . $itemParam;
            }
            return $placeholders ? $column . ' IN (' . implode(', ', $placeholders) . ')' : null;
        }
        if (in_array($operator, ['relativedate', 'relative_date'], true)) {
            $range = $this->relativeDateRange($value);
            if ($range === null) {
                return null;
            }
            $from = $param . '_from';
            $to = $param . '_to';
            $qb->setParameter($from, $range[0]);
            $qb->setParameter($to, $range[1]);
            return '(' . $column . ' >= :' . $from . ' AND ' . $column . ' <= :' . $to . ')';
        }

        throw new RuntimeHttpException('ANALYTICS_FILTER_OPERATOR_INVALID', 'Operador de filtro analytics nao permitido.', 422, [
            'operator' => $operator,
        ]);
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function relativeDateRange(mixed $value): ?array
    {
        $today = new \DateTimeImmutable('today');
        $key = strtolower((string) (is_array($value) ? ($value['preset'] ?? '') : $value));

        return match ($key) {
            'today', 'hoje' => [$today->format('Y-m-d'), $today->format('Y-m-d')],
            'yesterday', 'ontem' => [$today->modify('-1 day')->format('Y-m-d'), $today->modify('-1 day')->format('Y-m-d')],
            'last_7_days', 'ultimos_7_dias' => [$today->modify('-6 days')->format('Y-m-d'), $today->format('Y-m-d')],
            'last_30_days', 'ultimos_30_dias' => [$today->modify('-29 days')->format('Y-m-d'), $today->format('Y-m-d')],
            'this_month', 'mes_atual' => [$today->modify('first day of this month')->format('Y-m-d'), $today->modify('last day of this month')->format('Y-m-d')],
            default => null,
        };
    }

    private function normalizeValue(string $type, mixed $value): mixed
    {
        return match ($type) {
            'integer' => $value === null || $value === '' ? null : (int) $value,
            'decimal', 'number', 'currency', 'float' => $value === null || $value === '' ? null : (float) $value,
            'boolean' => $value === true || $value === 'true' || $value === '1' || $value === 1,
            'date' => $value === null || $value === '' ? null : substr((string) $value, 0, 10),
            default => $value,
        };
    }

    /**
     * @param list<array<string, mixed>> $columns
     * @return array<string, mixed>
     */
    private function formatRow(array $row, array $columns): array
    {
        foreach ($columns as $column) {
            $field = (string) ($column['field'] ?? '');
            if ($field === '' || !array_key_exists($field, $row)) {
                continue;
            }
            $type = (string) ($column['type'] ?? 'string');
            if (in_array($type, ['integer'], true) && $row[$field] !== null) {
                $row[$field] = (int) $row[$field];
            }
            if (in_array($type, ['decimal', 'number', 'currency', 'float'], true) && $row[$field] !== null) {
                $row[$field] = (float) $row[$field];
            }
        }

        return $row;
    }

    private function resolveLimit(array $dataset, array $payload): int
    {
        $datasetLimit = max(1, (int) ($dataset['limit'] ?? self::MAX_LIMIT));
        $requested = max(1, (int) ($payload['take'] ?? $payload['pageSize'] ?? $datasetLimit));

        return min($datasetLimit, $requested, self::MAX_LIMIT);
    }

    private function resolveExecutionMode(array $dataset, array $payload): string
    {
        if (($payload['forceLive'] ?? false) === true) {
            return 'live';
        }
        $mode = strtolower((string) ($payload['executionMode'] ?? $dataset['executionMode'] ?? 'live'));

        return in_array($mode, ['live', 'cached', 'auto'], true) ? $mode : 'live';
    }

    private function resolveCacheExpiration(array $dataset): ?\DateTimeImmutable
    {
        $cache = is_array($dataset['cache'] ?? null) ? $dataset['cache'] : [];
        $ttl = (int) ($cache['ttlSeconds'] ?? $dataset['cacheTtlSeconds'] ?? 900);
        if ($ttl <= 0) {
            return null;
        }

        return (new \DateTimeImmutable())->modify('+' . $ttl . ' seconds');
    }

    private function findCache(string $screenId, string $datasetId, string $viewId, string $fingerprint, ?string $tenantId, bool $includeExpired = false): ?array
    {
        if (!$this->cacheTableExists()) {
            return null;
        }
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from(self::CACHE_TABLE)
            ->where('tenant_id = :tenantId')
            ->andWhere('screen_id = :screenId')
            ->andWhere('dataset_id = :datasetId')
            ->andWhere('COALESCE(view_id, \'\') = :viewId')
            ->andWhere('filter_fingerprint = :fingerprint')
            ->setParameter('tenantId', $tenantId ?: $this->permissions->getTenantId())
            ->setParameter('screenId', $screenId)
            ->setParameter('datasetId', $datasetId)
            ->setParameter('viewId', $viewId)
            ->setParameter('fingerprint', $fingerprint)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();
        if (!$row) {
            return null;
        }
        $cache = $this->normalizeCacheRow($row);
        if (!$includeExpired && (($cache['status'] ?? '') !== 'ready' || $this->isCacheExpired($cache))) {
            return null;
        }

        return $cache;
    }

    private function storeCache(string $screenId, string $datasetId, string $viewId, string $fingerprint, string $status, array $payload, ?string $error, ?\DateTimeImmutable $expiresAt, ?string $tenantId): void
    {
        if (!$this->cacheTableExists()) {
            throw new RuntimeHttpException('ANALYTICS_CACHE_TABLE_MISSING', 'Tabela de cache analytics nao encontrada. Execute as migrations.', 500);
        }
        $now = new \DateTimeImmutable();
        $tenant = $tenantId ?: $this->permissions->getTenantId();

        $this->connection->delete(self::CACHE_TABLE, [
            'tenant_id' => $tenant,
            'screen_id' => $screenId,
            'dataset_id' => $datasetId,
            'view_id' => $viewId,
            'filter_fingerprint' => $fingerprint,
        ]);
        $this->connection->insert(self::CACHE_TABLE, [
            'tenant_id' => $tenant,
            'screen_id' => $screenId,
            'dataset_id' => $datasetId,
            'view_id' => $viewId,
            'filter_fingerprint' => $fingerprint,
            'status' => $status,
            'row_count' => count($payload['data'] ?? []),
            'payload' => $payload,
            'metadata' => [
                'generatedAt' => $payload['generatedAt'] ?? null,
                'columns' => $payload['columns'] ?? [],
            ],
            'last_error' => $error,
            'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
            'created_at' => $now->format('Y-m-d H:i:s'),
            'updated_at' => $now->format('Y-m-d H:i:s'),
            'refreshed_at' => $now->format('Y-m-d H:i:s'),
        ], [
            'payload' => Types::JSON,
            'metadata' => Types::JSON,
        ]);
    }

    private function cacheTableExists(): bool
    {
        try {
            return $this->connection->createSchemaManager()->tablesExist([self::CACHE_TABLE]);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeCacheRow(array $row): array
    {
        $payload = $row['payload'] ?? [];
        if (is_string($payload)) {
            $payload = json_decode($payload, true) ?: [];
        }

        return [
            'status' => (string) ($row['status'] ?? ''),
            'payload' => is_array($payload) ? $payload : [],
            'rowCount' => (int) ($row['row_count'] ?? 0),
            'lastError' => $row['last_error'] ?? null,
            'expiresAt' => $this->formatDateTime($row['expires_at'] ?? null),
            'refreshedAt' => $this->formatDateTime($row['refreshed_at'] ?? null),
        ];
    }

    private function isCacheExpired(array $cache): bool
    {
        if (empty($cache['expiresAt'])) {
            return false;
        }

        return new \DateTimeImmutable((string) $cache['expiresAt']) <= new \DateTimeImmutable();
    }

    private function formatDateTime(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }

        return (new \DateTimeImmutable((string) $value))->format(DATE_ATOM);
    }

    private function applySoftDeleteFilter(QueryBuilder $qb, array $definition, string $alias): void
    {
        $condition = $this->joinSoftDeleteCondition($definition, $alias);
        if ($condition !== null) {
            $qb->andWhere($condition);
        }
    }

    private function joinSoftDeleteCondition(array $definition, string $alias): ?string
    {
        $config = is_array($definition['softDelete'] ?? null) ? $definition['softDelete'] : [];
        if (($config['enabled'] ?? false) !== true || empty($config['deletedAtColumn'])) {
            return null;
        }

        return $alias . '.' . $this->quote((string) $config['deletedAtColumn']) . ' IS NULL';
    }

    private function applySubscriberIsolation(QueryBuilder $qb, array $definition, string $alias, ?string $tenantId, string $parameter): void
    {
        $condition = $this->joinSubscriberIsolationCondition($definition, $alias, $tenantId, $parameter, $qb);
        if ($condition !== null) {
            $qb->andWhere($condition);
        }
    }

    private function joinSubscriberIsolationCondition(array $definition, string $alias, ?string $tenantId, string $parameter, QueryBuilder $qb): ?string
    {
        $config = is_array($definition['subscriberIsolation'] ?? null) ? $definition['subscriberIsolation'] : [];
        if (($config['enabled'] ?? false) !== true || empty($config['column'])) {
            return null;
        }
        $qb->setParameter($parameter, $tenantId ?: $this->permissions->getTenantId());

        return $alias . '.' . $this->quote((string) $config['column']) . ' = :' . $parameter;
    }

    private function assertNoFreeCode(mixed $value, string $path): void
    {
        if (!is_array($value)) {
            return;
        }
        foreach ($value as $key => $item) {
            $normalizedKey = strtolower((string) $key);
            if (in_array($normalizedKey, ['sql', 'rawsql', 'wheresql', 'having', 'select', 'expression', 'template', 'eval', 'script', 'javascript', 'function'], true)) {
                throw new RuntimeHttpException('ANALYTICS_FREE_CODE_BLOCKED', 'Analytics nao aceita SQL, JS ou template livre nos metadados.', 422, [
                    'path' => $path . '.' . (string) $key,
                ]);
            }
            $this->assertNoFreeCode($item, $path . '.' . (string) $key);
        }
    }

    private function isNumericType(string $type): bool
    {
        return in_array($type, ['integer', 'decimal', 'number', 'currency', 'float'], true);
    }

    private function safeCode(string $value): string
    {
        $value = trim($value);
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) ? $value : '';
    }

    private function safeAlias(string $value): string
    {
        $value = trim(str_replace('.', '_', $value));
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) ? $value : 'campo';
    }

    private function normalizeOptionalCode(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return '';
        }

        return preg_match('/^[A-Za-z0-9_.:-]+$/', $value) ? $value : '';
    }

    private function quote(string $identifier): string
    {
        return $this->connection->quoteSingleIdentifier($identifier);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }
        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $dataset
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $result
     * @param array<string, mixed> $extra
     */
    private function recordAudit(array $definition, array $dataset, array $payload, array $result, string $resultSource, string $fingerprint, ?string $tenantId, array $extra = []): void
    {
        if (!$this->auditStore instanceof RuntimeAnalyticsAuditStore || !$this->auditStore->isEnabled()) {
            return;
        }

        $auditConfig = is_array($definition['analytics']['audit'] ?? null) ? $definition['analytics']['audit'] : [];
        $datasetAuditConfig = is_array($dataset['audit'] ?? null) ? $dataset['audit'] : [];
        if (($auditConfig['enabled'] ?? true) === false || ($datasetAuditConfig['enabled'] ?? true) === false) {
            return;
        }
        if ($resultSource === 'cache_hit' && ($datasetAuditConfig['includeCacheHits'] ?? $auditConfig['includeCacheHits'] ?? true) !== true) {
            return;
        }

        $runtime = is_array($result['_runtime']['analytics'] ?? null) ? $result['_runtime']['analytics'] : [];
        $requestPayload = [
            'datasetId' => $payload['datasetId'] ?? $dataset['id'] ?? null,
            'viewId' => $payload['viewId'] ?? null,
            'filter' => $payload['filter'] ?? null,
            'filters' => $payload['filters'] ?? null,
            'parameters' => $payload['parameters'] ?? null,
            'sort' => $payload['sort'] ?? null,
            'take' => $payload['take'] ?? null,
            'skip' => $payload['skip'] ?? null,
            'executionMode' => $payload['executionMode'] ?? ($dataset['executionMode'] ?? null),
        ];

        $this->auditStore->record([
            'tenantId' => $tenantId ?: $this->permissions->getTenantId(),
            'userId' => $this->permissions->getUserId(),
            'sessionId' => $this->permissions->getSessionId(),
            'screenId' => (string) ($definition['screenId'] ?? ''),
            'datasetId' => (string) ($dataset['id'] ?? ''),
            'viewId' => $this->normalizeOptionalCode($payload['viewId'] ?? null) ?: null,
            'executionMode' => (string) ($runtime['executionMode'] ?? $this->resolveExecutionMode($dataset, $payload)),
            'resultSource' => $resultSource,
            'filterFingerprint' => $fingerprint,
            'filters' => $payload['filter'] ?? $payload['filters'] ?? null,
            'parameters' => $payload['parameters'] ?? null,
            'sort' => $payload['sort'] ?? null,
            'requestPayload' => $requestPayload,
            'resultColumns' => $result['columns'] ?? [],
            'resultRows' => $result['data'] ?? [],
            'rowCount' => count($result['data'] ?? []),
            'totalCount' => (int) ($result['total'] ?? count($result['data'] ?? [])),
            'errorMessage' => $extra['errorMessage'] ?? null,
            'metadata' => [
                'programId' => $definition['program']['id'] ?? null,
                'pageType' => $definition['pageType'] ?? 'analytics',
                'aggregated' => $runtime['aggregated'] ?? null,
                'limit' => $runtime['limit'] ?? null,
                'skip' => $runtime['skip'] ?? null,
                'auditConfig' => [
                    'screenEnabled' => $auditConfig['enabled'] ?? true,
                    'datasetEnabled' => $datasetAuditConfig['enabled'] ?? true,
                    'includeCacheHits' => $datasetAuditConfig['includeCacheHits'] ?? $auditConfig['includeCacheHits'] ?? true,
                ],
                'extra' => $extra,
            ],
            'consultedAt' => new \DateTimeImmutable(),
        ]);
    }
}
