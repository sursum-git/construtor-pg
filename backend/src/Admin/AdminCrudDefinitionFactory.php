<?php

namespace App\Admin;

final class AdminCrudDefinitionFactory
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function screens(): array
    {
        $definitions = [
            self::screen(
                'admin.usuarios',
                'admin-usuarios',
                'auth_user',
                'Usuarios',
                'Cadastro de usuarios do sistema, grupos e permissoes.',
                self::authUserFields(),
                ['tenant_id', 'username', 'status', 'auth_source'],
                ['id', 'tenant_id', 'username', 'display_name', 'email', 'status', 'auth_source', 'last_login_at', 'updated_at'],
                [
                    ['id' => 'geral', 'title' => 'Geral', 'fields' => ['id', 'tenant_id', 'username', 'display_name', 'email', 'status', 'auth_source']],
                    ['id' => 'permissoes', 'title' => 'Permissoes', 'fields' => ['groups', 'permissions']],
                    ['id' => 'seguranca', 'title' => 'Seguranca', 'fields' => ['last_login_at']],
                    ['id' => 'auditoria', 'title' => 'Auditoria', 'fields' => ['created_at', 'updated_at']],
                ],
                editable: true,
                defaultSort: [['field' => 'tenant_id', 'dir' => 'asc'], ['field' => 'username', 'dir' => 'asc']],
            ),
            self::screen(
                'admin.permissoes',
                'admin-permissoes',
                'auth_user',
                'Permissoes',
                'Gerenciamento de grupos e permissoes dos usuarios.',
                self::authUserPermissionFields(),
                ['tenant_id', 'username', 'status'],
                ['id', 'tenant_id', 'username', 'status', 'updated_at'],
                [
                    ['id' => 'geral', 'title' => 'Geral', 'fields' => ['id', 'tenant_id', 'username', 'status']],
                    ['id' => 'permissoes', 'title' => 'Permissoes', 'fields' => ['groups', 'permissions']],
                    ['id' => 'auditoria', 'title' => 'Auditoria', 'fields' => ['created_at', 'updated_at']],
                ],
                editable: true,
                defaultSort: [['field' => 'tenant_id', 'dir' => 'asc'], ['field' => 'username', 'dir' => 'asc']],
            ),
            self::screen(
                'admin.usuario-assinantes',
                'admin-usuario-assinantes',
                'auth_user_subscriber',
                'Usuarios por Assinante',
                'Relacao usuario-assinante e sobrescritas de permissao por contexto.',
                self::authUserSubscriberFields(),
                ['user_tenant_id', 'username', 'subscriber_code', 'enabled'],
                ['id', 'user_tenant_id', 'username', 'subscriber_code', 'default_subscriber', 'enabled', 'updated_at'],
                [
                    ['id' => 'geral', 'title' => 'Geral', 'fields' => ['id', 'user_tenant_id', 'username', 'subscriber_code', 'default_subscriber', 'enabled']],
                    ['id' => 'permissoes', 'title' => 'Permissoes', 'fields' => ['permission_overrides']],
                    ['id' => 'metadados', 'title' => 'Metadata', 'fields' => ['metadata']],
                    ['id' => 'auditoria', 'title' => 'Auditoria', 'fields' => ['created_at', 'updated_at']],
                ],
                editable: true,
                defaultSort: [['field' => 'user_tenant_id', 'dir' => 'asc'], ['field' => 'username', 'dir' => 'asc'], ['field' => 'subscriber_code', 'dir' => 'asc']],
            ),
            self::screen(
                'admin.parametros',
                'admin-parametros',
                'system_parameter',
                'Parametros',
                'Cadastro dos parametros do sistema.',
                self::parameterFields(),
                ['code', 'name', 'data_type', 'enabled'],
                ['id', 'code', 'name', 'data_type', 'required', 'enabled', 'updated_at'],
                [
                    ['id' => 'geral', 'title' => 'Geral', 'fields' => ['id', 'code', 'name', 'data_type', 'required', 'enabled', 'option_list_id']],
                    ['id' => 'detalhes', 'title' => 'Detalhes', 'fields' => ['description', 'default_value']],
                    ['id' => 'auditoria', 'title' => 'Auditoria', 'fields' => ['created_at', 'updated_at']],
                ],
                editable: true,
                defaultSort: [['field' => 'code', 'dir' => 'asc']],
            ),
            self::screen(
                'admin.parametro-valores',
                'admin-parametro-valores',
                'system_parameter_value',
                'Valores de Parametros',
                'Valores vigentes por periodo e estabelecimento.',
                self::parameterValueFields(),
                ['parameter_id', 'establishment_code', 'enabled'],
                ['id', 'parameter_id', 'establishment_code', 'starts_at', 'ends_at', 'value', 'enabled'],
                [
                    ['id' => 'geral', 'title' => 'Geral', 'fields' => ['id', 'parameter_id', 'establishment_code', 'starts_at', 'ends_at', 'enabled']],
                    ['id' => 'valor', 'title' => 'Valor', 'fields' => ['value']],
                    ['id' => 'auditoria', 'title' => 'Auditoria', 'fields' => ['created_at', 'updated_at']],
                ],
                editable: true,
                defaultSort: [['field' => 'starts_at', 'dir' => 'desc']],
            ),
            self::screen(
                'admin.literais',
                'admin-literais',
                'system_literal_translation',
                'Literais e Traducoes',
                'Cadastro de literais por locale usados pelo frontend.',
                self::literalTranslationFields(),
                ['code', 'locale', 'context', 'enabled'],
                ['id', 'code', 'locale', 'context', 'enabled', 'updated_at'],
                [
                    ['id' => 'geral', 'title' => 'Geral', 'fields' => ['id', 'code', 'locale', 'context', 'enabled']],
                    ['id' => 'texto', 'title' => 'Texto', 'fields' => ['text', 'description']],
                    ['id' => 'auditoria', 'title' => 'Auditoria', 'fields' => ['created_at', 'updated_at']],
                ],
                editable: true,
                defaultSort: [['field' => 'code', 'dir' => 'asc'], ['field' => 'locale', 'dir' => 'asc']],
            ),
            self::screen(
                'admin.listas-opcoes',
                'admin-listas-opcoes',
                'system_option_list',
                'Listas de Opcoes',
                'Cadastro de listas fechadas usadas por parametros.',
                self::optionListFields(),
                ['code', 'name', 'enabled'],
                ['id', 'code', 'name', 'enabled', 'updated_at'],
                [
                    ['id' => 'geral', 'title' => 'Geral', 'fields' => ['id', 'code', 'name', 'enabled']],
                    ['id' => 'detalhes', 'title' => 'Detalhes', 'fields' => ['description']],
                    ['id' => 'auditoria', 'title' => 'Auditoria', 'fields' => ['created_at', 'updated_at']],
                ],
                editable: true,
                defaultSort: [['field' => 'code', 'dir' => 'asc']],
            ),
            self::screen(
                'admin.opcoes',
                'admin-opcoes',
                'system_option',
                'Opcoes',
                'Cadastro das opcoes de cada lista.',
                self::optionFields(),
                ['option_list_id', 'code', 'description', 'enabled'],
                ['id', 'option_list_id', 'code', 'description', 'position', 'enabled'],
                [
                    ['id' => 'geral', 'title' => 'Geral', 'fields' => ['id', 'option_list_id', 'code', 'description', 'position', 'enabled']],
                    ['id' => 'metadata', 'title' => 'Metadata', 'fields' => ['metadata']],
                    ['id' => 'auditoria', 'title' => 'Auditoria', 'fields' => ['created_at', 'updated_at']],
                ],
                editable: true,
                defaultSort: [['field' => 'option_list_id', 'dir' => 'asc'], ['field' => 'position', 'dir' => 'asc']],
            ),
            self::screen(
                'admin.sessoes',
                'admin-sessoes',
                'runtime_user_session',
                'Sessoes',
                'Consulta das sessoes ativas, revogadas e dados do dispositivo.',
                self::sessionFields(),
                ['tenant_id', 'user_id', 'user_name', 'session_id', 'status'],
                ['id', 'status', 'tenant_id', 'user_id', 'user_name', 'session_id', 'device_name', 'is_mobile', 'entered_at', 'last_seen_at'],
                [
                    ['id' => 'identidade', 'title' => 'Identidade', 'fields' => ['id', 'tenant_id', 'user_id', 'user_name', 'session_id', 'php_session_id', 'status']],
                    ['id' => 'dispositivo', 'title' => 'Dispositivo', 'fields' => ['device_name', 'operating_system', 'browser', 'is_mobile', 'user_agent']],
                    ['id' => 'permissoes', 'title' => 'Permissoes', 'fields' => ['session_properties', 'permission_snapshot']],
                    ['id' => 'revogacao', 'title' => 'Revogacao', 'fields' => ['revoked_by', 'revoked_at', 'revoke_reason', 'entered_at', 'last_seen_at', 'created_at', 'updated_at']],
                ],
                editable: false,
                defaultSort: [['field' => 'last_seen_at', 'dir' => 'desc']],
                extraApi: ['runtime.admin.forceLogout' => ['endpointId' => 'runtime.admin.forceLogout', 'method' => 'POST']],
                otherActions: [
                    'enabled' => true,
                    'label' => 'Acoes',
                    'icon' => 'more-vertical',
                    'actions' => [
                        [
                            'id' => 'forceLogout',
                            'label' => 'Derrubar sessao',
                            'icon' => 'logout',
                            'endpointId' => 'runtime.admin.forceLogout',
                            'permission' => 'read',
                            'visibleIn' => ['view'],
                            'refreshGrid' => true,
                            'confirm' => [
                                'title' => 'Derrubar sessao',
                                'message' => 'Deseja derrubar a sessao {session_id} do usuario {user_id}?',
                                'confirmText' => 'Derrubar',
                                'confirmIcon' => 'logout',
                            ],
                            'successMessage' => 'Sessao revogada.',
                        ],
                    ],
                ],
            ),
            self::screen(
                'admin.transacoes',
                'admin-transacoes',
                'runtime_transaction',
                'Transacoes',
                'Consulta das transacoes executadas pelo runtime.',
                self::transactionFields(),
                ['session_id', 'screen_id', 'entity_code', 'record_id', 'status'],
                ['id', 'status', 'screen_id', 'entity_code', 'record_id', 'session_id', 'endpoint_id', 'started_at', 'finished_at'],
                [
                    ['id' => 'geral', 'title' => 'Geral', 'fields' => ['id', 'tenant_id', 'session_id', 'screen_id', 'program_id', 'entity_code', 'record_id']],
                    ['id' => 'acao', 'title' => 'Acao', 'fields' => ['endpoint_id', 'action_id', 'operation', 'status', 'started_at', 'finished_at']],
                    ['id' => 'contexto', 'title' => 'Contexto', 'fields' => ['request_context']],
                ],
                editable: false,
                defaultSort: [['field' => 'started_at', 'dir' => 'desc']],
            ),
            self::screen(
                'admin.logs-transacoes',
                'admin-logs-transacoes',
                'runtime_transaction_log',
                'Logs de Transacoes',
                'Consulta dos eventos, before, after, diff e metadata das transacoes.',
                self::transactionLogFields(),
                ['transaction_id', 'event_type', 'message'],
                ['id', 'transaction_id', 'event_type', 'message', 'created_at'],
                [
                    ['id' => 'geral', 'title' => 'Geral', 'fields' => ['id', 'transaction_id', 'event_type', 'message', 'created_at']],
                    ['id' => 'dados', 'title' => 'Dados', 'fields' => ['before_data', 'after_data', 'diff_data', 'metadata']],
                ],
                editable: false,
                defaultSort: [['field' => 'created_at', 'dir' => 'desc']],
            ),
        ];

        $result = [];
        foreach ($definitions as $definition) {
            $result[$definition['screenId']] = [
                'screenId' => $definition['screenId'],
                'programId' => $definition['program']['id'],
                'title' => $definition['program']['title'],
                'module' => 'administracao',
                'type' => 'crud',
                'entityCode' => $definition['program']['entity'],
                'entityName' => $definition['program']['title'],
                'tableName' => $definition['program']['entity'],
                'definition' => $definition,
            ];
        }

        return $result;
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     * @param string[] $filterFields
     * @param string[] $gridFields
     * @param array<int, array<string, mixed>> $tabs
     * @param array<string, array<string, mixed>> $extraApi
     * @param array<string, mixed>|null $otherActions
     */
    private static function screen(
        string $screenId,
        string $programId,
        string $entityCode,
        string $title,
        string $subtitle,
        array $fields,
        array $filterFields,
        array $gridFields,
        array $tabs,
        bool $editable,
        array $defaultSort,
        array $extraApi = [],
        ?array $otherActions = null,
    ): array {
        $readOnlyFields = array_keys(array_filter($fields, fn (array $field): bool => ($field['editable'] ?? true) === false));

        return [
            'schemaVersion' => '1.0',
            'pageType' => 'crud',
            'screenId' => $screenId,
            'program' => [
                'id' => $programId,
                'module' => 'administracao',
                'entity' => $entityCode,
                'title' => $title,
                'version' => '1.0.0',
                'subtitle' => $subtitle,
            ],
            'permissions' => [
                'read' => true,
                'create' => $editable,
                'edit' => $editable,
                'delete' => $editable,
                'saveLayout' => true,
            ],
            'security' => [
                'userGroups' => ['admin'],
            ],
            'dataSource' => [
                'api' => self::api($editable, $extraApi),
            ],
            'runtime' => [
                'entityCode' => $entityCode,
                'programId' => $programId,
                'lock' => [
                    'enabled' => $editable,
                    'modes' => $editable ? ['edit', 'delete'] : [],
                ],
                'messages' => [
                    'enabled' => true,
                    'pollIntervalSeconds' => 30,
                    'events' => ['enabled' => true],
                ],
            ],
            'dataModel' => [
                'primaryKey' => 'id',
                'fields' => $fields,
            ],
            'crud' => [
                'query' => [
                    'pageSize' => 20,
                    'defaultSort' => $defaultSort,
                ],
                'filter' => [
                    'type' => 'window',
                    'mode' => 'basic',
                    'title' => 'Filtros',
                    'openOnLoad' => false,
                    'showAppliedFilters' => true,
                    'fields' => array_map(fn (string $field): array => self::filterField($field, $fields[$field]), $filterFields),
                ],
                'grid' => [
                    'pageable' => true,
                    'sortable' => true,
                    'filterable' => true,
                    'resizable' => true,
                    'reorderable' => true,
                    'columnMenu' => true,
                    'toolbar' => self::toolbar($editable),
                    'columns' => array_map(fn (string $field): array => self::gridColumn($field, $fields[$field]), $gridFields),
                    'rowActions' => self::rowActions($editable),
                    'bulkActions' => ['enabled' => false, 'actions' => []],
                    'print' => ['enabled' => false, 'options' => []],
                ],
                'form' => [
                    'id' => str_replace('.', '-', $screenId) . '-form',
                    'mode' => 'popup',
                    'layout' => 'tabs',
                    'maximizeForm' => true,
                    'title' => [
                        'create' => 'Incluir ' . mb_strtolower($title),
                        'view' => 'Detalhe de ' . mb_strtolower($title),
                        'edit' => 'Alterar ' . mb_strtolower($title),
                        'delete' => 'Excluir ' . mb_strtolower($title),
                    ],
                    'behavior' => [
                        'closeOnSave' => true,
                        'closeOnCancel' => true,
                    ],
                    'tabs' => $tabs,
                    'fields' => array_map(function (string $field) use ($readOnlyFields): array {
                        $item = ['field' => $field];
                        if (in_array($field, $readOnlyFields, true)) {
                            $item['renderAs'] = 'readonly';
                        }
                        return $item;
                    }, array_keys($fields)),
                    'logs' => ['enabled' => false],
                    'print' => ['enabled' => false, 'options' => []],
                    'otherActions' => $otherActions ?? ['enabled' => false, 'actions' => []],
                ],
                'userLayout' => [
                    'enabled' => true,
                    'storageKey' => str_replace('.', '-', $screenId) . '-layout',
                ],
            ],
        ];
    }

    private static function api(bool $editable, array $extra): array
    {
        $api = [
            'read' => ['endpointId' => 'read', 'method' => 'POST'],
            'get' => ['endpointId' => 'get', 'method' => 'POST'],
        ];
        if ($editable) {
            $api += [
                'create' => ['endpointId' => 'create', 'method' => 'POST'],
                'update' => ['endpointId' => 'update', 'method' => 'POST'],
                'delete' => ['endpointId' => 'delete', 'method' => 'POST'],
                'runtime.lock.acquire' => ['endpointId' => 'runtime.lock.acquire', 'method' => 'POST'],
                'runtime.lock.heartbeat' => ['endpointId' => 'runtime.lock.heartbeat', 'method' => 'POST'],
                'runtime.lock.release' => ['endpointId' => 'runtime.lock.release', 'method' => 'POST'],
            ];
        }

        return $api + [
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
            'runtime.messages.poll' => ['endpointId' => 'runtime.messages.poll', 'method' => 'POST'],
            'runtime.messages.ack' => ['endpointId' => 'runtime.messages.ack', 'method' => 'POST'],
        ] + $extra;
    }

    private static function toolbar(bool $editable): array
    {
        $toolbar = [];
        if ($editable) {
            $toolbar[] = ['id' => 'create', 'label' => 'Incluir', 'action' => 'create', 'icon' => 'plus', 'permission' => 'create'];
        }
        return array_merge($toolbar, [
            ['id' => 'filters', 'label' => 'Filtros', 'action' => 'filters', 'icon' => 'filter'],
            ['id' => 'refresh', 'label' => 'Atualizar', 'action' => 'refresh', 'icon' => 'arrow-rotate-cw'],
            ['id' => 'layout', 'label' => 'Leiaute', 'action' => 'layout', 'icon' => 'columns', 'permission' => 'saveLayout'],
        ]);
    }

    private static function rowActions(bool $editable): array
    {
        $actions = [
            ['id' => 'view', 'label' => 'Visualizar', 'action' => 'view', 'icon' => 'eye', 'permission' => 'read'],
        ];
        if ($editable) {
            $actions[] = ['id' => 'edit', 'label' => 'Alterar', 'action' => 'edit', 'icon' => 'pencil', 'permission' => 'edit'];
            $actions[] = ['id' => 'delete', 'label' => 'Excluir', 'action' => 'delete', 'icon' => 'trash', 'permission' => 'delete'];
        }
        return $actions;
    }

    private static function filterField(string $field, array $config): array
    {
        $type = $config['type'] ?? 'text';
        $item = [
            'id' => $field,
            'field' => $field,
            'label' => $config['label'] ?? $field,
            'type' => in_array($type, ['boolean', 'enum', 'integer', 'date', 'datetime'], true) ? $type : 'text',
            'operator' => in_array($type, ['boolean', 'enum', 'integer'], true) ? 'eq' : 'contains',
        ];
        if (isset($config['options'])) {
            $item['options'] = $config['options'];
        }
        return $item;
    }

    private static function gridColumn(string $field, array $config): array
    {
        $column = [
            'field' => $field,
            'title' => $config['label'] ?? $field,
            'width' => $config['width'] ?? (in_array($config['type'] ?? '', ['datetime', 'text', 'json'], true) ? 220 : 150),
        ];
        if (($config['type'] ?? '') === 'integer') {
            $column['align'] = 'right';
        }

        return $column;
    }

    private static function field(string $type, string $label, bool $editable = true, bool $nullable = true, array $extra = []): array
    {
        return array_merge([
            'type' => $type,
            'label' => $label,
            'editable' => $editable,
            'nullable' => $nullable,
        ], $extra);
    }

    private static function parameterFields(): array
    {
        $types = array_map(fn (string $value): array => ['value' => $value, 'text' => $value], ['string', 'text', 'integer', 'decimal', 'boolean', 'date', 'datetime', 'json', 'option', 'multi_option']);
        return [
            'id' => self::field('integer', 'ID', false, false, ['width' => 80]),
            'code' => self::field('string', 'Codigo', true, false),
            'name' => self::field('string', 'Nome', true, false),
            'description' => self::field('text', 'Descricao', true, true, ['editor' => 'textarea']),
            'data_type' => self::field('enum', 'Tipo', true, false, ['options' => $types]),
            'option_list_id' => self::field('integer', 'Lista de opcoes', true, true),
            'required' => self::field('boolean', 'Obrigatorio', true, false),
            'default_value' => self::field('json', 'Valor padrao', true, true, ['editor' => 'textarea']),
            'enabled' => self::field('boolean', 'Ativo', true, false),
            'created_at' => self::field('datetime', 'Criado em', false, false),
            'updated_at' => self::field('datetime', 'Atualizado em', false, false),
        ];
    }

    private static function parameterValueFields(): array
    {
        return [
            'id' => self::field('integer', 'ID', false, false, ['width' => 80]),
            'parameter_id' => self::field('integer', 'Parametro', true, false),
            'establishment_code' => self::field('string', 'Estabelecimento', true, true),
            'starts_at' => self::field('datetime', 'Inicio', true, false),
            'ends_at' => self::field('datetime', 'Fim', true, true),
            'value' => self::field('json', 'Valor', true, true, ['editor' => 'textarea']),
            'enabled' => self::field('boolean', 'Ativo', true, false),
            'created_at' => self::field('datetime', 'Criado em', false, false),
            'updated_at' => self::field('datetime', 'Atualizado em', false, false),
        ];
    }

    private static function optionListFields(): array
    {
        return [
            'id' => self::field('integer', 'ID', false, false, ['width' => 80]),
            'code' => self::field('string', 'Codigo', true, false),
            'name' => self::field('string', 'Nome', true, false),
            'description' => self::field('text', 'Descricao', true, true, ['editor' => 'textarea']),
            'enabled' => self::field('boolean', 'Ativo', true, false),
            'created_at' => self::field('datetime', 'Criado em', false, false),
            'updated_at' => self::field('datetime', 'Atualizado em', false, false),
        ];
    }

    private static function literalTranslationFields(): array
    {
        return [
            'id' => self::field('integer', 'ID', false, false, ['width' => 80]),
            'code' => self::field('string', 'Chave', true, false),
            'locale' => self::field('string', 'Locale', true, false),
            'context' => self::field('string', 'Contexto', true, true),
            'text' => self::field('text', 'Texto', true, false, ['editor' => 'textarea']),
            'description' => self::field('text', 'Descricao', true, true, ['editor' => 'textarea']),
            'enabled' => self::field('boolean', 'Ativo', true, false),
            'created_at' => self::field('datetime', 'Criado em', false, false),
            'updated_at' => self::field('datetime', 'Atualizado em', false, false),
        ];
    }

    private static function optionFields(): array
    {
        return [
            'id' => self::field('integer', 'ID', false, false, ['width' => 80]),
            'option_list_id' => self::field('integer', 'Lista', true, false),
            'code' => self::field('string', 'Codigo', true, false),
            'description' => self::field('string', 'Descricao', true, false),
            'position' => self::field('integer', 'Posicao', true, false),
            'enabled' => self::field('boolean', 'Ativo', true, false),
            'metadata' => self::field('json', 'Metadata', true, false, ['editor' => 'textarea']),
            'created_at' => self::field('datetime', 'Criado em', false, false),
            'updated_at' => self::field('datetime', 'Atualizado em', false, false),
        ];
    }

    private static function authUserFields(): array
    {
        $statusOptions = [
            ['value' => 'active', 'text' => 'Ativo'],
            ['value' => 'inactive', 'text' => 'Inativo'],
            ['value' => 'blocked', 'text' => 'Bloqueado'],
        ];
        $authSources = [
            ['value' => 'local', 'text' => 'Local'],
            ['value' => 'ldap', 'text' => 'LDAP'],
            ['value' => 'sso', 'text' => 'SSO'],
            ['value' => 'oauth', 'text' => 'OAuth'],
        ];

        return [
            'id' => self::field('integer', 'ID', false, false, ['width' => 80]),
            'tenant_id' => self::field('string', 'Tenant', false, false),
            'username' => self::field('string', 'Usuario', false, false),
            'display_name' => self::field('string', 'Nome', true, true),
            'email' => self::field('string', 'E-mail', true, true),
            'status' => self::field('enum', 'Status', true, false, ['options' => $statusOptions]),
            'groups' => self::field('json', 'Grupos', true, true, ['editor' => 'textarea']),
            'permissions' => self::field('json', 'Permissoes', true, true, ['editor' => 'textarea']),
            'auth_source' => self::field('enum', 'Origem de acesso', true, false, ['options' => $authSources]),
            'last_login_at' => self::field('datetime', 'Ultimo login', false, true),
            'created_at' => self::field('datetime', 'Criado em', false, false),
            'updated_at' => self::field('datetime', 'Atualizado em', false, false),
        ];
    }

    private static function authUserPermissionFields(): array
    {
        return [
            'id' => self::field('integer', 'ID', false, false, ['width' => 80]),
            'tenant_id' => self::field('string', 'Tenant', false, false),
            'username' => self::field('string', 'Usuario', false, false),
            'status' => self::field('enum', 'Status', true, false, ['options' => [
                ['value' => 'active', 'text' => 'Ativo'],
                ['value' => 'inactive', 'text' => 'Inativo'],
                ['value' => 'blocked', 'text' => 'Bloqueado'],
            ]]),
            'groups' => self::field('json', 'Grupos', true, true, ['editor' => 'textarea']),
            'permissions' => self::field('json', 'Permissoes', true, true, ['editor' => 'textarea']),
            'created_at' => self::field('datetime', 'Criado em', false, false),
            'updated_at' => self::field('datetime', 'Atualizado em', false, false),
        ];
    }

    private static function authUserSubscriberFields(): array
    {
        return [
            'id' => self::field('integer', 'ID', false, false, ['width' => 80]),
            'user_tenant_id' => self::field('string', 'Tenant do usuario', false, false),
            'username' => self::field('string', 'Usuario', false, false),
            'subscriber_code' => self::field('string', 'Assinante', true, false),
            'default_subscriber' => self::field('boolean', 'Assinante padrao', true, false),
            'enabled' => self::field('boolean', 'Ativo', true, false),
            'permission_overrides' => self::field('json', 'Sobrescrita de permissoes', true, true, ['editor' => 'textarea']),
            'metadata' => self::field('json', 'Metadados', true, true, ['editor' => 'textarea']),
            'created_at' => self::field('datetime', 'Criado em', false, false),
            'updated_at' => self::field('datetime', 'Atualizado em', false, false),
        ];
    }

    private static function sessionFields(): array
    {
        return [
            'id' => self::field('integer', 'ID', false, false, ['width' => 80]),
            'tenant_id' => self::field('string', 'Assinante', false, false),
            'user_id' => self::field('string', 'Usuario', false, false),
            'user_name' => self::field('string', 'Nome do usuario', false, true),
            'session_id' => self::field('string', 'Sessao runtime', false, false),
            'php_session_id' => self::field('string', 'Sessao PHP', false, true),
            'status' => self::field('enum', 'Status', false, false, ['options' => [['value' => 'active', 'text' => 'Ativa'], ['value' => 'revoked', 'text' => 'Revogada'], ['value' => 'expired', 'text' => 'Expirada']]]),
            'entered_at' => self::field('datetime', 'Entrada', false, false),
            'device_name' => self::field('string', 'Dispositivo', false, true),
            'user_agent' => self::field('text', 'User agent', false, true),
            'operating_system' => self::field('string', 'Sistema operacional', false, true),
            'browser' => self::field('string', 'Navegador', false, true),
            'is_mobile' => self::field('boolean', 'Mobile', false, false),
            'session_properties' => self::field('json', 'Propriedades da sessao', false, false),
            'permission_snapshot' => self::field('json', 'Permissoes da sessao', false, false),
            'revoked_by' => self::field('string', 'Revogado por', false, true),
            'revoked_at' => self::field('datetime', 'Revogado em', false, true),
            'revoke_reason' => self::field('string', 'Motivo', false, true),
            'last_seen_at' => self::field('datetime', 'Ultima atividade', false, false),
            'created_at' => self::field('datetime', 'Criado em', false, false),
            'updated_at' => self::field('datetime', 'Atualizado em', false, false),
        ];
    }

    private static function transactionFields(): array
    {
        return [
            'id' => self::field('integer', 'ID', false, false, ['width' => 80]),
            'tenant_id' => self::field('string', 'Assinante', false, false),
            'session_id' => self::field('string', 'Sessao', false, false),
            'screen_id' => self::field('string', 'Tela', false, false),
            'program_id' => self::field('string', 'Programa', false, true),
            'entity_code' => self::field('string', 'Entidade', false, true),
            'record_id' => self::field('string', 'Registro', false, true),
            'endpoint_id' => self::field('string', 'Endpoint', false, false),
            'action_id' => self::field('string', 'Acao', false, true),
            'operation' => self::field('string', 'Operacao', false, false),
            'status' => self::field('string', 'Status', false, false),
            'request_context' => self::field('json', 'Contexto da requisicao', false, false),
            'started_at' => self::field('datetime', 'Inicio', false, false),
            'finished_at' => self::field('datetime', 'Fim', false, true),
        ];
    }

    private static function transactionLogFields(): array
    {
        return [
            'id' => self::field('integer', 'ID', false, false, ['width' => 80]),
            'transaction_id' => self::field('integer', 'Transacao', false, false),
            'event_type' => self::field('string', 'Evento', false, false),
            'message' => self::field('text', 'Mensagem', false, true),
            'before_data' => self::field('json', 'Before', false, false),
            'after_data' => self::field('json', 'After', false, false),
            'diff_data' => self::field('json', 'Diff', false, false),
            'metadata' => self::field('json', 'Metadata', false, false),
            'created_at' => self::field('datetime', 'Criado em', false, false),
        ];
    }
}
