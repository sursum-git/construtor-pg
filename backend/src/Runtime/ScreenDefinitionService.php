<?php

namespace App\Runtime;

use App\Repository\ScreenDefinitionRepository;

class ScreenDefinitionService
{
    public function __construct(
        private readonly ScreenDefinitionRepository $screens,
        private readonly RuntimeMetadataSanitizer $sanitizer,
        private readonly PermissionResolver $permissions,
        private readonly UserLayoutService $layouts,
        private readonly ProgramCustomizationResolver $customizations,
        private readonly StructuralIntegrityService $integrity,
        private readonly ?RuntimeAuditAccessPolicy $auditAccess = null,
        private readonly ?ApiCompanyContextResolver $apiCompanies = null,
    ) {
    }

    public function getDefinition(string $screenId): array
    {
        $screen = $this->screens->findPublishedByScreenId($screenId);
        if (!$screen) {
            throw new RuntimeHttpException('SCREEN_NOT_FOUND', 'Tela nao encontrada.', 404, [
                'screenId' => $screenId,
                'minimumRequired' => [
                    'screen_definition' => [
                        'screenId' => $screenId,
                        'status' => 'draft ou published',
                        'pageType' => 'crud, home, process, analytics, report, special_document, regulated_document ou master_detail',
                        'definition' => 'JSON declarativo da tela autorizado pelo backend.',
                    ],
                ],
            ]);
        }
        if (!$this->permissions->canReadScreen($screen)) {
            throw new RuntimeHttpException('SCREEN_FORBIDDEN', 'Voce nao possui permissao para acessar esta tela.', 403, [
                'screenId' => $screenId,
            ]);
        }
        $this->integrity->assertScreen($screen);

        $definition = $screen->getDefinition();
        $customized = $this->customizations->resolve($screenId, $definition);
        if (is_array($customized) && $customized) {
            $definition = $customized;
        }
        $definition['schemaVersion'] = $definition['schemaVersion'] ?? $screen->getSchemaVersion();
        $definition['pageType'] = $definition['pageType'] ?? $screen->getPageType();
        $definition['screenId'] = $screen->getScreenId();

        if (!isset($definition['currentUser'])) {
            $definition['currentUser'] = $this->permissions->getCurrentUserPayload();
        }
        if (($definition['pageType'] ?? '') === 'home') {
            $currentUser = $this->permissions->getCurrentUserPayload();
            $definition['currentUser'] = array_replace(
                isset($currentUser['source']) ? ($definition['currentUser'] ?? []) : $currentUser,
                isset($currentUser['source']) ? $currentUser : ($definition['currentUser'] ?? []),
            );
            if (is_array($currentUser['currentSubscriber'] ?? null)) {
                $definition['currentSubscriber'] = $currentUser['currentSubscriber'];
                $definition['currentTenant'] = $currentUser['currentSubscriber'];
                $definition['tenant'] = $currentUser['currentSubscriber'];
            }
            if (is_array($currentUser['availableSubscribers'] ?? null)) {
                $definition['availableSubscribers'] = $currentUser['availableSubscribers'];
            }
        }
        if (($definition['pageType'] ?? '') === 'crud') {
            $definition = $this->withAuditViewer($definition, $screen->getScreenId());
            $definition = $this->withTransactionLogs($definition, $screen->getScreenId());
            $definition['crud']['userLayout'] = $this->layouts->buildUserLayout($screen->getScreenId());
        }
        if (($definition['pageType'] ?? '') === 'process' && in_array($definition['program']['entity'] ?? '', ['container_processos_progress', 'container_alocacao_progress', 'container_despesas_selecao_progress'], true)) {
            $definition['logs'] = ['enabled'=>$this->canReadLogsTarget('admin.logs-transacoes') && ($this->auditAccess?->canViewScreen($screenId) ?? true), 'mode'=>'recordEvents',
                'documentId'=>'runtime.transaction-events', 'screenId'=>'ciw.container-painel', 'contextField'=>'container_id', 'requiresRecord'=>true, 'title'=>'Logs'];
        }
        $definition = $this->permissions->applyDefinitionPermissions($definition);

        unset($definition['apiCompany']);
        $company = $this->apiCompanies?->describeScreen($screenId, $definition);
        if ($company !== null) { $definition['apiCompany'] = $company; }
        return $this->sanitizer->sanitize($definition);
    }

