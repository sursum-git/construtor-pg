<?php

namespace App\ImportExport;

use App\Entity\BuilderEntity;
use App\Entity\ImportExportMapping;
use App\Repository\BuilderEntityRepository;
use App\Repository\ImportExportMappingRepository;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeApiEntityActionService;
use App\Runtime\RuntimeEntityActionService;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeOdooEntityActionService;
use App\Runtime\RuntimeTransactionService;
use Doctrine\ORM\EntityManagerInterface;

class ImportExportMappingService
{
    public function __construct(
        private readonly ImportExportMappingRepository $mappings,
        private readonly BuilderEntityRepository $entities,
        private readonly EntityManagerInterface $entityManager,
        private readonly RuntimeEntityActionService $runtimeEntities,
        private readonly RuntimeApiEntityActionService $runtimeApis,
        private readonly RuntimeOdooEntityActionService $runtimeOdoo,
        private readonly RuntimeTransactionService $transactions,
        private readonly PermissionResolver $permissions,
    ) {
    }

    public function list(): array
    {
        $this->assertAdminRead();
        $items = $this->mappings->findBy([], ['code' => 'ASC']);

        return [
            'items' => array_map(fn (ImportExportMapping $item): array => $this->summaryPayload($item), $items),
        ];
    }

    public function get(string $code): array
    {
        $this->assertAdminRead();
        $mapping = $this->mappings->findOneBy(['code' => trim($code)]);
        if (!$mapping) {
            throw new RuntimeHttpException('IMPORT_EXPORT_MAPPING_NOT_FOUND', 'Mapeamento nao encontrado.', 404, [
                'code' => $code,
            ]);
        }

        return [
            'mapping' => $this->mappingPayload($mapping),
        ];
    }

    public function save(array $payload): array
    {
        $this->assertAdminWrite();
        $config = $this->normalizeMappingPayload($payload);
        $mapping = $this->mappings->findOneBy(['code' => $config['code']]) ?? new ImportExportMapping();
        $mapping
            ->setCode($config['code'])
            ->setName($config['name'])
            ->setDirection($config['direction'])
            ->setTargetType($config['targetType'])
            ->setTargetCode($config['targetCode'])
            ->setFormat($config['format'])
            ->setStatus($config['status'])
            ->setMapping($config['mapping']);
        $this->entityManager->persist($mapping);
        $this->entityManager->flush();

        return [
            'mapping' => $this->mappingPayload($mapping),
        ];
    }

    public function preview(array $payload): array
    {
        $this->assertAdminRead();
        $config = $this->resolveRequestConfig($payload);
        $result = $this->executeNormalized($config, false, true, is_array($payload['parameters'] ?? null) ? $payload['parameters'] : []);

        return $result;
    }

    public function execute(array $payload): array
    {
        $this->assertAdminWrite();
        $config = $this->resolveRequestConfig($payload);

        return $this->executeNormalized($config, true, false, is_array($payload['parameters'] ?? null) ? $payload['parameters'] : []);
    }

