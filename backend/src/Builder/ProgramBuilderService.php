<?php

namespace App\Builder;

use App\Entity\BuilderEntity;
use App\Entity\BuilderModule;
use App\Entity\BuilderEntityVersion;
use App\Entity\BuilderField;
use App\Entity\BuilderProgramVersion;
use App\Entity\Program;
use App\Entity\RuntimeEndpoint;
use App\Entity\ScreenDefinition;
use App\Repository\BuilderEntityRepository;
use App\Repository\BuilderModuleRepository;
use App\Repository\BuilderEntityVersionRepository;
use App\Repository\BuilderFieldRepository;
use App\Repository\BuilderProgramVersionRepository;
use App\Repository\ProgramRepository;
use App\Repository\RuntimeEndpointRepository;
use App\Repository\ScreenDefinitionRepository;
use App\Runtime\PermissionResolver;
use App\Runtime\RuntimeHttpException;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class ProgramBuilderService
{
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
        private readonly BuilderModuleRepository $modules,
        private readonly BuilderFieldRepository $fields,
        private readonly BuilderEntityVersionRepository $entityVersions,
        private readonly BuilderProgramVersionRepository $versions,
        private readonly ProgramRepository $programs,
        private readonly ScreenDefinitionRepository $screens,
        private readonly RuntimeEndpointRepository $endpoints,
        private readonly EntityManagerInterface $entityManager,
        private readonly PermissionResolver $permissions,
    ) {
    }

    public function bootstrap(): array
    {
        $this->assertAdminRead();

        $entities = [];
        foreach ($this->entities->findBy([], ['name' => 'ASC']) as $entity) {
            $entities[] = [
                'code' => $entity->getCode(),
                'name' => $entity->getName(),
                'entityType' => $entity->getEntityType(),
                'status' => $entity->getStatus(),
                'tableName' => $entity->getTableName(),
            ];
        }

        $modules = [];
        foreach ($this->modules->findBy([], ['numberStart' => 'ASC', 'code' => 'ASC']) as $module) {
            $modules[] = $this->modulePayload($module);
        }

        $programs = [];
        foreach ($this->programs->findBy([], ['code' => 'ASC']) as $program) {
            $published = $this->versions->findPublishedByProgramCode($program->getCode());
            $programs[] = [
                'code' => $program->getCode(),
                'title' => $program->getTitle(),
                'module' => $program->getModule(),
                'programType' => $program->getProgramType(),
                'screenId' => $program->getScreenId(),
                'status' => $program->getStatus(),
                'publishedVersion' => $published?->getVersion(),
                'updatedAt' => $program->getUpdatedAt()->format(DATE_ATOM),
            ];
        }

        return [
            'entities' => $entities,
            'modules' => $modules,
            'programs' => $programs,
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

        return [
            'entity' => $this->entityPayload($entity),
            'versions' => array_map(fn (BuilderEntityVersion $version): array => $this->entityVersionPayload($version), $this->entityVersions->findByEntityCodeOrdered($entityCode)),
        ];
    }

    public function saveEntity(array $payload): array
    {
        $this->assertAdminWrite();

        $config = $this->normalizeEntityPayload($payload);
        $entity = $this->applyEntityConfig($config);
        $version = $this->createEntityVersionSnapshot($entity, 'save', 'Modelagem salva.');

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
                'uniqueKeys' => $config['uniqueKeys'],
                'rules' => $config['rules'],
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

    public function getProgram(string $programCode): array
    {
        $this->assertAdminRead();
        $programCode = trim($programCode);
        if ($programCode === '') {
            throw new RuntimeHttpException('PROGRAM_CODE_REQUIRED', 'Informe o codigo do programa.', 422);
        }

        $program = $this->programs->findOneBy(['code' => $programCode]);
        $versions = array_map(fn (BuilderProgramVersion $version): array => $this->versionPayload($version), $this->versions->findByProgramCodeOrdered($programCode));

        return [
            'program' => $program ? [
                'code' => $program->getCode(),
                'title' => $program->getTitle(),
                'module' => $program->getModule(),
                'programType' => $program->getProgramType(),
                'screenId' => $program->getScreenId(),
                'status' => $program->getStatus(),
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

        $config = $this->normalizeBuilderPayload($payload);
        $definition = $this->generateCrudDefinition($config);

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
            ->setBuilderConfig($this->publicBuilderConfig($config))
            ->setGeneratedDefinition($definition);

        $this->entityManager->persist($version);
        $this->entityManager->flush();

        return $this->versionPayload($version);
    }

    public function previewDraft(array $payload): array
    {
        $this->assertAdminRead();

        $config = $this->normalizeBuilderPayload($payload);
        $definition = $this->generateCrudDefinition($config);

        return [
            'builderConfig' => $this->publicBuilderConfig($config),
            'generatedDefinition' => $definition,
        ];
    }

    public function publishVersion(int $id): array
    {
        $this->assertAdminWrite();

        $version = $this->versions->find($id);
        if (!$version) {
            throw new RuntimeHttpException('PROGRAM_VERSION_NOT_FOUND', 'Versao do programa nao encontrada.', 404, ['id' => $id]);
        }

        foreach ($this->versions->findByProgramCodeOrdered($version->getProgramCode()) as $item) {
            if ($item->getStatus() === 'published' && $item->getId() !== $version->getId()) {
                $item->setStatus('archived');
                $this->entityManager->persist($item);
            }
        }

        $definition = $version->getGeneratedDefinition();
        $program = $this->programs->findOneBy(['code' => $version->getProgramCode()]) ?? new Program();
        $program
            ->setCode($version->getProgramCode())
            ->setTitle($version->getProgramTitle())
            ->setModule($version->getModule())
            ->setProgramType($version->getPageType())
            ->setScreenId($version->getScreenId())
            ->setStatus('published');
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

        $this->upsertCrudRuntimeEndpoints($version);

        $version
            ->setStatus('published')
            ->setPublishedAt(new \DateTimeImmutable());
        $this->entityManager->persist($version);
        $this->entityManager->flush();

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
            ->setBuilderConfig($builderConfig)
            ->setGeneratedDefinition($generatedDefinition);

        $this->entityManager->persist($copy);
        $this->entityManager->flush();

        return $this->versionPayload($copy);
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
            ];
        }

        return [
            'code' => $entity->getCode(),
            'name' => $entity->getName(),
            'entityType' => $entity->getEntityType(),
            'tableName' => $entity->getTableName(),
            'status' => $entity->getStatus(),
            'situationEnabled' => $entity->isSituationEnabled(),
            'situationFieldCode' => $entity->getSituationFieldCode(),
            'metadata' => $entity->getMetadata(),
            'structureModuleCode' => (string) ($entity->getMetadata()['structure']['moduleCode'] ?? ''),
            'structureType' => (string) ($entity->getMetadata()['structure']['type'] ?? 'main'),
            'structureBaseNumber' => $entity->getMetadata()['structure']['baseNumber'] ?? null,
            'structureSequenceNumber' => $entity->getMetadata()['structure']['sequenceNumber'] ?? null,
            'structureParentEntityCode' => (string) ($entity->getMetadata()['structure']['parentEntityCode'] ?? ''),
            'structureLeftEntityCode' => (string) ($entity->getMetadata()['structure']['leftEntityCode'] ?? ''),
            'structureRightEntityCode' => (string) ($entity->getMetadata()['structure']['rightEntityCode'] ?? ''),
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
        $situationEnabled = (bool) ($payload['situationEnabled'] ?? false);
        $situationFieldCode = $situationEnabled ? $this->safeSqlIdentifier((string) ($payload['situationFieldCode'] ?? 'status')) : null;
        $versioningEnabled = $entityType === 'persistence' && ($payload['versioningEnabled'] ?? false) === true;
        $versioningDeduplicate = ($payload['versioningDeduplicate'] ?? true) !== false;

        if ($code === '' || $name === '') {
            throw new RuntimeHttpException('ENTITY_BUILDER_REQUIRED_FIELDS', 'Informe codigo, nome e nome da tabela da entidade.', 422);
        }
        if (!in_array($entityType, ['persistence', 'query', 'io'], true)) {
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
        $structure = $this->normalizeEntityStructure(
            $structureModuleCode,
            $structureType,
            $structureBaseNumber,
            $structureSequenceNumber,
            $structureParentEntityCode,
            $structureLeftEntityCode,
            $structureRightEntityCode,
            $entityType
        );
        $this->validateTableNamingPattern($tableName, $entityType, $originalTableName, $structure);

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
            $columnName = $this->safeSqlIdentifier((string) ($item['columnName'] ?? $fieldCode));
            $options['columnName'] = $columnName;
            $defaultValue = $this->normalizeDefaultValue($item['defaultValue'] ?? null, $dataType);
            $unique = (bool) ($item['unique'] ?? false);
            $foreignKeyTable = $this->safeSqlIdentifier((string) ($item['foreignKeyTable'] ?? ''));
            $foreignKeyColumn = $this->safeSqlIdentifier((string) ($item['foreignKeyColumn'] ?? ''));
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
            $this->validateFieldNamingPattern($fields[count($fields) - 1], $dataType, $foreignKeyTable !== '' && $foreignKeyColumn !== '');
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

        $rules = $this->normalizeEntityRules($payload['rules'] ?? [], $fields);
        $uniqueKeys = $this->normalizeEntityUniqueKeys($payload['uniqueKeys'] ?? [], $fields);
        $this->validateUniqueKeyFieldPrefixes($uniqueKeys, $fields);

        return [
            'code' => $code,
            'name' => $name,
            'entityType' => $entityType,
            'tableName' => $entityType === 'persistence' ? $tableName : null,
            'fields' => $fields,
            'primaryKey' => $this->resolvePrimaryKeyCode($fields),
            'situationEnabled' => $situationEnabled,
            'situationFieldCode' => $situationFieldCode,
            'structure' => $structure,
            'uniqueKeys' => $uniqueKeys,
            'rules' => $rules,
            'versioningEnabled' => $versioningEnabled,
            'versioningDeduplicate' => $versioningDeduplicate,
            'createPhysicalTable' => $entityType === 'persistence' && ($payload['createPhysicalTable'] ?? true) !== false,
            'allowTableRename' => $entityType === 'persistence' && ($payload['allowTableRename'] ?? true) !== false,
            'allowColumnRename' => $entityType === 'persistence' && ($payload['allowColumnRename'] ?? true) !== false,
            'dropRemovedColumns' => $entityType === 'persistence' && ($payload['dropRemovedColumns'] ?? false) === true,
        ];
    }

    private function normalizeBuilderPayload(array $payload): array
    {
        $programCode = $this->safeCode((string) ($payload['programCode'] ?? ''));
        $programTitle = trim((string) ($payload['programTitle'] ?? ''));
        $moduleCode = $this->safeCode((string) ($payload['module'] ?? ''));
        $pageType = trim((string) ($payload['pageType'] ?? 'crud'));
        $builderEntityCode = $this->safeCode((string) ($payload['builderEntityCode'] ?? ''));
        $screenId = trim((string) ($payload['screenId'] ?? ''));
        $version = trim((string) ($payload['version'] ?? '1.0.0'));

        if ($programCode === '' || $programTitle === '' || $moduleCode === '' || $builderEntityCode === '' || $screenId === '') {
            throw new RuntimeHttpException('PROGRAM_BUILDER_REQUIRED_FIELDS', 'Informe codigo, titulo, modulo, entidade e screenId.', 422);
        }
        if ($pageType !== 'crud') {
            throw new RuntimeHttpException('PROGRAM_BUILDER_PAGE_TYPE_NOT_SUPPORTED', 'Nesta primeira etapa o construtor visual suporta apenas programas CRUD.', 422, [
                'pageType' => $pageType,
            ]);
        }
        if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            throw new RuntimeHttpException('PROGRAM_VERSION_INVALID', 'Use versao no formato semantico x.y.z.', 422, [
                'version' => $version,
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

        $entity = $this->entities->findOneBy(['code' => $builderEntityCode]);
        if (!$entity) {
            throw new RuntimeHttpException('PROGRAM_BUILDER_ENTITY_NOT_FOUND', 'Entidade do construtor nao encontrada.', 422, [
                'builderEntityCode' => $builderEntityCode,
            ]);
        }
        if ($entity->getEntityType() !== 'persistence') {
            throw new RuntimeHttpException('PROGRAM_BUILDER_ENTITY_TYPE_NOT_SUPPORTED', 'Nesta etapa o gerador de programa suporta apenas entidades persistentes.', 422, [
                'builderEntityCode' => $builderEntityCode,
                'entityType' => $entity->getEntityType(),
            ]);
        }

        return [
            'programCode' => $programCode,
            'programTitle' => $programTitle,
            'module' => $moduleCode,
            'pageType' => 'crud',
            'builderEntityCode' => $builderEntityCode,
            'screenId' => $screenId,
            'version' => $version,
            'subtitle' => trim((string) ($payload['subtitle'] ?? '')) ?: null,
            'icon' => trim((string) ($payload['icon'] ?? '')) ?: null,
            'permissionPrefix' => $this->safePermissionPrefix((string) ($payload['permissionPrefix'] ?? $programCode)),
            'allowCreate' => (bool) ($payload['allowCreate'] ?? true),
            'allowUpdate' => (bool) ($payload['allowUpdate'] ?? true),
            'allowDelete' => (bool) ($payload['allowDelete'] ?? true),
            'changeSummary' => trim((string) ($payload['changeSummary'] ?? '')) ?: null,
            '_module' => $module,
            '_entity' => $entity,
        ];
    }

    private function generateCrudDefinition(array $config): array
    {
        $entity = $config['_entity'];
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
            $fields[$code] = [
                'label' => $field->getLabel(),
                'type' => $dataType,
                'nullable' => !$field->isRequired(),
            ];
            if (($options['readonly'] ?? false) === true || ($options['virtual'] ?? false) === true) {
                $fields[$code]['readonly'] = true;
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
            }

            $formFields[] = ['field' => $code];
            if ($position < 6 && !in_array($dataType, ['json'], true)) {
                $gridColumns[] = [
                    'field' => $code,
                    'title' => $field->getLabel(),
                    'width' => in_array($dataType, ['datetime', 'text'], true) ? 220 : 150,
                ];
            }
            if (($options['virtual'] ?? false) !== true && $position < 5 && !in_array($dataType, ['json', 'text'], true)) {
                $filterFields[] = [
                    'id' => $code,
                    'field' => $code,
                    'label' => $field->getLabel(),
                    'type' => in_array($dataType, ['boolean', 'enum', 'integer', 'date', 'datetime'], true) ? $dataType : 'text',
                    'operator' => in_array($dataType, ['boolean', 'enum', 'integer'], true) ? 'eq' : 'contains',
                ];
            }
            ++$position;
        }

        $permissionPrefix = $config['permissionPrefix'];

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
                'lock' => [
                    'enabled' => $config['allowUpdate'] || $config['allowDelete'],
                    'modes' => array_values(array_filter([
                        $config['allowUpdate'] ? 'edit' : null,
                        $config['allowDelete'] ? 'delete' : null,
                    ])),
                ],
                'messages' => [
                    'enabled' => true,
                    'pollIntervalSeconds' => 30,
                    'events' => ['enabled' => true],
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
                    'filterable' => true,
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
                            'fields' => array_column($formFields, 'field'),
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

    private function upsertCrudRuntimeEndpoints(BuilderProgramVersion $version): void
    {
        $handlers = [
            'read' => 'entity.crud',
            'get' => 'entity.crud',
            'create' => 'entity.crud',
            'update' => 'entity.crud',
            'delete' => 'entity.crud',
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
            'runtime.lock.acquire' => 'runtime.lock.acquire',
            'runtime.lock.heartbeat' => 'runtime.lock.heartbeat',
            'runtime.lock.release' => 'runtime.lock.release',
            'runtime.messages.poll' => 'runtime.messages.poll',
            'runtime.messages.ack' => 'runtime.messages.ack',
        ];

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
            default => null,
        };
    }

    private function endpointConfig(BuilderProgramVersion $version, string $endpointId, string $handler): array
    {
        if ($handler === 'entity.crud') {
            return [
                'entityCode' => $version->getBuilderEntityCode(),
                'operation' => $endpointId,
                'actionId' => $endpointId,
                'programId' => $version->getProgramCode(),
                'permissionPrefix' => $version->getPermissionPrefix(),
            ];
        }

        return [];
    }

    private function crudApiMap(array $config): array
    {
        $api = [
            'read' => ['endpointId' => 'read', 'method' => 'POST'],
            'get' => ['endpointId' => 'get', 'method' => 'POST'],
        ];
        if ($config['allowCreate']) {
            $api['create'] = ['endpointId' => 'create', 'method' => 'POST'];
        }
        if ($config['allowUpdate']) {
            $api['update'] = ['endpointId' => 'update', 'method' => 'POST'];
        }
        if ($config['allowDelete']) {
            $api['delete'] = ['endpointId' => 'delete', 'method' => 'POST'];
        }

        return $api + [
            'runtime.lock.acquire' => ['endpointId' => 'runtime.lock.acquire', 'method' => 'POST'],
            'runtime.lock.heartbeat' => ['endpointId' => 'runtime.lock.heartbeat', 'method' => 'POST'],
            'runtime.lock.release' => ['endpointId' => 'runtime.lock.release', 'method' => 'POST'],
            'runtime.messages.poll' => ['endpointId' => 'runtime.messages.poll', 'method' => 'POST'],
            'runtime.messages.ack' => ['endpointId' => 'runtime.messages.ack', 'method' => 'POST'],
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
