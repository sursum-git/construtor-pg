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
                'admin.notificacoes',
                'admin-notificacoes',
                'runtime_notification',
                'Notificacoes',
                'Cadastro de notificacoes por usuario e grupo, com publicacao e rastreio de leitura.',
                self::notificationFields(),
                ['code', 'title', 'category', 'severity', 'status'],
                ['id', 'code', 'title', 'category', 'severity', 'status', 'published_at', 'expires_at', 'updated_at'],
                [
                    ['id' => 'geral', 'title' => 'Geral', 'fields' => ['id', 'tenant_id', 'code', 'title', 'category', 'severity', 'status', 'action_required']],
                    ['id' => 'destinatarios', 'title' => 'Destinatarios', 'fields' => ['target_user_ids', 'target_groups', 'link_program_id', 'link_screen_id']],
                    ['id' => 'mensagem', 'title' => 'Mensagem', 'fields' => ['message', 'metadata']],
                    ['id' => 'controle', 'title' => 'Controle', 'fields' => ['expires_at', 'published_at', 'created_by', 'created_at', 'updated_at']],
                ],
                editable: true,
                defaultSort: [['field' => 'updated_at', 'dir' => 'desc']],
            ),
            self::screen(
                'admin.notificacao-destinatarios',
                'admin-notificacao-destinatarios',
                'runtime_notification_recipient',
                'Destinatarios de Notificacoes',
                'Acompanhamento de entrega e leitura por usuario destinatario.',
                self::notificationRecipientFields(),
                ['notification_id', 'user_id', 'user_name', 'source_type', 'status'],
                ['id', 'notification_id', 'user_id', 'user_name', 'source_type', 'status', 'delivered_at', 'read_at', 'updated_at'],
                [
                    ['id' => 'geral', 'title' => 'Geral', 'fields' => ['id', 'tenant_id', 'notification_id', 'user_id', 'user_name', 'source_type', 'source_key', 'status']],
                    ['id' => 'entrega', 'title' => 'Entrega', 'fields' => ['delivered_at', 'read_at', 'created_at', 'updated_at']],
                ],
                editable: false,
                defaultSort: [['field' => 'updated_at', 'dir' => 'desc']],
            ),
            self::screen(
                'admin.instalacao-licencas',
                'admin-instalacao-licencas',
                'installer_activation_license',
                'Licencas de Instalacao',
                'Cadastro central dos assinantes autorizados a ativar instaladores compilados.',
                self::installerActivationLicenseFields(),
                ['subscriber_code', 'subscriber_name', 'activation_email', 'status'],
                ['id', 'subscriber_code', 'subscriber_name', 'activation_email', 'status', 'activation_count', 'max_activations', 'expires_at', 'updated_at'],
                [
                    ['id' => 'geral', 'title' => 'Geral', 'fields' => ['id', 'subscriber_code', 'subscriber_name', 'activation_email', 'status']],
                    ['id' => 'permissoes', 'title' => 'Permissoes', 'fields' => ['allowed_profiles', 'allowed_modes', 'max_activations']],
                    ['id' => 'controle', 'title' => 'Controle', 'fields' => ['activation_count', 'expires_at', 'last_activated_at']],
                    ['id' => 'metadata', 'title' => 'Metadata', 'fields' => ['notes', 'metadata']],
                    ['id' => 'auditoria', 'title' => 'Auditoria', 'fields' => ['created_at', 'updated_at']],
                ],
                editable: true,
                defaultSort: [['field' => 'subscriber_code', 'dir' => 'asc']],
            ),
            self::screen(
                'admin.instalacao-tokens',
                'admin-instalacao-tokens',
                'installer_activation_service_token',
                'Tokens Internos de Instalacao',
                'Tokens cadastrados para provisionamento SaaS sem confirmacao manual por e-mail.',
                self::installerActivationServiceTokenFields(),
                ['code', 'name', 'status'],
                ['id', 'code', 'name', 'status', 'usage_count', 'expires_at', 'last_used_at', 'updated_at'],
                [
                    ['id' => 'geral', 'title' => 'Geral', 'fields' => ['id', 'code', 'name', 'status']],
                    ['id' => 'seguranca', 'title' => 'Seguranca', 'fields' => ['token_hash', 'allowed_profiles', 'allowed_modes', 'expires_at']],
                    ['id' => 'uso', 'title' => 'Uso', 'fields' => ['usage_count', 'last_used_at', 'metadata']],
                    ['id' => 'auditoria', 'title' => 'Auditoria', 'fields' => ['created_at', 'updated_at']],
                ],
                editable: true,
                defaultSort: [['field' => 'updated_at', 'dir' => 'desc']],
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
                'admin.programa-solicitacoes',
                'admin-programa-solicitacoes',
                'program_change_request',
                'Solicitacoes de Alteracao',
                'Solicitacoes formais para editar programas padrao.',
                self::programChangeRequestFields(),
                ['request_code', 'program_code', 'builder_entity_code', 'requested_by', 'status'],
                ['id', 'request_code', 'program_code', 'builder_entity_code', 'requested_by', 'status', 'approved_by', 'updated_at'],
                [
                    ['id' => 'geral', 'title' => 'Geral', 'fields' => ['id', 'request_code', 'program_code', 'builder_entity_code', 'requested_by', 'status']],
                    ['id' => 'aprovacao', 'title' => 'Aprovacao', 'fields' => ['requested_actions', 'reason', 'approved_by', 'approved_at', 'metadata']],
                    ['id' => 'auditoria', 'title' => 'Auditoria', 'fields' => ['created_at', 'updated_at']],
                ],
                editable: true,
                defaultSort: [['field' => 'updated_at', 'dir' => 'desc']],
            ),
            self::screen(
                'admin.programa-grants',
                'admin-programa-grants',
                'program_change_grant',
                'Autorizacoes de Alteracao',
                'Grants temporarios para editar e publicar programas padrao.',
                self::programChangeGrantFields(),
                ['request_id', 'program_code', 'builder_entity_code', 'granted_to_user_id', 'status'],
                ['id', 'request_id', 'program_code', 'builder_entity_code', 'granted_to_user_id', 'status', 'consumed_at', 'updated_at'],
                [
                    ['id' => 'geral', 'title' => 'Geral', 'fields' => ['id', 'request_id', 'program_code', 'builder_entity_code', 'granted_to_user_id', 'status']],
                    ['id' => 'escopo', 'title' => 'Escopo', 'fields' => ['allowed_actions', 'valid_until_publish', 'metadata']],
                    ['id' => 'auditoria', 'title' => 'Auditoria', 'fields' => ['consumed_at', 'created_at', 'updated_at']],
                ],
                editable: true,
                defaultSort: [['field' => 'updated_at', 'dir' => 'desc']],
            ),
            self::screen(
                'admin.programa-testes',
                'admin-programa-testes',
                'program_test_execution',
                'Execucoes de Teste',
                'Roteiros de teste executados para liberar publicacao governada.',
                self::programTestExecutionFields(),
                ['program_code', 'builder_program_version_id', 'bundle_id', 'test_plan_id', 'status'],
                ['id', 'program_code', 'builder_program_version_id', 'bundle_id', 'test_plan_id', 'executed_by', 'status', 'executed_at'],
                [
                    ['id' => 'geral', 'title' => 'Geral', 'fields' => ['id', 'program_code', 'builder_program_version_id', 'builder_entity_version_id', 'bundle_id', 'test_plan_id', 'executed_by', 'status', 'executed_at']],
                    ['id' => 'evidencias', 'title' => 'Evidencias', 'fields' => ['checklist_snapshot', 'evidences', 'notes']],
                    ['id' => 'auditoria', 'title' => 'Auditoria', 'fields' => ['created_at', 'updated_at']],
                ],
                editable: true,
                defaultSort: [['field' => 'executed_at', 'dir' => 'desc']],
            ),
            self::screen(
                'admin.programa-aprovacoes',
                'admin-programa-aprovacoes',
                'program_publication_approval',
                'Aprovacoes de Publicacao',
                'Aprovacao final para publicacao de programas padrao.',
                self::programPublicationApprovalFields(),
                ['program_code', 'builder_program_version_id', 'requested_by', 'approved_by', 'status'],
                ['id', 'program_code', 'builder_program_version_id', 'requested_by', 'approved_by', 'status', 'approved_at', 'updated_at'],
                [
                    ['id' => 'geral', 'title' => 'Geral', 'fields' => ['id', 'program_code', 'builder_program_version_id', 'requested_by', 'approved_by', 'status']],
                    ['id' => 'controle', 'title' => 'Controle', 'fields' => ['test_execution_bundle_id', 'approved_at', 'metadata']],
                    ['id' => 'auditoria', 'title' => 'Auditoria', 'fields' => ['created_at', 'updated_at']],
                ],
                editable: true,
                defaultSort: [['field' => 'updated_at', 'dir' => 'desc']],
            ),
            self::screen(
                'admin.integridade',
                'admin-integridade',
                'system_record_integrity',
                'Integridade Estrutural',
                'Monitor administrativo das assinaturas estruturais.',
                self::systemRecordIntegrityFields(),
                ['table_name', 'record_id', 'last_check_status', 'signed_by'],
                ['id', 'table_name', 'record_id', 'last_check_status', 'signed_by', 'signed_at', 'last_checked_at'],
                [
                    ['id' => 'geral', 'title' => 'Geral', 'fields' => ['id', 'table_name', 'record_id', 'integrity_schema_version', 'last_check_status']],
                    ['id' => 'assinatura', 'title' => 'Assinatura', 'fields' => ['payload_hash', 'signature', 'signed_by', 'signed_at']],
                    ['id' => 'verificacao', 'title' => 'Verificacao', 'fields' => ['last_checked_at', 'last_error_message', 'metadata']],
                ],
                editable: false,
                defaultSort: [['field' => 'last_check_status', 'dir' => 'asc'], ['field' => 'last_checked_at', 'dir' => 'desc']],
                extraApi: ['runtime.admin.integrity.resign' => ['endpointId' => 'runtime.admin.integrity.resign', 'method' => 'POST']],
                otherActions: [
                    'enabled' => true,
                    'label' => 'Acoes',
                    'icon' => 'more-vertical',
                    'actions' => [
                        [
                            'id' => 'resignIntegrity',
                            'label' => 'Reassinar',
                            'icon' => 'reload',
                            'endpointId' => 'runtime.admin.integrity.resign',
                            'permission' => 'read',
                            'visibleIn' => ['view'],
                            'refreshGrid' => true,
                            'confirm' => [
                                'title' => 'Reassinar registro estrutural',
                                'message' => 'Deseja reassinar o registro {table_name}#{record_id}?',
                                'confirmText' => 'Reassinar',
                                'confirmIcon' => 'reload',
                            ],
                            'successMessage' => 'Registro reassinado.',
                        ],
                    ],
                ],
            ),
            self::screen(
                'admin.programa-overlays',
                'admin-programa-overlays',
                'builder_program_overlay',
                'Overlays de Programa',
                'Customizacoes por assinante para programas padrao.',
                self::programOverlayFields(),
                ['program_code', 'subscriber_id', 'customization_kind', 'status'],
                ['id', 'program_code', 'subscriber_id', 'customization_kind', 'status', 'base_program_version_id', 'upgrade_frozen', 'updated_at'],
                [
                    ['id' => 'geral', 'title' => 'Geral', 'fields' => ['id', 'program_code', 'subscriber_id', 'customization_kind', 'status', 'base_program_version_id']],
                    ['id' => 'customizacao', 'title' => 'Customizacao', 'fields' => ['upgrade_frozen', 'frozen_reason', 'overlay_config', 'metadata']],
                    ['id' => 'auditoria', 'title' => 'Auditoria', 'fields' => ['created_at', 'updated_at']],
                ],
                editable: true,
                defaultSort: [['field' => 'updated_at', 'dir' => 'desc']],
            ),
            self::screen(
                'admin.programa-overlay-versoes',
                'admin-programa-overlay-versoes',
                'builder_program_overlay_version',
                'Versoes de Overlay',
                'Versoes publicadas e rascunhos das customizacoes por assinante.',
                self::programOverlayVersionFields(),
                ['overlay_id', 'version_number', 'status'],
                ['id', 'overlay_id', 'version_number', 'status', 'published_at', 'updated_at'],
                [
                    ['id' => 'geral', 'title' => 'Geral', 'fields' => ['id', 'overlay_id', 'version_number', 'status', 'published_at']],
                    ['id' => 'conteudo', 'title' => 'Conteudo', 'fields' => ['snapshot', 'resolved_definition', 'change_summary']],
                    ['id' => 'auditoria', 'title' => 'Auditoria', 'fields' => ['created_at', 'updated_at']],
                ],
                editable: true,
                defaultSort: [['field' => 'updated_at', 'dir' => 'desc']],
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
        $fields = self::withTechnicalProperties($entityCode, $fields);
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

    /**
     * @param array<string, array<string, mixed>> $fields
     * @return array<string, array<string, mixed>>
     */
    private static function withTechnicalProperties(string $entityCode, array $fields): array
    {
        foreach ($fields as $fieldName => $config) {
            if (!empty($config['technicalProperties'])) {
                continue;
            }
            $properties = [
                ['section' => 'Modelo', 'labelKey' => 'technical.label.field', 'label' => 'Campo', 'value' => $fieldName],
                ['section' => 'Modelo', 'labelKey' => 'technical.label.entity', 'label' => 'Entidade', 'value' => $entityCode],
                ['section' => 'Modelo', 'labelKey' => 'technical.label.data_type', 'label' => 'Tipo de dado', 'value' => (string) ($config['type'] ?? 'string')],
                ['section' => 'Runtime', 'labelKey' => 'technical.label.editable', 'label' => 'Editavel', 'value' => (($config['editable'] ?? true) === true) ? 'Sim' : 'Nao', 'critical' => (($config['editable'] ?? true) !== true)],
                ['section' => 'Runtime', 'labelKey' => 'technical.label.nullable', 'label' => 'Aceita nulo', 'value' => (($config['nullable'] ?? true) === true) ? 'Sim' : 'Nao'],
            ];
            if (!empty($config['editor'])) {
                $properties[] = ['section' => 'Exibicao', 'labelKey' => 'technical.label.editor', 'label' => 'Editor', 'value' => (string) $config['editor']];
            }
            if (!empty($config['width'])) {
                $properties[] = ['section' => 'Exibicao', 'labelKey' => 'technical.label.suggested_width', 'label' => 'Largura sugerida', 'value' => (string) $config['width']];
            }
            $adminScreen = self::adminScreenForEntity($entityCode);
            if ($adminScreen !== null) {
                $properties[] = [
                    'section' => 'Navegacao',
                    'labelKey' => 'technical.label.admin_screen',
                    'label' => 'Tela administrativa',
                    'value' => $adminScreen,
                    'action' => [
                        'type' => 'openScreen',
                        'screenId' => $adminScreen,
                        'label' => 'Abrir tela',
                    ],
                ];
            }
            $fields[$fieldName]['technicalProperties'] = $properties;
        }

        return $fields;
    }

    private static function adminScreenForEntity(string $entityCode): ?string
    {
        return match ($entityCode) {
            'runtime_notification' => 'admin.notificacoes',
            'runtime_notification_recipient' => 'admin.notificacao-destinatarios',
            'runtime_user_session' => 'admin.sessoes',
            'runtime_transaction' => 'admin.transacoes',
            'program_change_request' => 'admin.programa-solicitacoes',
            'program_change_grant' => 'admin.programa-grants',
            'program_test_execution' => 'admin.programa-testes',
            'program_publication_approval' => 'admin.programa-aprovacoes',
            'builder_program_overlay' => 'admin.programa-overlays',
            'builder_program_overlay_version' => 'admin.programa-overlay-versoes',
            'import_export_mapping' => 'admin.integracoes',
            default => null,
        };
    }

    private static function programChangeRequestFields(): array
    {
        return [
            'id' => self::field('integer', 'ID', false, false, ['width' => 80]),
            'request_code' => self::field('string', 'Codigo da solicitacao', true, false),
            'program_code' => self::field('string', 'Programa', true, false),
            'builder_entity_code' => self::field('string', 'Entidade base', true, true),
            'requested_by' => self::field('string', 'Solicitado por', true, false),
            'requested_actions' => self::field('json', 'Acoes solicitadas', true, false, ['editor' => 'textarea']),
            'reason' => self::field('text', 'Justificativa', true, true, ['editor' => 'textarea']),
            'status' => self::field('enum', 'Status', true, false, ['options' => [
                ['value' => 'pending', 'text' => 'Pendente'],
                ['value' => 'approved', 'text' => 'Aprovada'],
                ['value' => 'rejected', 'text' => 'Rejeitada'],
                ['value' => 'revoked', 'text' => 'Revogada'],
                ['value' => 'frozen', 'text' => 'Congelada'],
                ['value' => 'consumed', 'text' => 'Consumida'],
                ['value' => 'expired', 'text' => 'Expirada'],
            ]]),
            'approved_by' => self::field('string', 'Aprovado por', true, true),
            'approved_at' => self::field('datetime', 'Aprovado em', true, true),
            'metadata' => self::field('json', 'Metadata', true, false, ['editor' => 'textarea']),
            'created_at' => self::field('datetime', 'Criado em', false, false),
            'updated_at' => self::field('datetime', 'Atualizado em', false, false),
        ];
    }

    private static function programChangeGrantFields(): array
    {
        return [
            'id' => self::field('integer', 'ID', false, false, ['width' => 80]),
            'request_id' => self::field('integer', 'Solicitacao', true, false),
            'program_code' => self::field('string', 'Programa', true, false),
            'builder_entity_code' => self::field('string', 'Entidade base', true, true),
            'granted_to_user_id' => self::field('string', 'Usuario liberado', true, false),
            'allowed_actions' => self::field('json', 'Acoes permitidas', true, false, ['editor' => 'textarea']),
            'status' => self::field('enum', 'Status', true, false, ['options' => [
                ['value' => 'active', 'text' => 'Ativa'],
                ['value' => 'revoked', 'text' => 'Revogada'],
                ['value' => 'frozen', 'text' => 'Congelada'],
                ['value' => 'consumed', 'text' => 'Consumida'],
                ['value' => 'expired', 'text' => 'Expirada'],
            ]]),
            'valid_until_publish' => self::field('boolean', 'Valida ate publicar', true, false),
            'consumed_at' => self::field('datetime', 'Consumida em', true, true),
            'metadata' => self::field('json', 'Metadata', true, false, ['editor' => 'textarea']),
            'created_at' => self::field('datetime', 'Criado em', false, false),
            'updated_at' => self::field('datetime', 'Atualizado em', false, false),
        ];
    }

    private static function programTestExecutionFields(): array
    {
        return [
            'id' => self::field('integer', 'ID', false, false, ['width' => 80]),
            'program_code' => self::field('string', 'Programa', true, false),
            'builder_program_version_id' => self::field('integer', 'Versao do programa', true, true),
            'builder_entity_version_id' => self::field('integer', 'Versao da entidade', true, true),
            'bundle_id' => self::field('string', 'Bundle de teste', true, false),
            'test_plan_id' => self::field('string', 'Roteiro', true, false),
            'executed_by' => self::field('string', 'Executado por', true, false),
            'status' => self::field('enum', 'Status', true, false, ['options' => [
                ['value' => 'passed', 'text' => 'Aprovado'],
                ['value' => 'failed', 'text' => 'Falhou'],
                ['value' => 'blocked', 'text' => 'Bloqueado'],
            ]]),
            'checklist_snapshot' => self::field('json', 'Checklist', true, false, ['editor' => 'textarea']),
            'evidences' => self::field('json', 'Evidencias', true, false, ['editor' => 'textarea']),
            'notes' => self::field('text', 'Observacoes', true, true, ['editor' => 'textarea']),
            'executed_at' => self::field('datetime', 'Executado em', true, false),
            'created_at' => self::field('datetime', 'Criado em', false, false),
            'updated_at' => self::field('datetime', 'Atualizado em', false, false),
        ];
    }

    private static function programPublicationApprovalFields(): array
    {
        return [
            'id' => self::field('integer', 'ID', false, false, ['width' => 80]),
            'program_code' => self::field('string', 'Programa', true, false),
            'builder_program_version_id' => self::field('integer', 'Versao do programa', true, true),
            'requested_by' => self::field('string', 'Solicitado por', true, false),
            'approved_by' => self::field('string', 'Aprovado por', true, true),
            'status' => self::field('enum', 'Status', true, false, ['options' => [
                ['value' => 'pending', 'text' => 'Pendente'],
                ['value' => 'approved', 'text' => 'Aprovada'],
                ['value' => 'rejected', 'text' => 'Rejeitada'],
                ['value' => 'revoked', 'text' => 'Revogada'],
                ['value' => 'frozen', 'text' => 'Congelada'],
            ]]),
            'test_execution_bundle_id' => self::field('string', 'Bundle de teste', true, true),
            'approved_at' => self::field('datetime', 'Aprovado em', true, true),
            'metadata' => self::field('json', 'Metadata', true, false, ['editor' => 'textarea']),
            'created_at' => self::field('datetime', 'Criado em', false, false),
            'updated_at' => self::field('datetime', 'Atualizado em', false, false),
        ];
    }

    private static function programOverlayFields(): array
    {
        return [
            'id' => self::field('integer', 'ID', false, false, ['width' => 80]),
            'program_code' => self::field('string', 'Programa base', true, false),
            'subscriber_id' => self::field('string', 'Assinante', true, false),
            'customization_kind' => self::field('enum', 'Tipo', true, false, ['options' => [
                ['value' => 'customer_overlay', 'text' => 'Overlay'],
                ['value' => 'customer_custom', 'text' => 'Custom completo'],
            ]]),
            'base_program_version_id' => self::field('integer', 'Versao base', true, true),
            'status' => self::field('enum', 'Status', true, false, ['options' => [
                ['value' => 'draft', 'text' => 'Rascunho'],
                ['value' => 'published', 'text' => 'Publicado'],
                ['value' => 'archived', 'text' => 'Arquivado'],
            ]]),
            'upgrade_frozen' => self::field('boolean', 'Upgrade congelado', true, false),
            'frozen_reason' => self::field('string', 'Motivo do congelamento', true, true),
            'overlay_config' => self::field('json', 'Configuracao', true, false, ['editor' => 'textarea']),
            'metadata' => self::field('json', 'Metadata', true, false, ['editor' => 'textarea']),
            'created_at' => self::field('datetime', 'Criado em', false, false),
            'updated_at' => self::field('datetime', 'Atualizado em', false, false),
        ];
    }

    private static function programOverlayVersionFields(): array
    {
        return [
            'id' => self::field('integer', 'ID', false, false, ['width' => 80]),
            'overlay_id' => self::field('integer', 'Overlay', true, false),
            'version_number' => self::field('integer', 'Versao', true, false),
            'status' => self::field('enum', 'Status', true, false, ['options' => [
                ['value' => 'draft', 'text' => 'Rascunho'],
                ['value' => 'published', 'text' => 'Publicado'],
                ['value' => 'archived', 'text' => 'Arquivado'],
            ]]),
            'snapshot' => self::field('json', 'Snapshot', true, false, ['editor' => 'textarea']),
            'resolved_definition' => self::field('json', 'Definicao resolvida', true, false, ['editor' => 'textarea']),
            'change_summary' => self::field('text', 'Resumo', true, true, ['editor' => 'textarea']),
            'published_at' => self::field('datetime', 'Publicado em', true, true),
            'created_at' => self::field('datetime', 'Criado em', false, false),
            'updated_at' => self::field('datetime', 'Atualizado em', false, false),
        ];
    }

    private static function systemRecordIntegrityFields(): array
    {
        return [
            'id' => self::field('integer', 'ID', false, false, ['width' => 80]),
            'table_name' => self::field('string', 'Tabela', false, false),
            'record_id' => self::field('integer', 'Registro', false, false),
            'integrity_schema_version' => self::field('integer', 'Schema da integridade', false, false),
            'payload_hash' => self::field('string', 'Hash do payload', false, false),
            'signature' => self::field('string', 'Assinatura', false, false),
            'signed_by' => self::field('string', 'Assinado por', false, true),
            'metadata' => self::field('json', 'Metadata', false, false, ['editor' => 'textarea']),
            'signed_at' => self::field('datetime', 'Assinado em', false, false),
            'last_check_status' => self::field('enum', 'Ultimo status', false, false, ['options' => [
                ['value' => 'pending', 'text' => 'Pendente'],
                ['value' => 'valid', 'text' => 'Valida'],
                ['value' => 'invalid', 'text' => 'Invalida'],
            ]]),
            'last_checked_at' => self::field('datetime', 'Ultima verificacao', false, true),
            'last_error_message' => self::field('text', 'Ultimo erro', false, true, ['editor' => 'textarea']),
        ];
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

    private static function notificationFields(): array
    {
        $severityOptions = [
            ['value' => 'info', 'text' => 'Informacao'],
            ['value' => 'warning', 'text' => 'Aviso'],
            ['value' => 'error', 'text' => 'Erro'],
            ['value' => 'success', 'text' => 'Sucesso'],
        ];
        $statusOptions = [
            ['value' => 'draft', 'text' => 'Rascunho'],
            ['value' => 'published', 'text' => 'Publicada'],
            ['value' => 'archived', 'text' => 'Arquivada'],
        ];

        return [
            'id' => self::field('integer', 'ID', false, false, ['width' => 80]),
            'tenant_id' => self::field('string', 'Assinante', false, false),
            'code' => self::field('string', 'Codigo', true, true),
            'title' => self::field('string', 'Titulo', true, false),
            'message' => self::field('text', 'Mensagem', true, false, ['editor' => 'textarea']),
            'category' => self::field('string', 'Categoria', true, false),
            'severity' => self::field('enum', 'Severidade', true, false, ['options' => $severityOptions]),
            'status' => self::field('enum', 'Status', true, false, ['options' => $statusOptions]),
            'action_required' => self::field('boolean', 'Exige acao', true, false),
            'target_user_ids' => self::field('json', 'Usuarios destinatarios', true, true, ['editor' => 'textarea']),
            'target_groups' => self::field('json', 'Grupos destinatarios', true, true, ['editor' => 'textarea']),
            'link_program_id' => self::field('string', 'Programa vinculado', true, true),
            'link_screen_id' => self::field('string', 'Screen ID vinculado', true, true),
            'metadata' => self::field('json', 'Metadata', true, true, ['editor' => 'textarea']),
            'expires_at' => self::field('datetime', 'Expira em', true, true),
            'published_at' => self::field('datetime', 'Publicada em', true, true),
            'created_by' => self::field('string', 'Criada por', false, true),
            'created_at' => self::field('datetime', 'Criado em', false, false),
            'updated_at' => self::field('datetime', 'Atualizado em', false, false),
        ];
    }

    private static function notificationRecipientFields(): array
    {
        $statusOptions = [
            ['value' => 'pending', 'text' => 'Pendente'],
            ['value' => 'delivered', 'text' => 'Entregue'],
            ['value' => 'read', 'text' => 'Lida'],
        ];
        $sourceOptions = [
            ['value' => 'user', 'text' => 'Usuario'],
            ['value' => 'group', 'text' => 'Grupo'],
        ];

        return [
            'id' => self::field('integer', 'ID', false, false, ['width' => 80]),
            'tenant_id' => self::field('string', 'Assinante', false, false),
            'notification_id' => self::field('integer', 'Notificacao', false, false),
            'user_id' => self::field('string', 'Usuario', false, false),
            'user_name' => self::field('string', 'Nome do usuario', false, false),
            'source_type' => self::field('enum', 'Origem', false, false, ['options' => $sourceOptions]),
            'source_key' => self::field('string', 'Chave da origem', false, false),
            'status' => self::field('enum', 'Status', false, false, ['options' => $statusOptions]),
            'delivered_at' => self::field('datetime', 'Entregue em', false, true),
            'read_at' => self::field('datetime', 'Lida em', false, true),
            'created_at' => self::field('datetime', 'Criado em', false, false),
            'updated_at' => self::field('datetime', 'Atualizado em', false, false),
        ];
    }

    private static function installerActivationLicenseFields(): array
    {
        $profileOptions = [
            ['value' => 'system_builder', 'text' => 'Construtor de Sistemas'],
            ['value' => 'subscriber', 'text' => 'Assinante'],
        ];
        $modeOptions = [
            ['value' => 'docker', 'text' => 'Linux Docker on-premise'],
            ['value' => 'native', 'text' => 'Linux/Windows sem Docker'],
            ['value' => 'saas-docker', 'text' => 'Docker SaaS'],
        ];

        return [
            'id' => self::field('integer', 'ID', false, false, ['width' => 80]),
            'subscriber_code' => self::field('string', 'Codigo do assinante', true, false),
            'subscriber_name' => self::field('string', 'Nome do assinante', true, false),
            'activation_email' => self::field('string', 'E-mail de ativacao', true, false),
            'status' => self::field('enum', 'Status', true, false, ['options' => [
                ['value' => 'active', 'text' => 'Ativa'],
                ['value' => 'suspended', 'text' => 'Suspensa'],
                ['value' => 'revoked', 'text' => 'Revogada'],
            ]]),
            'allowed_profiles' => self::field('json', 'Perfis permitidos', true, false, ['editor' => 'textarea', 'options' => $profileOptions]),
            'allowed_modes' => self::field('json', 'Modos permitidos', true, false, ['editor' => 'textarea', 'options' => $modeOptions]),
            'max_activations' => self::field('integer', 'Limite de ativacoes', true, false),
            'activation_count' => self::field('integer', 'Ativacoes emitidas', false, false),
            'expires_at' => self::field('datetime', 'Expira em', true, true),
            'last_activated_at' => self::field('datetime', 'Ultima ativacao', false, true),
            'notes' => self::field('text', 'Observacoes', true, true, ['editor' => 'textarea']),
            'metadata' => self::field('json', 'Metadata', true, false, ['editor' => 'textarea']),
            'created_at' => self::field('datetime', 'Criado em', false, false),
            'updated_at' => self::field('datetime', 'Atualizado em', false, false),
        ];
    }

    private static function installerActivationServiceTokenFields(): array
    {
        $profileOptions = [
            ['value' => 'system_builder', 'text' => 'Construtor de Sistemas'],
            ['value' => 'subscriber', 'text' => 'Assinante'],
        ];
        $modeOptions = [
            ['value' => 'docker', 'text' => 'Linux Docker on-premise'],
            ['value' => 'native', 'text' => 'Linux/Windows sem Docker'],
            ['value' => 'saas-docker', 'text' => 'Docker SaaS'],
        ];

        return [
            'id' => self::field('integer', 'ID', false, false, ['width' => 80]),
            'code' => self::field('string', 'Codigo', true, false),
            'name' => self::field('string', 'Nome', true, false),
            'status' => self::field('enum', 'Status', true, false, ['options' => [
                ['value' => 'active', 'text' => 'Ativo'],
                ['value' => 'suspended', 'text' => 'Suspenso'],
                ['value' => 'revoked', 'text' => 'Revogado'],
            ]]),
            'token_hash' => self::field('string', 'Hash do token', true, false),
            'allowed_profiles' => self::field('json', 'Perfis permitidos', true, false, ['editor' => 'textarea', 'options' => $profileOptions]),
            'allowed_modes' => self::field('json', 'Modos permitidos', true, false, ['editor' => 'textarea', 'options' => $modeOptions]),
            'expires_at' => self::field('datetime', 'Expira em', true, true),
            'last_used_at' => self::field('datetime', 'Ultimo uso', false, true),
            'usage_count' => self::field('integer', 'Usos', false, false),
            'metadata' => self::field('json', 'Metadata', true, false, ['editor' => 'textarea']),
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
