<?php

namespace App\Builder;

use App\Repository\BuilderEntityRepository;
use App\Repository\BuilderModuleRepository;
use App\Runtime\RuntimeHttpException;
use App\System\SystemParameterResolver;
use Psr\Log\LoggerInterface;

class ExternalBuilderContextService
{
    private const PARAM_ENABLED = 'ai.builder.public_context_enabled';
    private const PARAM_KEY = 'ai.builder.public_context_key';

    public function __construct(
        private readonly SystemParameterResolver $parameters,
        private readonly BuilderModuleRepository $modules,
        private readonly BuilderEntityRepository $entities,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getPublicContext(?string $providedKey, array $requestContext = []): array
    {
        if (!$this->isPublicContextEnabled()) {
            throw new RuntimeHttpException('BUILDER_PUBLIC_CONTEXT_DISABLED', 'O endpoint publico do construtor esta desabilitado.', 403);
        }

        $expectedKey = $this->resolvePublicKey();
        if ($expectedKey === null || $expectedKey === '' || !hash_equals($expectedKey, trim((string) $providedKey))) {
            $this->logger->warning('builder_public_context_denied', [
                'remote_addr' => $requestContext['remoteAddr'] ?? null,
                'user_agent' => $requestContext['userAgent'] ?? null,
            ]);
            throw new RuntimeHttpException('BUILDER_PUBLIC_CONTEXT_FORBIDDEN', 'Chave publica do construtor invalida.', 403);
        }

        $this->logger->info('builder_public_context_accessed', [
            'remote_addr' => $requestContext['remoteAddr'] ?? null,
            'user_agent' => $requestContext['userAgent'] ?? null,
        ]);

        return $this->buildContextPayload();
    }

    public function buildContextPayload(): array
    {
        return [
            'schemaVersion' => '1.0',
            'type' => 'program-builder-external-context',
            'catalog' => [
                'version' => '2026.05.27.1',
                'capabilities' => [
                    $this->capabilityEntityDraft(),
                    $this->capabilityProgramDraft(),
                    $this->capabilityField(),
                    $this->capabilityBusinessRuleDeclarative(),
                ],
            ],
            'contract' => [
                'acceptedPayload' => 'builderDraft',
                'pageTypesAllowed' => ['crud'],
                'supportsAutoPublish' => false,
                'supportsPhpRules' => false,
                'supportsManualSaveOnly' => true,
            ],
            'naming' => [
                'tablePatterns' => ['t1', 't1c1', 't1e1', 't1r', 't1m', 't1e2at2e3'],
                'fieldPrefixes' => [
                    'string' => ['c_'],
                    'text' => ['t_'],
                    'integer' => ['i_', 'si_', 'bi_'],
                    'decimal' => ['d_'],
                    'boolean' => ['log_'],
                    'date' => ['dt_'],
                    'datetime' => ['dt_hr_'],
                    'foreignKey' => ['*_id'],
                    'unique' => ['u_', 'id_'],
                ],
                'notes' => [
                    'Nao gere JavaScript, templates livres ou codigo PHP.',
                    'Use apenas nomes tecnicos compativeis com o padrao Genesis-ERP.',
                    'FKs devem usar sufixo _id.',
                ],
            ],
            'supportedFieldTypes' => ['string', 'text', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'enum', 'dropdown', 'email', 'json', 'custom_code'],
            'payloadShape' => [
                'entityDraft' => [
                    'code' => 'tipo_produto',
                    'name' => 'Tipo de Produto',
                    'entityType' => 'persistence',
                    'tableName' => 't123',
                    'fields' => [
                        [
                            'code' => 'id',
                            'label' => 'ID',
                            'dataType' => 'integer',
                            'primaryKey' => true,
                            'required' => true,
                        ],
                        [
                            'code' => 'c_descr',
                            'label' => 'Descricao',
                            'dataType' => 'string',
                            'length' => 160,
                            'required' => true,
                        ],
                    ],
                ],
                'programDraft' => [
                    'pageType' => 'crud',
                    'module' => 'cadastros',
                    'programCode' => 'cd0101',
                    'programTitle' => 'Tipos de Produto',
                    'screenId' => 'cadastros.tipos-produto',
                    'version' => '1.0.0',
                ],
                'optionalKeys' => ['diagnostics', 'sourcePrompt'],
            ],
            'instructions' => [
                'Retorne apenas um JSON valido.',
                'Nao tente publicar, executar SQL ou criar codigo fora do payload.',
                'Se faltar informacao, devolva diagnostics explicando as duvidas.',
            ],
            'modules' => array_map(function ($module): array {
                return [
                    'code' => $module->getCode(),
                    'name' => $module->getName(),
                    'abbreviation' => $module->getAbbreviation(),
                    'numberStart' => $module->getNumberStart(),
                    'numberEnd' => $module->getNumberEnd(),
                ];
            }, $this->modules->findBy(['enabled' => true], ['numberStart' => 'ASC', 'code' => 'ASC'])),
            'existingEntities' => array_map(function ($entity): array {
                $primaryKey = null;
                $fields = [];
                foreach ($entity->getFields() as $field) {
                    if ($field->isPrimaryKey()) {
                        $primaryKey = $field->getCode();
                    }
                    if (count($fields) < 10) {
                        $fields[] = [
                            'code' => $field->getCode(),
                            'label' => $field->getLabel(),
                            'dataType' => $field->getDataType(),
                        ];
                    }
                }

                return [
                    'code' => $entity->getCode(),
                    'name' => $entity->getName(),
                    'entityType' => $entity->getEntityType(),
                    'tableName' => $entity->getTableName(),
                    'primaryKey' => $primaryKey,
                    'fields' => $fields,
                ];
            }, $this->entities->findBy([], ['name' => 'ASC'])),
        ];
    }

    private function capabilityEntityDraft(): array
    {
        return [
            'capabilityCode' => 'builder.entityDraft',
            'schemaVersion' => '1.0',
            'description' => 'Rascunho de entidade do Program Builder.',
            'required' => ['code', 'name', 'entityType', 'tableName', 'fields'],
            'jsonSchema' => [
                'type' => 'object',
                'required' => ['code', 'name', 'entityType', 'tableName', 'fields'],
                'properties' => [
                    'code' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                    'entityType' => ['enum' => ['persistence', 'query', 'io', 'api']],
                    'tableName' => ['type' => 'string'],
                    'fields' => ['type' => 'array', 'items' => ['$ref' => 'builder.field']],
                    'rules' => ['type' => 'array', 'items' => ['$ref' => 'builder.businessRule.declarative']],
                ],
            ],
            'example' => [
                'code' => 'produto',
                'name' => 'Produto',
                'entityType' => 'persistence',
                'tableName' => 't1',
                'fields' => [
                    ['code' => 'id', 'label' => 'ID', 'dataType' => 'integer', 'primaryKey' => true, 'required' => true],
                    ['code' => 'c_descr', 'label' => 'Descricao', 'dataType' => 'string', 'length' => 160, 'required' => true],
                ],
            ],
        ];
    }

    private function capabilityProgramDraft(): array
    {
        return [
            'capabilityCode' => 'builder.programDraft',
            'schemaVersion' => '1.0',
            'description' => 'Rascunho de programa CRUD.',
            'required' => ['pageType', 'module', 'programCode', 'programTitle', 'screenId', 'version'],
            'jsonSchema' => [
                'type' => 'object',
                'required' => ['pageType', 'module', 'programCode', 'programTitle', 'screenId', 'version'],
                'properties' => [
                    'pageType' => ['const' => 'crud'],
                    'module' => ['type' => 'string'],
                    'programCode' => ['type' => 'string'],
                    'programTitle' => ['type' => 'string'],
                    'screenId' => ['type' => 'string'],
                    'version' => ['type' => 'string'],
                ],
            ],
            'example' => [
                'pageType' => 'crud',
                'module' => 'cadastros',
                'programCode' => 'cd0101',
                'programTitle' => 'Produto',
                'screenId' => 'cadastros.produto',
                'version' => '1.0.0',
            ],
        ];
    }

    private function capabilityField(): array
    {
        return [
            'capabilityCode' => 'builder.field',
            'schemaVersion' => '1.0',
            'description' => 'Campo de entidade.',
            'required' => ['code', 'label', 'dataType'],
            'jsonSchema' => [
                'type' => 'object',
                'required' => ['code', 'label', 'dataType'],
                'properties' => [
                    'code' => ['type' => 'string'],
                    'label' => ['type' => 'string'],
                    'dataType' => ['enum' => ['string', 'text', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'enum', 'dropdown', 'email', 'json', 'custom_code']],
                    'required' => ['type' => 'boolean'],
                    'primaryKey' => ['type' => 'boolean'],
                    'length' => ['type' => 'integer'],
                    'precision' => ['type' => 'integer'],
                    'scale' => ['type' => 'integer'],
                    'unique' => ['type' => 'boolean'],
                ],
            ],
            'example' => ['code' => 'd_vl_preco', 'label' => 'Preco', 'dataType' => 'decimal', 'precision' => 14, 'scale' => 2, 'required' => true],
        ];
    }

    private function capabilityBusinessRuleDeclarative(): array
    {
        return [
            'capabilityCode' => 'builder.businessRule.declarative',
            'schemaVersion' => '1.0',
            'description' => 'Regra declarativa segura. O assistente nao pode gerar classe, metodo, PHP, JavaScript ou expressao livre.',
            'required' => ['id', 'label', 'type', 'phase', 'field', 'when', 'message'],
            'jsonSchema' => [
                'type' => 'object',
                'required' => ['id', 'label', 'type', 'phase', 'field', 'when', 'message'],
                'properties' => [
                    'id' => ['type' => 'string'],
                    'label' => ['type' => 'string'],
                    'type' => ['const' => 'requiredWhen'],
                    'phase' => ['enum' => ['beforeValidate', 'beforePersist', 'afterPersist', 'afterCommit']],
                    'order' => ['type' => 'integer'],
                    'enabled' => ['type' => 'boolean'],
                    'continueOnError' => ['type' => 'boolean'],
                    'field' => ['type' => 'string'],
                    'when' => [
                        'type' => 'object',
                        'required' => ['field', 'equals'],
                        'properties' => [
                            'field' => ['type' => 'string'],
                            'equals' => [],
                        ],
                    ],
                    'message' => ['type' => 'string'],
                    'messageKey' => ['type' => 'string'],
                    'params' => ['type' => 'object'],
                ],
            ],
            'example' => [
                'id' => 'preco-obrigatorio-quando-ativo',
                'label' => 'Preco obrigatorio quando ativo',
                'type' => 'requiredWhen',
                'phase' => 'beforeValidate',
                'field' => 'd_vl_preco',
                'when' => ['field' => 'log_ativo', 'equals' => true],
                'message' => 'Informe o preco quando o produto estiver ativo.',
            ],
        ];
    }

    private function isPublicContextEnabled(): bool
    {
        try {
            return $this->parameters->getBoolean(self::PARAM_ENABLED);
        } catch (\Throwable) {
            return false;
        }
    }

    private function resolvePublicKey(): ?string
    {
        try {
            $value = $this->parameters->get(self::PARAM_KEY);
        } catch (\Throwable) {
            return null;
        }

        if (!is_scalar($value)) {
            return null;
        }

        $key = trim((string) $value);
        return $key !== '' ? $key : null;
    }
}
