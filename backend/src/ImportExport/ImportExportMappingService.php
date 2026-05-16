<?php

namespace App\ImportExport;

use App\Entity\ImportExportExecution;
use App\Entity\ImportExportMapping;
use App\Entity\ImportExportMappingVersion;
use App\Entity\ImportExportSchedule;
use App\Repository\ImportExportExecutionRepository;
use App\Repository\ImportExportMappingRepository;
use App\Repository\ImportExportMappingVersionRepository;
use App\Repository\ImportExportScheduleRepository;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeHttpException;
use App\Runtime\StructuralIntegrityService;
use App\Runtime\RuntimeTransactionService;
use Doctrine\ORM\EntityManagerInterface;

class ImportExportMappingService
{
    public function __construct(
        private readonly ImportExportMappingRepository $mappings,
        private readonly ImportExportMappingVersionRepository $versions,
        private readonly ImportExportExecutionRepository $executions,
        private readonly ImportExportScheduleRepository $schedules,
        private readonly EntityManagerInterface $entityManager,
        private readonly ImportExportEncodingHelper $encodingHelper,
        private readonly ImportExportValueMapper $valueMapper,
        private readonly ImportExportTxtLayoutRenderer $txtLayoutRenderer,
        private readonly ImportExportXmlRenderer $xmlRenderer,
        private readonly ImportExportSourceLoader $sourceLoader,
        private readonly ImportExportDestinationWriter $destinationWriter,
        private readonly RuntimeTransactionService $transactions,
        private readonly PermissionResolver $permissions,
        private readonly StructuralIntegrityService $integrity,
    ) {
    }