    private function withAuditViewer(array $definition, string $screenId): array
    {
        if (!in_array($screenId, ['admin.transacoes', 'admin.logs-transacoes'], true)) {
            return $definition;
        }
        foreach (['actor_user_name' => 'Usuário', 'actor_user_id' => 'Código do usuário'] as $field => $label) {
            $definition['dataModel']['fields'][$field] = ['type' => 'string', 'label' => $label, 'editable' => false, 'nullable' => true];
        }
        $actors = ['actor_user_name', 'actor_user_id'];
        $columns = array_values(array_filter($definition['crud']['grid']['columns'] ?? [], fn (array $column): bool => !in_array($column['field'] ?? '', $actors, true)));
        $definition['crud']['grid']['columns'] = array_merge([
            ['field' => 'actor_user_name', 'title' => 'Usuário', 'width' => 180, 'sortable' => false, 'filterable' => false, 'groupable' => false],
            ['field' => 'actor_user_id', 'title' => 'Código do usuário', 'width' => 140, 'sortable' => false, 'filterable' => false, 'groupable' => false],
        ], $columns);
        $actorItems = array_map(fn (string $field): array => ['field' => $field, 'renderAs' => 'readonly'], $actors);
        $form = $definition['crud']['form'] ?? [];
        $form['fields'] = array_merge($actorItems, array_values(array_filter($form['fields'] ?? [], fn (array $item): bool => !in_array($item['field'] ?? '', $actors, true))));
        if ($screenId === 'admin.logs-transacoes' && isset($definition['dataModel']['fields']['diff_data'])) {
            $comparison = ['field' => 'diff_data', 'label' => 'Alterações registradas', 'renderAs' => 'audit-diff', 'colSpan' => 2,
                'auditDiff' => ['beforeField' => 'before_data', 'afterField' => 'after_data', 'eventTypeField' => 'event_type', 'metadataField' => 'metadata']];
            $tabs = $form['tabs'] ?? [];
            foreach ($tabs as &$tab) {
                $tab['fields'] = array_values(array_filter($tab['fields'] ?? [], fn (mixed $item): bool => !in_array(is_array($item) ? ($item['field'] ?? '') : $item, array_merge($actors, ['diff_data']), true)));
            }
            unset($tab);
            $form['tabs'] = $tabs;
            array_unshift($form['tabs'], ['id' => 'alteracoes', 'title' => 'Alterações', 'fields' => array_merge($actorItems, [$comparison])]);
        } elseif (isset($form['tabs'][0])) {
            $form['tabs'][0]['fields'] = array_merge($actorItems, $form['tabs'][0]['fields'] ?? []);
        }
        $definition['crud']['form'] = $form;
        if ($screenId === 'admin.logs-transacoes') {
            $definition['crud']['auditSummary'] = [
                'enabled' => true, 'contextField' => 'transaction_id', 'field' => 'diff_data', 'maxRows' => 500,
                'actorNameField' => 'actor_user_name', 'actorIdField' => 'actor_user_id',
                'auditDiff' => ['beforeField' => 'before_data', 'afterField' => 'after_data', 'eventTypeField' => 'event_type', 'metadataField' => 'metadata'],
            ];
        }
        return $definition;
    }