    private function executeNormalized(array $config, bool $persist, bool $preview, array $parameters): array
    {
        $startedAt = microtime(true);
        $normalized = $config['mapping'];
        $sources = $this->loadSources($normalized, $parameters, $preview);
        $destination = $normalized['destination'];
        $diagnostics = [];
        $counts = [
            'read' => array_sum(array_map(static fn (array $source): int => count($source['records']), $sources)),
            'written' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        if ($destination['type'] === 'entity') {
            $entityResult = $this->executeEntityDestination($normalized, $sources, $persist, $preview, $counts, $diagnostics);
            $result = [
                'type' => 'entity',
                'records' => $entityResult['records'],
            ];
        } else {
            $fileResult = $this->executeFileDestination($normalized, $sources, $preview);
            $result = [
                'type' => 'file',
                'fileName' => $fileResult['fileName'],
                'mimeType' => $fileResult['mimeType'],
                'content' => $fileResult['content'],
                'contentBase64' => base64_encode($fileResult['content']),
                'previewText' => mb_substr($fileResult['content'], 0, 4000),
            ];
        }

        $metadata = [
            'mappingCode' => $config['code'],
            'direction' => $config['direction'],
            'format' => $config['format'],
            'persist' => $persist,
            'preview' => $preview,
            'durationMs' => (int) round((microtime(true) - $startedAt) * 1000),
            'counts' => $counts,
        ];
        $this->transactions->log(
            'import_export.' . $config['code'],
            $preview ? 'Preview de importacao/exportacao executado.' : 'Importacao/exportacao executada.',
            metadata: $metadata
        );

        return [
            'mapping' => [
                'code' => $config['code'],
                'name' => $config['name'],
                'direction' => $config['direction'],
                'format' => $config['format'],
            ],
            'counts' => $counts,
            'diagnostics' => $diagnostics,
            'result' => $result,
        ];
    }

    private function executeEntityDestination(array $mapping, array $sources, bool $persist, bool $preview, array &$counts, array &$diagnostics): array
    {
        if (count($sources) !== 1) {
            throw new RuntimeHttpException('IMPORT_EXPORT_ENTITY_MULTI_SOURCE_NOT_SUPPORTED', 'Destino entidade nesta etapa aceita apenas uma fonte por mapeamento.', 422);
        }
        $source = $sources[0];
        $destination = $mapping['destination'];
        $records = [];
        foreach ($source['records'] as $index => $record) {
            try {
                $mapped = $this->mapRecord($record, $mapping['fieldMappings']);
                $action = $this->resolveDestinationAction($destination, $mapped);
                if ($preview || !$persist) {
                    $records[] = [
                        'action' => $action['operation'],
                        'record' => $action['record'],
                    ];
                    continue;
                }
                $saved = $this->executeDestinationOperation($destination['entityCode'], $action['operation'], $action['record']);
                $records[] = [
                    'action' => $action['operation'],
                    'record' => $saved,
                ];
                $counts['written']++;
            } catch (\Throwable $error) {
                $counts['errors']++;
                $diagnostics[] = [
                    'level' => 'error',
                    'message' => 'Falha ao processar registro ' . ($index + 1) . ': ' . $error->getMessage(),
                ];
                if (($mapping['options']['stopOnError'] ?? true) === true) {
                    throw $error;
                }
                $counts['skipped']++;
            }
        }

        return ['records' => $records];
    }

    private function executeFileDestination(array $mapping, array $sources, bool $preview): array
    {
        $destination = $mapping['destination'];
        return match ($destination['fileFormat']) {
            'csv' => $this->buildCsv($mapping, $sources, $preview),
            'txt_layout' => $this->buildTxtLayout($mapping, $sources, $preview),
            default => throw new RuntimeHttpException('IMPORT_EXPORT_FILE_FORMAT_NOT_SUPPORTED', 'Formato de arquivo nao suportado nesta etapa.', 422, [
                'fileFormat' => $destination['fileFormat'],
            ]),
        };
    }

    private function buildCsv(array $mapping, array $sources, bool $preview): array
    {
        if (count($sources) !== 1) {
            throw new RuntimeHttpException('IMPORT_EXPORT_CSV_MULTI_SOURCE_NOT_SUPPORTED', 'CSV nesta etapa aceita apenas uma fonte por mapeamento.', 422);
        }
        $destination = $mapping['destination'];
        $columns = is_array($destination['columns'] ?? null) ? $destination['columns'] : [];
        if (!$columns) {
            throw new RuntimeHttpException('IMPORT_EXPORT_CSV_COLUMNS_REQUIRED', 'Informe as colunas do CSV.', 422);
        }
        $delimiter = (string) ($destination['delimiter'] ?? ';');
        $quote = (string) ($destination['quote'] ?? '"');
        $includeHeader = ($destination['includeHeader'] ?? true) !== false;
        $lines = [];
        if ($includeHeader) {
            $lines[] = implode($delimiter, array_map(function (array $column) use ($quote): string {
                return $this->escapeDelimited((string) ($column['header'] ?? $column['targetName'] ?? $column['sourcePath'] ?? ''), $quote);
            }, $columns));
        }
        foreach ($sources[0]['records'] as $record) {
            $row = [];
            foreach ($columns as $column) {
                $value = $this->extractValue($record, (string) ($column['sourcePath'] ?? ''));
                $value = $this->applyTransforms($value, $column['transforms'] ?? []);
                $row[] = $this->escapeDelimited($this->stringifyValue($value), $quote);
            }
            $lines[] = implode($delimiter, $row);
            if ($preview && count($lines) >= 11) {
                break;
            }
        }

        return [
            'fileName' => $this->resolveFileName($destination['fileNamePattern'] ?? 'export.csv', 'csv'),
            'mimeType' => 'text/csv; charset=UTF-8',
            'content' => implode("\r\n", $lines) . "\r\n",
        ];
    }

    private function buildTxtLayout(array $mapping, array $sources, bool $preview): array
    {
        $destination = $mapping['destination'];
        $recordLayouts = is_array($destination['recordLayouts'] ?? null) ? $destination['recordLayouts'] : [];
        if (!$recordLayouts) {
            throw new RuntimeHttpException('IMPORT_EXPORT_TXT_LAYOUT_REQUIRED', 'Informe os leiautes do TXT.', 422);
        }
        $sourceMap = [];
        foreach ($sources as $source) {
            $sourceMap[$source['alias']] = $source;
        }
        $lines = [];
        $limit = $preview ? max(1, (int) ($mapping['options']['previewLimit'] ?? 20)) : PHP_INT_MAX;
        $this->renderTxtLayoutNodes($recordLayouts, $sourceMap, null, $lines, $limit);

        $lineBreak = (string) ($destination['lineBreak'] ?? "\r\n");
        return [
            'fileName' => $this->resolveFileName($destination['fileNamePattern'] ?? 'export.txt', 'txt'),
            'mimeType' => 'text/plain; charset=UTF-8',
            'content' => implode($lineBreak, $lines) . $lineBreak,
        ];
    }

    private function renderTxtLayoutNodes(array $layouts, array $sourceMap, ?array $parentRecord, array &$lines, int $limit): void
    {
        foreach ($layouts as $layout) {
            if (count($lines) >= $limit) {
                return;
            }
            if (!is_array($layout)) {
                continue;
            }
            $this->renderTxtLayoutNode($layout, $sourceMap, $parentRecord, $lines, $limit);
        }
    }

    private function renderTxtLayoutNode(array $layout, array $sourceMap, ?array $parentRecord, array &$lines, int $limit): void
    {
        $nodeType = strtolower(trim((string) ($layout['nodeType'] ?? 'record')));
        $children = is_array($layout['children'] ?? null) ? $layout['children'] : [];

        if ($nodeType === 'group') {
            $this->renderTxtLayoutNodes($children, $sourceMap, $parentRecord, $lines, $limit);
            return;
        }

        if ($nodeType === 'totalizer') {
            $records = $this->resolveTxtNodeRecords($layout, $sourceMap, $parentRecord);
            $summaryRecord = $this->buildTxtSummaryRecord($layout, $records, $parentRecord);
            if (!empty($layout['fields'])) {
                $lines[] = $this->renderTxtLine($summaryRecord, $layout);
            }
            if (count($lines) >= $limit) {
                return;
            }
            if ($children) {
                $this->renderTxtLayoutNodes($children, $sourceMap, $summaryRecord, $lines, $limit);
            }
            return;
        }

        $records = $this->resolveTxtNodeRecords($layout, $sourceMap, $parentRecord);
        foreach ($records as $record) {
            if (count($lines) >= $limit) {
                return;
            }
            $decorated = $this->decorateTxtRecord($record, $parentRecord);
            if (!empty($layout['fields'])) {
                $lines[] = $this->renderTxtLine($decorated, $layout);
            }
            if (count($lines) >= $limit) {
                return;
            }
            if ($children) {
                $this->renderTxtLayoutNodes($children, $sourceMap, $decorated, $lines, $limit);
            }
        }
    }

    private function resolveTxtNodeRecords(array $layout, array $sourceMap, ?array $parentRecord): array
    {
        $alias = trim((string) ($layout['sourceAlias'] ?? $layout['sourceEntityCode'] ?? ''));
        if ($alias === '') {
            throw new RuntimeHttpException('IMPORT_EXPORT_TXT_SOURCE_NOT_FOUND', 'No de leiaute TXT precisa informar sourceAlias.', 422, [
                'recordType' => $layout['recordType'] ?? null,
                'nodeType' => $layout['nodeType'] ?? 'record',
            ]);
        }
        $source = $sourceMap[$alias] ?? null;
        if (!$source) {
            throw new RuntimeHttpException('IMPORT_EXPORT_TXT_SOURCE_NOT_FOUND', 'Leiaute TXT referencia uma fonte inexistente.', 422, [
                'sourceAlias' => $alias,
            ]);
        }

        $records = is_array($source['records'] ?? null) ? $source['records'] : [];
        $linkBy = is_array($layout['linkBy'] ?? null) ? $layout['linkBy'] : [];
        if (!$linkBy) {
            return $records;
        }

        return array_values(array_filter($records, fn (array $record): bool => $this->matchTxtLinkBy($record, $parentRecord, $linkBy)));
    }

    private function matchTxtLinkBy(array $record, ?array $parentRecord, array $linkBy): bool
    {
        if (!$parentRecord) {
            return false;
        }

        foreach ($linkBy as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $parentPath = trim((string) ($rule['parentPath'] ?? ''));
            $childField = trim((string) ($rule['childField'] ?? $rule['sourcePath'] ?? ''));
            if ($parentPath === '' || $childField === '') {
                continue;
            }
            $operator = strtolower(trim((string) ($rule['operator'] ?? 'eq')));
            $parentValue = $this->extractValue($parentRecord, $parentPath);
            $childValue = $this->extractValue($record, $childField);
            if (!$this->compareTxtValues($childValue, $parentValue, $operator)) {
                return false;
            }
        }

        return true;
    }

    private function compareTxtValues(mixed $left, mixed $right, string $operator): bool
    {
        return match ($operator) {
            'eq' => $left == $right,
            'neq' => $left != $right,
            'contains' => str_contains(mb_strtolower($this->stringifyValue($left)), mb_strtolower($this->stringifyValue($right))),
            'startswith' => str_starts_with(mb_strtolower($this->stringifyValue($left)), mb_strtolower($this->stringifyValue($right))),
            'gt' => $left > $right,
            'gte' => $left >= $right,
            'lt' => $left < $right,
            'lte' => $left <= $right,
            default => $left == $right,
        };
    }

    private function decorateTxtRecord(array $record, ?array $parentRecord): array
    {
        if ($parentRecord === null) {
            return $record;
        }

        $record['_parent'] = $parentRecord;
        return $record;
    }

    private function buildTxtSummaryRecord(array $layout, array $records, ?array $parentRecord): array
    {
        $aggregates = is_array($layout['aggregates'] ?? null) ? $layout['aggregates'] : [];
        $summary = [
            'count' => count($records),
        ];

        foreach ($aggregates as $aggregate) {
            if (!is_array($aggregate)) {
                continue;
            }
            $name = trim((string) ($aggregate['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $type = strtolower(trim((string) ($aggregate['type'] ?? 'count')));
            $sourcePath = trim((string) ($aggregate['sourcePath'] ?? ''));
            $summary[$name] = match ($type) {
                'count' => count($records),
                'sum' => $this->sumTxtRecords($records, $sourcePath),
                default => null,
            };
        }

        return [
            '_summary' => $summary,
            '_parent' => $parentRecord,
        ];
    }

    private function sumTxtRecords(array $records, string $sourcePath): float
    {
        if ($sourcePath === '') {
            return 0.0;
        }
        $sum = 0.0;
        foreach ($records as $record) {
            $value = $this->extractValue($record, $sourcePath);
            if (is_numeric($value)) {
                $sum += (float) $value;
            }
        }
        return $sum;
    }

    private function renderTxtLine(array $record, array $layout): string
    {
        $mode = strtolower(trim((string) ($layout['lineMode'] ?? 'fixed')));
        $fields = is_array($layout['fields'] ?? null) ? $layout['fields'] : [];
        if ($mode === 'delimited') {
            $separator = (string) ($layout['separator'] ?? ';');
            $items = [];
            foreach ($fields as $field) {
                if (!is_array($field)) {
                    continue;
                }
                $value = $this->extractValue($record, (string) ($field['sourcePath'] ?? ''));
                $value = $this->applyTransforms($value, $field['transforms'] ?? []);
                if (array_key_exists('constant', $field)) {
                    $value = $field['constant'];
                }
                $items[] = $this->stringifyValue($value);
            }
            return implode($separator, $items);
        }

        $cursor = 1;
        $line = '';
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $length = max(0, (int) ($field['length'] ?? 0));
            if ($length <= 0) {
                throw new RuntimeHttpException('IMPORT_EXPORT_TXT_FIELD_LENGTH_REQUIRED', 'Campo de leiaute posicional precisa informar length.', 422);
            }
            $start = max(1, (int) ($field['start'] ?? $cursor));
            $value = array_key_exists('constant', $field)
                ? $field['constant']
                : $this->applyTransforms($this->extractValue($record, (string) ($field['sourcePath'] ?? '')), $field['transforms'] ?? []);
            $text = $this->normalizeFixedWidthText(
                $this->stringifyValue($value),
                $length,
                strtolower(trim((string) ($field['align'] ?? 'left'))),
                mb_substr((string) ($field['padChar'] ?? ' '), 0, 1) ?: ' ',
            );
            $line = $this->applyFixedWidthSegment($line, $text, $start, $length);
            $cursor = $start + $length;
        }

        return $line;
    }

    private function normalizeFixedWidthText(string $text, int $length, string $align, string $padChar): string
    {
        if (mb_strlen($text) > $length) {
            $text = mb_substr($text, 0, $length);
        }
        $missing = $length - mb_strlen($text);
        if ($missing <= 0) {
            return $text;
        }

        $padding = str_repeat($padChar, $missing);
        if ($align === 'right') {
            return $padding . $text;
        }

        return $text . $padding;
    }

    private function applyFixedWidthSegment(string $line, string $text, int $start, int $length): string
    {
        $lineLength = mb_strlen($line);
        if ($start > $lineLength + 1) {
            $line .= str_repeat(' ', $start - ($lineLength + 1));
        }

        $prefix = mb_substr($line, 0, max(0, $start - 1));
        $suffixStart = $start - 1 + $length;
        $suffix = $lineLength > $suffixStart ? mb_substr($line, $suffixStart) : '';

        return $prefix . $text . $suffix;
    }

    private function loadSources(array $mapping, array $parameters, bool $preview): array
    {
        $sources = is_array($mapping['sources'] ?? null) && $mapping['sources']
            ? $mapping['sources']
            : (isset($mapping['source']) ? [$mapping['source']] : []);
        if (!$sources) {
            throw new RuntimeHttpException('IMPORT_EXPORT_SOURCE_REQUIRED', 'Informe pelo menos uma fonte no mapeamento.', 422);
        }
        $loaded = [];
        foreach ($sources as $source) {
            if (!is_array($source)) {
                continue;
            }
            $loaded[] = $this->loadSource($source, $parameters, $preview);
        }
        return $loaded;
    }

    private function loadSource(array $source, array $parameters, bool $preview): array
    {
        if (($source['type'] ?? 'entity') !== 'entity') {
            throw new RuntimeHttpException('IMPORT_EXPORT_SOURCE_TYPE_NOT_SUPPORTED', 'Fonte suportada nesta etapa: entity.', 422);
        }
        $entityCode = trim((string) ($source['entityCode'] ?? ''));
        $alias = trim((string) ($source['alias'] ?? $entityCode));
        if ($entityCode === '') {
            throw new RuntimeHttpException('IMPORT_EXPORT_SOURCE_ENTITY_REQUIRED', 'Fonte precisa informar entityCode.', 422);
        }
        $entity = $this->findEntity($entityCode);
        $mode = strtolower(trim((string) ($source['mode'] ?? 'list')));
        $limit = max(1, min(500, (int) ($source['limit'] ?? ($preview ? 20 : 200))));
        if ($mode === 'single') {
            $recordId = $source['recordId'] ?? $parameters[$alias . '_id'] ?? $parameters['recordId'] ?? null;
            if ($recordId === null || $recordId === '') {
                $read = $this->readEntityRecords($entity, [
                    'take' => 1,
                    'skip' => 0,
                ]);
                $record = $read[0] ?? null;
                $records = $record ? [$record] : [];
            } else {
                $records = [$this->getEntityRecord($entity, $recordId)];
            }
        } else {
            $records = $this->readEntityRecords($entity, [
                'take' => $limit,
                'skip' => 0,
                'filter' => $source['filter'] ?? null,
                'sort' => $source['sort'] ?? [],
            ]);
        }

        return [
            'alias' => $alias,
            'entityCode' => $entityCode,
            'entityType' => $entity->getEntityType(),
            'records' => $records,
        ];
    }

    private function readEntityRecords(BuilderEntity $entity, array $payload): array
    {
        $response = match ($this->entityRuntimeType($entity)) {
            'persistence' => $this->runtimeEntities->handle('admin.import-export', 'read', [
                'entityCode' => $entity->getCode(),
                'operation' => 'read',
            ], ['entityCode' => $entity->getCode()] + $payload),
            'api' => $this->runtimeApis->handle('admin.import-export', 'read', [
                'entityCode' => $entity->getCode(),
                'operation' => 'read',
            ], ['entityCode' => $entity->getCode()] + $payload),
            'odoo' => $this->runtimeOdoo->handle('admin.import-export', 'read', [
                'entityCode' => $entity->getCode(),
                'operation' => 'read',
            ], ['entityCode' => $entity->getCode()] + $payload),
            default => throw new RuntimeHttpException('IMPORT_EXPORT_ENTITY_TYPE_NOT_SUPPORTED', 'Tipo de entidade nao suportado nesta etapa.', 422, [
                'entityCode' => $entity->getCode(),
                'entityType' => $entity->getEntityType(),
            ]),
        };

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    private function getEntityRecord(BuilderEntity $entity, mixed $recordId): array
    {
        return match ($this->entityRuntimeType($entity)) {
            'persistence' => $this->runtimeEntities->handle('admin.import-export', 'get', [
                'entityCode' => $entity->getCode(),
                'operation' => 'get',
            ], ['entityCode' => $entity->getCode(), 'id' => $recordId]),
            'api' => $this->runtimeApis->handle('admin.import-export', 'get', [
                'entityCode' => $entity->getCode(),
                'operation' => 'get',
            ], ['entityCode' => $entity->getCode(), 'id' => $recordId]),
            'odoo' => $this->runtimeOdoo->handle('admin.import-export', 'get', [
                'entityCode' => $entity->getCode(),
                'operation' => 'get',
            ], ['entityCode' => $entity->getCode(), 'id' => $recordId]),
            default => throw new RuntimeHttpException('IMPORT_EXPORT_ENTITY_TYPE_NOT_SUPPORTED', 'Tipo de entidade nao suportado nesta etapa.', 422),
        };
    }

    private function executeDestinationOperation(string $entityCode, string $operation, array $record): array
    {
        $entity = $this->findEntity($entityCode);
        return match ($this->entityRuntimeType($entity)) {
            'persistence' => $this->runtimeEntities->handle('admin.import-export', $operation, [
                'entityCode' => $entityCode,
                'operation' => $operation,
            ], ['entityCode' => $entityCode, 'record' => $record] + $record),
            'api' => $this->runtimeApis->handle('admin.import-export', $operation, [
                'entityCode' => $entityCode,
                'operation' => $operation,
            ], ['entityCode' => $entityCode, 'record' => $record] + $record),
            'odoo' => throw new RuntimeHttpException('IMPORT_EXPORT_ODOO_WRITE_NOT_SUPPORTED', 'Destino Odoo ainda nao suporta gravacao por mapeamento.', 422, [
                'entityCode' => $entityCode,
            ]),
            default => throw new RuntimeHttpException('IMPORT_EXPORT_ENTITY_TYPE_NOT_SUPPORTED', 'Tipo de entidade de destino nao suportado nesta etapa.', 422),
        };
    }

    private function resolveDestinationAction(array $destination, array $mappedRecord): array
    {
        $operation = strtolower(trim((string) ($destination['operation'] ?? 'create')));
        if ($operation === 'upsert') {
            $matchBy = is_array($destination['matchBy'] ?? null) ? $destination['matchBy'] : [];
            if (!$matchBy) {
                throw new RuntimeHttpException('IMPORT_EXPORT_MATCH_BY_REQUIRED', 'Operacao upsert exige matchBy.', 422);
            }
            $existing = $this->findDestinationRecordByMatch($destination['entityCode'], $mappedRecord, $matchBy);
            if ($existing) {
                $record = $mappedRecord;
                $primaryKey = $this->findEntityPrimaryKey($destination['entityCode']);
                $record[$primaryKey] = $existing[$primaryKey] ?? $existing['id'] ?? null;
                return ['operation' => 'update', 'record' => $record];
            }
            return ['operation' => 'create', 'record' => $mappedRecord];
        }

        return ['operation' => $operation, 'record' => $mappedRecord];
    }

    private function findDestinationRecordByMatch(string $entityCode, array $mappedRecord, array $matchBy): ?array
    {
        $filters = [];
        foreach ($matchBy as $item) {
            if (!is_array($item)) {
                continue;
            }
            $targetField = trim((string) ($item['targetField'] ?? ''));
            $sourcePath = trim((string) ($item['sourcePath'] ?? $targetField));
            if ($targetField === '') {
                continue;
            }
            $value = $this->extractValue($mappedRecord, $sourcePath);
            $filters[] = [
                'field' => $targetField,
                'operator' => 'eq',
                'value' => $value,
            ];
        }
        if (!$filters) {
            return null;
        }
        $entity = $this->findEntity($entityCode);
        $records = $this->readEntityRecords($entity, [
            'take' => 1,
            'skip' => 0,
            'filter' => [
                'logic' => 'and',
                'filters' => $filters,
            ],
        ]);
        return $records[0] ?? null;
    }

    private function mapRecord(array $record, array $fieldMappings): array
    {
        $mapped = [];
        foreach ($fieldMappings as $item) {
            if (!is_array($item)) {
                continue;
            }
            $targetPath = trim((string) ($item['targetPath'] ?? ''));
            if ($targetPath === '') {
                continue;
            }
            $value = array_key_exists('constant', $item)
                ? $item['constant']
                : $this->extractValue($record, (string) ($item['sourcePath'] ?? ''));
            $value = $this->applyTransforms($value, $item['transforms'] ?? []);
            $this->assignValue($mapped, $targetPath, $value);
        }
        return $mapped;
    }

    private function applyTransforms(mixed $value, mixed $transforms): mixed
    {
        if (!is_array($transforms)) {
            return $value;
        }
        foreach ($transforms as $transform) {
            if (is_string($transform)) {
                $transform = ['type' => $transform];
            }
            if (!is_array($transform)) {
                continue;
            }
            $type = strtolower(trim((string) ($transform['type'] ?? '')));
            $value = match ($type) {
                'trim' => is_string($value) ? trim($value) : $value,
                'upper' => is_string($value) ? mb_strtoupper($value) : $value,
                'lower' => is_string($value) ? mb_strtolower($value) : $value,
                'constant' => $transform['value'] ?? null,
                'concat' => $this->transformConcat($transform, $value),
                'date_format' => $this->transformDateFormat($value, (string) ($transform['format'] ?? 'Y-m-d')),
                'number_format' => $this->transformNumberFormat($value, $transform),
                'pad_left' => str_pad($this->stringifyValue($value), (int) ($transform['length'] ?? 0), (string) ($transform['padChar'] ?? '0'), STR_PAD_LEFT),
                'pad_right' => str_pad($this->stringifyValue($value), (int) ($transform['length'] ?? 0), (string) ($transform['padChar'] ?? ' '), STR_PAD_RIGHT),
                default => $value,
            };
        }
        return $value;
    }

    private function transformConcat(array $transform, mixed $fallback): string
    {
        $parts = is_array($transform['parts'] ?? null) ? $transform['parts'] : [];
        if (!$parts) {
            return $this->stringifyValue($fallback);
        }
        $buffer = '';
        foreach ($parts as $part) {
            if (is_string($part)) {
                $buffer .= $part;
                continue;
            }
            if (!is_array($part)) {
                continue;
            }
            if (array_key_exists('constant', $part)) {
                $buffer .= $this->stringifyValue($part['constant']);
                continue;
            }
            $buffer .= $this->stringifyValue($part['value'] ?? '');
        }
        return $buffer;
    }

    private function transformDateFormat(mixed $value, string $format): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }
        try {
            return (new \DateTimeImmutable((string) $value))->format($format);
        } catch (\Throwable) {
            return $value;
        }
    }

    private function transformNumberFormat(mixed $value, array $transform): mixed
    {
        if (!is_numeric($value)) {
            return $value;
        }
        return number_format(
            (float) $value,
            max(0, (int) ($transform['decimals'] ?? 2)),
            (string) ($transform['decimalSeparator'] ?? ','),
            (string) ($transform['thousandSeparator'] ?? '.')
        );
    }

    private function extractValue(array $record, string $path): mixed
    {
        $normalized = trim($path);
        if ($normalized === '' || $normalized === '$') {
            return $record;
        }
        $current = $record;
        foreach (explode('.', $normalized) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }
        return $current;
    }

    private function assignValue(array &$target, string $path, mixed $value): void
    {
        $segments = explode('.', trim($path));
        $cursor = &$target;
        foreach ($segments as $index => $segment) {
            if ($segment === '') {
                continue;
            }
            if ($index === count($segments) - 1) {
                $cursor[$segment] = $value;
                return;
            }
            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor = &$cursor[$segment];
        }
    }

    private function escapeDelimited(string $value, string $quote): string
    {
        $escaped = str_replace($quote, $quote . $quote, $value);
        return $quote . $escaped . $quote;
    }

    private function stringifyValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private function resolveFileName(mixed $pattern, string $extension): string
    {
        $name = trim((string) $pattern);
        if ($name === '') {
            $name = 'export.' . $extension;
        }
        if (!str_contains($name, '.')) {
            $name .= '.' . $extension;
        }
        return $name;
    }

    private function resolveRequestConfig(array $payload): array
    {
        $code = trim((string) ($payload['code'] ?? ''));
        $hasInlineMapping = is_array($payload['mapping'] ?? null) && $payload['mapping'] !== [];
        if ($code !== '' && !$hasInlineMapping) {
            $mapping = $this->mappings->findOneBy(['code' => $code]);
            if (!$mapping) {
                throw new RuntimeHttpException('IMPORT_EXPORT_MAPPING_NOT_FOUND', 'Mapeamento nao encontrado.', 404, [
                    'code' => $code,
                ]);
            }
            return [
                'code' => $mapping->getCode(),
                'name' => $mapping->getName(),
                'direction' => $mapping->getDirection(),
                'format' => $mapping->getFormat(),
                'mapping' => $this->normalizeMappingBody($mapping->getMapping(), $mapping->getDirection(), $mapping->getFormat()),
            ];
        }

        return $this->normalizeMappingPayload($payload);
    }

    private function normalizeMappingPayload(array $payload): array
    {
        $code = $this->safeCode((string) ($payload['code'] ?? ''));
        $name = trim((string) ($payload['name'] ?? ''));
        $direction = strtolower(trim((string) ($payload['direction'] ?? 'export')));
        $targetType = strtolower(trim((string) ($payload['targetType'] ?? 'entity')));
        $targetCode = trim((string) ($payload['targetCode'] ?? ''));
        $format = strtolower(trim((string) ($payload['format'] ?? 'entity_copy')));
        $status = strtolower(trim((string) ($payload['status'] ?? 'draft')));
        $mappingBody = is_array($payload['mapping'] ?? null) ? $payload['mapping'] : [];

        if ($code === '' || $name === '') {
            throw new RuntimeHttpException('IMPORT_EXPORT_REQUIRED_FIELDS', 'Informe codigo e nome do mapeamento.', 422);
        }
        if (!in_array($direction, ['import', 'export'], true)) {
            throw new RuntimeHttpException('IMPORT_EXPORT_DIRECTION_INVALID', 'Direcao do mapeamento invalida.', 422);
        }
        if (!in_array($targetType, ['entity', 'file'], true)) {
            throw new RuntimeHttpException('IMPORT_EXPORT_TARGET_TYPE_INVALID', 'Tipo de destino invalido.', 422);
        }
        if (!in_array($format, ['entity_copy', 'api_json', 'csv', 'txt_layout'], true)) {
            throw new RuntimeHttpException('IMPORT_EXPORT_FORMAT_INVALID', 'Formato do mapeamento invalido nesta etapa.', 422, [
                'format' => $format,
            ]);
        }
        if (!in_array($status, ['draft', 'active', 'inactive'], true)) {
            $status = 'draft';
        }

        return [
            'code' => $code,
            'name' => $name,
            'direction' => $direction,
            'targetType' => $targetType,
            'targetCode' => $targetCode,
            'format' => $format,
            'status' => $status,
            'mapping' => $this->normalizeMappingBody($mappingBody, $direction, $format, $targetType, $targetCode),
        ];
    }

    private function normalizeMappingBody(array $mapping, string $direction, string $format, ?string $targetType = null, ?string $targetCode = null): array
    {
        $sources = is_array($mapping['sources'] ?? null) && $mapping['sources']
            ? $mapping['sources']
            : (isset($mapping['source']) ? [$mapping['source']] : []);
        if (!$sources) {
            throw new RuntimeHttpException('IMPORT_EXPORT_SOURCE_REQUIRED', 'Informe ao menos uma fonte.', 422);
        }
        $normalizedSources = array_map(fn (array $source): array => $this->normalizeSource($source), array_values(array_filter($sources, 'is_array')));
        $destination = is_array($mapping['destination'] ?? null) ? $mapping['destination'] : [];
        if ($targetType !== null && !isset($destination['type'])) {
            $destination['type'] = $targetType;
        }
        if ($targetCode !== null && !isset($destination['entityCode']) && !isset($destination['fileNamePattern'])) {
            if (($destination['type'] ?? $targetType) === 'entity') {
                $destination['entityCode'] = $targetCode;
            }
        }
        $normalizedDestination = $this->normalizeDestination($destination, $format);
        $fieldMappings = array_values(array_filter(array_map(fn ($item) => $this->normalizeFieldMapping($item), is_array($mapping['fieldMappings'] ?? null) ? $mapping['fieldMappings'] : [])));
        $options = is_array($mapping['options'] ?? null) ? $mapping['options'] : [];

        if ($normalizedDestination['type'] === 'entity' && !$fieldMappings) {
            throw new RuntimeHttpException('IMPORT_EXPORT_FIELD_MAPPINGS_REQUIRED', 'Destino entidade exige fieldMappings.', 422);
        }

        return [
            'direction' => $direction,
            'sources' => $normalizedSources,
            'destination' => $normalizedDestination,
            'fieldMappings' => $fieldMappings,
            'options' => [
                'batchSize' => max(1, min(500, (int) ($options['batchSize'] ?? 100))),
                'skipErrors' => ($options['skipErrors'] ?? false) === true,
                'stopOnError' => ($options['stopOnError'] ?? true) !== false,
                'previewLimit' => max(1, min(100, (int) ($options['previewLimit'] ?? 20))),
            ],
        ];
    }

    private function normalizeSource(array $source): array
    {
        $type = strtolower(trim((string) ($source['type'] ?? 'entity')));
        $entityCode = trim((string) ($source['entityCode'] ?? ''));
        $mode = strtolower(trim((string) ($source['mode'] ?? 'list')));
        $alias = trim((string) ($source['alias'] ?? $entityCode));
        if ($type !== 'entity' || $entityCode === '') {
            throw new RuntimeHttpException('IMPORT_EXPORT_SOURCE_INVALID', 'Fonte invalida. Use type=entity e entityCode.', 422);
        }
        if (!in_array($mode, ['list', 'single'], true)) {
            throw new RuntimeHttpException('IMPORT_EXPORT_SOURCE_MODE_INVALID', 'Modo da fonte invalido.', 422);
        }
        return [
            'type' => 'entity',
            'entityCode' => $entityCode,
            'alias' => $alias,
            'mode' => $mode,
            'recordId' => $source['recordId'] ?? null,
            'filter' => $source['filter'] ?? null,
            'sort' => is_array($source['sort'] ?? null) ? $source['sort'] : [],
            'limit' => max(1, min(500, (int) ($source['limit'] ?? 200))),
        ];
    }

    private function normalizeDestination(array $destination, string $format): array
    {
        $type = strtolower(trim((string) ($destination['type'] ?? ($format === 'csv' || $format === 'txt_layout' ? 'file' : 'entity'))));
        if ($type === 'entity') {
            $entityCode = trim((string) ($destination['entityCode'] ?? ''));
            if ($entityCode === '') {
                throw new RuntimeHttpException('IMPORT_EXPORT_DESTINATION_ENTITY_REQUIRED', 'Destino entidade exige entityCode.', 422);
            }
            return [
                'type' => 'entity',
                'entityCode' => $entityCode,
                'operation' => strtolower(trim((string) ($destination['operation'] ?? 'create'))),
                'matchBy' => is_array($destination['matchBy'] ?? null) ? $destination['matchBy'] : [],
            ];
        }

        return [
            'type' => 'file',
            'fileFormat' => $format,
            'fileNamePattern' => trim((string) ($destination['fileNamePattern'] ?? '')),
            'delimiter' => (string) ($destination['delimiter'] ?? ';'),
            'quote' => (string) ($destination['quote'] ?? '"'),
            'includeHeader' => ($destination['includeHeader'] ?? true) !== false,
            'columns' => is_array($destination['columns'] ?? null) ? $destination['columns'] : [],
            'lineBreak' => (string) ($destination['lineBreak'] ?? "\r\n"),
            'layoutMode' => strtolower(trim((string) ($destination['layoutMode'] ?? 'flat'))),
            'recordLayouts' => array_values(array_filter(array_map(
                fn ($layout) => $this->normalizeTxtLayoutNode($layout),
                is_array($destination['recordLayouts'] ?? null) ? $destination['recordLayouts'] : []
            ))),
        ];
    }

    private function normalizeTxtLayoutNode(mixed $layout): ?array
    {
        if (!is_array($layout)) {
            return null;
        }

        $nodeType = strtolower(trim((string) ($layout['nodeType'] ?? 'record')));
        if (!in_array($nodeType, ['record', 'group', 'totalizer'], true)) {
            $nodeType = 'record';
        }

        $normalized = [
            'nodeType' => $nodeType,
            'recordType' => trim((string) ($layout['recordType'] ?? '')),
            'label' => trim((string) ($layout['label'] ?? '')),
            'sourceAlias' => trim((string) ($layout['sourceAlias'] ?? $layout['sourceEntityCode'] ?? '')),
            'lineMode' => strtolower(trim((string) ($layout['lineMode'] ?? 'fixed'))),
            'separator' => (string) ($layout['separator'] ?? ';'),
            'fields' => array_values(array_filter(array_map(
                fn ($field) => $this->normalizeTxtLayoutField($field),
                is_array($layout['fields'] ?? null) ? $layout['fields'] : []
            ))),
            'children' => array_values(array_filter(array_map(
                fn ($child) => $this->normalizeTxtLayoutNode($child),
                is_array($layout['children'] ?? null) ? $layout['children'] : []
            ))),
            'linkBy' => array_values(array_filter(array_map(
                fn ($rule) => $this->normalizeTxtLinkRule($rule),
                is_array($layout['linkBy'] ?? null) ? $layout['linkBy'] : []
            ))),
            'aggregates' => array_values(array_filter(array_map(
                fn ($aggregate) => $this->normalizeTxtAggregate($aggregate),
                is_array($layout['aggregates'] ?? null) ? $layout['aggregates'] : []
            ))),
        ];

        if ($nodeType !== 'group' && $normalized['sourceAlias'] === '') {
            throw new RuntimeHttpException('IMPORT_EXPORT_TXT_SOURCE_REQUIRED', 'Registro TXT precisa informar sourceAlias.', 422, [
                'recordType' => $normalized['recordType'],
            ]);
        }

        return $normalized;
    }

    private function normalizeTxtLayoutField(mixed $field): ?array
    {
        if (!is_array($field)) {
            return null;
        }

        $normalized = [
            'sourcePath' => trim((string) ($field['sourcePath'] ?? '')),
            'transforms' => is_array($field['transforms'] ?? null) ? $field['transforms'] : [],
            'length' => isset($field['length']) ? (int) $field['length'] : null,
            'start' => isset($field['start']) ? (int) $field['start'] : null,
            'align' => strtolower(trim((string) ($field['align'] ?? 'left'))),
            'padChar' => (string) ($field['padChar'] ?? ' '),
        ];
        if (array_key_exists('constant', $field)) {
            $normalized['constant'] = $field['constant'];
        }

        if ($normalized['sourcePath'] === '' && !array_key_exists('constant', $normalized)) {
            return null;
        }

        return $normalized;
    }

    private function normalizeTxtLinkRule(mixed $rule): ?array
    {
        if (!is_array($rule)) {
            return null;
        }
        $parentPath = trim((string) ($rule['parentPath'] ?? ''));
        $childField = trim((string) ($rule['childField'] ?? $rule['sourcePath'] ?? ''));
        if ($parentPath === '' || $childField === '') {
            return null;
        }

        return [
            'parentPath' => $parentPath,
            'childField' => $childField,
            'operator' => strtolower(trim((string) ($rule['operator'] ?? 'eq'))),
        ];
    }

    private function normalizeTxtAggregate(mixed $aggregate): ?array
    {
        if (!is_array($aggregate)) {
            return null;
        }
        $name = trim((string) ($aggregate['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        return [
            'name' => $name,
            'type' => strtolower(trim((string) ($aggregate['type'] ?? 'count'))),
            'sourcePath' => trim((string) ($aggregate['sourcePath'] ?? '')),
        ];
    }

    private function normalizeFieldMapping(mixed $item): ?array
    {
        if (!is_array($item)) {
            return null;
        }
        $targetPath = trim((string) ($item['targetPath'] ?? ''));
        if ($targetPath === '') {
            return null;
        }
        $normalized = [
            'sourcePath' => trim((string) ($item['sourcePath'] ?? '')),
            'targetPath' => $targetPath,
            'transforms' => is_array($item['transforms'] ?? null) ? $item['transforms'] : [],
        ];
        if (array_key_exists('constant', $item)) {
            $normalized['constant'] = $item['constant'];
        }

        return $normalized;
    }

    private function mappingPayload(ImportExportMapping $mapping): array
    {
        return [
            'id' => $mapping->getId(),
            'code' => $mapping->getCode(),
            'name' => $mapping->getName(),
            'direction' => $mapping->getDirection(),
            'targetType' => $mapping->getTargetType(),
            'targetCode' => $mapping->getTargetCode(),
            'format' => $mapping->getFormat(),
            'status' => $mapping->getStatus(),
            'mapping' => $mapping->getMapping(),
        ];
    }

    private function summaryPayload(ImportExportMapping $mapping): array
    {
        return [
            'code' => $mapping->getCode(),
            'name' => $mapping->getName(),
            'direction' => $mapping->getDirection(),
            'targetType' => $mapping->getTargetType(),
            'targetCode' => $mapping->getTargetCode(),
            'format' => $mapping->getFormat(),
            'status' => $mapping->getStatus(),
        ];
    }

    private function findEntity(string $entityCode): BuilderEntity
    {
        $entity = $this->entities->findOneBy(['code' => trim($entityCode)]);
        if (!$entity instanceof BuilderEntity) {
            throw new RuntimeHttpException('IMPORT_EXPORT_ENTITY_NOT_FOUND', 'Entidade nao encontrada para o mapeamento.', 422, [
                'entityCode' => $entityCode,
            ]);
        }
        return $entity;
    }

    private function entityRuntimeType(BuilderEntity $entity): string
    {
        if ($entity->getEntityType() !== 'api') {
            return 'persistence';
        }
        $apiSource = is_array($entity->getMetadata()['apiSource'] ?? null) ? $entity->getMetadata()['apiSource'] : [];
        if (($apiSource['providerType'] ?? '') === 'odoo') {
            return 'odoo';
        }
        return 'api';
    }

    private function findEntityPrimaryKey(string $entityCode): string
    {
        $entity = $this->findEntity($entityCode);
        foreach ($entity->getFields() as $field) {
            if ($field->isPrimaryKey()) {
                return $field->getCode();
            }
        }
        return 'id';
    }

    private function assertAdminRead(): void
    {
        if (!$this->permissions->hasPermission('admin.read')) {
            throw new RuntimeHttpException('ADMIN_FORBIDDEN', 'Voce nao possui permissao para acessar integracoes.', 403);
        }
    }

    private function assertAdminWrite(): void
    {
        if (!$this->permissions->hasPermission('admin.write')) {
            throw new RuntimeHttpException('ADMIN_FORBIDDEN', 'Voce nao possui permissao para alterar integracoes.', 403);
        }
    }

    private function safeCode(string $value): string
    {
        $normalized = strtolower(trim($value));
        return preg_match('/^[a-z0-9_.-]+$/', $normalized) ? $normalized : '';
    }
}