    public function list(): array
    {
        $this->assertAdminRead();
        $items = $this->mappings->findBy([], ['code' => 'ASC']);
        foreach ($items as $item) {
            $this->integrity->assertImportExportMapping($item);
        }

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
        $this->integrity->assertImportExportMapping($mapping);
        foreach ($this->versions->findByMapping($mapping, 20) as $version) {
            $this->integrity->assertImportExportMappingVersion($version);
        }

        return [
            'mapping' => $this->mappingPayload($mapping),
            'versions' => array_map(fn (ImportExportMappingVersion $version): array => $this->versionPayload($version), $this->versions->findByMapping($mapping, 20)),
            'recentExecutions' => array_map(fn (ImportExportExecution $execution): array => $this->executionPayload($execution), $this->executions->findRecent(10, $mapping->getCode())),
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
        $this->integrity->signImportExportMapping($mapping, ['source' => 'saveImportExportMapping']);
        $this->createMappingVersion($mapping, trim((string) ($payload['changeSummary'] ?? '')) ?: null);
        $this->entityManager->flush();

        return [
            'mapping' => $this->mappingPayload($mapping),
            'versions' => array_map(fn (ImportExportMappingVersion $version): array => $this->versionPayload($version), $this->versions->findByMapping($mapping, 20)),
        ];
    }

    public function listExecutions(array $filters = []): array
    {
        $this->assertAdminRead();
        $mappingCode = trim((string) ($filters['mappingCode'] ?? '')) ?: null;

        return [
            'items' => array_map(fn (ImportExportExecution $execution): array => $this->executionPayload($execution), $this->executions->findRecent(100, $mappingCode)),
        ];
    }

    public function listSchedules(): array
    {
        $this->assertAdminRead();
        return [
            'items' => array_map(fn (ImportExportSchedule $schedule): array => $this->schedulePayload($schedule), $this->schedules->findBy([], ['name' => 'ASC'])),
        ];
    }

    public function saveSchedule(array $payload): array
    {
        $this->assertAdminWrite();
        $schedule = $this->normalizeSchedulePayload($payload);
        $entity = $this->schedules->findOneBy(['code' => $schedule['code']]) ?? new ImportExportSchedule();
        $entity
            ->setCode($schedule['code'])
            ->setName($schedule['name'])
            ->setMappingCode($schedule['mappingCode'])
            ->setFrequency($schedule['frequency'])
            ->setEnabled($schedule['enabled'])
            ->setParameters($schedule['parameters'])
            ->setIntervalMinutes($schedule['intervalMinutes'])
            ->setDailyHour($schedule['dailyHour'])
            ->setDailyMinute($schedule['dailyMinute'])
            ->setNextRunAt($this->computeNextRunAt($schedule, new \DateTimeImmutable()))
            ->setUpdatedAt(new \DateTimeImmutable())
            ->setUpdatedBy($this->permissions->getUserId());
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        $this->integrity->signImportExportSchedule($entity, ['source' => 'saveImportExportSchedule']);
        $this->entityManager->flush();

        return ['schedule' => $this->schedulePayload($entity)];
    }

    public function runDueSchedules(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $executed = [];
        foreach ($this->schedules->findDue($now) as $schedule) {
            if (!$schedule instanceof ImportExportSchedule) {
                continue;
            }
            $status = 'succeeded';
            $config = null;
            try {
                $config = $this->resolveRequestConfig(['code' => $schedule->getMappingCode()]);
                $result = $this->executeNormalized($config, true, false, $schedule->getParameters(), $schedule->getCode());
                $executed[] = [
                    'scheduleCode' => $schedule->getCode(),
                    'mappingCode' => $schedule->getMappingCode(),
                    'counts' => $result['counts'] ?? [],
                ];
            } catch (\Throwable $error) {
                $status = 'failed';
                if (isset($config) && is_array($config)) {
                    $this->recordFailedExecution($config, $schedule->getParameters(), $error, $schedule->getCode());
                }
                $executed[] = [
                    'scheduleCode' => $schedule->getCode(),
                    'mappingCode' => $schedule->getMappingCode(),
                    'error' => $error->getMessage(),
                ];
            }
            $schedule
                ->setLastRunAt($now)
                ->setLastStatus($status)
                ->setNextRunAt($this->computeNextRunAt([
                    'frequency' => $schedule->getFrequency(),
                    'intervalMinutes' => $schedule->getIntervalMinutes(),
                    'dailyHour' => $schedule->getDailyHour(),
                    'dailyMinute' => $schedule->getDailyMinute(),
                ], $now))
                ->setUpdatedAt($now);
            $this->entityManager->persist($schedule);
        }
        $this->entityManager->flush();

        return [
            'executed' => $executed,
            'count' => count($executed),
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
        $parameters = is_array($payload['parameters'] ?? null) ? $payload['parameters'] : [];
        try {
            return $this->executeNormalized($config, true, false, $parameters);
        } catch (\Throwable $error) {
            $this->recordFailedExecution($config, $parameters, $error, null);
            throw $error;
        }
    }

    private function executeNormalized(array $config, bool $persist, bool $preview, array $parameters, ?string $scheduleCode = null): array
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
            'scheduleCode' => $scheduleCode,
        ];
        $this->transactions->log(
            'import_export.' . $config['code'],
            $preview ? 'Preview de importacao/exportacao executado.' : 'Importacao/exportacao executada.',
            metadata: $metadata
        );

        $payload = [
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
        if (!$preview) {
            $this->recordExecution($config, $payload, $parameters, $metadata, $scheduleCode);
        }

        return $payload;
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
            'xml' => $this->buildXml($mapping, $sources, $preview),
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

    private function buildXml(array $mapping, array $sources, bool $preview): array
    {
        return $this->xmlRenderer->build($mapping, $sources, $preview);
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
        if (!in_array($format, ['entity_copy', 'api_json', 'csv', 'txt_layout', 'xml'], true)) {
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
        if ($type === 'file') {
            return $this->normalizeFileSource($source);
        }
        $entityCode = trim((string) ($source['entityCode'] ?? ''));
        $mode = strtolower(trim((string) ($source['mode'] ?? 'list')));
        $alias = trim((string) ($source['alias'] ?? $entityCode));
        if ($type !== 'entity' || $entityCode === '') {
            throw new RuntimeHttpException('IMPORT_EXPORT_SOURCE_INVALID', 'Fonte invalida. Use type=entity e entityCode, ou type=file para XML.', 422);
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

    private function normalizeFileSource(array $source): array
    {
        $fileFormat = strtolower(trim((string) ($source['fileFormat'] ?? 'xml')));
        if ($fileFormat !== 'xml') {
            throw new RuntimeHttpException('IMPORT_EXPORT_SOURCE_FILE_FORMAT_INVALID', 'Fonte file suporta apenas XML nesta etapa.', 422);
        }
        $alias = trim((string) ($source['alias'] ?? 'xml_file'));
        $recordPath = trim((string) ($source['recordPath'] ?? ''));
        if ($recordPath === '') {
            throw new RuntimeHttpException('IMPORT_EXPORT_XML_RECORD_PATH_REQUIRED', 'Fonte XML exige recordPath.', 422);
        }
        $fields = array_values(array_filter(array_map(
            fn ($field) => $this->normalizeXmlSourceField($field),
            is_array($source['fields'] ?? null) ? $source['fields'] : []
        )));
        if (!$fields) {
            throw new RuntimeHttpException('IMPORT_EXPORT_XML_SOURCE_FIELDS_REQUIRED', 'Fonte XML exige ao menos um campo.', 422);
        }

        return [
            'type' => 'file',
            'fileFormat' => 'xml',
            'alias' => $alias,
            'contentParameter' => trim((string) ($source['contentParameter'] ?? 'xmlContent')),
            'recordPath' => $recordPath,
            'fields' => $fields,
            'namespaces' => array_values(array_filter(array_map(
                fn ($namespace) => $this->normalizeXmlNamespace($namespace),
                is_array($source['namespaces'] ?? null) ? $source['namespaces'] : []
            ))),
            'limit' => max(1, min(500, (int) ($source['limit'] ?? 200))),
        ];
    }

    private function normalizeXmlSourceField(mixed $field): ?array
    {
        if (!is_array($field)) {
            return null;
        }
        $targetField = trim((string) ($field['targetField'] ?? $field['name'] ?? ''));
        $xpath = trim((string) ($field['xpath'] ?? $field['sourcePath'] ?? ''));
        if ($targetField === '' || $xpath === '') {
            return null;
        }

        return [
            'targetField' => $targetField,
            'xpath' => $xpath,
            'transforms' => is_array($field['transforms'] ?? null) ? $field['transforms'] : [],
        ];
    }

    private function normalizeDestination(array $destination, string $format): array
    {
        $type = strtolower(trim((string) ($destination['type'] ?? (in_array($format, ['csv', 'txt_layout', 'xml'], true) ? 'file' : 'entity'))));
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
            'rootName' => trim((string) ($destination['rootName'] ?? 'items')),
            'itemName' => trim((string) ($destination['itemName'] ?? 'item')),
            'prettyPrint' => ($destination['prettyPrint'] ?? true) !== false,
            'namespaces' => array_values(array_filter(array_map(
                fn ($namespace) => $this->normalizeXmlNamespace($namespace),
                is_array($destination['namespaces'] ?? null) ? $destination['namespaces'] : []
            ))),
            'rootAttributes' => array_values(array_filter(array_map(
                fn ($attribute) => $this->normalizeXmlAttribute($attribute),
                is_array($destination['rootAttributes'] ?? null) ? $destination['rootAttributes'] : []
            ))),
            'xmlLayouts' => array_values(array_filter(array_map(
                fn ($layout) => $this->normalizeXmlLayoutNode($layout),
                is_array($destination['xmlLayouts'] ?? null) ? $destination['xmlLayouts'] : []
            ))),
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

    private function normalizeXmlNamespace(mixed $item): ?array
    {
        if (!is_array($item)) {
            return null;
        }
        $prefix = preg_replace('/[^A-Za-z0-9_.-]+/', '_', trim((string) ($item['prefix'] ?? ''))) ?: '';
        $uri = trim((string) ($item['uri'] ?? ''));
        if ($prefix === '' || $uri === '') {
            return null;
        }

        return [
            'prefix' => $prefix,
            'uri' => $uri,
        ];
    }

    private function normalizeXmlAttribute(mixed $item): ?array
    {
        if (!is_array($item)) {
            return null;
        }
        $name = trim((string) ($item['name'] ?? ''));
        if ($name === '') {
            return null;
        }
        $normalized = [
            'name' => $name,
            'sourcePath' => trim((string) ($item['sourcePath'] ?? '')),
            'transforms' => is_array($item['transforms'] ?? null) ? $item['transforms'] : [],
        ];
        if (array_key_exists('constant', $item)) {
            $normalized['constant'] = $item['constant'];
        }
        if ($normalized['sourcePath'] === '' && !array_key_exists('constant', $normalized)) {
            return null;
        }

        return $normalized;
    }

    private function normalizeXmlField(mixed $item): ?array
    {
        if (!is_array($item)) {
            return null;
        }
        $name = trim((string) ($item['name'] ?? $item['targetName'] ?? ''));
        if ($name === '') {
            return null;
        }
        $normalized = [
            'name' => $name,
            'sourcePath' => trim((string) ($item['sourcePath'] ?? '')),
            'transforms' => is_array($item['transforms'] ?? null) ? $item['transforms'] : [],
        ];
        if (array_key_exists('constant', $item)) {
            $normalized['constant'] = $item['constant'];
        }
        if ($normalized['sourcePath'] === '' && !array_key_exists('constant', $normalized)) {
            return null;
        }

        return $normalized;
    }

    private function normalizeXmlLayoutNode(mixed $layout): ?array
    {
        if (!is_array($layout)) {
            return null;
        }
        $name = trim((string) ($layout['name'] ?? ''));
        if ($name === '') {
            return null;
        }
        $normalized = [
            'name' => $name,
            'label' => trim((string) ($layout['label'] ?? $name)),
            'sourceAlias' => trim((string) ($layout['sourceAlias'] ?? '')),
            'attributes' => array_values(array_filter(array_map(
                fn ($attribute) => $this->normalizeXmlAttribute($attribute),
                is_array($layout['attributes'] ?? null) ? $layout['attributes'] : []
            ))),
            'fields' => array_values(array_filter(array_map(
                fn ($field) => $this->normalizeXmlField($field),
                is_array($layout['fields'] ?? null) ? $layout['fields'] : []
            ))),
            'children' => array_values(array_filter(array_map(
                fn ($child) => $this->normalizeXmlLayoutNode($child),
                is_array($layout['children'] ?? null) ? $layout['children'] : []
            ))),
            'linkBy' => array_values(array_filter(array_map(
                fn ($rule) => $this->normalizeTxtLinkRule($rule),
                is_array($layout['linkBy'] ?? null) ? $layout['linkBy'] : []
            ))),
            'textSourcePath' => trim((string) ($layout['textSourcePath'] ?? '')),
            'textTransforms' => is_array($layout['textTransforms'] ?? null) ? $layout['textTransforms'] : [],
        ];
        if (array_key_exists('textConstant', $layout)) {
            $normalized['textConstant'] = $layout['textConstant'];
        }

        return $normalized;
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

    private function versionPayload(ImportExportMappingVersion $version): array
    {
        return [
            'id' => $version->getId(),
            'versionNumber' => $version->getVersionNumber(),
            'changeSummary' => $version->getChangeSummary(),
            'createdBy' => $version->getCreatedBy(),
            'createdAt' => $version->getCreatedAt()->format(DATE_ATOM),
            'snapshot' => $version->getSnapshot(),
        ];
    }

    private function executionPayload(ImportExportExecution $execution): array
    {
        return [
            'id' => $execution->getId(),
            'mappingCode' => $execution->getMappingCode(),
            'mappingName' => $execution->getMappingName(),
            'direction' => $execution->getDirection(),
            'format' => $execution->getFormat(),
            'mode' => $execution->getMode(),
            'status' => $execution->getStatus(),
            'parameters' => $execution->getParameters(),
            'counts' => $execution->getCounts(),
            'diagnostics' => $execution->getDiagnostics(),
            'resultSummary' => $execution->getResultSummary(),
            'fileName' => $execution->getFileName(),
            'mimeType' => $execution->getMimeType(),
            'durationMs' => $execution->getDurationMs(),
            'scheduleCode' => $execution->getScheduleCode(),
            'createdBy' => $execution->getCreatedBy(),
            'createdAt' => $execution->getCreatedAt()->format(DATE_ATOM),
        ];
    }

    private function schedulePayload(ImportExportSchedule $schedule): array
    {
        return [
            'id' => $schedule->getId(),
            'code' => $schedule->getCode(),
            'name' => $schedule->getName(),
            'mappingCode' => $schedule->getMappingCode(),
            'frequency' => $schedule->getFrequency(),
            'enabled' => $schedule->isEnabled(),
            'parameters' => $schedule->getParameters(),
            'intervalMinutes' => $schedule->getIntervalMinutes(),
            'dailyHour' => $schedule->getDailyHour(),
            'dailyMinute' => $schedule->getDailyMinute(),
            'nextRunAt' => $schedule->getNextRunAt()?->format(DATE_ATOM),
            'lastRunAt' => $schedule->getLastRunAt()?->format(DATE_ATOM),
            'lastStatus' => $schedule->getLastStatus(),
            'updatedBy' => $schedule->getUpdatedBy(),
            'updatedAt' => $schedule->getUpdatedAt()->format(DATE_ATOM),
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

    private function createMappingVersion(ImportExportMapping $mapping, ?string $changeSummary = null): void
    {
        $version = (new ImportExportMappingVersion())
            ->setMapping($mapping)
            ->setVersionNumber($this->versions->findLatestVersionNumber($mapping) + 1)
            ->setSnapshot($this->mappingPayload($mapping))
            ->setChangeSummary($changeSummary)
            ->setCreatedBy($this->permissions->getUserId());
        $this->entityManager->persist($version);
        $this->entityManager->flush();
        $this->integrity->signImportExportMappingVersion($version, ['source' => 'createImportExportMappingVersion']);
    }

    private function recordExecution(array $config, array $payload, array $parameters, array $metadata, ?string $scheduleCode): void
    {
        $mapping = $this->mappings->findOneBy(['code' => $config['code']]);
        $result = is_array($payload['result'] ?? null) ? $payload['result'] : [];
        $summary = [
            'type' => $result['type'] ?? null,
            'recordCount' => is_array($result['records'] ?? null) ? count($result['records']) : null,
            'previewText' => isset($result['previewText']) ? mb_substr((string) $result['previewText'], 0, 4000) : null,
        ];
        $execution = (new ImportExportExecution())
            ->setMapping($mapping instanceof ImportExportMapping ? $mapping : null)
            ->setMappingCode($config['code'])
            ->setMappingName($config['name'])
            ->setDirection($config['direction'])
            ->setFormat($config['format'])
            ->setMode($scheduleCode ? 'scheduled' : 'execute')
            ->setStatus('succeeded')
            ->setParameters($parameters)
            ->setCounts(is_array($payload['counts'] ?? null) ? $payload['counts'] : [])
            ->setDiagnostics(is_array($payload['diagnostics'] ?? null) ? $payload['diagnostics'] : [])
            ->setResultSummary($summary)
            ->setFileName(is_string($result['fileName'] ?? null) ? $result['fileName'] : null)
            ->setMimeType(is_string($result['mimeType'] ?? null) ? $result['mimeType'] : null)
            ->setDurationMs((int) ($metadata['durationMs'] ?? 0))
            ->setScheduleCode($scheduleCode)
            ->setCreatedBy($this->permissions->getUserId());
        $this->entityManager->persist($execution);
        $this->entityManager->flush();
    }

    private function recordFailedExecution(array $config, array $parameters, \Throwable $error, ?string $scheduleCode): void
    {
        $mapping = $this->mappings->findOneBy(['code' => $config['code']]);
        $execution = (new ImportExportExecution())
            ->setMapping($mapping instanceof ImportExportMapping ? $mapping : null)
            ->setMappingCode($config['code'])
            ->setMappingName($config['name'])
            ->setDirection($config['direction'])
            ->setFormat($config['format'])
            ->setMode($scheduleCode ? 'scheduled' : 'execute')
            ->setStatus('failed')
            ->setParameters($parameters)
            ->setCounts(['read' => 0, 'written' => 0, 'skipped' => 0, 'errors' => 1])
            ->setDiagnostics([['level' => 'error', 'message' => $error->getMessage()]])
            ->setResultSummary(['exception' => $error::class])
            ->setScheduleCode($scheduleCode)
            ->setCreatedBy($this->permissions->getUserId());
        $this->entityManager->persist($execution);
        $this->entityManager->flush();
    }

    private function normalizeSchedulePayload(array $payload): array
    {
        $code = $this->safeCode((string) ($payload['code'] ?? ''));
        $name = trim((string) ($payload['name'] ?? ''));
        $mappingCode = trim((string) ($payload['mappingCode'] ?? ''));
        $frequency = strtolower(trim((string) ($payload['frequency'] ?? 'daily')));
        if ($code === '' || $name === '' || $mappingCode === '') {
            throw new RuntimeHttpException('IMPORT_EXPORT_SCHEDULE_REQUIRED_FIELDS', 'Informe codigo, nome e mappingCode do agendamento.', 422);
        }
        if (!in_array($frequency, ['manual', 'interval', 'hourly', 'daily'], true)) {
            throw new RuntimeHttpException('IMPORT_EXPORT_SCHEDULE_FREQUENCY_INVALID', 'Frequencia invalida para o agendamento.', 422);
        }
        if (!$this->mappings->findOneBy(['code' => $mappingCode])) {
            throw new RuntimeHttpException('IMPORT_EXPORT_SCHEDULE_MAPPING_NOT_FOUND', 'Mapping do agendamento nao encontrado.', 422, [
                'mappingCode' => $mappingCode,
            ]);
        }

        return [
            'code' => $code,
            'name' => $name,
            'mappingCode' => $mappingCode,
            'frequency' => $frequency,
            'enabled' => ($payload['enabled'] ?? true) !== false,
            'parameters' => is_array($payload['parameters'] ?? null) ? $payload['parameters'] : [],
            'intervalMinutes' => isset($payload['intervalMinutes']) ? max(1, (int) $payload['intervalMinutes']) : null,
            'dailyHour' => isset($payload['dailyHour']) ? max(0, min(23, (int) $payload['dailyHour'])) : 8,
            'dailyMinute' => isset($payload['dailyMinute']) ? max(0, min(59, (int) $payload['dailyMinute'])) : 0,
        ];
    }

    private function computeNextRunAt(array $schedule, \DateTimeImmutable $base): ?\DateTimeImmutable
    {
        $frequency = strtolower(trim((string) ($schedule['frequency'] ?? 'daily')));
        return match ($frequency) {
            'manual' => null,
            'interval' => $base->modify('+' . max(1, (int) ($schedule['intervalMinutes'] ?? 60)) . ' minutes'),
            'hourly' => $base->modify('+1 hour'),
            default => $base->setTime((int) ($schedule['dailyHour'] ?? 8), (int) ($schedule['dailyMinute'] ?? 0))->modify('+1 day'),
        };
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

    private function safeXmlName(string $value, string $fallback): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9_.-]+/', '_', trim($value)) ?: '';
        if ($normalized === '' || preg_match('/^[0-9.-]/', $normalized)) {
            return $fallback;
        }

        return $normalized;
    }
}
