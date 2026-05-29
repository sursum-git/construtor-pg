<?php

namespace App\Runtime;

use App\Repository\ScreenDefinitionRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

class RuntimeReportService
{
    private const MAX_LIMIT = 5000;
    private const SPECIAL_DOCUMENT_KINDS = [
        'danfe',
        'dacte',
        'boleto',
        'label',
        'etiqueta',
        'fiscal_form',
        'fiscal_document',
    ];

    public function __construct(
        private readonly ScreenDefinitionRepository $screens,
        private readonly RuntimeEntityDefinitionResolver $entities,
        private readonly Connection $connection,
        private readonly PermissionResolver $permissions,
        private readonly StructuralIntegrityService $integrity,
        private readonly ProgramCustomizationResolver $customizations,
        private readonly RuntimeAnalyticsService $analytics,
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
            'pageType' => 'report',
            'program' => is_array($definition['program'] ?? null) ? $definition['program'] : [],
            'report' => $definition['report'],
            'runtime' => [
                'report' => [
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
        $report = is_array($definition['report'] ?? null) ? $definition['report'] : [];
        $tenantId = $tenantId ?? $this->permissions->getTenantId();
        $parameters = is_array($payload['parameters'] ?? null) ? $payload['parameters'] : [];
        $generatedAt = (new \DateTimeImmutable())->format(DATE_ATOM);

        $sourceType = strtolower(trim((string) ($report['source']['type'] ?? 'operational')));
        if ($sourceType === 'analytic') {
            $sourceResult = $this->runAnalyticSource($report, $payload, $tenantId);
        } else {
            $sourceResult = $this->runOperationalSource($report, $payload, $tenantId);
        }

        $columns = is_array($sourceResult['columns'] ?? null) ? $sourceResult['columns'] : [];
        $rows = is_array($sourceResult['rows'] ?? null) ? $sourceResult['rows'] : [];
        $groupDefinitions = $this->normalizeGroupDefinitions(is_array($report['layout'] ?? null) ? $report['layout'] : []);
        $groups = $groupDefinitions ? $this->groupRows($rows, $columns, $groupDefinitions) : [];
        $totals = $this->summarizeRows($rows, $columns);
        $summary = [
            ['label' => 'Linhas', 'value' => count($rows)],
            ['label' => 'Fonte', 'value' => $sourceType === 'analytic' ? 'Analytics' : 'Operacional'],
        ];
        foreach ($totals as $field => $value) {
            $column = $this->findColumn($columns, $field);
            if ($column === null) {
                continue;
            }
            $summary[] = [
                'label' => (string) ($column['title'] ?? $column['label'] ?? $field),
                'value' => $value,
                'formattedValue' => $this->formatValue($value, $column['format'] ?? null),
            ];
        }

        $result = [
            'screenId' => $screenId,
            'reportId' => (string) ($definition['program']['id'] ?? $screenId),
            'title' => (string) ($definition['program']['title'] ?? 'Relatorio'),
            'subtitle' => (string) ($definition['program']['subtitle'] ?? ''),
            'sourceType' => $sourceType,
            'rows' => $rows,
            'columns' => $columns,
            'groups' => $groups,
            'totals' => $totals,
            'summary' => $summary,
            'metadata' => [
                ['label' => 'Gerado em', 'value' => $generatedAt],
                ['label' => 'Parametros', 'value' => $this->formatParametersLabel($parameters)],
            ],
            'total' => count($rows),
            'generatedAt' => $generatedAt,
            'outputs' => $this->normalizeOutputs(is_array($report['outputs'] ?? null) ? $report['outputs'] : []),
            '_runtime' => [
                'report' => [
                    'sourceType' => $sourceType,
                    'groupCount' => count($groupDefinitions),
                ],
            ],
        ];
        $authenticity = $this->buildAuthenticity($definition, $report, $result, $payload);
        if ($authenticity !== null) {
            $result['authenticity'] = $authenticity;
            $result['metadata'][] = ['label' => (string) $authenticity['footerLabel'], 'value' => (string) $authenticity['hash']];
            if (trim((string) ($authenticity['verificationUrl'] ?? '')) !== '') {
                $result['metadata'][] = ['label' => 'Conferencia publica', 'value' => (string) $authenticity['verificationUrl']];
            }
        }

        $this->recordAudit($definition, $result, $payload, $sourceType, $tenantId);

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function export(string $screenId, array $payload, ?string $tenantId = null): array
    {
        $format = strtolower(trim((string) ($payload['format'] ?? 'csv')));
        if (!in_array($format, ['csv', 'excel', 'pdf'], true)) {
            throw new RuntimeHttpException('REPORT_EXPORT_FORMAT_NOT_SUPPORTED', 'A exportacao da camada reports aceita CSV, Excel e PDF.', 422, [
                'format' => $format,
            ]);
        }

        $result = $this->run($screenId, $payload, $tenantId);
        $definition = $this->loadDefinition($screenId);
        $report = is_array($definition['report'] ?? null) ? $definition['report'] : [];
        $tenantId = $tenantId ?? $this->permissions->getTenantId();
        $rows = is_array($result['rows'] ?? null) ? $result['rows'] : [];
        $columns = is_array($result['columns'] ?? null) ? $result['columns'] : [];
        $safeName = $this->safeFileName((string) ($result['reportId'] ?? 'relatorio'));

        if ($format === 'excel') {
            $xlsx = $this->rowsToXlsx($result);
            $response = [
                'ok' => true,
                'format' => 'excel',
                'fileName' => $safeName . '.xlsx',
                'contentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'contentBase64' => base64_encode($xlsx),
                'authenticity' => $result['authenticity'] ?? null,
            ];
            $this->recordExportArtifactAudit($definition, $report, $result, $payload, 'excel', $response, $tenantId);

            return $response;
        }
        if ($format === 'pdf') {
            $pdf = $this->resultToPdf($result);
            $response = [
                'ok' => true,
                'format' => 'pdf',
                'fileName' => $safeName . '.pdf',
                'contentType' => 'application/pdf',
                'contentBase64' => base64_encode($pdf),
                'authenticity' => $result['authenticity'] ?? null,
            ];
            $this->recordExportArtifactAudit($definition, $report, $result, $payload, 'pdf', $response, $tenantId);

            return $response;
        }

        $csv = $this->rowsToCsv($columns, $rows);
        $response = [
            'ok' => true,
            'format' => 'csv',
            'fileName' => $safeName . '.csv',
            'contentType' => 'text/csv; charset=utf-8',
            'contentBase64' => base64_encode($csv),
            'authenticity' => $result['authenticity'] ?? null,
        ];
        $this->recordExportArtifactAudit($definition, $report, $result, $payload, 'csv', $response, $tenantId);

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadDefinition(string $screenId): array
    {
        $screen = $this->screens->findPublishedByScreenId($screenId);
        if (!$screen) {
            throw new RuntimeHttpException('REPORT_SCREEN_NOT_FOUND', 'Tela de relatorio nao encontrada.', 404, [
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

        if (($definition['pageType'] ?? '') !== 'report') {
            throw new RuntimeHttpException('REPORT_PAGE_TYPE_INVALID', 'A tela informada nao e do tipo report.', 422, [
                'screenId' => $screenId,
                'pageType' => $definition['pageType'] ?? null,
            ]);
        }
        if (!is_array($definition['report'] ?? null)) {
            throw new RuntimeHttpException('REPORT_DEFINITION_MISSING', 'Definicao de relatorio nao configurada.', 422, [
                'screenId' => $screenId,
            ]);
        }

        $this->assertNoUnsafeMetadata($definition['report']);
        $this->assertSupportedDocumentProfile($definition['report']);

        return $definition;
    }

    /**
     * @param array<string, mixed> $report
     */
    private function assertSupportedDocumentProfile(array $report): void
    {
        $classification = is_array($report['classification'] ?? null) ? $report['classification'] : [];
        $profile = strtolower(trim((string) ($classification['documentProfile'] ?? 'general')));
        $kind = strtolower(trim((string) ($classification['documentKind'] ?? '')));
        if ($profile === 'special' || in_array($kind, self::SPECIAL_DOCUMENT_KINDS, true)) {
            throw new RuntimeHttpException('REPORT_SPECIAL_DOCUMENT_NOT_SUPPORTED', 'Documento especial fica fora da camada reports v1. Use trilha separada.', 422, [
                'documentProfile' => $profile,
                'documentKind' => $kind,
            ]);
        }
    }

    private function assertNoUnsafeMetadata(mixed $value, array $path = []): void
    {
        if (!is_array($value)) {
            if (is_string($value) && preg_match('/<\s*script|javascript\s*:/i', $value)) {
                throw new RuntimeHttpException('REPORT_UNSAFE_METADATA', 'Reports nao aceitam HTML, JS ou template livre nos metadados.', 422, [
                    'path' => implode('.', $path),
                ]);
            }
            return;
        }

        foreach ($value as $key => $item) {
            $normalizedKey = strtolower((string) $key);
            if (in_array($normalizedKey, ['sql', 'template', 'javascript', 'script', 'handler', 'function'], true)) {
                throw new RuntimeHttpException('REPORT_UNSAFE_METADATA', 'Reports nao aceitam HTML, JS ou template livre nos metadados.', 422, [
                    'path' => implode('.', [...$path, (string) $key]),
                ]);
            }
            $this->assertNoUnsafeMetadata($item, [...$path, (string) $key]);
        }
    }

    /**
     * @param array<string, mixed> $report
     * @param array<string, mixed> $payload
     * @return array{rows: array<int, array<string, mixed>>, columns: array<int, array<string, mixed>>}
     */
    private function runOperationalSource(array $report, array $payload, string $tenantId): array
    {
        $source = is_array($report['source'] ?? null) ? $report['source'] : [];
        $entityCode = trim((string) ($source['entityCode'] ?? $payload['entityCode'] ?? ''));
        if ($entityCode === '') {
            throw new RuntimeHttpException('REPORT_ENTITY_REQUIRED', 'Fonte operacional do relatorio exige entityCode.', 422);
        }

        $entity = $this->entities->resolve($entityCode);
        $fieldsConfig = is_array($report['query']['fields'] ?? null) ? $report['query']['fields'] : [];
        $selectedFieldCodes = [];
        foreach ($fieldsConfig as $field) {
            if (!is_array($field)) {
                continue;
            }
            $code = trim((string) ($field['field'] ?? ''));
            if ($code !== '') {
                $selectedFieldCodes[] = $code;
            }
        }
        if (!$selectedFieldCodes) {
            foreach ($entity['fields'] as $code => $field) {
                if (($field['readable'] ?? true) !== false && ($field['virtual'] ?? false) !== true) {
                    $selectedFieldCodes[] = (string) $code;
                }
            }
        }

        $columns = [];
        $qb = $this->connection->createQueryBuilder()
            ->from((string) $entity['quotedTableName'], 'base');

        foreach ($selectedFieldCodes as $code) {
            $field = $entity['fields'][$code] ?? null;
            if (!is_array($field) || ($field['readable'] ?? true) === false || ($field['virtual'] ?? false) === true) {
                continue;
            }
            $columnName = (string) ($field['column'] ?? '');
            if ($columnName === '') {
                continue;
            }
            $qb->addSelect('base.' . $this->quoteIdentifier($columnName) . ' AS ' . $this->quoteIdentifier($code));
            $fieldConfig = $this->findFieldConfig($fieldsConfig, $code);
            $columns[] = [
                'field' => $code,
                'title' => (string) ($fieldConfig['label'] ?? $field['label'] ?? $code),
                'label' => (string) ($fieldConfig['label'] ?? $field['label'] ?? $code),
                'type' => (string) ($fieldConfig['type'] ?? $field['dataType'] ?? 'string'),
                'format' => $fieldConfig['format'] ?? null,
                'align' => $fieldConfig['align'] ?? ($this->isNumericType((string) ($field['dataType'] ?? '')) ? 'right' : 'left'),
                'totalable' => ($fieldConfig['totalable'] ?? false) === true,
            ];
        }

        if (!$columns) {
            throw new RuntimeHttpException('REPORT_FIELDS_NOT_USABLE', 'Nenhum campo legivel foi configurado para o relatorio.', 422, [
                'entityCode' => $entityCode,
            ]);
        }

        $this->applyEntityIsolation($qb, $entity, $tenantId);
        $this->applyReportFilters($qb, $entity, is_array($report['query']['filters'] ?? null) ? $report['query']['filters'] : [], 'static');
        $this->applyParameterFilters($qb, $entity, is_array($report['query']['parameters'] ?? null) ? $report['query']['parameters'] : [], is_array($payload['parameters'] ?? null) ? $payload['parameters'] : []);
        $this->applyReportFilters($qb, $entity, is_array($payload['filters'] ?? null) ? $payload['filters'] : [], 'payload');
        $this->applySort($qb, $entity, is_array($payload['sort'] ?? null) && $payload['sort'] ? $payload['sort'] : (is_array($report['query']['sort'] ?? null) ? $report['query']['sort'] : []));

        $limit = (int) ($payload['limit'] ?? $report['query']['limit'] ?? 200);
        $qb->setMaxResults(max(1, min(self::MAX_LIMIT, $limit)));

        return [
            'rows' => $qb->executeQuery()->fetchAllAssociative(),
            'columns' => $columns,
        ];
    }

    /**
     * @param array<string, mixed> $report
     * @param array<string, mixed> $payload
     * @return array{rows: array<int, array<string, mixed>>, columns: array<int, array<string, mixed>>}
     */
    private function runAnalyticSource(array $report, array $payload, string $tenantId): array
    {
        $source = is_array($report['source'] ?? null) ? $report['source'] : [];
        $analyticsScreenId = trim((string) ($source['analyticsScreenId'] ?? ''));
        $analyticsDatasetId = trim((string) ($source['analyticsDatasetId'] ?? ''));
        if ($analyticsScreenId === '' || $analyticsDatasetId === '') {
            throw new RuntimeHttpException('REPORT_ANALYTIC_SOURCE_INVALID', 'Fonte analitica exige analyticsScreenId e analyticsDatasetId.', 422);
        }

        $result = $this->analytics->run($analyticsScreenId, [
            'datasetId' => $analyticsDatasetId,
            'parameters' => is_array($payload['parameters'] ?? null) ? $payload['parameters'] : [],
            'sort' => is_array($payload['sort'] ?? null) ? $payload['sort'] : [],
            'take' => (int) ($payload['limit'] ?? $report['query']['limit'] ?? 200),
        ], $tenantId);

        $rows = is_array($result['data'] ?? null) ? $result['data'] : [];
        $columns = [];
        foreach ((array) ($result['columns'] ?? []) as $column) {
            if (!is_array($column)) {
                continue;
            }
            $columns[] = [
                'field' => (string) ($column['field'] ?? $column['id'] ?? ''),
                'title' => (string) ($column['title'] ?? $column['label'] ?? $column['field'] ?? ''),
                'label' => (string) ($column['label'] ?? $column['title'] ?? $column['field'] ?? ''),
                'type' => (string) ($column['type'] ?? 'string'),
                'format' => $column['format'] ?? null,
                'align' => $this->isNumericType((string) ($column['type'] ?? '')) ? 'right' : 'left',
                'totalable' => (($column['role'] ?? '') === 'measure'),
            ];
        }

        return [
            'rows' => $rows,
            'columns' => $columns,
        ];
    }

    /**
     * @param array<string, mixed> $entity
     */
    private function applyEntityIsolation(QueryBuilder $qb, array $entity, string $tenantId): void
    {
        $subscriberIsolation = is_array($entity['subscriberIsolation'] ?? null) ? $entity['subscriberIsolation'] : [];
        if (($subscriberIsolation['enabled'] ?? false) === true && !empty($subscriberIsolation['column'])) {
            $column = (string) $subscriberIsolation['column'];
            $qb->andWhere('base.' . $this->quoteIdentifier($column) . ' = :runtimeReportTenantId')
                ->setParameter('runtimeReportTenantId', $tenantId);
        }

        $softDelete = is_array($entity['softDelete'] ?? null) ? $entity['softDelete'] : [];
        if (($softDelete['enabled'] ?? false) === true && !empty($softDelete['deletedAtColumn'])) {
            $qb->andWhere('base.' . $this->quoteIdentifier((string) $softDelete['deletedAtColumn']) . ' IS NULL');
        }
    }

    /**
     * @param array<string, mixed> $entity
     * @param array<int, array<string, mixed>> $filters
     */
    private function applyReportFilters(QueryBuilder $qb, array $entity, array $filters, string $prefix): void
    {
        foreach ($filters as $index => $filter) {
            if (!is_array($filter)) {
                continue;
            }
            $fieldCode = trim((string) ($filter['field'] ?? ''));
            $operator = strtolower(trim((string) ($filter['operator'] ?? 'eq')));
            if ($fieldCode === '' || $operator === '') {
                continue;
            }
            $entityField = $entity['fields'][$fieldCode] ?? null;
            if (!is_array($entityField) || ($entityField['virtual'] ?? false) === true) {
                continue;
            }
            $column = 'base.' . $this->quoteIdentifier((string) $entityField['column']);
            $param = $prefix . $index;
            $value = $filter['value'] ?? null;
            $this->applyWhereClause($qb, $column, $operator, $value, $param);
        }
    }

    /**
     * @param array<string, mixed> $entity
     * @param array<int, array<string, mixed>> $definitions
     * @param array<string, mixed> $values
     */
    private function applyParameterFilters(QueryBuilder $qb, array $entity, array $definitions, array $values): void
    {
        foreach ($definitions as $index => $parameter) {
            if (!is_array($parameter)) {
                continue;
            }
            $fieldCode = trim((string) ($parameter['field'] ?? ''));
            $parameterId = trim((string) ($parameter['id'] ?? $fieldCode));
            if ($fieldCode === '' || $parameterId === '' || !array_key_exists($parameterId, $values)) {
                continue;
            }
            $entityField = $entity['fields'][$fieldCode] ?? null;
            if (!is_array($entityField) || ($entityField['virtual'] ?? false) === true) {
                continue;
            }
            $column = 'base.' . $this->quoteIdentifier((string) $entityField['column']);
            $operator = strtolower(trim((string) ($parameter['operator'] ?? 'eq')));
            $this->applyWhereClause($qb, $column, $operator, $values[$parameterId], 'param' . $index);
        }
    }

    /**
     * @param array<string, mixed> $entity
     * @param array<int, array<string, mixed>> $sorts
     */
    private function applySort(QueryBuilder $qb, array $entity, array $sorts): void
    {
        foreach ($sorts as $sort) {
            if (!is_array($sort)) {
                continue;
            }
            $fieldCode = trim((string) ($sort['field'] ?? ''));
            $entityField = $entity['fields'][$fieldCode] ?? null;
            if (!is_array($entityField) || ($entityField['virtual'] ?? false) === true) {
                continue;
            }
            $dir = strtolower(trim((string) ($sort['dir'] ?? 'asc'))) === 'desc' ? 'DESC' : 'ASC';
            $qb->addOrderBy('base.' . $this->quoteIdentifier((string) $entityField['column']), $dir);
        }
    }

    private function applyWhereClause(QueryBuilder $qb, string $column, string $operator, mixed $value, string $param): void
    {
        $parameterName = 'report_' . preg_replace('/[^A-Za-z0-9_]+/', '_', $param);
        switch ($operator) {
            case 'contains':
                $qb->andWhere($column . ' LIKE :' . $parameterName)->setParameter($parameterName, '%' . (string) $value . '%');
                return;
            case 'startswith':
                $qb->andWhere($column . ' LIKE :' . $parameterName)->setParameter($parameterName, (string) $value . '%');
                return;
            case 'neq':
                $qb->andWhere($column . ' <> :' . $parameterName)->setParameter($parameterName, $value);
                return;
            case 'gt':
                $qb->andWhere($column . ' > :' . $parameterName)->setParameter($parameterName, $value);
                return;
            case 'gte':
                $qb->andWhere($column . ' >= :' . $parameterName)->setParameter($parameterName, $value);
                return;
            case 'lt':
                $qb->andWhere($column . ' < :' . $parameterName)->setParameter($parameterName, $value);
                return;
            case 'lte':
                $qb->andWhere($column . ' <= :' . $parameterName)->setParameter($parameterName, $value);
                return;
            case 'in':
                $items = array_values(array_filter(is_array($value) ? $value : explode(',', (string) $value), static fn (mixed $item): bool => trim((string) $item) !== ''));
                if ($items) {
                    $qb->andWhere($column . ' IN (:' . $parameterName . ')')->setParameter($parameterName, $items, ArrayParameterType::STRING);
                }
                return;
            case 'isnull':
                $qb->andWhere($column . ' IS NULL');
                return;
            case 'notnull':
                $qb->andWhere($column . ' IS NOT NULL');
                return;
            case 'eq':
            default:
                $qb->andWhere($column . ' = :' . $parameterName)->setParameter($parameterName, $value);
                return;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, array<string, mixed>> $columns
     * @return array<int, array<string, mixed>>
     */
    private function groupRows(array $rows, array $columns, array $groupDefinitions, int $level = 0): array
    {
        $groups = [];
        $groupDefinition = $groupDefinitions[$level] ?? null;
        if (!is_array($groupDefinition)) {
            return [];
        }
        $field = (string) ($groupDefinition['field'] ?? '');
        if ($field === '') {
            return [];
        }
        foreach ($rows as $row) {
            $key = (string) ($row[$field] ?? '(vazio)');
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'field' => $field,
                    'key' => $key,
                    'label' => trim((string) ($groupDefinition['label'] ?? '')) !== '' ? (string) $groupDefinition['label'] . ': ' . $key : $key,
                    'level' => $level + 1,
                    'showSubtotal' => ($groupDefinition['showSubtotal'] ?? true) !== false,
                    'pageBreakBefore' => ($groupDefinition['pageBreakBefore'] ?? false) === true,
                    'rows' => [],
                ];
            }
            $groups[$key]['rows'][] = $row;
        }

        foreach ($groups as &$group) {
            $group['rowCount'] = count($group['rows']);
            $group['totals'] = $this->summarizeRows($group['rows'], $columns);
            $children = $this->groupRows($group['rows'], $columns, $groupDefinitions, $level + 1);
            if ($children) {
                $group['children'] = $children;
            }
        }
        unset($group);

        return array_values($groups);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, array<string, mixed>> $columns
     * @return array<string, int|float>
     */
    private function summarizeRows(array $rows, array $columns): array
    {
        $totals = [];
        foreach ($columns as $column) {
            $field = (string) ($column['field'] ?? '');
            if ($field === '' || ($column['totalable'] ?? false) !== true) {
                continue;
            }
            $sum = 0.0;
            $hasValue = false;
            foreach ($rows as $row) {
                $value = $row[$field] ?? null;
                if (is_numeric($value)) {
                    $sum += (float) $value;
                    $hasValue = true;
                }
            }
            if ($hasValue) {
                $totals[$field] = $sum;
            }
        }

        return $totals;
    }

    /**
     * @param array<string, mixed> $layout
     * @return array<int, array<string, mixed>>
     */
    private function normalizeGroupDefinitions(array $layout): array
    {
        $groups = [];
        foreach ((array) ($layout['groups'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $field = trim((string) ($item['field'] ?? ''));
            if ($field === '') {
                continue;
            }
            $groups[] = [
                'field' => $field,
                'label' => trim((string) ($item['label'] ?? '')),
                'showSubtotal' => ($item['showSubtotal'] ?? true) !== false,
                'pageBreakBefore' => ($item['pageBreakBefore'] ?? false) === true,
            ];
            if (count($groups) >= 3) {
                break;
            }
        }

        if (!$groups) {
            $legacyField = trim((string) ($layout['groupField'] ?? ''));
            if ($legacyField !== '') {
                $groups[] = [
                    'field' => $legacyField,
                    'label' => '',
                    'showSubtotal' => true,
                    'pageBreakBefore' => false,
                ];
            }
        }

        return $groups;
    }

    /**
     * @param array<string, mixed> $outputs
     * @return array<string, bool>
     */
    private function normalizeOutputs(array $outputs): array
    {
        $pdfEnabled = ($outputs['pdf'] ?? null) === true || ($outputs['pdfBrowser'] ?? true) !== false;

        return [
            'html' => ($outputs['html'] ?? true) !== false,
            'print' => ($outputs['print'] ?? true) !== false,
            'pdf' => $pdfEnabled,
            'pdfBrowser' => ($outputs['pdfBrowser'] ?? true) !== false,
            'excel' => ($outputs['excel'] ?? true) !== false,
            'csv' => ($outputs['csv'] ?? true) !== false,
        ];
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $report
     * @param array<string, mixed> $result
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private function buildAuthenticity(array $definition, array $report, array $result, array $payload): ?array
    {
        $config = is_array($report['authenticity'] ?? null) ? $report['authenticity'] : [];
        if (($config['enabled'] ?? false) !== true) {
            return null;
        }

        $algorithm = strtolower(trim((string) ($config['algorithm'] ?? 'sha256')));
        if ($algorithm !== 'sha256') {
            $algorithm = 'sha256';
        }

        $canonical = $this->buildAuthenticityCanonicalPayload($definition, $result, $payload);
        $encoded = (string) json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $hash = $algorithm . ':' . hash($algorithm, $encoded);
        $verificationPath = trim((string) ($config['verificationPath'] ?? 'report-authenticity.html'));
        $verificationUrl = $verificationPath !== ''
            ? $verificationPath . (str_contains($verificationPath, '?') ? '&' : '?') . 'hash=' . rawurlencode($hash)
            : '';

        return [
            'enabled' => true,
            'algorithm' => $algorithm,
            'hash' => $hash,
            'shortHash' => substr($hash, 0, 24),
            'footerLabel' => trim((string) ($config['footerLabel'] ?? 'Codigo de autenticidade')) ?: 'Codigo de autenticidade',
            'includeInFooter' => ($config['includeInFooter'] ?? true) !== false,
            'verificationPath' => $verificationPath,
            'verificationUrl' => $verificationUrl,
            'recorded' => $this->auditStore instanceof RuntimeAnalyticsAuditStore && $this->auditStore->isEnabled(),
            'storage' => [
                'storeCanonicalPayload' => ($config['storage']['storeCanonicalPayload'] ?? true) !== false,
                'storeExportArtifact' => ($config['storage']['storeExportArtifact'] ?? false) === true,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $result
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function buildAuthenticityCanonicalPayload(array $definition, array $result, array $payload): array
    {
        return $this->canonicalizeAuthenticityPayload([
            'screenId' => (string) ($definition['screenId'] ?? ''),
            'reportId' => (string) ($result['reportId'] ?? ''),
            'title' => (string) ($result['title'] ?? ''),
            'subtitle' => (string) ($result['subtitle'] ?? ''),
            'sourceType' => (string) ($result['sourceType'] ?? ''),
            'parameters' => $payload['parameters'] ?? [],
            'sort' => $payload['sort'] ?? [],
            'columns' => $result['columns'] ?? [],
            'rows' => $result['rows'] ?? [],
            'groups' => $result['groups'] ?? [],
            'totals' => $result['totals'] ?? [],
        ]);
    }

    private function canonicalizeAuthenticityPayload(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalizeAuthenticityPayload($item), $value);
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalizeAuthenticityPayload($item);
        }

        return $value;
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     * @param array<int, array<string, mixed>> $rows
     */
    private function rowsToCsv(array $columns, array $rows): string
    {
        $lines = [];
        $lines[] = implode(';', array_map(fn (array $column): string => $this->escapeCsv((string) ($column['title'] ?? $column['label'] ?? $column['field'] ?? '')), $columns));
        foreach ($rows as $row) {
            $values = [];
            foreach ($columns as $column) {
                $values[] = $this->escapeCsv($this->stringifyValue($row[$column['field']] ?? null));
            }
            $lines[] = implode(';', $values);
        }

        return "\xEF\xBB\xBF" . implode("\r\n", $lines);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function rowsToXlsx(array $result): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new RuntimeHttpException('REPORT_EXPORT_EXCEL_UNAVAILABLE', 'Exportacao Excel indisponivel neste ambiente.', 500);
        }
        $columns = is_array($result['columns'] ?? null) ? $result['columns'] : [];
        $rows = is_array($result['rows'] ?? null) ? $result['rows'] : [];
        $sheetName = (string) ($result['title'] ?? $result['reportId'] ?? 'Relatorio');

        $tempFile = tempnam(sys_get_temp_dir(), 'report-xlsx-');
        if ($tempFile === false) {
            throw new RuntimeHttpException('REPORT_EXPORT_EXCEL_IO_ERROR', 'Nao foi possivel preparar o arquivo Excel.', 500);
        }
        $xlsxPath = $tempFile . '.xlsx';
        @unlink($tempFile);

        $zip = new \ZipArchive();
        if ($zip->open($xlsxPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeHttpException('REPORT_EXPORT_EXCEL_IO_ERROR', 'Nao foi possivel gerar o arquivo Excel.', 500);
        }

        $zip->addFromString('[Content_Types].xml', $this->xlsxContentTypesXml());
        $zip->addFromString('_rels/.rels', $this->xlsxRootRelationshipsXml());
        $zip->addFromString('xl/workbook.xml', $this->xlsxWorkbookXml($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->xlsxWorkbookRelationshipsXml());
        $zip->addFromString('xl/styles.xml', $this->xlsxStylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->xlsxWorksheetXml($columns, $rows));
        $zip->close();

        $binary = (string) file_get_contents($xlsxPath);
        @unlink($xlsxPath);

        return $binary;
    }

    private function escapeCsv(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    private function stringifyValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'Sim' : 'Nao';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function xlsxContentTypesXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>
XML;
    }

    private function xlsxRootRelationshipsXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;
    }

    private function xlsxWorkbookXml(string $sheetName): string
    {
        $safeSheetName = trim(substr(preg_replace('/[\\\\\\/\\?\\*\\[\\]:]+/', '-', $sheetName) ?: 'Relatorio', 0, 31)) ?: 'Relatorio';
        $escapedSheetName = $this->escapeXml($safeSheetName);
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="{$escapedSheetName}" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML;
    }

    private function xlsxWorkbookRelationshipsXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;
    }

    private function xlsxStylesXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/></font>
  </fonts>
  <fills count="2">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
  </fills>
  <borders count="1">
    <border><left/><right/><top/><bottom/><diagonal/></border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="4">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>
    <xf numFmtId="2" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>
    <xf numFmtId="2" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyNumberFormat="1"/>
  </cellXfs>
</styleSheet>
XML;
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     * @param array<int, array<string, mixed>> $rows
     */
    private function xlsxWorksheetXml(array $columns, array $rows): string
    {
        $totals = $this->summarizeRows($rows, $columns);
        $headerRowIndex = 1;
        $lastDataRowIndex = 1 + count($rows) + ($totals ? 1 : 0);
        $lines = [];
        $lines[] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $lines[] = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $lines[] = '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
        if ($columns) {
            $lines[] = '<cols>';
            foreach ($columns as $columnIndex => $column) {
                $lines[] = '<col min="' . ($columnIndex + 1) . '" max="' . ($columnIndex + 1) . '" width="' . $this->xlsxColumnWidth($column, $rows) . '" customWidth="1"/>';
            }
            $lines[] = '</cols>';
        }
        $lines[] = '<sheetData>';

        $rowIndex = 1;
        $lines[] = '<row r="' . $rowIndex . '">';
        foreach ($columns as $columnIndex => $column) {
            $reference = $this->xlsxCellReference($columnIndex, $rowIndex);
            $value = (string) ($column['title'] ?? $column['label'] ?? $column['field'] ?? '');
            $lines[] = '<c r="' . $reference . '" s="1" t="inlineStr"><is><t>' . $this->escapeXml($value) . '</t></is></c>';
        }
        $lines[] = '</row>';

        foreach ($rows as $row) {
            ++$rowIndex;
            $lines[] = '<row r="' . $rowIndex . '">';
            foreach ($columns as $columnIndex => $column) {
                $reference = $this->xlsxCellReference($columnIndex, $rowIndex);
                $value = $row[$column['field']] ?? null;
                if (is_numeric($value)) {
                    $lines[] = '<c r="' . $reference . '" s="2"><v>' . $this->escapeXml((string) $value) . '</v></c>';
                    continue;
                }
                $lines[] = '<c r="' . $reference . '" t="inlineStr"><is><t>' . $this->escapeXml($this->stringifyValue($value)) . '</t></is></c>';
            }
            $lines[] = '</row>';
        }

        if ($totals) {
            ++$rowIndex;
            $lines[] = '<row r="' . $rowIndex . '">';
            foreach ($columns as $columnIndex => $column) {
                $reference = $this->xlsxCellReference($columnIndex, $rowIndex);
                $field = (string) ($column['field'] ?? '');
                if ($columnIndex === 0) {
                    $lines[] = '<c r="' . $reference . '" s="1" t="inlineStr"><is><t>Total geral</t></is></c>';
                    continue;
                }
                if ($field !== '' && array_key_exists($field, $totals)) {
                    $lines[] = '<c r="' . $reference . '" s="3"><v>' . $this->escapeXml((string) $totals[$field]) . '</v></c>';
                    continue;
                }
                $lines[] = '<c r="' . $reference . '" s="1" t="inlineStr"><is><t></t></is></c>';
            }
            $lines[] = '</row>';
        }

        $lines[] = '</sheetData>';
        if ($columns) {
            $lastColumnReference = $this->xlsxCellReference(count($columns) - 1, 1);
            $lines[] = '<autoFilter ref="A' . $headerRowIndex . ':' . $lastColumnReference . $lastDataRowIndex . '"/>';
        }
        $lines[] = '</worksheet>';

        return implode('', $lines);
    }

    /**
     * @param array<string, mixed> $column
     * @param array<int, array<string, mixed>> $rows
     */
    private function xlsxColumnWidth(array $column, array $rows): float
    {
        $width = (int) ($column['width'] ?? 0);
        if ($width > 0) {
            return max(12, min(48, $width / 8));
        }
        $maxLength = strlen((string) ($column['title'] ?? $column['label'] ?? $column['field'] ?? ''));
        $field = (string) ($column['field'] ?? '');
        foreach (array_slice($rows, 0, 50) as $row) {
            $maxLength = max($maxLength, strlen($this->stringifyValue($row[$field] ?? null)));
        }

        return max(12, min(48, $maxLength + 2));
    }

    private function xlsxCellReference(int $columnIndex, int $rowIndex): string
    {
        return $this->xlsxColumnLetters($columnIndex) . $rowIndex;
    }

    private function xlsxColumnLetters(int $columnIndex): string
    {
        $index = $columnIndex + 1;
        $letters = '';
        while ($index > 0) {
            $remainder = ($index - 1) % 26;
            $letters = chr(65 + $remainder) . $letters;
            $index = (int) floor(($index - 1) / 26);
        }

        return $letters;
    }

    private function xlsxSuggestedWidth(string $value): int
    {
        return max(96, min(320, (int) strlen($value) * 10));
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function quoteIdentifier(string $value): string
    {
        return $this->connection->quoteIdentifier($value);
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @return array<string, mixed>|null
     */
    private function findFieldConfig(array $fields, string $fieldCode): ?array
    {
        foreach ($fields as $field) {
            if (is_array($field) && ($field['field'] ?? null) === $fieldCode) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     * @return array<string, mixed>|null
     */
    private function findColumn(array $columns, string $field): ?array
    {
        foreach ($columns as $column) {
            if (($column['field'] ?? null) === $field) {
                return $column;
            }
        }

        return null;
    }

    private function isNumericType(string $type): bool
    {
        return in_array(strtolower(trim($type)), ['integer', 'decimal', 'number', 'currency', 'float'], true);
    }

    private function safeFileName(string $value): string
    {
        $clean = preg_replace('/[^A-Za-z0-9._-]+/', '-', $value) ?: 'relatorio';
        return trim($clean, '-');
    }

    private function formatValue(mixed $value, mixed $format): string
    {
        if ($value === null || $format === null || $format === '') {
            return (string) $value;
        }
        if (!is_numeric($value)) {
            return (string) $value;
        }
        if ($format === 'c2') {
            return 'R$ ' . number_format((float) $value, 2, ',', '.');
        }
        if ($format === 'n0') {
            return number_format((float) $value, 0, ',', '.');
        }

        return (string) $value;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function formatParametersLabel(array $parameters): string
    {
        if (!$parameters) {
            return 'Sem parametros';
        }

        $items = [];
        foreach ($parameters as $key => $value) {
            $items[] = $key . ': ' . $this->stringifyValue($value);
        }

        return implode(' | ', $items);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function resultToPdf(array $result): string
    {
        $columns = is_array($result['columns'] ?? null) ? $result['columns'] : [];
        $rows = is_array($result['rows'] ?? null) ? $result['rows'] : [];
        $groups = is_array($result['groups'] ?? null) ? $result['groups'] : [];
        if (!$groups && $columns && count($rows) <= 18) {
            return $this->buildVisualTablePdf($result);
        }

        $headerLines = [];
        $headerLines[] = (string) ($result['title'] ?? 'Relatorio');
        if (trim((string) ($result['subtitle'] ?? '')) !== '') {
            $headerLines[] = (string) $result['subtitle'];
        }
        $headerLines[] = 'Gerado em: ' . $this->stringifyValue($result['generatedAt'] ?? '');
        $headerLines[] = ' ';
        $lines = [];
        foreach ((array) ($result['metadata'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $lines[] = (string) ($item['label'] ?? 'Item') . ': ' . $this->stringifyValue($item['value'] ?? '');
        }
        $lines[] = ' ';
        foreach ((array) ($result['summary'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $lines[] = (string) ($item['label'] ?? 'Resumo') . ': ' . $this->stringifyValue($item['formattedValue'] ?? $item['value'] ?? '');
        }
        $lines[] = ' ';

        if ($groups) {
            $this->appendGroupPdfLines($lines, $groups, $columns, 0);
        } else {
            $this->appendTablePdfLines($lines, $columns, is_array($result['rows'] ?? null) ? $result['rows'] : []);
        }

        if (!empty($result['totals']) && is_array($result['totals'])) {
            $lines[] = ' ';
            $lines[] = 'Total geral';
            foreach ($result['totals'] as $field => $value) {
                $column = $this->findColumn($columns, (string) $field);
                $label = (string) ($column['title'] ?? $column['label'] ?? $field);
                $lines[] = '  ' . $label . ': ' . $this->formatValue($value, $column['format'] ?? null);
            }
        }
        if (is_array($result['authenticity'] ?? null) && (($result['authenticity']['includeInFooter'] ?? true) !== false)) {
            $lines[] = ' ';
            $lines[] = (string) ($result['authenticity']['footerLabel'] ?? 'Codigo de autenticidade') . ': ' . (string) ($result['authenticity']['hash'] ?? '');
        }

        return $this->textLinesToPdf($headerLines, $lines);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function buildVisualTablePdf(array $result): string
    {
        $columns = is_array($result['columns'] ?? null) ? $result['columns'] : [];
        $rows = is_array($result['rows'] ?? null) ? $result['rows'] : [];
        $summary = is_array($result['summary'] ?? null) ? $result['summary'] : [];
        $totals = is_array($result['totals'] ?? null) ? $result['totals'] : [];
        $content = [];

        $content[] = '0.2 w';
        $content[] = 'BT /F2 16 Tf 40 805 Td (' . $this->escapePdfText((string) ($result['title'] ?? 'Relatorio')) . ') Tj ET';
        if (trim((string) ($result['subtitle'] ?? '')) !== '') {
            $content[] = 'BT /F1 10 Tf 40 790 Td (' . $this->escapePdfText((string) $result['subtitle']) . ') Tj ET';
        }
        $content[] = '40 780 m 555 780 l S';

        $summaryX = 40;
        foreach (array_slice($summary, 0, 3) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $content[] = $summaryX . ' 735 150 34 re S';
            $content[] = 'BT /F1 8 Tf ' . ($summaryX + 8) . ' 756 Td (' . $this->escapePdfText((string) ($item['label'] ?? 'Resumo')) . ') Tj ET';
            $content[] = 'BT /F2 12 Tf ' . ($summaryX + 8) . ' 742 Td (' . $this->escapePdfText($this->stringifyValue($item['formattedValue'] ?? $item['value'] ?? '')) . ') Tj ET';
            $summaryX += 165;
        }

        $tableTop = 700;
        $tableLeft = 40;
        $tableWidth = 515;
        $rowHeight = 20;
        $columnWidth = $columns ? $tableWidth / count($columns) : $tableWidth;
        $content[] = $tableLeft . ' ' . ($tableTop - $rowHeight) . ' ' . $tableWidth . ' ' . $rowHeight . ' re S';
        foreach ($columns as $index => $column) {
            $x = $tableLeft + ($index * $columnWidth);
            if ($index > 0) {
                $content[] = $x . ' ' . ($tableTop - ($rowHeight * (count($rows) + 1 + ($totals ? 1 : 0)))) . ' m ' . $x . ' ' . $tableTop . ' l S';
            }
            $content[] = 'BT /F2 9 Tf ' . ($x + 4) . ' ' . ($tableTop - 13) . ' Td (' . $this->escapePdfText((string) ($column['title'] ?? $column['field'] ?? '')) . ') Tj ET';
        }

        $y = $tableTop - $rowHeight;
        foreach ($rows as $row) {
            $content[] = $tableLeft . ' ' . ($y - $rowHeight) . ' ' . $tableWidth . ' ' . $rowHeight . ' re S';
            foreach ($columns as $index => $column) {
                $x = $tableLeft + ($index * $columnWidth);
                $value = $this->formatValue($row[$column['field']] ?? null, $column['format'] ?? null);
                $content[] = 'BT /F1 8 Tf ' . ($x + 4) . ' ' . ($y - 13) . ' Td (' . $this->escapePdfText($value) . ') Tj ET';
            }
            $y -= $rowHeight;
        }

        if ($totals) {
            $content[] = $tableLeft . ' ' . ($y - $rowHeight) . ' ' . $tableWidth . ' ' . $rowHeight . ' re S';
            foreach ($columns as $index => $column) {
                $x = $tableLeft + ($index * $columnWidth);
                $field = (string) ($column['field'] ?? '');
                $value = $index === 0 ? 'Total geral' : (array_key_exists($field, $totals) ? $this->formatValue($totals[$field], $column['format'] ?? null) : '');
                $content[] = 'BT /F2 8 Tf ' . ($x + 4) . ' ' . ($y - 13) . ' Td (' . $this->escapePdfText($value) . ') Tj ET';
            }
        }

        if (is_array($result['authenticity'] ?? null) && (($result['authenticity']['includeInFooter'] ?? true) !== false)) {
            $content[] = 'BT /F1 6 Tf 40 25 Td (' . $this->escapePdfText((string) ($result['authenticity']['footerLabel'] ?? 'Codigo de autenticidade') . ': ' . (string) ($result['authenticity']['hash'] ?? '')) . ') Tj ET';
        }
        $content[] = 'BT /F1 8 Tf 500 25 Td (Pagina 1 de 1) Tj ET';

        return $this->singlePagePdf(implode("\n", $content));
    }

    /**
     * @param list<string> $lines
     * @param array<int, array<string, mixed>> $groups
     * @param array<int, array<string, mixed>> $columns
     */
    private function appendGroupPdfLines(array &$lines, array $groups, array $columns, int $indent): void
    {
        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            $prefix = str_repeat('  ', $indent);
            $lines[] = $prefix . (string) ($group['label'] ?? $group['key'] ?? 'Grupo') . ' (' . (int) ($group['rowCount'] ?? 0) . ')';
            if (!empty($group['children']) && is_array($group['children'])) {
                $this->appendGroupPdfLines($lines, $group['children'], $columns, $indent + 1);
            } else {
                $this->appendTablePdfLines($lines, $columns, is_array($group['rows'] ?? null) ? $group['rows'] : [], $indent + 1);
            }
            if (($group['showSubtotal'] ?? true) !== false && !empty($group['totals']) && is_array($group['totals'])) {
                foreach ($group['totals'] as $field => $value) {
                    $column = $this->findColumn($columns, (string) $field);
                    $label = (string) ($column['title'] ?? $column['label'] ?? $field);
                    $lines[] = $prefix . 'Subtotal ' . $label . ': ' . $this->formatValue($value, $column['format'] ?? null);
                }
            }
            $lines[] = ' ';
        }
    }

    /**
     * @param list<string> $lines
     * @param array<int, array<string, mixed>> $columns
     * @param array<int, array<string, mixed>> $rows
     */
    private function appendTablePdfLines(array &$lines, array $columns, array $rows, int $indent = 0): void
    {
        $prefix = str_repeat('  ', $indent);
        $headers = [];
        foreach ($columns as $column) {
            $headers[] = (string) ($column['title'] ?? $column['label'] ?? $column['field'] ?? '');
        }
        if ($headers) {
            $lines[] = $prefix . implode(' | ', $headers);
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $values = [];
            foreach ($columns as $column) {
                $values[] = $this->stringifyValue($row[$column['field']] ?? null);
            }
            $lines[] = $prefix . implode(' | ', $values);
        }
    }

    /**
     * @param list<string> $lines
     */
    private function textLinesToPdf(array $headerLines, array $bodyLines): string
    {
        $pageContents = [];
        $currentPage = [];
        $lineCount = count($headerLines);
        $limitPerPage = 42;
        foreach ($bodyLines as $line) {
            if (!$currentPage) {
                foreach ($headerLines as $headerLine) {
                    $currentPage[] = $headerLine;
                }
            }
            $currentPage[] = $line;
            ++$lineCount;
            if ($lineCount >= $limitPerPage) {
                $pageContents[] = $currentPage;
                $currentPage = [];
                $lineCount = count($headerLines);
            }
        }
        if ($currentPage) {
            $pageContents[] = $currentPage;
        }
        if (!$pageContents) {
            $pageContents[] = array_merge($headerLines, ['Relatorio']);
        }

        $objects = [];
        $pageObjectIds = [];
        $nextObjectId = 3;
        $totalPages = count($pageContents);
        foreach ($pageContents as $pageIndex => $linesPerPage) {
            $pageLines = $linesPerPage;
            $pageLines[] = ' ';
            $pageLines[] = 'Pagina ' . ($pageIndex + 1) . ' de ' . $totalPages;
            $content = "BT /F1 10 Tf 40 800 Td 14 TL ";
            $first = true;
            foreach ($pageLines as $line) {
                if (!$first) {
                    $content .= "T* ";
                }
                $content .= '(' . $this->escapePdfText((string) $line) . ') Tj ';
                $first = false;
            }
            $content .= 'ET';

            $pageObjectId = $nextObjectId++;
            $contentObjectId = $nextObjectId++;
            $pageObjectIds[] = $pageObjectId;
            $objects[$pageObjectId] = $pageObjectId . " 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents " . $contentObjectId . " 0 R /Resources << /Font << /F1 1 0 R >> >> >> endobj\n";
            $objects[$contentObjectId] = $contentObjectId . " 0 obj << /Length " . strlen($content) . " >> stream\n" . $content . "\nendstream endobj\n";
        }
        $objects[1] = "1 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n";
        $objects[2] = "2 0 obj << /Type /Pages /Kids [" . implode(' ', array_map(static fn (int $id): string => $id . ' 0 R', $pageObjectIds)) . "] /Count " . count($pageObjectIds) . " >> endobj\n";
        $catalogObjectId = $nextObjectId++;
        $objects[$catalogObjectId] = $catalogObjectId . " 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n";
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $object;
        }
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . ($catalogObjectId + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($index = 1; $index <= $catalogObjectId; ++$index) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$index] ?? 0);
        }
        $pdf .= "trailer << /Size " . ($catalogObjectId + 1) . " /Root " . $catalogObjectId . " 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    private function singlePagePdf(string $content): string
    {
        $objects = [
            "1 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n",
            "2 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >> endobj\n",
            "3 0 obj << /Type /Pages /Kids [4 0 R] /Count 1 >> endobj\n",
            "4 0 obj << /Type /Page /Parent 3 0 R /MediaBox [0 0 595 842] /Contents 5 0 R /Resources << /Font << /F1 1 0 R /F2 2 0 R >> >> >> endobj\n",
            "5 0 obj << /Length " . strlen($content) . " >> stream\n" . $content . "\nendstream endobj\n",
            "6 0 obj << /Type /Catalog /Pages 3 0 R >> endobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[$index + 1] = strlen($pdf);
            $pdf .= $object;
        }
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 7\n0000000000 65535 f \n";
        for ($index = 1; $index <= 6; ++$index) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$index] ?? 0);
        }
        $pdf .= "trailer << /Size 7 /Root 6 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    private function escapePdfText(string $value): string
    {
        $normalized = preg_replace('/[^\x20-\x7E]/', '?', $value) ?? '';
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $normalized);
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $result
     * @param array<string, mixed> $payload
     */
    private function recordAudit(array $definition, array $result, array $payload, string $sourceType, string $tenantId): void
    {
        if (!$this->auditStore instanceof RuntimeAnalyticsAuditStore || !$this->auditStore->isEnabled()) {
            return;
        }

        $authenticity = is_array($result['authenticity'] ?? null) ? $result['authenticity'] : null;
        $metadata = [
            'auditContext' => 'report',
            'reportId' => $definition['program']['id'] ?? null,
            'reportTitle' => $definition['program']['title'] ?? null,
            'sourceType' => $sourceType,
            'authenticity' => $authenticity,
        ];
        if ($authenticity && (($authenticity['storage']['storeCanonicalPayload'] ?? true) !== false)) {
            $metadata['canonicalPayload'] = $this->buildAuthenticityCanonicalPayload($definition, $result, $payload);
        }

        $this->auditStore->record([
            'tenantId' => $tenantId,
            'userId' => $this->permissions->getUserId(),
            'sessionId' => $this->permissions->getSessionId(),
            'screenId' => (string) ($definition['screenId'] ?? 'report'),
            'datasetId' => (string) ($definition['program']['id'] ?? $definition['screenId'] ?? 'report'),
            'viewId' => trim((string) ($payload['format'] ?? '')),
            'executionMode' => $sourceType,
            'resultSource' => 'report_run',
            'filterFingerprint' => hash('sha256', (string) json_encode([
                'parameters' => $payload['parameters'] ?? [],
                'sort' => $payload['sort'] ?? [],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'rowCount' => (int) ($result['total'] ?? count($result['rows'] ?? [])),
            'totalCount' => (int) ($result['total'] ?? count($result['rows'] ?? [])),
            'filters' => $payload['filters'] ?? [],
            'parameters' => $payload['parameters'] ?? [],
            'sort' => $payload['sort'] ?? [],
            'requestPayload' => $payload,
            'resultColumns' => $result['columns'] ?? [],
            'resultRows' => $result['rows'] ?? [],
            'metadata' => $metadata,
            'consultedAt' => $result['generatedAt'] ?? (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $report
     * @param array<string, mixed> $result
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $exportResponse
     */
    private function recordExportArtifactAudit(array $definition, array $report, array $result, array $payload, string $format, array $exportResponse, string $tenantId): void
    {
        if (!$this->auditStore instanceof RuntimeAnalyticsAuditStore || !$this->auditStore->isEnabled()) {
            return;
        }

        $authenticityConfig = is_array($report['authenticity'] ?? null) ? $report['authenticity'] : [];
        if (($authenticityConfig['enabled'] ?? false) !== true) {
            return;
        }
        if (($authenticityConfig['storage']['storeExportArtifact'] ?? false) !== true) {
            return;
        }

        $authenticity = is_array($result['authenticity'] ?? null) ? $result['authenticity'] : null;
        $metadata = [
            'auditContext' => 'report',
            'reportId' => $definition['program']['id'] ?? null,
            'reportTitle' => $definition['program']['title'] ?? null,
            'sourceType' => (string) ($result['sourceType'] ?? ''),
            'authenticity' => $authenticity,
            'artifact' => [
                'stored' => true,
                'format' => $format,
                'fileName' => (string) ($exportResponse['fileName'] ?? ''),
                'contentType' => (string) ($exportResponse['contentType'] ?? ''),
                'contentBase64' => (string) ($exportResponse['contentBase64'] ?? ''),
            ],
        ];
        if ($authenticity && (($authenticity['storage']['storeCanonicalPayload'] ?? true) !== false)) {
            $metadata['canonicalPayload'] = $this->buildAuthenticityCanonicalPayload($definition, $result, $payload);
        }

        $this->auditStore->record([
            'tenantId' => $tenantId,
            'userId' => $this->permissions->getUserId(),
            'sessionId' => $this->permissions->getSessionId(),
            'screenId' => (string) ($definition['screenId'] ?? 'report'),
            'datasetId' => (string) ($definition['program']['id'] ?? $definition['screenId'] ?? 'report'),
            'viewId' => $format,
            'executionMode' => (string) ($result['sourceType'] ?? 'operational'),
            'resultSource' => 'report_export',
            'filterFingerprint' => hash('sha256', (string) json_encode([
                'parameters' => $payload['parameters'] ?? [],
                'sort' => $payload['sort'] ?? [],
                'format' => $format,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'rowCount' => (int) ($result['total'] ?? count($result['rows'] ?? [])),
            'totalCount' => (int) ($result['total'] ?? count($result['rows'] ?? [])),
            'filters' => $payload['filters'] ?? [],
            'parameters' => $payload['parameters'] ?? [],
            'sort' => $payload['sort'] ?? [],
            'requestPayload' => $payload,
            'resultColumns' => $result['columns'] ?? [],
            'resultRows' => $result['rows'] ?? [],
            'metadata' => $metadata,
            'consultedAt' => $result['generatedAt'] ?? (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }
}