    private function withTransactionLogs(array $definition, string $screenId): array
    {
        if (in_array($screenId, ['admin.transacoes', 'admin.logs-transacoes'], true)) {
            $definition['logs'] = $definition['program']['logs'] = $definition['crud']['form']['logs'] = ['enabled' => false];
        }
        if ($screenId === 'admin.logs-transacoes') {
            $contextFields = $definition['contextFields'] ?? [];
            if (!array_filter($contextFields, fn (array $field): bool => ($field['field'] ?? '') === 'transaction_id')) {
                $contextFields[] = ['field' => 'transaction_id', 'filter' => true];
            }
            foreach (['audit_screen_id' => 'string', 'audit_operation' => 'string', 'audit_record_id' => 'string'] as $field => $type) {
                $contextFields = array_values(array_filter($contextFields, fn (array $item): bool => ($item['field'] ?? '') !== $field));
                $contextFields[] = ['field' => $field, 'filter' => true];
                $label = ['audit_screen_id' => 'Tela de origem', 'audit_operation' => 'Operação auditada', 'audit_record_id' => 'Registro de origem'][$field];
                $definition['dataModel']['fields'][$field] = ['type' => $type, 'label' => $label, 'editable' => false, 'nullable' => true];
            }
            $definition['dataModel']['fields']['audit_screen_id']['validation'] = ['maxLength' => 160];
            $definition['dataModel']['fields']['audit_operation']['options'] = [['value' => 'delete', 'text' => 'Exclusão']];
            $definition['contextFields'] = $contextFields;
            return $definition;
        }
        if ($screenId === 'admin.transacoes') {
            $contextFields = array_values(array_filter($definition['contextFields'] ?? [], fn (array $field): bool => !in_array($field['field'] ?? '', ['screen_id', 'record_id'], true)));
            $definition['contextFields'] = array_merge($contextFields, [['field' => 'screen_id', 'filter' => true], ['field' => 'record_id', 'filter' => true]]);
            if (!$this->canReadLogsTarget('admin.logs-transacoes')) { return $definition; }
            $tabs = array_values(array_filter($definition['crud']['form']['tabs'] ?? [], fn (array $tab): bool => ($tab['id'] ?? '') !== 'transaction_events'));
            array_unshift($tabs, ['id' => 'transaction_events', 'title' => 'Alterações e eventos', 'type' => 'linkedPage',
                'linkedPage' => ['screenId' => 'admin.logs-transacoes', 'hideHeader' => true, 'runtimeMessages' => false,
                    'filters' => [['field' => 'transaction_id', 'sourceField' => 'id', 'operator' => 'eq']]]]);
            $definition['crud']['form']['tabs'] = $tabs;
            $links = array_values(array_filter($definition['crud']['grid']['rowLinks'] ?? [], fn (array $link): bool => !in_array($link['id'] ?? '', ['transaction_logs', 'transaction_events'], true)));
            $links[] = ['id' => 'transaction_events', 'label' => 'Eventos', 'screenId' => 'admin.logs-transacoes',
                'parameters' => [['field' => 'transaction_id', 'sourceField' => 'id']]];
            $definition['crud']['grid']['rowLinks'] = $links;
            return $definition;
        }
        $originAllowed = $this->auditAccess?->canViewScreen($screenId) ?? true;
        $eventsAllowed = $originAllowed && $this->canReadLogsTarget('admin.logs-transacoes');
        $batch = ($definition['crud']['grid']['editing']['mode'] ?? '') === 'batch'
            || in_array($definition['program']['entity'] ?? '', ['container_painel_progress','container_despesas_progress','container_itens_progress','container_versoes_custo_progress','container_custo_itens_progress','proc_compra_pagamentos_progress','procs_compra_financeiro_med_progress','procs_compra_med_progress','proc_compra_itens_progress','proc_compra_amostras_progress','proc_compra_anexos_progress','proc_compra_documentos_progress','proc_compra_despesas_progress'], true);
        $formAllowed = $originAllowed && ($batch ? $eventsAllowed : $this->canReadLogsTarget('admin.transacoes'));
        $logs = ['enabled' => true, 'mode' => $batch ? 'recordEvents' : 'transactions',
            'documentId' => $batch ? 'runtime.transaction-events' : 'runtime.transactions',
            'screenId' => $screenId, 'requiresRecord' => true, 'title' => 'Logs'];
        $form = $definition['crud']['form'] ?? $definition['form'] ?? [];
        $customButton = array_filter($form['buttons'] ?? [], fn (array $button): bool =>
            (($button['action'] ?? $button['id'] ?? '') === 'logs') && $this->hasCustomLogs($button));
        if (!$customButton && !$this->hasCustomLogs($form['logs'] ?? [])) {
            if ($formAllowed) { $definition['crud']['form']['logs'] = $logs; }
            elseif (isset($form['logs'])) { $definition['crud']['form']['logs'] = ['enabled' => false]; }
        }
        if ($eventsAllowed && !$this->hasCustomLogs($definition['program']['logs'] ?? $definition['logs'] ?? [])) {
            $definition['program']['logs'] = ['enabled' => true, 'mode' => 'deletions', 'documentId' => 'runtime.transaction-events',
                'screenId' => $screenId, 'requiresRecord' => false, 'title' => 'Logs Exclusão'];
        } elseif (!$eventsAllowed && !$this->hasCustomLogs($definition['program']['logs'] ?? $definition['logs'] ?? [])) {
            if (isset($definition['program']['logs'])) { $definition['program']['logs'] = ['enabled' => false]; }
            if (isset($definition['logs'])) { $definition['logs'] = ['enabled' => false]; }
        }
        return $definition;
    }

    private function canReadLogsTarget(string $screenId): bool
    {
        $target = $this->screens->findPublishedByScreenId($screenId);
        return $target && $this->permissions->canReadScreen($target)
            && ($this->permissions->applyDefinitionPermissions($target->getDefinition())['permissions']['read'] ?? true) !== false;
    }

    private function hasCustomLogs(array $logs): bool
    {
        $builtIn = ['transactions' => 'runtime.transactions', 'transactionEvents' => 'runtime.transaction-events',
            'recordEvents' => 'runtime.transaction-events', 'deletions' => 'runtime.transaction-events'];
        if (!array_intersect(array_keys($logs), ['url', 'endpointId', 'actionId'])
            && isset($builtIn[$logs['mode'] ?? '']) && ($logs['documentId'] ?? null) === $builtIn[$logs['mode']]) {
            return false;
        }
        foreach (['url', 'documentId', 'endpointId', 'actionId', 'mode'] as $key) {
            if (!empty($logs[$key])) {
                return true;
            }
        }
        return ($logs['enabled'] ?? false) === true;
    }

}

