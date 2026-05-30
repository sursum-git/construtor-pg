<?php

namespace App\Builder;

use App\Entity\BuilderApiSource;
use App\Entity\BuilderEntity;
use App\Entity\BuilderEditorLock;
use App\Entity\BuilderModule;
use App\Entity\BuilderEntityVersion;
use App\Entity\BuilderField;
use App\Entity\BuilderProgramVersion;
use App\Entity\ProgramChangeGrant;
use App\Entity\ProgramChangeRequest;
use App\Entity\Program;
use App\Entity\RuntimeEndpoint;
use App\Entity\ScreenDefinition;
use App\Odoo\OdooClient;
use App\Repository\BuilderEntityRepository;
use App\Repository\BuilderApiSourceRepository;
use App\Repository\BuilderEditorLockRepository;
use App\Repository\BuilderModuleRepository;
use App\Repository\BuilderEntityVersionRepository;
use App\Repository\BuilderFieldRepository;
use App\Repository\BuilderProgramVersionRepository;
use App\Repository\ProgramRepository;
use App\Repository\RuntimeEndpointRepository;
use App\Repository\ScreenDefinitionRepository;
use App\Runtime\ProgramOverlayService;
use App\Runtime\ProgramGovernanceService;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeEnvironmentIdentityResolver;
use App\Runtime\RuntimeEventService;
use App\Runtime\RuntimeHttpException;
use App\Runtime\RuntimeNotificationService;
use App\Runtime\RuntimeSessionGuard;
use App\Runtime\StructuralIntegrityService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class ProgramBuilderService
{
    private const EDITOR_LOCK_TTL_SECONDS = 180;
    private const EDITOR_LOCK_HEARTBEAT_SECONDS = 45;

    private const FIELD_ABBREVIATIONS = [
        'abrev' => 'Abreviado',
        'acum' => 'Acumulado',
        'apg' => 'A pagar',
        'arec' => 'A receber',
        'atu' => 'Atualizacao',
        'bruto' => 'Bruto',
        'cc' => 'Centro de custo',
        'cli' => 'Cliente',
        'cobr' => 'Cobranca',
        'cod' => 'Codigo',
        'cond' => 'Condicao',
        'cred' => 'Credito',
        'ct' => 'Conta',
        'desc' => 'Desconto',
        'descr' => 'Descricao',
        'dif' => 'Diferenca',
        'dupl' => 'Duplicata',
        'email' => 'Email',
        'emis' => 'Emissao',
        'end' => 'Endereco',
        'entr' => 'Entrada',
        'esp' => 'Especial',
        'espec' => 'Especifico',
        'fim' => 'Fim',
        'forn' => 'Fornecedor',
        'id' => 'Campo unico',
        'inf' => 'Informacao',
        'ini' => 'Inicio',
        'inscr' => 'Inscricao',
        'lib' => 'Liberado',
        'lim' => 'Limite',
        'liq' => 'Liquido',
        'mo' => 'Moeda',
        'mod' => 'Modelo',
        'nat' => 'Natureza',
        'nf' => 'Nota fiscal',
        'nome' => 'Nome',
        'nr' => 'Numero',
        'docto' => 'Documento',
        'num' => 'Numero',
        'obs' => 'Observacao',
        'ori' => 'Origem',
        'orig' => 'Original',
        'pagto' => 'Pagamento',
        'pct' => 'Pacote',
        'perc' => 'Percentual',
        'prox' => 'Proximo',
        'qt' => 'Quantidade',
        'rend' => 'Rendimento',
        'repres' => 'Representante',
        'saida' => 'Saida',
        'seq' => 'Sequencia',
        'sit' => 'Situacao',
        'tel' => 'Telefone',
        'tit' => 'Titulo',
        'tp' => 'Tipo',
        'tx' => 'Taxa',
        'vl' => 'Valor',
    ];

    public function __construct(
        private readonly BuilderEntityRepository $entities,
        private readonly BuilderApiSourceRepository $apiSources,
        private readonly BuilderEditorLockRepository $editorLocks,
        private readonly BuilderModuleRepository $modules,
        private readonly BuilderFieldRepository $fields,
        private readonly BuilderEntityVersionRepository $entityVersions,
        private readonly BuilderProgramVersionRepository $versions,
        private readonly ProgramRepository $programs,
        private readonly ScreenDefinitionRepository $screens,
        private readonly RuntimeEndpointRepository $endpoints,
        private readonly EntityManagerInterface $entityManager,
        private readonly StructuralIntegrityService $integrity,
        private readonly ProgramGovernanceService $governance,
        private readonly ProgramOverlayService $overlays,
        private readonly RuntimeNotificationService $notifications,
        private readonly RuntimeEnvironmentIdentityResolver $environmentIdentity,
        private readonly PermissionResolver $permissions,
        private readonly RuntimeSessionGuard $sessions,
        private readonly OdooClient $odoo,
        private readonly RuntimeEventService $events,
    ) {
    }

    public function bootstrap(): array
    {
        $this->assertAdminRead();

        $entities = [];
        foreach ($this->entities->findBy([], ['name' => 'ASC']) as $entity) {
            $versioningConfig = $entity->getMetadata()['versioning'] ?? [];
            $fieldCount = 0;
            $hasPrimaryKey = false;
            $foreignKeysCount = 0;
            foreach ($entity->getFields() as $field) {
                ++$fieldCount;
                if ($field->isPrimaryKey()) {
                    $hasPrimaryKey = true;
                }
                $options = $field->getOptions();
                if (is_array($options['foreignKey'] ?? null)) {
                    ++$foreignKeysCount;
                }
            }
            $rulesCount = count($entity->getMetadata()['rules'] ?? []);
            $uniqueKeysCount = count($entity->getMetadata()['uniqueKeys'] ?? []);
            $entities[] = [
                'code' => $entity->getCode(),
                'name' => $entity->getName(),
                'entityType' => $entity->getEntityType(),
                'status' => $entity->getStatus(),
                'tableName' => $entity->getTableName(),
                'versioningEnabled' => ($versioningConfig['enabled'] ?? false) === true,
                'fieldsCount' => $fieldCount,
                'hasPrimaryKey' => $hasPrimaryKey,
                'foreignKeysCount' => $foreignKeysCount,
                'rulesCount' => $rulesCount,
                'uniqueKeysCount' => $uniqueKeysCount,
            ];
        }

        $modules = [];
        foreach ($this->modules->findBy([], ['numberStart' => 'ASC', 'code' => 'ASC']) as $module) {
            $modules[] = $this->modulePayload($module);
        }

        $programs = [];
        foreach ($this->programs->findBy([], ['code' => 'ASC']) as $program) {
            $published = $this->versions->findPublishedByProgramCode($program->getCode());
            $latest = $this->versions->findByProgramCodeOrdered($program->getCode())[0] ?? null;
            $programs[] = [
                'code' => $program->getCode(),
                'title' => $program->getTitle(),
                'module' => $program->getModule(),
                'programType' => $program->getProgramType(),
                'screenId' => $program->getScreenId(),
                'status' => $program->getStatus(),
                'programOrigin' => $program->getProgramOrigin(),
                'ownerScope' => $program->getOwnerScope(),
                'customizationPolicy' => $program->getCustomizationPolicy(),
                'subscriberId' => $program->getSubscriberId(),
                'baseProgramCode' => $program->getBaseProgramCode(),
                'baseProgramVersionId' => $program->getBaseProgramVersionId(),
                'upgradeFrozen' => $program->isUpgradeFrozen(),
                'frozenReason' => $program->getFrozenReason(),
                'builderEntityCode' => $published?->getBuilderEntityCode() ?? $latest?->getBuilderEntityCode(),
                'publishedVersion' => $published?->getVersion(),
                'updatedAt' => $program->getUpdatedAt()->format(DATE_ATOM),
            ];
        }

        $apiSources = [];
        foreach ($this->apiSources->findBy([], ['name' => 'ASC']) as $source) {
            $this->integrity->assertApiSource($source);
            $apiSources[] = $this->apiSourceSummaryPayload($source);
        }

        return [
            'entities' => $entities,
            'modules' => $modules,
            'programs' => $programs,
            'apiSources' => $apiSources,
            'currentUser' => $this->permissions->getCurrentUserPayload(),
        ];
    }

    public function getApiSource(string $code): array
    {
        $this->assertAdminRead();
        $source = $this->apiSources->findOneBy(['code' => $this->safeCode($code)]);
        if (!$source) {
            throw new RuntimeHttpException('API_SOURCE_NOT_FOUND', 'Cadastro de API nao encontrado.', 404, [
                'code' => $code,
            ]);
        }
        $this->integrity->assertApiSource($source);

        return [
            'apiSource' => $this->apiSourcePayload($source),
        ];
    }

    public function saveApiSource(array $payload): array
    {
        $this->assertAdminWrite();
        $config = $this->normalizeApiSourceRegistryPayload($payload);
        $source = $this->apiSources->findOneBy(['code' => $config['code']]) ?? new BuilderApiSource();
        $existingMetadata = $source->getMetadata();

        $metadata = [
            'providerType' => $config['providerType'],
            'timeoutSeconds' => $config['timeoutSeconds'],
            'authHeaders' => $this->restoreMaskedApiHeaderSecrets($config['authHeaders'], is_array($existingMetadata['authHeaders'] ?? null) ? $existingMetadata['authHeaders'] : []),
            'operations' => $this->restoreMaskedApiOperationSecrets($config['operations'], is_array($existingMetadata['operations'] ?? null) ? $existingMetadata['operations'] : []),
            'odoo' => $this->restoreMaskedOdooSourceSecrets(
                is_array($config['odoo'] ?? null) ? $config['odoo'] : [],
                is_array($existingMetadata['odoo'] ?? null) ? $existingMetadata['odoo'] : []
            ),
        ];

        $source
            ->setCode($config['code'])
            ->setName($config['name'])
            ->setAuthMode($config['authMode'])
            ->setBaseUrl($config['baseUrl'])
            ->setOpenapiUrl($config['openapiUrl'])
            ->setStatus($config['status'])
            ->setMetadata($metadata);

        $this->entityManager->persist($source);
        $this->entityManager->flush();
        $this->integrity->signApiSource($source, ['source' => 'saveApiSource']);
        $this->entityManager->flush();

        return [
            'apiSource' => $this->apiSourcePayload($source),
        ];
    }

    public function importOpenApi(array $payload): array
    {
        $this->assertAdminWrite();

        if (strtolower(trim((string) ($payload['providerType'] ?? 'generic'))) === 'odoo') {
            throw new RuntimeHttpException('API_OPENAPI_PROVIDER_INVALID', 'Importacao OpenAPI nao se aplica ao provedor Odoo.', 422);
        }

        $documentUrl = trim((string) ($payload['openapiUrl'] ?? $payload['url'] ?? ''));
        if ($documentUrl === '') {
            throw new RuntimeHttpException('API_OPENAPI_URL_REQUIRED', 'Informe a URL do documento OpenAPI.', 422);
        }

        $document = $this->fetchRemoteApiDocument($documentUrl);
        $parsed = $this->parseOpenApiDocument($document, $documentUrl);
        $code = $this->safeCode((string) ($payload['code'] ?? $this->slugToCode((string) ($parsed['info']['title'] ?? 'api-externa'))));
        $name = trim((string) ($payload['name'] ?? ($parsed['info']['title'] ?? 'API externa')));
        $requestedBaseUrl = trim((string) ($payload['baseUrl'] ?? ''));
        $baseUrl = $requestedBaseUrl !== '' ? $requestedBaseUrl : trim((string) ($this->extractOpenApiBaseUrl($parsed) ?? ''));
        $operations = $this->extractOpenApiOperations($parsed, $baseUrl);

        return [
            'apiSourceDraft' => [
                'code' => $code,
                'name' => $name,
                'authMode' => 'none',
                'baseUrl' => $baseUrl,
                'openapiUrl' => $documentUrl,
                'status' => 'active',
                'timeoutSeconds' => 20,
                'authHeaders' => [],
                'operations' => $operations,
            ],
            'diagnostics' => [
                [
                    'level' => 'info',
                    'message' => 'Documento OpenAPI importado. Revise operacoes, autenticacao e paths antes de salvar.',
                ],
            ],
        ];
    }

    public function testOdooConnection(array $payload): array
    {
        $this->assertAdminWrite();
        $config = $this->resolveOdooSourceConfig($payload);
        $result = $this->odoo->testConnection($config);

        return [
            'connection' => $result,
            'diagnostics' => [
                [
                    'level' => 'info',
                    'message' => 'Conexao com o Odoo validada com sucesso.',
                ],
                [
                    'level' => 'warning',
                    'message' => 'XML-RPC e JSON-RPC legados estao descontinuados na documentacao mais recente do Odoo; o cadastro ja fica marcado como pronto para migracao futura a JSON-2.',
                ],
            ],
        ];
    }

    public function readOdooModelMetadata(array $payload): array
    {
        $this->assertAdminWrite();
        $config = $this->resolveOdooSourceConfig($payload);
        if (trim((string) ($config['model'] ?? '')) === '') {
            throw new RuntimeHttpException('ODOO_MODEL_REQUIRED', 'Informe o modelo Odoo antes de ler os metadados.', 422);
        }

        $rawFields = $this->odoo->fieldsGet($config);
        $mappedFields = [];
        $diagnostics = [
            [
                'level' => 'warning',
                'message' => 'Campos relacionais complexos do Odoo entram apenas em leitura nesta fase.',
            ],
        ];

        foreach ($rawFields as $fieldName => $definition) {
            if (!is_array($definition)) {
                continue;
            }
            $mapped = $this->mapOdooModelField((string) $fieldName, $definition);
            if ($mapped === null) {
                continue;
            }
            if (($mapped['dataType'] ?? '') === 'json') {
                $diagnostics[] = [
                    'level' => 'info',
                    'message' => 'Campo ' . $mapped['code'] . ' foi mapeado como json por ser relacional ou multivalorado.',
                ];
            }
            $mappedFields[] = $mapped;
        }

        usort($mappedFields, static fn (array $left, array $right): int => strcmp((string) $left['code'], (string) $right['code']));

        return [
            'model' => $config['model'],
            'fields' => $mappedFields,
            'diagnostics' => $diagnostics,
        ];
    }

    public function listDatabaseTables(array $payload = []): array
    {
        $this->assertAdminRead();

        $filter = strtolower(trim((string) ($payload['filter'] ?? '')));
        $limit = max(1, min(500, (int) ($payload['limit'] ?? 200)));
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            "SELECT table_schema, table_name
               FROM information_schema.tables
              WHERE table_type = 'BASE TABLE'
                AND table_schema NOT IN ('pg_catalog', 'information_schema')
              ORDER BY table_schema, table_name"
        );

        $existingByQualified = [];
        foreach ($this->entities->findBy([], ['code' => 'ASC']) as $entity) {
            $tableName = strtolower(trim((string) $entity->getTableName()));
            if ($tableName !== '') {
                $existingByQualified['public.' . $tableName] = $entity->getCode();
            }
        }

        $tables = [];
        foreach ($rows as $row) {
            $schema = strtolower((string) ($row['table_schema'] ?? 'public'));
            $tableName = strtolower((string) ($row['table_name'] ?? ''));
            if (!$this->isImportableTable($schema, $tableName)) {
                continue;
            }

            $qualifiedName = $schema . '.' . $tableName;
            if ($filter !== '' && str_contains($qualifiedName, $filter) === false && str_contains($tableName, $filter) === false) {
                continue;
            }

            $tables[] = [
                'schema' => $schema,
                'tableName' => $tableName,
                'qualifiedName' => $qualifiedName,
                'existingEntityCode' => $existingByQualified[$qualifiedName] ?? null,
            ];

            if (count($tables) >= $limit) {
                break;
            }
        }

        return ['tables' => $tables];
    }

    public function inspectDatabaseTable(array $payload): array
    {
        $this->assertAdminRead();

        [$schema, $tableName] = $this->normalizeDatabaseTableTarget($payload);
        $table = $this->loadDatabaseTableMetadata($schema, $tableName);
        $entityDraft = $this->buildImportedEntityDraft($table, $payload);
        $programDraft = $this->buildImportedProgramDraft($entityDraft, $payload);

        return [
            'table' => [
                'schema' => $schema,
                'tableName' => $tableName,
                'qualifiedName' => $schema . '.' . $tableName,
            ],
            'classification' => $table['classification'],
            'diagnostics' => $table['diagnostics'],
            'entityDraft' => $entityDraft,
            'programDraft' => $programDraft,
        ];
    }

    public function importDatabaseTable(array $payload): array
    {
        $this->assertAdminWrite();

        $inspection = $this->inspectDatabaseTable($payload);
        $entityConfig = $this->normalizeEntityPayload($inspection['entityDraft']);
        $entity = $this->applyEntityConfig($entityConfig);
        $entityVersion = $this->createEntityVersionSnapshot($entity, 'import', 'Entidade importada do banco de dados.');

        $programVersion = null;
        $programPayload = $inspection['programDraft'];
        $generateProgramDraft = ($payload['generateProgramDraft'] ?? true) !== false;
        if ($generateProgramDraft && (string) ($programPayload['programCode'] ?? '') !== '' && (string) ($programPayload['module'] ?? '') !== '' && (string) ($programPayload['screenId'] ?? '') !== '') {
            $programVersion = $this->saveDraft($programPayload);
        }

        return [
            'table' => $inspection['table'],
            'classification' => $inspection['classification'],
            'diagnostics' => $inspection['diagnostics'],
            'entity' => $this->entityPayload($entity),
            'entityVersion' => $this->entityVersionPayload($entityVersion),
            'entityVersions' => array_map(fn (BuilderEntityVersion $item): array => $this->entityVersionPayload($item), $this->entityVersions->findByEntityCodeOrdered($entity->getCode())),
            'programVersion' => $programVersion,
            'programDraftGenerated' => $programVersion !== null,
        ];
    }

    public function inspectDatabaseDdl(array $payload): array
    {
        $this->assertAdminRead();

        $table = $this->parseDatabaseDdl((string) ($payload['ddl'] ?? ''));
        $entityDraft = $this->buildImportedEntityDraft($table, $payload);
        $programDraft = $this->buildImportedProgramDraft($entityDraft, array_merge([
            'subtitle' => 'Gerado a partir de script de tabela',
            'icon' => 'file-txt',
            'changeSummary' => 'Rascunho gerado por importacao de script SQL/DDL.',
        ], $payload));

        return [
            'table' => [
                'schema' => $table['schema'],
                'tableName' => $table['tableName'],
                'qualifiedName' => $table['schema'] . '.' . $table['tableName'],
            ],
            'classification' => $table['classification'],
            'diagnostics' => $table['diagnostics'],
            'entityDraft' => $entityDraft,
            'programDraft' => $programDraft,
            'source' => 'ddl',
        ];
    }

    public function importDatabaseDdl(array $payload): array
    {
        $this->assertAdminWrite();

        $inspection = $this->inspectDatabaseDdl($payload);
        $entityConfig = $this->normalizeEntityPayload($inspection['entityDraft']);
        $entity = $this->applyEntityConfig($entityConfig);
        $entityVersion = $this->createEntityVersionSnapshot($entity, 'import', 'Entidade importada de script SQL/DDL.');

        $programVersion = null;
        $programPayload = $inspection['programDraft'];
        $generateProgramDraft = ($payload['generateProgramDraft'] ?? true) !== false;
        if ($generateProgramDraft && (string) ($programPayload['programCode'] ?? '') !== '' && (string) ($programPayload['module'] ?? '') !== '' && (string) ($programPayload['screenId'] ?? '') !== '') {
            $programVersion = $this->saveDraft($programPayload);
        }

        return [
            'table' => $inspection['table'],
            'classification' => $inspection['classification'],
            'diagnostics' => $inspection['diagnostics'],
            'entity' => $this->entityPayload($entity),
            'entityVersion' => $this->entityVersionPayload($entityVersion),
            'entityVersions' => array_map(fn (BuilderEntityVersion $item): array => $this->entityVersionPayload($item), $this->entityVersions->findByEntityCodeOrdered($entity->getCode())),
            'programVersion' => $programVersion,
            'programDraftGenerated' => $programVersion !== null,
            'source' => 'ddl',
        ];
    }

    private function normalizeDatabaseTableTarget(array $payload): array
    {
        $qualifiedName = strtolower(trim((string) ($payload['qualifiedName'] ?? '')));
        $schema = strtolower(trim((string) ($payload['schema'] ?? 'public')));
        $tableName = strtolower(trim((string) ($payload['tableName'] ?? '')));

        if ($qualifiedName !== '' && str_contains($qualifiedName, '.')) {
            [$schema, $tableName] = explode('.', $qualifiedName, 2);
        }

        if ($schema === '' || $tableName === '') {
            throw new RuntimeHttpException('PROGRAM_BUILDER_IMPORT_TABLE_REQUIRED', 'Informe a tabela do banco que sera importada.', 422);
        }
        if (!$this->isImportableTable($schema, $tableName)) {
            throw new RuntimeHttpException('PROGRAM_BUILDER_IMPORT_TABLE_NOT_ALLOWED', 'Esta tabela nao pode ser importada pelo construtor.', 422, [
                'schema' => $schema,
                'tableName' => $tableName,
            ]);
        }

        return [$schema, $tableName];
    }

    private function loadDatabaseTableMetadata(string $schema, string $tableName): array
    {
        $connection = $this->entityManager->getConnection();
        $exists = $connection->fetchOne(
            "SELECT 1
               FROM information_schema.tables
              WHERE table_schema = :schema
                AND table_name = :table
                AND table_type = 'BASE TABLE'",
            ['schema' => $schema, 'table' => $tableName]
        );
        if (!$exists) {
            throw new RuntimeHttpException('PROGRAM_BUILDER_IMPORT_TABLE_NOT_FOUND', 'Tabela nao encontrada no banco de dados.', 404, [
                'schema' => $schema,
                'tableName' => $tableName,
            ]);
        }

        $columns = $connection->fetchAllAssociative(
            "SELECT column_name,
                    data_type,
                    udt_name,
                    is_nullable,
                    column_default,
                    character_maximum_length,
                    numeric_precision,
                    numeric_scale,
                    ordinal_position
               FROM information_schema.columns
              WHERE table_schema = :schema
                AND table_name = :table
              ORDER BY ordinal_position",
            ['schema' => $schema, 'table' => $tableName]
        );

        $primaryKeys = $connection->fetchFirstColumn(
            "SELECT kcu.column_name
               FROM information_schema.table_constraints tc
               JOIN information_schema.key_column_usage kcu
                 ON tc.constraint_name = kcu.constraint_name
                AND tc.table_schema = kcu.table_schema
                AND tc.table_name = kcu.table_name
              WHERE tc.table_schema = :schema
                AND tc.table_name = :table
                AND tc.constraint_type = 'PRIMARY KEY'
              ORDER BY kcu.ordinal_position",
            ['schema' => $schema, 'table' => $tableName]
        );

        $uniqueRows = $connection->fetchAllAssociative(
            "SELECT tc.constraint_name,
                    json_agg(kcu.column_name ORDER BY kcu.ordinal_position) AS columns_json
               FROM information_schema.table_constraints tc
               JOIN information_schema.key_column_usage kcu
                 ON tc.constraint_name = kcu.constraint_name
                AND tc.table_schema = kcu.table_schema
                AND tc.table_name = kcu.table_name
              WHERE tc.table_schema = :schema
                AND tc.table_name = :table
                AND tc.constraint_type = 'UNIQUE'
              GROUP BY tc.constraint_name
              ORDER BY tc.constraint_name",
            ['schema' => $schema, 'table' => $tableName]
        );
        $uniqueConstraints = [];
        foreach ($uniqueRows as $row) {
            $columnsList = json_decode((string) ($row['columns_json'] ?? '[]'), true);
            if (!is_array($columnsList) || !$columnsList) {
                continue;
            }
            $uniqueConstraints[] = [
                'name' => (string) ($row['constraint_name'] ?? ''),
                'columns' => array_values(array_filter(array_map('strval', $columnsList))),
            ];
        }

        $foreignKeys = $connection->fetchAllAssociative(
            "SELECT kcu.column_name,
                    ccu.table_schema AS foreign_table_schema,
                    ccu.table_name AS foreign_table_name,
                    ccu.column_name AS foreign_column_name,
                    rc.update_rule,
                    rc.delete_rule,
                    tc.constraint_name
               FROM information_schema.table_constraints tc
               JOIN information_schema.key_column_usage kcu
                 ON tc.constraint_name = kcu.constraint_name
                AND tc.table_schema = kcu.table_schema
                AND tc.table_name = kcu.table_name
               JOIN information_schema.constraint_column_usage ccu
                 ON tc.constraint_name = ccu.constraint_name
                AND tc.table_schema = ccu.table_schema
               JOIN information_schema.referential_constraints rc
                 ON tc.constraint_name = rc.constraint_name
                AND tc.table_schema = rc.constraint_schema
              WHERE tc.table_schema = :schema
                AND tc.table_name = :table
                AND tc.constraint_type = 'FOREIGN KEY'
              ORDER BY kcu.ordinal_position",
            ['schema' => $schema, 'table' => $tableName]
        );
        $foreignKeysByColumn = [];
        foreach ($foreignKeys as $row) {
            $column = strtolower((string) ($row['column_name'] ?? ''));
            if ($column === '') {
                continue;
            }
            $foreignKeysByColumn[$column] = [
                'table' => strtolower((string) ($row['foreign_table_name'] ?? '')),
                'column' => strtolower((string) ($row['foreign_column_name'] ?? '')),
                'schema' => strtolower((string) ($row['foreign_table_schema'] ?? 'public')),
                'onDelete' => $this->normalizeForeignKeyAction($row['delete_rule'] ?? null),
                'onUpdate' => $this->normalizeForeignKeyAction($row['update_rule'] ?? null),
                'dependencyType' => 'reference',
            ];
        }

        $classification = $this->classifyImportedTable($tableName, $columns, $primaryKeys, $uniqueConstraints, $foreignKeysByColumn);
        $diagnostics = $this->buildImportDiagnostics($columns, $primaryKeys, $foreignKeysByColumn);

        return [
            'schema' => $schema,
            'tableName' => $tableName,
            'columns' => $columns,
            'primaryKeys' => array_values(array_map('strtolower', $primaryKeys)),
            'uniqueConstraints' => $uniqueConstraints,
            'foreignKeys' => $foreignKeysByColumn,
            'classification' => $classification,
            'diagnostics' => $diagnostics,
        ];
    }

    private function parseDatabaseDdl(string $ddl): array
    {
        $ddl = trim(str_replace("\0", '', $ddl));
        if ($ddl === '') {
            throw new RuntimeHttpException('PROGRAM_BUILDER_IMPORT_DDL_REQUIRED', 'Cole o script CREATE TABLE para analisar.', 422);
        }
        if (strlen($ddl) > 200000) {
            throw new RuntimeHttpException('PROGRAM_BUILDER_IMPORT_DDL_TOO_LARGE', 'O script SQL informado e muito grande para importacao direta.', 422);
        }

        $statements = $this->splitSqlStatements($ddl);
        $createTable = '';
        $comments = [];
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            if (preg_match('/^\s*create\s+table\b/i', $statement) === 1) {
                if ($createTable !== '') {
                    throw new RuntimeHttpException('PROGRAM_BUILDER_IMPORT_DDL_SINGLE_TABLE_ONLY', 'Informe apenas um CREATE TABLE por importacao.', 422);
                }
                $createTable = $statement;
                continue;
            }
            if (preg_match('/^\s*comment\s+on\s+(table|column)\b/i', $statement) === 1) {
                $comments[] = $statement;
                continue;
            }
            throw new RuntimeHttpException('PROGRAM_BUILDER_IMPORT_DDL_STATEMENT_NOT_ALLOWED', 'O importador aceita apenas CREATE TABLE e COMMENT ON TABLE/COLUMN.', 422, [
                'statement' => substr($statement, 0, 120),
            ]);
        }

        if ($createTable === '') {
            throw new RuntimeHttpException('PROGRAM_BUILDER_IMPORT_DDL_CREATE_TABLE_REQUIRED', 'O script precisa conter um CREATE TABLE.', 422);
        }

        $table = $this->parseCreateTableStatement($createTable);
        $this->applyDdlComments($table, $comments);
        $table['classification'] = $this->classifyImportedTable($table['tableName'], $table['columns'], $table['primaryKeys'], $table['uniqueConstraints'], $table['foreignKeys']);
        $table['diagnostics'] = array_merge(
            $this->buildImportDiagnostics($table['columns'], $table['primaryKeys'], $table['foreignKeys']),
            [[
                'level' => 'info',
                'message' => 'Script analisado sem executar comandos no schema real.',
            ]]
        );

        return $table;
    }

    private function parseCreateTableStatement(string $statement): array
    {
        $statement = trim($statement);
        $start = stripos($statement, '(');
        if ($start === false) {
            throw new RuntimeHttpException('PROGRAM_BUILDER_IMPORT_DDL_INVALID_CREATE', 'CREATE TABLE invalido: lista de colunas nao encontrada.', 422);
        }
        $prefix = trim(substr($statement, 0, $start));
        if (!preg_match('/^create\s+table\s+(?:if\s+not\s+exists\s+)?(.+)$/i', $prefix, $matches)) {
            throw new RuntimeHttpException('PROGRAM_BUILDER_IMPORT_DDL_INVALID_CREATE', 'CREATE TABLE invalido ou nao suportado.', 422);
        }

        $end = $this->findMatchingSqlParenthesis($statement, $start);
        if ($end === null) {
            throw new RuntimeHttpException('PROGRAM_BUILDER_IMPORT_DDL_INVALID_CREATE', 'CREATE TABLE invalido: parenteses nao balanceados.', 422);
        }

        [$schema, $tableName] = $this->parseQualifiedSqlName(trim($matches[1]));
        if (!$this->isImportableTable($schema, $tableName)) {
            throw new RuntimeHttpException('PROGRAM_BUILDER_IMPORT_TABLE_NOT_ALLOWED', 'Esta tabela nao pode ser importada pelo construtor.', 422, [
                'schema' => $schema,
                'tableName' => $tableName,
            ]);
        }

        $columns = [];
        $primaryKeys = [];
        $uniqueConstraints = [];
        $foreignKeys = [];
        $ordinal = 1;
        foreach ($this->splitSqlTopLevel(substr($statement, $start + 1, $end - $start - 1), ',') as $definition) {
            $definition = trim($definition);
            if ($definition === '') {
                continue;
            }

            if ($this->parseTableConstraintDefinition($definition, $tableName, $primaryKeys, $uniqueConstraints, $foreignKeys)) {
                continue;
            }

            $column = $this->parseColumnDefinition($definition, $ordinal, $tableName, $primaryKeys, $uniqueConstraints, $foreignKeys);
            if ($column !== null) {
                $columns[] = $column;
                ++$ordinal;
            }
        }

        if (!$columns) {
            throw new RuntimeHttpException('PROGRAM_BUILDER_IMPORT_DDL_NO_COLUMNS', 'Nenhuma coluna valida foi encontrada no CREATE TABLE.', 422);
        }

        return [
            'schema' => $schema,
            'tableName' => $tableName,
            'tableComment' => '',
            'columns' => $columns,
            'primaryKeys' => array_values(array_unique(array_map('strtolower', $primaryKeys))),
            'uniqueConstraints' => $uniqueConstraints,
            'foreignKeys' => $foreignKeys,
            'classification' => [],
            'diagnostics' => [],
        ];
    }

    private function parseColumnDefinition(array|string $definition, int $ordinal, string $tableName, array &$primaryKeys, array &$uniqueConstraints, array &$foreignKeys): ?array
    {
        $definition = trim((string) $definition);
        if (!preg_match('/^("[^"]+"|[A-Za-z_][A-Za-z0-9_]*)\s+(.+)$/s', $definition, $matches)) {
            throw new RuntimeHttpException('PROGRAM_BUILDER_IMPORT_DDL_COLUMN_INVALID', 'Definicao de coluna invalida no CREATE TABLE.', 422, [
                'definition' => substr($definition, 0, 120),
            ]);
        }

        $columnName = $this->normalizeSqlIdentifier($matches[1]);
        $rest = trim($matches[2]);
        $constraintOffset = $this->findColumnConstraintOffset($rest);
        $typeSql = trim($constraintOffset === null ? $rest : substr($rest, 0, $constraintOffset));
        $constraintsSql = trim($constraintOffset === null ? '' : substr($rest, $constraintOffset));
        $typeInfo = $this->mapDdlTypeToInformationSchema($typeSql);
        $defaultValue = $this->extractColumnDefault($constraintsSql);
        $isNullable = preg_match('/\bnot\s+null\b/i', $constraintsSql) === 1 ? 'NO' : 'YES';

        if (preg_match('/\bprimary\s+key\b/i', $constraintsSql) === 1) {
            $primaryKeys[] = $columnName;
            $isNullable = 'NO';
        }
        if (preg_match('/\bunique\b/i', $constraintsSql) === 1) {
            $uniqueConstraints[] = [
                'name' => 'uk_' . $tableName . '_' . $columnName,
                'columns' => [$columnName],
            ];
        }
        if (preg_match('/\breferences\s+((?:"[^"]+"|[A-Za-z_][A-Za-z0-9_]*)(?:\.(?:"[^"]+"|[A-Za-z_][A-Za-z0-9_]*))?)\s*\(([^)]+)\)/i', $constraintsSql, $fkMatches)) {
            [$foreignSchema, $foreignTable] = $this->parseQualifiedSqlName($fkMatches[1]);
            $foreignColumn = $this->normalizeSqlIdentifier(trim($fkMatches[2]));
            $foreignKeys[$columnName] = [
                'table' => $foreignTable,
                'column' => $foreignColumn,
                'schema' => $foreignSchema,
                'onDelete' => $this->extractForeignKeyAction($constraintsSql, 'delete'),
                'onUpdate' => $this->extractForeignKeyAction($constraintsSql, 'update'),
                'dependencyType' => 'reference',
            ];
        }

        return [
            'column_name' => $columnName,
            'data_type' => $typeInfo['data_type'],
            'udt_name' => $typeInfo['udt_name'],
            'is_nullable' => $isNullable,
            'column_default' => $defaultValue,
            'character_maximum_length' => $typeInfo['character_maximum_length'],
            'numeric_precision' => $typeInfo['numeric_precision'],
            'numeric_scale' => $typeInfo['numeric_scale'],
            'ordinal_position' => $ordinal,
            'column_comment' => '',
        ];
    }

    private function parseTableConstraintDefinition(string $definition, string $tableName, array &$primaryKeys, array &$uniqueConstraints, array &$foreignKeys): bool
    {
        $normalized = preg_replace('/^constraint\s+("[^"]+"|[A-Za-z_][A-Za-z0-9_]*)\s+/i', '', trim($definition), 1, $count);
        $constraintName = $count > 0 && preg_match('/^constraint\s+("[^"]+"|[A-Za-z_][A-Za-z0-9_]*)/i', trim($definition), $nameMatch)
            ? $this->normalizeSqlIdentifier($nameMatch[1])
            : '';
        $normalized = trim((string) $normalized);

        if (preg_match('/^primary\s+key\s*\(([^)]+)\)/i', $normalized, $matches)) {
            foreach ($this->parseSqlIdentifierList($matches[1]) as $column) {
                $primaryKeys[] = $column;
            }
            return true;
        }
        if (preg_match('/^unique\s*\(([^)]+)\)/i', $normalized, $matches)) {
            $columns = $this->parseSqlIdentifierList($matches[1]);
            if ($columns) {
                $uniqueConstraints[] = [
                    'name' => $constraintName !== '' ? $constraintName : 'uk_' . $tableName . '_' . implode('_', $columns),
                    'columns' => $columns,
                ];
            }
            return true;
        }
        if (preg_match('/^foreign\s+key\s*\(([^)]+)\)\s+references\s+((?:"[^"]+"|[A-Za-z_][A-Za-z0-9_]*)(?:\.(?:"[^"]+"|[A-Za-z_][A-Za-z0-9_]*))?)\s*\(([^)]+)\)(.*)$/is', $normalized, $matches)) {
            $columns = $this->parseSqlIdentifierList($matches[1]);
            [$foreignSchema, $foreignTable] = $this->parseQualifiedSqlName($matches[2]);
            $foreignColumns = $this->parseSqlIdentifierList($matches[3]);
            $tail = (string) ($matches[4] ?? '');
            foreach ($columns as $index => $column) {
                $foreignKeys[$column] = [
                    'table' => $foreignTable,
                    'column' => $foreignColumns[$index] ?? ($foreignColumns[0] ?? 'id'),
                    'schema' => $foreignSchema,
                    'onDelete' => $this->extractForeignKeyAction($tail, 'delete'),
                    'onUpdate' => $this->extractForeignKeyAction($tail, 'update'),
                    'dependencyType' => 'reference',
                ];
            }
            return true;
        }

        if (preg_match('/^(check|exclude)\b/i', $normalized) === 1) {
            return true;
        }

        return false;
    }

    private function applyDdlComments(array &$table, array $comments): void
    {
        foreach ($comments as $comment) {
            if (preg_match('/^comment\s+on\s+table\s+(.+?)\s+is\s+(.+)$/is', trim($comment), $matches)) {
                [$schema, $tableName] = $this->parseQualifiedSqlName(trim($matches[1]));
                if ($schema === $table['schema'] && $tableName === $table['tableName']) {
                    $table['tableComment'] = $this->unquoteSqlLiteral(trim($matches[2]));
                }
                continue;
            }
            if (preg_match('/^comment\s+on\s+column\s+(.+?)\s+is\s+(.+)$/is', trim($comment), $matches)) {
                $target = trim($matches[1]);
                $literal = $this->unquoteSqlLiteral(trim($matches[2]));
                $parts = array_map(fn (string $part): string => $this->normalizeSqlIdentifier($part), $this->splitSqlTopLevel($target, '.'));
                $columnName = end($parts);
                $targetTable = count($parts) >= 2 ? $parts[count($parts) - 2] : $table['tableName'];
                if ($targetTable !== $table['tableName'] || $columnName === false) {
                    continue;
                }
                foreach ($table['columns'] as &$column) {
                    if (($column['column_name'] ?? '') === $columnName) {
                        $column['column_comment'] = $literal;
                        break;
                    }
                }
                unset($column);
            }
        }
    }

    private function splitSqlStatements(string $sql): array
    {
        return $this->splitSqlTopLevel($sql, ';');
    }

    private function splitSqlTopLevel(string $value, string $separator): array
    {
        $items = [];
        $buffer = '';
        $depth = 0;
        $inSingle = false;
        $inDouble = false;
        $length = strlen($value);
        for ($i = 0; $i < $length; ++$i) {
            $char = $value[$i];
            $next = $i + 1 < $length ? $value[$i + 1] : '';
            if ($inSingle) {
                $buffer .= $char;
                if ($char === "'" && $next === "'") {
                    $buffer .= $next;
                    ++$i;
                    continue;
                }
                if ($char === "'") {
                    $inSingle = false;
                }
                continue;
            }
            if ($inDouble) {
                $buffer .= $char;
                if ($char === '"' && $next === '"') {
                    $buffer .= $next;
                    ++$i;
                    continue;
                }
                if ($char === '"') {
                    $inDouble = false;
                }
                continue;
            }
            if ($char === "'") {
                $inSingle = true;
                $buffer .= $char;
                continue;
            }
            if ($char === '"') {
                $inDouble = true;
                $buffer .= $char;
                continue;
            }
            if ($char === '(') {
                ++$depth;
            } elseif ($char === ')' && $depth > 0) {
                --$depth;
            }
            if ($char === $separator && $depth === 0) {
                $items[] = trim($buffer);
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        if (trim($buffer) !== '') {
            $items[] = trim($buffer);
        }
        return $items;
    }

    private function findMatchingSqlParenthesis(string $value, int $start): ?int
    {
        $depth = 0;
        $inSingle = false;
        $inDouble = false;
        $length = strlen($value);
        for ($i = $start; $i < $length; ++$i) {
            $char = $value[$i];
            $next = $i + 1 < $length ? $value[$i + 1] : '';
            if ($inSingle) {
                if ($char === "'" && $next === "'") {
                    ++$i;
                    continue;
                }
                if ($char === "'") {
                    $inSingle = false;
                }
                continue;
            }
            if ($inDouble) {
                if ($char === '"' && $next === '"') {
                    ++$i;
                    continue;
                }
                if ($char === '"') {
                    $inDouble = false;
                }
                continue;
            }
            if ($char === "'") {
                $inSingle = true;
                continue;
            }
            if ($char === '"') {
                $inDouble = true;
                continue;
            }
            if ($char === '(') {
                ++$depth;
            } elseif ($char === ')') {
                --$depth;
                if ($depth === 0) {
                    return $i;
                }
            }
        }
        return null;
    }

    private function parseQualifiedSqlName(string $value): array
    {
        $value = trim($value);
        $parts = array_map(fn (string $part): string => $this->normalizeSqlIdentifier($part), $this->splitSqlTopLevel($value, '.'));
        if (count($parts) === 1) {
            return ['public', $parts[0]];
        }
        return [$parts[count($parts) - 2], $parts[count($parts) - 1]];
    }

    private function normalizeSqlIdentifier(string $value): string
    {
        $value = trim($value);
        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            $value = str_replace('""', '"', substr($value, 1, -1));
        }
        $value = strtolower(trim($value));
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $value)) {
            throw new RuntimeHttpException('PROGRAM_BUILDER_IMPORT_DDL_IDENTIFIER_INVALID', 'Identificador SQL invalido ou nao suportado na importacao.', 422, [
                'identifier' => $value,
            ]);
        }
        return $value;
    }

    private function parseSqlIdentifierList(string $value): array
    {
        $columns = [];
        foreach ($this->splitSqlTopLevel($value, ',') as $column) {
            $columns[] = $this->normalizeSqlIdentifier(trim($column));
        }
        return array_values(array_filter($columns));
    }

    private function findColumnConstraintOffset(string $rest): ?int
    {
        $patterns = [
            '\bconstraint\b',
            '\bnot\s+null\b',
            '\bprimary\s+key\b',
            '\bdefault\b',
            '\breferences\b',
            '\bunique\b',
            '\bcheck\b',
            '\bgenerated\b',
            '\bcollate\b',
        ];
        $best = null;
        foreach ($patterns as $pattern) {
            if (preg_match('/' . $pattern . '/i', $rest, $matches, PREG_OFFSET_CAPTURE) === 1) {
                $offset = (int) $matches[0][1];
                $best = $best === null ? $offset : min($best, $offset);
            }
        }
        return $best;
    }

    private function mapDdlTypeToInformationSchema(string $typeSql): array
    {
        $typeSql = strtolower(trim(preg_replace('/\s+/', ' ', $typeSql) ?? ''));
        $length = null;
        $precision = null;
        $scale = null;

        if (preg_match('/^(character varying|varchar|character|char)\s*\((\d+)\)$/', $typeSql, $matches)) {
            $length = (int) $matches[2];
            return [
                'data_type' => in_array($matches[1], ['character', 'char'], true) ? 'character' : 'character varying',
                'udt_name' => in_array($matches[1], ['character', 'char'], true) ? 'bpchar' : 'varchar',
                'character_maximum_length' => $length,
                'numeric_precision' => null,
                'numeric_scale' => null,
            ];
        }
        if (preg_match('/^(numeric|decimal)\s*\((\d+)(?:\s*,\s*(\d+))?\)$/', $typeSql, $matches)) {
            $precision = (int) $matches[2];
            $scale = isset($matches[3]) ? (int) $matches[3] : 0;
            return [
                'data_type' => 'numeric',
                'udt_name' => 'numeric',
                'character_maximum_length' => null,
                'numeric_precision' => $precision,
                'numeric_scale' => $scale,
            ];
        }

        return match ($typeSql) {
            'serial', 'serial4', 'integer', 'int', 'int4' => ['data_type' => 'integer', 'udt_name' => 'int4', 'character_maximum_length' => null, 'numeric_precision' => 32, 'numeric_scale' => 0],
            'bigserial', 'serial8', 'bigint', 'int8' => ['data_type' => 'bigint', 'udt_name' => 'int8', 'character_maximum_length' => null, 'numeric_precision' => 64, 'numeric_scale' => 0],
            'smallserial', 'serial2', 'smallint', 'int2' => ['data_type' => 'smallint', 'udt_name' => 'int2', 'character_maximum_length' => null, 'numeric_precision' => 16, 'numeric_scale' => 0],
            'boolean', 'bool' => ['data_type' => 'boolean', 'udt_name' => 'bool', 'character_maximum_length' => null, 'numeric_precision' => null, 'numeric_scale' => null],
            'date' => ['data_type' => 'date', 'udt_name' => 'date', 'character_maximum_length' => null, 'numeric_precision' => null, 'numeric_scale' => null],
            'timestamp', 'timestamp without time zone' => ['data_type' => 'timestamp without time zone', 'udt_name' => 'timestamp', 'character_maximum_length' => null, 'numeric_precision' => null, 'numeric_scale' => null],
            'timestamp with time zone', 'timestamptz' => ['data_type' => 'timestamp with time zone', 'udt_name' => 'timestamptz', 'character_maximum_length' => null, 'numeric_precision' => null, 'numeric_scale' => null],
            'text' => ['data_type' => 'text', 'udt_name' => 'text', 'character_maximum_length' => null, 'numeric_precision' => null, 'numeric_scale' => null],
            'json' => ['data_type' => 'json', 'udt_name' => 'json', 'character_maximum_length' => null, 'numeric_precision' => null, 'numeric_scale' => null],
            'jsonb' => ['data_type' => 'jsonb', 'udt_name' => 'jsonb', 'character_maximum_length' => null, 'numeric_precision' => null, 'numeric_scale' => null],
            'uuid' => ['data_type' => 'uuid', 'udt_name' => 'uuid', 'character_maximum_length' => null, 'numeric_precision' => null, 'numeric_scale' => null],
            default => ['data_type' => $typeSql !== '' ? $typeSql : 'text', 'udt_name' => $typeSql !== '' ? $typeSql : 'text', 'character_maximum_length' => null, 'numeric_precision' => null, 'numeric_scale' => null],
        };
    }

    private function extractColumnDefault(string $constraintsSql): ?string
    {
        if (preg_match('/\bdefault\s+(.+?)(?=\s+\bconstraint\b|\s+\bnot\s+null\b|\s+\bprimary\s+key\b|\s+\breferences\b|\s+\bunique\b|\s+\bcheck\b|$)/is', $constraintsSql, $matches) !== 1) {
            return null;
        }
        $default = trim($matches[1]);
        return $default !== '' ? $default : null;
    }

    private function extractForeignKeyAction(string $sql, string $kind): ?string
    {
        if (preg_match('/\bon\s+' . preg_quote($kind, '/') . '\s+(cascade|restrict|set\s+null|set\s+default|no\s+action)\b/i', $sql, $matches) !== 1) {
            return null;
        }
        return $this->normalizeForeignKeyAction(str_replace(' ', '_', strtolower($matches[1])));
    }

    private function unquoteSqlLiteral(string $value): string
    {
        $value = trim($value);
        if (str_starts_with($value, "'") && str_ends_with($value, "'")) {
            return str_replace("''", "'", substr($value, 1, -1));
        }
        return trim($value, "'");
    }

    private function buildImportedEntityDraft(array $table, array $payload): array
    {
        $entityCode = $this->safeSqlIdentifier((string) ($payload['entityCode'] ?? $table['tableName']));
        $entityName = trim((string) ($payload['entityName'] ?? ($table['tableComment'] ?? $this->humanizeIdentifier((string) $table['tableName']))));
        $fields = [];
        $singleUniqueColumns = [];
        $uniqueKeys = [];

        foreach ($table['uniqueConstraints'] as $constraint) {
            $columns = array_values(array_filter(array_map('strval', $constraint['columns'] ?? [])));
            if (count($columns) === 1) {
                $singleUniqueColumns[strtolower($columns[0])] = true;
                continue;
            }
            $uniqueKeys[] = [
                'name' => $this->safeSqlIdentifier((string) ($constraint['name'] ?? 'uk_' . $table['tableName'])),
                'fields' => array_values(array_map(function(string $column): string {
                    return $this->safeSqlIdentifier($column);
                }, $columns)),
            ];
        }

        foreach ($table['columns'] as $column) {
            $columnName = strtolower((string) ($column['column_name'] ?? ''));
            $field = $this->mapImportedColumnToField(
                $column,
                in_array($columnName, $table['primaryKeys'], true),
                isset($singleUniqueColumns[$columnName]),
                $table['foreignKeys'][$columnName] ?? null
            );
            if ($field !== null) {
                $fields[] = $field;
            }
        }

        return [
            'code' => $entityCode,
            'name' => $entityName,
            'entityType' => 'persistence',
            'tableName' => $table['tableName'],
            'originalTableName' => $table['tableName'],
            'createPhysicalTable' => false,
            'allowTableRename' => false,
            'allowColumnRename' => false,
            'dropRemovedColumns' => false,
            'skipStructureValidation' => true,
            'situationEnabled' => false,
            'situationFieldCode' => '',
            'versioningEnabled' => false,
            'versioningDeduplicate' => true,
            'structureModuleCode' => '',
            'structureType' => (string) ($table['classification']['structureType'] ?? 'main'),
            'structureBaseNumber' => null,
            'structureSequenceNumber' => null,
            'structureParentEntityCode' => '',
            'structureLeftEntityCode' => '',
            'structureRightEntityCode' => '',
            'fields' => $fields,
            'uniqueKeys' => $uniqueKeys,
            'rules' => [],
        ];
    }

    private function buildImportedProgramDraft(array $entityDraft, array $payload): array
    {
        $tableName = (string) ($entityDraft['tableName'] ?? '');
        $programTitle = trim((string) ($payload['programTitle'] ?? ($entityDraft['name'] ?? '')));
        $programCode = trim((string) ($payload['programCode'] ?? ''));
        $moduleCode = $this->safeCode((string) ($payload['module'] ?? ''));
        $screenId = trim((string) ($payload['screenId'] ?? ('cadastros.' . str_replace('_', '-', $tableName))));
        $version = trim((string) ($payload['version'] ?? '1.0.0'));

        return [
            'programCode' => $programCode,
            'programTitle' => $programTitle,
            'module' => $moduleCode,
            'pageType' => 'crud',
            'builderEntityCode' => (string) ($entityDraft['code'] ?? ''),
            'screenId' => $screenId,
            'version' => $version !== '' ? $version : '1.0.0',
            'subtitle' => trim((string) ($payload['subtitle'] ?? 'Gerado a partir do banco de dados')),
            'icon' => trim((string) ($payload['icon'] ?? 'database')),
            'permissionPrefix' => trim((string) ($payload['permissionPrefix'] ?? $programCode)),
            'allowCreate' => ($payload['allowCreate'] ?? true) !== false,
            'allowUpdate' => ($payload['allowUpdate'] ?? true) !== false,
            'allowDelete' => ($payload['allowDelete'] ?? true) !== false,
            'changeSummary' => trim((string) ($payload['changeSummary'] ?? 'Rascunho gerado por importacao de tabela existente.')),
        ];
    }

    private function classifyImportedTable(string $tableName, array $columns, array $primaryKeys, array $uniqueConstraints, array $foreignKeys): array
    {
        $columnNames = array_map(static fn (array $column): string => strtolower((string) ($column['column_name'] ?? '')), $columns);
        $foreignKeyCount = count($foreignKeys);
        $primaryKeyCount = count($primaryKeys);
        $nonAuditColumns = array_filter($columnNames, fn (string $name): bool => !$this->isAuditColumnName($name));
        $businessColumnCount = count($nonAuditColumns);
        $joinedName = implode(' ', $columnNames);

        if ($foreignKeyCount >= 2 && $primaryKeyCount >= 2 && $businessColumnCount <= ($foreignKeyCount + 2)) {
            return [
                'code' => 'junction',
                'label' => 'Tabela de juncao/agregacao',
                'structureType' => 'aggregation',
            ];
        }

        if (preg_match('/(item|mov|nota|pedido|lanc|trans|hist)/', $tableName) || preg_match('/(valor|total|quantidade|dt_|dt_hr_)/', $joinedName)) {
            return [
                'code' => 'transactional',
                'label' => 'Tabela transacional',
                'structureType' => 'main',
            ];
        }

        if ($businessColumnCount <= 6 && $foreignKeyCount <= 1) {
            return [
                'code' => 'support',
                'label' => 'Tabela de apoio/cadastro simples',
                'structureType' => 'main',
            ];
        }

        return [
            'code' => 'master',
            'label' => 'Cadastro mestre',
            'structureType' => 'main',
        ];
    }

    private function buildImportDiagnostics(array $columns, array $primaryKeys, array $foreignKeys): array
    {
        $diagnostics = [];
        if (!$primaryKeys) {
            $diagnostics[] = [
                'level' => 'warn',
                'message' => 'A tabela nao possui chave primaria declarada.',
            ];
        }
        if (!$foreignKeys) {
            $diagnostics[] = [
                'level' => 'info',
                'message' => 'Nao foram encontradas chaves estrangeiras para dropdowns automaticos.',
            ];
        }
        if (count($columns) > 40) {
            $diagnostics[] = [
                'level' => 'warn',
                'message' => 'A tabela tem muitos campos. Revise o CRUD gerado antes de publicar.',
            ];
        }
        return $diagnostics;
    }

    private function mapImportedColumnToField(array $column, bool $primaryKey, bool $unique, ?array $foreignKey): ?array
    {
        $columnName = strtolower((string) ($column['column_name'] ?? ''));
        if ($columnName === '') {
            return null;
        }

        $dataType = $this->mapDatabaseTypeToBuilderType((string) ($column['data_type'] ?? ''), (string) ($column['udt_name'] ?? ''));
        $label = trim((string) ($column['column_comment'] ?? ''));
        if ($label === '') {
            $label = $this->humanizeIdentifier($columnName);
        }
        $length = $column['character_maximum_length'] !== null ? (int) $column['character_maximum_length'] : null;
        $precision = $column['numeric_precision'] !== null ? (int) $column['numeric_precision'] : null;
        $scale = $column['numeric_scale'] !== null ? (int) $column['numeric_scale'] : null;
        $defaultValue = $this->normalizeImportedDefaultValue((string) ($column['column_default'] ?? ''), $dataType);
        $required = strtoupper((string) ($column['is_nullable'] ?? 'YES')) === 'NO' && !$primaryKey && $defaultValue === null;

        return [
            'id' => 0,
            'code' => $columnName,
            'label' => $label,
            'dataType' => $dataType,
            'columnName' => $columnName,
            'originalCode' => $columnName,
            'originalColumnName' => $columnName,
            'length' => in_array($dataType, ['string', 'email'], true) ? $length : null,
            'precision' => $dataType === 'decimal' ? $precision : null,
            'scale' => $dataType === 'decimal' ? $scale : null,
            'defaultValue' => $defaultValue,
            'unique' => $unique,
            'readonlyField' => $primaryKey || $this->isReadonlyImportedField($columnName),
            'foreignKeyTable' => $foreignKey['table'] ?? '',
            'foreignKeyColumn' => $foreignKey['column'] ?? '',
            'foreignKeyDependencyType' => $foreignKey['dependencyType'] ?? '',
            'foreignKeyOnDelete' => $foreignKey['onDelete'] ?? '',
            'foreignKeyOnUpdate' => $foreignKey['onUpdate'] ?? '',
            'optionItems' => [],
            'virtualField' => false,
            'includeInVersion' => true,
            'versionRefEntityCode' => '',
            'versionRefSourceIdField' => '',
            'versionSnapshotVersionField' => '',
            'versionSnapshotPath' => '',
            'customCodeMode' => '',
            'customCodePrefix' => '',
            'customCodePattern' => '',
            'customCodeSequenceEnabled' => true,
            'customCodeSequenceScope' => 'global',
            'customCodeSequencePadding' => 4,
            'customCodeStaticClass' => '',
            'customCodeStaticMethod' => '',
            'customCodeAssistantScreenId' => '',
            'customCodePromptTitle' => '',
            'customCodePromptFields' => [],
            'required' => $primaryKey ? true : $required,
            'primaryKey' => $primaryKey,
            'options' => [],
        ];
    }

    private function mapDatabaseTypeToBuilderType(string $dataType, string $udtName): string
    {
        $dataType = strtolower(trim($dataType));
        $udtName = strtolower(trim($udtName));
        return match (true) {
            in_array($dataType, ['integer', 'bigint', 'smallint'], true) => 'integer',
            in_array($dataType, ['numeric', 'decimal', 'real', 'double precision'], true) => 'decimal',
            $dataType === 'boolean' => 'boolean',
            $dataType === 'date' => 'date',
            str_contains($dataType, 'timestamp') => 'datetime',
            in_array($dataType, ['json', 'jsonb'], true) => 'json',
            $dataType === 'text' => 'text',
            $udtName === 'uuid' => 'string',
            default => 'string',
        };
    }

    private function normalizeImportedDefaultValue(string $defaultValue, string $dataType): mixed
    {
        $defaultValue = trim($defaultValue);
        if ($defaultValue === '') {
            return null;
        }
        if (preg_match("/^'(.*)'::/", $defaultValue, $matches)) {
            return (string) $matches[1];
        }
        if ($dataType === 'boolean') {
            if ($defaultValue === 'true') {
                return true;
            }
            if ($defaultValue === 'false') {
                return false;
            }
        }
        if (in_array($dataType, ['integer', 'decimal'], true) && preg_match('/^-?\d+(\.\d+)?$/', $defaultValue)) {
            return str_contains($defaultValue, '.') ? (float) $defaultValue : (int) $defaultValue;
        }
        if (preg_match('/^(now\(\)|CURRENT_TIMESTAMP)/i', $defaultValue)) {
            return null;
        }
        return $defaultValue;
    }

    private function isReadonlyImportedField(string $columnName): bool
    {
        return preg_match('/(^id$|_id$|^created_|^updated_|^deleted_|^dt_hr_|^log_)/', $columnName) === 1;
    }

    private function isAuditColumnName(string $columnName): bool
    {
        return preg_match('/(^id$|^created_|^updated_|^deleted_|^dt_|^dt_hr_|^log_)/', $columnName) === 1;
    }

    private function humanizeIdentifier(string $value): string
    {
        $parts = array_values(array_filter(explode('_', strtolower(trim($value)))));
        if (!$parts) {
            return '';
        }

        return implode(' ', array_map(function(string $part): string {
            $label = self::FIELD_ABBREVIATIONS[$part] ?? $part;
            return ucfirst((string) $label);
        }, $parts));
    }

    private function isImportableTable(string $schema, string $tableName): bool
    {
        if ($schema !== 'public') {
            return true;
        }

        if (preg_match('/^(builder_|runtime_|auth_|system_|privacy_|user_|screen_definition$|runtime_endpoint$|program$|doctrine_migration_versions$|messenger_messages$)/', $tableName) === 1) {
            return false;
        }

        return !in_array($tableName, [
            'api_repository',
            'data_source',
            'database_repository',
            'decision_evaluation',
            'decision_rule',
            'decision_rule_clause',
            'import_export_mapping',
            'option_list',
            'option_list_item',
            'outcome',
            'parameter',
            'parameter_group',
            'parameter_structure_version',
            'result_field',
            'rule_lookup_audit_log',
            'rule_lookup_cache',
            'subscriber',
            'subscriber_connection',
            'synchronization_pending',
            'synchronization_policy',
            'value_validation_rule',
        ], true);
    }

    public function acquireEditorLock(array $payload): array
    {
        $this->assertAdminWrite();
        $scopeType = $this->normalizeLockScopeType((string) ($payload['scopeType'] ?? ''));
        $scopeCode = trim((string) ($payload['scopeCode'] ?? ''));
        $displayName = trim((string) ($payload['displayName'] ?? ''));
        $grantId = $this->normalizePositiveInt($payload['grantId'] ?? null);
        $lockCategory = in_array((string) ($payload['lockCategory'] ?? 'general'), ['general', 'authoring'], true) ? (string) $payload['lockCategory'] : 'general';
        if ($scopeType === '' || $scopeCode === '') {
            throw new RuntimeHttpException('PROGRAM_BUILDER_LOCK_SCOPE_REQUIRED', 'Informe o tipo e o codigo do item para bloquear.', 422);
        }
        if ($grantId) {
            $this->governance->assertLockGrantStillActive($grantId, $scopeType, $scopeCode);
        }

        $session = $this->sessions->ensureActive();
        $tenantId = $session->getTenantId();
        $current = $this->editorLocks->findActiveByScope($scopeType, $scopeCode, $tenantId);
        $now = new \DateTimeImmutable();
        if ($current && $current->isExpired($now)) {
            $current->release();
            $this->entityManager->persist($current);
            $this->entityManager->flush();
            $current = null;
        }

        if ($current && $current->getSessionId() !== $session->getSessionId()) {
            return [
                'status' => 'readonly',
                'scopeType' => $scopeType,
                'scopeCode' => $scopeCode,
                'heartbeatIntervalSeconds' => self::EDITOR_LOCK_HEARTBEAT_SECONDS,
                'lock' => $this->editorLockPayload($current),
                'message' => 'Este item esta em edicao por outro usuario.',
            ];
        }

        $lock = $current ?? (new BuilderEditorLock())
            ->setScopeType($scopeType)
            ->setScopeCode($scopeCode)
            ->setTenantId($tenantId)
            ->setSessionId($session->getSessionId())
            ->setUserId($session->getUserId())
            ->setUserName($session->getUserName())
            ->setLockToken(bin2hex(random_bytes(16)));

        $lock
            ->setDisplayName($displayName !== '' ? $displayName : $scopeCode)
            ->setGrantId($grantId)
            ->setLockCategory($lockCategory)
            ->setStatus('active')
            ->setAcquiredAt($current ? $current->getAcquiredAt() : $now)
            ->heartbeat(self::EDITOR_LOCK_TTL_SECONDS);

        $this->entityManager->persist($lock);
        $this->entityManager->flush();

        return [
            'status' => 'acquired',
            'scopeType' => $scopeType,
            'scopeCode' => $scopeCode,
            'heartbeatIntervalSeconds' => self::EDITOR_LOCK_HEARTBEAT_SECONDS,
            'lock' => $this->editorLockPayload($lock),
            'message' => 'Lock de edicao adquirido.',
        ];
    }

    public function heartbeatEditorLock(array $payload): array
    {
        $this->assertAdminWrite();
        $token = trim((string) ($payload['lockToken'] ?? ''));
        if ($token === '') {
            throw new RuntimeHttpException('PROGRAM_BUILDER_LOCK_TOKEN_REQUIRED', 'Informe o token do lock.', 422);
        }

        $session = $this->sessions->ensureActive();
        $lock = $this->editorLocks->findActiveByToken($token, $session->getTenantId());
        if (!$lock) {
            throw new RuntimeHttpException('PROGRAM_BUILDER_LOCK_NOT_FOUND', 'Lock do construtor nao encontrado ou ja expirado.', 409, [
                'lockToken' => $token,
            ]);
        }
        if ($lock->getSessionId() !== $session->getSessionId()) {
            throw new RuntimeHttpException('PROGRAM_BUILDER_LOCK_NOT_OWNER', 'A sessao atual nao controla este lock.', 409, [
                'lockToken' => $token,
            ]);
        }

        $lock->heartbeat(self::EDITOR_LOCK_TTL_SECONDS);
        if ($lock->getGrantId()) {
            try {
                $this->governance->assertLockGrantStillActive((int) $lock->getGrantId(), $lock->getScopeType(), $lock->getScopeCode());
            } catch (\Throwable $error) {
                $lock->release();
                $this->entityManager->persist($lock);
                $this->entityManager->flush();
                throw $error;
            }
        }
        $this->entityManager->persist($lock);
        $this->entityManager->flush();

        return [
            'status' => 'active',
            'heartbeatIntervalSeconds' => self::EDITOR_LOCK_HEARTBEAT_SECONDS,
            'lock' => $this->editorLockPayload($lock),
        ];
    }

    public function releaseEditorLock(array $payload): array
    {
        $this->assertAdminWrite();
        $token = trim((string) ($payload['lockToken'] ?? ''));
        if ($token === '') {
            return ['status' => 'released'];
        }

        $session = $this->sessions->ensureActive(false);
        $lock = $this->editorLocks->findActiveByToken($token, $session->getTenantId());
        if (!$lock) {
            return ['status' => 'released'];
        }
        if ($lock->getSessionId() !== $session->getSessionId()) {
            throw new RuntimeHttpException('PROGRAM_BUILDER_LOCK_NOT_OWNER', 'A sessao atual nao controla este lock.', 409, [
                'lockToken' => $token,
            ]);
        }

        $lock->release();
        $this->entityManager->persist($lock);
        $this->entityManager->flush();

        return [
            'status' => 'released',
            'lock' => $this->editorLockPayload($lock),
        ];
    }

    public function saveModule(array $payload): array
    {
        $this->assertAdminWrite();

        $id = (int) ($payload['id'] ?? 0);
        $module = $id > 0 ? $this->modules->find($id) : new BuilderModule();
        if (!$module) {
            throw new RuntimeHttpException('BUILDER_MODULE_NOT_FOUND', 'Modulo do construtor nao encontrado.', 404, ['id' => $id]);
        }

        $code = $this->safeCode((string) ($payload['code'] ?? ''));
        $name = trim((string) ($payload['name'] ?? ''));
        $abbreviation = strtolower($this->safeCode((string) ($payload['abbreviation'] ?? '')));
        $numberStart = max(1, (int) ($payload['numberStart'] ?? 0));
        $numberEnd = max(1, (int) ($payload['numberEnd'] ?? 0));
        $enabled = ($payload['enabled'] ?? true) !== false;

        if ($code === '' || $name === '' || $abbreviation === '') {
            throw new RuntimeHttpException('BUILDER_MODULE_REQUIRED_FIELDS', 'Informe codigo, nome e abreviacao do modulo.', 422);
        }
        if (!preg_match('/^[a-z]{2,6}$/', $abbreviation)) {
            throw new RuntimeHttpException('BUILDER_MODULE_ABBREVIATION_INVALID', 'A abreviacao do modulo deve ter entre 2 e 6 letras minusculas.', 422, [
                'abbreviation' => $abbreviation,
            ]);
        }
        if ($numberStart > $numberEnd) {
            throw new RuntimeHttpException('BUILDER_MODULE_RANGE_INVALID', 'O numero inicial nao pode ser maior que o numero final.', 422, [
                'numberStart' => $numberStart,
                'numberEnd' => $numberEnd,
            ]);
        }

        $existing = $this->modules->findOneBy(['code' => $code]);
        if ($existing && $existing->getId() !== $module->getId()) {
            throw new RuntimeHttpException('BUILDER_MODULE_CODE_ALREADY_EXISTS', 'Ja existe um modulo com este codigo.', 422, [
                'code' => $code,
            ]);
        }
        foreach ($this->modules->findAll() as $item) {
            if ($module->getId() !== null && $item->getId() === $module->getId()) {
                continue;
            }
            if ($item->getAbbreviation() === $abbreviation) {
                throw new RuntimeHttpException('BUILDER_MODULE_ABBREVIATION_ALREADY_EXISTS', 'Ja existe um modulo com esta abreviacao.', 422, [
                    'abbreviation' => $abbreviation,
                    'conflictModule' => $item->getCode(),
                ]);
            }
        }

        foreach ($this->modules->findAll() as $item) {
            if ($module->getId() !== null && $item->getId() === $module->getId()) {
                continue;
            }
            if (!$item->isEnabled() || !$enabled) {
                continue;
            }
            if ($numberStart <= $item->getNumberEnd() && $numberEnd >= $item->getNumberStart()) {
                throw new RuntimeHttpException('BUILDER_MODULE_RANGE_OVERLAP', 'A faixa numerica do modulo conflita com outro modulo habilitado.', 422, [
                    'code' => $code,
                    'conflictModule' => $item->getCode(),
                    'conflictRange' => [$item->getNumberStart(), $item->getNumberEnd()],
                ]);
            }
        }

        $module
            ->setCode($code)
            ->setName($name)
            ->setAbbreviation($abbreviation)
            ->setNumberStart($numberStart)
            ->setNumberEnd($numberEnd)
            ->setEnabled($enabled)
            ->setMetadata([]);

        $this->entityManager->persist($module);
        $this->entityManager->flush();

        return [
            'module' => $this->modulePayload($module),
            'modules' => array_map(fn (BuilderModule $item): array => $this->modulePayload($item), $this->modules->findBy([], ['numberStart' => 'ASC', 'code' => 'ASC'])),
        ];
    }

    public function getEntity(string $entityCode): array
    {
        $this->assertAdminRead();
        $entityCode = trim($entityCode);
        if ($entityCode === '') {
            throw new RuntimeHttpException('ENTITY_CODE_REQUIRED', 'Informe o codigo da entidade.', 422);
        }

        $entity = $this->entities->findOneBy(['code' => $entityCode]);
        if (!$entity) {
            throw new RuntimeHttpException('PROGRAM_BUILDER_ENTITY_NOT_FOUND', 'Entidade do construtor nao encontrada.', 404, [
                'builderEntityCode' => $entityCode,
            ]);
        }
        $this->integrity->assertBuilderEntity($entity);

        return [
            'entity' => $this->entityPayload($entity),
            'versions' => array_map(fn (BuilderEntityVersion $version): array => $this->entityVersionPayload($version), $this->entityVersions->findByEntityCodeOrdered($entityCode)),
        ];
    }

    public function saveEntity(array $payload): array
    {
        $this->assertAdminWrite();

        $config = $this->normalizeEntityPayload($payload);
        $this->governance->assertCanEditEntity($config['code']);
        $entity = $this->applyEntityConfig($config);
        $version = $this->createEntityVersionSnapshot($entity, 'save', 'Modelagem salva.');
        $this->integrity->signBuilderEntity($entity, ['source' => 'saveEntity']);
        $this->signEntityFields($entity, 'saveEntity');
        $this->integrity->signBuilderEntityVersion($version, ['source' => 'saveEntity']);

        return [
            'entity' => $this->entityPayload($entity),
            'version' => $this->entityVersionPayload($version),
            'versions' => array_map(fn (BuilderEntityVersion $item): array => $this->entityVersionPayload($item), $this->entityVersions->findByEntityCodeOrdered($entity->getCode())),
        ];
    }

    public function restoreEntityVersion(int $id): array
    {
        $this->assertAdminWrite();

        $version = $this->entityVersions->find($id);
        if (!$version) {
            throw new RuntimeHttpException('ENTITY_VERSION_NOT_FOUND', 'Versao da entidade nao encontrada.', 404, ['id' => $id]);
        }

        $snapshot = $version->getSnapshot();
        if (!is_array($snapshot)) {
            throw new RuntimeHttpException('ENTITY_VERSION_SNAPSHOT_INVALID', 'Snapshot da entidade esta invalido.', 422, ['id' => $id]);
        }

        $config = $this->normalizeEntityPayload($snapshot);
        $current = $this->entities->findOneBy(['code' => $version->getBuilderEntityCode()]);
        if ($current) {
            $config = $this->mergeRestoreOrigins($config, $current);
        }
        if ($config['entityType'] === 'persistence') {
            $config['createPhysicalTable'] = true;
            $config['allowTableRename'] = true;
            $config['allowColumnRename'] = true;
            $config['dropRemovedColumns'] = true;
        }

        $entity = $this->applyEntityConfig($config);
        $restored = $this->createEntityVersionSnapshot(
            $entity,
            'restore',
            'Rollback a partir da revisao ' . $version->getRevision() . '.',
            $version->getId()
        );
        $this->integrity->signBuilderEntity($entity, ['source' => 'restoreEntityVersion']);
        $this->signEntityFields($entity, 'restoreEntityVersion');
        $this->integrity->signBuilderEntityVersion($restored, ['source' => 'restoreEntityVersion']);

        return [
            'entity' => $this->entityPayload($entity),
            'version' => $this->entityVersionPayload($restored),
            'versions' => array_map(fn (BuilderEntityVersion $item): array => $this->entityVersionPayload($item), $this->entityVersions->findByEntityCodeOrdered($entity->getCode())),
        ];
    }

    private function applyEntityConfig(array $config): BuilderEntity
    {
        $existingSnapshots = [];
        $entity = $this->entities->findOneBy(['code' => $config['code']]) ?? new BuilderEntity();
        $existingMetadata = $entity->getMetadata();
        foreach ($entity->getFields() as $existingField) {
            $existingSnapshots[$existingField->getId() ?? 0] = [
                'id' => $existingField->getId(),
                'code' => $existingField->getCode(),
                'columnName' => (string) ($existingField->getOptions()['columnName'] ?? $existingField->getCode()),
            ];
        }
        $originalTableName = $entity->getTableName();
        $entity
            ->setCode($config['code'])
            ->setName($config['name'])
            ->setEntityType($config['entityType'])
            ->setTableName($config['tableName'])
            ->setStatus('published')
            ->setSituationEnabled($config['situationEnabled'])
            ->setSituationFieldCode($config['situationFieldCode'])
            ->setMetadata(array_replace($existingMetadata, [
                'primaryKey' => $config['primaryKey'],
                'createdByBuilder' => true,
                'builderMode' => 'visual',
                'structure' => $config['structure'],
                'subscriberIsolation' => $config['subscriberIsolation'],
                'uniqueKeys' => $config['uniqueKeys'],
                'rules' => $config['rules'],
                'softDelete' => $config['softDelete'],
                'apiSource' => $this->restoreMaskedApiSourceSecrets(
                    $config['apiSource'] ?? null,
                    is_array($existingMetadata['apiSource'] ?? null) ? $existingMetadata['apiSource'] : []
                ),
                'apiBinding' => $config['apiBinding'] ?? null,
                'versioning' => [
                    'enabled' => $config['versioningEnabled'],
                    'mode' => 'snapshot_on_change',
                    'deduplicate' => $config['versioningDeduplicate'],
                ],
            ]));

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $configuredCodes = [];
        $configuredIds = [];
        foreach ($config['fields'] as $position => $fieldConfig) {
            $configuredCodes[$fieldConfig['code']] = true;
            $field = null;
            if (($fieldConfig['id'] ?? 0) > 0) {
                foreach ($entity->getFields() as $existingField) {
                    if ($existingField->getId() === (int) $fieldConfig['id']) {
                        $field = $existingField;
                        $configuredIds[] = $existingField->getId();
                        break;
                    }
                }
            }
            if (!$field) {
                $field = $this->fields->findOneBy([
                    'builderEntity' => $entity,
                    'code' => $fieldConfig['code'],
                ]) ?? new BuilderField();
                if ($field->getId()) {
                    $configuredIds[] = $field->getId();
                }
            }
            $field
                ->setBuilderEntity($entity)
                ->setCode($fieldConfig['code'])
                ->setLabel($fieldConfig['label'])
                ->setDataType($fieldConfig['dataType'])
                ->setDatabaseType($this->guessDatabaseType($fieldConfig['dataType']))
                ->setLength($fieldConfig['length'])
                ->setPrecisionValue($fieldConfig['precision'])
                ->setScaleValue($fieldConfig['scale'])
                ->setRequired($fieldConfig['required'])
                ->setPrimaryKey($fieldConfig['primaryKey'])
                ->setPosition($position)
                ->setOptions($fieldConfig['options']);
            $this->entityManager->persist($field);
        }

        $removedColumns = [];
        foreach ($entity->getFields() as $existingField) {
            if (!isset($configuredCodes[$existingField->getCode()]) && !in_array($existingField->getId(), $configuredIds, true)) {
                $removedColumns[] = [
                    'code' => $existingField->getCode(),
                    'columnName' => (string) ($existingField->getOptions()['columnName'] ?? $existingField->getCode()),
                ];
                $this->entityManager->remove($existingField);
            }
        }

        $this->entityManager->flush();
        $this->synchronizePhysicalTable(
            $entity,
            $config['fields'],
            $config['uniqueKeys'],
            $config['createPhysicalTable'],
            is_string($originalTableName) ? $originalTableName : null,
            $existingSnapshots,
            $removedColumns,
            $config['allowTableRename'],
            $config['allowColumnRename'],
            $config['dropRemovedColumns']
        );
        $this->entityManager->refresh($entity);
        return $entity;
    }

    private function signEntityFields(BuilderEntity $entity, string $source): void
    {
        foreach ($entity->getFields() as $field) {
            if ($field->getId()) {
                $this->integrity->signBuilderField($field, ['source' => $source]);
            }
        }
    }

    public function getProgram(string $programCode): array
    {
        $this->assertAdminRead();
        $programCode = trim($programCode);
        if ($programCode === '') {
            throw new RuntimeHttpException('PROGRAM_CODE_REQUIRED', 'Informe o codigo do programa.', 422);
        }

        $program = $this->programs->findOneBy(['code' => $programCode]);
        if ($program) {
            $this->integrity->assertProgram($program);
        }
        $versionItems = $this->versions->findByProgramCodeOrdered($programCode);
        foreach ($versionItems as $versionItem) {
            $this->integrity->assertProgramVersion($versionItem);
        }
        $versions = array_map(fn (BuilderProgramVersion $version): array => $this->versionPayload($version), $versionItems);

        return [
            'program' => $program ? [
                'code' => $program->getCode(),
                'title' => $program->getTitle(),
                'module' => $program->getModule(),
                'programType' => $program->getProgramType(),
                'screenId' => $program->getScreenId(),
                'status' => $program->getStatus(),
                'programOrigin' => $program->getProgramOrigin(),
                'ownerScope' => $program->getOwnerScope(),
                'customizationPolicy' => $program->getCustomizationPolicy(),
                'subscriberId' => $program->getSubscriberId(),
                'baseProgramCode' => $program->getBaseProgramCode(),
                'baseProgramVersionId' => $program->getBaseProgramVersionId(),
                'upgradeFrozen' => $program->isUpgradeFrozen(),
                'frozenReason' => $program->getFrozenReason(),
                'updatedAt' => $program->getUpdatedAt()->format(DATE_ATOM),
            ] : null,
            'versions' => $versions,
        ];
    }

    public function saveDraft(array $payload): array
    {
        $this->assertAdminWrite();

        $id = (int) ($payload['id'] ?? 0);
        $version = $id > 0 ? $this->versions->find($id) : new BuilderProgramVersion();
        if (!$version) {
            throw new RuntimeHttpException('PROGRAM_VERSION_NOT_FOUND', 'Versao do programa nao encontrada.', 404, ['id' => $id]);
        }
        if ($version->getId() !== null && $version->getStatus() !== 'draft') {
            throw new RuntimeHttpException('PROGRAM_VERSION_NOT_EDITABLE', 'Apenas rascunhos podem ser alterados.', 422, ['id' => $id]);
        }
        if ($version->getId() !== null) {
            $this->integrity->assertProgramVersion($version);
        }

        $config = $this->normalizeBuilderPayload($payload);
        $definition = $this->generateProgramDefinition($config);

        $existing = $this->findByProgramCodeAndVersion($config['programCode'], $config['version']);
        if ($existing && $existing->getId() !== $version->getId()) {
            throw new RuntimeHttpException('PROGRAM_VERSION_ALREADY_EXISTS', 'Ja existe uma versao com este numero para o programa.', 422, [
                'programCode' => $config['programCode'],
                'version' => $config['version'],
            ]);
        }

        $version
            ->setProgramCode($config['programCode'])
            ->setProgramTitle($config['programTitle'])
            ->setModule($config['module'])
            ->setPageType($config['pageType'])
            ->setBuilderEntityCode($config['builderEntityCode'])
            ->setScreenId($config['screenId'])
            ->setVersion($config['version'])
            ->setStatus('draft')
            ->setSubtitle($config['subtitle'])
            ->setIcon($config['icon'])
            ->setPermissionPrefix($config['permissionPrefix'])
            ->setAllowCreate($config['allowCreate'])
            ->setAllowUpdate($config['allowUpdate'])
            ->setAllowDelete($config['allowDelete'])
            ->setChangeSummary($config['changeSummary'])
            ->setProgramOrigin($config['programOrigin'])
            ->setOwnerScope($config['ownerScope'])
            ->setCustomizationPolicy($config['customizationPolicy'])
            ->setSubscriberId($config['subscriberId'])
            ->setBaseProgramCode($config['baseProgramCode'])
            ->setBaseProgramVersionId($config['baseProgramVersionId'])
            ->setUpgradeFrozen($config['upgradeFrozen'])
            ->setFrozenReason($config['frozenReason'])
            ->setBuilderConfig($this->publicBuilderConfig($config))
            ->setGeneratedDefinition($definition);

        $this->governance->assertCanEditProgramDraft($version, $config['builderEntityCode'] !== '' ? $config['builderEntityCode'] : null);

        $this->entityManager->persist($version);
        $this->entityManager->flush();
        $this->integrity->signProgramVersion($version, ['source' => 'saveDraft']);
        $this->entityManager->flush();

        return $this->versionPayload($version);
    }

    public function previewDraft(array $payload): array
    {
        $this->assertAdminRead();

        $config = $this->normalizeBuilderPayload($payload);
        $definition = $this->generateProgramDefinition($config);

        return [
            'builderConfig' => $this->publicBuilderConfig($config),
            'generatedDefinition' => $definition,
        ];
    }

    public function validateExternalDraft(array $payload): array
    {
        $this->assertAdminRead();

        $input = is_array($payload['payload'] ?? null) ? $payload['payload'] : $payload;
        $entityPayload = is_array($input['entityDraft'] ?? null) ? $input['entityDraft'] : [];
        $programPayload = is_array($input['programDraft'] ?? null) ? $input['programDraft'] : [];
        if (!$entityPayload || !$programPayload) {
            throw new RuntimeHttpException('PROGRAM_BUILDER_EXTERNAL_DRAFT_REQUIRED', 'Informe entityDraft e programDraft no JSON externo.', 422);
        }
        if (($programPayload['pageType'] ?? 'crud') !== 'crud') {
            throw new RuntimeHttpException('PROGRAM_BUILDER_EXTERNAL_PAGE_TYPE_NOT_SUPPORTED', 'Nesta etapa a importacao externa aceita apenas pageType=crud.', 422, [
                'pageType' => $programPayload['pageType'] ?? null,
            ]);
        }

        $entityConfig = $this->normalizeEntityPayload($entityPayload);
        $transientEntity = $this->buildTransientEntity($entityConfig);

        $programDefaults = [
            'pageType' => 'crud',
            'builderEntityCode' => $entityConfig['code'],
            'version' => '1.0.0',
            'subtitle' => '',
            'icon' => 'file',
            'permissionPrefix' => '',
            'allowCreate' => true,
            'allowUpdate' => true,
            'allowDelete' => false,
            'changeSummary' => '',
        ];
        $programConfig = $this->normalizeBuilderPayload(array_merge($programDefaults, $programPayload), $transientEntity);
        $definition = $this->generateProgramDefinition($programConfig);
        $diagnostics = array_merge(
            $this->normalizeExternalDiagnostics($input['diagnostics'] ?? null),
            $this->collectExternalDraftDiagnostics($entityPayload, $programPayload, $entityConfig, $programConfig)
        );

        return [
            'readyToApply' => true,
            'entityDraft' => $this->externalEntityPayload($entityConfig),
            'programDraft' => $this->externalProgramPayload($programConfig, $definition),
            'generatedDefinition' => $definition,
            'diagnostics' => $diagnostics,
            'normalizedDraft' => [
                'entityDraft' => $this->externalEntityPayload($entityConfig),
                'programDraft' => $this->externalProgramPayload($programConfig, $definition),
            ],
            'sourcePrompt' => isset($input['sourcePrompt']) ? trim((string) $input['sourcePrompt']) : '',
        ];
    }

    public function publishVersion(int $id): array
    {
        $this->assertAdminWrite();

        $version = $this->versions->find($id);
        if (!$version) {
            throw new RuntimeHttpException('PROGRAM_VERSION_NOT_FOUND', 'Versao do programa nao encontrada.', 404, ['id' => $id]);
        }

        $grant = null;
        try {
            if ($version->getProgramOrigin() === 'standard' && $version->getOwnerScope() === 'system') {
                $grant = $this->governance->assertCanPublish($version);
            }
            $this->assertPublicationEnvironmentAllowed($version);
            $this->integrity->assertProgramVersion($version);
        } catch (RuntimeHttpException $error) {
            $this->notifications->createAdministrativeNotification(
                'Publicacao governada bloqueada',
                sprintf('A publicacao da versao %s do programa %s foi bloqueada: %s', $version->getVersion(), $version->getProgramCode(), $error->getMessage()),
                [
                    'code' => 'governanca.publish_blocked.' . strtolower($version->getProgramCode()) . '.' . (int) $version->getId(),
                    'category' => 'governanca',
                    'severity' => 'warning',
                    'actionRequired' => true,
                    'linkProgramId' => $version->getProgramCode(),
                    'linkScreenId' => 'admin.programa-aprovacoes',
                    'metadata' => [
                        'builderProgramVersionId' => $version->getId(),
                        'programCode' => $version->getProgramCode(),
                        'errorCode' => $error->getErrorCode(),
                        'message' => $error->getMessage(),
                    ],
                ]
            );
            throw $error;
        }

        foreach ($this->versions->findByProgramCodeOrdered($version->getProgramCode()) as $item) {
            if ($item->getStatus() === 'published' && $item->getId() !== $version->getId()) {
                $item->setStatus('archived');
                $this->entityManager->persist($item);
            }
        }

        $definition = $this->decoratePublishedDefinition($version, $version->getGeneratedDefinition());
        $program = $this->programs->findOneBy(['code' => $version->getProgramCode()]) ?? new Program();
        $program
            ->setCode($version->getProgramCode())
            ->setTitle($version->getProgramTitle())
            ->setModule($version->getModule())
            ->setProgramType($version->getPageType())
            ->setScreenId($version->getScreenId())
            ->setStatus('published')
            ->setProgramOrigin($version->getProgramOrigin())
            ->setOwnerScope($version->getOwnerScope())
            ->setCustomizationPolicy($version->getCustomizationPolicy())
            ->setSubscriberId($version->getSubscriberId())
            ->setBaseProgramCode($version->getBaseProgramCode())
            ->setBaseProgramVersionId($version->getBaseProgramVersionId())
            ->setUpgradeFrozen($version->isUpgradeFrozen())
            ->setFrozenReason($version->getFrozenReason());
        $this->entityManager->persist($program);

        $screen = $this->screens->findOneBy(['screenId' => $version->getScreenId()]) ?? new ScreenDefinition();
        $screen
            ->setScreenId($version->getScreenId())
            ->setPageType($version->getPageType())
            ->setSchemaVersion((string) ($definition['schemaVersion'] ?? '1.0'))
            ->setDefinition($definition)
            ->setStatus('published')
            ->setVersion($version->getVersion());
        $this->entityManager->persist($screen);

        $this->syncRuntimeEndpoints($version);

        $version
            ->setStatus('published')
            ->setPublishedAt(new \DateTimeImmutable());
        $this->entityManager->persist($version);
        $this->entityManager->flush();
        $this->integrity->signProgram($program, ['source' => 'publishVersion']);
        $this->integrity->signScreen($screen, ['source' => 'publishVersion']);
        $this->signRuntimeEndpointsForScreen($version->getScreenId());
        $this->integrity->signProgramVersion($version, [
            'source' => 'publishVersion',
            'grantId' => $grant?->getId(),
        ]);
        if ($grant) {
            $this->governance->consumeGrant($grant);
            $this->integrity->signTarget('program_change_grant', (int) $grant->getId(), ['source' => 'publishVersion.consumeGrant']);
        }
        $this->entityManager->flush();
        $this->events->publish('builder.program.published', [
            'tenantId' => $this->permissions->getTenantId(),
            'userId' => $this->permissions->getUserId(),
            'screenId' => $version->getScreenId(),
            'programCode' => $version->getProgramCode(),
            'entityCode' => $version->getBuilderEntityCode(),
            'recordId' => $version->getId(),
            'operation' => 'publish',
            'before' => [],
            'after' => [
                'versionId' => $version->getId(),
                'version' => $version->getVersion(),
                'status' => $version->getStatus(),
            ],
            'changes' => [],
            'transactionId' => null,
            'occurredAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ], [
            'source' => 'program_builder',
            'screenId' => $version->getScreenId(),
            'programCode' => $version->getProgramCode(),
            'entityCode' => $version->getBuilderEntityCode(),
            'recordId' => $version->getId(),
            'operation' => 'publish',
        ]);

        return $this->getProgram($version->getProgramCode());
    }

    public function duplicateVersion(int $id): array
    {
        $this->assertAdminWrite();

        $source = $this->versions->find($id);
        if (!$source) {
            throw new RuntimeHttpException('PROGRAM_VERSION_NOT_FOUND', 'Versao do programa nao encontrada.', 404, ['id' => $id]);
        }

        $nextVersion = $this->nextVersion($source->getVersion());
        $builderConfig = $this->publicBuilderConfig($source->getBuilderConfig());
        $builderConfig['version'] = $nextVersion;
        $generatedDefinition = $source->getGeneratedDefinition();
        if (isset($generatedDefinition['program']) && is_array($generatedDefinition['program'])) {
            $generatedDefinition['program']['version'] = $nextVersion;
        }

        $copy = (new BuilderProgramVersion())
            ->setProgramCode($source->getProgramCode())
            ->setProgramTitle($source->getProgramTitle())
            ->setModule($source->getModule())
            ->setPageType($source->getPageType())
            ->setBuilderEntityCode($source->getBuilderEntityCode())
            ->setScreenId($source->getScreenId())
            ->setVersion($nextVersion)
            ->setStatus('draft')
            ->setSubtitle($source->getSubtitle())
            ->setIcon($source->getIcon())
            ->setPermissionPrefix($source->getPermissionPrefix())
            ->setAllowCreate($source->isAllowCreate())
            ->setAllowUpdate($source->isAllowUpdate())
            ->setAllowDelete($source->isAllowDelete())
            ->setChangeSummary($source->getChangeSummary())
            ->setProgramOrigin($source->getProgramOrigin())
            ->setOwnerScope($source->getOwnerScope())
            ->setCustomizationPolicy($source->getCustomizationPolicy())
            ->setSubscriberId($source->getSubscriberId())
            ->setBaseProgramCode($source->getBaseProgramCode())
            ->setBaseProgramVersionId($source->getBaseProgramVersionId())
            ->setUpgradeFrozen($source->isUpgradeFrozen())
            ->setFrozenReason($source->getFrozenReason())
            ->setBuilderConfig($builderConfig)
            ->setGeneratedDefinition($generatedDefinition);

        $this->entityManager->persist($copy);
        $this->entityManager->flush();
        $this->integrity->signProgramVersion($copy, ['source' => 'duplicateVersion']);
        $this->entityManager->flush();

        return $this->versionPayload($copy);
    }

    public function createGovernanceRequest(array $payload): array
    {
        $this->assertAdminWrite();

        $programCode = $this->safeCode((string) ($payload['programCode'] ?? ''));
        $builderEntityCode = $this->normalizeOptionalCode((string) ($payload['builderEntityCode'] ?? ''));
        $reason = trim((string) ($payload['reason'] ?? ''));
        $requestedActions = array_values(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            (array) ($payload['requestedActions'] ?? ['edit', 'publish'])
        )));
        if ($programCode === '') {
            throw new RuntimeHttpException('PROGRAM_CHANGE_REQUEST_PROGRAM_REQUIRED', 'Informe o codigo do programa para solicitar alteracao.', 422);
        }
        if (!$requestedActions) {
            throw new RuntimeHttpException('PROGRAM_CHANGE_REQUEST_ACTIONS_REQUIRED', 'Informe ao menos uma acao para a solicitacao.', 422);
        }

        $request = $this->governance->createChangeRequest(
            $programCode,
            $builderEntityCode !== '' ? $builderEntityCode : null,
            $requestedActions,
            $reason !== '' ? $reason : null
        );
        $this->entityManager->flush();
        $this->integrity->signTarget('program_change_request', (int) $request->getId(), ['source' => 'governance.request']);
        $this->entityManager->flush();

        return [
            'request' => [
                'id' => $request->getId(),
                'requestCode' => $request->getRequestCode(),
                'programCode' => $request->getProgramCode(),
                'builderEntityCode' => $request->getBuilderEntityCode(),
                'requestedBy' => $request->getRequestedBy(),
                'requestedActions' => $request->getRequestedActions(),
                'reason' => $request->getReason(),
                'status' => $request->getStatus(),
            ],
        ];
    }

    public function approveGovernanceRequest(array $payload): array
    {
        $this->assertAdminWrite();

        $requestId = $this->normalizePositiveInt($payload['requestId'] ?? null);
        $grantedToUserId = trim((string) ($payload['grantedToUserId'] ?? $this->permissions->getUserId()));
        $allowedActions = array_values(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            (array) ($payload['allowedActions'] ?? ['edit', 'publish'])
        )));
        if (!$requestId || $grantedToUserId === '') {
            throw new RuntimeHttpException('PROGRAM_CHANGE_GRANT_REQUIRED_FIELDS', 'Informe a solicitacao e o usuario que recebera a liberacao.', 422);
        }

        /** @var ProgramChangeRequest|null $request */
        $request = $this->entityManager->find(ProgramChangeRequest::class, $requestId);
        if (!$request) {
            throw new RuntimeHttpException('PROGRAM_CHANGE_REQUEST_NOT_FOUND', 'Solicitacao de alteracao nao encontrada.', 404, ['requestId' => $requestId]);
        }

        $grant = $this->governance->createGrant($request, $grantedToUserId, $allowedActions ?: ['edit', 'publish']);
        $this->entityManager->flush();
        $this->integrity->signTarget('program_change_request', (int) $request->getId(), ['source' => 'governance.request.approved']);
        $this->integrity->signTarget('program_change_grant', (int) $grant->getId(), ['source' => 'governance.grant']);
        $this->entityManager->flush();

        return [
            'grant' => $this->governance->grantPayload($grant),
        ];
    }

    public function changeGovernanceGrantStatus(array $payload): array
    {
        $this->assertAdminWrite();
        $grantId = $this->normalizePositiveInt($payload['grantId'] ?? null);
        $status = strtolower(trim((string) ($payload['status'] ?? '')));
        if (!$grantId || $status === '') {
            throw new RuntimeHttpException('PROGRAM_CHANGE_GRANT_STATUS_REQUIRED', 'Informe grant e status para alterar a autorizacao.', 422);
        }

        $grant = $this->governance->changeGrantStatus($grantId, $status);
        $this->entityManager->flush();
        $this->integrity->signTarget('program_change_grant', (int) $grant->getId(), ['source' => 'governance.grant.status']);
        $this->entityManager->flush();

        return [
            'grant' => $this->governance->grantPayload($grant),
        ];
    }

    public function registerGovernanceTest(array $payload): array
    {
        $this->assertAdminWrite();
        $versionId = $this->normalizePositiveInt($payload['builderProgramVersionId'] ?? null);
        $bundleId = trim((string) ($payload['bundleId'] ?? ''));
        $testPlanId = trim((string) ($payload['testPlanId'] ?? 'roteiro-web'));
        $status = strtolower(trim((string) ($payload['status'] ?? 'passed')));
        $checklist = is_array($payload['checklistSnapshot'] ?? null) ? $payload['checklistSnapshot'] : [];
        $evidences = is_array($payload['evidences'] ?? null) ? $payload['evidences'] : [];
        $notes = trim((string) ($payload['notes'] ?? ''));
        if (!$versionId || $bundleId === '') {
            throw new RuntimeHttpException('PROGRAM_TEST_EXECUTION_REQUIRED_FIELDS', 'Informe versao e bundle para registrar o roteiro.', 422);
        }

        $version = $this->versions->find($versionId);
        if (!$version) {
            throw new RuntimeHttpException('PROGRAM_VERSION_NOT_FOUND', 'Versao do programa nao encontrada.', 404, ['id' => $versionId]);
        }

        $test = $this->governance->registerTestExecution(
            $version,
            $bundleId,
            $testPlanId,
            $status,
            $checklist,
            $evidences,
            $notes !== '' ? $notes : null
        );
        $this->entityManager->flush();
        $this->integrity->signTarget('program_test_execution', (int) $test->getId(), ['source' => 'governance.test']);
        $this->entityManager->flush();

        return [
            'testExecution' => [
                'id' => $test->getId(),
                'programCode' => $test->getProgramCode(),
                'builderProgramVersionId' => $test->getBuilderProgramVersionId(),
                'bundleId' => $test->getBundleId(),
                'testPlanId' => $test->getTestPlanId(),
                'status' => $test->getStatus(),
                'executedBy' => $test->getExecutedBy(),
                'executedAt' => $test->getExecutedAt()?->format(DATE_ATOM),
            ],
        ];
    }

    public function approveGovernancePublication(array $payload): array
    {
        $this->assertAdminWrite();
        $versionId = $this->normalizePositiveInt($payload['builderProgramVersionId'] ?? null);
        $bundleId = trim((string) ($payload['bundleId'] ?? ''));
        if (!$versionId || $bundleId === '') {
            throw new RuntimeHttpException('PROGRAM_PUBLICATION_APPROVAL_REQUIRED_FIELDS', 'Informe versao e bundle para aprovar a publicacao.', 422);
        }

        $version = $this->versions->find($versionId);
        if (!$version) {
            throw new RuntimeHttpException('PROGRAM_VERSION_NOT_FOUND', 'Versao do programa nao encontrada.', 404, ['id' => $versionId]);
        }

        $approval = $this->governance->approvePublication($version, $bundleId);
        $this->entityManager->flush();
        $this->integrity->signTarget('program_publication_approval', (int) $approval->getId(), ['source' => 'governance.approval']);
        $this->entityManager->flush();

        return [
            'approval' => [
                'id' => $approval->getId(),
                'programCode' => $approval->getProgramCode(),
                'builderProgramVersionId' => $approval->getBuilderProgramVersionId(),
                'testExecutionBundleId' => $approval->getTestExecutionBundleId(),
                'status' => $approval->getStatus(),
                'approvedBy' => $approval->getApprovedBy(),
                'approvedAt' => $approval->getApprovedAt()?->format(DATE_ATOM),
            ],
        ];
    }

    public function governanceDashboard(array $payload): array
    {
        $this->assertAdminRead();

        $programCode = $this->safeCode((string) ($payload['programCode'] ?? ''));
        $builderProgramVersionId = $this->normalizePositiveInt($payload['builderProgramVersionId'] ?? null);
        if ($programCode === '') {
            throw new RuntimeHttpException('PROGRAM_GOVERNANCE_PROGRAM_REQUIRED', 'Informe o codigo do programa para consultar a governanca.', 422);
        }

        return $this->governance->dashboard($programCode, $builderProgramVersionId);
    }

    public function governanceAudit(array $payload): array
    {
        $this->assertAdminRead();

        $programCode = $this->safeCode((string) ($payload['programCode'] ?? ''));
        $builderProgramVersionId = $this->normalizePositiveInt($payload['builderProgramVersionId'] ?? null);
        if ($programCode === '') {
            throw new RuntimeHttpException('PROGRAM_GOVERNANCE_PROGRAM_REQUIRED', 'Informe o codigo do programa para consultar a auditoria.', 422);
        }

        return $this->governance->audit($programCode, $builderProgramVersionId, [
            'eventType' => $payload['eventType'] ?? null,
            'userId' => $payload['userId'] ?? null,
            'dateFrom' => $payload['dateFrom'] ?? null,
            'dateTo' => $payload['dateTo'] ?? null,
        ]);
    }

    public function governanceRetentionPolicy(): array
    {
        $this->assertAdminRead();
        return $this->governance->retentionPolicy();
    }

    public function governanceOperationsSnapshot(array $payload): array
    {
        $this->assertAdminRead();
        $programCode = $this->safeCode((string) ($payload['programCode'] ?? ''));
        $builderProgramVersionId = $this->normalizePositiveInt($payload['builderProgramVersionId'] ?? null);

        return $this->governance->operationsSnapshot($programCode, $builderProgramVersionId);
    }

    public function runGovernanceMonitor(array $payload): array
    {
        $this->assertAdminWrite();
        $programCode = $this->safeCode((string) ($payload['programCode'] ?? ''));

        return $this->governance->runOperationalMonitor($programCode !== '' ? $programCode : null);
    }

    public function runGovernanceOperations(array $payload): array
    {
        $this->assertAdminWrite();
        $this->assertMaintenanceEnvironmentAllowed('operacao unificada da governanca');
        $programCode = $this->safeCode((string) ($payload['programCode'] ?? ''));
        $applyCleanup = (bool) ($payload['applyCleanup'] ?? false);

        return $this->governance->runOperations($programCode !== '' ? $programCode : null, $applyCleanup);
    }

    public function updateGovernanceRetentionPolicy(array $payload): array
    {
        $this->assertAdminWrite();
        return $this->governance->updateRetentionPolicy($payload);
    }

    public function previewGovernanceRetentionCleanup(): array
    {
        $this->assertAdminRead();
        return $this->governance->previewRetentionCleanup();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listGovernanceRetentionRuns(int $limit = 20): array
    {
        $this->assertAdminRead();
        return $this->governance->listRetentionRuns($limit);
    }

    public function executeGovernanceRetentionCleanup(?array $payload = null): array
    {
        $this->assertAdminWrite();
        $this->assertMaintenanceEnvironmentAllowed('limpeza de historico da governanca');
        $previewRunId = $this->normalizePositiveInt(($payload ?? [])['previewRunId'] ?? null);
        return $this->governance->executeRetentionCleanup($previewRunId);
    }

    public function previewOverlayRebase(int $overlayId): array
    {
        $this->assertAdminWrite();
        return $this->overlays->previewRebase($overlayId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listOverlayVersions(int $overlayId): array
    {
        $this->assertAdminRead();
        return $this->governance->listOverlayVersions($overlayId);
    }

    public function compareOverlayVersions(array $payload): array
    {
        $this->assertAdminRead();
        $leftVersionId = $this->normalizePositiveInt($payload['leftVersionId'] ?? null);
        $rightVersionId = $this->normalizePositiveInt($payload['rightVersionId'] ?? null);
        if (!$leftVersionId || !$rightVersionId) {
            throw new RuntimeHttpException('PROGRAM_OVERLAY_VERSION_COMPARE_REQUIRED', 'Informe as duas versoes de overlay que serao comparadas.', 422);
        }

        return $this->governance->compareOverlayVersions($leftVersionId, $rightVersionId);
    }

    public function rebaseOverlayVersion(int $overlayVersionId, array $payload = []): array
    {
        $this->assertAdminWrite();
        $this->assertMaintenanceEnvironmentAllowed('rebase de overlay');
        $preview = $this->overlays->previewRebaseVersion($overlayVersionId);
        $targetBaseVersionId = (int) ($preview['targetBaseVersionId'] ?? 0);
        if ($targetBaseVersionId > 0) {
            $targetBaseVersion = $this->versions->find($targetBaseVersionId);
            if ($targetBaseVersion instanceof BuilderProgramVersion) {
                $this->assertPublicationEnvironmentAllowed($targetBaseVersion);
            }
        }
        $resolutions = is_array($payload['resolutions'] ?? null) ? $payload['resolutions'] : [];
        $result = $this->overlays->rebase($overlayVersionId, $resolutions);
        $this->entityManager->flush();
        $this->events->publish('builder.overlay.rebased', [
            'tenantId' => $this->permissions->getTenantId(),
            'userId' => $this->permissions->getUserId(),
            'screenId' => 'admin.programa-overlay-versoes',
            'programCode' => (string) ($result['programCode'] ?? ''),
            'entityCode' => null,
            'recordId' => $overlayVersionId,
            'operation' => 'rebase',
            'before' => $preview,
            'after' => $result,
            'changes' => [],
            'transactionId' => null,
            'occurredAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ], [
            'source' => 'program_builder',
            'screenId' => 'admin.programa-overlay-versoes',
            'programCode' => (string) ($result['programCode'] ?? ''),
            'recordId' => $overlayVersionId,
            'operation' => 'rebase',
        ]);
        return $result;
    }

    public function publishOverlayVersion(int $overlayVersionId): array
    {
        $this->assertAdminWrite();
        $this->assertMaintenanceEnvironmentAllowed('publicacao de overlay');
        $result = $this->governance->publishOverlayVersion($overlayVersionId);
        $this->entityManager->flush();
        return $result;
    }

    public function resignIntegrityRecord(array $payload): array
    {
        $this->assertAdminWrite();
        $this->assertMaintenanceEnvironmentAllowed('reassinatura estrutural');

        $record = is_array($payload['record'] ?? null) ? $payload['record'] : [];
        $tableName = strtolower(trim((string) ($payload['tableName'] ?? $record['table_name'] ?? '')));
        $recordId = $this->normalizePositiveInt($payload['recordId'] ?? $record['record_id'] ?? null);
        if ($tableName === '' || !$recordId) {
            throw new RuntimeHttpException('STRUCTURAL_INTEGRITY_TARGET_REQUIRED', 'Informe a tabela e o registro que serao reassinados.', 422);
        }

        $metadata = [
            'source' => 'admin.integridade.ui',
            'triggeredBy' => $this->permissions->getUserId(),
            'tenantId' => $this->permissions->getTenantId(),
            'sessionId' => $this->permissions->getSessionId(),
        ];
        $metadata['reason'] = trim((string) ($payload['reason'] ?? 'Reassinatura manual via admin.integridade'));

        $this->integrity->resignTarget($tableName, $recordId, $metadata);
        $this->entityManager->flush();
        $status = $this->integrity->verifyTarget($tableName, $recordId);
        $this->entityManager->flush();

        return [
            'integrity' => $status,
        ];
    }

    private function assertAdminRead(): void
    {
        if (!$this->permissions->hasPermission('admin.read')) {
            throw new RuntimeHttpException('ADMIN_FORBIDDEN', 'Voce nao possui permissao para acessar o construtor de programas.', 403);
        }
    }

    private function assertAdminWrite(): void
    {
        if (!$this->permissions->hasPermission('admin.write')) {
            throw new RuntimeHttpException('ADMIN_FORBIDDEN', 'Voce nao possui permissao para alterar o construtor de programas.', 403);
        }
    }

    private function entityPayload(BuilderEntity $entity): array
    {
        $fields = [];
        foreach ($entity->getFields() as $field) {
            $options = $field->getOptions();
            $fields[] = [
                'id' => $field->getId(),
                'code' => $field->getCode(),
                'label' => $field->getLabel(),
                'dataType' => $field->getDataType(),
                'required' => $field->isRequired(),
                'primaryKey' => $field->isPrimaryKey(),
                'length' => $field->getLength(),
                'precision' => $field->getPrecisionValue(),
                'scale' => $field->getScaleValue(),
                'position' => $field->getPosition(),
                'options' => $options,
                'columnName' => (string) ($options['columnName'] ?? $field->getCode()),
                'originalCode' => $field->getCode(),
                'originalColumnName' => (string) ($options['columnName'] ?? $field->getCode()),
                'defaultValue' => $options['defaultValue'] ?? null,
                'unique' => ($options['unique'] ?? false) === true,
                'foreignKeyTable' => is_array($options['foreignKey'] ?? null) ? ($options['foreignKey']['table'] ?? null) : null,
                'foreignKeyColumn' => is_array($options['foreignKey'] ?? null) ? ($options['foreignKey']['column'] ?? null) : null,
                'foreignKeyOnDelete' => is_array($options['foreignKey'] ?? null) ? ($options['foreignKey']['onDelete'] ?? null) : null,
                'foreignKeyOnUpdate' => is_array($options['foreignKey'] ?? null) ? ($options['foreignKey']['onUpdate'] ?? null) : null,
                'foreignKeyDependencyType' => is_array($options['foreignKey'] ?? null) ? ($options['foreignKey']['dependencyType'] ?? null) : null,
                'readonlyField' => ($options['readonly'] ?? false) === true || ($options['writable'] ?? true) === false,
                'optionItems' => is_array($options['options'] ?? null) ? $options['options'] : [],
                'virtualField' => ($options['virtual'] ?? false) === true,
                'includeInVersion' => ($options['includeInVersion'] ?? true) !== false,
                'versionRefEntityCode' => is_array($options['versionReference'] ?? null) ? ($options['versionReference']['sourceEntityCode'] ?? null) : null,
                'versionRefSourceIdField' => is_array($options['versionReference'] ?? null) ? ($options['versionReference']['sourceIdField'] ?? null) : null,
                'versionSnapshotVersionField' => is_array($options['versionSnapshot'] ?? null) ? ($options['versionSnapshot']['versionField'] ?? null) : null,
                'versionSnapshotPath' => is_array($options['versionSnapshot'] ?? null) ? ($options['versionSnapshot']['path'] ?? null) : null,
                'customCodeMode' => is_array($options['customCode'] ?? null) ? ($options['customCode']['mode'] ?? null) : null,
                'customCodePrefix' => is_array($options['customCode'] ?? null) ? ($options['customCode']['prefix'] ?? null) : null,
                'customCodePattern' => is_array($options['customCode'] ?? null) ? ($options['customCode']['pattern'] ?? null) : null,
                'customCodeSequenceEnabled' => is_array($options['customCode'] ?? null) ? (($options['customCode']['sequenceEnabled'] ?? true) !== false) : true,
                'customCodeSequenceScope' => is_array($options['customCode'] ?? null) ? ($options['customCode']['sequenceScope'] ?? null) : null,
                'customCodeSequencePadding' => is_array($options['customCode'] ?? null) ? ($options['customCode']['sequencePadding'] ?? null) : null,
                'customCodeStaticClass' => is_array($options['customCode'] ?? null) ? ($options['customCode']['staticClass'] ?? null) : null,
                'customCodeStaticMethod' => is_array($options['customCode'] ?? null) ? ($options['customCode']['staticMethod'] ?? null) : null,
                'customCodeAssistantScreenId' => is_array($options['customCode'] ?? null) ? ($options['customCode']['assistantScreenId'] ?? null) : null,
                'customCodePromptTitle' => is_array($options['customCode'] ?? null) ? ($options['customCode']['promptTitle'] ?? null) : null,
                'customCodePromptFields' => is_array($options['customCode'] ?? null) && is_array($options['customCode']['promptFields'] ?? null) ? $options['customCode']['promptFields'] : [],
                'apiJsonPath' => is_array($options['api'] ?? null) ? ($options['api']['jsonPath'] ?? null) : null,
                'apiWritePath' => is_array($options['api'] ?? null) ? ($options['api']['writePath'] ?? null) : null,
                'apiShowInGrid' => !is_array($options['api'] ?? null) || ($options['api']['showInGrid'] ?? true) !== false,
                'apiShowInForm' => !is_array($options['api'] ?? null) || ($options['api']['showInForm'] ?? true) !== false,
                'apiShowInFilter' => is_array($options['api'] ?? null) && ($options['api']['showInFilter'] ?? false) === true,
            ];
        }

        $metadata = $entity->getMetadata();
        if (is_array($metadata['apiSource'] ?? null)) {
            $metadata['apiSource'] = $this->maskApiSourceSecrets($metadata['apiSource']);
        }

        return [
            'code' => $entity->getCode(),
            'name' => $entity->getName(),
            'entityType' => $entity->getEntityType(),
            'tableName' => $entity->getTableName(),
            'status' => $entity->getStatus(),
            'situationEnabled' => $entity->isSituationEnabled(),
            'situationFieldCode' => $entity->getSituationFieldCode(),
            'metadata' => $metadata,
            'apiSource' => $this->maskApiSourceSecrets(is_array($entity->getMetadata()['apiSource'] ?? null) ? $entity->getMetadata()['apiSource'] : null),
            'apiSourceCode' => (string) ($entity->getMetadata()['apiBinding']['sourceCode'] ?? ''),
            'apiListOperationCode' => (string) ($entity->getMetadata()['apiBinding']['listOperationCode'] ?? ''),
            'apiDetailOperationCode' => (string) ($entity->getMetadata()['apiBinding']['detailOperationCode'] ?? ''),
            'apiCreateOperationCode' => (string) ($entity->getMetadata()['apiBinding']['createOperationCode'] ?? ''),
            'apiUpdateOperationCode' => (string) ($entity->getMetadata()['apiBinding']['updateOperationCode'] ?? ''),
            'apiDeleteOperationCode' => (string) ($entity->getMetadata()['apiBinding']['deleteOperationCode'] ?? ''),
            'structureModuleCode' => (string) ($entity->getMetadata()['structure']['moduleCode'] ?? ''),
            'structureType' => (string) ($entity->getMetadata()['structure']['type'] ?? 'main'),
            'structureBaseNumber' => $entity->getMetadata()['structure']['baseNumber'] ?? null,
            'structureSequenceNumber' => $entity->getMetadata()['structure']['sequenceNumber'] ?? null,
            'structureParentEntityCode' => (string) ($entity->getMetadata()['structure']['parentEntityCode'] ?? ''),
            'structureLeftEntityCode' => (string) ($entity->getMetadata()['structure']['leftEntityCode'] ?? ''),
            'structureRightEntityCode' => (string) ($entity->getMetadata()['structure']['rightEntityCode'] ?? ''),
            'subscriberIsolationMode' => (string) ($entity->getMetadata()['subscriberIsolation']['mode'] ?? 'none'),
            'subscriberColumnName' => (string) ($entity->getMetadata()['subscriberIsolation']['columnName'] ?? ''),
            'subscriberGlobalTable' => ($entity->getMetadata()['subscriberIsolation']['globalTable'] ?? false) === true,
            'softDeleteEnabled' => ($entity->getMetadata()['softDelete']['enabled'] ?? false) === true,
            'softDeleteDeletedAtField' => (string) ($entity->getMetadata()['softDelete']['deletedAtField'] ?? ''),
            'softDeleteDeletedByField' => (string) ($entity->getMetadata()['softDelete']['deletedByField'] ?? ''),
            'softDeleteReasonField' => (string) ($entity->getMetadata()['softDelete']['reasonField'] ?? ''),
            'uniqueKeys' => $this->entityUniqueKeysPayload($entity->getMetadata()['uniqueKeys'] ?? []),
            'rules' => $this->entityRulesPayload($entity->getMetadata()['rules'] ?? []),
            'versioningEnabled' => ($entity->getMetadata()['versioning']['enabled'] ?? false) === true,
            'versioningDeduplicate' => ($entity->getMetadata()['versioning']['deduplicate'] ?? true) !== false,
            'fields' => $fields,
            'supportsPhysicalCrud' => $entity->getEntityType() === 'persistence',
        ];
    }

    private function entityVersionPayload(BuilderEntityVersion $version): array
    {
        return [
            'id' => $version->getId(),
            'builderEntityCode' => $version->getBuilderEntityCode(),
            'entityName' => $version->getEntityName(),
            'entityType' => $version->getEntityType(),
            'tableName' => $version->getTableName(),
            'revision' => $version->getRevision(),
            'status' => $version->getStatus(),
            'action' => $version->getAction(),
            'sourceVersionId' => $version->getSourceVersionId(),
            'changeSummary' => $version->getChangeSummary(),
            'snapshot' => $version->getSnapshot(),
            'createdAt' => $version->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $version->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    private function modulePayload(BuilderModule $module): array
    {
        return [
            'id' => $module->getId(),
            'code' => $module->getCode(),
            'name' => $module->getName(),
            'abbreviation' => $module->getAbbreviation(),
            'numberStart' => $module->getNumberStart(),
            'numberEnd' => $module->getNumberEnd(),
            'enabled' => $module->isEnabled(),
            'metadata' => $module->getMetadata(),
            'createdAt' => $module->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $module->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    private function apiSourceSummaryPayload(BuilderApiSource $source): array
    {
        $metadata = $source->getMetadata();
        $operations = $this->apiSourceOperationsFromMetadata($metadata);
        return [
            'id' => $source->getId(),
            'code' => $source->getCode(),
            'name' => $source->getName(),
            'providerType' => (string) ($metadata['providerType'] ?? 'generic'),
            'authMode' => $source->getAuthMode(),
            'baseUrl' => $source->getBaseUrl(),
            'openapiUrl' => $source->getOpenapiUrl(),
            'status' => $source->getStatus(),
            'operationsCount' => count($operations),
            'createdAt' => $source->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $source->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    private function apiSourcePayload(BuilderApiSource $source): array
    {
        $metadata = $source->getMetadata();
        $providerType = (string) ($metadata['providerType'] ?? 'generic');
        return [
            'id' => $source->getId(),
            'code' => $source->getCode(),
            'name' => $source->getName(),
            'providerType' => $providerType,
            'authMode' => $source->getAuthMode(),
            'baseUrl' => $source->getBaseUrl(),
            'openapiUrl' => $source->getOpenapiUrl(),
            'status' => $source->getStatus(),
            'timeoutSeconds' => (int) ($metadata['timeoutSeconds'] ?? 20),
            'authHeaders' => $this->maskApiHeaderSecrets(is_array($metadata['authHeaders'] ?? null) ? $metadata['authHeaders'] : []),
            'operations' => $this->maskApiOperationSecrets($this->apiSourceOperationsFromMetadata($metadata)),
            'odoo' => $providerType === 'odoo' ? $this->maskOdooSourceSecrets(is_array($metadata['odoo'] ?? null) ? $metadata['odoo'] : []) : null,
            'createdAt' => $source->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $source->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    private function normalizeLockScopeType(string $scopeType): string
    {
        $normalized = strtolower(trim($scopeType));
        return in_array($normalized, ['module', 'entity', 'program'], true) ? $normalized : '';
    }

    private function normalizeProgramOrigin(string $value): string
    {
        $normalized = strtolower(trim($value));
        return in_array($normalized, ['standard', 'customer_overlay', 'customer_custom'], true) ? $normalized : 'standard';
    }

    private function normalizeOwnerScope(string $value): string
    {
        $normalized = strtolower(trim($value));
        return in_array($normalized, ['system', 'subscriber'], true) ? $normalized : 'system';
    }

    private function normalizeCustomizationPolicy(string $value): string
    {
        $normalized = strtolower(trim($value));
        return in_array($normalized, ['locked', 'overlay_only', 'full_override_allowed'], true) ? $normalized : 'overlay_only';
    }

    private function normalizeOptionalCode(string $value): string
    {
        return $this->safeCode($value);
    }

    private function editorLockPayload(BuilderEditorLock $lock): array
    {
        return [
            'id' => $lock->getId(),
            'scopeType' => $lock->getScopeType(),
            'scopeCode' => $lock->getScopeCode(),
            'displayName' => $lock->getDisplayName(),
            'userId' => $lock->getUserId(),
            'userName' => $lock->getUserName(),
            'sessionId' => $lock->getSessionId(),
            'lockToken' => $lock->getLockToken(),
            'status' => $lock->getStatus(),
            'acquiredAt' => $lock->getAcquiredAt()->format(DATE_ATOM),
            'grantId' => $lock->getGrantId(),
            'lockCategory' => $lock->getLockCategory(),
            'lastSeenAt' => $lock->getLastSeenAt()->format(DATE_ATOM),
            'expiresAt' => $lock->getExpiresAt()->format(DATE_ATOM),
            'releasedAt' => $lock->getReleasedAt()?->format(DATE_ATOM),
        ];
    }

    private function normalizeEntityPayload(array $payload): array
    {
        $code = $this->safeSqlIdentifier((string) ($payload['code'] ?? ''));
        $name = trim((string) ($payload['name'] ?? ''));
        $entityType = strtolower(trim((string) ($payload['entityType'] ?? 'persistence')));
        $tableName = $this->safeSqlIdentifier((string) ($payload['tableName'] ?? $code));
        $originalTableName = $this->safeSqlIdentifier((string) ($payload['originalTableName'] ?? $tableName));
        $rawFields = is_array($payload['fields'] ?? null) ? $payload['fields'] : [];
        $structureModuleCode = $this->safeCode((string) ($payload['structureModuleCode'] ?? ''));
        $structureType = strtolower(trim((string) ($payload['structureType'] ?? 'main')));
        $structureBaseNumber = $this->normalizePositiveInt($payload['structureBaseNumber'] ?? null);
        $structureSequenceNumber = $this->normalizePositiveInt($payload['structureSequenceNumber'] ?? null);
        $structureParentEntityCode = $this->safeSqlIdentifier((string) ($payload['structureParentEntityCode'] ?? ''));
        $structureLeftEntityCode = $this->safeSqlIdentifier((string) ($payload['structureLeftEntityCode'] ?? ''));
        $structureRightEntityCode = $this->safeSqlIdentifier((string) ($payload['structureRightEntityCode'] ?? ''));
        $skipStructureValidation = ($payload['skipStructureValidation'] ?? false) === true;
        $situationEnabled = (bool) ($payload['situationEnabled'] ?? false);
        $situationFieldCode = $situationEnabled ? $this->safeSqlIdentifier((string) ($payload['situationFieldCode'] ?? 'status')) : null;
        $versioningEnabled = $entityType === 'persistence' && ($payload['versioningEnabled'] ?? false) === true;
        $versioningDeduplicate = ($payload['versioningDeduplicate'] ?? true) !== false;
        $softDelete = $this->normalizeSoftDeleteConfig($payload, $entityType, $rawFields);
        $subscriberIsolation = $this->normalizeSubscriberIsolationConfig($payload, $entityType, $rawFields);
        $apiSourceCode = $this->safeCode((string) ($payload['apiSourceCode'] ?? ''));
        $apiListOperationCode = $this->safeCode((string) ($payload['apiListOperationCode'] ?? ''));
        $apiDetailOperationCode = $this->safeCode((string) ($payload['apiDetailOperationCode'] ?? ''));
        $apiCreateOperationCode = $this->safeCode((string) ($payload['apiCreateOperationCode'] ?? ''));
        $apiUpdateOperationCode = $this->safeCode((string) ($payload['apiUpdateOperationCode'] ?? ''));
        $apiDeleteOperationCode = $this->safeCode((string) ($payload['apiDeleteOperationCode'] ?? ''));
        $apiSource = $entityType === 'api' && $apiSourceCode === ''
            ? $this->normalizeApiSource(is_array($payload['apiSource'] ?? null) ? $payload['apiSource'] : [])
            : null;
        $apiBinding = null;
        if ($entityType === 'api' && $apiSourceCode !== '') {
            $apiSourceEntity = $this->apiSources->findOneBy(['code' => $apiSourceCode]);
            if (!$apiSourceEntity) {
                throw new RuntimeHttpException('ENTITY_API_SOURCE_NOT_FOUND', 'Cadastro de API nao encontrado.', 422, [
                    'apiSourceCode' => $apiSourceCode,
                ]);
            }
            $apiBinding = [
                'sourceCode' => $apiSourceCode,
                'listOperationCode' => $apiListOperationCode,
                'detailOperationCode' => $apiDetailOperationCode,
                'createOperationCode' => $apiCreateOperationCode,
                'updateOperationCode' => $apiUpdateOperationCode,
                'deleteOperationCode' => $apiDeleteOperationCode,
            ];
            $apiSource = $this->resolveApiSourceBinding($apiSourceEntity, $apiListOperationCode, $apiDetailOperationCode, $apiCreateOperationCode, $apiUpdateOperationCode, $apiDeleteOperationCode);
        }

        if ($code === '' || $name === '') {
            throw new RuntimeHttpException('ENTITY_BUILDER_REQUIRED_FIELDS', 'Informe codigo, nome e nome da tabela da entidade.', 422);
        }
        if (!in_array($entityType, ['persistence', 'query', 'io', 'api'], true)) {
            throw new RuntimeHttpException('ENTITY_TYPE_INVALID', 'Tipo de entidade invalido.', 422, [
                'entityType' => $entityType,
            ]);
        }
        if ($entityType === 'persistence' && $tableName === '') {
            throw new RuntimeHttpException('ENTITY_TABLE_REQUIRED', 'Informe o nome da tabela fisica para entidades persistentes.', 422);
        }
        if ($entityType !== 'persistence' && ($payload['createPhysicalTable'] ?? false)) {
            throw new RuntimeHttpException('ENTITY_TYPE_NOT_SUPPORTED_YET', 'Nesta etapa a criacao fisica esta suportada apenas para entidades do tipo persistence.', 422, [
                'entityType' => $entityType,
            ]);
        }
        $structure = $skipStructureValidation
            ? [
                'moduleCode' => $structureModuleCode,
                'type' => $structureType,
                'baseNumber' => $structureBaseNumber,
                'sequenceNumber' => $structureSequenceNumber,
                'parentEntityCode' => $structureParentEntityCode,
                'leftEntityCode' => $structureLeftEntityCode,
                'rightEntityCode' => $structureRightEntityCode,
            ]
            : $this->normalizeEntityStructure(
                $structureModuleCode,
                $structureType,
                $structureBaseNumber,
                $structureSequenceNumber,
                $structureParentEntityCode,
                $structureLeftEntityCode,
                $structureRightEntityCode,
                $entityType
            );
        if ($entityType !== 'api' && $entityType !== 'io') {
            $this->validateTableNamingPattern($tableName, $entityType, $originalTableName, $structure);
        }

        $fields = [];
        $hasPrimaryKey = false;
        foreach ($rawFields as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $fieldCode = $this->safeSqlIdentifier((string) ($item['code'] ?? ''));
            $label = trim((string) ($item['label'] ?? ''));
            $dataType = $this->normalizeBuilderFieldType((string) ($item['dataType'] ?? 'string'));
            if ($fieldCode === '' || $label === '') {
                continue;
            }

            $primaryKey = (bool) ($item['primaryKey'] ?? false);
            $required = $primaryKey ? true : (bool) ($item['required'] ?? false);
            $length = $this->normalizeFieldLength($item['length'] ?? null, $dataType);
            $precision = $this->normalizePositiveInt($item['precision'] ?? null);
            $scale = $this->normalizePositiveInt($item['scale'] ?? null);
            $options = is_array($item['options'] ?? null) ? $item['options'] : [];
            $virtualField = ($item['virtualField'] ?? false) === true;
            $columnName = $entityType === 'api'
                ? $fieldCode
                : $this->safeSqlIdentifier((string) ($item['columnName'] ?? $fieldCode));
            $options['columnName'] = $columnName;
            $apiJsonPath = $this->normalizeApiJsonPath((string) ($item['apiJsonPath'] ?? ''));
            $apiWritePath = $this->normalizeApiJsonPath((string) ($item['apiWritePath'] ?? ''));
            $apiShowInGrid = ($item['apiShowInGrid'] ?? true) !== false;
            $apiShowInForm = ($item['apiShowInForm'] ?? true) !== false;
            $apiShowInFilter = ($item['apiShowInFilter'] ?? false) === true;
            if ($entityType === 'api' && $apiJsonPath === '') {
                throw new RuntimeHttpException('ENTITY_API_FIELD_JSON_PATH_REQUIRED', 'Campo de entidade API precisa informar JSON path.', 422, [
                    'field' => $fieldCode,
                ]);
            }
            $defaultValue = $this->normalizeDefaultValue($item['defaultValue'] ?? null, $dataType);
            $unique = (bool) ($item['unique'] ?? false);
            $foreignKeyTable = $entityType === 'api' ? '' : $this->safeSqlIdentifier((string) ($item['foreignKeyTable'] ?? ''));
            $foreignKeyColumn = $entityType === 'api' ? '' : $this->safeSqlIdentifier((string) ($item['foreignKeyColumn'] ?? ''));
            $foreignKeyOnDelete = $this->normalizeForeignKeyAction($item['foreignKeyOnDelete'] ?? null);
            $foreignKeyOnUpdate = $this->normalizeForeignKeyAction($item['foreignKeyOnUpdate'] ?? null);
            $foreignKeyDependencyType = $this->normalizeDependencyType($item['foreignKeyDependencyType'] ?? null);
            $optionItems = $this->normalizeOptionItems($item['optionItems'] ?? null, $dataType);
            $includeInVersion = ($item['includeInVersion'] ?? true) !== false;
            $readonlyField = ($item['readonlyField'] ?? false) === true;
            $versionRefEntityCode = $this->safeSqlIdentifier((string) ($item['versionRefEntityCode'] ?? ''));
            $versionRefSourceIdField = $this->safeSqlIdentifier((string) ($item['versionRefSourceIdField'] ?? ''));
            $versionSnapshotVersionField = $this->safeSqlIdentifier((string) ($item['versionSnapshotVersionField'] ?? ''));
            $versionSnapshotPath = trim((string) ($item['versionSnapshotPath'] ?? ''));
            $customCodeMode = strtolower(trim((string) ($item['customCodeMode'] ?? '')));
            $customCodePrefix = trim((string) ($item['customCodePrefix'] ?? ''));
            $customCodePattern = trim((string) ($item['customCodePattern'] ?? ''));
            $customCodeSequenceEnabled = ($item['customCodeSequenceEnabled'] ?? true) !== false;
            $customCodeSequenceScope = in_array(($item['customCodeSequenceScope'] ?? null), ['global', 'year', 'month', 'day'], true) ? $item['customCodeSequenceScope'] : 'global';
            $customCodeSequencePadding = $this->normalizePositiveInt($item['customCodeSequencePadding'] ?? null) ?? 4;
            $customCodeStaticClass = $this->safePhpClassName((string) ($item['customCodeStaticClass'] ?? ''));
            $customCodeStaticMethod = $this->safePhpMethodName((string) ($item['customCodeStaticMethod'] ?? ''));
            $customCodeAssistantScreenId = $this->safeScreenId((string) ($item['customCodeAssistantScreenId'] ?? ''));
            $customCodePromptTitle = trim((string) ($item['customCodePromptTitle'] ?? ''));
            $customCodePromptFields = $this->normalizeCustomCodePromptFields($item['customCodePromptFields'] ?? null);
            if ($virtualField) {
                $required = false;
                $primaryKey = false;
                $unique = false;
                $defaultValue = null;
                $foreignKeyTable = '';
                $foreignKeyColumn = '';
                $foreignKeyOnDelete = null;
                $foreignKeyOnUpdate = null;
                $foreignKeyDependencyType = null;
                $options['virtual'] = true;
                $options['readonly'] = true;
                $options['writable'] = false;
            } else {
                unset($options['virtual']);
            }
            if ($entityType === 'api') {
                $options['api'] = [
                    'jsonPath' => $apiJsonPath,
                    'writePath' => $apiWritePath !== '' ? $apiWritePath : $apiJsonPath,
                    'showInGrid' => $apiShowInGrid,
                    'showInForm' => $apiShowInForm,
                    'showInFilter' => $apiShowInFilter,
                ];
                $defaultValue = null;
                $unique = false;
                $foreignKeyTable = '';
                $foreignKeyColumn = '';
                $foreignKeyOnDelete = null;
                $foreignKeyOnUpdate = null;
                $foreignKeyDependencyType = null;
                $virtualField = false;
                unset(
                    $options['foreignKey'],
                    $options['unique'],
                    $options['defaultValue'],
                    $options['versionReference'],
                    $options['versionSnapshot'],
                    $options['customCode']
                );
            } else {
                unset($options['api']);
            }
            if ($readonlyField) {
                $options['readonly'] = true;
                $options['writable'] = false;
            } else {
                unset($options['readonly'], $options['writable']);
            }
            if ($length !== null) {
                $options['maxLength'] = $length;
            }
            if ($dataType === 'decimal') {
                $precision = $precision ?? 18;
                $scale = $scale ?? 2;
                if ($scale > $precision) {
                    throw new RuntimeHttpException('ENTITY_FIELD_SCALE_INVALID', 'Scale nao pode ser maior que precision.', 422, [
                        'field' => $fieldCode,
                    ]);
                }
                $options['precision'] = $precision;
                $options['scale'] = $scale;
            } else {
                $precision = null;
                $scale = null;
                unset($options['precision'], $options['scale']);
            }
            if ($defaultValue !== null) {
                $options['defaultValue'] = $defaultValue;
            } else {
                unset($options['defaultValue']);
            }
            if ($unique) {
                $options['unique'] = true;
            } else {
                unset($options['unique']);
            }
            if ($foreignKeyTable !== '' && $foreignKeyColumn !== '') {
                $options['foreignKey'] = [
                    'table' => $foreignKeyTable,
                    'column' => $foreignKeyColumn,
                ];
                if ($foreignKeyOnDelete !== null) {
                    $options['foreignKey']['onDelete'] = $foreignKeyOnDelete;
                }
                if ($foreignKeyOnUpdate !== null) {
                    $options['foreignKey']['onUpdate'] = $foreignKeyOnUpdate;
                }
                if ($foreignKeyDependencyType !== null) {
                    $options['foreignKey']['dependencyType'] = $foreignKeyDependencyType;
                }
            } else {
                unset($options['foreignKey']);
            }
            if ($optionItems) {
                $options['options'] = $optionItems;
            } else {
                unset($options['options']);
            }
            if (!$includeInVersion) {
                $options['includeInVersion'] = false;
            } else {
                unset($options['includeInVersion']);
            }
            if ($versionRefEntityCode !== '' && $versionRefSourceIdField !== '') {
                $options['versionReference'] = [
                    'sourceEntityCode' => $versionRefEntityCode,
                    'sourceIdField' => $versionRefSourceIdField,
                ];
            } else {
                unset($options['versionReference']);
            }
            if ($versionSnapshotVersionField !== '' && $versionSnapshotPath !== '') {
                $options['versionSnapshot'] = [
                    'versionField' => $versionSnapshotVersionField,
                    'path' => $versionSnapshotPath,
                ];
            } else {
                unset($options['versionSnapshot']);
            }
            if ($dataType === 'custom_code') {
                $options['customCode'] = [
                    'mode' => in_array($customCodeMode, ['pattern', 'static_method'], true) ? $customCodeMode : 'pattern',
                    'prefix' => $customCodePrefix,
                    'pattern' => $customCodePattern !== '' ? $customCodePattern : '{YYYY}{MM}{DD}-{SEQ:4}',
                    'sequenceEnabled' => $customCodeSequenceEnabled,
                    'sequenceScope' => $customCodeSequenceScope,
                    'sequencePadding' => $customCodeSequencePadding,
                    'assistantScreenId' => $customCodeAssistantScreenId,
                    'promptTitle' => $customCodePromptTitle,
                    'promptFields' => $customCodePromptFields,
                ];
                if ($customCodeStaticClass !== '' && $customCodeStaticMethod !== '') {
                    $options['customCode']['staticClass'] = $customCodeStaticClass;
                    $options['customCode']['staticMethod'] = $customCodeStaticMethod;
                }
            } else {
                unset($options['customCode']);
            }

            $fields[] = [
                'id' => (int) ($item['id'] ?? 0),
                'code' => $fieldCode,
                'label' => $label,
                'dataType' => $dataType,
                'required' => $required,
                'primaryKey' => $primaryKey,
                'length' => $length,
                'precision' => $precision,
                'scale' => $scale,
                'options' => $options,
                'columnName' => $columnName,
                'virtualField' => $virtualField,
                'originalCode' => $this->safeSqlIdentifier((string) ($item['originalCode'] ?? $fieldCode)),
                'originalColumnName' => $this->safeSqlIdentifier((string) ($item['originalColumnName'] ?? $columnName)),
                'position' => (int) $index,
            ];
            if ($entityType !== 'api') {
                $this->validateFieldNamingPattern($fields[count($fields) - 1], $dataType, $foreignKeyTable !== '' && $foreignKeyColumn !== '');
            }
            if ($primaryKey) {
                $hasPrimaryKey = true;
            }
        }

        if (!$fields) {
            throw new RuntimeHttpException('ENTITY_BUILDER_FIELDS_REQUIRED', 'Cadastre ao menos um campo para a entidade.', 422);
        }
        if (!$hasPrimaryKey) {
            throw new RuntimeHttpException('ENTITY_BUILDER_PRIMARY_KEY_REQUIRED', 'Defina um campo como chave primaria.', 422);
        }
        if ($situationEnabled && !$this->fieldExistsInConfig($fields, (string) $situationFieldCode)) {
            throw new RuntimeHttpException('ENTITY_BUILDER_SITUATION_FIELD_REQUIRED', 'O campo de situacao precisa existir na lista de campos.', 422, [
                'situationFieldCode' => $situationFieldCode,
            ]);
        }

        $rules = $entityType === 'api' ? [] : $this->normalizeEntityRules($payload['rules'] ?? [], $fields);
        $uniqueKeys = $entityType === 'api' ? [] : $this->normalizeEntityUniqueKeys($payload['uniqueKeys'] ?? [], $fields);
        $this->validateUniqueKeyFieldPrefixes($uniqueKeys, $fields);

        return [
            'code' => $code,
            'name' => $name,
            'entityType' => $entityType,
            'tableName' => $entityType === 'persistence' ? $tableName : null,
            'apiSource' => $apiSource,
            'apiBinding' => $apiBinding,
            'fields' => $fields,
            'primaryKey' => $this->resolvePrimaryKeyCode($fields),
            'situationEnabled' => $situationEnabled,
            'situationFieldCode' => $situationFieldCode,
            'structure' => $structure,
            'subscriberIsolation' => $subscriberIsolation,
            'uniqueKeys' => $uniqueKeys,
            'rules' => $rules,
            'softDelete' => $softDelete,
            'versioningEnabled' => $versioningEnabled,
            'versioningDeduplicate' => $versioningDeduplicate,
            'createPhysicalTable' => $entityType === 'persistence' && ($payload['createPhysicalTable'] ?? true) !== false,
            'allowTableRename' => $entityType === 'persistence' && ($payload['allowTableRename'] ?? true) !== false,
            'allowColumnRename' => $entityType === 'persistence' && ($payload['allowColumnRename'] ?? true) !== false,
            'dropRemovedColumns' => $entityType === 'persistence' && ($payload['dropRemovedColumns'] ?? false) === true,
        ];
    }

    private function normalizeSubscriberIsolationConfig(array $payload, string $entityType, array $rawFields): array
    {
        if ($entityType !== 'persistence') {
            return [
                'mode' => 'none',
                'columnName' => null,
                'globalTable' => false,
            ];
        }

        $mode = strtolower(trim((string) ($payload['subscriberIsolationMode'] ?? 'none')));
        if (!in_array($mode, ['none', 'subscriber_column'], true)) {
            $mode = 'none';
        }
        $columnName = $this->safeSqlIdentifier((string) ($payload['subscriberColumnName'] ?? ''));
        $globalTable = ($payload['subscriberGlobalTable'] ?? false) === true;
        if ($mode !== 'subscriber_column') {
            if (!$globalTable) {
                throw new RuntimeHttpException('ENTITY_GLOBAL_TABLE_CONFIRMATION_REQUIRED', 'Marque explicitamente quando a tabela persistente for global e compartilhada entre assinantes.', 422);
            }
            return [
                'mode' => 'none',
                'columnName' => null,
                'globalTable' => true,
            ];
        }
        if ($columnName === '') {
            throw new RuntimeHttpException('ENTITY_SUBSCRIBER_COLUMN_REQUIRED', 'Informe a coluna do assinante para isolamento por tabela.', 422);
        }
        if (!$this->fieldColumnExistsInRawConfig($rawFields, $columnName)) {
            throw new RuntimeHttpException('ENTITY_SUBSCRIBER_COLUMN_NOT_FOUND', 'A coluna de assinante precisa existir na lista de campos da entidade.', 422, [
                'columnName' => $columnName,
            ]);
        }

        return [
            'mode' => 'subscriber_column',
            'columnName' => $columnName,
            'globalTable' => false,
        ];
    }

    private function normalizeSoftDeleteConfig(array $payload, string $entityType, array $rawFields): array
    {
        if ($entityType !== 'persistence') {
            return [
                'enabled' => false,
                'deletedAtField' => null,
                'deletedByField' => null,
                'reasonField' => null,
            ];
        }

        $enabled = ($payload['softDeleteEnabled'] ?? false) === true
            || (is_array($payload['softDelete'] ?? null) && (($payload['softDelete']['enabled'] ?? false) === true));
        if (!$enabled) {
            return [
                'enabled' => false,
                'deletedAtField' => null,
                'deletedByField' => null,
                'reasonField' => null,
            ];
        }

        $softDelete = is_array($payload['softDelete'] ?? null) ? $payload['softDelete'] : [];
        $deletedAtField = $this->safeSqlIdentifier((string) ($payload['softDeleteDeletedAtField'] ?? $softDelete['deletedAtField'] ?? 'deleted_at'));
        $deletedByField = $this->safeSqlIdentifier((string) ($payload['softDeleteDeletedByField'] ?? $softDelete['deletedByField'] ?? ''));
        $reasonField = $this->safeSqlIdentifier((string) ($payload['softDeleteReasonField'] ?? $softDelete['reasonField'] ?? ''));
        if ($deletedAtField === '') {
            throw new RuntimeHttpException('ENTITY_SOFT_DELETE_FIELD_REQUIRED', 'Informe o campo de data/hora da exclusao logica.', 422);
        }
        $fieldCodes = [];
        foreach ($rawFields as $field) {
            if (is_array($field)) {
                $fieldCode = $this->safeSqlIdentifier((string) ($field['code'] ?? ''));
                if ($fieldCode !== '') {
                    $fieldCodes[$fieldCode] = true;
                }
            }
        }
        foreach ([$deletedAtField, $deletedByField, $reasonField] as $fieldCode) {
            if ($fieldCode !== '' && !isset($fieldCodes[$fieldCode])) {
                throw new RuntimeHttpException('ENTITY_SOFT_DELETE_FIELD_NOT_FOUND', 'Campo de soft delete nao encontrado na entidade.', 422, [
                    'fieldCode' => $fieldCode,
                ]);
            }
        }

        return [
            'enabled' => true,
            'deletedAtField' => $deletedAtField,
            'deletedByField' => $deletedByField !== '' ? $deletedByField : null,
            'reasonField' => $reasonField !== '' ? $reasonField : null,
        ];
    }

    private function normalizeBuilderPayload(array $payload, ?BuilderEntity $overrideEntity = null): array
    {
        $programCode = $this->safeCode((string) ($payload['programCode'] ?? ''));
        $programTitle = trim((string) ($payload['programTitle'] ?? ''));
        $moduleCode = $this->safeCode((string) ($payload['module'] ?? ''));
        $pageType = trim((string) ($payload['pageType'] ?? 'crud'));
        $builderEntityCode = $this->safeCode((string) ($payload['builderEntityCode'] ?? ''));
        $screenId = trim((string) ($payload['screenId'] ?? ''));
        $version = trim((string) ($payload['version'] ?? '1.0.0'));
        $customMode = strtolower(trim((string) ($payload['customMode'] ?? 'iframe')));
        $customEntryUrl = trim((string) ($payload['customEntryUrl'] ?? ''));
        $customFrameTitle = trim((string) ($payload['customFrameTitle'] ?? ''));
        $programOrigin = $this->normalizeProgramOrigin((string) ($payload['programOrigin'] ?? 'standard'));
        $ownerScope = $this->normalizeOwnerScope((string) ($payload['ownerScope'] ?? ($programOrigin === 'standard' ? 'system' : 'subscriber')));
        $customizationPolicy = $this->normalizeCustomizationPolicy((string) ($payload['customizationPolicy'] ?? 'overlay_only'));
        $subscriberId = $this->normalizeOptionalCode((string) ($payload['subscriberId'] ?? ''));
        $baseProgramCode = $this->normalizeOptionalCode((string) ($payload['baseProgramCode'] ?? ''));
        $baseProgramVersionId = $this->normalizePositiveInt($payload['baseProgramVersionId'] ?? null);
        $upgradeFrozen = ($payload['upgradeFrozen'] ?? false) === true;
        $frozenReason = trim((string) ($payload['frozenReason'] ?? '')) ?: null;
        $publicationPolicy = $this->normalizePublicationPolicy($payload['publicationPolicy'] ?? null);
        $reportSourceType = strtolower(trim((string) ((is_array($payload['reportConfig'] ?? null) ? $payload['reportConfig'] : [])['sourceType'] ?? 'operational')));

        if ($programCode === '' || $programTitle === '' || $moduleCode === '' || $screenId === '') {
            throw new RuntimeHttpException('PROGRAM_BUILDER_REQUIRED_FIELDS', 'Informe codigo, titulo, modulo e screenId.', 422);
        }
        if (!in_array($pageType, ['crud', 'custom', 'analytics', 'report', 'special_document', 'regulated_document'], true)) {
            throw new RuntimeHttpException('PROGRAM_BUILDER_PAGE_TYPE_NOT_SUPPORTED', 'Nesta etapa o construtor visual suporta programas CRUD, analytics, report, special_document, regulated_document e custom.', 422, [
                'pageType' => $pageType,
            ]);
        }
        if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            throw new RuntimeHttpException('PROGRAM_VERSION_INVALID', 'Use versao no formato semantico x.y.z.', 422, [
                'version' => $version,
            ]);
        }
        if ($programOrigin === 'standard' && $ownerScope !== 'system') {
            throw new RuntimeHttpException('PROGRAM_STANDARD_SCOPE_INVALID', 'Programa padrao precisa pertencer ao escopo do sistema.', 422, [
                'programCode' => $programCode,
                'ownerScope' => $ownerScope,
            ]);
        }
        if ($programOrigin !== 'standard' && $ownerScope !== 'subscriber') {
            throw new RuntimeHttpException('PROGRAM_CUSTOM_SCOPE_INVALID', 'Programas customizados precisam pertencer a um assinante.', 422, [
                'programCode' => $programCode,
                'ownerScope' => $ownerScope,
            ]);
        }
        if ($ownerScope === 'subscriber' && $subscriberId === '') {
            throw new RuntimeHttpException('PROGRAM_SUBSCRIBER_REQUIRED', 'Informe o assinante para programas de escopo do cliente.', 422, [
                'programCode' => $programCode,
            ]);
        }
        if ($programOrigin === 'standard' && $subscriberId !== '') {
            throw new RuntimeHttpException('PROGRAM_STANDARD_SUBSCRIBER_NOT_ALLOWED', 'Programa padrao nao pode ser vinculado a assinante especifico.', 422, [
                'programCode' => $programCode,
                'subscriberId' => $subscriberId,
            ]);
        }
        if ($programOrigin === 'standard' && ($baseProgramCode !== '' || $baseProgramVersionId !== null)) {
            throw new RuntimeHttpException('PROGRAM_STANDARD_BASE_NOT_ALLOWED', 'Programa padrao nao pode apontar para uma base de outro programa.', 422, [
                'programCode' => $programCode,
            ]);
        }
        if ($programOrigin !== 'standard' && $baseProgramCode === '') {
            throw new RuntimeHttpException('PROGRAM_CUSTOM_BASE_REQUIRED', 'Informe o programa base para customizacao overlay ou especifica.', 422, [
                'programCode' => $programCode,
                'programOrigin' => $programOrigin,
            ]);
        }

        $module = $this->modules->findOneBy(['code' => $moduleCode]);
        if (!$module) {
            throw new RuntimeHttpException('PROGRAM_MODULE_NOT_FOUND', 'Modulo do programa nao encontrado.', 422, [
                'module' => $moduleCode,
            ]);
        }
        $expectedPrefix = strtolower($module->getAbbreviation());
        if (!preg_match('/^' . preg_quote($expectedPrefix, '/') . '(\d{4})$/i', strtolower($programCode), $matches)) {
            throw new RuntimeHttpException('PROGRAM_CODE_PATTERN_INVALID', 'O codigo do programa deve usar a abreviacao do modulo seguida de 4 digitos.', 422, [
                'programCode' => $programCode,
                'expectedPrefix' => $expectedPrefix,
                'example' => $expectedPrefix . str_pad((string) $module->getNumberStart(), 4, '0', STR_PAD_LEFT),
            ]);
        }
        $sequenceNumber = (int) $matches[1];
        if ($sequenceNumber < $module->getNumberStart() || $sequenceNumber > $module->getNumberEnd()) {
            throw new RuntimeHttpException('PROGRAM_CODE_OUT_OF_MODULE_RANGE', 'O sequencial do programa esta fora da faixa do modulo.', 422, [
                'programCode' => $programCode,
                'module' => $moduleCode,
                'range' => [$module->getNumberStart(), $module->getNumberEnd()],
            ]);
        }

        $entity = $overrideEntity;
        if (
            in_array($pageType, ['crud', 'analytics'], true)
            || ($pageType === 'report' && $reportSourceType !== 'analytic')
            || ($pageType === 'special_document' && $reportSourceType !== 'analytic')
            || ($pageType === 'regulated_document' && $reportSourceType !== 'analytic')
        ) {
            if ($builderEntityCode === '') {
                throw new RuntimeHttpException('PROGRAM_BUILDER_ENTITY_REQUIRED', 'Informe a entidade base para o programa.', 422);
            }
            if ($entity === null) {
                $entity = $this->entities->findOneBy(['code' => $builderEntityCode]);
            }
            if (!$entity) {
                throw new RuntimeHttpException('PROGRAM_BUILDER_ENTITY_NOT_FOUND', 'Entidade do construtor nao encontrada.', 422, [
                    'builderEntityCode' => $builderEntityCode,
                ]);
            }
            if ($pageType === 'analytics' && $entity->getEntityType() !== 'persistence') {
                throw new RuntimeHttpException('PROGRAM_BUILDER_ENTITY_TYPE_NOT_SUPPORTED', 'Analytics v1 aceita apenas entidades persistence.', 422, [
                    'builderEntityCode' => $builderEntityCode,
                    'entityType' => $entity->getEntityType(),
                ]);
            }
            if (in_array($pageType, ['report', 'special_document', 'regulated_document'], true) && $entity->getEntityType() !== 'persistence') {
                throw new RuntimeHttpException('PROGRAM_BUILDER_ENTITY_TYPE_NOT_SUPPORTED', 'Reports, documentos especiais e documentos regulados v1 aceitam apenas entidades persistence na fonte operacional.', 422, [
                    'builderEntityCode' => $builderEntityCode,
                    'entityType' => $entity->getEntityType(),
                ]);
            }
            if ($pageType === 'crud' && !in_array($entity->getEntityType(), ['persistence', 'api'], true)) {
                throw new RuntimeHttpException('PROGRAM_BUILDER_ENTITY_TYPE_NOT_SUPPORTED', 'Nesta etapa o gerador de programa suporta apenas entidades persistence e api.', 422, [
                    'builderEntityCode' => $builderEntityCode,
                    'entityType' => $entity->getEntityType(),
                ]);
            }
        }

        if ($pageType === 'custom') {
            if (!in_array($customMode, ['iframe', 'htmlUrl'], true)) {
                throw new RuntimeHttpException('PROGRAM_BUILDER_CUSTOM_MODE_INVALID', 'Modo custom invalido.', 422, [
                    'customMode' => $customMode,
                ]);
            }
            if ($customEntryUrl === '') {
                throw new RuntimeHttpException('PROGRAM_BUILDER_CUSTOM_ENTRY_REQUIRED', 'Informe a URL/entrypoint manual do programa custom.', 422);
            }
            if (preg_match('/^https?:\/\//i', $customEntryUrl)) {
                throw new RuntimeHttpException('PROGRAM_BUILDER_CUSTOM_ENTRY_UNSAFE', 'Programas custom publicados pelo builder aceitam apenas entrypoints relativos do proprio sistema.', 422, [
                    'customEntryUrl' => $customEntryUrl,
                ]);
            }
        }

        $existingProgram = $this->programs->findOneBy(['code' => $programCode]);
        if ($existingProgram) {
            if (
                $existingProgram->getProgramOrigin() !== $programOrigin
                || $existingProgram->getOwnerScope() !== $ownerScope
            ) {
                throw new RuntimeHttpException('PROGRAM_ORIGIN_TRANSITION_NOT_ALLOWED', 'Nao e permitido converter a origem ou o escopo do programa por edicao direta.', 422, [
                    'programCode' => $programCode,
                    'currentProgramOrigin' => $existingProgram->getProgramOrigin(),
                    'currentOwnerScope' => $existingProgram->getOwnerScope(),
                    'requestedProgramOrigin' => $programOrigin,
                    'requestedOwnerScope' => $ownerScope,
                ]);
            }
        }

        return [
            'programCode' => $programCode,
            'programTitle' => $programTitle,
            'module' => $moduleCode,
            'pageType' => $pageType,
            'builderEntityCode' => $builderEntityCode,
            'screenId' => $screenId,
            'version' => $version,
            'subtitle' => trim((string) ($payload['subtitle'] ?? '')) ?: null,
            'icon' => trim((string) ($payload['icon'] ?? '')) ?: null,
            'permissionPrefix' => $this->safePermissionPrefix((string) ($payload['permissionPrefix'] ?? $programCode)),
            'allowCreate' => $pageType === 'crud' ? ($this->apiEntitySupportsOperation($entity, 'create') ? (bool) ($payload['allowCreate'] ?? true) : ($entity && $entity->getEntityType() === 'api' ? false : (bool) ($payload['allowCreate'] ?? true))) : false,
            'allowUpdate' => $pageType === 'crud' ? ($this->apiEntitySupportsOperation($entity, 'update') ? (bool) ($payload['allowUpdate'] ?? true) : ($entity && $entity->getEntityType() === 'api' ? false : (bool) ($payload['allowUpdate'] ?? true))) : false,
            'allowDelete' => $pageType === 'crud' ? ($this->apiEntitySupportsOperation($entity, 'delete') ? (bool) ($payload['allowDelete'] ?? true) : ($entity && $entity->getEntityType() === 'api' ? false : (bool) ($payload['allowDelete'] ?? true))) : false,
            'changeSummary' => trim((string) ($payload['changeSummary'] ?? '')) ?: null,
            'programOrigin' => $programOrigin,
            'ownerScope' => $ownerScope,
            'customizationPolicy' => $customizationPolicy,
            'subscriberId' => $subscriberId !== '' ? $subscriberId : null,
            'baseProgramCode' => $baseProgramCode !== '' ? $baseProgramCode : null,
            'baseProgramVersionId' => $baseProgramVersionId,
            'upgradeFrozen' => $upgradeFrozen,
            'frozenReason' => $frozenReason,
            'publicationPolicy' => $publicationPolicy,
            'analyticsConfig' => $pageType === 'analytics' && $entity instanceof BuilderEntity ? $this->normalizeAnalyticsBuilderConfig($payload['analyticsConfig'] ?? null, $entity) : null,
            'reportConfig' => $pageType === 'report' ? $this->normalizeReportBuilderConfig($payload['reportConfig'] ?? null, $entity instanceof BuilderEntity ? $entity : null) : null,
            'specialDocumentConfig' => $pageType === 'special_document' ? $this->normalizeSpecialDocumentBuilderConfig($payload['specialDocumentConfig'] ?? null, $entity instanceof BuilderEntity ? $entity : null) : null,
            'regulatedDocumentConfig' => $pageType === 'regulated_document' ? $this->normalizeRegulatedDocumentBuilderConfig($payload['regulatedDocumentConfig'] ?? null, $entity instanceof BuilderEntity ? $entity : null) : null,
            'customMode' => $pageType === 'custom' ? $customMode : null,
            'customEntryUrl' => $pageType === 'custom' ? $customEntryUrl : null,
            'customFrameTitle' => $pageType === 'custom' ? ($customFrameTitle !== '' ? $customFrameTitle : $programTitle) : null,
            '_module' => $module,
            '_entity' => $entity,
        ];
    }

    private function normalizeReportBuilderConfig(mixed $value, ?BuilderEntity $entity): array
    {
        $config = is_array($value) ? $value : [];
        $sourceType = strtolower(trim((string) ($config['sourceType'] ?? 'operational')));
        if (!in_array($sourceType, ['operational', 'analytic'], true)) {
            $sourceType = 'operational';
        }

        $documentKind = strtolower(trim((string) ($config['documentKind'] ?? 'management')));
        $groupField = $this->safeCode((string) ($config['groupField'] ?? ''));
        $totalField = $this->safeCode((string) ($config['totalField'] ?? ''));
        $limit = max(1, min(5000, $this->normalizePositiveInt($config['limit'] ?? null) ?? 200));
        $analyticsScreenId = trim((string) ($config['analyticsScreenId'] ?? ''));
        $analyticsDatasetId = $this->safeCode((string) ($config['analyticsDatasetId'] ?? ''));
        $headerText = trim((string) ($config['headerText'] ?? ''));
        $footerText = trim((string) ($config['footerText'] ?? ''));
        $sortField = $this->safeCode((string) ($config['sortField'] ?? ''));
        $sortDir = strtolower(trim((string) ($config['sortDir'] ?? 'asc'))) === 'desc' ? 'desc' : 'asc';
        $outputs = is_array($config['outputs'] ?? null) ? $config['outputs'] : [];
        $groups = [];

        if ($entity instanceof BuilderEntity) {
            $availableFields = [];
            foreach ($entity->getFields() as $field) {
                $options = $field->getOptions();
                if (($options['virtual'] ?? false) === true) {
                    continue;
                }
                $dataType = $this->normalizeFieldType($field->getDataType());
                if ($dataType === 'json') {
                    continue;
                }
                $availableFields[$field->getCode()] = $dataType;
            }
            foreach ((array) ($config['groups'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $itemField = $this->safeCode((string) ($item['field'] ?? ''));
                if ($itemField === '' || !isset($availableFields[$itemField])) {
                    continue;
                }
                $groups[] = [
                    'field' => $itemField,
                    'label' => trim((string) ($item['label'] ?? '')),
                    'showSubtotal' => ($item['showSubtotal'] ?? true) !== false,
                    'pageBreakBefore' => ($item['pageBreakBefore'] ?? false) === true,
                ];
                if (count($groups) >= 3) {
                    break;
                }
            }
            if (!$groups && $groupField !== '' && !isset($availableFields[$groupField])) {
                $groupField = '';
            }
            if (!$groups && $groupField !== '' && isset($availableFields[$groupField])) {
                $groups[] = [
                    'field' => $groupField,
                    'label' => '',
                    'showSubtotal' => true,
                    'pageBreakBefore' => false,
                ];
            }
            if ($totalField !== '' && (!isset($availableFields[$totalField]) || !in_array($availableFields[$totalField], ['integer', 'decimal', 'number', 'currency'], true))) {
                $totalField = '';
            }
            if ($sortField !== '' && !isset($availableFields[$sortField])) {
                $sortField = '';
            }
        } elseif ($groupField !== '') {
            $groups[] = [
                'field' => $groupField,
                'label' => '',
                'showSubtotal' => true,
                'pageBreakBefore' => false,
            ];
        }

        return [
            'sourceType' => $sourceType,
            'analyticsScreenId' => $analyticsScreenId,
            'analyticsDatasetId' => $analyticsDatasetId,
            'documentKind' => $documentKind,
            'groupField' => $groups[0]['field'] ?? $groupField,
            'groups' => $groups,
            'totalField' => $totalField,
            'sortField' => $sortField,
            'sortDir' => $sortDir,
            'limit' => $limit,
            'headerText' => $headerText,
            'footerText' => $footerText,
            'outputs' => [
                'html' => ($outputs['html'] ?? true) !== false,
                'print' => ($outputs['print'] ?? true) !== false,
                'pdf' => ($outputs['pdf'] ?? ($outputs['pdfBrowser'] ?? true)) !== false,
                'pdfBrowser' => ($outputs['pdfBrowser'] ?? true) !== false,
                'excel' => ($outputs['excel'] ?? true) !== false,
                'csv' => ($outputs['csv'] ?? true) !== false,
            ],
        ];
    }

    private function normalizeSpecialDocumentBuilderConfig(mixed $value, ?BuilderEntity $entity): array
    {
        $config = is_array($value) ? $value : [];
        $sourceType = strtolower(trim((string) ($config['sourceType'] ?? 'operational')));
        if (!in_array($sourceType, ['operational', 'analytic'], true)) {
            $sourceType = 'operational';
        }

        return [
            'sourceType' => $sourceType,
            'documentKind' => strtolower(trim((string) ($config['documentKind'] ?? 'fiscal_document'))) ?: 'fiscal_document',
            'renderEngine' => 'native',
            'analyticsScreenId' => trim((string) ($config['analyticsScreenId'] ?? '')),
            'analyticsDatasetId' => $this->safeCode((string) ($config['analyticsDatasetId'] ?? '')),
            'title' => trim((string) ($config['title'] ?? '')),
            'subtitle' => trim((string) ($config['subtitle'] ?? '')),
            'notes' => trim((string) ($config['notes'] ?? '')),
            'outputs' => [
                'html' => ($config['outputs']['html'] ?? true) !== false,
                'pdf' => ($config['outputs']['pdf'] ?? true) !== false,
            ],
        ];
    }

    private function normalizeRegulatedDocumentBuilderConfig(mixed $value, ?BuilderEntity $entity): array
    {
        $config = is_array($value) ? $value : [];
        $sourceType = strtolower(trim((string) ($config['sourceType'] ?? 'operational')));
        if (!in_array($sourceType, ['operational', 'analytic'], true)) {
            $sourceType = 'operational';
        }
        $track = strtolower(trim((string) ($config['track'] ?? 'fiscal')));
        if (!in_array($track, ['fiscal', 'banking', 'logistics'], true)) {
            $track = 'fiscal';
        }

        return [
            'track' => $track,
            'documentType' => strtolower(trim((string) ($config['documentType'] ?? 'regulated_document'))) ?: 'regulated_document',
            'complianceProfile' => 'near_homologated',
            'sourceType' => $sourceType,
            'analyticsScreenId' => trim((string) ($config['analyticsScreenId'] ?? '')),
            'analyticsDatasetId' => $this->safeCode((string) ($config['analyticsDatasetId'] ?? '')),
            'title' => trim((string) ($config['title'] ?? '')),
            'subtitle' => trim((string) ($config['subtitle'] ?? '')),
            'notes' => trim((string) ($config['notes'] ?? '')),
            'artifactPolicy' => [
                'storeCanonicalPayload' => ($config['artifactPolicy']['storeCanonicalPayload'] ?? true) !== false,
                'storeArtifact' => ($config['artifactPolicy']['storeArtifact'] ?? true) !== false,
                'defaultFormat' => strtolower(trim((string) ($config['artifactPolicy']['defaultFormat'] ?? 'pdf'))) === 'html' ? 'html' : 'pdf',
            ],
            'verification' => [
                'enabled' => ($config['verification']['enabled'] ?? true) !== false,
                'algorithm' => 'sha256',
                'publicPath' => trim((string) ($config['verification']['publicPath'] ?? '')) ?: 'regulated-document-authenticity.html',
                'label' => trim((string) ($config['verification']['label'] ?? '')) ?: 'Codigo de conferencia',
            ],
            'retention' => [
                'keepPayload' => ($config['retention']['keepPayload'] ?? true) !== false,
                'keepArtifact' => ($config['retention']['keepArtifact'] ?? true) !== false,
                'storeDays' => max(1, min(3650, (int) ($config['retention']['storeDays'] ?? 365))),
            ],
            'outputs' => [
                'html' => ($config['outputs']['html'] ?? true) !== false,
                'pdf' => ($config['outputs']['pdf'] ?? true) !== false,
            ],
        ];
    }

    private function normalizeAnalyticsBuilderConfig(mixed $value, BuilderEntity $entity): array
    {
        $config = is_array($value) ? $value : [];
        $executionMode = strtolower(trim((string) ($config['executionMode'] ?? 'live')));
        if (!in_array($executionMode, ['live', 'cached', 'auto'], true)) {
            $executionMode = 'live';
        }
        $limit = $this->normalizePositiveInt($config['limit'] ?? null) ?? 1000;
        $limit = max(1, min(5000, $limit));
        $cacheTtlSeconds = $this->normalizePositiveInt($config['cacheTtlSeconds'] ?? null) ?? 900;

        $fieldMap = [];
        $baseFieldMetaMap = [];
        $measureMap = [];
        foreach ($entity->getFields() as $field) {
            $code = $field->getCode();
            $options = $field->getOptions();
            if (($options['virtual'] ?? false) === true) {
                continue;
            }
            $dataType = $this->normalizeFieldType($field->getDataType());
            if ($dataType === 'json') {
                continue;
            }
            $fieldMap[$code] = true;
            $baseFieldMetaMap[$code] = [
                'id' => $code,
                'field' => $code,
                'label' => $field->getLabel(),
                'type' => $dataType,
                'format' => $options['analytics']['format'] ?? null,
            ];
            if (in_array($dataType, ['integer', 'decimal', 'number', 'currency'], true)) {
                $measureMap[$code . '_sum'] = true;
            }
            $measureMap[$code . '_count'] = true;
        }
        $contextFieldMetaMap = [
            'base' => $baseFieldMetaMap,
        ];

        $defaultSortField = $this->safeCode((string) ($config['defaultSortField'] ?? ''));
        if ($defaultSortField !== '' && !isset($fieldMap[$defaultSortField])) {
            $defaultSortField = '';
        }
        $defaultSortDir = strtolower(trim((string) ($config['defaultSortDir'] ?? 'asc')));
        if (!in_array($defaultSortDir, ['asc', 'desc'], true)) {
            $defaultSortDir = 'asc';
        }

        $chartSeriesType = strtolower(trim((string) ($config['chartSeriesType'] ?? 'column')));
        if (!in_array($chartSeriesType, ['column', 'bar', 'line', 'area'], true)) {
            $chartSeriesType = 'column';
        }
        $chartCategoryField = $this->safeCode((string) ($config['chartCategoryField'] ?? ''));
        $chartValueField = $this->safeCode((string) ($config['chartValueField'] ?? ''));
        $audit = is_array($config['audit'] ?? null) ? $config['audit'] : [];
        $datasetAudit = is_array($config['datasetAudit'] ?? null) ? $config['datasetAudit'] : [];
        $normalizedAudit = [
            'enabled' => ($audit['enabled'] ?? true) !== false,
            'includeCacheHits' => ($audit['includeCacheHits'] ?? true) !== false,
        ];
        $normalizedDatasetAudit = [
            'enabled' => ($datasetAudit['enabled'] ?? true) !== false,
            'includeCacheHits' => ($datasetAudit['includeCacheHits'] ?? true) !== false,
        ];

        $views = is_array($config['views'] ?? null) ? $config['views'] : [];
        $normalizedViews = [
            'grid' => ($views['grid'] ?? true) !== false,
            'chart' => ($views['chart'] ?? true) !== false,
            'pivot' => ($views['pivot'] ?? true) !== false,
            'kpi' => ($views['kpi'] ?? false) === true,
            'dashboard' => ($views['dashboard'] ?? true) !== false,
        ];

        $joins = [];
        foreach ((array) ($config['joins'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = $this->safeCode((string) ($item['id'] ?? ''));
            $source = $this->safeCode((string) ($item['source'] ?? 'base')) ?: 'base';
            $localField = $this->safeCode((string) ($item['localField'] ?? ''));
            $entityCode = $this->safeCode((string) ($item['entityCode'] ?? ''));
            $foreignField = $this->safeCode((string) ($item['foreignField'] ?? ''));
            $type = strtolower(trim((string) ($item['type'] ?? 'left')));
            if ($id === '' || $localField === '' || $entityCode === '' || $foreignField === '') {
                continue;
            }
            $sourceFieldMap = $contextFieldMetaMap[$source] ?? null;
            if ($sourceFieldMap === null || !isset($sourceFieldMap[$localField])) {
                continue;
            }
            $targetEntity = $this->entities->findOneBy(['code' => $entityCode]);
            if (!$targetEntity || $targetEntity->getEntityType() !== 'persistence') {
                continue;
            }
            $targetFieldExists = false;
            $targetFieldMetaMap = [];
            foreach ($targetEntity->getFields() as $targetField) {
                $targetOptions = $targetField->getOptions();
                if (($targetOptions['virtual'] ?? false) === true) {
                    continue;
                }
                $targetDataType = $this->normalizeFieldType($targetField->getDataType());
                if ($targetDataType === 'json') {
                    continue;
                }
                $targetFieldMetaMap[$targetField->getCode()] = [
                    'id' => $id . '_' . $targetField->getCode(),
                    'field' => $id . '.' . $targetField->getCode(),
                    'label' => $targetField->getLabel(),
                    'type' => $targetDataType,
                    'format' => $targetOptions['analytics']['format'] ?? null,
                ];
                if ($targetField->getCode() === $foreignField) {
                    $targetFieldExists = true;
                }
            }
            if (!$targetFieldExists) {
                continue;
            }
            $joins[] = [
                'id' => $id,
                'source' => $source,
                'localField' => $localField,
                'entityCode' => $entityCode,
                'foreignField' => $foreignField,
                'type' => $type === 'inner' ? 'inner' : 'left',
            ];
            $contextFieldMetaMap[$id] = $targetFieldMetaMap;
        }

        $pipelines = [];
        foreach ((array) ($config['semanticPipelines'] ?? []) as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = $this->safeCode((string) ($item['id'] ?? ('pipeline' . ($index + 1))));
            if ($id === '') {
                continue;
            }
            $title = trim((string) ($item['title'] ?? $id)) ?: $id;
            $steps = [];
            foreach ((array) ($item['steps'] ?? []) as $stepIndex => $step) {
                if (!is_array($step)) {
                    continue;
                }
                $stepId = $this->safeCode((string) ($step['id'] ?? ('step' . ($stepIndex + 1))));
                $stepType = strtolower(trim((string) ($step['type'] ?? '')));
                if ($stepId === '' || !in_array($stepType, ['source', 'select', 'filter', 'having', 'join', 'derive', 'group', 'sort', 'limit', 'union', 'union_all', 'intersect', 'except', 'publish'], true)) {
                    continue;
                }
                $normalizedStep = [
                    'id' => $stepId,
                    'type' => $stepType,
                ];
                foreach (['sourceEntityCode', 'sourceDatasetId', 'sourcePipelineId', 'publishedDatasetId', 'title', 'field', 'dir', 'operation', 'targetField', 'sourceField', 'separator', 'bucket', 'joinType', 'localField', 'foreignField', 'datasetId', 'entityCode', 'defaultLabel'] as $key) {
                    if (array_key_exists($key, $step) && $step[$key] !== null && $step[$key] !== '') {
                        $normalizedStep[$key] = is_string($step[$key]) ? trim((string) $step[$key]) : $step[$key];
                    }
                }
                foreach (['fields', 'filters', 'join', 'joins', 'derive', 'group', 'sort', 'ranges', 'cases'] as $key) {
                    if (array_key_exists($key, $step) && is_array($step[$key])) {
                        $normalizedStep[$key] = $step[$key];
                    }
                }
                foreach (['take', 'skip', 'limit', 'start', 'length'] as $key) {
                    if (array_key_exists($key, $step) && $step[$key] !== null && $step[$key] !== '') {
                        $normalizedStep[$key] = max(0, (int) $step[$key]);
                    }
                }
                $steps[] = $normalizedStep;
            }
            $publishConfig = is_array($item['publishConfig'] ?? null) ? $item['publishConfig'] : [];
            $publishedDatasetId = trim((string) ($publishConfig['publishedDatasetId'] ?? ($item['publishedDatasetId'] ?? ($id . '_published'))));
            if ($publishedDatasetId === '') {
                $publishedDatasetId = $id . '_published';
            }
            $pipelines[] = [
                'id' => $id,
                'title' => $title,
                'enabled' => ($item['enabled'] ?? true) !== false,
                'sourceEntityCode' => trim((string) ($item['sourceEntityCode'] ?? $entity->getCode())),
                'sourceDatasetId' => trim((string) ($item['sourceDatasetId'] ?? '')),
                'sourcePipelineId' => trim((string) ($item['sourcePipelineId'] ?? '')),
                'steps' => $steps,
                'publishConfig' => [
                    'publishedDatasetId' => $publishedDatasetId,
                    'title' => trim((string) ($publishConfig['title'] ?? $title)) ?: $title,
                    'visibility' => strtolower(trim((string) ($publishConfig['visibility'] ?? 'internal'))) === 'shared' ? 'shared' : 'internal',
                    'compatibilityMode' => strtolower(trim((string) ($publishConfig['compatibilityMode'] ?? 'warn'))) === 'block' ? 'block' : 'warn',
                ],
                'schedule' => is_array($item['schedule'] ?? null) ? $item['schedule'] : [],
                'retention' => is_array($item['retention'] ?? null) ? $item['retention'] : [],
            ];
        }
        $datasetBlueprint = $this->normalizeAnalyticsDatasetBlueprint($config['datasetBlueprint'] ?? null, $contextFieldMetaMap);
        if ($datasetBlueprint !== null) {
            $allowedFieldIds = [];
            $allowedMeasureIds = [];
            foreach ((array) ($datasetBlueprint['fields'] ?? []) as $spec) {
                if (!empty($spec['id'])) {
                    $allowedFieldIds[(string) $spec['id']] = true;
                }
            }
            foreach ((array) ($datasetBlueprint['dimensions'] ?? []) as $spec) {
                if (!empty($spec['id'])) {
                    $allowedFieldIds[(string) $spec['id']] = true;
                }
            }
            foreach ((array) ($datasetBlueprint['measures'] ?? []) as $spec) {
                if (!empty($spec['id'])) {
                    $allowedFieldIds[(string) $spec['id']] = true;
                    $allowedMeasureIds[(string) $spec['id']] = true;
                }
            }
            if ($defaultSortField !== '' && !isset($allowedFieldIds[$defaultSortField])) {
                $defaultSortField = '';
            }
            if ($chartCategoryField !== '' && !isset($allowedFieldIds[$chartCategoryField])) {
                $chartCategoryField = '';
            }
            if ($chartValueField !== '' && !isset($allowedMeasureIds[$chartValueField])) {
                $chartValueField = '';
            }
        } else {
            if ($chartCategoryField !== '' && !isset($fieldMap[$chartCategoryField])) {
                $chartCategoryField = '';
            }
            if ($chartValueField !== '' && !isset($measureMap[$chartValueField])) {
                $chartValueField = '';
            }
        }

        return [
            'executionMode' => $executionMode,
            'limit' => $limit,
            'cacheTtlSeconds' => max(0, $cacheTtlSeconds),
            'defaultSortField' => $defaultSortField,
            'defaultSortDir' => $defaultSortDir,
            'chartSeriesType' => $chartSeriesType,
            'chartCategoryField' => $chartCategoryField,
            'chartValueField' => $chartValueField,
            'audit' => $normalizedAudit,
            'datasetAudit' => $normalizedDatasetAudit,
            'views' => $normalizedViews,
            'datasetBlueprint' => $datasetBlueprint,
            'wizardMeta' => is_array($config['wizardMeta'] ?? null) ? $config['wizardMeta'] : null,
            'joins' => $joins,
            'semanticPipelines' => $pipelines,
            'ingestionPipelines' => [],
        ];
    }

    /**
     * @param array<string, array<string, array<string, mixed>>> $contextFieldMetaMap
     * @return array<string, mixed>|null
     */
    private function normalizeAnalyticsDatasetBlueprint(mixed $value, array $contextFieldMetaMap): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $normalizeFieldSpec = function (mixed $item, bool $measure = false) use ($contextFieldMetaMap): ?array {
            if (!is_array($item)) {
                return null;
            }
            $id = $this->safeCode((string) ($item['id'] ?? ''));
            $source = $this->safeCode((string) ($item['source'] ?? 'base')) ?: 'base';
            $fieldRef = trim((string) ($item['field'] ?? ''));
            if ($fieldRef === '' && !$measure) {
                return null;
            }
            $fieldCode = $fieldRef;
            $fieldSource = $source;
            if ($fieldRef !== '' && str_contains($fieldRef, '.')) {
                [$fieldSource, $fieldCode] = explode('.', $fieldRef, 2);
                $fieldSource = $this->safeCode($fieldSource) ?: $source;
            }
            $fieldCode = $this->safeCode($fieldCode);
            if ($fieldCode !== '' && !isset($contextFieldMetaMap[$fieldSource][$fieldCode])) {
                return null;
            }
            $meta = $fieldCode !== '' ? ($contextFieldMetaMap[$fieldSource][$fieldCode] ?? null) : null;
            $spec = [
                'id' => $id !== '' ? $id : ($meta['id'] ?? ''),
                'field' => $fieldCode !== '' ? ($fieldSource === 'base' ? $fieldCode : ($fieldSource . '.' . $fieldCode)) : '',
                'source' => $fieldSource,
                'label' => trim((string) ($item['label'] ?? ($meta['label'] ?? ''))),
                'type' => trim((string) ($item['type'] ?? ($meta['type'] ?? 'string'))),
            ];
            $format = trim((string) ($item['format'] ?? ($meta['format'] ?? '')));
            if ($format !== '') {
                $spec['format'] = $format;
            }
            if ($measure) {
                $aggregate = strtolower(trim((string) ($item['aggregate'] ?? 'count')));
                if (!in_array($aggregate, ['count', 'sum', 'avg', 'min', 'max', 'distinct_count'], true)) {
                    $aggregate = 'count';
                }
                $spec['aggregate'] = $aggregate;
            }
            if ($spec['id'] === '' || ($spec['field'] === '' && !$measure)) {
                return null;
            }
            return $spec;
        };

        $fields = [];
        foreach ((array) ($value['fields'] ?? []) as $item) {
            $normalized = $normalizeFieldSpec($item, false);
            if ($normalized !== null) {
                $fields[] = $normalized;
            }
        }
        $dimensions = [];
        foreach ((array) ($value['dimensions'] ?? []) as $item) {
            $normalized = $normalizeFieldSpec($item, false);
            if ($normalized !== null) {
                $dimensions[] = $normalized;
            }
        }
        $measures = [];
        foreach ((array) ($value['measures'] ?? []) as $item) {
            $normalized = $normalizeFieldSpec($item, true);
            if ($normalized !== null) {
                $measures[] = $normalized;
            }
        }
        $parameters = [];
        foreach ((array) ($value['parameters'] ?? []) as $item) {
            $normalized = $normalizeFieldSpec($item, false);
            if ($normalized === null) {
                continue;
            }
            $operator = strtolower(trim((string) ($item['operator'] ?? 'contains')));
            if (!in_array($operator, ['eq', 'contains', 'startswith', 'in', 'between', 'gte', 'lte'], true)) {
                $operator = 'contains';
            }
            $normalized['operator'] = $operator;
            $parameterType = strtolower(trim((string) ($item['type'] ?? $normalized['type'] ?? 'text')));
            if (!in_array($parameterType, ['text', 'string', 'number', 'integer', 'decimal', 'date', 'datetime', 'boolean', 'enum'], true)) {
                $parameterType = 'text';
            }
            $normalized['type'] = $parameterType;
            $parameters[] = $normalized;
        }
        $defaultSort = [];
        foreach ((array) ($value['defaultSort'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $field = $this->safeCode((string) ($item['field'] ?? ''));
            if ($field === '') {
                continue;
            }
            $defaultSort[] = [
                'field' => $field,
                'dir' => strtolower(trim((string) ($item['dir'] ?? 'asc'))) === 'desc' ? 'desc' : 'asc',
            ];
        }

        return [
            'title' => trim((string) ($value['title'] ?? '')),
            'fields' => $fields,
            'dimensions' => $dimensions,
            'measures' => $measures,
            'parameters' => $parameters,
            'defaultSort' => $defaultSort,
            'chartCategoryField' => $this->safeCode((string) ($value['chartCategoryField'] ?? '')),
            'chartValueField' => $this->safeCode((string) ($value['chartValueField'] ?? '')),
        ];
    }

    private function generateProgramDefinition(array $config): array
    {
        $definition = match ($config['pageType']) {
            'custom' => $this->generateCustomDefinition($config),
            'analytics' => $this->generateAnalyticsDefinition($config),
            'report' => $this->generateReportDefinition($config),
            'special_document' => $this->generateSpecialDocumentDefinition($config),
            'regulated_document' => $this->generateRegulatedDocumentDefinition($config),
            default => $this->generateCrudDefinition($config),
        };

        $definition['program'] = is_array($definition['program'] ?? null) ? $definition['program'] : [];
        $definition['program']['programOrigin'] = $config['programOrigin'];
        $definition['program']['ownerScope'] = $config['ownerScope'];
        $definition['program']['customizationPolicy'] = $config['customizationPolicy'];
        $definition['program']['subscriberId'] = $config['subscriberId'];
        $definition['program']['baseProgramCode'] = $config['baseProgramCode'];
        $definition['program']['baseProgramVersionId'] = $config['baseProgramVersionId'];
        $definition['program']['upgradeFrozen'] = $config['upgradeFrozen'];
        $definition['program']['frozenReason'] = $config['frozenReason'];
        $definition['program']['publicationPolicy'] = $config['publicationPolicy'];

        return $definition;
    }

    private function decoratePublishedDefinition(BuilderProgramVersion $version, array $definition): array
    {
        $definition['program'] = is_array($definition['program'] ?? null) ? $definition['program'] : [];
        $definition['program']['programOrigin'] = $version->getProgramOrigin();
        $definition['program']['ownerScope'] = $version->getOwnerScope();
        $definition['program']['customizationPolicy'] = $version->getCustomizationPolicy();
        $definition['program']['subscriberId'] = $version->getSubscriberId();
        $definition['program']['baseProgramCode'] = $version->getBaseProgramCode();
        $definition['program']['baseProgramVersionId'] = $version->getBaseProgramVersionId();
        $definition['program']['upgradeFrozen'] = $version->isUpgradeFrozen();
        $definition['program']['frozenReason'] = $version->getFrozenReason();

        $definition['runtime'] = is_array($definition['runtime'] ?? null) ? $definition['runtime'] : [];
        $traceability = is_array($definition['runtime']['traceability'] ?? null) ? $definition['runtime']['traceability'] : [];
        $traceability['programCode'] = $version->getProgramCode();
        $traceability['programVersion'] = $version->getVersion();
        $traceability['builderProgramVersionId'] = $version->getId();
        $traceability['builderEntityVersionId'] = $this->latestBuilderEntityVersionId($version->getBuilderEntityCode());
        $traceability['screenDefinitionVersion'] = $version->getVersion();
        $traceability['customizationKind'] = $version->getProgramOrigin();
        $traceability['schemaFingerprint'] = $this->programSchemaFingerprint($version, $definition);
        $definition['runtime']['traceability'] = $traceability;

        return $definition;
    }

    private function latestBuilderEntityVersionId(string $builderEntityCode): ?int
    {
        if ($builderEntityCode === '') {
            return null;
        }
        $versions = $this->entityVersions->findByEntityCodeOrdered($builderEntityCode);
        return $versions[0]?->getId();
    }

    private function programSchemaFingerprint(BuilderProgramVersion $version, array $definition): string
    {
        $payload = [
            'programCode' => $version->getProgramCode(),
            'programVersion' => $version->getVersion(),
            'builderEntityCode' => $version->getBuilderEntityCode(),
            'pageType' => $version->getPageType(),
            'permissions' => $definition['permissions'] ?? [],
            'dataModel' => $definition['dataModel'] ?? [],
            'analytics' => $definition['analytics'] ?? [],
            'report' => $definition['report'] ?? [],
            'specialDocument' => $definition['specialDocument'] ?? [],
            'regulatedDocument' => $definition['regulatedDocument'] ?? [],
        ];

        return hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function generateCrudDefinition(array $config): array
    {
        $entity = $config['_entity'];
        $apiEntity = $entity instanceof BuilderEntity && $entity->getEntityType() === 'api';
        $fields = [];
        $filterFields = [];
        $gridColumns = [];
        $formFields = [];
        $primaryKey = 'id';
        $position = 0;

        foreach ($entity->getFields() as $field) {
            $code = $field->getCode();
            $options = $field->getOptions();
            $dataType = $this->normalizeFieldType($field->getDataType());
            $apiField = is_array($options['api'] ?? null) ? $options['api'] : [];
            $showInGrid = !$apiEntity || ($apiField['showInGrid'] ?? true) !== false;
            $showInForm = !$apiEntity || ($apiField['showInForm'] ?? true) !== false;
            $showInFilter = $apiEntity ? (($apiField['showInFilter'] ?? false) === true) : (($options['virtual'] ?? false) !== true);
            $fields[$code] = [
                'label' => $field->getLabel(),
                'type' => $dataType,
                'nullable' => !$field->isRequired(),
                'technicalProperties' => $this->programFieldTechnicalProperties($entity, $field, $dataType, $options),
            ];
            if (($options['readonly'] ?? false) === true || ($options['virtual'] ?? false) === true) {
                $fields[$code]['readonly'] = true;
                $fields[$code]['editable'] = false;
            }
            if (($options['editor'] ?? '') === 'textarea' || $dataType === 'json') {
                $fields[$code]['editor'] = 'textarea';
            }
            if ($field->getDataType() === 'custom_code') {
                $fields[$code]['type'] = 'string';
                $fields[$code]['editor'] = 'customCode';
                $fields[$code]['customCode'] = $options['customCode'] ?? ['mode' => 'pattern'];
            }
            if (isset($options['options']) && is_array($options['options'])) {
                $fields[$code]['options'] = $options['options'];
            }
            if ($field->isPrimaryKey()) {
                $primaryKey = $code;
                $fields[$code]['readonly'] = true;
                $fields[$code]['editable'] = false;
            }

            if ($showInForm) {
                $formItem = ['field' => $code];
                if (($fields[$code]['readonly'] ?? false) === true) {
                    $formItem['readonly'] = true;
                }
                $formFields[] = $formItem;
            }
            if ($showInGrid && $position < 6 && !in_array($dataType, ['json'], true)) {
                $gridColumns[] = [
                    'field' => $code,
                    'title' => $field->getLabel(),
                    'width' => in_array($dataType, ['datetime', 'text'], true) ? 220 : 150,
                    'technicalProperties' => $this->programGridTechnicalProperties($entity, $field, $dataType, $options),
                ];
            }
            if ($showInFilter && $position < 5 && !in_array($dataType, ['json', 'text'], true)) {
                $filterFields[] = [
                    'id' => $code,
                    'field' => $code,
                    'label' => $field->getLabel(),
                    'type' => in_array($dataType, ['boolean', 'enum', 'integer', 'date', 'datetime'], true) ? $dataType : 'text',
                    'operator' => in_array($dataType, ['boolean', 'enum', 'integer'], true) ? 'eq' : 'contains',
                    'technicalProperties' => $this->programFilterTechnicalProperties($entity, $field, $dataType, $options),
                ];
            }
            ++$position;
        }

        if (isset($fields[$primaryKey])) {
            $fields[$primaryKey]['readonly'] = true;
            $fields[$primaryKey]['editable'] = false;
        }
        foreach ($formFields as &$formField) {
            if (($formField['field'] ?? '') === $primaryKey) {
                $formField['readonly'] = true;
            }
        }
        unset($formField);

        $permissionPrefix = $config['permissionPrefix'];

        $groups = array_values(array_filter((array) ($reportConfig['groups'] ?? []), 'is_array'));
        $hasGroups = $groups || $groupField !== '';

        return [
            'schemaVersion' => '1.0',
            'pageType' => 'crud',
            'screenId' => $config['screenId'],
            'program' => [
                'id' => $config['programCode'],
                'module' => $config['module'] ?? 'cadastros',
                'entity' => $config['builderEntityCode'],
                'title' => $config['programTitle'],
                'version' => $config['version'],
                'subtitle' => $config['subtitle'],
                'icon' => $config['icon'],
                'permission' => $permissionPrefix !== '' ? $permissionPrefix . '.read' : null,
            ],
            'permissions' => [
                'read' => $permissionPrefix !== '' ? $permissionPrefix . '.read' : true,
                'create' => $config['allowCreate'] ? ($permissionPrefix !== '' ? $permissionPrefix . '.create' : true) : false,
                'edit' => $config['allowUpdate'] ? ($permissionPrefix !== '' ? $permissionPrefix . '.edit' : true) : false,
                'delete' => $config['allowDelete'] ? ($permissionPrefix !== '' ? $permissionPrefix . '.delete' : true) : false,
                'saveLayout' => 'user.preferences',
            ],
            'dataSource' => [
                'api' => $this->crudApiMap($config),
            ],
            'runtime' => [
                'entityCode' => $config['builderEntityCode'],
                'programId' => $config['programCode'],
                'mode' => $apiEntity ? (($config['allowCreate'] || $config['allowUpdate'] || $config['allowDelete']) ? 'api-crud' : 'readonly-api') : 'crud',
                'lock' => [
                    'enabled' => !$apiEntity && ($config['allowUpdate'] || $config['allowDelete']),
                    'modes' => array_values(array_filter([
                        $config['allowUpdate'] ? 'edit' : null,
                        $config['allowDelete'] ? 'delete' : null,
                    ])),
                ],
                'messages' => [
                    'enabled' => !$apiEntity,
                    'pollIntervalSeconds' => 30,
                    'events' => ['enabled' => !$apiEntity],
                ],
            ],
            'dataModel' => [
                'primaryKey' => $primaryKey,
                'fields' => $fields,
            ],
            'crud' => [
                'query' => [
                    'pageSize' => 20,
                    'defaultSort' => [['field' => $primaryKey, 'dir' => 'asc']],
                ],
                'filter' => [
                    'type' => 'window',
                    'mode' => 'basic',
                    'title' => 'Filtros',
                    'openOnLoad' => false,
                    'showAppliedFilters' => true,
                    'fields' => $filterFields,
                ],
                'grid' => [
                    'pageable' => true,
                    'sortable' => true,
                    'filterable' => !$apiEntity,
                    'resizable' => true,
                    'reorderable' => true,
                    'columnMenu' => true,
                    'toolbar' => $this->toolbar($config),
                    'columns' => $gridColumns,
                    'rowActions' => $this->rowActions($config),
                    'bulkActions' => ['enabled' => false, 'actions' => []],
                    'print' => ['enabled' => false, 'options' => []],
                ],
                'form' => [
                    'id' => str_replace('.', '-', $config['screenId']) . '-form',
                    'mode' => 'popup',
                    'layout' => 'tabs',
                    'maximizeForm' => true,
                    'title' => [
                        'create' => 'Incluir ' . mb_strtolower($config['programTitle']),
                        'view' => 'Detalhe de ' . mb_strtolower($config['programTitle']),
                        'edit' => 'Alterar ' . mb_strtolower($config['programTitle']),
                        'delete' => 'Excluir ' . mb_strtolower($config['programTitle']),
                    ],
                    'behavior' => [
                        'closeOnSave' => true,
                        'closeOnCancel' => true,
                    ],
                    'tabs' => [
                        [
                            'id' => 'geral',
                            'title' => 'Geral',
                            'sections' => [
                                [
                                    'id' => 'principal',
                                    'title' => 'Principal',
                                    'columns' => 2,
                                    'fields' => $formFields,
                                ],
                            ],
                        ],
                    ],
                    'fields' => $formFields,
                    'logs' => ['enabled' => false],
                    'print' => ['enabled' => false, 'options' => []],
                    'otherActions' => ['enabled' => false, 'actions' => []],
                ],
                'userLayout' => [
                    'enabled' => true,
                    'storageKey' => str_replace('.', '-', $config['screenId']) . '-layout',
                ],
            ],
        ];
    }

    private function generateCustomDefinition(array $config): array
    {
        $permissionPrefix = $config['permissionPrefix'];

        return [
            'schemaVersion' => '1.0',
            'pageType' => 'custom',
            'screenId' => $config['screenId'],
            'program' => [
                'id' => $config['programCode'],
                'module' => $config['module'],
                'title' => $config['programTitle'],
                'version' => $config['version'],
                'subtitle' => $config['subtitle'],
                'icon' => $config['icon'],
                'permission' => $permissionPrefix !== '' ? $permissionPrefix . '.read' : null,
            ],
            'permissions' => [
                'read' => $permissionPrefix !== '' ? $permissionPrefix . '.read' : true,
            ],
            'custom' => [
                'mode' => $config['customMode'] ?? 'iframe',
                'entryUrl' => $config['customEntryUrl'] ?? '',
                'frameTitle' => $config['customFrameTitle'] ?? $config['programTitle'],
            ],
            'runtime' => [
                'programId' => $config['programCode'],
                'messages' => [
                    'enabled' => false,
                ],
            ],
        ];
    }

    private function generateAnalyticsDefinition(array $config): array
    {
        $entity = $config['_entity'];
        $datasetId = 'principal';
        $analyticsConfig = is_array($config['analyticsConfig'] ?? null) ? $config['analyticsConfig'] : [];
        $datasetBlueprint = is_array($analyticsConfig['datasetBlueprint'] ?? null) ? $analyticsConfig['datasetBlueprint'] : null;
        $fields = [];
        $dimensions = [];
        $measures = [];
        $parameters = [];
        $position = 0;
        if ($datasetBlueprint !== null && (($datasetBlueprint['fields'] ?? []) !== [] || ($datasetBlueprint['dimensions'] ?? []) !== [] || ($datasetBlueprint['measures'] ?? []) !== [])) {
            $fields = array_values(array_filter((array) ($datasetBlueprint['fields'] ?? []), 'is_array'));
            $dimensions = array_values(array_filter((array) ($datasetBlueprint['dimensions'] ?? []), 'is_array'));
            $measures = array_values(array_filter((array) ($datasetBlueprint['measures'] ?? []), 'is_array'));
            $parameters = array_values(array_filter((array) ($datasetBlueprint['parameters'] ?? []), 'is_array'));
        } else {
            foreach ($entity->getFields() as $field) {
                $code = $field->getCode();
                $options = $field->getOptions();
                $analytics = is_array($options['analytics'] ?? null) ? $options['analytics'] : [];
                if (($analytics['hidden'] ?? false) === true || ($options['virtual'] ?? false) === true) {
                    continue;
                }
                $dataType = $this->normalizeFieldType($field->getDataType());
                if ($dataType === 'json') {
                    continue;
                }
                $item = [
                    'id' => $code,
                    'field' => $code,
                    'label' => $field->getLabel(),
                    'type' => $dataType,
                    'format' => $analytics['format'] ?? null,
                    'technicalProperties' => $this->programFieldTechnicalProperties($entity, $field, $dataType, $options),
                ];
                $fields[] = array_filter($item, static fn ($value): bool => $value !== null);

                $isNumeric = in_array($dataType, ['integer', 'decimal', 'number', 'currency'], true);
                if (($analytics['dimension'] ?? null) === true || (!$isNumeric && count($dimensions) < 6)) {
                    $dimensions[] = [
                        'id' => $code,
                        'field' => $code,
                        'label' => $field->getLabel(),
                        'type' => $dataType,
                        'format' => $analytics['format'] ?? null,
                    ];
                }
                if (($analytics['measure'] ?? null) === true || $isNumeric) {
                    $aggregate = strtolower((string) ($analytics['defaultAggregate'] ?? ($isNumeric ? 'sum' : 'count')));
                    if (!in_array($aggregate, ['count', 'sum', 'avg', 'min', 'max', 'distinct_count'], true)) {
                        $aggregate = $isNumeric ? 'sum' : 'count';
                    }
                    $measures[] = [
                        'id' => $code . '_' . $aggregate,
                        'field' => $code,
                        'label' => $aggregate === 'count' ? 'Qtde. ' . $field->getLabel() : 'Total de ' . $field->getLabel(),
                        'aggregate' => $aggregate,
                        'format' => $analytics['format'] ?? ($dataType === 'currency' ? 'c2' : null),
                    ];
                }
                if ($position < 5 && !in_array($dataType, ['text'], true)) {
                    $parameters[] = [
                        'id' => $code,
                        'field' => $code,
                        'label' => $field->getLabel(),
                        'type' => in_array($dataType, ['boolean', 'enum', 'integer', 'date', 'datetime'], true) ? $dataType : 'text',
                        'operator' => in_array($dataType, ['boolean', 'enum', 'integer'], true) ? 'eq' : 'contains',
                    ];
                }
                ++$position;
            }
        }

        if (!$dimensions && $fields) {
            $first = $fields[0];
            $dimensions[] = [
                'id' => (string) $first['id'],
                'field' => (string) $first['field'],
                'label' => (string) $first['label'],
                'type' => (string) $first['type'],
            ];
        }
        if (!$measures) {
            $measures[] = [
                'id' => 'total_registros',
                'label' => 'Registros',
                'aggregate' => 'count',
            ];
        }

        $defaultSort = [];
        $configuredSortField = $this->safeCode((string) ($analyticsConfig['defaultSortField'] ?? ''));
        $blueprintSort = $datasetBlueprint !== null && is_array($datasetBlueprint['defaultSort'] ?? null) ? $datasetBlueprint['defaultSort'] : [];
        if ($configuredSortField !== '') {
            $defaultSort[] = [
                'field' => $configuredSortField,
                'dir' => strtolower((string) ($analyticsConfig['defaultSortDir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc',
            ];
        } elseif ($blueprintSort !== []) {
            $defaultSort = array_values(array_filter($blueprintSort, 'is_array'));
        } elseif ($dimensions) {
            $defaultSort[] = ['field' => $dimensions[0]['id'], 'dir' => 'asc'];
        }

        $chartCategoryField = $this->safeCode((string) ($analyticsConfig['chartCategoryField'] ?? ''));
        if ($chartCategoryField === '' && $datasetBlueprint !== null) {
            $chartCategoryField = $this->safeCode((string) ($datasetBlueprint['chartCategoryField'] ?? ''));
        }
        if ($chartCategoryField === '' && $dimensions) {
            $chartCategoryField = (string) ($dimensions[0]['id'] ?? '');
        }
        $chartValueField = $this->safeCode((string) ($analyticsConfig['chartValueField'] ?? ''));
        if ($chartValueField === '' && $datasetBlueprint !== null) {
            $chartValueField = $this->safeCode((string) ($datasetBlueprint['chartValueField'] ?? ''));
        }
        if ($chartValueField === '' && $measures) {
            $chartValueField = (string) ($measures[0]['id'] ?? '');
        }
        $viewFlags = is_array($analyticsConfig['views'] ?? null) ? $analyticsConfig['views'] : [];

        $permissionPrefix = $config['permissionPrefix'];
        $readPermission = $permissionPrefix !== '' ? $permissionPrefix . '.read' : true;
        $builderEntityCode = (string) ($config['builderEntityCode'] ?? ($entity instanceof BuilderEntity ? $entity->getCode() : ''));
        $programCode = (string) ($config['programCode'] ?? '');
        $programTitle = (string) ($config['programTitle'] ?? '');
        $programVersion = (string) ($config['version'] ?? '1.0.0');
        $screenId = (string) ($config['screenId'] ?? '');

        $views = [];
        if (($viewFlags['grid'] ?? true) !== false) {
            $views[] = [
                'id' => 'grid',
                'type' => 'grid',
                'title' => 'Detalhamento',
                'datasetId' => $datasetId,
            ];
        }
        if (($viewFlags['chart'] ?? true) !== false) {
            $views[] = [
                'id' => 'chart',
                'type' => 'chart',
                'title' => 'Grafico',
                'datasetId' => $datasetId,
                'categoryField' => $chartCategoryField !== '' ? $chartCategoryField : null,
                'valueField' => $chartValueField !== '' ? $chartValueField : null,
                'seriesType' => (string) ($analyticsConfig['chartSeriesType'] ?? 'column'),
            ];
        }
        if (($viewFlags['pivot'] ?? true) !== false) {
            $views[] = [
                'id' => 'pivot',
                'type' => 'pivot',
                'title' => 'Pivot',
                'datasetId' => $datasetId,
            ];
        }
        if (($viewFlags['kpi'] ?? false) === true) {
            $views[] = [
                'id' => 'kpi',
                'type' => 'kpi',
                'title' => 'Indicador',
                'datasetId' => $datasetId,
                'valueField' => $chartValueField !== '' ? $chartValueField : ($measures[0]['id'] ?? null),
                'format' => $measures[0]['format'] ?? null,
            ];
        }
        if (($viewFlags['dashboard'] ?? true) !== false) {
            $dashboardTiles = [];
            $dashboardTiles[] = ['id' => 'kpi-total', 'type' => 'kpi', 'title' => $measures[0]['label'] ?? 'Indicador', 'valueField' => $chartValueField !== '' ? $chartValueField : ($measures[0]['id'] ?? null), 'format' => $measures[0]['format'] ?? null];
            if (($viewFlags['chart'] ?? true) !== false) {
                $dashboardTiles[] = ['id' => 'chart-main', 'type' => 'chart', 'title' => 'Distribuicao', 'categoryField' => $chartCategoryField !== '' ? $chartCategoryField : null, 'valueField' => $chartValueField !== '' ? $chartValueField : ($measures[0]['id'] ?? null), 'seriesType' => (string) ($analyticsConfig['chartSeriesType'] ?? 'column')];
            }
            $views[] = [
                'id' => 'dashboard',
                'type' => 'dashboard',
                'title' => 'Dashboard',
                'datasetId' => $datasetId,
                'tiles' => $dashboardTiles,
            ];
        }

        $semanticPipelines = array_values(array_filter((array) ($analyticsConfig['semanticPipelines'] ?? []), 'is_array'));
        $hasPipelines = $semanticPipelines !== [];
        $publishedDatasetId = $hasPipelines
            ? trim((string) (($semanticPipelines[0]['publishConfig']['publishedDatasetId'] ?? null) ?: (($semanticPipelines[0]['id'] ?? 'principal') . '_published')))
            : '';
        $datasetSource = $hasPipelines
            ? [
                'type' => 'pipeline_published',
                'pipelineId' => (string) ($semanticPipelines[0]['id'] ?? 'pipeline1'),
                'publishedDatasetId' => $publishedDatasetId,
            ]
            : [
                'type' => 'entity',
                'entityCode' => $builderEntityCode,
            ];

        return [
            'schemaVersion' => '1.0',
            'pageType' => 'analytics',
            'screenId' => $screenId,
            'program' => [
                'id' => $programCode,
                'module' => $config['module'] ?? 'analytics',
                'entity' => $builderEntityCode,
                'title' => $programTitle,
                'version' => $programVersion,
                'subtitle' => $config['subtitle'] ?? 'Consulta avancada e BI',
                'icon' => $config['icon'] ?? 'chart-line',
                'permission' => $readPermission,
            ],
            'permissions' => [
                'read' => $readPermission,
                'materialize' => $readPermission,
            ],
            'dataSource' => [
                'api' => $this->analyticsApiMap(),
            ],
            'runtime' => [
                'entityCode' => $builderEntityCode,
                'programId' => $programCode,
                'mode' => 'analytics',
                'messages' => [
                    'enabled' => false,
                ],
            ],
            'analytics' => [
                'version' => '1.0',
                'endpoints' => $this->analyticsApiMap(),
                'audit' => [
                    'enabled' => ($analyticsConfig['audit']['enabled'] ?? true) !== false,
                    'includeCacheHits' => ($analyticsConfig['audit']['includeCacheHits'] ?? true) !== false,
                ],
                'semanticPipelines' => $semanticPipelines,
                'ingestionPipelines' => [],
                'datasets' => [
                    [
                        'id' => $datasetId,
                        'title' => $programTitle,
                        'source' => $datasetSource,
                        'joins' => array_values(array_filter((array) ($analyticsConfig['joins'] ?? []), 'is_array')),
                        'fields' => $fields,
                        'dimensions' => $dimensions,
                        'measures' => $measures,
                        'parameters' => $parameters,
                        'defaultSort' => $defaultSort,
                        'limit' => max(1, (int) ($analyticsConfig['limit'] ?? 1000)),
                        'executionMode' => (string) ($analyticsConfig['executionMode'] ?? 'live'),
                        'audit' => [
                            'enabled' => ($analyticsConfig['datasetAudit']['enabled'] ?? true) !== false,
                            'includeCacheHits' => ($analyticsConfig['datasetAudit']['includeCacheHits'] ?? true) !== false,
                        ],
                        'cache' => [
                            'ttlSeconds' => max(0, (int) ($analyticsConfig['cacheTtlSeconds'] ?? 900)),
                        ],
                    ],
                ],
                'views' => $views,
            ],
        ];
    }

    private function generateReportDefinition(array $config): array
    {
        $reportConfig = is_array($config['reportConfig'] ?? null) ? $config['reportConfig'] : [];
        $permissionPrefix = $config['permissionPrefix'];
        $readPermission = $permissionPrefix !== '' ? $permissionPrefix . '.read' : true;
        $sourceType = strtolower((string) ($reportConfig['sourceType'] ?? 'operational'));
        $queryFields = [];
        $parameters = [];
        $sort = [];
        $groupField = $reportConfig['groupField'] ?? '';
        $totalField = $reportConfig['totalField'] ?? '';

        if ($sourceType !== 'analytic') {
            $entity = $config['_entity'];
            $position = 0;
            foreach ($entity->getFields() as $field) {
                $options = $field->getOptions();
                if (($options['virtual'] ?? false) === true) {
                    continue;
                }
                $dataType = $this->normalizeFieldType($field->getDataType());
                if ($dataType === 'json') {
                    continue;
                }
                $queryFields[] = array_filter([
                    'field' => $field->getCode(),
                    'label' => $field->getLabel(),
                    'type' => $dataType,
                    'format' => $dataType === 'currency' ? 'c2' : null,
                    'align' => in_array($dataType, ['integer', 'decimal', 'number', 'currency'], true) ? 'right' : 'left',
                    'groupable' => $groupField === $field->getCode(),
                    'totalable' => $totalField === $field->getCode(),
                ], static fn (mixed $value): bool => $value !== null && $value !== false);

                if ($position < 5 && !in_array($dataType, ['text', 'json'], true)) {
                    $parameters[] = [
                        'id' => $field->getCode(),
                        'field' => $field->getCode(),
                        'label' => $field->getLabel(),
                        'type' => in_array($dataType, ['boolean', 'enum', 'integer', 'date', 'datetime'], true) ? $dataType : 'text',
                        'operator' => in_array($dataType, ['boolean', 'enum', 'integer'], true) ? 'eq' : 'contains',
                    ];
                }
                ++$position;
            }
        }

        $sortField = $this->safeCode((string) ($reportConfig['sortField'] ?? ''));
        if ($sortField !== '') {
            $sort[] = [
                'field' => $sortField,
                'dir' => strtolower((string) ($reportConfig['sortDir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc',
            ];
        } elseif ($queryFields) {
            $sort[] = ['field' => $queryFields[0]['field'], 'dir' => 'asc'];
        }

        return [
            'schemaVersion' => '1.0',
            'pageType' => 'report',
            'screenId' => $config['screenId'],
            'program' => [
                'id' => $config['programCode'],
                'module' => $config['module'] ?? 'relatorios',
                'entity' => $config['builderEntityCode'] ?: null,
                'title' => $config['programTitle'],
                'version' => $config['version'],
                'subtitle' => $config['subtitle'] ?? 'Relatorio nativo',
                'icon' => $config['icon'] ?? 'file',
                'permission' => $readPermission,
            ],
            'permissions' => [
                'read' => $readPermission,
                'export' => $readPermission,
            ],
            'dataSource' => [
                'api' => $this->reportApiMap(),
            ],
            'runtime' => [
                'entityCode' => $config['builderEntityCode'],
                'programId' => $config['programCode'],
                'mode' => 'report',
                'messages' => [
                    'enabled' => false,
                ],
            ],
            'report' => [
                'version' => '1.0',
                'classification' => [
                    'documentProfile' => in_array(($reportConfig['documentKind'] ?? ''), ['danfe', 'dacte', 'boleto', 'label', 'etiqueta'], true) ? 'special' : 'general',
                    'documentKind' => (string) ($reportConfig['documentKind'] ?? 'management'),
                ],
                'endpoints' => $this->reportApiMap(),
                'source' => $sourceType === 'analytic'
                    ? [
                        'type' => 'analytic',
                        'analyticsScreenId' => (string) ($reportConfig['analyticsScreenId'] ?? ''),
                        'analyticsDatasetId' => (string) ($reportConfig['analyticsDatasetId'] ?? ''),
                    ]
                    : [
                        'type' => 'operational',
                        'entityCode' => $config['builderEntityCode'],
                    ],
                'query' => [
                    'fields' => $queryFields,
                    'parameters' => $parameters,
                    'filters' => [],
                    'sort' => $sort,
                    'limit' => max(1, (int) ($reportConfig['limit'] ?? 200)),
                ],
                'layout' => [
                    'title' => $config['programTitle'],
                    'subtitle' => $config['subtitle'] ?? 'Relatorio nativo',
                    'groupField' => $groupField !== '' ? $groupField : null,
                    'groups' => $groups,
                    'footerText' => $reportConfig['footerText'] ?? '',
                    'blocks' => array_values(array_filter([
                        ['id' => 'header', 'type' => 'header', 'title' => 'Cabecalho'],
                        ['id' => 'summary', 'type' => 'summary', 'title' => 'Resumo'],
                        ['id' => 'table', 'type' => 'table', 'title' => 'Detalhamento'],
                        $hasGroups ? ['id' => 'group', 'type' => 'group', 'title' => 'Agrupamento'] : null,
                        ['id' => 'totals', 'type' => 'totals', 'title' => 'Totais'],
                        ['id' => 'footer', 'type' => 'footer', 'title' => 'Rodape'],
                    ])),
                ],
                'authenticity' => [
                    'enabled' => ($reportConfig['authenticity']['enabled'] ?? false) === true,
                    'algorithm' => 'sha256',
                    'footerLabel' => trim((string) ($reportConfig['authenticity']['footerLabel'] ?? '')) ?: 'Codigo de autenticidade',
                    'includeInFooter' => true,
                    'verificationPath' => trim((string) ($reportConfig['authenticity']['verificationPath'] ?? '')) ?: 'report-authenticity.html',
                    'storage' => [
                        'storeCanonicalPayload' => ($reportConfig['authenticity']['storage']['storeCanonicalPayload'] ?? true) !== false,
                        'storeExportArtifact' => ($reportConfig['authenticity']['storage']['storeExportArtifact'] ?? false) === true,
                    ],
                ],
                'outputs' => [
                    'html' => ($reportConfig['outputs']['html'] ?? true) !== false,
                    'print' => ($reportConfig['outputs']['print'] ?? true) !== false,
                    'pdf' => ($reportConfig['outputs']['pdf'] ?? ($reportConfig['outputs']['pdfBrowser'] ?? true)) !== false,
                    'pdfBrowser' => ($reportConfig['outputs']['pdfBrowser'] ?? true) !== false,
                    'excel' => ($reportConfig['outputs']['excel'] ?? true) !== false,
                    'csv' => ($reportConfig['outputs']['csv'] ?? true) !== false,
                ],
            ],
        ];
    }

    private function generateSpecialDocumentDefinition(array $config): array
    {
        $special = is_array($config['specialDocumentConfig'] ?? null) ? $config['specialDocumentConfig'] : [];
        $permissionPrefix = $config['permissionPrefix'];
        $readPermission = $permissionPrefix !== '' ? $permissionPrefix . '.read' : true;
        $sourceType = strtolower((string) ($special['sourceType'] ?? 'operational')) === 'analytic' ? 'analytic' : 'operational';

        return [
            'schemaVersion' => '1.0',
            'pageType' => 'special_document',
            'screenId' => $config['screenId'],
            'program' => [
                'id' => $config['programCode'],
                'module' => $config['module'] ?? 'documentos',
                'entity' => $config['builderEntityCode'] ?: null,
                'title' => $config['programTitle'],
                'version' => $config['version'],
                'subtitle' => $config['subtitle'] ?? 'Documento especial',
                'icon' => $config['icon'] ?? 'file',
                'permission' => $readPermission,
            ],
            'permissions' => [
                'read' => $readPermission,
                'export' => $readPermission,
            ],
            'dataSource' => [
                'api' => $this->specialDocumentApiMap(),
            ],
            'runtime' => [
                'entityCode' => $config['builderEntityCode'],
                'programId' => $config['programCode'],
                'mode' => 'special_document',
                'messages' => [
                    'enabled' => false,
                ],
            ],
            'specialDocument' => [
                'version' => '1.0',
                'classification' => [
                    'documentProfile' => 'special',
                    'documentKind' => (string) ($special['documentKind'] ?? 'fiscal_document'),
                ],
                'renderEngine' => 'native',
                'endpoints' => $this->specialDocumentApiMap(),
                'source' => $sourceType === 'analytic'
                    ? [
                        'type' => 'analytic',
                        'analyticsScreenId' => (string) ($special['analyticsScreenId'] ?? ''),
                        'analyticsDatasetId' => (string) ($special['analyticsDatasetId'] ?? ''),
                    ]
                    : [
                        'type' => 'operational',
                        'entityCode' => $config['builderEntityCode'],
                    ],
                'parameters' => [],
                'layout' => [
                    'title' => $special['title'] ?: $config['programTitle'],
                    'subtitle' => $special['subtitle'] ?: ($config['subtitle'] ?? 'Documento especial'),
                    'notes' => $special['notes'] ?? '',
                ],
                'outputs' => [
                    'html' => ($special['outputs']['html'] ?? true) !== false,
                    'pdf' => ($special['outputs']['pdf'] ?? true) !== false,
                ],
            ],
        ];
    }

    private function generateRegulatedDocumentDefinition(array $config): array
    {
        $regulated = is_array($config['regulatedDocumentConfig'] ?? null) ? $config['regulatedDocumentConfig'] : [];
        $permissionPrefix = $config['permissionPrefix'];
        $readPermission = $permissionPrefix !== '' ? $permissionPrefix . '.read' : true;
        $sourceType = strtolower((string) ($regulated['sourceType'] ?? 'operational')) === 'analytic' ? 'analytic' : 'operational';
        $parameters = [];

        if ($sourceType !== 'analytic' && $config['_entity'] instanceof BuilderEntity) {
            $position = 0;
            foreach ($config['_entity']->getFields() as $field) {
                $options = $field->getOptions();
                if (($options['virtual'] ?? false) === true) {
                    continue;
                }
                $dataType = $this->normalizeFieldType($field->getDataType());
                if (in_array($dataType, ['text', 'json'], true)) {
                    continue;
                }
                if ($position < 5) {
                    $parameters[] = [
                        'id' => $field->getCode(),
                        'field' => $field->getCode(),
                        'label' => $field->getLabel(),
                        'type' => in_array($dataType, ['boolean', 'enum', 'integer', 'date', 'datetime'], true) ? $dataType : 'text',
                        'operator' => in_array($dataType, ['boolean', 'enum', 'integer'], true) ? 'eq' : 'contains',
                        'required' => false,
                    ];
                }
                ++$position;
            }
        }

        return [
            'schemaVersion' => '1.0',
            'pageType' => 'regulated_document',
            'screenId' => $config['screenId'],
            'program' => [
                'id' => $config['programCode'],
                'module' => $config['module'] ?? 'documentos',
                'entity' => $config['builderEntityCode'] ?: null,
                'title' => $config['programTitle'],
                'version' => $config['version'],
                'subtitle' => $config['subtitle'] ?? 'Documento regulado',
                'icon' => $config['icon'] ?? 'file',
                'permission' => $readPermission,
            ],
            'permissions' => [
                'read' => $readPermission,
                'issue' => $readPermission,
                'verify' => $readPermission,
                'artifact' => $readPermission,
            ],
            'dataSource' => [
                'api' => $this->regulatedDocumentApiMap(),
            ],
            'runtime' => [
                'entityCode' => $config['builderEntityCode'],
                'programId' => $config['programCode'],
                'mode' => 'regulated_document',
                'messages' => [
                    'enabled' => false,
                ],
            ],
            'regulatedDocument' => [
                'version' => '1.0',
                'track' => (string) ($regulated['track'] ?? 'fiscal'),
                'documentType' => (string) ($regulated['documentType'] ?? 'regulated_document'),
                'complianceProfile' => 'near_homologated',
                'renderEngine' => 'internal',
                'endpoints' => $this->regulatedDocumentApiMap(),
                'source' => $sourceType === 'analytic'
                    ? [
                        'type' => 'analytic',
                        'analyticsScreenId' => (string) ($regulated['analyticsScreenId'] ?? ''),
                        'analyticsDatasetId' => (string) ($regulated['analyticsDatasetId'] ?? ''),
                    ]
                    : [
                        'type' => 'operational',
                        'entityCode' => $config['builderEntityCode'],
                    ],
                'parameters' => $parameters,
                'outputs' => [
                    'html' => ($regulated['outputs']['html'] ?? true) !== false,
                    'pdf' => ($regulated['outputs']['pdf'] ?? true) !== false,
                ],
                'artifactPolicy' => [
                    'storeCanonicalPayload' => ($regulated['artifactPolicy']['storeCanonicalPayload'] ?? true) !== false,
                    'storeArtifact' => ($regulated['artifactPolicy']['storeArtifact'] ?? true) !== false,
                    'defaultFormat' => (string) ($regulated['artifactPolicy']['defaultFormat'] ?? 'pdf'),
                ],
                'verification' => [
                    'enabled' => ($regulated['verification']['enabled'] ?? true) !== false,
                    'algorithm' => 'sha256',
                    'publicPath' => trim((string) ($regulated['verification']['publicPath'] ?? '')) ?: 'regulated-document-authenticity.html',
                    'label' => trim((string) ($regulated['verification']['label'] ?? '')) ?: 'Codigo de conferencia',
                ],
                'retention' => [
                    'keepPayload' => ($regulated['retention']['keepPayload'] ?? true) !== false,
                    'keepArtifact' => ($regulated['retention']['keepArtifact'] ?? true) !== false,
                    'storeDays' => max(1, (int) ($regulated['retention']['storeDays'] ?? 365)),
                ],
                'layout' => [
                    'title' => $regulated['title'] ?: $config['programTitle'],
                    'subtitle' => $regulated['subtitle'] ?: ($config['subtitle'] ?? 'Documento regulado'),
                    'notes' => $regulated['notes'] ?? '',
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function programFieldTechnicalProperties(BuilderEntity $entity, BuilderField $field, string $dataType, array $options): array
    {
        $properties = [
            ['section' => 'Modelo', 'label' => 'Campo', 'value' => $field->getCode()],
            ['section' => 'Modelo', 'label' => 'Entidade', 'value' => $entity->getCode()],
            ['section' => 'Modelo', 'label' => 'Tipo de dado', 'value' => $dataType],
            ['section' => 'Modelo', 'label' => 'Obrigatorio', 'value' => $field->isRequired() ? 'Sim' : 'Nao', 'critical' => $field->isRequired()],
            ['section' => 'Modelo', 'label' => 'Chave primaria', 'value' => $field->isPrimaryKey() ? 'Sim' : 'Nao', 'critical' => $field->isPrimaryKey()],
        ];

        if ($entity->getEntityType() !== 'api') {
            $columnName = trim((string) ($options['columnName'] ?? $field->getCode()));
            if ($entity->getTableName()) {
                $properties[] = ['section' => 'Banco', 'label' => 'Tabela', 'value' => (string) $entity->getTableName()];
            }
            if ($columnName !== '') {
                $properties[] = ['section' => 'Banco', 'label' => 'Coluna', 'value' => $columnName];
            }
        }

        if ($field->getDatabaseType()) {
            $properties[] = ['section' => 'Banco', 'label' => 'Tipo banco', 'value' => (string) $field->getDatabaseType()];
        }
        if ($field->getLength() !== null) {
            $properties[] = ['section' => 'Banco', 'label' => 'Tamanho', 'value' => (string) $field->getLength()];
        }
        if ($field->isPrimaryKey() || ($options['readonly'] ?? false) === true || ($options['editable'] ?? true) === false) {
            $properties[] = ['section' => 'Runtime', 'label' => 'Somente leitura', 'value' => 'Sim', 'critical' => true];
        }
        if (($options['virtual'] ?? false) === true) {
            $properties[] = ['section' => 'Runtime', 'label' => 'Campo virtual', 'value' => 'Sim'];
        }
        if ($entity->getEntityType() === 'api') {
            $apiJsonPath = trim((string) (($options['api']['jsonPath'] ?? '') ?: ($options['apiJsonPath'] ?? '')));
            if ($apiJsonPath !== '') {
                $properties[] = ['section' => 'API', 'label' => 'JSON Path', 'value' => $apiJsonPath, 'critical' => true];
            }
            $apiSourceCode = trim((string) ($entity->getMetadata()['apiSourceCode'] ?? ''));
            if ($apiSourceCode !== '') {
                $properties[] = ['section' => 'API', 'label' => 'API cadastrada', 'value' => $apiSourceCode];
            }
            $listOperationCode = trim((string) ($entity->getMetadata()['apiListOperationCode'] ?? ''));
            if ($listOperationCode !== '') {
                $properties[] = ['section' => 'API', 'label' => 'Operacao de lista', 'value' => $listOperationCode];
            }
            $detailOperationCode = trim((string) ($entity->getMetadata()['apiDetailOperationCode'] ?? ''));
            if ($detailOperationCode !== '') {
                $properties[] = ['section' => 'API', 'label' => 'Operacao de detalhe', 'value' => $detailOperationCode];
            }
            $providerType = strtolower(trim((string) ($entity->getMetadata()['apiSource']['providerType'] ?? $entity->getMetadata()['providerType'] ?? '')));
            if ($providerType === 'odoo') {
                $odoo = is_array($entity->getMetadata()['apiSource']['odoo'] ?? null) ? $entity->getMetadata()['apiSource']['odoo'] : [];
                if (($odoo['model'] ?? '') !== '') {
                    $properties[] = ['section' => 'Odoo', 'label' => 'Modelo', 'value' => (string) $odoo['model']];
                }
                if (($odoo['transport'] ?? '') !== '') {
                    $properties[] = ['section' => 'Odoo', 'label' => 'Transporte', 'value' => strtoupper((string) $odoo['transport'])];
                }
            }
        }

        if (!empty($options['foreignKey']['entityCode'])) {
            $properties[] = ['section' => 'Relacionamento', 'label' => 'FK entidade', 'value' => (string) $options['foreignKey']['entityCode']];
        }
        if (!empty($options['foreignKey']['fieldCode'])) {
            $properties[] = ['section' => 'Relacionamento', 'label' => 'FK campo', 'value' => (string) $options['foreignKey']['fieldCode']];
        }

        return $properties;
    }

    private function programGridTechnicalProperties(BuilderEntity $entity, BuilderField $field, string $dataType, array $options): array
    {
        return array_merge([
            ['section' => 'Exibicao', 'label' => 'Superficie', 'value' => 'Grid'],
            ['section' => 'Exibicao', 'label' => 'Largura sugerida', 'value' => in_array($dataType, ['datetime', 'text'], true) ? '220' : '150'],
        ], $this->programFieldTechnicalProperties($entity, $field, $dataType, $options));
    }

    private function programFilterTechnicalProperties(BuilderEntity $entity, BuilderField $field, string $dataType, array $options): array
    {
        return array_merge([
            ['section' => 'Exibicao', 'label' => 'Superficie', 'value' => 'Filtro'],
            ['section' => 'Exibicao', 'label' => 'Operador inicial', 'value' => in_array($dataType, ['boolean', 'enum', 'integer'], true) ? 'eq' : 'contains'],
        ], $this->programFieldTechnicalProperties($entity, $field, $dataType, $options));
    }

    private function syncRuntimeEndpoints(BuilderProgramVersion $version): void
    {
        if ($version->getPageType() === 'crud') {
            $this->upsertCrudRuntimeEndpoints($version);
            return;
        }

        if ($version->getPageType() === 'analytics') {
            $this->upsertAnalyticsRuntimeEndpoints($version);
            return;
        }
        if ($version->getPageType() === 'report') {
            $this->upsertReportRuntimeEndpoints($version);
            return;
        }
        if ($version->getPageType() === 'special_document') {
            $this->upsertSpecialDocumentRuntimeEndpoints($version);
            return;
        }
        if ($version->getPageType() === 'regulated_document') {
            $this->upsertRegulatedDocumentRuntimeEndpoints($version);
            return;
        }

        $this->disableRuntimeEndpointsForScreen($version->getScreenId());
    }

    private function upsertCrudRuntimeEndpoints(BuilderProgramVersion $version): void
    {
        $apiEntity = $this->isApiEntityVersion($version);
        $odooEntity = $this->isOdooApiEntityVersion($version);
        $handlers = [
            'read' => $odooEntity ? 'entity.api.odoo.readonly' : ($apiEntity ? 'entity.api.crud' : 'entity.crud'),
            'get' => $odooEntity ? 'entity.api.odoo.readonly' : ($apiEntity ? 'entity.api.crud' : 'entity.crud'),
            'saveLayout' => 'layout.save',
            'restoreLayout' => 'layout.restore',
            'saveSort' => 'layout.saveSort',
            'deleteSort' => 'layout.deleteSort',
            'saveGroup' => 'layout.saveGroup',
            'deleteGroup' => 'layout.deleteGroup',
            'saveFilter' => 'layout.saveFilter',
            'deleteFilter' => 'layout.deleteFilter',
            'saveMobileTemplate' => 'layout.saveMobileTemplate',
            'deleteMobileTemplate' => 'layout.deleteMobileTemplate',
        ];
        if ($apiEntity && !$odooEntity) {
            $handlers['create'] = 'entity.api.crud';
            $handlers['update'] = 'entity.api.crud';
            $handlers['delete'] = 'entity.api.crud';
        } elseif (!$apiEntity && !$odooEntity) {
            $handlers['create'] = 'entity.crud';
            $handlers['update'] = 'entity.crud';
            $handlers['delete'] = 'entity.crud';
            $handlers['runtime.lock.acquire'] = 'runtime.lock.acquire';
            $handlers['runtime.lock.heartbeat'] = 'runtime.lock.heartbeat';
            $handlers['runtime.lock.release'] = 'runtime.lock.release';
            $handlers['runtime.messages.poll'] = 'runtime.messages.poll';
            $handlers['runtime.messages.ack'] = 'runtime.messages.ack';
        }

        $activeEndpointIds = [];
        foreach ($handlers as $endpointId => $handler) {
            if (in_array($endpointId, ['create', 'update', 'delete'], true)) {
                if ($endpointId === 'create' && !$version->isAllowCreate()) {
                    continue;
                }
                if ($endpointId === 'update' && !$version->isAllowUpdate()) {
                    continue;
                }
                if ($endpointId === 'delete' && !$version->isAllowDelete()) {
                    continue;
                }
            }

            $endpoint = $this->endpoints->findOneBy([
                'screenId' => $version->getScreenId(),
                'endpointId' => $endpointId,
            ]) ?? new RuntimeEndpoint();

            $endpoint
                ->setScreenId($version->getScreenId())
                ->setEndpointId($endpointId)
                ->setHandler($handler)
                ->setEnabled(true)
                ->setPermission($this->endpointPermission($version, $endpointId, $handler))
                ->setConfig($this->endpointConfig($version, $endpointId, $handler));

            $this->entityManager->persist($endpoint);
            $this->integrity->signEndpoint($endpoint, ['source' => 'syncRuntimeEndpoints']);
            $activeEndpointIds[] = $endpointId;
        }

        $this->disableUnusedCrudGeneratedEndpoints($version->getScreenId(), $activeEndpointIds);
    }

    private function upsertAnalyticsRuntimeEndpoints(BuilderProgramVersion $version): void
    {
        $handlers = [
            'analytics.schema' => 'analytics.schema',
            'analytics.query.run' => 'analytics.query.run',
            'analytics.materialize' => 'analytics.materialize',
            'analytics.cache.status' => 'analytics.cache.status',
            'analytics.pipeline.schema' => 'analytics.pipeline.schema',
            'analytics.pipeline.preview' => 'analytics.pipeline.preview',
            'analytics.pipeline.run' => 'analytics.pipeline.run',
            'analytics.pipeline.publish' => 'analytics.pipeline.publish',
            'analytics.pipeline.status' => 'analytics.pipeline.status',
            'analytics.pipeline.logs' => 'analytics.pipeline.logs',
            'analytics.pipeline.versions' => 'analytics.pipeline.versions',
            'analytics.pipeline.rollback' => 'analytics.pipeline.rollback',
        ];

        $activeEndpointIds = [];
        foreach ($handlers as $endpointId => $handler) {
            $endpoint = $this->endpoints->findOneBy([
                'screenId' => $version->getScreenId(),
                'endpointId' => $endpointId,
            ]) ?? new RuntimeEndpoint();

            $endpoint
                ->setScreenId($version->getScreenId())
                ->setEndpointId($endpointId)
                ->setHandler($handler)
                ->setEnabled(true)
                ->setPermission($this->endpointPermission($version, $endpointId, $handler))
                ->setConfig($this->endpointConfig($version, $endpointId, $handler));

            $this->entityManager->persist($endpoint);
            $this->integrity->signEndpoint($endpoint, ['source' => 'syncRuntimeEndpoints']);
            $activeEndpointIds[] = $endpointId;
        }

        $this->disableUnusedCrudGeneratedEndpoints($version->getScreenId(), $activeEndpointIds);
    }

    private function upsertReportRuntimeEndpoints(BuilderProgramVersion $version): void
    {
        $handlers = [
            'reports.schema' => 'reports.schema',
            'reports.run' => 'reports.run',
            'reports.export' => 'reports.export',
        ];

        $activeEndpointIds = [];
        foreach ($handlers as $endpointId => $handler) {
            $endpoint = $this->endpoints->findOneBy([
                'screenId' => $version->getScreenId(),
                'endpointId' => $endpointId,
            ]) ?? new RuntimeEndpoint();

            $endpoint
                ->setScreenId($version->getScreenId())
                ->setEndpointId($endpointId)
                ->setHandler($handler)
                ->setEnabled(true)
                ->setPermission($this->endpointPermission($version, $endpointId, $handler))
                ->setConfig($this->endpointConfig($version, $endpointId, $handler));

            $this->entityManager->persist($endpoint);
            $this->integrity->signEndpoint($endpoint, ['source' => 'syncRuntimeEndpoints']);
            $activeEndpointIds[] = $endpointId;
        }

        $this->disableUnusedCrudGeneratedEndpoints($version->getScreenId(), $activeEndpointIds);
    }

    private function upsertSpecialDocumentRuntimeEndpoints(BuilderProgramVersion $version): void
    {
        $handlers = [
            'specialDocuments.schema' => 'specialDocuments.schema',
            'specialDocuments.render' => 'specialDocuments.render',
            'specialDocuments.export' => 'specialDocuments.export',
        ];

        $activeEndpointIds = [];
        foreach ($handlers as $endpointId => $handler) {
            $endpoint = $this->endpoints->findOneBy([
                'screenId' => $version->getScreenId(),
                'endpointId' => $endpointId,
            ]) ?? new RuntimeEndpoint();

            $endpoint
                ->setScreenId($version->getScreenId())
                ->setEndpointId($endpointId)
                ->setHandler($handler)
                ->setEnabled(true)
                ->setPermission($this->endpointPermission($version, $endpointId, $handler))
                ->setConfig($this->endpointConfig($version, $endpointId, $handler));

            $this->entityManager->persist($endpoint);
            $this->integrity->signEndpoint($endpoint, ['source' => 'syncRuntimeEndpoints']);
            $activeEndpointIds[] = $endpointId;
        }

        $this->disableUnusedCrudGeneratedEndpoints($version->getScreenId(), $activeEndpointIds);
    }

    private function upsertRegulatedDocumentRuntimeEndpoints(BuilderProgramVersion $version): void
    {
        $handlers = [
            'regulatedDocuments.schema' => 'regulatedDocuments.schema',
            'regulatedDocuments.prepare' => 'regulatedDocuments.prepare',
            'regulatedDocuments.render' => 'regulatedDocuments.render',
            'regulatedDocuments.issue' => 'regulatedDocuments.issue',
            'regulatedDocuments.verify' => 'regulatedDocuments.verify',
            'regulatedDocuments.artifact' => 'regulatedDocuments.artifact',
        ];

        $activeEndpointIds = [];
        foreach ($handlers as $endpointId => $handler) {
            $endpoint = $this->endpoints->findOneBy([
                'screenId' => $version->getScreenId(),
                'endpointId' => $endpointId,
            ]) ?? new RuntimeEndpoint();

            $endpoint
                ->setScreenId($version->getScreenId())
                ->setEndpointId($endpointId)
                ->setHandler($handler)
                ->setEnabled(true)
                ->setPermission($this->endpointPermission($version, $endpointId, $handler))
                ->setConfig($this->endpointConfig($version, $endpointId, $handler));

            $this->entityManager->persist($endpoint);
            $this->integrity->signEndpoint($endpoint, ['source' => 'syncRuntimeEndpoints']);
            $activeEndpointIds[] = $endpointId;
        }

        $this->disableUnusedCrudGeneratedEndpoints($version->getScreenId(), $activeEndpointIds);
    }

    private function disableRuntimeEndpointsForScreen(string $screenId): void
    {
        foreach ($this->endpoints->findBy(['screenId' => $screenId]) as $endpoint) {
            $endpoint->setEnabled(false);
            $this->entityManager->persist($endpoint);
            $this->integrity->signEndpoint($endpoint, ['source' => 'disableRuntimeEndpointsForScreen']);
        }
    }

    private function signRuntimeEndpointsForScreen(string $screenId): void
    {
        foreach ($this->endpoints->findBy(['screenId' => $screenId]) as $endpoint) {
            $this->integrity->signEndpoint($endpoint, ['source' => 'publishVersion']);
        }
    }

    /**
     * @param list<string> $activeEndpointIds
     */
    private function disableUnusedCrudGeneratedEndpoints(string $screenId, array $activeEndpointIds): void
    {
        $generatedEndpointIds = [
            'read',
            'get',
            'create',
            'update',
            'delete',
            'saveLayout',
            'restoreLayout',
            'saveSort',
            'deleteSort',
            'saveGroup',
            'deleteGroup',
            'saveFilter',
            'deleteFilter',
            'saveMobileTemplate',
            'deleteMobileTemplate',
            'runtime.lock.acquire',
            'runtime.lock.heartbeat',
            'runtime.lock.release',
            'runtime.messages.poll',
            'runtime.messages.ack',
            'analytics.schema',
            'analytics.query.run',
            'analytics.materialize',
            'analytics.cache.status',
            'analytics.pipeline.schema',
            'analytics.pipeline.preview',
            'analytics.pipeline.run',
            'analytics.pipeline.publish',
            'analytics.pipeline.status',
            'analytics.pipeline.logs',
            'analytics.pipeline.versions',
            'analytics.pipeline.rollback',
            'reports.schema',
            'reports.run',
            'reports.export',
            'specialDocuments.schema',
            'specialDocuments.render',
            'specialDocuments.export',
            'regulatedDocuments.schema',
            'regulatedDocuments.prepare',
            'regulatedDocuments.render',
            'regulatedDocuments.issue',
            'regulatedDocuments.verify',
            'regulatedDocuments.artifact',
        ];

        foreach ($this->endpoints->findBy(['screenId' => $screenId]) as $endpoint) {
            if (!in_array($endpoint->getEndpointId(), $generatedEndpointIds, true)) {
                continue;
            }
            if (in_array($endpoint->getEndpointId(), $activeEndpointIds, true)) {
                continue;
            }
            $endpoint->setEnabled(false);
            $this->entityManager->persist($endpoint);
            $this->integrity->signEndpoint($endpoint, ['source' => 'disableUnusedCrudGeneratedEndpoints']);
        }
    }

    private function endpointPermission(BuilderProgramVersion $version, string $endpointId, string $handler): ?string
    {
        if (str_starts_with($endpointId, 'runtime.messages.') || str_starts_with($endpointId, 'runtime.lock.')) {
            return null;
        }
        if (str_starts_with($handler, 'layout.')) {
            return 'user.preferences';
        }

        $prefix = $version->getPermissionPrefix();
        if ($prefix === null || $prefix === '') {
            return null;
        }

        return match ($endpointId) {
            'read', 'get' => $prefix . '.read',
            'create' => $prefix . '.create',
            'update' => $prefix . '.edit',
            'delete' => $prefix . '.delete',
            'analytics.schema', 'analytics.query.run', 'analytics.materialize', 'analytics.cache.status', 'analytics.pipeline.schema', 'analytics.pipeline.preview', 'analytics.pipeline.run', 'analytics.pipeline.publish', 'analytics.pipeline.status', 'analytics.pipeline.logs', 'analytics.pipeline.versions', 'analytics.pipeline.rollback', 'reports.schema', 'reports.run', 'reports.export', 'specialDocuments.schema', 'specialDocuments.render', 'specialDocuments.export', 'regulatedDocuments.schema', 'regulatedDocuments.prepare', 'regulatedDocuments.render', 'regulatedDocuments.issue', 'regulatedDocuments.verify', 'regulatedDocuments.artifact' => $prefix . '.read',
            default => null,
        };
    }

    private function endpointConfig(BuilderProgramVersion $version, string $endpointId, string $handler): array
    {
        if (in_array($handler, ['entity.crud', 'entity.api.readonly', 'entity.api.crud', 'entity.api.odoo.readonly'], true)) {
            return [
                'entityCode' => $version->getBuilderEntityCode(),
                'operation' => $endpointId,
                'actionId' => $endpointId,
                'programId' => $version->getProgramCode(),
                'permissionPrefix' => $version->getPermissionPrefix(),
                'traceability' => [
                    'programCode' => $version->getProgramCode(),
                    'programVersion' => $version->getVersion(),
                    'builderProgramVersionId' => $version->getId(),
                    'builderEntityVersionId' => $this->latestBuilderEntityVersionId($version->getBuilderEntityCode()),
                    'screenDefinitionVersion' => $version->getVersion(),
                    'schemaFingerprint' => $this->programSchemaFingerprint($version, $version->getGeneratedDefinition()),
                    'customizationKind' => $version->getProgramOrigin(),
                    'subscriberId' => $version->getSubscriberId(),
                ],
            ];
        }

        if (str_starts_with($handler, 'analytics.')) {
            return [
                'entityCode' => $version->getBuilderEntityCode(),
                'operation' => $handler,
                'actionId' => $endpointId,
                'programId' => $version->getProgramCode(),
                'permissionPrefix' => $version->getPermissionPrefix(),
                'traceability' => [
                    'programCode' => $version->getProgramCode(),
                    'programVersion' => $version->getVersion(),
                    'builderProgramVersionId' => $version->getId(),
                    'builderEntityVersionId' => $this->latestBuilderEntityVersionId($version->getBuilderEntityCode()),
                    'screenDefinitionVersion' => $version->getVersion(),
                    'schemaFingerprint' => $this->programSchemaFingerprint($version, $version->getGeneratedDefinition()),
                    'customizationKind' => $version->getProgramOrigin(),
                    'subscriberId' => $version->getSubscriberId(),
                ],
            ];
        }

        if (str_starts_with($handler, 'reports.')) {
            return [
                'entityCode' => $version->getBuilderEntityCode(),
                'operation' => $handler,
                'actionId' => $endpointId,
                'programId' => $version->getProgramCode(),
                'permissionPrefix' => $version->getPermissionPrefix(),
                'traceability' => [
                    'programCode' => $version->getProgramCode(),
                    'programVersion' => $version->getVersion(),
                    'builderProgramVersionId' => $version->getId(),
                    'builderEntityVersionId' => $this->latestBuilderEntityVersionId($version->getBuilderEntityCode()),
                    'screenDefinitionVersion' => $version->getVersion(),
                    'schemaFingerprint' => $this->programSchemaFingerprint($version, $version->getGeneratedDefinition()),
                    'customizationKind' => $version->getProgramOrigin(),
                    'subscriberId' => $version->getSubscriberId(),
                ],
            ];
        }

        if (str_starts_with($handler, 'specialDocuments.')) {
            return [
                'entityCode' => $version->getBuilderEntityCode(),
                'operation' => $handler,
                'actionId' => $endpointId,
                'programId' => $version->getProgramCode(),
                'permissionPrefix' => $version->getPermissionPrefix(),
                'traceability' => [
                    'programCode' => $version->getProgramCode(),
                    'programVersion' => $version->getVersion(),
                    'builderProgramVersionId' => $version->getId(),
                    'builderEntityVersionId' => $this->latestBuilderEntityVersionId($version->getBuilderEntityCode()),
                    'screenDefinitionVersion' => $version->getVersion(),
                    'schemaFingerprint' => $this->programSchemaFingerprint($version, $version->getGeneratedDefinition()),
                    'customizationKind' => $version->getProgramOrigin(),
                    'subscriberId' => $version->getSubscriberId(),
                ],
            ];
        }

        if (str_starts_with($handler, 'regulatedDocuments.')) {
            return [
                'entityCode' => $version->getBuilderEntityCode(),
                'operation' => $handler,
                'actionId' => $endpointId,
                'programId' => $version->getProgramCode(),
                'permissionPrefix' => $version->getPermissionPrefix(),
                'traceability' => [
                    'programCode' => $version->getProgramCode(),
                    'programVersion' => $version->getVersion(),
                    'builderProgramVersionId' => $version->getId(),
                    'builderEntityVersionId' => $this->latestBuilderEntityVersionId($version->getBuilderEntityCode()),
                    'screenDefinitionVersion' => $version->getVersion(),
                    'schemaFingerprint' => $this->programSchemaFingerprint($version, $version->getGeneratedDefinition()),
                    'customizationKind' => $version->getProgramOrigin(),
                    'subscriberId' => $version->getSubscriberId(),
                ],
            ];
        }

        return [];
    }

    private function crudApiMap(array $config): array
    {
        $api = [
            'read' => ['endpointId' => 'read', 'method' => 'POST'],
        ];
        if (!$this->isApiEntity($config['_entity']) || $this->apiEntityHasDetailEndpoint($config['_entity'])) {
            $api['get'] = ['endpointId' => 'get', 'method' => 'POST'];
        }
        if ($config['allowCreate']) {
            $api['create'] = ['endpointId' => 'create', 'method' => 'POST'];
        }
        if ($config['allowUpdate']) {
            $api['update'] = ['endpointId' => 'update', 'method' => 'POST'];
        }
        if ($config['allowDelete']) {
            $api['delete'] = ['endpointId' => 'delete', 'method' => 'POST'];
        }

        $systemEndpoints = [
            'saveLayout' => ['endpointId' => 'saveLayout', 'method' => 'POST'],
            'restoreLayout' => ['endpointId' => 'restoreLayout', 'method' => 'POST'],
            'saveSort' => ['endpointId' => 'saveSort', 'method' => 'POST'],
            'deleteSort' => ['endpointId' => 'deleteSort', 'method' => 'POST'],
            'saveGroup' => ['endpointId' => 'saveGroup', 'method' => 'POST'],
            'deleteGroup' => ['endpointId' => 'deleteGroup', 'method' => 'POST'],
            'saveFilter' => ['endpointId' => 'saveFilter', 'method' => 'POST'],
            'deleteFilter' => ['endpointId' => 'deleteFilter', 'method' => 'POST'],
            'saveMobileTemplate' => ['endpointId' => 'saveMobileTemplate', 'method' => 'POST'],
            'deleteMobileTemplate' => ['endpointId' => 'deleteMobileTemplate', 'method' => 'POST'],
        ];
        if (!$this->isApiEntity($config['_entity'])) {
            $systemEndpoints = [
                'runtime.lock.acquire' => ['endpointId' => 'runtime.lock.acquire', 'method' => 'POST'],
                'runtime.lock.heartbeat' => ['endpointId' => 'runtime.lock.heartbeat', 'method' => 'POST'],
                'runtime.lock.release' => ['endpointId' => 'runtime.lock.release', 'method' => 'POST'],
                'runtime.messages.poll' => ['endpointId' => 'runtime.messages.poll', 'method' => 'POST'],
                'runtime.messages.ack' => ['endpointId' => 'runtime.messages.ack', 'method' => 'POST'],
            ] + $systemEndpoints;
        }

        return $api + $systemEndpoints;
    }

    private function analyticsApiMap(): array
    {
        return [
            'schema' => ['endpointId' => 'analytics.schema', 'method' => 'POST'],
            'run' => ['endpointId' => 'analytics.query.run', 'method' => 'POST'],
            'materialize' => ['endpointId' => 'analytics.materialize', 'method' => 'POST'],
            'cacheStatus' => ['endpointId' => 'analytics.cache.status', 'method' => 'POST'],
            'pipelineSchema' => ['endpointId' => 'analytics.pipeline.schema', 'method' => 'POST'],
            'pipelinePreview' => ['endpointId' => 'analytics.pipeline.preview', 'method' => 'POST'],
            'pipelineRun' => ['endpointId' => 'analytics.pipeline.run', 'method' => 'POST'],
            'pipelinePublish' => ['endpointId' => 'analytics.pipeline.publish', 'method' => 'POST'],
            'pipelineStatus' => ['endpointId' => 'analytics.pipeline.status', 'method' => 'POST'],
            'pipelineLogs' => ['endpointId' => 'analytics.pipeline.logs', 'method' => 'POST'],
            'pipelineVersions' => ['endpointId' => 'analytics.pipeline.versions', 'method' => 'POST'],
            'pipelineRollback' => ['endpointId' => 'analytics.pipeline.rollback', 'method' => 'POST'],
        ];
    }

    private function reportApiMap(): array
    {
        return [
            'schema' => ['endpointId' => 'reports.schema', 'method' => 'POST'],
            'run' => ['endpointId' => 'reports.run', 'method' => 'POST'],
            'export' => ['endpointId' => 'reports.export', 'method' => 'POST'],
        ];
    }

    private function specialDocumentApiMap(): array
    {
        return [
            'schema' => ['endpointId' => 'specialDocuments.schema', 'method' => 'POST'],
            'render' => ['endpointId' => 'specialDocuments.render', 'method' => 'POST'],
            'export' => ['endpointId' => 'specialDocuments.export', 'method' => 'POST'],
        ];
    }

    private function regulatedDocumentApiMap(): array
    {
        return [
            'schema' => ['endpointId' => 'regulatedDocuments.schema', 'method' => 'POST'],
            'prepare' => ['endpointId' => 'regulatedDocuments.prepare', 'method' => 'POST'],
            'render' => ['endpointId' => 'regulatedDocuments.render', 'method' => 'POST'],
            'issue' => ['endpointId' => 'regulatedDocuments.issue', 'method' => 'POST'],
            'verify' => ['endpointId' => 'regulatedDocuments.verify', 'method' => 'POST'],
            'artifact' => ['endpointId' => 'regulatedDocuments.artifact', 'method' => 'POST'],
        ];
    }

    private function toolbar(array $config): array
    {
        $toolbar = [];
        if ($config['allowCreate']) {
            $toolbar[] = ['id' => 'create', 'label' => 'Incluir', 'action' => 'create', 'icon' => 'plus', 'permission' => 'create'];
        }

        return array_merge($toolbar, [
            ['id' => 'filters', 'label' => 'Filtros', 'action' => 'filters', 'icon' => 'filter'],
            ['id' => 'refresh', 'label' => 'Atualizar', 'action' => 'refresh', 'icon' => 'arrow-rotate-cw'],
            ['id' => 'layout', 'label' => 'Leiaute', 'action' => 'layout', 'icon' => 'columns', 'permission' => 'saveLayout'],
        ]);
    }

    private function rowActions(array $config): array
    {
        $actions = [
            ['id' => 'view', 'label' => 'Visualizar', 'action' => 'view', 'icon' => 'eye', 'permission' => 'read'],
        ];
        if ($config['allowUpdate']) {
            $actions[] = ['id' => 'edit', 'label' => 'Alterar', 'action' => 'edit', 'icon' => 'pencil', 'permission' => 'edit'];
        }
        if ($config['allowDelete']) {
            $actions[] = ['id' => 'delete', 'label' => 'Excluir', 'action' => 'delete', 'icon' => 'trash', 'permission' => 'delete'];
        }

        return $actions;
    }

    private function versionPayload(BuilderProgramVersion $version): array
    {
        return [
            'id' => $version->getId(),
            'programCode' => $version->getProgramCode(),
            'programTitle' => $version->getProgramTitle(),
            'module' => $version->getModule(),
            'pageType' => $version->getPageType(),
            'builderEntityCode' => $version->getBuilderEntityCode(),
            'screenId' => $version->getScreenId(),
            'version' => $version->getVersion(),
            'status' => $version->getStatus(),
            'subtitle' => $version->getSubtitle(),
            'icon' => $version->getIcon(),
            'permissionPrefix' => $version->getPermissionPrefix(),
            'allowCreate' => $version->isAllowCreate(),
            'allowUpdate' => $version->isAllowUpdate(),
            'allowDelete' => $version->isAllowDelete(),
            'changeSummary' => $version->getChangeSummary(),
            'programOrigin' => $version->getProgramOrigin(),
            'ownerScope' => $version->getOwnerScope(),
            'customizationPolicy' => $version->getCustomizationPolicy(),
            'subscriberId' => $version->getSubscriberId(),
            'baseProgramCode' => $version->getBaseProgramCode(),
            'baseProgramVersionId' => $version->getBaseProgramVersionId(),
            'upgradeFrozen' => $version->isUpgradeFrozen(),
            'frozenReason' => $version->getFrozenReason(),
            'governance' => $this->governance->governanceSummary($version),
            'customMode' => $version->getBuilderConfig()['customMode'] ?? null,
            'customEntryUrl' => $version->getBuilderConfig()['customEntryUrl'] ?? null,
            'customFrameTitle' => $version->getBuilderConfig()['customFrameTitle'] ?? null,
            'builderConfig' => $this->publicBuilderConfig($version->getBuilderConfig()),
            'generatedDefinition' => $version->getGeneratedDefinition(),
            'publishedAt' => $version->getPublishedAt()?->format(DATE_ATOM),
            'createdAt' => $version->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $version->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    private function publicBuilderConfig(array $config): array
    {
        $copy = $config;
        unset($copy['_entity']);

        return $copy;
    }

    private function normalizePublicationPolicy(mixed $value): array
    {
        $input = is_array($value) ? $value : [];
        $allowed = [];
        foreach ((array) ($input['allowedDatabaseEnvironments'] ?? []) as $item) {
            $normalized = strtolower(trim((string) $item));
            if ($normalized === '') {
                continue;
            }
            $allowed[] = $normalized;
        }
        $allowed = array_values(array_unique($allowed));

        return [
            'allowedDatabaseEnvironments' => $allowed,
        ];
    }

    private function assertPublicationEnvironmentAllowed(BuilderProgramVersion $version): void
    {
        $config = $version->getBuilderConfig();
        $policy = is_array($config['publicationPolicy'] ?? null) ? $config['publicationPolicy'] : [];
        $allowed = array_values(array_filter(array_map(static fn (mixed $item): string => strtolower(trim((string) $item)), (array) ($policy['allowedDatabaseEnvironments'] ?? []))));
        if (!$allowed) {
            return;
        }

        $environment = strtolower(trim((string) ($this->environmentIdentity->resolve()['databaseEnvironment'] ?? '')));
        if ($environment !== '' && !in_array($environment, $allowed, true)) {
            throw new RuntimeHttpException('PROGRAM_PUBLICATION_ENVIRONMENT_BLOCKED', 'A publicacao nao esta autorizada para o ambiente atual.', 422, [
                'programCode' => $version->getProgramCode(),
                'builderProgramVersionId' => $version->getId(),
                'databaseEnvironment' => $environment,
                'allowedDatabaseEnvironments' => $allowed,
            ]);
        }
    }

    private function assertMaintenanceEnvironmentAllowed(string $operation): void
    {
        $rawAllowed = (string) ($_ENV['PROGRAM_BUILDER_MAINTENANCE_DATABASE_ENVIRONMENTS'] ?? $_SERVER['PROGRAM_BUILDER_MAINTENANCE_DATABASE_ENVIRONMENTS'] ?? 'dev,test,homolog');
        $allowed = array_values(array_filter(array_map(
            static fn (string $item): string => strtolower(trim($item)),
            explode(',', $rawAllowed)
        )));
        if (!$allowed) {
            $allowed = ['dev', 'test', 'homolog'];
        }

        $environment = strtolower(trim((string) ($this->environmentIdentity->resolve()['databaseEnvironment'] ?? '')));
        if ($environment !== '' && !in_array($environment, $allowed, true)) {
            throw new RuntimeHttpException('PROGRAM_BUILDER_MAINTENANCE_ENVIRONMENT_BLOCKED', 'A operacao de manutencao nao esta autorizada para o ambiente atual.', 422, [
                'operation' => $operation,
                'databaseEnvironment' => $environment,
                'allowedDatabaseEnvironments' => $allowed,
            ]);
        }
    }

    private function buildTransientEntity(array $config): BuilderEntity
    {
        $entity = (new BuilderEntity())
            ->setCode($config['code'])
            ->setName($config['name'])
            ->setEntityType($config['entityType'])
            ->setTableName($config['tableName'])
            ->setStatus('draft')
            ->setSituationEnabled($config['situationEnabled'])
            ->setSituationFieldCode($config['situationFieldCode'])
            ->setMetadata([
                'structure' => $config['structure'],
                'subscriberIsolation' => $config['subscriberIsolation'],
                'uniqueKeys' => $config['uniqueKeys'],
                'rules' => $config['rules'],
                'softDelete' => $config['softDelete'],
                'apiSource' => $config['apiSource'] ?? null,
                'apiBinding' => $config['apiBinding'] ?? null,
                'versioning' => [
                    'enabled' => $config['versioningEnabled'],
                    'deduplicate' => $config['versioningDeduplicate'],
                ],
            ]);

        foreach ($config['fields'] as $fieldConfig) {
            $field = (new BuilderField())
                ->setBuilderEntity($entity)
                ->setCode($fieldConfig['code'])
                ->setLabel($fieldConfig['label'])
                ->setDataType($fieldConfig['dataType'])
                ->setDatabaseType($this->guessDatabaseType($fieldConfig['dataType']))
                ->setLength($fieldConfig['length'])
                ->setPrecisionValue($fieldConfig['precision'])
                ->setScaleValue($fieldConfig['scale'])
                ->setRequired($fieldConfig['required'])
                ->setPrimaryKey($fieldConfig['primaryKey'])
                ->setPosition((int) $fieldConfig['position'])
                ->setOptions($fieldConfig['options']);
            $entity->addField($field);
        }

        return $entity;
    }

    private function externalEntityPayload(array $config): array
    {
        $fields = array_map(function (array $field): array {
            return [
                'id' => (int) ($field['id'] ?? 0),
                'code' => $field['code'],
                'label' => $field['label'],
                'dataType' => $field['dataType'],
                'required' => $field['required'],
                'primaryKey' => $field['primaryKey'],
                'length' => $field['length'],
                'precision' => $field['precision'],
                'scale' => $field['scale'],
                'position' => $field['position'],
                'options' => $field['options'],
                'columnName' => $field['columnName'],
                'originalCode' => $field['originalCode'],
                'originalColumnName' => $field['originalColumnName'],
                'defaultValue' => $field['options']['defaultValue'] ?? null,
                'unique' => ($field['options']['unique'] ?? false) === true,
                'foreignKeyTable' => is_array($field['options']['foreignKey'] ?? null) ? ($field['options']['foreignKey']['table'] ?? null) : null,
                'foreignKeyColumn' => is_array($field['options']['foreignKey'] ?? null) ? ($field['options']['foreignKey']['column'] ?? null) : null,
                'foreignKeyOnDelete' => is_array($field['options']['foreignKey'] ?? null) ? ($field['options']['foreignKey']['onDelete'] ?? null) : null,
                'foreignKeyOnUpdate' => is_array($field['options']['foreignKey'] ?? null) ? ($field['options']['foreignKey']['onUpdate'] ?? null) : null,
                'foreignKeyDependencyType' => is_array($field['options']['foreignKey'] ?? null) ? ($field['options']['foreignKey']['dependencyType'] ?? null) : null,
                'readonlyField' => ($field['options']['readonly'] ?? false) === true || ($field['options']['writable'] ?? true) === false,
                'optionItems' => is_array($field['options']['options'] ?? null) ? $field['options']['options'] : [],
                'virtualField' => ($field['options']['virtual'] ?? false) === true,
                'includeInVersion' => ($field['options']['includeInVersion'] ?? true) !== false,
                'versionRefEntityCode' => is_array($field['options']['versionReference'] ?? null) ? ($field['options']['versionReference']['sourceEntityCode'] ?? null) : null,
                'versionRefSourceIdField' => is_array($field['options']['versionReference'] ?? null) ? ($field['options']['versionReference']['sourceIdField'] ?? null) : null,
                'versionSnapshotVersionField' => is_array($field['options']['versionSnapshot'] ?? null) ? ($field['options']['versionSnapshot']['versionField'] ?? null) : null,
                'versionSnapshotPath' => is_array($field['options']['versionSnapshot'] ?? null) ? ($field['options']['versionSnapshot']['path'] ?? null) : null,
                'customCodeMode' => is_array($field['options']['customCode'] ?? null) ? ($field['options']['customCode']['mode'] ?? null) : null,
                'customCodePrefix' => is_array($field['options']['customCode'] ?? null) ? ($field['options']['customCode']['prefix'] ?? null) : null,
                'customCodePattern' => is_array($field['options']['customCode'] ?? null) ? ($field['options']['customCode']['pattern'] ?? null) : null,
                'customCodeSequenceEnabled' => is_array($field['options']['customCode'] ?? null) ? (($field['options']['customCode']['sequenceEnabled'] ?? true) !== false) : true,
                'customCodeSequenceScope' => is_array($field['options']['customCode'] ?? null) ? ($field['options']['customCode']['sequenceScope'] ?? null) : null,
                'customCodeSequencePadding' => is_array($field['options']['customCode'] ?? null) ? ($field['options']['customCode']['sequencePadding'] ?? null) : null,
                'customCodeStaticClass' => is_array($field['options']['customCode'] ?? null) ? ($field['options']['customCode']['staticClass'] ?? null) : null,
                'customCodeStaticMethod' => is_array($field['options']['customCode'] ?? null) ? ($field['options']['customCode']['staticMethod'] ?? null) : null,
                'customCodeAssistantScreenId' => is_array($field['options']['customCode'] ?? null) ? ($field['options']['customCode']['assistantScreenId'] ?? null) : null,
                'customCodePromptTitle' => is_array($field['options']['customCode'] ?? null) ? ($field['options']['customCode']['promptTitle'] ?? null) : null,
                'customCodePromptFields' => is_array($field['options']['customCode'] ?? null) && is_array($field['options']['customCode']['promptFields'] ?? null) ? $field['options']['customCode']['promptFields'] : [],
                'apiJsonPath' => is_array($field['options']['api'] ?? null) ? ($field['options']['api']['jsonPath'] ?? null) : null,
                'apiWritePath' => is_array($field['options']['api'] ?? null) ? ($field['options']['api']['writePath'] ?? null) : null,
                'apiShowInGrid' => !is_array($field['options']['api'] ?? null) || ($field['options']['api']['showInGrid'] ?? true) !== false,
                'apiShowInForm' => !is_array($field['options']['api'] ?? null) || ($field['options']['api']['showInForm'] ?? true) !== false,
                'apiShowInFilter' => is_array($field['options']['api'] ?? null) && ($field['options']['api']['showInFilter'] ?? false) === true,
            ];
        }, $config['fields']);

        return [
            'code' => $config['code'],
            'name' => $config['name'],
            'entityType' => $config['entityType'],
            'tableName' => $config['tableName'],
            'status' => 'draft',
            'situationEnabled' => $config['situationEnabled'],
            'situationFieldCode' => $config['situationFieldCode'],
            'metadata' => [
                'structure' => $config['structure'],
                'subscriberIsolation' => $config['subscriberIsolation'],
                'uniqueKeys' => $config['uniqueKeys'],
                'rules' => $config['rules'],
                'softDelete' => $config['softDelete'],
                'apiSource' => $this->maskApiSourceSecrets($config['apiSource'] ?? null),
                'apiBinding' => $config['apiBinding'] ?? null,
                'versioning' => [
                    'enabled' => $config['versioningEnabled'],
                    'deduplicate' => $config['versioningDeduplicate'],
                ],
            ],
            'apiSourceCode' => (string) ($config['apiBinding']['sourceCode'] ?? ''),
            'apiListOperationCode' => (string) ($config['apiBinding']['listOperationCode'] ?? ''),
            'apiDetailOperationCode' => (string) ($config['apiBinding']['detailOperationCode'] ?? ''),
            'apiCreateOperationCode' => (string) ($config['apiBinding']['createOperationCode'] ?? ''),
            'apiUpdateOperationCode' => (string) ($config['apiBinding']['updateOperationCode'] ?? ''),
            'apiDeleteOperationCode' => (string) ($config['apiBinding']['deleteOperationCode'] ?? ''),
            'structureModuleCode' => (string) ($config['structure']['moduleCode'] ?? ''),
            'structureType' => (string) ($config['structure']['type'] ?? 'main'),
            'structureBaseNumber' => $config['structure']['baseNumber'] ?? null,
            'structureSequenceNumber' => $config['structure']['sequenceNumber'] ?? null,
            'structureParentEntityCode' => (string) ($config['structure']['parentEntityCode'] ?? ''),
            'structureLeftEntityCode' => (string) ($config['structure']['leftEntityCode'] ?? ''),
            'structureRightEntityCode' => (string) ($config['structure']['rightEntityCode'] ?? ''),
            'subscriberIsolationMode' => (string) ($config['subscriberIsolation']['mode'] ?? 'none'),
            'subscriberColumnName' => (string) ($config['subscriberIsolation']['columnName'] ?? ''),
            'subscriberGlobalTable' => ($config['subscriberIsolation']['globalTable'] ?? false) === true,
            'softDeleteEnabled' => ($config['softDelete']['enabled'] ?? false) === true,
            'softDeleteDeletedAtField' => (string) ($config['softDelete']['deletedAtField'] ?? ''),
            'softDeleteDeletedByField' => (string) ($config['softDelete']['deletedByField'] ?? ''),
            'softDeleteReasonField' => (string) ($config['softDelete']['reasonField'] ?? ''),
            'uniqueKeys' => $config['uniqueKeys'],
            'rules' => $config['rules'],
            'versioningEnabled' => $config['versioningEnabled'],
            'versioningDeduplicate' => $config['versioningDeduplicate'],
            'fields' => $fields,
            'supportsPhysicalCrud' => $config['entityType'] === 'persistence',
        ];
    }

    private function externalProgramPayload(array $config, array $definition): array
    {
        return [
            'programCode' => $config['programCode'],
            'programTitle' => $config['programTitle'],
            'module' => $config['module'],
            'pageType' => $config['pageType'],
            'builderEntityCode' => $config['builderEntityCode'],
            'screenId' => $config['screenId'],
            'version' => $config['version'],
            'subtitle' => $config['subtitle'],
            'icon' => $config['icon'],
            'permissionPrefix' => $config['permissionPrefix'],
            'allowCreate' => $config['allowCreate'],
            'allowUpdate' => $config['allowUpdate'],
            'allowDelete' => $config['allowDelete'],
            'changeSummary' => $config['changeSummary'],
            'programOrigin' => $config['programOrigin'],
            'ownerScope' => $config['ownerScope'],
            'customizationPolicy' => $config['customizationPolicy'],
            'subscriberId' => $config['subscriberId'],
            'baseProgramCode' => $config['baseProgramCode'],
            'baseProgramVersionId' => $config['baseProgramVersionId'],
            'upgradeFrozen' => $config['upgradeFrozen'],
            'frozenReason' => $config['frozenReason'],
            'publicationPolicy' => $config['publicationPolicy'],
            'generatedDefinition' => $definition,
            'builderConfig' => $this->publicBuilderConfig($config),
        ];
    }

    private function normalizeExternalDiagnostics(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $diagnostics = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $message = trim((string) ($item['message'] ?? ''));
            if ($message === '') {
                continue;
            }
            $level = strtolower(trim((string) ($item['level'] ?? 'info')));
            if (!in_array($level, ['info', 'warn', 'error'], true)) {
                $level = 'info';
            }
            $diagnostics[] = [
                'level' => $level,
                'message' => $message,
                'source' => 'externo',
            ];
        }

        return $diagnostics;
    }

    private function collectExternalDraftDiagnostics(array $entityPayload, array $programPayload, array $entityConfig, array $programConfig): array
    {
        $diagnostics = [];

        $this->appendNormalizationDiagnostic($diagnostics, 'info', 'entityDraft.code', $entityPayload['code'] ?? null, $entityConfig['code']);
        $this->appendNormalizationDiagnostic($diagnostics, 'info', 'entityDraft.tableName', $entityPayload['tableName'] ?? null, $entityConfig['tableName']);
        $this->appendNormalizationDiagnostic($diagnostics, 'info', 'programDraft.programCode', $programPayload['programCode'] ?? null, $programConfig['programCode']);
        $this->appendNormalizationDiagnostic($diagnostics, 'info', 'programDraft.screenId', $programPayload['screenId'] ?? null, $programConfig['screenId']);

        if (!isset($programPayload['version']) || trim((string) $programPayload['version']) === '') {
            $diagnostics[] = [
                'level' => 'info',
                'message' => 'Versao ausente no JSON externo. O builder assumiu 1.0.0.',
                'source' => 'builder',
            ];
        }

        foreach (($entityPayload['fields'] ?? []) as $index => $field) {
            if (!is_array($field)) {
                continue;
            }
            $rawType = strtolower(trim((string) ($field['dataType'] ?? 'string')));
            $normalizedField = $entityConfig['fields'][$index] ?? null;
            if (!$normalizedField) {
                continue;
            }
            if (!in_array($rawType, ['string', 'text', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'enum', 'dropdown', 'email', 'json', 'custom_code'], true)) {
                $diagnostics[] = [
                    'level' => 'warn',
                    'message' => 'Campo ' . $normalizedField['code'] . ' usou tipo nao suportado (' . $rawType . ') e foi ajustado para string.',
                    'source' => 'builder',
                ];
            }
            $this->appendNormalizationDiagnostic($diagnostics, 'info', 'field.' . $normalizedField['code'] . '.code', $field['code'] ?? null, $normalizedField['code']);
            $this->appendNormalizationDiagnostic($diagnostics, 'info', 'field.' . $normalizedField['code'] . '.columnName', $field['columnName'] ?? null, $normalizedField['columnName']);
        }

        return $diagnostics;
    }

    private function appendNormalizationDiagnostic(array &$diagnostics, string $level, string $path, mixed $rawValue, mixed $normalizedValue): void
    {
        if ($rawValue === null || $rawValue === '') {
            return;
        }
        $raw = trim((string) $rawValue);
        $normalized = trim((string) $normalizedValue);
        if ($raw === '' || $raw === $normalized) {
            return;
        }
        $diagnostics[] = [
            'level' => $level,
            'message' => $path . ' foi normalizado de "' . $raw . '" para "' . $normalized . '".',
            'source' => 'builder',
        ];
    }

    private function createEntityVersionSnapshot(
        BuilderEntity $entity,
        string $action,
        ?string $changeSummary = null,
        ?int $sourceVersionId = null
    ): BuilderEntityVersion {
        foreach ($this->entityVersions->findByEntityCodeOrdered($entity->getCode()) as $item) {
            if ($item->getStatus() === 'current') {
                $item->setStatus('archived');
                $this->entityManager->persist($item);
            }
        }

        $snapshot = $this->entityPayload($entity);
        $snapshot['createPhysicalTable'] = $entity->getEntityType() === 'persistence';
        $snapshot['allowTableRename'] = true;
        $snapshot['allowColumnRename'] = true;
        $snapshot['dropRemovedColumns'] = false;

        $version = (new BuilderEntityVersion())
            ->setBuilderEntityCode($entity->getCode())
            ->setEntityName($entity->getName())
            ->setEntityType($entity->getEntityType())
            ->setTableName($entity->getTableName())
            ->setRevision($this->entityVersions->nextRevision($entity->getCode()))
            ->setStatus('current')
            ->setAction($action)
            ->setSourceVersionId($sourceVersionId)
            ->setChangeSummary($changeSummary)
            ->setSnapshot($snapshot);

        $this->entityManager->persist($version);
        $this->entityManager->flush();
        $this->events->publish('builder.entity.versioned', [
            'tenantId' => $this->permissions->getTenantId(),
            'userId' => $this->permissions->getUserId(),
            'screenId' => 'admin.program-builder',
            'programCode' => null,
            'entityCode' => $entity->getCode(),
            'recordId' => $version->getId(),
            'operation' => $action,
            'before' => [],
            'after' => [
                'entityCode' => $entity->getCode(),
                'revision' => $version->getRevision(),
                'status' => $version->getStatus(),
                'action' => $action,
            ],
            'changes' => [],
            'transactionId' => null,
            'occurredAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ], [
            'source' => 'program_builder',
            'screenId' => 'admin.program-builder',
            'entityCode' => $entity->getCode(),
            'recordId' => $version->getId(),
            'operation' => $action,
        ]);

        return $version;
    }

    private function mergeRestoreOrigins(array $config, BuilderEntity $current): array
    {
        $currentById = [];
        $currentByCode = [];
        foreach ($current->getFields() as $field) {
            $options = $field->getOptions();
            if ($field->getId() !== null) {
                $currentById[$field->getId()] = [
                    'code' => $field->getCode(),
                    'columnName' => (string) ($options['columnName'] ?? $field->getCode()),
                ];
            }
            $currentByCode[$field->getCode()] = [
                'code' => $field->getCode(),
                'columnName' => (string) ($options['columnName'] ?? $field->getCode()),
            ];
        }

        foreach ($config['fields'] as $index => $field) {
            $currentField = null;
            $fieldId = (int) ($field['id'] ?? 0);
            if ($fieldId > 0 && isset($currentById[$fieldId])) {
                $currentField = $currentById[$fieldId];
            } elseif (isset($currentByCode[$field['code']])) {
                $currentField = $currentByCode[$field['code']];
            }
            if (!$currentField) {
                continue;
            }
            $config['fields'][$index]['originalCode'] = $currentField['code'];
            $config['fields'][$index]['originalColumnName'] = $currentField['columnName'];
        }

        return $config;
    }

    private function normalizeFieldType(string $type): string
    {
        return match ($type) {
            'string' => 'text',
            'custom_code' => 'string',
            'decimal' => 'number',
            default => $type,
        };
    }

    private function normalizeBuilderFieldType(string $type): string
    {
        $normalized = strtolower(trim($type));
        if (!in_array($normalized, ['string', 'text', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'enum', 'dropdown', 'email', 'json', 'custom_code'], true)) {
            return 'string';
        }

        return $normalized;
    }

    private function normalizeApiJsonPath(string $path): string
    {
        $normalized = trim($path);
        if ($normalized === '') {
            return '';
        }
        if ($normalized === '$') {
            return '$';
        }
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)*$/', $normalized)) {
            throw new RuntimeHttpException('ENTITY_API_JSON_PATH_INVALID', 'Path JSON da entidade API invalido.', 422, [
                'path' => $path,
            ]);
        }

        return $normalized;
    }

    private function normalizeApiSource(array $payload): array
    {
        $baseUrl = $this->normalizeApiUrl((string) ($payload['baseUrl'] ?? ''), false);
        $listEndpoint = $this->normalizeApiEndpointConfig(is_array($payload['listEndpoint'] ?? null) ? $payload['listEndpoint'] : [], 'list', true, $baseUrl);
        $listResponse = is_array($payload['listResponse'] ?? null) ? $payload['listResponse'] : [];
        $itemsPath = $this->normalizeApiJsonPath((string) ($listResponse['itemsPath'] ?? ''));
        if ($itemsPath === '') {
            throw new RuntimeHttpException('ENTITY_API_ITEMS_PATH_REQUIRED', 'Entidade API exige itemsPath para a resposta da lista.', 422);
        }
        $totalPath = $this->normalizeApiJsonPath((string) ($listResponse['totalPath'] ?? ''));
        $detailPayload = is_array($payload['detailEndpoint'] ?? null) ? $payload['detailEndpoint'] : [];
        $detailEnabled = !empty($detailPayload['url']);
        $detailEndpoint = $detailEnabled ? $this->normalizeApiEndpointConfig($detailPayload, 'detail', false, $baseUrl) : null;
        $detailResponse = is_array($payload['detailResponse'] ?? null) ? $payload['detailResponse'] : [];
        $itemPath = $detailEnabled ? $this->normalizeApiJsonPath((string) ($detailResponse['itemPath'] ?? '')) : '';
        if ($detailEnabled && $itemPath === '') {
            throw new RuntimeHttpException('ENTITY_API_DETAIL_ITEM_PATH_REQUIRED', 'Ao informar endpoint de detalhe, informe itemPath.', 422);
        }
        $createEndpoint = $this->normalizeApiEndpointConfig(is_array($payload['createEndpoint'] ?? null) ? $payload['createEndpoint'] : [], 'create', false, $baseUrl);
        $createResponse = is_array($payload['createResponse'] ?? null) ? $payload['createResponse'] : [];
        $createItemPath = $createEndpoint ? $this->normalizeApiJsonPath((string) ($createResponse['itemPath'] ?? '$')) : '';
        $updateEndpoint = $this->normalizeApiEndpointConfig(is_array($payload['updateEndpoint'] ?? null) ? $payload['updateEndpoint'] : [], 'update', false, $baseUrl);
        $updateResponse = is_array($payload['updateResponse'] ?? null) ? $payload['updateResponse'] : [];
        $updateItemPath = $updateEndpoint ? $this->normalizeApiJsonPath((string) ($updateResponse['itemPath'] ?? '$')) : '';
        $deleteEndpoint = $this->normalizeApiEndpointConfig(is_array($payload['deleteEndpoint'] ?? null) ? $payload['deleteEndpoint'] : [], 'delete', false, $baseUrl);

        return [
            'mode' => ($createEndpoint || $updateEndpoint || $deleteEndpoint) ? 'crud' : 'readonly',
            'baseUrl' => $baseUrl !== '' ? $baseUrl : null,
            'authHeaders' => $this->normalizeApiKeyValueMap($payload['authHeaders'] ?? [], 'authHeaders'),
            'timeoutSeconds' => max(1, min(120, (int) ($payload['timeoutSeconds'] ?? 20))),
            'listEndpoint' => $listEndpoint,
            'listResponse' => [
                'itemsPath' => $itemsPath,
                'totalPath' => $totalPath !== '' ? $totalPath : null,
            ],
            'detailEndpoint' => $detailEndpoint,
            'detailResponse' => $detailEnabled ? ['itemPath' => $itemPath] : null,
            'createEndpoint' => $createEndpoint,
            'createResponse' => $createEndpoint ? ['itemPath' => $createItemPath !== '' ? $createItemPath : '$'] : null,
            'updateEndpoint' => $updateEndpoint,
            'updateResponse' => $updateEndpoint ? ['itemPath' => $updateItemPath !== '' ? $updateItemPath : '$'] : null,
            'deleteEndpoint' => $deleteEndpoint,
        ];
    }

    private function normalizeApiSourceRegistryPayload(array $payload): array
    {
        $code = $this->safeCode((string) ($payload['code'] ?? ''));
        $name = trim((string) ($payload['name'] ?? ''));
        $providerType = strtolower(trim((string) ($payload['providerType'] ?? 'generic')));
        $authMode = strtolower(trim((string) ($payload['authMode'] ?? 'none')));
        $baseUrl = $this->normalizeApiUrl((string) ($payload['baseUrl'] ?? ''), false);
        $openapiUrl = trim((string) ($payload['openapiUrl'] ?? ''));
        $status = strtolower(trim((string) ($payload['status'] ?? 'active')));
        $timeoutSeconds = max(1, min(120, (int) ($payload['timeoutSeconds'] ?? 20)));
        $authHeaders = $this->normalizeApiKeyValueMap($payload['authHeaders'] ?? [], 'apiSource.authHeaders');
        $operations = $this->normalizeApiOperationsRegistry($payload['operations'] ?? [], $baseUrl);

        if ($code === '' || $name === '') {
            throw new RuntimeHttpException('API_SOURCE_REQUIRED_FIELDS', 'Informe codigo e nome do cadastro da API.', 422);
        }
        if (!in_array($providerType, ['generic', 'odoo'], true)) {
            throw new RuntimeHttpException('API_SOURCE_PROVIDER_INVALID', 'Tipo de provedor da API invalido.', 422, [
                'providerType' => $providerType,
            ]);
        }
        if (!in_array($authMode, ['none', 'header_static', 'bearer_static', 'basic_static'], true)) {
            throw new RuntimeHttpException('API_SOURCE_AUTH_MODE_INVALID', 'Modo de autenticacao da API invalido.', 422, [
                'authMode' => $authMode,
            ]);
        }
        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        return [
            'code' => $code,
            'name' => $name,
            'providerType' => $providerType,
            'authMode' => $authMode,
            'baseUrl' => $baseUrl !== '' ? $baseUrl : null,
            'openapiUrl' => $providerType === 'generic' && $openapiUrl !== '' ? $openapiUrl : null,
            'status' => $status,
            'timeoutSeconds' => $timeoutSeconds,
            'authHeaders' => $providerType === 'generic' ? $authHeaders : [],
            'operations' => $providerType === 'generic' ? $operations : [],
            'odoo' => $providerType === 'odoo' ? $this->normalizeOdooSourceConfig($payload, $baseUrl !== '' ? $baseUrl : null, $timeoutSeconds) : null,
        ];
    }

    private function normalizeOdooSourceConfig(array $payload, ?string $resolvedBaseUrl, int $timeoutSeconds): array
    {
        $odoo = is_array($payload['odoo'] ?? null) ? $payload['odoo'] : [];
        $baseUrl = $resolvedBaseUrl ?: $this->normalizeApiUrl((string) ($odoo['baseUrl'] ?? $payload['baseUrl'] ?? ''), true);
        $transport = strtolower(trim((string) ($odoo['transport'] ?? 'xmlrpc')));
        $database = trim((string) ($odoo['database'] ?? ''));
        $login = trim((string) ($odoo['login'] ?? ''));
        $secretMode = strtolower(trim((string) ($odoo['secretMode'] ?? 'password')));
        $secretValue = trim((string) ($odoo['secretValue'] ?? ''));
        $model = trim((string) ($odoo['model'] ?? ''));
        $defaultContext = $odoo['defaultContext'] ?? [];
        $defaultDomain = $odoo['defaultDomain'] ?? [];
        $defaultOrder = trim((string) ($odoo['defaultOrder'] ?? ''));
        $defaultLimit = max(1, min(500, (int) ($odoo['defaultLimit'] ?? 80)));
        $json2Ready = ($odoo['json2Ready'] ?? true) !== false;

        if ($database === '' || $login === '' || $secretValue === '' || $model === '') {
            throw new RuntimeHttpException('ODOO_SOURCE_REQUIRED_FIELDS', 'Informe URL, banco, login, segredo e modelo do Odoo.', 422);
        }
        if (!in_array($transport, ['xmlrpc', 'jsonrpc'], true)) {
            throw new RuntimeHttpException('ODOO_SOURCE_TRANSPORT_INVALID', 'Transporte do Odoo invalido. Use xmlrpc ou jsonrpc.', 422, [
                'transport' => $transport,
            ]);
        }
        if (!in_array($secretMode, ['password', 'api_key'], true)) {
            throw new RuntimeHttpException('ODOO_SOURCE_SECRET_MODE_INVALID', 'Tipo de segredo do Odoo invalido.', 422, [
                'secretMode' => $secretMode,
            ]);
        }
        if (!is_array($defaultContext)) {
            throw new RuntimeHttpException('ODOO_SOURCE_CONTEXT_INVALID', 'O contexto padrao do Odoo deve ser um objeto JSON.', 422);
        }
        if (!is_array($defaultDomain)) {
            throw new RuntimeHttpException('ODOO_SOURCE_DOMAIN_INVALID', 'O dominio padrao do Odoo deve ser um array JSON.', 422);
        }

        return [
            'transport' => $transport,
            'baseUrl' => $baseUrl,
            'database' => $database,
            'login' => $login,
            'secretMode' => $secretMode,
            'secretValue' => $secretValue,
            'model' => $model,
            'defaultContext' => $defaultContext,
            'defaultDomain' => $defaultDomain,
            'defaultOrder' => $defaultOrder !== '' ? $defaultOrder : null,
            'defaultLimit' => $defaultLimit,
            'timeoutSeconds' => $timeoutSeconds,
            'json2Ready' => $json2Ready,
        ];
    }

    private function apiSourceOperationsFromMetadata(array $metadata): array
    {
        $providerType = (string) ($metadata['providerType'] ?? 'generic');
        if ($providerType === 'odoo') {
            return [
                [
                    'code' => 'odoo_list',
                    'name' => 'Lista do modelo Odoo',
                    'type' => 'list',
                    'method' => 'RPC',
                    'path' => '',
                    'headers' => [],
                    'queryParams' => [],
                    'bodyTemplate' => null,
                    'itemsPath' => '$',
                    'itemPath' => null,
                    'totalPath' => null,
                ],
                [
                    'code' => 'odoo_detail',
                    'name' => 'Detalhe do modelo Odoo',
                    'type' => 'detail',
                    'method' => 'RPC',
                    'path' => '',
                    'headers' => [],
                    'queryParams' => [],
                    'bodyTemplate' => null,
                    'itemsPath' => null,
                    'itemPath' => '$',
                    'totalPath' => null,
                ],
            ];
        }

        return is_array($metadata['operations'] ?? null) ? $metadata['operations'] : [];
    }

    private function resolveOdooSourceConfig(array $payload): array
    {
        $sourceCode = $this->safeCode((string) ($payload['sourceCode'] ?? ''));
        if ($sourceCode !== '') {
            $source = $this->apiSources->findOneBy(['code' => $sourceCode]);
            if (!$source) {
                throw new RuntimeHttpException('API_SOURCE_NOT_FOUND', 'Cadastro de API nao encontrado.', 404, [
                    'sourceCode' => $sourceCode,
                ]);
            }
            $metadata = $source->getMetadata();
            if ((string) ($metadata['providerType'] ?? 'generic') !== 'odoo') {
                throw new RuntimeHttpException('ODOO_SOURCE_PROVIDER_REQUIRED', 'O cadastro selecionado nao usa o provedor Odoo.', 422, [
                    'sourceCode' => $sourceCode,
                ]);
            }

            return is_array($metadata['odoo'] ?? null) ? $metadata['odoo'] : [];
        }

        return $this->normalizeOdooSourceConfig($payload, null, max(1, min(120, (int) ($payload['timeoutSeconds'] ?? 20))));
    }

    private function mapOdooModelField(string $fieldName, array $definition): ?array
    {
        $type = strtolower(trim((string) ($definition['type'] ?? 'char')));
        $label = trim((string) ($definition['string'] ?? $fieldName));
        $relation = trim((string) ($definition['relation'] ?? ''));
        $selection = is_array($definition['selection'] ?? null) ? $definition['selection'] : [];
        $dataType = match ($type) {
            'integer' => 'integer',
            'float', 'monetary' => 'decimal',
            'boolean' => 'boolean',
            'date' => 'date',
            'datetime' => 'datetime',
            'text', 'html' => 'text',
            'one2many', 'many2many' => 'json',
            default => 'string',
        };
        if ($fieldName === 'id') {
            $dataType = 'integer';
        }

        return [
            'code' => $this->safeSqlIdentifier($fieldName),
            'label' => $label !== '' ? $label : $fieldName,
            'dataType' => $dataType,
            'columnName' => $this->safeSqlIdentifier($fieldName),
            'apiJsonPath' => $fieldName,
            'apiWritePath' => $fieldName,
            'apiShowInGrid' => true,
            'apiShowInForm' => true,
            'apiShowInFilter' => in_array($dataType, ['string', 'integer', 'decimal', 'boolean', 'date', 'datetime'], true),
            'required' => ($definition['required'] ?? false) === true,
            'primaryKey' => $fieldName === 'id',
            'readonlyField' => $fieldName === 'id' || ($definition['readonly'] ?? false) === true,
            'optionItems' => array_map(static fn ($item) => [
                'value' => is_array($item) ? ($item[0] ?? '') : '',
                'text' => is_array($item) ? ($item[1] ?? '') : '',
            ], $selection),
            'options' => [
                'odoo' => [
                    'fieldType' => $type,
                    'relation' => $relation !== '' ? $relation : null,
                ],
            ],
        ];
    }

    private function normalizeApiOperationsRegistry(mixed $value, string $baseUrl): array
    {
        if (!is_array($value)) {
            return [];
        }
        $operations = [];
        foreach ($value as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $code = $this->safeCode((string) ($item['code'] ?? ''));
            $name = trim((string) ($item['name'] ?? ''));
            $type = strtolower(trim((string) ($item['type'] ?? 'custom')));
            $endpoint = $this->normalizeApiEndpointConfig([
                'url' => (string) ($item['path'] ?? $item['url'] ?? ''),
                'method' => (string) ($item['method'] ?? 'GET'),
                'headers' => $item['headers'] ?? [],
                'queryParams' => $item['queryParams'] ?? [],
                'bodyTemplate' => $item['bodyTemplate'] ?? null,
            ], 'operation.' . $index, true, $baseUrl);
            $itemsPath = $this->normalizeApiJsonPath((string) ($item['itemsPath'] ?? ''));
            $itemPath = $this->normalizeApiJsonPath((string) ($item['itemPath'] ?? ''));
            $totalPath = $this->normalizeApiJsonPath((string) ($item['totalPath'] ?? ''));
            if ($code === '' || $name === '') {
                continue;
            }
            $operations[] = [
                'code' => $code,
                'name' => $name,
                'type' => in_array($type, ['list', 'detail', 'create', 'update', 'delete', 'custom'], true) ? $type : 'custom',
                'method' => $endpoint['method'],
                'path' => $endpoint['url'],
                'headers' => $endpoint['headers'],
                'queryParams' => $endpoint['queryParams'],
                'bodyTemplate' => $endpoint['bodyTemplate'],
                'itemsPath' => $itemsPath !== '' ? $itemsPath : null,
                'itemPath' => $itemPath !== '' ? $itemPath : null,
                'totalPath' => $totalPath !== '' ? $totalPath : null,
            ];
        }

        return $operations;
    }

    private function resolveApiSourceBinding(BuilderApiSource $source, string $listOperationCode, string $detailOperationCode, string $createOperationCode = '', string $updateOperationCode = '', string $deleteOperationCode = ''): array
    {
        $metadata = $source->getMetadata();
        if ((string) ($metadata['providerType'] ?? 'generic') === 'odoo') {
            $odoo = is_array($metadata['odoo'] ?? null) ? $metadata['odoo'] : [];
            if (!$odoo) {
                throw new RuntimeHttpException('ODOO_SOURCE_NOT_CONFIGURED', 'Cadastro Odoo sem configuracao valida.', 422, [
                    'apiSourceCode' => $source->getCode(),
                ]);
            }

            return [
                'providerType' => 'odoo',
                'mode' => 'readonly',
                'odoo' => $odoo,
            ];
        }

        $operations = is_array($metadata['operations'] ?? null) ? $metadata['operations'] : [];
        $listOperation = null;
        $detailOperation = null;
        $createOperation = null;
        $updateOperation = null;
        $deleteOperation = null;
        foreach ($operations as $operation) {
            if (!is_array($operation)) {
                continue;
            }
            if ($listOperationCode !== '' && (string) ($operation['code'] ?? '') === $listOperationCode) {
                $listOperation = $operation;
            }
            if ($detailOperationCode !== '' && (string) ($operation['code'] ?? '') === $detailOperationCode) {
                $detailOperation = $operation;
            }
            if ($createOperationCode !== '' && (string) ($operation['code'] ?? '') === $createOperationCode) {
                $createOperation = $operation;
            }
            if ($updateOperationCode !== '' && (string) ($operation['code'] ?? '') === $updateOperationCode) {
                $updateOperation = $operation;
            }
            if ($deleteOperationCode !== '' && (string) ($operation['code'] ?? '') === $deleteOperationCode) {
                $deleteOperation = $operation;
            }
        }
        if (!$listOperation) {
            $listOperation = $this->findFirstApiOperationByType($operations, 'list');
        }
        if (!$detailOperation) {
            $detailOperation = $this->findFirstApiOperationByType($operations, 'detail');
        }
        if (!$createOperation && $createOperationCode === '') {
            $createOperation = $this->findFirstApiOperationByType($operations, 'create');
        }
        if (!$updateOperation && $updateOperationCode === '') {
            $updateOperation = $this->findFirstApiOperationByType($operations, 'update');
        }
        if (!$deleteOperation && $deleteOperationCode === '') {
            $deleteOperation = $this->findFirstApiOperationByType($operations, 'delete');
        }
        if (!is_array($listOperation)) {
            throw new RuntimeHttpException('ENTITY_API_LIST_OPERATION_REQUIRED', 'Selecione uma operacao de lista no cadastro da API.', 422, [
                'apiSourceCode' => $source->getCode(),
            ]);
        }

        return [
            'providerType' => 'generic',
            'mode' => (is_array($createOperation) || is_array($updateOperation) || is_array($deleteOperation)) ? 'crud' : 'readonly',
            'baseUrl' => $source->getBaseUrl(),
            'authHeaders' => is_array($metadata['authHeaders'] ?? null) ? $metadata['authHeaders'] : [],
            'timeoutSeconds' => max(1, min(120, (int) ($metadata['timeoutSeconds'] ?? 20))),
            'listEndpoint' => [
                'url' => (string) ($listOperation['path'] ?? ''),
                'method' => strtoupper((string) ($listOperation['method'] ?? 'GET')),
                'headers' => is_array($listOperation['headers'] ?? null) ? $listOperation['headers'] : [],
                'queryParams' => is_array($listOperation['queryParams'] ?? null) ? $listOperation['queryParams'] : [],
                'bodyTemplate' => $listOperation['bodyTemplate'] ?? null,
            ],
            'listResponse' => [
                'itemsPath' => (string) ($listOperation['itemsPath'] ?? ''),
                'totalPath' => $listOperation['totalPath'] ?? null,
            ],
            'detailEndpoint' => is_array($detailOperation) ? [
                'url' => (string) ($detailOperation['path'] ?? ''),
                'method' => strtoupper((string) ($detailOperation['method'] ?? 'GET')),
                'headers' => is_array($detailOperation['headers'] ?? null) ? $detailOperation['headers'] : [],
                'queryParams' => is_array($detailOperation['queryParams'] ?? null) ? $detailOperation['queryParams'] : [],
                'bodyTemplate' => $detailOperation['bodyTemplate'] ?? null,
            ] : null,
            'detailResponse' => is_array($detailOperation) ? [
                'itemPath' => (string) ($detailOperation['itemPath'] ?? '$'),
            ] : null,
            'createEndpoint' => is_array($createOperation) ? [
                'url' => (string) ($createOperation['path'] ?? ''),
                'method' => strtoupper((string) ($createOperation['method'] ?? 'POST')),
                'headers' => is_array($createOperation['headers'] ?? null) ? $createOperation['headers'] : [],
                'queryParams' => is_array($createOperation['queryParams'] ?? null) ? $createOperation['queryParams'] : [],
                'bodyTemplate' => $createOperation['bodyTemplate'] ?? null,
            ] : null,
            'createResponse' => is_array($createOperation) ? [
                'itemPath' => (string) ($createOperation['itemPath'] ?? '$'),
            ] : null,
            'updateEndpoint' => is_array($updateOperation) ? [
                'url' => (string) ($updateOperation['path'] ?? ''),
                'method' => strtoupper((string) ($updateOperation['method'] ?? 'PUT')),
                'headers' => is_array($updateOperation['headers'] ?? null) ? $updateOperation['headers'] : [],
                'queryParams' => is_array($updateOperation['queryParams'] ?? null) ? $updateOperation['queryParams'] : [],
                'bodyTemplate' => $updateOperation['bodyTemplate'] ?? null,
            ] : null,
            'updateResponse' => is_array($updateOperation) ? [
                'itemPath' => (string) ($updateOperation['itemPath'] ?? '$'),
            ] : null,
            'deleteEndpoint' => is_array($deleteOperation) ? [
                'url' => (string) ($deleteOperation['path'] ?? ''),
                'method' => strtoupper((string) ($deleteOperation['method'] ?? 'DELETE')),
                'headers' => is_array($deleteOperation['headers'] ?? null) ? $deleteOperation['headers'] : [],
                'queryParams' => is_array($deleteOperation['queryParams'] ?? null) ? $deleteOperation['queryParams'] : [],
                'bodyTemplate' => $deleteOperation['bodyTemplate'] ?? null,
            ] : null,
        ];
    }

    private function findFirstApiOperationByType(array $operations, string $type): ?array
    {
        foreach ($operations as $operation) {
            if (is_array($operation) && (string) ($operation['type'] ?? '') === $type) {
                return $operation;
            }
        }

        return null;
    }

    private function normalizeApiEndpointConfig(array $payload, string $name, bool $required, string $baseUrl = ''): ?array
    {
        $url = $this->normalizeApiUrl((string) ($payload['url'] ?? ''), $required, $baseUrl);
        if ($url === '') {
            return null;
        }
        $method = strtoupper(trim((string) ($payload['method'] ?? 'GET')));
        if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            throw new RuntimeHttpException('ENTITY_API_METHOD_INVALID', 'Metodo da entidade API invalido. Use GET, POST, PUT, PATCH ou DELETE.', 422, [
                'endpoint' => $name,
                'method' => $method,
            ]);
        }

        return [
            'url' => $url,
            'method' => $method,
            'headers' => $this->normalizeApiKeyValueMap($payload['headers'] ?? [], $name . '.headers'),
            'queryParams' => $this->normalizeApiKeyValueMap($payload['queryParams'] ?? [], $name . '.queryParams'),
            'bodyTemplate' => in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) ? $this->normalizeApiBodyTemplate($payload['bodyTemplate'] ?? null, $name . '.bodyTemplate') : null,
        ];
    }

    private function normalizeApiBodyTemplate(mixed $value, string $name): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_scalar($value)) {
            return $value;
        }
        if (!is_array($value)) {
            throw new RuntimeHttpException('ENTITY_API_BODY_TEMPLATE_INVALID', 'Body template da entidade API aceita apenas valores estaticos.', 422, [
                'field' => $name,
            ]);
        }

        foreach ($value as $key => $item) {
            if (!is_scalar($key) || !(is_scalar($item) || $item === null)) {
                throw new RuntimeHttpException('ENTITY_API_BODY_TEMPLATE_INVALID', 'Body template da entidade API aceita apenas pares estaticos.', 422, [
                    'field' => $name,
                ]);
            }
        }

        return $value;
    }

    private function normalizeApiKeyValueMap(mixed $value, string $name): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (!is_array($value)) {
            throw new RuntimeHttpException('ENTITY_API_MAP_INVALID', 'Configuracao da entidade API invalida.', 422, [
                'field' => $name,
            ]);
        }
        $normalized = [];
        foreach ($value as $key => $item) {
            $normalizedKey = trim((string) $key);
            if ($normalizedKey === '' || !(is_scalar($item) || $item === null)) {
                throw new RuntimeHttpException('ENTITY_API_MAP_INVALID', 'Configuracao da entidade API aceita apenas pares estaticos.', 422, [
                    'field' => $name,
                ]);
            }
            $normalized[$normalizedKey] = $item === null ? '' : (string) $item;
        }

        return $normalized;
    }

    private function normalizeApiUrl(string $url, bool $required, string $baseUrl = ''): string
    {
        $normalized = trim($url);
        if ($normalized === '') {
            if ($required) {
                throw new RuntimeHttpException('ENTITY_API_URL_REQUIRED', 'Informe a URL do endpoint da entidade API.', 422);
            }

            return '';
        }
        if ($baseUrl !== '' && !preg_match('/^https?:\/\//i', $normalized)) {
            $normalized = rtrim($baseUrl, '/') . '/' . ltrim($normalized, '/');
        }
        if (!preg_match('/^https?:\/\//i', $normalized)) {
            throw new RuntimeHttpException('ENTITY_API_URL_INVALID', 'URL da entidade API deve ser absoluta.', 422, [
                'url' => $normalized,
            ]);
        }
        $parts = parse_url($normalized) ?: [];
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $allowHttpLocal = $scheme === 'http' && in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        if ($scheme !== 'https' && !$allowHttpLocal) {
            throw new RuntimeHttpException('ENTITY_API_URL_UNSAFE', 'URL da entidade API deve usar https. Http fica restrito ao ambiente local.', 422, [
                'url' => $normalized,
            ]);
        }

        return $normalized;
    }

    private function maskApiSourceSecrets(?array $apiSource): ?array
    {
        if ($apiSource === null) {
            return null;
        }
        if ((string) ($apiSource['providerType'] ?? 'generic') === 'odoo') {
            $apiSource['odoo'] = $this->maskOdooSourceSecrets(is_array($apiSource['odoo'] ?? null) ? $apiSource['odoo'] : []);
            return $apiSource;
        }

        return $this->mapApiSourceSecrets($apiSource, static fn (string $key, string $value, array $context = []): string => self::isSensitiveApiHeaderName($key) && $value !== '' ? '********' : $value);
    }

    private function restoreMaskedApiSourceSecrets(?array $apiSource, array $existing): ?array
    {
        if ($apiSource === null) {
            return null;
        }
        if ((string) ($apiSource['providerType'] ?? 'generic') === 'odoo') {
            $apiSource['odoo'] = $this->restoreMaskedOdooSourceSecrets(
                is_array($apiSource['odoo'] ?? null) ? $apiSource['odoo'] : [],
                is_array($existing['odoo'] ?? null) ? $existing['odoo'] : []
            );
            return $apiSource;
        }

        return $this->mapApiSourceSecrets($apiSource, static function (string $key, string $value, array $context) use ($existing): string {
            if ($value !== '********' || !self::isSensitiveApiHeaderName($key)) {
                return $value;
            }
            if (($context['endpoint'] ?? null) !== null) {
                return (string) ($existing[$context['group']][$context['endpoint']][$key] ?? '');
            }
            return (string) ($existing[$context['group']][$key] ?? '');
        });
    }

    private function mapApiSourceSecrets(array $apiSource, callable $resolver): array
    {
        $groups = [
            ['group' => 'authHeaders', 'endpoint' => null],
            ['group' => 'listEndpoint', 'endpoint' => 'headers'],
            ['group' => 'detailEndpoint', 'endpoint' => 'headers'],
        ];
        foreach ($groups as $group) {
            if ($group['endpoint'] === null) {
                $map = is_array($apiSource[$group['group']] ?? null) ? $apiSource[$group['group']] : [];
                foreach ($map as $key => $value) {
                    $apiSource[$group['group']][$key] = $resolver((string) $key, (string) $value, $group);
                }
                continue;
            }
            $map = is_array($apiSource[$group['group']][$group['endpoint']] ?? null) ? $apiSource[$group['group']][$group['endpoint']] : [];
            foreach ($map as $key => $value) {
                $apiSource[$group['group']][$group['endpoint']][$key] = $resolver((string) $key, (string) $value, $group);
            }
        }

        return $apiSource;
    }

    private static function isSensitiveApiHeaderName(string $name): bool
    {
        return (bool) preg_match('/(authorization|token|api[-_]?key|secret)/i', $name);
    }

    private function maskApiHeaderSecrets(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $key => $value) {
            $normalized[(string) $key] = self::isSensitiveApiHeaderName((string) $key) && (string) $value !== '' ? '********' : (string) $value;
        }

        return $normalized;
    }

    private function restoreMaskedApiHeaderSecrets(array $headers, array $existing): array
    {
        $normalized = [];
        foreach ($headers as $key => $value) {
            $name = (string) $key;
            $text = (string) $value;
            $normalized[$name] = ($text === '********' && self::isSensitiveApiHeaderName($name)) ? (string) ($existing[$name] ?? '') : $text;
        }

        return $normalized;
    }

    private function maskOdooSourceSecrets(array $config): array
    {
        if (($config['secretValue'] ?? '') !== '') {
            $config['secretValue'] = '********';
        }

        return $config;
    }

    private function restoreMaskedOdooSourceSecrets(array $config, array $existing): array
    {
        if (($config['secretValue'] ?? '') === '********') {
            $config['secretValue'] = (string) ($existing['secretValue'] ?? '');
        }

        return $config;
    }

    private function maskApiOperationSecrets(array $operations): array
    {
        return array_map(function ($operation) {
            if (!is_array($operation)) {
                return $operation;
            }
            $copy = $operation;
            $copy['headers'] = $this->maskApiHeaderSecrets(is_array($copy['headers'] ?? null) ? $copy['headers'] : []);
            return $copy;
        }, $operations);
    }

    private function restoreMaskedApiOperationSecrets(array $operations, array $existing): array
    {
        $existingByCode = [];
        foreach ($existing as $item) {
            if (is_array($item) && !empty($item['code'])) {
                $existingByCode[(string) $item['code']] = $item;
            }
        }

        return array_map(function ($operation) use ($existingByCode) {
            if (!is_array($operation)) {
                return $operation;
            }
            $copy = $operation;
            $current = $existingByCode[(string) ($copy['code'] ?? '')] ?? [];
            $copy['headers'] = $this->restoreMaskedApiHeaderSecrets(
                is_array($copy['headers'] ?? null) ? $copy['headers'] : [],
                is_array($current['headers'] ?? null) ? $current['headers'] : []
            );
            return $copy;
        }, $operations);
    }

    private function fetchRemoteApiDocument(string $url): string
    {
        $resolved = $this->normalizeApiUrl($url, true);
        $ch = curl_init($resolved);
        if ($ch === false) {
            throw new RuntimeHttpException('API_OPENAPI_REQUEST_FAILED', 'Nao foi possivel iniciar a leitura do documento OpenAPI.', 422);
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Accept: application/json, application/yaml, text/yaml, text/plain, */*'],
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status >= 400 || $error !== '') {
            throw new RuntimeHttpException('API_OPENAPI_REQUEST_FAILED', 'Falha ao carregar o documento OpenAPI.', 422, [
                'url' => $resolved,
                'status' => $status,
                'curlError' => $error,
            ]);
        }

        return (string) $body;
    }

    private function parseOpenApiDocument(string $document, string $url): array
    {
        $trimmed = trim(preg_replace('/^\xEF\xBB\xBF/', '', $document) ?? $document);
        if ($trimmed === '') {
            throw new RuntimeHttpException('API_OPENAPI_EMPTY', 'Documento OpenAPI vazio.', 422, ['url' => $url]);
        }
        $json = json_decode($trimmed, true);
        if (is_array($json)) {
            return $json;
        }
        try {
            $yaml = Yaml::parse($trimmed);
        } catch (ParseException) {
            throw new RuntimeHttpException('API_OPENAPI_PARSE_FAILED', 'Documento OpenAPI invalido.', 422, ['url' => $url]);
        }
        if (!is_array($yaml)) {
            throw new RuntimeHttpException('API_OPENAPI_PARSE_FAILED', 'Documento OpenAPI invalido.', 422, ['url' => $url]);
        }

        return $yaml;
    }

    private function extractOpenApiBaseUrl(array $document): ?string
    {
        $servers = is_array($document['servers'] ?? null) ? $document['servers'] : [];
        foreach ($servers as $server) {
            $url = trim((string) ($server['url'] ?? ''));
            if ($url !== '') {
                return $url;
            }
        }

        return null;
    }

    private function extractOpenApiOperations(array $document, string $baseUrl): array
    {
        $operations = [];
        $paths = is_array($document['paths'] ?? null) ? $document['paths'] : [];
        foreach ($paths as $path => $pathConfig) {
            if (!is_array($pathConfig)) {
                continue;
            }
            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                if (!is_array($pathConfig[$method] ?? null)) {
                    continue;
                }
                $operation = $pathConfig[$method];
                $type = $this->inferOpenApiOperationType($method, (string) $path);
                $schema = $this->extractOpenApiResponseSchema($operation);
                $operationCode = $this->safeCode((string) ($operation['operationId'] ?? ($method . '_' . $this->slugToCode((string) $path))));
                $itemsPath = $type === 'list' ? ($this->guessOpenApiArrayPath($schema) ?? '$') : null;
                $itemPath = $type === 'detail' ? ($this->guessOpenApiObjectPath($schema) ?? '$') : null;
                $totalPath = $type === 'list' && is_array($schema['properties']['total'] ?? null) ? 'total' : null;
                $operations[] = [
                    'code' => $operationCode,
                    'name' => trim((string) ($operation['summary'] ?? $operation['operationId'] ?? strtoupper($method) . ' ' . $path)),
                    'type' => $type,
                    'method' => strtoupper($method),
                    'path' => $this->normalizeApiUrl((string) $path, true, $baseUrl),
                    'headers' => [],
                    'queryParams' => $this->extractOpenApiStaticQueryParams($operation, $pathConfig),
                    'bodyTemplate' => null,
                    'itemsPath' => $itemsPath,
                    'itemPath' => $itemPath,
                    'totalPath' => $totalPath,
                ];
            }
        }

        return $operations;
    }

    private function inferOpenApiOperationType(string $method, string $path): string
    {
        $hasPathParam = (bool) preg_match('/\{[^}]+\}/', $path);
        return match (strtolower($method)) {
            'get' => $hasPathParam ? 'detail' : 'list',
            'post' => $hasPathParam ? 'custom' : 'create',
            'put', 'patch' => 'update',
            'delete' => 'delete',
            default => 'custom',
        };
    }

    private function extractOpenApiResponseSchema(array $operation): array
    {
        $responses = is_array($operation['responses'] ?? null) ? $operation['responses'] : [];
        foreach (['200', '201', 'default'] as $status) {
            $response = is_array($responses[$status] ?? null) ? $responses[$status] : null;
            if (!$response) {
                continue;
            }
            $content = is_array($response['content'] ?? null) ? $response['content'] : [];
            foreach (['application/json', 'application/*+json', '*/*'] as $contentType) {
                $entry = is_array($content[$contentType] ?? null) ? $content[$contentType] : null;
                if ($entry && is_array($entry['schema'] ?? null)) {
                    return $entry['schema'];
                }
            }
            foreach ($content as $entry) {
                if (is_array($entry) && is_array($entry['schema'] ?? null)) {
                    return $entry['schema'];
                }
            }
        }

        return [];
    }

    private function guessOpenApiArrayPath(array $schema): ?string
    {
        if (($schema['type'] ?? null) === 'array') {
            return '$';
        }
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        foreach (['data', 'items', 'results'] as $name) {
            if (($properties[$name]['type'] ?? null) === 'array') {
                return $name;
            }
        }

        return null;
    }

    private function guessOpenApiObjectPath(array $schema): ?string
    {
        if (($schema['type'] ?? null) === 'object' || !empty($schema['properties'])) {
            $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
            foreach (['data', 'item'] as $name) {
                if (($properties[$name]['type'] ?? null) === 'object' || !empty($properties[$name]['properties'])) {
                    return $name;
                }
            }
            return '$';
        }

        return null;
    }

    private function extractOpenApiStaticQueryParams(array $operation, array $pathConfig): array
    {
        $params = [];
        foreach ([is_array($pathConfig['parameters'] ?? null) ? $pathConfig['parameters'] : [], is_array($operation['parameters'] ?? null) ? $operation['parameters'] : []] as $list) {
            foreach ($list as $parameter) {
                if (!is_array($parameter) || (string) ($parameter['in'] ?? '') !== 'query') {
                    continue;
                }
                $name = trim((string) ($parameter['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $default = $parameter['schema']['default'] ?? $parameter['example'] ?? '';
                $params[$name] = is_scalar($default) ? (string) $default : '';
            }
        }

        return $params;
    }

    private function isApiEntity(mixed $entity): bool
    {
        return $entity instanceof BuilderEntity && $entity->getEntityType() === 'api';
    }

    private function isApiEntityVersion(BuilderProgramVersion $version): bool
    {
        $entityCode = trim((string) $version->getBuilderEntityCode());
        if ($entityCode === '') {
            return false;
        }
        $entity = $this->entities->findOneBy(['code' => $entityCode]);

        return $entity instanceof BuilderEntity && $entity->getEntityType() === 'api';
    }

    private function isOdooApiEntityVersion(BuilderProgramVersion $version): bool
    {
        $entityCode = trim((string) $version->getBuilderEntityCode());
        if ($entityCode === '') {
            return false;
        }
        $entity = $this->entities->findOneBy(['code' => $entityCode]);

        return $this->isOdooApiEntity($entity);
    }

    private function apiEntitySupportsOperation(mixed $entity, string $operation): bool
    {
        if (!$entity instanceof BuilderEntity || $entity->getEntityType() !== 'api') {
            return false;
        }
        if ($this->isOdooApiEntity($entity)) {
            return false;
        }
        $apiSource = is_array($entity->getMetadata()['apiSource'] ?? null) ? $entity->getMetadata()['apiSource'] : [];

        return !empty($apiSource[$operation . 'Endpoint']['url']);
    }

    private function apiEntityHasDetailEndpoint(mixed $entity): bool
    {
        if (!$entity instanceof BuilderEntity) {
            return false;
        }
        if ($this->isOdooApiEntity($entity)) {
            return true;
        }
        $apiSource = is_array($entity->getMetadata()['apiSource'] ?? null) ? $entity->getMetadata()['apiSource'] : [];

        return !empty($apiSource['detailEndpoint']['url']);
    }

    private function isOdooApiEntity(mixed $entity): bool
    {
        if (!$entity instanceof BuilderEntity || $entity->getEntityType() !== 'api') {
            return false;
        }

        return (string) (($entity->getMetadata()['apiSource']['providerType'] ?? 'generic')) === 'odoo';
    }

    private function normalizeFieldLength(mixed $value, string $dataType): ?int
    {
        if ($value === null || $value === '') {
            return in_array($dataType, ['string', 'email', 'enum', 'dropdown', 'custom_code'], true) ? 160 : null;
        }

        $length = (int) $value;
        return $length > 0 ? $length : null;
    }

    private function normalizePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (int) $value;
        return $number > 0 ? $number : null;
    }

    private function normalizeDefaultValue(mixed $value, string $dataType): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($dataType) {
            'integer' => (int) $value,
            'decimal' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
            'json' => is_string($value) ? trim($value) : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => trim((string) $value),
        };
    }

    private function normalizeOptionItems(mixed $value, string $dataType): array
    {
        if (!in_array($dataType, ['enum', 'dropdown'], true) || !is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $optionValue = array_key_exists('value', $item) ? (string) $item['value'] : '';
            $optionText = array_key_exists('text', $item) ? trim((string) $item['text']) : '';
            if ($optionValue === '' && $optionText === '') {
                continue;
            }
            $items[] = [
                'value' => $optionValue,
                'text' => $optionText !== '' ? $optionText : $optionValue,
            ];
        }

        return $items;
    }

    private function normalizeCustomCodePromptFields(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $fields = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = $this->safeSqlIdentifier((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $type = strtolower(trim((string) ($item['type'] ?? 'string')));
            if (!in_array($type, ['string', 'integer', 'decimal', 'boolean', 'enum', 'dropdown'], true)) {
                $type = 'string';
            }
            $field = [
                'name' => $name,
                'label' => trim((string) ($item['label'] ?? $name)),
                'type' => $type,
                'required' => ($item['required'] ?? false) === true,
                'options' => [],
            ];
            if (in_array($type, ['enum', 'dropdown'], true)) {
                $field['options'] = $this->normalizeOptionItems($item['options'] ?? null, 'dropdown');
            }
            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function entityUniqueKeysPayload(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $items[] = [
                'name' => (string) ($item['name'] ?? ('uk_' . ($index + 1))),
                'fields' => array_values(array_filter(array_map('strval', is_array($item['fields'] ?? null) ? $item['fields'] : []))),
            ];
        }

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @return list<array{name: string, fields: list<string>}>
     */
    private function normalizeEntityUniqueKeys(mixed $value, array $fields): array
    {
        if (!is_array($value)) {
            return [];
        }

        $fieldMap = [];
        foreach ($fields as $field) {
            if (!is_array($field) || empty($field['code'])) {
                continue;
            }
            $fieldMap[(string) $field['code']] = $field;
        }

        $keys = [];
        foreach ($value as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = $this->safeSqlIdentifier((string) ($item['name'] ?? ''));
            if ($name === '') {
                $name = 'uk' . ($index + 1);
            }
            $members = [];
            foreach ((is_array($item['fields'] ?? null) ? $item['fields'] : []) as $fieldCode) {
                $fieldCode = $this->safeSqlIdentifier((string) $fieldCode);
                if ($fieldCode === '' || !isset($fieldMap[$fieldCode])) {
                    continue;
                }
                $members[] = $fieldCode;
            }
            $members = array_values(array_unique($members));
            if (count($members) < 2) {
                continue;
            }
            $keys[] = [
                'name' => $name,
                'fields' => $members,
            ];
        }

        return $keys;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function entityRulesPayload(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $rules = [];
        foreach ($value as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $type = strtolower(trim((string) ($item['type'] ?? 'requiredWhen')));
            if ($type === 'requiredwhen') {
                $type = 'requiredWhen';
            }
            if (!in_array($type, ['requiredWhen', 'class_method'], true)) {
                continue;
            }
            $when = is_array($item['when'] ?? null) ? $item['when'] : [];
            $rules[] = [
                'id' => (string) ($item['id'] ?? ('regra-' . ($index + 1))),
                'label' => (string) ($item['label'] ?? $item['message'] ?? ('Regra ' . ($index + 1))),
                'ruleType' => $type,
                'phase' => (string) ($item['phase'] ?? 'beforeValidate'),
                'order' => (int) ($item['order'] ?? (($index + 1) * 10)),
                'enabled' => ($item['enabled'] ?? true) !== false,
                'continueOnError' => ($item['continueOnError'] ?? false) === true,
                'field' => (string) ($item['field'] ?? ''),
                'whenField' => (string) ($when['field'] ?? ''),
                'whenEquals' => $when['equals'] ?? null,
                'message' => (string) ($item['message'] ?? ''),
                'className' => (string) ($item['className'] ?? $item['class'] ?? ''),
                'methodName' => (string) ($item['methodName'] ?? $item['method'] ?? ''),
                'params' => is_array($item['params'] ?? null) ? $item['params'] : [],
            ];
        }

        return $rules;
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @return list<array<string, mixed>>
     */
    private function normalizeEntityRules(mixed $value, array $fields): array
    {
        if (!is_array($value)) {
            return [];
        }

        $fieldMap = [];
        foreach ($fields as $field) {
            if (!is_array($field) || empty($field['code'])) {
                continue;
            }
            $fieldMap[(string) $field['code']] = true;
        }

        $rules = [];
        foreach ($value as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $type = strtolower(trim((string) ($item['ruleType'] ?? $item['type'] ?? 'requiredWhen')));
            if ($type === 'requiredwhen') {
                $type = 'requiredWhen';
            }
            if (!in_array($type, ['requiredWhen', 'class_method'], true)) {
                continue;
            }

            $phase = trim((string) ($item['phase'] ?? 'beforeValidate'));
            if (!in_array($phase, ['beforeValidate', 'beforePersist', 'afterPersist', 'afterCommit'], true)) {
                $phase = 'beforeValidate';
            }

            $rule = [
                'id' => $this->safeCode((string) ($item['id'] ?? ('regra-' . ($index + 1)))),
                'label' => trim((string) ($item['label'] ?? $item['message'] ?? ('Regra ' . ($index + 1)))),
                'type' => $type,
                'phase' => $phase,
                'order' => max(0, (int) ($item['order'] ?? (($index + 1) * 10))),
                'enabled' => ($item['enabled'] ?? true) !== false,
                'continueOnError' => ($item['continueOnError'] ?? false) === true,
                'params' => is_array($item['params'] ?? null) ? $item['params'] : [],
            ];

            if ($type === 'requiredWhen') {
                $field = $this->safeSqlIdentifier((string) ($item['field'] ?? ''));
                $whenField = $this->safeSqlIdentifier((string) ($item['whenField'] ?? (($item['when']['field'] ?? ''))));
                if ($field === '' || !isset($fieldMap[$field])) {
                    throw new RuntimeHttpException('ENTITY_RULE_FIELD_INVALID', 'A regra declarativa precisa apontar para um campo valido da entidade.', 422, [
                        'ruleId' => $rule['id'],
                        'field' => $field,
                    ]);
                }
                if ($whenField === '' || !isset($fieldMap[$whenField])) {
                    throw new RuntimeHttpException('ENTITY_RULE_WHEN_FIELD_INVALID', 'A regra declarativa precisa apontar para um campo gatilho valido.', 422, [
                        'ruleId' => $rule['id'],
                        'whenField' => $whenField,
                    ]);
                }
                $rule['field'] = $field;
                $rule['when'] = [
                    'field' => $whenField,
                    'equals' => $item['whenEquals'] ?? ($item['when']['equals'] ?? null),
                ];
                $rule['message'] = trim((string) ($item['message'] ?? ''));
            } else {
                $className = $this->safePhpRuleClassName((string) ($item['className'] ?? $item['class'] ?? ''));
                $methodName = $this->safePhpMethodName((string) ($item['methodName'] ?? $item['method'] ?? ''));
                if ($className === '' || $methodName === '') {
                    throw new RuntimeHttpException('ENTITY_RULE_CLASS_METHOD_REQUIRED', 'Informe classe e metodo validos para a regra de classe.', 422, [
                        'ruleId' => $rule['id'],
                    ]);
                }
                $rule['className'] = $className;
                $rule['methodName'] = $methodName;
                if (trim((string) ($item['message'] ?? '')) !== '') {
                    $rule['message'] = trim((string) $item['message']);
                }
            }

            $rules[] = $rule;
        }

        usort($rules, static function (array $left, array $right): int {
            $order = (int) ($left['order'] ?? 0) <=> (int) ($right['order'] ?? 0);
            if ($order !== 0) {
                return $order;
            }

            return strcmp((string) ($left['id'] ?? ''), (string) ($right['id'] ?? ''));
        });

        return $rules;
    }

    private function safePhpClassName(string $value): string
    {
        $value = trim($value);
        return preg_match('/^App\\\\[A-Za-z0-9_\\\\]+$/', $value) ? $value : '';
    }

    private function safePhpRuleClassName(string $value): string
    {
        $value = trim($value);
        return preg_match('/^App\\\\Runtime\\\\BusinessRule\\\\[A-Za-z0-9_\\\\]+$/', $value) ? $value : '';
    }

    private function safePhpMethodName(string $value): string
    {
        $value = trim($value);
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) ? $value : '';
    }

    private function safeCode(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9._-]+/', '-', $value) ?: '';
        return trim($value, '-');
    }

    private function slugToCode(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
        return trim($value, '-');
    }

    private function safePermissionPrefix(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9.*_-]+/', '-', $value) ?: '';
        return trim($value, '-');
    }

    private function safeScreenId(string $value): string
    {
        $value = strtolower(trim($value));
        return preg_match('/^[a-z0-9._-]+$/', $value) ? $value : '';
    }

    private function normalizeForeignKeyAction(mixed $value): ?string
    {
        $value = strtolower(trim((string) $value));
        return in_array($value, ['cascade', 'restrict', 'set_null', 'set_default', 'no_action'], true) ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeEntityStructure(
        string $moduleCode,
        string $structureType,
        ?int $baseNumber,
        ?int $sequenceNumber,
        string $parentEntityCode,
        string $leftEntityCode,
        string $rightEntityCode,
        string $entityType,
    ): array {
        $allowedTypes = ['main', 'composition', 'specific_relation', 'aggregation', 'recursive', 'multi_level', 'view'];
        if (!in_array($structureType, $allowedTypes, true)) {
            $structureType = $entityType === 'query' ? 'view' : 'main';
        }
        if ($entityType === 'query') {
            $structureType = 'view';
        }

        $module = $moduleCode !== '' ? $this->modules->findOneBy(['code' => $moduleCode]) : null;
        if (in_array($structureType, ['main', 'view'], true)) {
            if (!$module) {
                throw new RuntimeHttpException('ENTITY_STRUCTURE_MODULE_REQUIRED', 'Selecione um modulo para a numeracao estrutural.', 422, [
                    'structureType' => $structureType,
                ]);
            }
            if ($baseNumber === null) {
                throw new RuntimeHttpException('ENTITY_STRUCTURE_BASE_NUMBER_REQUIRED', 'Informe o numero base da entidade.', 422, [
                    'structureType' => $structureType,
                ]);
            }
            if ($baseNumber < $module->getNumberStart() || $baseNumber > $module->getNumberEnd()) {
                throw new RuntimeHttpException('ENTITY_STRUCTURE_BASE_NUMBER_OUT_OF_RANGE', 'O numero base da entidade esta fora da faixa do modulo.', 422, [
                    'moduleCode' => $moduleCode,
                    'range' => [$module->getNumberStart(), $module->getNumberEnd()],
                    'baseNumber' => $baseNumber,
                ]);
            }
        }

        if (in_array($structureType, ['composition', 'specific_relation'], true) && ($parentEntityCode === '' || $sequenceNumber === null)) {
            throw new RuntimeHttpException('ENTITY_STRUCTURE_PARENT_REQUIRED', 'Informe a entidade pai e a sequencia estrutural.', 422, [
                'structureType' => $structureType,
            ]);
        }
        if (in_array($structureType, ['recursive', 'multi_level'], true) && $parentEntityCode === '') {
            throw new RuntimeHttpException('ENTITY_STRUCTURE_PARENT_REQUIRED', 'Informe a entidade base da estrutura.', 422, [
                'structureType' => $structureType,
            ]);
        }
        if ($structureType === 'aggregation' && ($leftEntityCode === '' || $rightEntityCode === '')) {
            throw new RuntimeHttpException('ENTITY_STRUCTURE_AGGREGATION_REQUIRED', 'Informe as duas entidades da agregacao.', 422);
        }

        return [
            'moduleCode' => $moduleCode,
            'type' => $structureType,
            'baseNumber' => $baseNumber,
            'sequenceNumber' => $sequenceNumber,
            'parentEntityCode' => $parentEntityCode,
            'leftEntityCode' => $leftEntityCode,
            'rightEntityCode' => $rightEntityCode,
        ];
    }

    private function normalizeDependencyType(mixed $value): ?string
    {
        $value = strtolower(trim((string) $value));
        return in_array($value, ['reference', 'composition', 'aggregation', 'specific_relation', 'recursive', 'multi_level'], true) ? $value : null;
    }

    private function validateTableNamingPattern(string $tableName, string $entityType, string $originalTableName, array $structure = []): void
    {
        if ($tableName === $originalTableName && $originalTableName !== '') {
            return;
        }

        $expected = $this->suggestTableNameByStructure($structure);
        if ($expected !== null) {
            if ($tableName !== $expected) {
                throw new RuntimeHttpException(
                    'ENTITY_TABLE_NAME_PATTERN_INVALID',
                    'O nome da tabela nao segue o padrao estrutural definido.',
                    422,
                    [
                        'tableName' => $tableName,
                        'expected' => $expected,
                    ]
                );
            }
            return;
        }

        $valid = $entityType === 'query'
            ? (bool) preg_match('/^v\d+$/', $tableName)
            : (bool) preg_match('/^(t\d+|t\d+c\d+|t\d+e\d+|t\d+r|t\d+m|t\d+(?:e\d+)?at\d+(?:e\d+)?)$/', $tableName);
        if ($valid) {
            return;
        }

        throw new RuntimeHttpException(
            'ENTITY_TABLE_NAME_PATTERN_INVALID',
            'O nome da tabela nao segue o padrao definido.',
            422,
            [
                'tableName' => $tableName,
                'expectedExamples' => $entityType === 'query'
                    ? ['v1']
                    : ['t1', 't1c1', 't1e1', 't1r', 't1m', 't1e2at2e3'],
            ]
        );
    }

    private function suggestTableNameByStructure(array $structure): ?string
    {
        $type = (string) ($structure['type'] ?? '');
        return match ($type) {
            'main' => ($structure['baseNumber'] ?? null) ? 't' . (int) $structure['baseNumber'] : null,
            'view' => ($structure['baseNumber'] ?? null) ? 'v' . (int) $structure['baseNumber'] : null,
            'composition' => $this->suggestFromParentStructure((string) ($structure['parentEntityCode'] ?? ''), 'c', (int) ($structure['sequenceNumber'] ?? 0)),
            'specific_relation' => $this->suggestFromParentStructure((string) ($structure['parentEntityCode'] ?? ''), 'e', (int) ($structure['sequenceNumber'] ?? 0)),
            'recursive' => $this->suggestFromParentSuffix((string) ($structure['parentEntityCode'] ?? ''), 'r'),
            'multi_level' => $this->suggestFromParentSuffix((string) ($structure['parentEntityCode'] ?? ''), 'm'),
            'aggregation' => $this->suggestAggregationTableName((string) ($structure['leftEntityCode'] ?? ''), (string) ($structure['rightEntityCode'] ?? '')),
            default => null,
        };
    }

    private function suggestFromParentStructure(string $parentEntityCode, string $suffix, int $sequence): ?string
    {
        if ($parentEntityCode === '' || $sequence <= 0) {
            return null;
        }
        $parent = $this->entities->findOneBy(['code' => $parentEntityCode]);
        $parentTable = $parent?->getTableName();
        if (!$parentTable || !preg_match('/^t\d+(?:e\d+)?$/', $parentTable)) {
            return null;
        }

        return $parentTable . $suffix . $sequence;
    }

    private function suggestFromParentSuffix(string $parentEntityCode, string $suffix): ?string
    {
        if ($parentEntityCode === '') {
            return null;
        }
        $parent = $this->entities->findOneBy(['code' => $parentEntityCode]);
        $parentTable = $parent?->getTableName();
        if (!$parentTable || !preg_match('/^t\d+$/', $parentTable)) {
            return null;
        }

        return $parentTable . $suffix;
    }

    private function suggestAggregationTableName(string $leftEntityCode, string $rightEntityCode): ?string
    {
        if ($leftEntityCode === '' || $rightEntityCode === '') {
            return null;
        }
        $left = $this->entities->findOneBy(['code' => $leftEntityCode]);
        $right = $this->entities->findOneBy(['code' => $rightEntityCode]);
        $leftTable = $left?->getTableName();
        $rightTable = $right?->getTableName();
        if (!$leftTable || !$rightTable || !preg_match('/^t\d+(?:e\d+)?$/', $leftTable) || !preg_match('/^t\d+(?:e\d+)?$/', $rightTable)) {
            return null;
        }

        return $leftTable . 'a' . $rightTable;
    }

    private function validateFieldNamingPattern(array $field, string $dataType, bool $isForeignKey): void
    {
        $code = (string) ($field['columnName'] ?? '');
        $originalCode = (string) ($field['originalColumnName'] ?? $code);
        if ($code === $originalCode && $originalCode !== '') {
            return;
        }

        if (($field['primaryKey'] ?? false) === true && ($code === 'id' || str_starts_with($code, 'id_'))) {
            return;
        }

        if ($isForeignKey) {
            if (!str_ends_with($code, '_id')) {
                throw new RuntimeHttpException('ENTITY_FIELD_NAME_PATTERN_INVALID', 'Campo chave estrangeira precisa terminar com _id.', 422, [
                    'field' => $code,
                ]);
            }
            return;
        }

        $uniqueLike = ($field['options']['unique'] ?? false) === true;
        if ($uniqueLike && preg_match('/^(u_|id_)/', $code)) {
            return;
        }

        $valid = match ($dataType) {
            'date' => str_starts_with($code, 'dt_'),
            'datetime' => str_starts_with($code, 'dt_hr_') || str_starts_with($code, 'dt_hr_tz_'),
            'integer' => preg_match('/^(si_|i_|bi_)/', $code) === 1,
            'boolean' => str_starts_with($code, 'log_'),
            'text' => str_starts_with($code, 't_'),
            'decimal' => str_starts_with($code, 'd_'),
            'string', 'email', 'enum', 'dropdown', 'custom_code' => str_starts_with($code, 'c_') || str_starts_with($code, '_'),
            default => true,
        };

        if ($valid) {
            $this->validateFieldSemanticTokens($code, $dataType, $isForeignKey, ($field['options']['unique'] ?? false) === true);
            return;
        }

        throw new RuntimeHttpException('ENTITY_FIELD_NAME_PATTERN_INVALID', 'O nome do campo nao segue o padrao definido.', 422, [
            'field' => $code,
            'dataType' => $dataType,
        ]);
    }

    private function validateFieldSemanticTokens(string $columnName, string $dataType, bool $isForeignKey, bool $uniqueLike): void
    {
        if ($columnName === '' || str_starts_with($columnName, '_')) {
            return;
        }

        $tokens = explode('_', $columnName);
        if (!$tokens) {
            return;
        }

        if ($isForeignKey && end($tokens) === 'id') {
            array_pop($tokens);
        }
        if ($uniqueLike && $tokens && in_array($tokens[0], ['u', 'id'], true)) {
            array_shift($tokens);
        }

        $prefixesToDrop = match ($dataType) {
            'date' => ['dt'],
            'datetime' => ['dt', 'hr'],
            'integer' => ['si', 'i', 'bi'],
            'boolean' => ['log'],
            'text' => ['t'],
            'decimal' => ['d'],
            'string', 'email', 'enum', 'dropdown', 'custom_code' => ['c'],
            default => [],
        };

        foreach ($prefixesToDrop as $expected) {
            if ($tokens && $tokens[0] === $expected) {
                array_shift($tokens);
            }
        }

        $invalid = [];
        foreach ($tokens as $token) {
            if ($token === '' || ctype_digit($token)) {
                continue;
            }
            if (isset(self::FIELD_ABBREVIATIONS[$token])) {
                continue;
            }
            $invalid[] = $token;
        }

        if ($invalid) {
            throw new RuntimeHttpException('ENTITY_FIELD_ABBREVIATION_INVALID', 'O nome do campo usa abreviacoes fora do padrao definido.', 422, [
                'field' => $columnName,
                'invalidTokens' => array_values(array_unique($invalid)),
                'allowedExamples' => ['dt_hr_ini', 'si_sit_nf', 'c_nome', 'd_vl_bruto', 'cli_id'],
            ]);
        }
    }

    private function validateUniqueKeyFieldPrefixes(array $uniqueKeys, array $fields): void
    {
        if (!$uniqueKeys) {
            return;
        }

        $fieldMap = [];
        foreach ($fields as $field) {
            if (!is_array($field) || empty($field['code'])) {
                continue;
            }
            $fieldMap[(string) $field['code']] = $field;
        }

        foreach ($uniqueKeys as $key) {
            foreach (($key['fields'] ?? []) as $fieldCode) {
                $field = $fieldMap[$fieldCode] ?? null;
                if (!$field) {
                    continue;
                }
                $code = (string) ($field['columnName'] ?? '');
                $originalCode = (string) ($field['originalColumnName'] ?? $code);
                if ($code === $originalCode && $originalCode !== '') {
                    continue;
                }
                if (preg_match('/^(u_|id_)/', $code) === 1) {
                    continue;
                }
                throw new RuntimeHttpException('ENTITY_FIELD_NAME_PATTERN_INVALID', 'Campo participante de chave unica deve comecar com u_ ou id_.', 422, [
                    'field' => $code,
                    'uniqueKey' => $key['name'] ?? null,
                ]);
            }
        }
    }

    private function nextVersion(string $version): string
    {
        $parts = array_map('intval', explode('.', $version));
        while (count($parts) < 3) {
            $parts[] = 0;
        }
        ++$parts[2];
        return implode('.', array_slice($parts, 0, 3));
    }

    private function findByProgramCodeAndVersion(string $programCode, string $version): ?BuilderProgramVersion
    {
        return $this->findOneByProgramCodeVersion($programCode, $version);
    }

    private function findOneByProgramCodeVersion(string $programCode, string $version): ?BuilderProgramVersion
    {
        return $this->versions->findOneBy([
            'programCode' => $programCode,
            'version' => $version,
        ]);
    }

    private function synchronizePhysicalTable(
        BuilderEntity $entity,
        array $fields,
        array $uniqueKeys,
        bool $createPhysicalTable,
        ?string $originalTableName = null,
        array $existingSnapshots = [],
        array $removedColumns = [],
        bool $allowTableRename = true,
        bool $allowColumnRename = true,
        bool $dropRemovedColumns = false
    ): void
    {
        if (!$createPhysicalTable) {
            return;
        }

        $physicalFields = array_values(array_filter($fields, static fn (array $field): bool => ($field['virtualField'] ?? false) !== true));

        $connection = $this->entityManager->getConnection();
        $tableName = (string) $entity->getTableName();
        $schemaManager = $connection->createSchemaManager();
        $tableNames = $schemaManager->listTableNames();
        $tableExists = in_array($tableName, $tableNames, true);
        if (!$tableExists && $allowTableRename && $originalTableName && $originalTableName !== $tableName && in_array($originalTableName, $tableNames, true)) {
            foreach ($existingSnapshots as $snapshot) {
                $this->dropManagedConstraints($connection, $originalTableName, (string) ($snapshot['columnName'] ?? ''));
            }
            $connection->executeStatement(
                'ALTER TABLE ' . $connection->quoteSingleIdentifier($originalTableName)
                . ' RENAME TO ' . $connection->quoteSingleIdentifier($tableName)
            );
            $tableExists = true;
        }

        if (!$tableExists) {
            $columnsSql = [];
            $primaryColumns = [];
            foreach ($physicalFields as $field) {
                $columnsSql[] = $this->buildCreateColumnSql($connection, $field, false);
                if ($field['primaryKey']) {
                    $primaryColumns[] = $connection->quoteSingleIdentifier($field['columnName']);
                }
            }
            if (!isset($this->fieldMapByColumn($physicalFields)['created_at'])) {
                $columnsSql[] = $connection->quoteSingleIdentifier('created_at') . ' TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL';
            }
            if (!isset($this->fieldMapByColumn($physicalFields)['updated_at'])) {
                $columnsSql[] = $connection->quoteSingleIdentifier('updated_at') . ' TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL';
            }
            $columnsSql[] = 'PRIMARY KEY (' . implode(', ', $primaryColumns) . ')';
            $sql = 'CREATE TABLE ' . $connection->quoteSingleIdentifier($tableName) . ' (' . implode(', ', $columnsSql) . ')';
            $connection->executeStatement($sql);
            foreach ($physicalFields as $field) {
                $this->ensureColumnConstraints($connection, $tableName, $field, false);
            }
            $this->ensureUniqueKeyConstraints($connection, $tableName, $physicalFields, $uniqueKeys);
            return;
        }

        $existingColumns = [];
        foreach ($schemaManager->listTableColumns($tableName) as $name => $column) {
            $existingColumns[(string) $name] = true;
        }

        foreach ($physicalFields as $field) {
            $oldColumn = (string) ($field['originalColumnName'] ?? $field['columnName']);
            $newColumn = (string) $field['columnName'];
            if (
                $allowColumnRename
                && $oldColumn !== ''
                && $newColumn !== ''
                && $oldColumn !== $newColumn
                && isset($existingColumns[$oldColumn])
                && !isset($existingColumns[$newColumn])
            ) {
                $this->dropManagedConstraints($connection, $tableName, $oldColumn);
                $connection->executeStatement(
                    'ALTER TABLE ' . $connection->quoteSingleIdentifier($tableName)
                    . ' RENAME COLUMN ' . $connection->quoteSingleIdentifier($oldColumn)
                    . ' TO ' . $connection->quoteSingleIdentifier($newColumn)
                );
                unset($existingColumns[$oldColumn]);
                $existingColumns[$newColumn] = true;
            }

            if (!isset($existingColumns[$newColumn])) {
                $sql = 'ALTER TABLE ' . $connection->quoteSingleIdentifier($tableName) . ' ADD COLUMN ' . $this->buildCreateColumnSql($connection, $field, false);
                $connection->executeStatement($sql);
                $this->ensureColumnConstraints($connection, $tableName, $field, true);
                $existingColumns[$newColumn] = true;
                continue;
            }

            $this->syncExistingColumnDefinition($connection, $tableName, $field);
            $this->ensureColumnConstraints($connection, $tableName, $field, true);
        }

        $this->ensureUniqueKeyConstraints($connection, $tableName, $physicalFields, $uniqueKeys);

        if ($dropRemovedColumns) {
            $desiredColumns = $this->fieldMapByColumn($physicalFields);
            $currentColumns = [];
            foreach ($connection->createSchemaManager()->listTableColumns($tableName) as $name => $column) {
                $currentColumns[(string) $name] = true;
            }
            foreach (array_keys($currentColumns) as $columnName) {
                if (isset($desiredColumns[$columnName]) || in_array($columnName, ['created_at', 'updated_at'], true)) {
                    continue;
                }
                $this->dropManagedConstraints($connection, $tableName, $columnName);
                $connection->executeStatement(
                    'ALTER TABLE ' . $connection->quoteSingleIdentifier($tableName)
                    . ' DROP COLUMN ' . $connection->quoteSingleIdentifier($columnName)
                );
            }
        }
    }

    private function buildCreateColumnSql(Connection $connection, array $field, bool $withPrimaryKey = true): string
    {
        $sql = $connection->quoteSingleIdentifier($field['columnName']) . ' ' . $this->columnSqlType($field);
        if ($field['required']) {
            $sql .= ' NOT NULL';
        }
        $defaultClause = $this->columnDefaultSql($field);
        if ($defaultClause !== null) {
            $sql .= ' DEFAULT ' . $defaultClause;
        }
        if ($withPrimaryKey && $field['primaryKey']) {
            $sql .= ' PRIMARY KEY';
        }

        return $sql;
    }

    private function columnSqlType(array $field): string
    {
        return match ($field['dataType']) {
            'integer' => $field['primaryKey'] ? 'INT GENERATED BY DEFAULT AS IDENTITY' : 'INT',
            'decimal' => 'NUMERIC(' . (int) ($field['precision'] ?? 18) . ', ' . (int) ($field['scale'] ?? 2) . ')',
            'boolean' => 'BOOLEAN',
            'date' => 'DATE',
            'datetime' => 'TIMESTAMP(0) WITHOUT TIME ZONE',
            'text' => 'TEXT',
            'json' => 'JSON',
            default => 'VARCHAR(' . (int) ($field['length'] ?? 160) . ')',
        };
    }

    private function columnDefaultSql(array $field): ?string
    {
        $value = $field['options']['defaultValue'] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        return match ($field['dataType']) {
            'integer' => (string) (int) $value,
            'decimal' => (string) (float) $value,
            'boolean' => ((bool) $value) ? 'TRUE' : 'FALSE',
            'json' => "'" . str_replace("'", "''", (string) $value) . "'::json",
            default => "'" . str_replace("'", "''", (string) $value) . "'",
        };
    }

    private function ensureColumnConstraints(Connection $connection, string $tableName, array $field, bool $tableAlreadyExisted): void
    {
        $table = $connection->quoteSingleIdentifier($tableName);
        $column = $connection->quoteSingleIdentifier($field['columnName']);

        if (($field['options']['unique'] ?? false) === true) {
            $constraint = $this->constraintName('uniq', $tableName, $field['columnName']);
            $sql = 'ALTER TABLE ' . $table . ' ADD CONSTRAINT ' . $connection->quoteSingleIdentifier($constraint) . ' UNIQUE (' . $column . ')';
            $this->executeSchemaStatement($connection, $sql);
        }

        if (is_array($field['options']['foreignKey'] ?? null)) {
            $foreign = $field['options']['foreignKey'];
            $foreignTable = $this->safeSqlIdentifier((string) ($foreign['table'] ?? ''));
            $foreignColumn = $this->safeSqlIdentifier((string) ($foreign['column'] ?? ''));
            if ($foreignTable !== '' && $foreignColumn !== '') {
                $constraint = $this->constraintName('fk', $tableName, $field['columnName']);
                $sql = 'ALTER TABLE ' . $table
                    . ' ADD CONSTRAINT ' . $connection->quoteSingleIdentifier($constraint)
                    . ' FOREIGN KEY (' . $column . ') REFERENCES '
                    . $connection->quoteSingleIdentifier($foreignTable)
                    . ' (' . $connection->quoteSingleIdentifier($foreignColumn) . ')';
                if ($action = $this->foreignKeyActionSql($foreign['onUpdate'] ?? null)) {
                    $sql .= ' ON UPDATE ' . $action;
                }
                if ($action = $this->foreignKeyActionSql($foreign['onDelete'] ?? null)) {
                    $sql .= ' ON DELETE ' . $action;
                }
                $this->executeSchemaStatement($connection, $sql);
            }
        }
    }

    private function ensureUniqueKeyConstraints(Connection $connection, string $tableName, array $fields, array $uniqueKeys): void
    {
        $this->dropManagedUniqueConstraints($connection, $tableName);
        $table = $connection->quoteSingleIdentifier($tableName);
        $fieldMap = [];
        foreach ($fields as $field) {
            $fieldMap[(string) ($field['code'] ?? '')] = $field;
        }

        foreach ($fields as $field) {
            if (($field['options']['unique'] ?? false) !== true) {
                continue;
            }
            $constraint = $this->constraintName('uniq', $tableName, (string) $field['columnName']);
            $sql = 'ALTER TABLE ' . $table . ' ADD CONSTRAINT ' . $connection->quoteSingleIdentifier($constraint)
                . ' UNIQUE (' . $connection->quoteSingleIdentifier((string) $field['columnName']) . ')';
            $this->executeSchemaStatement($connection, $sql);
        }

        foreach ($uniqueKeys as $key) {
            $columns = [];
            foreach (($key['fields'] ?? []) as $code) {
                if (!isset($fieldMap[$code])) {
                    continue;
                }
                $columns[] = $connection->quoteSingleIdentifier((string) $fieldMap[$code]['columnName']);
            }
            if (count($columns) < 2) {
                continue;
            }
            $constraint = $this->constraintName('uniq', $tableName, (string) ($key['name'] ?? 'composta'));
            $sql = 'ALTER TABLE ' . $table . ' ADD CONSTRAINT ' . $connection->quoteSingleIdentifier($constraint)
                . ' UNIQUE (' . implode(', ', $columns) . ')';
            $this->executeSchemaStatement($connection, $sql);
        }
    }

    private function executeSchemaStatement(Connection $connection, string $sql): void
    {
        try {
            $connection->executeStatement($sql);
        } catch (\Throwable) {
        }
    }

    private function constraintName(string $prefix, string $tableName, string $columnName): string
    {
        return substr($prefix . '_' . $tableName . '_' . $columnName, 0, 60);
    }

    private function syncExistingColumnDefinition(Connection $connection, string $tableName, array $field): void
    {
        if (($field['primaryKey'] ?? false) === true) {
            return;
        }

        $table = $connection->quoteSingleIdentifier($tableName);
        $column = $connection->quoteSingleIdentifier($field['columnName']);
        $typeSql = $this->columnSqlType($field);
        $usingSql = $this->columnUsingSql($field);
        $connection->executeStatement(
            'ALTER TABLE ' . $table
            . ' ALTER COLUMN ' . $column
            . ' TYPE ' . $typeSql
            . ($usingSql ? ' USING ' . $usingSql : '')
        );

        $defaultClause = $this->columnDefaultSql($field);
        if ($defaultClause !== null) {
            $connection->executeStatement('ALTER TABLE ' . $table . ' ALTER COLUMN ' . $column . ' SET DEFAULT ' . $defaultClause);
        } else {
            $connection->executeStatement('ALTER TABLE ' . $table . ' ALTER COLUMN ' . $column . ' DROP DEFAULT');
        }

        if ($field['required']) {
            $connection->executeStatement('ALTER TABLE ' . $table . ' ALTER COLUMN ' . $column . ' SET NOT NULL');
        } else {
            $connection->executeStatement('ALTER TABLE ' . $table . ' ALTER COLUMN ' . $column . ' DROP NOT NULL');
        }

        $this->dropManagedConstraints($connection, $tableName, $field['columnName']);
    }

    private function dropManagedConstraints(Connection $connection, string $tableName, string $columnName): void
    {
        $table = $connection->quoteSingleIdentifier($tableName);
        foreach (['uniq', 'fk'] as $prefix) {
            $constraint = $this->constraintName($prefix, $tableName, $columnName);
            $this->executeSchemaStatement(
                $connection,
                'ALTER TABLE ' . $table . ' DROP CONSTRAINT IF EXISTS ' . $connection->quoteSingleIdentifier($constraint)
            );
        }
    }

    private function dropManagedUniqueConstraints(Connection $connection, string $tableName): void
    {
        $schemaManager = $connection->createSchemaManager();
        foreach ($schemaManager->listTableForeignKeys($tableName) as $foreignKey) {
            $name = (string) $foreignKey->getName();
            if (str_starts_with($name, 'uniq_' . $tableName . '_')) {
                $this->executeSchemaStatement(
                    $connection,
                    'ALTER TABLE ' . $connection->quoteSingleIdentifier($tableName)
                    . ' DROP CONSTRAINT IF EXISTS ' . $connection->quoteSingleIdentifier($name)
                );
            }
        }
        foreach ($schemaManager->listTableIndexes($tableName) as $index) {
            $name = (string) $index->getName();
            if (!$index->isUnique() || !str_starts_with($name, 'uniq_' . $tableName . '_')) {
                continue;
            }
            $this->executeSchemaStatement(
                $connection,
                'ALTER TABLE ' . $connection->quoteSingleIdentifier($tableName)
                . ' DROP CONSTRAINT IF EXISTS ' . $connection->quoteSingleIdentifier($name)
            );
        }
    }

    private function foreignKeyActionSql(mixed $value): ?string
    {
        return match ($value) {
            'cascade' => 'CASCADE',
            'restrict' => 'RESTRICT',
            'set_null' => 'SET NULL',
            'set_default' => 'SET DEFAULT',
            'no_action' => 'NO ACTION',
            default => null,
        };
    }

    private function columnUsingSql(array $field): ?string
    {
        $column = '"' . str_replace('"', '""', (string) $field['columnName']) . '"';
        return match ($field['dataType']) {
            'integer' => $column . '::integer',
            'decimal' => $column . '::numeric',
            'boolean' => $column . '::boolean',
            'date' => $column . '::date',
            'datetime' => $column . '::timestamp',
            'json' => $column . '::json',
            default => null,
        };
    }

    private function fieldExistsInConfig(array $fields, string $code): bool
    {
        foreach ($fields as $field) {
            if (($field['code'] ?? '') === $code) {
                return true;
            }
        }

        return false;
    }

    private function fieldColumnExistsInRawConfig(array $fields, string $columnName): bool
    {
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            if (($field['virtualField'] ?? false) === true) {
                continue;
            }
            $currentColumn = $this->safeSqlIdentifier((string) ($field['columnName'] ?? $field['code'] ?? ''));
            if ($currentColumn === $columnName) {
                return true;
            }
        }

        return false;
    }

    private function resolvePrimaryKeyCode(array $fields): string
    {
        foreach ($fields as $field) {
            if ($field['primaryKey']) {
                return $field['code'];
            }
        }

        return 'id';
    }

    private function safeSqlIdentifier(string $value): string
    {
        $value = strtolower(trim($value));
        return preg_match('/^[a-z_][a-z0-9_]*$/', $value) ? $value : '';
    }

    private function guessDatabaseType(string $dataType): ?string
    {
        return match ($dataType) {
            'integer' => 'integer',
            'decimal' => 'numeric',
            'boolean' => 'boolean',
            'date' => 'date',
            'datetime' => 'timestamp',
            'text' => 'text',
            'json' => 'json',
            default => 'string',
        };
    }

    private function fieldMapByColumn(array $fields): array
    {
        $map = [];
        foreach ($fields as $field) {
            $map[(string) $field['columnName']] = true;
        }

        return $map;
    }
}
