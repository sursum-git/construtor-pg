<?php

namespace App\Runtime;

use App\Repository\BuilderEntityRepository;
use App\Repository\BuilderEntitySituationRepository;
use App\Repository\BuilderEntitySituationTransitionRepository;
use Doctrine\DBAL\Connection;

class RuntimeEntityDefinitionResolver
{
    /** @var array<string, array<string, mixed>> */
    private array $cache = [];

    public function __construct(
        private readonly BuilderEntityRepository $entities,
        private readonly Connection $connection,
        private readonly ?BuilderEntitySituationRepository $situations = null,
        private readonly ?BuilderEntitySituationTransitionRepository $situationTransitions = null,
    ) {
    }

    public function resolve(string $entityCode): array
    {
        $entityCode = trim($entityCode);
        if ($entityCode === '') {
            throw new RuntimeHttpException('ENTITY_CODE_REQUIRED', 'Informe a entidade da operacao.', 422);
        }
        if (isset($this->cache[$entityCode])) {
            return $this->cache[$entityCode];
        }

        $entity = $this->entities->findOneBy(['code' => $entityCode]);
        if (!$entity) {
            throw new RuntimeHttpException('ENTITY_METADATA_NOT_CONFIGURED', 'Entidade nao configurada no construtor.', 422, [
                'entityCode' => $entityCode,
                'physicalTableExistsWithSameName' => $this->safeTableExists($entityCode),
                'hint' => 'A classe Doctrine ou a tabela fisica pode existir, mas o runtime generico depende dos metadados do construtor.',
                'minimumRequired' => $this->minimumCrudRequirements($entityCode),
            ]);
        }
        if ($entity->getEntityType() !== 'persistence') {
            throw new RuntimeHttpException('ENTITY_NOT_PERSISTENT', 'A entidade informada nao e uma tabela persistente.', 422, [
                'entityCode' => $entityCode,
                'entityType' => $entity->getEntityType(),
                'minimumRequired' => $this->minimumCrudRequirements($entityCode, $entity->getTableName()),
            ]);
        }

        $tableName = trim((string) $entity->getTableName());
        if ($tableName === '') {
            throw new RuntimeHttpException('ENTITY_TABLE_NOT_CONFIGURED', 'Tabela fisica da entidade nao configurada.', 422, [
                'entityCode' => $entityCode,
                'minimumRequired' => $this->minimumCrudRequirements($entityCode),
            ]);
        }
        $this->assertSafeIdentifier($tableName, 'tableName');
        $dbColumns = $this->loadTableColumns($tableName, $entityCode);
        $fields = [];
        $configuredFieldCount = 0;
        $ignoredFields = [];
        $primaryKey = (string) (($entity->getMetadata()['primaryKey'] ?? '') ?: 'id');
        $entityMetadata = $entity->getMetadata();
        $subscriberIsolation = $this->resolveSubscriberIsolationConfig($entityMetadata, $dbColumns, $entityCode, $tableName);
        $versioning = $this->resolveVersioningConfig($entityMetadata);
        foreach ($entity->getFields() as $field) {
            ++$configuredFieldCount;
            $code = trim($field->getCode());
            $options = $field->getOptions();
            $column = (string) (($options['columnName'] ?? '') ?: $code);
            $this->assertSafeIdentifier($code, 'fieldCode');
            if (($options['virtual'] ?? false) === true) {
                $fields[$code] = [
                    'code' => $code,
                    'column' => null,
                    'label' => $field->getLabel(),
                    'dataType' => $field->getDataType(),
                    'databaseType' => $field->getDatabaseType(),
                    'required' => false,
                    'primaryKey' => false,
                    'writable' => false,
                    'readable' => ($options['readable'] ?? true) !== false,
                    'audit' => false,
                    'length' => $field->getLength() ?? ($options['validation']['maxLength'] ?? $options['maxLength'] ?? null),
                    'options' => $options,
                    'virtual' => true,
                    'technicalProperties' => $this->extendTechnicalProperties(
                        $this->buildTechnicalProperties($entity->getCode(), $tableName, $field->getLabel(), $code, $field->getDataType(), $field->getDatabaseType(), $field->isRequired(), false, false, ($options['readable'] ?? true) !== false, true, $field->getLength() ?? ($options['validation']['maxLength'] ?? $options['maxLength'] ?? null), null),
                        $options,
                        $entityMetadata
                    ),
                    'derivedVersionField' => $this->normalizeVersionSnapshotFieldConfig($options),
                    'customCode' => $this->normalizeCustomCodeConfig($options),
                ];
                continue;
            }
            $this->assertSafeIdentifier($column, 'columnName');
            if (!isset($dbColumns[$column])) {
                $ignoredFields[] = [
                    'fieldCode' => $code,
                    'columnName' => $column,
                    'reason' => 'physical_column_not_found',
                ];
                continue;
            }
            if ($field->isPrimaryKey()) {
                $primaryKey = $code;
            }

            $fields[$code] = [
                'code' => $code,
                'column' => $column,
                'label' => $field->getLabel(),
                'dataType' => $field->getDataType(),
                'databaseType' => $field->getDatabaseType(),
                'required' => $field->isRequired(),
                'primaryKey' => $field->isPrimaryKey(),
                'writable' => !$field->isPrimaryKey()
                    && ($options['writable'] ?? true) !== false
                    && ($options['editable'] ?? true) !== false
                    && ($options['readonly'] ?? false) !== true,
                'readable' => ($options['readable'] ?? true) !== false,
                'audit' => ($options['audit'] ?? true) !== false,
                'length' => $field->getLength() ?? ($options['validation']['maxLength'] ?? $options['maxLength'] ?? null),
                'options' => $options,
                'virtual' => false,
                'technicalProperties' => $this->extendTechnicalProperties(
                    $this->buildTechnicalProperties($entity->getCode(), $tableName, $field->getLabel(), $code, $field->getDataType(), $field->getDatabaseType(), $field->isRequired(), $field->isPrimaryKey(), (($options['writable'] ?? true) !== false && ($options['editable'] ?? true) !== false && ($options['readonly'] ?? false) !== true), ($options['readable'] ?? true) !== false, false, $field->getLength() ?? ($options['validation']['maxLength'] ?? $options['maxLength'] ?? null), $column),
                    $options,
                    $entityMetadata
                ),
                'versionReference' => $this->normalizeVersionReferenceConfig($options),
                'customCode' => $this->normalizeCustomCodeConfig($options),
            ];
        }

        if ($configuredFieldCount === 0) {
            throw new RuntimeHttpException('ENTITY_FIELDS_NOT_CONFIGURED', 'Campos da entidade nao configurados no construtor.', 422, [
                'entityCode' => $entityCode,
                'tableName' => $tableName,
                'minimumRequired' => $this->minimumCrudRequirements($entityCode, $tableName),
            ]);
        }
        if (!$fields) {
            throw new RuntimeHttpException('ENTITY_FIELDS_NOT_USABLE', 'Nenhum campo configurado aponta para uma coluna fisica existente.', 422, [
                'entityCode' => $entityCode,
                'tableName' => $tableName,
                'ignoredFields' => $ignoredFields,
                'minimumRequired' => $this->minimumCrudRequirements($entityCode, $tableName),
            ]);
        }
        if (!isset($fields[$primaryKey])) {
            throw new RuntimeHttpException('ENTITY_PRIMARY_KEY_NOT_FOUND', 'Chave primaria da entidade nao encontrada nos metadados.', 422, [
                'entityCode' => $entityCode,
                'tableName' => $tableName,
                'primaryKey' => $primaryKey,
                'ignoredFields' => $ignoredFields,
                'minimumRequired' => $this->minimumCrudRequirements($entityCode, $tableName),
            ]);
        }
        $situation = $this->resolveSituationConfig($entity, $fields);

        return $this->cache[$entityCode] = [
            'entityCode' => $entity->getCode(),
            'name' => $entity->getName(),
            'tableName' => $tableName,
            'quotedTableName' => $this->connection->quoteSingleIdentifier($tableName),
            'primaryKey' => $primaryKey,
            'primaryColumn' => $fields[$primaryKey]['column'],
            'metadata' => $entityMetadata,
            'fields' => $fields,
            'dbColumns' => $dbColumns,
            'subscriberIsolation' => $subscriberIsolation,
            'versioning' => $versioning,
            'situation' => $situation,
        ];
    }

