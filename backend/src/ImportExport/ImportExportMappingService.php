<?php

namespace App\ImportExport;

use App\Entity\ImportExportMapping;
use App\Repository\ImportExportMappingRepository;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeTransactionService;
use Doctrine\ORM\EntityManagerInterface;

class ImportExportMappingService
{
    public function __construct(
        private readonly ImportExportMappingRepository $mappings,
        private readonly EntityManagerInterface $entityManager,
        private readonly ImportExportEncodingHelper $encodingHelper,
        private readonly ImportExportValueMapper $valueMapper,
        private readonly ImportExportTxtLayoutRenderer $txtLayoutRenderer,
        private readonly ImportExportSourceLoader $sourceLoader,
        private readonly ImportExportDestinationWriter $destinationWriter,
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
        $sources = $this->sourceLoader->loadSources($normalized, $parameters, $preview);
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
                'previewText' => mb_substr((string) ($fileResult['previewText'] ?? $fileResult['content']), 0, 4000),
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
                $mapped = $this->valueMapper->mapRecord($record, $mapping['fieldMappings']);
                $action = $this->destinationWriter->resolveDestinationAction($destination, $mapped);
                if ($preview || !$persist) {
                    $records[] = [
                        'action' => $action['operation'],
                        'record' => $action['record'],
                    ];
                    continue;
                }
                $saved = $this->destinationWriter->executeDestinationOperation($destination['entityCode'], $action['operation'], $action['record']);
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
        $encoding = $this->encodingHelper->normalizeEncodingLabel((string) ($destination['encodingLabel'] ?? 'UTF-8'));
        $lines = [];
        if ($includeHeader) {
            $lines[] = implode($delimiter, array_map(function (array $column) use ($quote): string {
                return $this->valueMapper->escapeDelimited((string) ($column['header'] ?? $column['targetName'] ?? $column['sourcePath'] ?? ''), $quote);
            }, $columns));
        }
        foreach ($sources[0]['records'] as $record) {
            $row = [];
            foreach ($columns as $column) {
                $value = $this->valueMapper->extractValue($record, (string) ($column['sourcePath'] ?? ''));
                $value = $this->valueMapper->applyTransforms($value, $column['transforms'] ?? []);
                $row[] = $this->valueMapper->escapeDelimited($this->valueMapper->stringifyValue($value), $quote);
            }
            $lines[] = implode($delimiter, $row);
            if ($preview && count($lines) >= 11) {
                break;
            }
        }

        $contentUtf8 = implode("\r\n", $lines) . "\r\n";
        return [
            'fileName' => $this->valueMapper->resolveFileName($destination['fileNamePattern'] ?? 'export.csv', 'csv'),
            'mimeType' => 'text/csv; charset=' . $encoding,
            'content' => $this->encodingHelper->encodeOutput($contentUtf8, $encoding),
            'previewText' => $contentUtf8,
        ];
    }

    private function buildTxtLayout(array $mapping, array $sources, bool $preview): array
    {
        return $this->txtLayoutRenderer->build($mapping, $sources, $preview);
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
            'encodingLabel' => $this->encodingHelper->normalizeEncodingLabel($destination['encodingLabel'] ?? 'UTF-8'),
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
            'lineMode' => $this->normalizeLineMode($layout['lineMode'] ?? 'fixed'),
            'widthMode' => $this->normalizeWidthMode($layout['widthMode'] ?? 'characters'),
            'encodingLabel' => $this->encodingHelper->normalizeEncodingLabel($layout['encodingLabel'] ?? 'UTF-8'),
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

    private function normalizeLineMode(mixed $value): string
    {
        $lineMode = strtolower(trim((string) $value));
        if (!in_array($lineMode, ['fixed', 'delimited'], true)) {
            throw new RuntimeHttpException('IMPORT_EXPORT_TXT_LINE_MODE_INVALID', 'lineMode do TXT deve ser fixed ou delimited.', 422, [
                'lineMode' => $value,
            ]);
        }

        return $lineMode;
    }

    private function normalizeWidthMode(mixed $value): string
    {
        $widthMode = strtolower(trim((string) $value));
        if (!in_array($widthMode, ['characters', 'bytes'], true)) {
            throw new RuntimeHttpException('IMPORT_EXPORT_TXT_WIDTH_MODE_INVALID', 'widthMode do TXT deve ser characters ou bytes.', 422, [
                'widthMode' => $value,
            ]);
        }

        return $widthMode;
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
