<?php

namespace App\Runtime;

use App\Repository\ScreenDefinitionRepository;
use Doctrine\DBAL\Connection;

class RuntimeRegulatedDocumentService
{
    public function __construct(
        private readonly ScreenDefinitionRepository $screens,
        private readonly RuntimeEntityDefinitionResolver $entities,
        private readonly Connection $connection,
        private readonly PermissionResolver $permissions,
        private readonly StructuralIntegrityService $integrity,
        private readonly ProgramCustomizationResolver $customizations,
        private readonly RuntimeAnalyticsService $analytics,
        private readonly RuntimeRegulatedDocumentStore $store,
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
            'pageType' => 'regulated_document',
            'program' => is_array($definition['program'] ?? null) ? $definition['program'] : [],
            'regulatedDocument' => $definition['regulatedDocument'],
            'runtime' => [
                'regulatedDocument' => [
                    'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                    'storageEnabled' => $this->store->isEnabled(),
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function prepare(string $screenId, array $payload): array
    {
        $definition = $this->loadDefinition($screenId);
        $document = is_array($definition['regulatedDocument'] ?? null) ? $definition['regulatedDocument'] : [];
        $issueId = trim((string) ($payload['issueId'] ?? '')) ?: $this->newIssueId();
        $parameters = is_array($payload['parameters'] ?? null) ? $payload['parameters'] : [];
        $validation = $this->validateParameters($document, $parameters);

        try {
            $documentData = $this->resolveSourceData($definition, $document, $payload);
            $canonicalPayload = $this->buildCanonicalPayload($definition, $document, $parameters, $documentData);
        } catch (\Throwable $error) {
            $validation[] = [
                'level' => 'error',
                'code' => 'SOURCE_RESOLUTION_FAILED',
                'message' => $error->getMessage(),
                'blocking' => true,
            ];
            $documentData = [
                'columns' => [],
                'rows' => [],
                'totals' => [],
            ];
            $canonicalPayload = [
                'parameters' => $parameters,
                'columns' => [],
                'rows' => [],
                'totals' => [],
            ];
        }

        $state = $this->hasBlockingValidation($validation) ? 'failed' : 'prepared';
        $record = [
            'issueId' => $issueId,
            'tenantId' => $this->permissions->getTenantId(),
            'userId' => $this->permissions->getUserId(),
            'sessionId' => $this->permissions->getSessionId(),
            'screenId' => (string) ($definition['screenId'] ?? $screenId),
            'documentId' => (string) ($definition['program']['id'] ?? $screenId),
            'track' => (string) ($document['track'] ?? 'fiscal'),
            'documentType' => (string) ($document['documentType'] ?? 'regulated_document'),
            'complianceProfile' => (string) ($document['complianceProfile'] ?? 'near_homologated'),
            'state' => $state,
            'parameters' => $parameters,
            'canonicalPayload' => (($document['artifactPolicy']['storeCanonicalPayload'] ?? true) !== false) ? $canonicalPayload : null,
            'validation' => $validation,
            'metadata' => [
                'sourceType' => (string) (($document['source']['type'] ?? 'operational')),
                'programTitle' => (string) ($definition['program']['title'] ?? 'Documento regulado'),
            ],
            'errorMessage' => $state === 'failed' ? $this->firstBlockingMessage($validation) : null,
            'createdAt' => new \DateTimeImmutable(),
            'updatedAt' => new \DateTimeImmutable(),
        ];
        $this->store->saveRecord($record);
        $this->store->appendEvent($issueId, 'prepare', [
            'state' => $state,
            'validationCount' => count($validation),
        ]);

        return [
            'ok' => $state !== 'failed',
            'issueId' => $issueId,
            'state' => $state,
            'track' => (string) ($document['track'] ?? 'fiscal'),
            'documentType' => (string) ($document['documentType'] ?? 'regulated_document'),
            'complianceProfile' => (string) ($document['complianceProfile'] ?? 'near_homologated'),
            'validation' => $validation,
            'parameters' => $parameters,
            'canonicalPayload' => $canonicalPayload,
            'artifactPolicy' => is_array($document['artifactPolicy'] ?? null) ? $document['artifactPolicy'] : [],
            'verification' => is_array($document['verification'] ?? null) ? $document['verification'] : [],
            'message' => $state === 'failed'
                ? 'Preparacao falhou nas validacoes estruturais.'
                : 'Payload canonico preparado para emissao.',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function render(string $screenId, array $payload): array
    {
        $prepared = $this->resolvePreparedRecord($screenId, $payload);
        if (($prepared['state'] ?? '') === 'failed') {
            throw new RuntimeHttpException('REGULATED_DOCUMENT_PREPARE_FAILED', 'O documento regulado possui validacoes bloqueantes.', 422, [
                'issueId' => $prepared['issueId'] ?? null,
                'validation' => $prepared['validation'] ?? [],
            ]);
        }

        $definition = $this->loadDefinition($screenId);
        $document = is_array($definition['regulatedDocument'] ?? null) ? $definition['regulatedDocument'] : [];
        $canonical = is_array($prepared['canonicalPayload'] ?? null) ? $prepared['canonicalPayload'] : [];
        $preview = $this->buildPreviewResult($definition, $document, $prepared, $canonical);

        $record = $this->store->findByIssueId((string) $prepared['issueId']) ?? [];
        $record['issueId'] = (string) $prepared['issueId'];
        $record['tenantId'] = $this->permissions->getTenantId();
        $record['userId'] = $this->permissions->getUserId();
        $record['sessionId'] = $this->permissions->getSessionId();
        $record['screenId'] = (string) ($definition['screenId'] ?? $screenId);
        $record['documentId'] = (string) ($definition['program']['id'] ?? $screenId);
        $record['track'] = (string) ($document['track'] ?? 'fiscal');
        $record['documentType'] = (string) ($document['documentType'] ?? 'regulated_document');
        $record['complianceProfile'] = (string) ($document['complianceProfile'] ?? 'near_homologated');
        $record['state'] = 'rendered';
        $record['parameters'] = $prepared['parameters'] ?? [];
        $record['canonicalPayload'] = (($document['artifactPolicy']['storeCanonicalPayload'] ?? true) !== false) ? $canonical : null;
        $record['validation'] = $prepared['validation'] ?? [];
        $record['metadata'] = array_merge(is_array($record['metadata'] ?? null) ? $record['metadata'] : [], [
            'preview' => [
                'rowCount' => (int) ($preview['table']['rowCount'] ?? 0),
                'sectionCount' => count((array) ($preview['sections'] ?? [])),
            ],
        ]);
        $record['updatedAt'] = new \DateTimeImmutable();
        $this->store->saveRecord($record);
        $this->store->appendEvent((string) $prepared['issueId'], 'render', [
            'state' => 'rendered',
        ]);

        return $preview;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function issue(string $screenId, array $payload): array
    {
        $rendered = $this->render($screenId, $payload);
        $definition = $this->loadDefinition($screenId);
        $document = is_array($definition['regulatedDocument'] ?? null) ? $definition['regulatedDocument'] : [];
        $format = strtolower(trim((string) ($payload['format'] ?? ($document['artifactPolicy']['defaultFormat'] ?? 'pdf'))));
        if (!in_array($format, ['pdf', 'html'], true)) {
            throw new RuntimeHttpException('REGULATED_DOCUMENT_FORMAT_NOT_SUPPORTED', 'A emissao inicial aceita apenas HTML/PDF.', 422, [
                'format' => $format,
            ]);
        }

        $canonical = is_array($rendered['canonicalPayload'] ?? null) ? $rendered['canonicalPayload'] : [];
        $hash = 'sha256:' . hash('sha256', (string) json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $artifact = $format === 'html'
            ? [
                'format' => 'html',
                'fileName' => $this->safeFileName((string) ($rendered['documentId'] ?? 'documento-regulado')) . '.html',
                'contentType' => 'text/html; charset=utf-8',
                'contentBase64' => base64_encode($this->buildHtmlArtifact($rendered)),
            ]
            : [
                'format' => 'pdf',
                'fileName' => $this->safeFileName((string) ($rendered['documentId'] ?? 'documento-regulado')) . '.pdf',
                'contentType' => 'application/pdf',
                'contentBase64' => base64_encode($this->buildPdfArtifact($rendered)),
            ];

        $storeArtifact = ($document['artifactPolicy']['storeArtifact'] ?? true) === true;
        $record = $this->store->findByIssueId((string) $rendered['issueId']) ?? [];
        $record['issueId'] = (string) $rendered['issueId'];
        $record['tenantId'] = $this->permissions->getTenantId();
        $record['userId'] = $this->permissions->getUserId();
        $record['sessionId'] = $this->permissions->getSessionId();
        $record['screenId'] = (string) ($definition['screenId'] ?? $screenId);
        $record['documentId'] = (string) ($definition['program']['id'] ?? $screenId);
        $record['track'] = (string) ($document['track'] ?? 'fiscal');
        $record['documentType'] = (string) ($document['documentType'] ?? 'regulated_document');
        $record['complianceProfile'] = (string) ($document['complianceProfile'] ?? 'near_homologated');
        $record['state'] = 'issued';
        $record['format'] = $format;
        $record['hash'] = $hash;
        $record['parameters'] = $rendered['parameters'] ?? [];
        $record['canonicalPayload'] = (($document['artifactPolicy']['storeCanonicalPayload'] ?? true) !== false) ? $canonical : null;
        $record['artifact'] = $storeArtifact ? $artifact : [
            'stored' => false,
            'format' => $artifact['format'],
            'fileName' => $artifact['fileName'],
            'contentType' => $artifact['contentType'],
        ];
        $record['validation'] = $rendered['validation'] ?? [];
        $record['verification'] = [
            'enabled' => ($document['verification']['enabled'] ?? true) !== false,
            'algorithm' => (string) ($document['verification']['algorithm'] ?? 'sha256'),
            'label' => (string) ($document['verification']['label'] ?? 'Codigo de conferencia'),
            'publicPath' => (string) ($document['verification']['publicPath'] ?? 'regulated-document-authenticity.html'),
            'hash' => $hash,
        ];
        $record['metadata'] = array_merge(is_array($record['metadata'] ?? null) ? $record['metadata'] : [], [
            'sourceType' => (string) (($document['source']['type'] ?? 'operational')),
            'programTitle' => (string) ($definition['program']['title'] ?? 'Documento regulado'),
            'artifactStored' => $storeArtifact,
        ]);
        $record['updatedAt'] = new \DateTimeImmutable();
        $record['issuedAt'] = new \DateTimeImmutable();
        $this->store->saveRecord($record);
        $this->store->appendEvent((string) $rendered['issueId'], 'issue', [
            'state' => 'issued',
            'format' => $format,
            'hash' => $hash,
        ]);

        return [
            'ok' => true,
            'issueId' => $record['issueId'],
            'state' => 'issued',
            'hash' => $hash,
            'format' => $format,
            'artifactStored' => $storeArtifact,
            'artifact' => $artifact,
            'verificationUrl' => $this->buildVerificationUrl((string) ($document['verification']['publicPath'] ?? 'regulated-document-authenticity.html'), $hash),
            'message' => 'Documento regulado emitido em trilha separada.',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function verify(string $screenId, array $payload): array
    {
        $issueId = trim((string) ($payload['issueId'] ?? ''));
        $hash = strtolower(trim((string) ($payload['hash'] ?? '')));
        $record = $issueId !== '' ? $this->store->findByIssueId($issueId) : ($hash !== '' ? $this->store->findByHash($hash) : null);
        if (!$record) {
            throw new RuntimeHttpException('REGULATED_DOCUMENT_NOT_FOUND', 'Documento regulado nao encontrado para conferencia.', 404, [
                'issueId' => $issueId,
                'hash' => $hash,
            ]);
        }

        $canonical = is_array($record['canonicalPayload'] ?? null) ? $record['canonicalPayload'] : [];
        $expectedHash = 'sha256:' . hash('sha256', (string) json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $recordedHash = strtolower((string) ($record['hash'] ?? ''));
        $providedMatches = $hash === '' || ($recordedHash !== '' && hash_equals($recordedHash, $hash));
        $verified = $providedMatches && $expectedHash !== 'sha256:' && $recordedHash !== '' && hash_equals($expectedHash, $recordedHash);
        $verification = [
            'verified' => $verified,
            'expectedHash' => $expectedHash,
            'recordedHash' => (string) ($record['hash'] ?? ''),
            'providedHash' => $hash !== '' ? $hash : null,
            'providedHashMatches' => $providedMatches,
            'artifactStored' => is_array($record['artifact'] ?? null) && !empty($record['artifact']['contentBase64']),
            'checkedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];

        $record['state'] = $verified ? 'verified' : (string) ($record['state'] ?? 'failed');
        $record['verification'] = $verification;
        $record['updatedAt'] = new \DateTimeImmutable();
        $record['verifiedAt'] = $verified ? new \DateTimeImmutable() : ($record['verifiedAt'] ?? null);
        $record['errorMessage'] = $verified ? null : 'Falha ao conferir hash do documento regulado.';
        $this->store->saveRecord($record);
        $this->store->appendEvent((string) ($record['issueId'] ?? ''), 'verify', $verification);

        return [
            'ok' => $verified,
            'issueId' => (string) ($record['issueId'] ?? ''),
            'state' => (string) ($record['state'] ?? ''),
            'hash' => (string) ($record['hash'] ?? ''),
            'verification' => $verification,
            'message' => $verified
                ? 'Documento regulado conferido com sucesso.'
                : 'Nao foi possivel confirmar a integridade do documento regulado.',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function artifact(string $screenId, array $payload): array
    {
        $issueId = trim((string) ($payload['issueId'] ?? ''));
        if ($issueId === '') {
            throw new RuntimeHttpException('REGULATED_DOCUMENT_ISSUE_ID_REQUIRED', 'Informe o issueId do documento regulado.', 422);
        }

        $record = $this->store->findByIssueId($issueId);
        if (!$record) {
            throw new RuntimeHttpException('REGULATED_DOCUMENT_NOT_FOUND', 'Documento regulado nao encontrado.', 404, [
                'issueId' => $issueId,
            ]);
        }
        $artifact = is_array($record['artifact'] ?? null) ? $record['artifact'] : [];
        if (empty($artifact['contentBase64'])) {
            throw new RuntimeHttpException('REGULATED_DOCUMENT_ARTIFACT_NOT_AVAILABLE', 'Artefato nao esta disponivel para este issueId.', 404, [
                'issueId' => $issueId,
            ]);
        }

        return [
            'ok' => true,
            'issueId' => $issueId,
            'format' => (string) ($artifact['format'] ?? ''),
            'fileName' => (string) ($artifact['fileName'] ?? ''),
            'contentType' => (string) ($artifact['contentType'] ?? 'application/octet-stream'),
            'contentBase64' => (string) ($artifact['contentBase64'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadDefinition(string $screenId): array
    {
        $screen = $this->screens->findPublishedByScreenId($screenId);
        if (!$screen) {
            throw new RuntimeHttpException('REGULATED_DOCUMENT_SCREEN_NOT_FOUND', 'Tela do documento regulado nao encontrada.', 404, [
                'screenId' => $screenId,
            ]);
        }
        $this->integrity->assertScreen($screen);

        $definition = $screen->getDefinition();
        $customized = $this->customizations->resolve($screenId, $definition);
        if (is_array($customized) && $customized) {
            $definition = $customized;
        }
        if (($definition['pageType'] ?? '') !== 'regulated_document') {
            throw new RuntimeHttpException('REGULATED_DOCUMENT_PAGE_TYPE_INVALID', 'A tela informada nao e do tipo regulated_document.', 422, [
                'screenId' => $screenId,
                'pageType' => $definition['pageType'] ?? null,
            ]);
        }

        $this->assertSafeDefinition($definition);

        return $definition;
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $document
     * @param array<string, mixed> $payload
     * @return array{columns: array<int, array<string, mixed>>, rows: array<int, array<string, mixed>>, totals: array<string, int|float>}
     */
    private function resolveSourceData(array $definition, array $document, array $payload): array
    {
        $source = is_array($document['source'] ?? null) ? $document['source'] : [];
        $type = strtolower(trim((string) ($source['type'] ?? 'operational')));

        return $type === 'analytic'
            ? $this->runAnalyticSource($document, $payload)
            : $this->runOperationalSource($document, $payload);
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
            throw new RuntimeHttpException('REGULATED_DOCUMENT_ENTITY_REQUIRED', 'Documento regulado operacional exige entityCode.', 422);
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
            throw new RuntimeHttpException('REGULATED_DOCUMENT_FIELDS_NOT_USABLE', 'Nenhum campo legivel foi encontrado para o documento regulado.', 422, [
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
            throw new RuntimeHttpException('REGULATED_DOCUMENT_ANALYTIC_SOURCE_INVALID', 'Documento regulado analitico exige analyticsScreenId e analyticsDatasetId.', 422);
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
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $document
     * @param array<string, mixed> $parameters
     * @param array{columns: array<int, array<string, mixed>>, rows: array<int, array<string, mixed>>, totals: array<string, int|float>} $documentData
     * @return array<string, mixed>
     */
    private function buildCanonicalPayload(array $definition, array $document, array $parameters, array $documentData): array
    {
        return [
            'issueId' => null,
            'screenId' => (string) ($definition['screenId'] ?? ''),
            'documentId' => (string) ($definition['program']['id'] ?? $definition['screenId'] ?? ''),
            'title' => (string) (($document['layout']['title'] ?? $definition['program']['title'] ?? 'Documento regulado')),
            'subtitle' => (string) (($document['layout']['subtitle'] ?? $definition['program']['subtitle'] ?? '')),
            'track' => (string) ($document['track'] ?? 'fiscal'),
            'documentType' => (string) ($document['documentType'] ?? 'regulated_document'),
            'complianceProfile' => (string) ($document['complianceProfile'] ?? 'near_homologated'),
            'sourceType' => (string) (($document['source']['type'] ?? 'operational')),
            'parameters' => $parameters,
            'columns' => $documentData['columns'],
            'rows' => $documentData['rows'],
            'totals' => $documentData['totals'],
            'generatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $document
     * @param array<string, mixed> $prepared
     * @param array<string, mixed> $canonical
     * @return array<string, mixed>
     */
    private function buildPreviewResult(array $definition, array $document, array $prepared, array $canonical): array
    {
        $parameterFields = [];
        foreach ((array) ($prepared['parameters'] ?? []) as $key => $value) {
            $parameterFields[] = [
                'label' => (string) $key,
                'value' => $this->stringifyValue($value),
            ];
        }

        return [
            'screenId' => (string) ($definition['screenId'] ?? ''),
            'documentId' => (string) ($definition['program']['id'] ?? ''),
            'issueId' => (string) ($prepared['issueId'] ?? ''),
            'state' => 'rendered',
            'title' => (string) ($definition['program']['title'] ?? 'Documento regulado'),
            'subtitle' => (string) ($definition['program']['subtitle'] ?? ''),
            'track' => (string) ($document['track'] ?? 'fiscal'),
            'documentType' => (string) ($document['documentType'] ?? 'regulated_document'),
            'complianceProfile' => (string) ($document['complianceProfile'] ?? 'near_homologated'),
            'renderEngine' => 'internal',
            'parameters' => $prepared['parameters'] ?? [],
            'validation' => $prepared['validation'] ?? [],
            'headerFields' => [
                ['label' => 'Documento', 'value' => (string) ($definition['program']['title'] ?? 'Documento regulado')],
                ['label' => 'Track', 'value' => (string) ($document['track'] ?? 'fiscal')],
                ['label' => 'Tipo', 'value' => (string) ($document['documentType'] ?? 'regulated_document')],
                ['label' => 'IssueId', 'value' => (string) ($prepared['issueId'] ?? '')],
            ],
            'parameterFields' => $parameterFields,
            'summary' => [
                ['label' => 'Compliance', 'value' => (string) ($document['complianceProfile'] ?? 'near_homologated')],
                ['label' => 'Fonte', 'value' => (string) (($document['source']['type'] ?? 'operational') === 'analytic' ? 'Analytics' : 'Operacional')],
                ['label' => 'Linhas', 'value' => count((array) ($canonical['rows'] ?? []))],
                ['label' => 'Estado', 'value' => (string) ($prepared['state'] ?? 'prepared')],
            ],
            'table' => [
                'columns' => is_array($canonical['columns'] ?? null) ? $canonical['columns'] : [],
                'rows' => is_array($canonical['rows'] ?? null) ? $canonical['rows'] : [],
                'rowCount' => count((array) ($canonical['rows'] ?? [])),
            ],
            'totals' => is_array($canonical['totals'] ?? null) ? $canonical['totals'] : [],
            'sections' => [
                [
                    'id' => 'escopo',
                    'title' => 'Escopo do modulo regulado',
                    'lines' => [
                        'Trilha separada de reports e special_document.',
                        'Contrato fechado, sem template livre e sem JavaScript vindo do metadado.',
                        'Base geral pronta para fiscal, banking e logistics sem prometer homologacao final nesta etapa.',
                    ],
                ],
                [
                    'id' => 'retencao',
                    'title' => 'Retencao e conferencia',
                    'lines' => [
                        'Hash publico: ' . (($document['verification']['enabled'] ?? true) !== false ? 'habilitado' : 'desligado'),
                        'Guardar payload: ' . ((($document['artifactPolicy']['storeCanonicalPayload'] ?? true) !== false) ? 'sim' : 'nao'),
                        'Guardar artefato: ' . ((($document['artifactPolicy']['storeArtifact'] ?? true) === true) ? 'sim' : 'nao'),
                    ],
                ],
            ],
            'canonicalPayload' => $canonical,
            'message' => 'Preview estruturado pronto para emissao controlada.',
        ];
    }

    /**
     * @param array<string, mixed> $document
     * @param array<string, mixed> $parameters
     * @return list<array<string, mixed>>
     */
    private function validateParameters(array $document, array $parameters): array
    {
        $validation = [];
        foreach ((array) ($document['parameters'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = trim((string) ($item['id'] ?? $item['field'] ?? ''));
            if ($id === '') {
                continue;
            }
            $required = ($item['required'] ?? false) === true;
            if ($required && (!array_key_exists($id, $parameters) || $parameters[$id] === '' || $parameters[$id] === null)) {
                $validation[] = [
                    'level' => 'error',
                    'code' => 'PARAMETER_REQUIRED',
                    'message' => 'Parametro obrigatorio nao informado: ' . $id . '.',
                    'field' => $id,
                    'blocking' => true,
                ];
            }
        }

        return $validation;
    }

    private function hasBlockingValidation(array $validation): bool
    {
        foreach ($validation as $item) {
            if (is_array($item) && (($item['blocking'] ?? false) === true || ($item['level'] ?? '') === 'error')) {
                return true;
            }
        }

        return false;
    }

    private function firstBlockingMessage(array $validation): string
    {
        foreach ($validation as $item) {
            if (!is_array($item)) {
                continue;
            }
            if ((($item['blocking'] ?? false) === true || ($item['level'] ?? '') === 'error') && !empty($item['message'])) {
                return (string) $item['message'];
            }
        }

        return 'Falha de validacao estrutural.';
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function assertSafeDefinition(array $definition): void
    {
        $document = is_array($definition['regulatedDocument'] ?? null) ? $definition['regulatedDocument'] : [];
        $layout = is_array($document['layout'] ?? null) ? $document['layout'] : [];
        foreach (['title', 'subtitle', 'notes'] as $key) {
            $value = (string) ($layout[$key] ?? '');
            if ($value !== '' && preg_match('/<[^>]+>|javascript:|onload=|onclick=|<script/i', $value) === 1) {
                throw new RuntimeHttpException('REGULATED_DOCUMENT_UNSAFE_METADATA', 'Metadado inseguro no documento regulado.', 422, [
                    'field' => 'regulatedDocument.layout.' . $key,
                ]);
            }
        }
    }

    /**
     * @param array<string, mixed> $entity
     * @return list<string>
     */
    private function selectDocumentFieldCodes(array $entity): array
    {
        return array_keys(is_array($entity['fields'] ?? null) ? $entity['fields'] : []);
    }

    /**
     * @param array<string, mixed> $entity
     */
    private function applyEntityIsolation(\Doctrine\DBAL\Query\QueryBuilder $qb, array $entity, ?string $tenantId): void
    {
        $subscriber = is_array($entity['subscriberIsolation'] ?? null) ? $entity['subscriberIsolation'] : [];
        if (($subscriber['enabled'] ?? false) === true && trim((string) ($subscriber['column'] ?? '')) !== '' && $tenantId) {
            $qb->andWhere('base.' . $this->quoteIdentifier((string) $subscriber['column']) . ' = :tenantId')->setParameter('tenantId', $tenantId);
        }
        $softDelete = is_array($entity['softDelete'] ?? null) ? $entity['softDelete'] : [];
        if (($softDelete['enabled'] ?? false) === true && trim((string) ($softDelete['deletedAtColumn'] ?? '')) !== '') {
            $qb->andWhere('base.' . $this->quoteIdentifier((string) $softDelete['deletedAtColumn']) . ' IS NULL');
        }
    }

    /**
     * @param array<string, mixed> $entity
     * @param array<int, array<string, mixed>> $parameterDefinitions
     * @param array<string, mixed> $parameterValues
     */
    private function applyParameterFilters(\Doctrine\DBAL\Query\QueryBuilder $qb, array $entity, array $parameterDefinitions, array $parameterValues): void
    {
        foreach ($parameterDefinitions as $index => $parameter) {
            if (!is_array($parameter)) {
                continue;
            }
            $fieldCode = trim((string) ($parameter['field'] ?? $parameter['id'] ?? ''));
            if ($fieldCode === '' || !array_key_exists($parameter['id'] ?? $fieldCode, $parameterValues)) {
                continue;
            }
            $field = $entity['fields'][$fieldCode] ?? null;
            if (!is_array($field) || trim((string) ($field['column'] ?? '')) === '') {
                continue;
            }
            $value = $parameterValues[$parameter['id'] ?? $fieldCode];
            if ($value === '' || $value === null) {
                continue;
            }
            $operator = strtolower(trim((string) ($parameter['operator'] ?? 'eq')));
            $column = 'base.' . $this->quoteIdentifier((string) $field['column']);
            $paramName = 'param' . $index;
            if ($operator === 'contains') {
                $qb->andWhere($column . ' LIKE :' . $paramName)->setParameter($paramName, '%' . $value . '%');
            } else {
                $qb->andWhere($column . ' = :' . $paramName)->setParameter($paramName, $value);
            }
        }
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
                $value = is_array($row) ? ($row[$field] ?? null) : null;
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

    private function stringy(mixed $value): string
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

    private function buildHtmlArtifact(array $result): string
    {
        $title = htmlspecialchars((string) ($result['title'] ?? 'Documento regulado'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $subtitle = htmlspecialchars((string) ($result['subtitle'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>' . $title . '</title>'
            . '<style>body{font-family:Arial,sans-serif;color:#1f2937;margin:24px}h1{margin:0 0 4px}h2{margin:20px 0 8px}.meta{display:flex;gap:16px;flex-wrap:wrap;margin:12px 0}.card,.pair,.section{border:1px solid #d6dde6;border-radius:6px;padding:10px 12px;background:#fff}.card{min-width:180px}.pair{margin:0 0 8px}.section{margin-top:12px}table{width:100%;border-collapse:collapse;margin-top:8px}th,td{border:1px solid #d6dde6;padding:8px;text-align:left}th{background:#f5f7fa}td.num{text-align:right}</style>'
            . '</head><body><h1>' . $title . '</h1><p>' . $subtitle . '</p><div class="meta">';
        foreach ((array) ($result['summary'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $html .= '<div class="card"><strong>' . htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>'
                . htmlspecialchars($this->stringifyValue($item['value'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
        }
        $html .= '</div><h2>Cabecalho</h2>';
        foreach ((array) ($result['headerFields'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $html .= '<p class="pair"><strong>' . htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>'
                . htmlspecialchars($this->stringifyValue($item['value'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        }
        if (!empty($result['parameterFields'])) {
            $html .= '<h2>Parametros</h2>';
            foreach ((array) ($result['parameterFields'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $html .= '<p class="pair"><strong>' . htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>'
                    . htmlspecialchars($this->stringifyValue($item['value'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
            }
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
        $html .= '<h2>Observacoes</h2>';
        foreach ((array) ($result['sections'] ?? []) as $section) {
            if (!is_array($section)) {
                continue;
            }
            $html .= '<div class="section"><strong>' . htmlspecialchars((string) ($section['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>';
            foreach ((array) ($section['lines'] ?? []) as $line) {
                $html .= '<div>' . htmlspecialchars((string) $line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
            }
            $html .= '</div>';
        }
        $html .= '</body></html>';

        return $html;
    }

    private function buildPdfArtifact(array $result): string
    {
        $lines = [
            (string) ($result['title'] ?? 'Documento regulado'),
            (string) ($result['subtitle'] ?? ''),
            'Track: ' . (string) ($result['track'] ?? ''),
            'Tipo: ' . (string) ($result['documentType'] ?? ''),
            'IssueId: ' . (string) ($result['issueId'] ?? ''),
            ' ',
        ];
        foreach ((array) ($result['headerFields'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $lines[] = (string) ($item['label'] ?? '') . ': ' . $this->stringifyValue($item['value'] ?? '');
        }
        foreach ((array) ($result['parameterFields'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $lines[] = 'Parametro - ' . (string) ($item['label'] ?? '') . ': ' . $this->stringifyValue($item['value'] ?? '');
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
                $lines[] = $field . ': ' . $this->stringifyValue($value);
            }
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

    /**
     * @return array<string, mixed>
     */
    private function resolvePreparedRecord(string $screenId, array $payload): array
    {
        $issueId = trim((string) ($payload['issueId'] ?? ''));
        if ($issueId !== '') {
            $record = $this->store->findByIssueId($issueId);
            if ($record) {
                return $record;
            }
        }

        return $this->prepare($screenId, $payload);
    }

    private function newIssueId(): string
    {
        return 'rdoc-' . bin2hex(random_bytes(6));
    }

    private function buildVerificationUrl(string $path, string $hash): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        return $path . '?hash=' . rawurlencode($hash);
    }

    private function stringifyValue(mixed $value): string
    {
        return $this->stringy($value);
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
        $clean = preg_replace('/[^A-Za-z0-9._-]+/', '-', $value) ?: 'documento-regulado';
        return trim($clean, '-');
    }
}