    /**
     * @return string[]
     */
    public function getAllowedFieldCodes(string $entityCode): array
    {
        return array_keys($this->resolve($entityCode)['fields']);
    }

    private function assertSafeIdentifier(string $value, string $name): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            throw new RuntimeHttpException('UNSAFE_ENTITY_IDENTIFIER', 'Identificador de banco invalido nos metadados.', 422, [
                $name => $value,
            ]);
        }
    }

    /**
     * @return array<string, bool>
     */
    private function loadTableColumns(string $tableName, ?string $entityCode): array
    {
        $columns = [];
        try {
            foreach ($this->connection->createSchemaManager()->listTableColumns($tableName) as $name => $column) {
                $columns[(string) $name] = true;
            }
        } catch (\Throwable $error) {
            throw new RuntimeHttpException('ENTITY_TABLE_NOT_FOUND', 'Tabela fisica da entidade nao encontrada no banco.', 422, [
                'entityCode' => $entityCode,
                'tableName' => $tableName,
                'minimumRequired' => $this->minimumCrudRequirements($entityCode, $tableName),
                'previousException' => $error::class,
            ]);
        }

        return $columns;
    }

    private function safeTableExists(string $tableName): bool
    {
        try {
            foreach ($this->connection->createSchemaManager()->listTableNames() as $existingTable) {
                if (strcasecmp((string) $existingTable, $tableName) === 0) {
                    return true;
                }
                if (strcasecmp((string) $existingTable, 'public.' . $tableName) === 0) {
                    return true;
                }
            }
        } catch (\Throwable) {
        }

        return false;
    }

    private function normalizeCustomCodeConfig(array $options): ?array
    {
        $config = is_array($options['customCode'] ?? null) ? $options['customCode'] : [];
        $mode = strtolower(trim((string) ($config['mode'] ?? '')));
        if ($mode === '') {
            return null;
        }

        $promptFields = [];
        foreach (is_array($config['promptFields'] ?? null) ? $config['promptFields'] : [] as $field) {
            if (!is_array($field)) {
                continue;
            }
            $name = trim((string) ($field['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $promptFields[] = [
                'name' => $name,
                'label' => trim((string) ($field['label'] ?? $name)),
                'type' => strtolower(trim((string) ($field['type'] ?? 'string'))),
                'required' => ($field['required'] ?? false) === true,
                'options' => is_array($field['options'] ?? null) ? array_values($field['options']) : [],
            ];
        }

        return [
            'mode' => in_array($mode, ['pattern', 'static_method'], true) ? $mode : 'pattern',
            'prefix' => trim((string) ($config['prefix'] ?? '')),
            'pattern' => trim((string) ($config['pattern'] ?? '{YYYY}{MM}{DD}-{SEQ:4}')),
            'sequenceEnabled' => ($config['sequenceEnabled'] ?? true) !== false,
            'sequenceScope' => in_array(($config['sequenceScope'] ?? null), ['global', 'year', 'month', 'day'], true) ? $config['sequenceScope'] : 'global',
            'sequencePadding' => max(1, min(12, (int) ($config['sequencePadding'] ?? 4))),
            'staticClass' => trim((string) ($config['staticClass'] ?? '')),
            'staticMethod' => trim((string) ($config['staticMethod'] ?? '')),
            'assistantScreenId' => trim((string) ($config['assistantScreenId'] ?? '')),
            'promptTitle' => trim((string) ($config['promptTitle'] ?? '')),
            'promptFields' => $promptFields,
        ];
    }

    private function resolveSituationConfig(object $entity, array $fields): array
    {
        $metadata = $entity->getMetadata();
        $metadataSituation = is_array($metadata['situation'] ?? null) ? $metadata['situation'] : [];
        $situations = $this->loadSituations($entity);
        $transitions = $this->loadSituationTransitions($entity);
        $enabled = $entity->isSituationEnabled()
            || ($metadataSituation['enabled'] ?? false) === true
            || count($situations) > 0;
        $field = trim((string) ($entity->getSituationFieldCode() ?: ($metadataSituation['field'] ?? $metadataSituation['fieldCode'] ?? '')));

        if (!$enabled) {
            return [
                'enabled' => false,
                'field' => null,
                'situations' => [],
                'transitions' => [],
            ];
        }
        if ($field === '' || !isset($fields[$field])) {
            throw new RuntimeHttpException('ENTITY_SITUATION_FIELD_NOT_FOUND', 'Campo de situacao da entidade nao encontrado nos metadados.', 422, [
                'entityCode' => $entity->getCode(),
                'field' => $field,
                'minimumRequired' => [
                    'builder_entity' => [
                        'situationEnabled' => true,
                        'situationFieldCode' => 'Campo cadastrado em builder_field.',
                    ],
                    'builder_entity_situation' => [
                        'required' => true,
                        'minimum' => 'Uma situacao inicial e as situacoes permitidas.',
                    ],
                ],
            ]);
        }

        return [
            'enabled' => true,
            'field' => $field,
            'initial' => $this->initialSituationCode($situations, $metadataSituation),
            'situations' => $situations,
            'transitions' => $transitions,
        ];
    }

    private function loadSituations(object $entity): array
    {
        $items = [];
        foreach ($this->situations?->findEnabledByEntity($entity) ?? [] as $situation) {
            $items[$situation->getCode()] = [
                'code' => $situation->getCode(),
                'label' => $situation->getLabel(),
                'description' => $situation->getDescription(),
                'position' => $situation->getPosition(),
                'initial' => $situation->isInitial(),
                'final' => $situation->isFinal(),
                'metadata' => $situation->getMetadata(),
            ];
        }

        return $items;
    }

    private function loadSituationTransitions(object $entity): array
    {
        $items = [];
        foreach ($this->situationTransitions?->findEnabledByEntity($entity) ?? [] as $transition) {
            $items[] = [
                'from' => $transition->getFromCode(),
                'to' => $transition->getToCode(),
                'actionId' => $transition->getActionId(),
                'label' => $transition->getLabel(),
                'permission' => $transition->getPermission(),
                'position' => $transition->getPosition(),
                'guardConfig' => $transition->getGuardConfig(),
                'effects' => $transition->getEffects(),
                'metadata' => $transition->getMetadata(),
            ];
        }

        return $items;
    }

    private function initialSituationCode(array $situations, array $metadataSituation): ?string
    {
        $configured = trim((string) ($metadataSituation['initial'] ?? $metadataSituation['initialCode'] ?? ''));
        if ($configured !== '' && isset($situations[$configured])) {
            return $configured;
        }
        foreach ($situations as $code => $situation) {
            if (($situation['initial'] ?? false) === true) {
                return (string) $code;
            }
        }

        return $situations ? (string) array_key_first($situations) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function minimumCrudRequirements(?string $entityCode = null, ?string $tableName = null): array
    {
        return [
            'physicalTable' => [
                'tableName' => $tableName ?: $entityCode,
                'required' => true,
                'purpose' => 'Tabela PostgreSQL com as colunas reais.',
            ],
            'builder_entity' => [
                'code' => $entityCode,
                'entityType' => 'persistence',
                'tableName' => $tableName,
                'required' => true,
                'purpose' => 'Cadastro da entidade no construtor.',
            ],
            'builder_field' => [
                'required' => true,
                'minimum' => 'Um campo primaryKey e os campos legiveis/gravaveis usados pelo frontend.',
                'columnName' => 'Use options.columnName quando o nome do campo for diferente da coluna fisica.',
            ],
            'subscriberIsolation' => [
                'required' => false,
                'mode' => 'none | subscriber_column',
                'columnName' => 'Obrigatorio quando a tabela for filtrada por assinante.',
            ],
            'builder_entity_situation' => [
                'required' => false,
                'purpose' => 'Situacoes permitidas quando a entidade tiver fluxo de situacao.',
            ],
            'builder_entity_situation_transition' => [
                'required' => false,
                'purpose' => 'Transicoes permitidas e pontos para regras/acoes quando a situacao mudar.',
            ],
            'screen_definition' => [
                'required' => true,
                'purpose' => 'JSON publicado da tela, com dataModel e dataSource.api usando endpointId.',
            ],
            'runtime_endpoint' => [
                'required' => true,
                'handler' => 'entity.crud',
                'config' => [
                    'entityCode' => $entityCode,
                    'operation' => 'read|get|create|update|delete',
                ],
            ],
        ];
    }

    private function resolveVersioningConfig(array $metadata): array
    {
        $config = is_array($metadata['versioning'] ?? null) ? $metadata['versioning'] : [];

        return [
            'enabled' => ($config['enabled'] ?? false) === true,
            'mode' => (string) ($config['mode'] ?? 'snapshot_on_change'),
            'deduplicate' => ($config['deduplicate'] ?? true) !== false,
        ];
    }

    private function resolveSubscriberIsolationConfig(array $metadata, array $dbColumns, string $entityCode, string $tableName): array
    {
        $config = is_array($metadata['subscriberIsolation'] ?? null) ? $metadata['subscriberIsolation'] : [];
        $mode = strtolower(trim((string) ($config['mode'] ?? 'none')));
        if ($mode !== 'subscriber_column') {
            return [
                'enabled' => false,
                'mode' => 'none',
                'column' => null,
            ];
        }

        $columnName = trim((string) ($config['columnName'] ?? ''));
        if ($columnName === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $columnName)) {
            throw new RuntimeHttpException('ENTITY_SUBSCRIBER_ISOLATION_INVALID', 'Configuracao de isolamento por assinante invalida.', 422, [
                'entityCode' => $entityCode,
                'tableName' => $tableName,
            ]);
        }
        if (!isset($dbColumns[$columnName])) {
            throw new RuntimeHttpException('ENTITY_SUBSCRIBER_COLUMN_NOT_FOUND', 'A coluna de assinante configurada nao existe na tabela fisica.', 422, [
                'entityCode' => $entityCode,
                'tableName' => $tableName,
                'columnName' => $columnName,
            ]);
        }

        return [
            'enabled' => true,
            'mode' => 'subscriber_column',
            'column' => $columnName,
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildTechnicalProperties(
        string $entityCode,
        string $tableName,
        string $label,
        string $fieldCode,
        string $dataType,
        ?string $databaseType,
        bool $required,
        bool $primaryKey,
        bool $writable,
        bool $readable,
        bool $virtual,
        mixed $length,
        ?string $column
    ): array {
        $properties = [
            ['section' => 'Modelo', 'label' => 'Campo', 'value' => $fieldCode],
            ['section' => 'Modelo', 'label' => 'Entidade', 'value' => $entityCode],
            ['section' => 'Modelo', 'label' => 'Rotulo', 'value' => $label],
            ['section' => 'Modelo', 'label' => 'Tipo de dado', 'value' => $dataType],
            ['section' => 'Modelo', 'label' => 'Obrigatorio', 'value' => $required ? 'Sim' : 'Nao', 'critical' => $required],
            ['section' => 'Modelo', 'label' => 'Chave primaria', 'value' => $primaryKey ? 'Sim' : 'Nao', 'critical' => $primaryKey],
            ['section' => 'Runtime', 'label' => 'Gravavel', 'value' => $writable ? 'Sim' : 'Nao'],
            ['section' => 'Runtime', 'label' => 'Legivel', 'value' => $readable ? 'Sim' : 'Nao'],
        ];

        if ($tableName !== '') {
            $properties[] = ['section' => 'Banco', 'label' => 'Tabela', 'value' => $tableName];
        }
        if ($column !== null && $column !== '') {
            $properties[] = ['section' => 'Banco', 'label' => 'Coluna', 'value' => $column];
        }
        if ($databaseType !== null && $databaseType !== '') {
            $properties[] = ['section' => 'Banco', 'label' => 'Tipo banco', 'value' => $databaseType];
        }
        if ($length !== null && $length !== '') {
            $properties[] = ['section' => 'Banco', 'label' => 'Tamanho', 'value' => (string) $length];
        }
        if ($virtual) {
            $properties[] = ['section' => 'Runtime', 'label' => 'Campo virtual', 'value' => 'Sim'];
        }

        return $properties;
    }

    /**
     * @param array<int, array<string, string>> $properties
     * @param array<string, mixed> $options
     * @param array<string, mixed> $entityMetadata
     * @return array<int, array<string, string>>
     */
    private function extendTechnicalProperties(array $properties, array $options, array $entityMetadata): array
    {
        if (!empty($options['foreignKey']['entityCode'])) {
            $properties[] = ['section' => 'Relacionamento', 'label' => 'FK entidade', 'value' => (string) $options['foreignKey']['entityCode']];
        }
        if (!empty($options['foreignKey']['fieldCode'])) {
            $properties[] = ['section' => 'Relacionamento', 'label' => 'FK campo', 'value' => (string) $options['foreignKey']['fieldCode']];
        }
        if (!empty($options['unique'])) {
            $properties[] = ['section' => 'Regra', 'label' => 'Valor unico', 'value' => 'Sim', 'critical' => true];
        }
        if (!empty($entityMetadata['versioning']['enabled'])) {
            $properties[] = ['section' => 'Versionamento', 'label' => 'Entidade versionada', 'value' => 'Sim'];
        }
        if (($entityMetadata['subscriberIsolation']['mode'] ?? 'none') === 'subscriber_column') {
            $properties[] = ['section' => 'Tenancy', 'label' => 'Filtro por assinante', 'value' => (string) ($entityMetadata['subscriberIsolation']['columnName'] ?? ''), 'critical' => true];
        }

        return $properties;
    }

    private function normalizeVersionReferenceConfig(array $options): ?array
    {
        $config = is_array($options['versionReference'] ?? null) ? $options['versionReference'] : [];
        $entityCode = trim((string) ($config['sourceEntityCode'] ?? ''));
        $sourceIdField = trim((string) ($config['sourceIdField'] ?? ''));
        if ($entityCode === '' || $sourceIdField === '') {
            return null;
        }

        return [
            'sourceEntityCode' => $entityCode,
            'sourceIdField' => $sourceIdField,
        ];
    }

    private function normalizeVersionSnapshotFieldConfig(array $options): ?array
    {
        $config = is_array($options['versionSnapshot'] ?? null) ? $options['versionSnapshot'] : [];
        $versionField = trim((string) ($config['versionField'] ?? ''));
        $path = trim((string) ($config['path'] ?? ''));
        if ($versionField === '' || $path === '') {
            return null;
        }

        return [
            'versionField' => $versionField,
            'path' => $path,
        ];
    }
}
