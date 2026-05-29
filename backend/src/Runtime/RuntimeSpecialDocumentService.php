<?php

namespace App\Runtime;

use App\Repository\ScreenDefinitionRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

class RuntimeSpecialDocumentService
{
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
            'pageType' => 'special_document',
            'program' => is_array($definition['program'] ?? null) ? $definition['program'] : [],
            'specialDocument' => $definition['specialDocument'],
            'runtime' => [
                'specialDocument' => [
                    'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function render(string $screenId, array $payload): array
    {
        $definition = $this->loadDefinition($screenId);
        $document = is_array($definition['specialDocument'] ?? null) ? $definition['specialDocument'] : [];
        $source = is_array($document['source'] ?? null) ? $document['source'] : [];
        $parameters = is_array($payload['parameters'] ?? null) ? $payload['parameters'] : [];
        $renderEngine = (string) ($document['renderEngine'] ?? 'native');
        $generatedAt = (new \DateTimeImmutable())->format(DATE_ATOM);
        $documentData = strtolower(trim((string) ($source['type'] ?? 'operational'))) === 'analytic'
            ? $this->runAnalyticSource($document, $payload)
            : $this->runOperationalSource($document, $payload);
        $columns = $documentData['columns'];
        $rows = $documentData['rows'];
        $totals = $documentData['totals'];
        $documentKind = strtolower(trim((string) ($document['classification']['documentKind'] ?? 'special')));
        $documentModel = $this->buildDocumentModel($documentKind, $definition, $document, $parameters, $columns, $rows, $totals, $generatedAt);
        $parameterFields = [];
        foreach ($parameters as $key => $value) {
            $parameterFields[] = [
                'label' => (string) $key,
                'value' => $this->stringifyValue($value),
            ];
        }

        $result = [
            'screenId' => $screenId,
            'documentId' => (string) ($definition['program']['id'] ?? $screenId),
            'title' => (string) ($definition['program']['title'] ?? 'Documento especial'),
            'subtitle' => (string) ($definition['program']['subtitle'] ?? ''),
            'documentKind' => (string) ($document['classification']['documentKind'] ?? 'special'),
            'renderEngine' => $renderEngine,
            'profileType' => $documentModel['profileType'] ?? 'generic',
            'documentModel' => $documentModel,
            'sourceType' => (string) ($source['type'] ?? 'operational'),
            'parameters' => $parameters,
            'summary' => [
                ['label' => 'Renderer', 'value' => $renderEngine],
                ['label' => 'Linhas', 'value' => count($rows)],
                ['label' => 'Fonte', 'value' => (string) ($source['type'] ?? 'operational') === 'analytic' ? 'Analytics' : 'Operacional'],
            ],
            'headerFields' => [
                ['label' => 'Documento', 'value' => (string) ($definition['program']['title'] ?? 'Documento especial')],
                ['label' => 'Tipo', 'value' => (string) ($document['classification']['documentKind'] ?? 'special')],
                ['label' => 'Gerado em', 'value' => $generatedAt],
            ],
            'parameterFields' => $parameterFields,
            'table' => [
                'columns' => $columns,
                'rows' => $rows,
                'rowCount' => count($rows),
            ],
            'totals' => $totals,
            'sections' => [
                [
                    'id' => 'escopo',
                    'title' => 'Escopo controlado',
                    'lines' => [
                        'Esta camada continua separada de reports.',
                        'O renderer usa fonte real do runtime, sem template livre, sem SQL livre e sem JavaScript vindo do metadado.',
                        'Documentos fiscais/oficiais continuam dependentes de engine especifica futura.',
                    ],
                ],
                [
                    'id' => 'dados',
                    'title' => 'Dados consultados',
                    'lines' => [
                        'Linhas retornadas: ' . count($rows),
                        'Campos considerados: ' . implode(', ', array_map(static fn (array $column): string => (string) ($column['title'] ?? $column['field'] ?? ''), $columns)),
                    ],
                ],
            ],
            'artifact' => [
                'recommendedFormat' => (($document['outputs']['pdf'] ?? true) === true) ? 'pdf' : 'html',
                'status' => 'ready',
            ],
            'message' => count($rows) > 0
                ? 'Documento especial renderizado com fonte real em layout controlado.'
                : 'Documento especial sem linhas para os parametros informados.',
            'generatedAt' => $generatedAt,
        ];

        $this->recordAudit($definition, $result, $payload);

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function export(string $screenId, array $payload): array
    {
        $result = $this->render($screenId, $payload);
        $format = strtolower(trim((string) ($payload['format'] ?? 'pdf')));
        if (!in_array($format, ['pdf', 'html'], true)) {
            throw new RuntimeHttpException('SPECIAL_DOCUMENT_EXPORT_FORMAT_NOT_SUPPORTED', 'A exportacao inicial do documento especial aceita apenas PDF/HTML.', 422, [
                'format' => $format,
            ]);
        }

        if ($format === 'html') {
            $html = $this->buildHtmlDocument($result);

            return [
                'ok' => true,
                'format' => 'html',
                'fileName' => $this->safeFileName((string) ($result['documentId'] ?? 'documento-especial')) . '.html',
                'contentType' => 'text/html; charset=utf-8',
                'contentBase64' => base64_encode($html),
            ];
        }

        $pdf = $this->buildDocumentPdf($result);

        return [
            'ok' => true,
            'format' => 'pdf',
            'fileName' => $this->safeFileName((string) ($result['documentId'] ?? 'documento-especial')) . '.pdf',
            'contentType' => 'application/pdf',
            'contentBase64' => base64_encode($pdf),
        ];
    }

    /**
     * @param array<string, mixed> $document
     * @param array<string, mixed> $payload
     * @return array{columns: array<int, array<string, mixed>>, rows: array<int, array<string, mixed>>, totals: array<string, int|float>}
     */
    private function runOperationalSource(array $document, array $payload): array
    {
        $source = is_array($document['source'] ?? null) ? $document['source'] : [];
        $entityCode = trim((string) ($source['entityCode'] ?? $payload['entityCode'] ?? ''));
        if ($entityCode === '') {
            throw new RuntimeHttpException('SPECIAL_DOCUMENT_ENTITY_REQUIRED', 'Documento especial operacional exige entityCode.', 422);
        }

        $entity = $this->entities->resolve($entityCode);
        $columns = [];
        $qb = $this->connection->createQueryBuilder()
            ->from((string) $entity['quotedTableName'], 'base');

        foreach ($this->selectDocumentFieldCodes($entity) as $code) {
            $field = $entity['fields'][$code] ?? null;
            if (!is_array($field) || ($field['readable'] ?? true) === false || ($field['virtual'] ?? false) === true) {
                continue;
            }
            $columnName = (string) ($field['column'] ?? '');
            if ($columnName === '') {
                continue;
            }
            $qb->addSelect('base.' . $this->quoteIdentifier($columnName) . ' AS ' . $this->quoteIdentifier($code));
            $columns[] = [
                'field' => $code,
                'title' => (string) ($field['label'] ?? $code),
                'label' => (string) ($field['label'] ?? $code),
                'type' => (string) ($field['dataType'] ?? 'string'),
                'align' => $this->isNumericType((string) ($field['dataType'] ?? '')) ? 'right' : 'left',
                'totalable' => $this->isNumericType((string) ($field['dataType'] ?? '')),
                'format' => $this->defaultFormatForType((string) ($field['dataType'] ?? '')),
            ];
        }

        if (!$columns) {
            throw new RuntimeHttpException('SPECIAL_DOCUMENT_FIELDS_NOT_USABLE', 'Nenhum campo legivel foi encontrado para o documento especial.', 422, [
                'entityCode' => $entityCode,
            ]);
        }

        $this->applyEntityIsolation($qb, $entity, $this->permissions->getTenantId());
        $this->applyParameterFilters($qb, $entity, is_array($document['parameters'] ?? null) ? $document['parameters'] : [], is_array($payload['parameters'] ?? null) ? $payload['parameters'] : []);
        $qb->setMaxResults(max(1, min(100, (int) ($payload['limit'] ?? 25))));

        $rows = $qb->executeQuery()->fetchAllAssociative();

        return [
            'columns' => $columns,
            'rows' => $rows,
            'totals' => $this->summarizeRows($rows, $columns),
        ];
    }

    /**
     * @param array<string, mixed> $document
     * @param array<string, mixed> $payload
     * @return array{columns: array<int, array<string, mixed>>, rows: array<int, array<string, mixed>>, totals: array<string, int|float>}
     */
    private function runAnalyticSource(array $document, array $payload): array
    {
        $source = is_array($document['source'] ?? null) ? $document['source'] : [];
        $analyticsScreenId = trim((string) ($source['analyticsScreenId'] ?? ''));
        $analyticsDatasetId = trim((string) ($source['analyticsDatasetId'] ?? ''));
        if ($analyticsScreenId === '' || $analyticsDatasetId === '') {
            throw new RuntimeHttpException('SPECIAL_DOCUMENT_ANALYTIC_SOURCE_INVALID', 'Documento especial analitico exige analyticsScreenId e analyticsDatasetId.', 422);
        }

        $result = $this->analytics->run($analyticsScreenId, [
            'datasetId' => $analyticsDatasetId,
            'parameters' => is_array($payload['parameters'] ?? null) ? $payload['parameters'] : [],
            'take' => max(1, min(100, (int) ($payload['limit'] ?? 25))),
        ], $this->permissions->getTenantId());

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
                'align' => $this->isNumericType((string) ($column['type'] ?? '')) ? 'right' : 'left',
                'totalable' => (($column['role'] ?? '') === 'measure') || $this->isNumericType((string) ($column['type'] ?? '')),
                'format' => $column['format'] ?? $this->defaultFormatForType((string) ($column['type'] ?? '')),
            ];
        }
        $rows = is_array($result['data'] ?? null) ? $result['data'] : [];

        return [
            'columns' => $columns,
            'rows' => $rows,
            'totals' => $this->summarizeRows($rows, $columns),
        ];
    }

    /**
     * @param array<string, mixed> $entity
     * @return array<int, string>
     */
    private function selectDocumentFieldCodes(array $entity): array
    {
        $preferred = ['nome', 'razaoSocial', 'status', 'uf', 'cidade', 'valorTotal', 'qtdePedidos', 'dataCadastro'];
        $selected = [];
        foreach ($preferred as $code) {
            if (is_array($entity['fields'][$code] ?? null)) {
                $selected[] = $code;
            }
        }
        foreach ((array) ($entity['fields'] ?? []) as $code => $field) {
            if (!is_array($field) || ($field['readable'] ?? true) === false || ($field['virtual'] ?? false) === true) {
                continue;
            }
            $selected[] = (string) $code;
            if (count(array_unique($selected)) >= 8) {
                break;
            }
        }

        return array_values(array_unique($selected));
    }

    /**
     * @param array<string, mixed> $entity
     */
    private function applyEntityIsolation(QueryBuilder $qb, array $entity, string $tenantId): void
    {
        $subscriberIsolation = is_array($entity['subscriberIsolation'] ?? null) ? $entity['subscriberIsolation'] : [];
        if (($subscriberIsolation['enabled'] ?? false) === true && !empty($subscriberIsolation['column'])) {
            $column = (string) $subscriberIsolation['column'];
            $qb->andWhere('base.' . $this->quoteIdentifier($column) . ' = :runtimeSpecialDocumentTenantId')
                ->setParameter('runtimeSpecialDocumentTenantId', $tenantId);
        }

        $softDelete = is_array($entity['softDelete'] ?? null) ? $entity['softDelete'] : [];
        if (($softDelete['enabled'] ?? false) === true && !empty($softDelete['deletedAtColumn'])) {
            $qb->andWhere('base.' . $this->quoteIdentifier((string) $softDelete['deletedAtColumn']) . ' IS NULL');
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
            $this->applyWhereClause($qb, $column, $operator, $values[$parameterId], 'doc_param_' . $index);
        }
    }

    private function applyWhereClause(QueryBuilder $qb, string $column, string $operator, mixed $value, string $param): void
    {
        $parameterName = preg_replace('/[^A-Za-z0-9_]+/', '_', $param) ?: 'doc_param';
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
            case 'eq':
            default:
                $qb->andWhere($column . ' = :' . $parameterName)->setParameter($parameterName, $value);
                return;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadDefinition(string $screenId): array
    {
        $screen = $this->screens->findPublishedByScreenId($screenId);
        if (!$screen) {
            throw new RuntimeHttpException('SPECIAL_DOCUMENT_SCREEN_NOT_FOUND', 'Tela de documento especial nao encontrada.', 404, [
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
        if (($definition['pageType'] ?? '') !== 'special_document') {
            throw new RuntimeHttpException('SPECIAL_DOCUMENT_PAGE_TYPE_INVALID', 'A tela informada nao e do tipo special_document.', 422, [
                'screenId' => $screenId,
                'pageType' => $definition['pageType'] ?? null,
            ]);
        }
        if (!is_array($definition['specialDocument'] ?? null)) {
            throw new RuntimeHttpException('SPECIAL_DOCUMENT_DEFINITION_MISSING', 'Definicao de documento especial nao configurada.', 422, [
                'screenId' => $screenId,
            ]);
        }

        $this->assertNoUnsafeMetadata($definition['specialDocument']);

        return $definition;
    }

    private function assertNoUnsafeMetadata(mixed $value, array $path = []): void
    {
        if (!is_array($value)) {
            if (is_string($value) && preg_match('/<\s*script|javascript\s*:/i', $value)) {
                throw new RuntimeHttpException('SPECIAL_DOCUMENT_UNSAFE_METADATA', 'Documentos especiais nao aceitam HTML, JS ou template livre nos metadados.', 422, [
                    'path' => implode('.', $path),
                ]);
            }
            return;
        }

        foreach ($value as $key => $item) {
            $normalizedKey = strtolower((string) $key);
            if (in_array($normalizedKey, ['sql', 'template', 'javascript', 'script', 'handler', 'function'], true)) {
                throw new RuntimeHttpException('SPECIAL_DOCUMENT_UNSAFE_METADATA', 'Documentos especiais nao aceitam HTML, JS ou template livre nos metadados.', 422, [
                    'path' => implode('.', [...$path, (string) $key]),
                ]);
            }
            $this->assertNoUnsafeMetadata($item, [...$path, (string) $key]);
        }
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $result
     * @param array<string, mixed> $payload
     */
    private function recordAudit(array $definition, array $result, array $payload): void
    {
        if (!$this->auditStore instanceof RuntimeAnalyticsAuditStore || !$this->auditStore->isEnabled()) {
            return;
        }

        $generatedAt = $result['generatedAt'] ?? (new \DateTimeImmutable())->format(DATE_ATOM);
        $this->auditStore->record([
            'tenantId' => $this->permissions->getTenantId(),
            'userId' => $this->permissions->getUserId(),
            'sessionId' => $this->permissions->getSessionId(),
            'screenId' => (string) ($definition['screenId'] ?? 'special_document'),
            'datasetId' => (string) ($definition['program']['id'] ?? $definition['screenId'] ?? 'special_document'),
            'viewId' => trim((string) ($payload['format'] ?? '')),
            'executionMode' => 'special_document',
            'resultSource' => 'special_document_render',
            'filterFingerprint' => hash('sha256', (string) json_encode([
                'parameters' => $payload['parameters'] ?? [],
                'format' => $payload['format'] ?? 'pdf',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'rowCount' => 0,
            'totalCount' => 0,
            'filters' => [],
            'parameters' => $payload['parameters'] ?? [],
            'sort' => [],
            'requestPayload' => $payload,
            'resultColumns' => [],
            'resultRows' => [],
            'metadata' => [
                'auditContext' => 'special_document',
                'documentId' => $definition['program']['id'] ?? null,
                'documentKind' => $definition['specialDocument']['classification']['documentKind'] ?? null,
                'renderEngine' => $definition['specialDocument']['renderEngine'] ?? null,
            ],
            'consultedAt' => $generatedAt,
        ]);
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $document
     * @param array<string, mixed> $parameters
     * @param array<int, array<string, mixed>> $columns
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, int|float> $totals
     * @return array<string, mixed>
     */
    private function buildDocumentModel(
        string $documentKind,
        array $definition,
        array $document,
        array $parameters,
        array $columns,
        array $rows,
        array $totals,
        string $generatedAt
    ): array {
        return match ($documentKind) {
            'danfe', 'dacte', 'fiscal_document', 'fiscal_form' => $this->buildDanfeModel($definition, $document, $parameters, $columns, $rows, $totals, $generatedAt),
            'boleto' => $this->buildBoletoModel($definition, $document, $parameters, $rows, $totals, $generatedAt),
            'label', 'etiqueta' => $this->buildLabelModel($definition, $document, $rows, $generatedAt),
            default => $this->buildGenericModel($definition, $document, $columns, $rows, $totals, $generatedAt),
        };
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $document
     * @param array<string, mixed> $parameters
     * @param array<int, array<string, mixed>> $columns
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, int|float> $totals
     * @return array<string, mixed>
     */
    private function buildDanfeModel(array $definition, array $document, array $parameters, array $columns, array $rows, array $totals, string $generatedAt): array
    {
        $layout = is_array($document['layout'] ?? null) ? $document['layout'] : [];
        $firstRow = is_array($rows[0] ?? null) ? $rows[0] : [];
        $accessKey = $layout['barcodeText'] ?? $this->formatBarcodeNumber('35140530290856000160550010000001234567890123');

        return [
            'profileType' => 'danfe',
            'issuer' => [
                'name' => (string) ($layout['issuerName'] ?? 'Emitente padrao LTDA'),
                'document' => (string) ($layout['issuerDocument'] ?? '12.345.678/0001-90'),
                'city' => (string) ($firstRow['cidade'] ?? 'Fortaleza'),
                'state' => (string) ($firstRow['uf'] ?? 'CE'),
            ],
            'recipient' => [
                'name' => (string) ($firstRow['nome'] ?? 'Destinatario nao informado'),
                'document' => (string) ($firstRow['cnpj'] ?? $firstRow['cpf'] ?? '---'),
                'city' => (string) ($firstRow['cidade'] ?? '---'),
                'state' => (string) ($firstRow['uf'] ?? '---'),
            ],
            'invoice' => [
                'number' => (string) ($parameters['numero'] ?? '12345'),
                'series' => (string) ($parameters['serie'] ?? '1'),
                'issueDate' => (string) ($parameters['emissao'] ?? substr($generatedAt, 0, 10)),
                'protocol' => (string) ($parameters['protocolo'] ?? '135240000123456'),
                'accessKey' => (string) $accessKey,
            ],
            'items' => array_values(array_map(function (array $row, int $index): array {
                return [
                    'code' => (string) ($row['id'] ?? ($index + 1)),
                    'description' => (string) ($row['nome'] ?? $row['descricao'] ?? 'Item'),
                    'quantity' => (float) ($row['qtdePedidos'] ?? $row['qtde_pedidos'] ?? 1),
                    'amount' => (float) ($row['valorTotal'] ?? $row['valor_total'] ?? 0),
                ];
            }, $rows, array_keys($rows))),
            'totals' => [
                'items' => count($rows),
                'quantity' => array_sum(array_map(static fn (array $row): float => (float) ($row['qtdePedidos'] ?? $row['qtde_pedidos'] ?? 1), $rows)),
                'amount' => (float) ($totals['valorTotal'] ?? $totals['valor_total'] ?? 0),
            ],
            'tableColumns' => $columns,
            'notes' => (string) ($layout['notes'] ?? 'Documento controlado pela trilha special_document.'),
        ];
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $document
     * @param array<string, mixed> $parameters
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, int|float> $totals
     * @return array<string, mixed>
     */
    private function buildBoletoModel(array $definition, array $document, array $parameters, array $rows, array $totals, string $generatedAt): array
    {
        $layout = is_array($document['layout'] ?? null) ? $document['layout'] : [];
        $firstRow = is_array($rows[0] ?? null) ? $rows[0] : [];
        $amount = (float) ($totals['valorTotal'] ?? $totals['valor_total'] ?? $firstRow['valorTotal'] ?? $firstRow['valor_total'] ?? 0);
        $barcode = $layout['barcodeText'] ?? $this->formatBarcodeNumber('34191790010104351004791020150008291070026000');

        return [
            'profileType' => 'boleto',
            'beneficiary' => [
                'name' => (string) ($layout['beneficiaryName'] ?? $layout['issuerName'] ?? 'Beneficiario padrao LTDA'),
                'document' => (string) ($layout['beneficiaryDocument'] ?? '12.345.678/0001-90'),
            ],
            'payer' => [
                'name' => (string) ($firstRow['nome'] ?? 'Pagador nao informado'),
                'document' => (string) ($firstRow['cnpj'] ?? $firstRow['cpf'] ?? '---'),
            ],
            'payment' => [
                'dueDate' => (string) ($layout['dueDate'] ?? $parameters['vencimento'] ?? substr($generatedAt, 0, 10)),
                'documentNumber' => (string) ($parameters['documento'] ?? 'DOC-0001'),
                'nossoNumero' => (string) ($parameters['nossoNumero'] ?? '10987654321'),
                'amount' => $amount,
                'barcode' => (string) $barcode,
            ],
            'instructions' => [
                'Nao receber apos o vencimento sem autorizacao.',
                'Documento gerado por renderer controlado da trilha special_document.',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $document
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function buildLabelModel(array $definition, array $document, array $rows, string $generatedAt): array
    {
        $labels = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $labels[] = [
                'code' => (string) ($row['id'] ?? $row['codigo'] ?? $index + 1),
                'recipient' => (string) ($row['nome'] ?? 'Destinatario'),
                'line1' => (string) ($row['razaoSocial'] ?? $row['status'] ?? 'Sem complemento'),
                'line2' => trim((string) ($row['cidade'] ?? '') . ' / ' . (string) ($row['uf'] ?? '')),
                'printedAt' => substr($generatedAt, 0, 16),
            ];
        }

        return [
            'profileType' => 'label',
            'labels' => $labels,
            'layoutMode' => 'grid',
        ];
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $document
     * @param array<int, array<string, mixed>> $columns
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, int|float> $totals
     * @return array<string, mixed>
     */
    private function buildGenericModel(array $definition, array $document, array $columns, array $rows, array $totals, string $generatedAt): array
    {
        return [
            'profileType' => 'generic',
            'generatedAt' => $generatedAt,
            'tableColumns' => $columns,
            'tableRows' => $rows,
            'totals' => $totals,
        ];
    }

    /**
     * @param array<string, mixed> $result
     */
    private function buildDocumentPdf(array $result): string
    {
        $profileType = (string) ($result['profileType'] ?? 'generic');
        $model = is_array($result['documentModel'] ?? null) ? $result['documentModel'] : [];
        if ($profileType === 'label') {
            return $this->buildLabelPdf($result, $model);
        }
        $lines = [
            (string) ($result['title'] ?? 'Documento especial'),
            (string) ($result['subtitle'] ?? ''),
            'Documento especial em trilha separada.',
            'Renderer atual: ' . (string) ($result['renderEngine'] ?? 'native'),
            'Gerado em: ' . $this->stringifyValue($result['generatedAt'] ?? ''),
            ' ',
        ];
        if ($profileType === 'danfe') {
            $lines[] = 'Emitente: ' . $this->stringifyValue($model['issuer']['name'] ?? '');
            $lines[] = 'Destinatario: ' . $this->stringifyValue($model['recipient']['name'] ?? '');
            $lines[] = 'Chave: ' . $this->stringifyValue($model['invoice']['accessKey'] ?? '');
            $lines[] = 'Protocolo: ' . $this->stringifyValue($model['invoice']['protocol'] ?? '');
            $lines[] = ' ';
        } elseif ($profileType === 'boleto') {
            $lines[] = 'Beneficiario: ' . $this->stringifyValue($model['beneficiary']['name'] ?? '');
            $lines[] = 'Pagador: ' . $this->stringifyValue($model['payer']['name'] ?? '');
            $lines[] = 'Vencimento: ' . $this->stringifyValue($model['payment']['dueDate'] ?? '');
            $lines[] = 'Linha digitavel: ' . $this->stringifyValue($model['payment']['barcode'] ?? '');
            $lines[] = ' ';
        }
        foreach ((array) ($result['headerFields'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $lines[] = (string) ($item['label'] ?? 'Campo') . ': ' . $this->stringifyValue($item['value'] ?? '');
        }
        foreach ((array) ($result['parameterFields'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $lines[] = 'Parametro - ' . (string) ($item['label'] ?? 'Campo') . ': ' . $this->stringifyValue($item['value'] ?? '');
        }
        $lines[] = ' ';
        $columns = is_array($result['table']['columns'] ?? null) ? $result['table']['columns'] : [];
        if ($columns) {
            $lines[] = implode(' | ', array_map(static fn (array $column): string => (string) ($column['title'] ?? $column['field'] ?? ''), $columns));
            foreach ((array) ($result['table']['rows'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $values = [];
                foreach ($columns as $column) {
                    $values[] = $this->stringifyValue($row[$column['field']] ?? null);
                }
                $lines[] = implode(' | ', $values);
            }
        }
        if (!empty($result['totals']) && is_array($result['totals'])) {
            $lines[] = ' ';
            $lines[] = 'Totais';
            foreach ($result['totals'] as $field => $value) {
                $lines[] = '  ' . $field . ': ' . $this->stringifyValue($value);
            }
        }
        $content = "BT /F1 12 Tf 40 780 Td ";
        $first = true;
        foreach ($lines as $line) {
            if (!$first) {
                $content .= "T* ";
            }
            $content .= '(' . $this->escapePdfText($line) . ') Tj ';
            $first = false;
        }
        $content .= 'ET';

        $objects = [
            "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n",
            "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n",
            "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj\n",
            "4 0 obj << /Length " . strlen($content) . " >> stream\n" . $content . "\nendstream endobj\n",
            "5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($index = 1; $index <= count($objects); ++$index) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$index]);
        }
        $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function buildHtmlDocument(array $result): string
    {
        $title = htmlspecialchars((string) ($result['title'] ?? 'Documento especial'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $subtitle = htmlspecialchars((string) ($result['subtitle'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $profileType = (string) ($result['profileType'] ?? 'generic');
        $model = is_array($result['documentModel'] ?? null) ? $result['documentModel'] : [];
        $html = '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>' . $title . '</title>'
            . '<style>body{font-family:Arial,sans-serif;color:#1f2937;margin:24px}h1{margin:0 0 4px}h2{margin:20px 0 8px}.meta,.doc-grid,.label-grid{display:flex;gap:16px;flex-wrap:wrap;margin:12px 0}.card,.label-card,.doc-block{border:1px solid #d6dde6;border-radius:6px;padding:10px 12px;min-width:160px;background:#fff}.doc-block{flex:1 1 240px}.doc-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;border:1px solid #d6dde6;border-radius:8px;padding:14px;margin:14px 0;background:#f8fafc}.card strong,.pair strong,.doc-block strong,.label-card strong{display:block;margin-bottom:4px}.pair{margin:0 0 8px}.barcode{font-family:monospace;letter-spacing:1px;border:1px dashed #94a3b8;padding:10px;background:repeating-linear-gradient(90deg,#0f172a 0,#0f172a 2px,#fff 2px,#fff 4px)}table{width:100%;border-collapse:collapse;margin-top:8px}th,td{border:1px solid #d6dde6;padding:8px;text-align:left}th{background:#f5f7fa}td.num{text-align:right}.notes{color:#4b5563}.label-card{width:260px;min-height:140px}</style>'
            . '</head><body><h1>' . $title . '</h1><p>' . $subtitle . '</p>';
        $html .= '<div class="meta">';
        foreach ((array) ($result['summary'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $html .= '<div class="card"><strong>' . htmlspecialchars((string) ($item['label'] ?? 'Resumo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>'
                . htmlspecialchars($this->stringifyValue($item['value'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
        }
        $html .= '</div><h2>Cabecalho</h2>';
        foreach ((array) ($result['headerFields'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $html .= '<p class="pair"><strong>' . htmlspecialchars((string) ($item['label'] ?? 'Campo'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>'
                . htmlspecialchars($this->stringifyValue($item['value'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        }
        if (!empty($result['parameterFields'])) {
            $html .= '<h2>Parametros</h2>';
            foreach ((array) ($result['parameterFields'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $html .= '<p class="pair"><strong>' . htmlspecialchars((string) ($item['label'] ?? 'Parametro'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>'
                    . htmlspecialchars($this->stringifyValue($item['value'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
            }
        }
        if ($profileType === 'danfe') {
            $html .= '<h2>Documento fiscal</h2><section class="doc-head"><div><strong>Emitente</strong><div>' . htmlspecialchars((string) ($model['issuer']['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div><div>' . htmlspecialchars((string) ($model['issuer']['document'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div></div><div><strong>Destinatario</strong><div>' . htmlspecialchars((string) ($model['recipient']['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div><div>' . htmlspecialchars((string) ($model['recipient']['document'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div></div></section>';
            $html .= '<div class="doc-grid"><div class="doc-block"><strong>Numero / Serie</strong>' . htmlspecialchars((string) ($model['invoice']['number'] ?? '') . ' / ' . (string) ($model['invoice']['series'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div><div class="doc-block"><strong>Protocolo</strong>' . htmlspecialchars((string) ($model['invoice']['protocol'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div><div class="doc-block"><strong>Emissao</strong>' . htmlspecialchars((string) ($model['invoice']['issueDate'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div></div>';
            $html .= '<div class="barcode">' . htmlspecialchars((string) ($model['invoice']['accessKey'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
        } elseif ($profileType === 'boleto') {
            $html .= '<h2>Boleto</h2><section class="doc-head"><div><strong>Beneficiario</strong><div>' . htmlspecialchars((string) ($model['beneficiary']['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div><div>' . htmlspecialchars((string) ($model['beneficiary']['document'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div></div><div><strong>Pagador</strong><div>' . htmlspecialchars((string) ($model['payer']['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div><div>' . htmlspecialchars((string) ($model['payer']['document'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div></div></section>';
            $html .= '<div class="doc-grid"><div class="doc-block"><strong>Vencimento</strong>' . htmlspecialchars((string) ($model['payment']['dueDate'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div><div class="doc-block"><strong>Nosso numero</strong>' . htmlspecialchars((string) ($model['payment']['nossoNumero'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div><div class="doc-block"><strong>Valor</strong>' . htmlspecialchars($this->stringifyValue($model['payment']['amount'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div></div>';
            $html .= '<div class="barcode">' . htmlspecialchars((string) ($model['payment']['barcode'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
        } elseif ($profileType === 'label') {
            $html .= '<h2>Etiquetas</h2><div class="label-grid">';
            foreach ((array) ($model['labels'] ?? []) as $label) {
                if (!is_array($label)) {
                    continue;
                }
                $html .= '<article class="label-card"><strong>' . htmlspecialchars((string) ($label['recipient'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong><div>' . htmlspecialchars((string) ($label['line1'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div><div>' . htmlspecialchars((string) ($label['line2'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div><div style="margin-top:12px;font-family:monospace">' . htmlspecialchars((string) ($label['code'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div></article>';
            }
            $html .= '</div>';
        }
        $columns = is_array($result['table']['columns'] ?? null) ? $result['table']['columns'] : [];
        $rows = is_array($result['table']['rows'] ?? null) ? $result['table']['rows'] : [];
        $html .= '<h2>Dados consultados</h2><table><thead><tr>';
        foreach ($columns as $column) {
            $html .= '<th>' . htmlspecialchars((string) ($column['title'] ?? $column['field'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $html .= '<tr>';
            foreach ($columns as $column) {
                $field = (string) ($column['field'] ?? '');
                $class = (($column['align'] ?? '') === 'right') ? ' class="num"' : '';
                $html .= '<td' . $class . '>' . htmlspecialchars($this->stringifyValue($row[$field] ?? null), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        if (!empty($result['totals']) && is_array($result['totals'])) {
            $html .= '<h2>Totais</h2>';
            foreach ($result['totals'] as $field => $value) {
                $html .= '<p class="pair"><strong>' . htmlspecialchars((string) $field, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>'
                    . htmlspecialchars($this->stringifyValue($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
            }
        }
        $html .= '<h2>Observacoes</h2><p class="notes">' . htmlspecialchars((string) ($result['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p></body></html>';

        return $html;
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, mixed> $model
     */
    private function buildLabelPdf(array $result, array $model): string
    {
        $lines = [
            (string) ($result['title'] ?? 'Etiqueta'),
            'Renderer atual: ' . (string) ($result['renderEngine'] ?? 'native'),
            'Gerado em: ' . $this->stringifyValue($result['generatedAt'] ?? ''),
            ' ',
        ];
        foreach ((array) ($model['labels'] ?? []) as $label) {
            if (!is_array($label)) {
                continue;
            }
            $lines[] = 'Etiqueta ' . $this->stringifyValue($label['code'] ?? '');
            $lines[] = '  ' . $this->stringifyValue($label['recipient'] ?? '');
            $lines[] = '  ' . $this->stringifyValue($label['line1'] ?? '');
            $lines[] = '  ' . $this->stringifyValue($label['line2'] ?? '');
            $lines[] = ' ';
        }

        return $this->buildSimplePdfFromLines($lines);
    }

    /**
     * @param list<string> $lines
     */
    private function buildSimplePdfFromLines(array $lines): string
    {
        $content = "BT /F1 12 Tf 40 780 Td ";
        $first = true;
        foreach ($lines as $line) {
            if (!$first) {
                $content .= "T* ";
            }
            $content .= '(' . $this->escapePdfText($line) . ') Tj ';
            $first = false;
        }
        $content .= 'ET';

        $objects = [
            "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n",
            "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n",
            "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj\n",
            "4 0 obj << /Length " . strlen($content) . " >> stream\n" . $content . "\nendstream endobj\n",
            "5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($index = 1; $index <= count($objects); ++$index) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$index]);
        }
        $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    private function formatBarcodeNumber(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?: '';
        return trim(chunk_split($digits, 4, ' '));
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

    private function isNumericType(string $type): bool
    {
        return in_array(strtolower(trim($type)), ['integer', 'decimal', 'number', 'currency', 'float'], true);
    }

    private function defaultFormatForType(string $type): ?string
    {
        return match (strtolower(trim($type))) {
            'currency' => 'c2',
            'decimal', 'float', 'number' => 'n2',
            'integer' => 'n0',
            default => null,
        };
    }

    private function quoteIdentifier(string $value): string
    {
        return $this->connection->quoteIdentifier($value);
    }

    private function escapePdfText(string $value): string
    {
        $normalized = preg_replace('/[^\x20-\x7E]/', '?', $value) ?? '';
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $normalized);
    }

    private function safeFileName(string $value): string
    {
        $clean = preg_replace('/[^A-Za-z0-9._-]+/', '-', $value) ?: 'documento-especial';
        return trim($clean, '-');
    }
}
