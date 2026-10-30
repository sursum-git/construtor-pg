<?php

namespace App\Runtime;

use App\Builder\BuilderAiSettingsService;
use App\Privacy\PrivacySubjectRequestService;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

class RuntimeEntityActionService
{
    public function __construct(
        private readonly RuntimeEntityDefinitionResolver $definitions,
        private readonly Connection $connection,
        private readonly RuntimeEntityVersionService $entityVersions,
        private readonly RuntimeCustomCodeService $customCodes,
        private readonly RuntimeLockService $locks,
        private readonly RuntimeConcurrencyGuard $concurrency,
        private readonly RuntimeTransactionService $transactions,
        private readonly RuntimeBusinessRuleRegistry $rules,
        private readonly RuntimeConfiguredRuleExecutor $configuredRules,
        private readonly RuntimeSituationService $situations,
        private readonly BuilderAiSettingsService $builderAiSettings,
        private readonly RuntimeNotificationService $notifications,
        private readonly PrivacySubjectRequestService $privacyRequests,
        private readonly StructuralIntegrityService $integrity,
        private readonly PermissionResolver $permissions,
        private readonly RuntimeEventService $events,
        private readonly ?RuntimeAuditAccessPolicy $auditAccess = null,
    ) {
    }

    public function handle(string $screenId, string $endpointId, array $config, array $payload): array
    {
        $configuredEntity = (string) ($config['entityCode'] ?? '');
        if ($configuredEntity !== '' && array_key_exists('entityCode', $payload) && $payload['entityCode'] !== $configuredEntity) {
            throw new RuntimeHttpException('ENTITY_CONTEXT_CONFLICT', 'A entidade informada diverge do endpoint publicado.', 422);
        }
        $entityCode = $configuredEntity !== '' ? $configuredEntity : (string) ($payload['entityCode'] ?? '');
        $operation = (string) ($config['operation'] ?? $payload['operation'] ?? $endpointId);
        $actionId = (string) ($payload['actionId'] ?? $config['actionId'] ?? $operation);
        $definition = $this->definitions->resolve($entityCode);
        $payload = $this->withResolvedRuntimePayload($definition, $payload, $actionId, $operation);
        $payload = $this->withAuditLogScope($definition, $payload, $operation);

        return match ($operation) {
            'read' => $this->read($definition, $payload),
            'get' => $this->get($definition, $this->payloadId($definition, $payload)),
            'create' => $this->create($screenId, $definition, $payload, $actionId),
            'update', 'edit' => $this->update($screenId, $definition, $this->payloadId($definition, $payload), $payload, $actionId),
            'delete' => $this->delete($screenId, $definition, $this->payloadId($definition, $payload), $payload, $actionId),
            default => throw new RuntimeHttpException('ENTITY_OPERATION_NOT_FOUND', 'Operacao generica da entidade nao encontrada.', 404, [
                'screenId' => $screenId,
                'endpointId' => $endpointId,
                'operation' => $operation,
                'entityCode' => $entityCode,
            ]),
        };
    }

    private function read(array $definition, array $payload): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select(...$this->selectColumns($definition, 't'))
            ->from($definition['quotedTableName'], 't');
        $this->applySubscriberIsolation($qb, $definition, 't');
        $this->applySoftDeleteFilter($qb, $definition, 't', $payload);
        $this->applyRequiredAuditAccess($qb, $definition);
        $this->applyAuditLogScope($qb, $payload['_auditLogScope']);
        $this->applyCustomFilters($qb, $definition, $payload['filters'] ?? []);
        $this->applyKendoFilter($qb, $definition, $payload['filter'] ?? null);

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(*)')->executeQuery()->fetchOne();

        $this->applySort($qb, $definition, $payload['sort'] ?? []);
        $take = max(1, min(500, (int) ($payload['take'] ?? $payload['pageSize'] ?? 20)));
        $skip = max(0, (int) ($payload['skip'] ?? 0));
        $rows = $qb->setFirstResult($skip)->setMaxResults($take)->executeQuery()->fetchAllAssociative();
        $rows = $this->withStoredAuditActors($definition, $rows);
        $data = array_map(fn (array $row) => $this->formatRow($definition, $row), $rows);

        $this->transactions->log($definition['entityCode'] . '.read', 'Listagem executada pelo runtime generico.', metadata: [
            'entityCode' => $definition['entityCode'],
            'total' => $total,
            'count' => count($data),
        ]);

        return [
            'data' => $data,
            'total' => $total,
        ];
    }

    /** Reserved context is accepted only as closed, flat EQ filters on the log entity. */
    private function withAuditLogScope(array $definition, array $payload, string $operation): array
    {
        $invalid = static function (): never {
            throw new RuntimeHttpException('AUDIT_LOG_SCOPE_INVALID', 'Escopo de logs invalido. Use filtros de contexto EQ validos para tela, exclusao ou registro.', 422);
        };
        $containsReserved = function (mixed $value) use (&$containsReserved, $definition): bool {
            if (!is_array($value)) { return false; }
            foreach (['field', 'id'] as $key) {
                if (is_string($value[$key] ?? null) && (in_array($value[$key], ['audit_screen_id', 'audit_operation', 'audit_record_id'], true)
                    || $definition['entityCode'] === 'runtime_transaction_log' && str_starts_with($value[$key], 'audit_'))) { return true; }
            }
            foreach ($value as $child) { if ($containsReserved($child)) { return true; } }
            return false;
        };
        foreach (['filter', 'sort', 'group'] as $key) {
            if ($containsReserved($payload[$key] ?? null)) { $invalid(); }
        }
        $scope = [];
        $filters = $payload['filters'] ?? [];
        if (!is_array($filters) || !array_is_list($filters)) {
            if ($definition['entityCode'] === 'runtime_transaction_log' || $containsReserved($filters)) { $invalid(); }
            $payload['_auditLogScope'] = [];
            return $payload;
        }
        $remaining = [];
        foreach ($filters as $filter) {
            if ($definition['entityCode'] === 'runtime_transaction_log' && (!is_array($filter) || !is_string($filter['field'] ?? null))) { $invalid(); }
            if (!$containsReserved($filter)) { $remaining[] = $filter; continue; }
            $field = $filter['field'] ?? null;
            if ($definition['entityCode'] !== 'runtime_transaction_log' || $operation !== 'read' || !is_string($field)
                || !in_array($field, ['audit_screen_id', 'audit_operation', 'audit_record_id'], true)
                || ($filter['operator'] ?? null) !== 'eq' || isset($scope[$field])
                || array_diff(array_keys($filter), ['id', 'field', 'operator', 'value'])) { $invalid(); }
            $value = $filter['value'] ?? null;
            if ($field === 'audit_screen_id') {
                if (!is_string($value) || !preg_match('/\A[A-Za-z_][A-Za-z0-9_.:-]{0,159}\z/D', $value)) { $invalid(); }
            } elseif ($field === 'audit_operation') {
                if ($value !== 'delete') { $invalid(); }
            } else {
                $termIdentity = is_string($value) && mb_strlen($value) <= 50 && preg_match("/\A[\p{L}0-9.'-]+\z/uD", $value) && preg_match('/\p{L}/u', $value);
                if (!(is_string($value) && preg_match('/\Ap_[A-F0-9]{64}\z/D', $value)) && !$termIdentity) {
                    if (!(is_int($value) || is_string($value) && preg_match('/\A[1-9][0-9]{0,9}\z/D', $value)) || (int) $value < 1 || (int) $value > 2147483647) { $invalid(); }
                    $value = (int) $value;
                }
            }
            $scope[$field] = $value;
        }
        if ($scope && !isset($scope['audit_screen_id'])) { $invalid(); }
        if (isset($scope['audit_record_id'])) {
            $stockTerms = $scope['audit_screen_id'] === 'iol.estoque-termos-busca';
            $payments = in_array($scope['audit_screen_id'], ['ciw.container-pagamentos','ciw.proc-compra-pagamentos'], true);
            if (($stockTerms || $payments) !== is_string($scope['audit_record_id'])) { $invalid(); }
            if ($stockTerms && !(mb_strlen($scope['audit_record_id']) <= 50 && preg_match("/\A[\p{L}0-9.'-]+\z/uD", $scope['audit_record_id']) && preg_match('/\p{L}/u', $scope['audit_record_id']))) { $invalid(); }
            if ($payments && !preg_match('/\Ap_[A-F0-9]{64}\z/D', $scope['audit_record_id'])) { $invalid(); }
        }
        if (($scope['audit_screen_id']??null)==='ciw.proc-compra-itens' && isset($payload['context'])) {
            $ctx=$payload['context'];
            if (!RuntimeProgressAuditSchema::exact($ctx,['proc_compra_id']) || RuntimePurchaseItemAudit::recordId($ctx['proc_compra_id'])===null) { $invalid(); }
            $scope['audit_proc_compra_id']=RuntimePurchaseItemAudit::recordId($ctx['proc_compra_id']);
        }
        $payload['filters'] = $remaining;
        // Always replace any caller-supplied internal representation.
        $payload['_auditLogScope'] = $scope;
        return $payload;
    }

    private function applyAuditLogScope(QueryBuilder $qb, array $scope): void
    {
        if (!$scope) { return; }
        if (($scope['audit_screen_id']??null)==='ciw.proc-compra-despesas') { $this->applyPurchaseExpenseAuditScope($qb,$scope); return; }
        if (in_array($scope['audit_screen_id']??null,['ciw.proc-compra-amostras','ciw.proc-compra-anexos','ciw.proc-compra-documentos'],true)) { $this->applyPurchaseSampleAuditScope($qb,$scope); return; }
        if (($scope['audit_screen_id']??null)==='ciw.proc-compra-itens') { $this->applyPurchaseItemAuditScope($qb,$scope); return; }
        if ((isset($scope['audit_record_id']) || ($scope['audit_screen_id'] ?? null) === 'ciw.processos-compra-med' && isset($scope['audit_operation'])) && $this->applyPurchasePaymentAuditScope($qb, $scope)) { return; }
        if (isset($scope['audit_record_id']) && !isset($scope['audit_operation']) && $this->applyContainerAuditScope($qb, $scope)) { return; }
        $conditions = ['audit_scope.id = t.' . $this->quote('transaction_id'), 'audit_scope.tenant_id = :auditScopeTenant', 'audit_scope.screen_id = :auditScopeScreen'];
        $qb->setParameter('auditScopeTenant', $this->permissions->getTenantId())->setParameter('auditScopeScreen', $scope['audit_screen_id']);
        if (isset($scope['audit_operation'])) {
            // A successful CRUD delete confirms the operation even when its optional snapshot is absent.
            $conditions[] = "audit_scope.status = 'succeeded'";
            $conditions[] = "t." . $this->quote('event_type') . " LIKE '%.delete'";
        }
        if (isset($scope['audit_record_id'])) {
            $payment = in_array($scope['audit_screen_id'], ['ciw.container-pagamentos','iol.estoque-termos-busca'], true);
            $json = 't.' . $this->quote('metadata');
            if ($this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLitePlatform) {
                $confirmed = "COALESCE((json_type($json, '$.audit.available') = 'true' AND json_type($json, '$.audit.version') = 'integer' AND json_extract($json, '$.audit.version') = 1 AND json_extract($json, '$.audit.source') = 'progress-transaction'), FALSE)";
                $record = "json_type($json, '$.audit.recordId') = '".($payment ? 'text' : 'integer')."' AND json_extract($json, '$.audit.recordId') = :auditScopeRecord";
            } else {
                $json = 'CAST(' . $json . ' AS jsonb)';
                $confirmed = "COALESCE(($json #> '{audit,available}' = 'true'::jsonb AND $json #> '{audit,version}' = '1'::jsonb AND $json #>> '{audit,source}' = 'progress-transaction'), FALSE)";
                $record = "jsonb_typeof($json #> '{audit,recordId}') = '".($payment ? 'string' : 'number')."' AND $json #>> '{audit,recordId}' = :auditScopeRecordText";
                $qb->setParameter('auditScopeRecordText', (string) $scope['audit_record_id']);
            }
            $conditions[] = "(($confirmed AND $record) OR (NOT $confirmed AND COALESCE(audit_scope.operation, '') <> 'batch' AND audit_scope.record_id = :auditScopeParentRecord))";
            if ($scope['audit_screen_id'] === 'ciw.cotacoes-bc' && !isset($scope['audit_operation'])) {
                // Processo separado, mesmo registro: alias fechado, confirmado e sem ampliar o tenant.
                $import = $this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLitePlatform
                    ? "json_extract($json, '$.audit.resource') = 'hist_cotacoes_bc' AND json_extract($json, '$.audit.operation') = 'import-bc'"
                    : "$json #>> '{audit,resource}' = 'hist_cotacoes_bc' AND $json #>> '{audit,operation}' = 'import-bc'";
                $conditions[2] = "(audit_scope.screen_id = :auditScopeScreen OR (audit_scope.screen_id = 'ciw.cotacoes-bc-buscar' AND audit_scope.status = 'succeeded' AND t." . $this->quote('event_type') . " = 'hist_cotacoes_bc_progress.api.process' AND $confirmed AND $import))";
            }
            if ($this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLitePlatform) {
                $qb->setParameter('auditScopeRecord', $scope['audit_record_id'], $payment ? \Doctrine\DBAL\ParameterType::STRING : \Doctrine\DBAL\ParameterType::INTEGER);
            }
            $qb->setParameter('auditScopeParentRecord', (string) $scope['audit_record_id']);
        }
        $qb->andWhere('EXISTS (SELECT 1 FROM runtime_transaction audit_scope WHERE ' . implode(' AND ', $conditions) . ')');
    }

    /** Closed purchase screens; actual and initiating parents remain distinct and tenant bound. */
    private function applyPurchasePaymentAuditScope(QueryBuilder $qb, array $scope): bool
    {
        if (!in_array($scope['audit_screen_id'], ['ciw.proc-compra-pagamentos','ciw.processos-compra-financeiro-med','ciw.processos-compra-med'], true)) { return false; }
        $payment = $scope['audit_screen_id'] === 'ciw.proc-compra-pagamentos';
        $headerDeletion = $scope['audit_screen_id'] === 'ciw.processos-compra-med' && ($scope['audit_operation'] ?? null) === 'delete';
        $hasRecord = isset($scope['audit_record_id']);
        if (!$hasRecord && !$headerDeletion) { return false; }
        $column = $payment ? 'paymentKey' : 'procCompraId';
        $json = 't.'.$this->quote('metadata');
        $sqlite = $this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLitePlatform;
        if ($sqlite) {
            $field = static fn(string $name): string => "json_extract($json, '$.audit.$name')";
            $confirmed = "COALESCE((json_type($json, '$.audit.available') = 'true' AND json_type($json, '$.audit.version') = 'integer' AND ".$field('version')." = 1 AND ".$field('source')." = 'progress-transaction'), FALSE)";
            $actual = "json_type($json, '$.audit.procCompraId') = 'integer' AND ".$field('procCompraId')." > 0";
            $paymentIdentity = "$actual AND json_type($json, '$.audit.paymentKey') = 'text'";
            $headerIdentity = "$actual AND json_type($json, '$.audit.originProcCompraId') = 'integer' AND ".$field('originProcCompraId')." > 0 AND json_type($json, '$.audit.recordId') = 'integer' AND ".$field('recordId')." > 0";
            if ($hasRecord) { $qb->setParameter('purchaseAuditRecord', $scope['audit_record_id'], $payment ? \Doctrine\DBAL\ParameterType::STRING : \Doctrine\DBAL\ParameterType::INTEGER); }
        } else {
            $json = 'CAST('.$json.' AS jsonb)';
            $field = static fn(string $name): string => "($json #>> '{audit,$name}')";
            $confirmed = "COALESCE(($json #> '{audit,available}' = 'true'::jsonb AND $json #> '{audit,version}' = '1'::jsonb AND ".$field('source')." = 'progress-transaction'), FALSE)";
            $actual = "jsonb_typeof($json #> '{audit,procCompraId}') = 'number' AND ".$field('procCompraId')." ~ '^[1-9][0-9]{0,9}$'";
            $paymentIdentity = "$actual AND jsonb_typeof($json #> '{audit,paymentKey}') = 'string'";
            $headerIdentity = "$actual AND jsonb_typeof($json #> '{audit,originProcCompraId}') = 'number' AND ".$field('originProcCompraId')." ~ '^[1-9][0-9]{0,9}$' AND jsonb_typeof($json #> '{audit,recordId}') = 'number' AND ".$field('recordId')." ~ '^[1-9][0-9]{0,9}$'";
            if ($hasRecord) { $qb->setParameter('purchaseAuditRecord', (string) $scope['audit_record_id']); }
        }
        $main = $field('resource')." = 'procs_compra' AND ".$field('recordId').' = '.$field('procCompraId').' AND '.$field('originProcCompraId').' = '.$field('procCompraId');
        if ($headerDeletion) {
            // Header deletion navigation excludes physical ledger deletes and payment unlinks.
            $match = "$confirmed AND $headerIdentity AND audit_scope.status = 'succeeded' AND audit_scope.screen_id = 'ciw.processos-compra-med' AND $main AND ".$field('operation')." = 'delete'";
            $fallback = "NOT $confirmed AND audit_scope.screen_id = :purchaseAuditScreen AND audit_scope.status = 'succeeded' AND audit_scope.operation = 'delete' AND t.".$this->quote('event_type')." = 'procs_compra_med_progress.api.delete'";
            if ($hasRecord) { $match .= ' AND '.$field('procCompraId').' = :purchaseAuditRecord'; $fallback .= ' AND audit_scope.record_id = :purchaseAuditParentRecord'; }
        } else {
            $paymentMatch = "$paymentIdentity AND audit_scope.screen_id = 'ciw.proc-compra-pagamentos' AND ".$field($column)." = :purchaseAuditRecord AND ".$field('resource')." IN ('purchase_payment_links','cta_corren_fornec_imp')";
            $headerMatch = $payment ? 'FALSE' : "$headerIdentity AND audit_scope.screen_id = 'ciw.processos-compra-med' AND (($main) OR ".$field('resource')." = 'cta_corren_fornec_imp') AND (".$field('procCompraId').' = :purchaseAuditRecord OR '.$field('originProcCompraId').' = :purchaseAuditRecord)';
            $itemMatch=$payment?'FALSE':$this->purchaseItemLogPredicate($field,$json,$sqlite,'procCompraId','originProcCompraId',':purchaseAuditRecord');
            $expenseMatch=$payment?'FALSE':$this->purchaseExpenseLogPredicate($field,$json,$sqlite,'procCompraId',':purchaseAuditRecord',true);
            $match = "$confirmed AND audit_scope.status = 'succeeded' AND (($paymentMatch) OR ($headerMatch) OR ($itemMatch) OR ($expenseMatch))";
            $fallback = "NOT $confirmed AND audit_scope.screen_id = :purchaseAuditScreen AND COALESCE(audit_scope.operation, '') <> 'batch' AND audit_scope.record_id = :purchaseAuditParentRecord";
            if (isset($scope['audit_operation'])) { $match .= ' AND '.$field('operation')." = 'delete'"; $fallback .= " AND audit_scope.status = 'succeeded' AND t.".$this->quote('event_type')." LIKE '%.delete'"; }
        }
        $qb->setParameter('purchaseAuditScreen', $scope['audit_screen_id'])->setParameter('purchaseAuditTenant', $this->permissions->getTenantId());
        if ($hasRecord) { $qb->setParameter('purchaseAuditParentRecord', (string) $scope['audit_record_id']); }
        $qb->andWhere('EXISTS (SELECT 1 FROM runtime_transaction audit_scope WHERE audit_scope.id = t.'.$this->quote('transaction_id').' AND audit_scope.tenant_id = :purchaseAuditTenant AND (('.$match.') OR ('.$fallback.')))');
        return true;
    }

    /** SQL proof for the registered item transaction source; scalar IDs stay typed on both platforms. */
    /** Shipment events have no collateral effects; match only confirmed native main CRUD. */
    private function purchaseSampleLogPredicate(callable $field,string $json,bool $sqlite,string $actual,string $parameter,?string $onlyScreen=null): string
    {
        $typed=static function(string $name,bool $zero=false)use($field,$json,$sqlite):string {
            $value=$field($name);$pattern=$zero?'^(0|[1-9][0-9]{0,9})$':'^[1-9][0-9]{0,9}$';
            return $sqlite ? "json_type($json, '$.audit.$name') = 'integer' AND $value ".($zero?'>= 0':'> 0')." AND $value <= 2147483647"
                : "jsonb_typeof($json #> '{audit,$name}') = 'number' AND $value ~ '$pattern' AND (length($value) < 10 OR $value <= '2147483647')";
        };
        $confirmed=$sqlite?"json_type($json, '$.audit.available') = 'true' AND json_type($json, '$.audit.version') = 'integer' AND ".$field('version')." = 1 AND ".$field('source')." = 'progress-transaction'":"$json #> '{audit,available}' = 'true'::jsonb AND $json #> '{audit,version}' = '1'::jsonb AND ".$field('source')." = 'progress-transaction'";
        $attachmentScreens=['ciw.proc-compra-anexos','ciw.proc-compra-documentos'];$sources=[];
        foreach (['ciw.proc-compra-amostras'=>['envios_amostras_repres','proc_compra_amostras_progress'],'ciw.proc-compra-anexos'=>['anexos_proc_compra','proc_compra_anexos_progress'],'ciw.proc-compra-documentos'=>['anexos_proc_compra','proc_compra_documentos_progress']] as $screen=>[$resource,$entity]) {
            if ($onlyScreen!==null && $onlyScreen!==$screen && !(in_array($onlyScreen,$attachmentScreens,true)&&in_array($screen,$attachmentScreens,true))) { continue; }
            // Header item0 is never a child of an item or visible under the old item-only audit scope.
            $zero=$screen==='ciw.proc-compra-documentos'&&$onlyScreen!=='ciw.proc-compra-anexos';
            $sources[]="(audit_scope.screen_id = '$screen' AND ".$field('resource')." = '$resource' AND t.".$this->quote('event_type')." = '$entity.api.' || ".$field('operation').' AND ('.$typed('itemProcCompraId',$zero).'))';
        }
        $source=$sources?'('.implode(' OR ',$sources).')':'FALSE';
        $match="($confirmed) AND $source AND audit_scope.operation = 'entity.api.crud' AND audit_scope.status = 'succeeded' AND (".$typed('recordId').') AND ('.$typed('procCompraId').') AND '.$field('operation')." IN ('create','update','delete')";
        if ($parameter!=='') { $match.=' AND '.$field($actual).' = '.$parameter; }
        return $match;
    }

    /** Closed registered expense writers, typed expense/calculation identities, no fallback from unconfirmed audit. */
    private function purchaseExpenseLogPredicate(callable $field,string $json,bool $sqlite,string $actual,string $parameter,bool $parent=false,bool $deletion=false): string
    {
        $typed=static function(string $name)use($field,$json,$sqlite):string{$v=$field($name);return $sqlite
            ? "json_type($json, '$.audit.$name') = 'integer' AND $v > 0 AND $v <= 2147483647"
            : "jsonb_typeof($json #> '{audit,$name}') = 'number' AND $v ~ '^[1-9][0-9]{0,9}$' AND (length($v) < 10 OR $v <= '2147483647')";};
        $confirmed=$sqlite?"json_type($json, '$.audit.available') = 'true' AND json_type($json, '$.audit.version') = 'integer' AND ".$field('version')." = 1 AND ".$field('source')." = 'progress-transaction'":"$json #> '{audit,available}' = 'true'::jsonb AND $json #> '{audit,version}' = '1'::jsonb AND ".$field('source')." = 'progress-transaction'";
        $event='t.'.$this->quote('event_type');
        $manual="audit_scope.screen_id = 'ciw.proc-compra-despesas' AND $event = 'proc_compra_despesas_progress.api.' || ".$field('operation')." AND ".$field('operation')." IN ('create','update','delete')";
        $generated="audit_scope.screen_id = 'ciw.proc-compra-gerar-despesas' AND $event = 'proc_compra_despesas_progress.api.create' AND ".$field('operation')." = 'create'";
        $taxScope="audit_scope.screen_id = 'ciw.proc-compra-calcular-impostos' AND (".$typed('calculationId').")";
        $taxExpense="($taxScope) AND $event = 'proc_compra_despesas_progress.api.' || ".$field('operation')." AND ".$field('operation')." IN ('create','update')";
        $source=$deletion?"($manual) AND ".$field('operation')." = 'delete'":"(($manual) OR ($generated) OR ($taxExpense))";
        $match=$field('resource')." = 'despesas_proc_compra' AND ($source)";
        if($parent&&!$deletion){$match="(($match) OR (audit_scope.screen_id = 'ciw.proc-compra-gerar-despesas' AND $event = 'proc_compra_despesas_progress.api.process' AND ".$field('resource')." = 'procs_compra' AND ".$field('operation')." = 'generate_expenses' AND ".$field('recordId').' = '.$field('procCompraId').'))';}
        if($parent&&!$deletion){
            $taxRoot="($taxScope) AND $event = 'proc_compra_despesas_progress.api.process' AND ".$field('resource')." = 'procs_compra' AND ".$field('operation')." = 'calculate_taxes' AND ".$field('recordId').' = '.$field('procCompraId');
            $taxHeader="($taxScope) AND $event = 'proc_compra_despesas_progress.api.create' AND ".$field('resource')." = 'calcs_imp_proc_compra' AND ".$field('operation')." = 'create' AND ".$field('recordId').' = '.$field('calculationId');
            $match="(($match) OR ($taxRoot) OR ($taxHeader))";
        }
        $match="($confirmed) AND audit_scope.operation = 'entity.api.crud' AND audit_scope.status = 'succeeded' AND (".$typed('recordId').') AND ('.$typed('procCompraId').") AND ($match)";
        if($parameter!==''){$match.=' AND '.$field($actual).' = '.$parameter;}
        return $match;
    }
    private function applyPurchaseExpenseAuditScope(QueryBuilder $qb,array $scope): void
    {
        $sqlite=$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLitePlatform;
        $json='t.'.$this->quote('metadata');if(!$sqlite){$json='CAST('.$json.' AS jsonb)';}
        $field=$sqlite?static fn(string $name):string=>"json_extract($json, '$.audit.$name')":static fn(string $name):string=>"($json #>> '{audit,$name}')";
        $hasRecord=isset($scope['audit_record_id']);$match=$this->purchaseExpenseLogPredicate($field,$json,$sqlite,'recordId',$hasRecord?':expenseAuditRecord':'',false,isset($scope['audit_operation']));
        if($hasRecord){$qb->setParameter('expenseAuditRecord',$sqlite?$scope['audit_record_id']:(string)$scope['audit_record_id'],$sqlite?\Doctrine\DBAL\ParameterType::INTEGER:\Doctrine\DBAL\ParameterType::STRING);}
        $qb->setParameter('expenseAuditTenant',$this->permissions->getTenantId());
        $qb->andWhere('EXISTS (SELECT 1 FROM runtime_transaction audit_scope WHERE audit_scope.id = t.'.$this->quote('transaction_id')." AND audit_scope.tenant_id = :expenseAuditTenant AND ($match))");
    }

    private function applyPurchaseSampleAuditScope(QueryBuilder $qb,array $scope): void
    {
        $sqlite=$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLitePlatform;
        $json='t.'.$this->quote('metadata');if(!$sqlite){$json='CAST('.$json.' AS jsonb)';}
        $field=$sqlite?static fn(string $name):string=>"json_extract($json, '$.audit.$name')":static fn(string $name):string=>"($json #>> '{audit,$name}')";
        $match=$this->purchaseSampleLogPredicate($field,$json,$sqlite,'recordId',isset($scope['audit_record_id'])?':sampleAuditRecord':'',$scope['audit_screen_id']);
        if(isset($scope['audit_record_id'])){$qb->setParameter('sampleAuditRecord',$sqlite?$scope['audit_record_id']:(string)$scope['audit_record_id'],$sqlite?\Doctrine\DBAL\ParameterType::INTEGER:\Doctrine\DBAL\ParameterType::STRING);}
        if(isset($scope['audit_operation'])){$match.=' AND '.$field('operation')." = 'delete'";}
        $qb->setParameter('sampleAuditTenant',$this->permissions->getTenantId());
        $qb->andWhere('EXISTS (SELECT 1 FROM runtime_transaction audit_scope WHERE audit_scope.id = t.'.$this->quote('transaction_id')." AND audit_scope.tenant_id = :sampleAuditTenant AND ($match))");
    }

    private function purchaseItemLogPredicate(callable $field,string $json,bool $sqlite,string $actual,string $origin,string $parameter): string
    {
        $typed=static function(string $name) use($field,$json,$sqlite):string { return $sqlite
            ? "json_type($json, '$.audit.$name') = 'integer' AND ".$field($name).' > 0'
            : "jsonb_typeof($json #> '{audit,$name}') = 'number' AND ".$field($name)." ~ '^[1-9][0-9]{0,9}$'"; };
        $ident=$typed('originProcCompraId').' AND '.$typed('originItemProcCompraId').' AND '.$typed('recordId');
        $main=$field('resource')." = 'itens_proc_compra' AND ".$typed('procCompraId').' AND '.$typed('itemProcCompraId').' AND '.$field('recordId').' = '.$field('itemProcCompraId').' AND '.$field('originItemProcCompraId').' = '.$field('itemProcCompraId').' AND '.$field('originProcCompraId').' = '.$field('procCompraId');
        $resource="(($main) OR ".$field('resource')." IN ('cta_corren_fornec_imp','containers','itens_container','despesas_container','calcs_imp_container'))";
        $match="audit_scope.screen_id = 'ciw.proc-compra-itens' AND $ident AND $resource";
        if ($parameter!=='') { $match.=' AND ((('.$typed($actual).') AND '.$field($actual)." = $parameter) OR (".$field($origin)." = $parameter))"; }
        return "((".$match.") OR (".$this->purchaseSampleLogPredicate($field,$json,$sqlite,$actual,$parameter)."))";
    }

    private function applyPurchaseItemAuditScope(QueryBuilder $qb,array $scope): void
    {
        $sqlite=$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLitePlatform;
        $json='t.'.$this->quote('metadata');if (!$sqlite) { $json='CAST('.$json.' AS jsonb)'; }
        $field=$sqlite?static fn(string $name):string=>"json_extract($json, '$.audit.$name')":static fn(string $name):string=>"($json #>> '{audit,$name}')";
        $confirmed=$sqlite?"json_type($json, '$.audit.available') = 'true' AND json_type($json, '$.audit.version') = 'integer' AND ".$field('version')." = 1 AND ".$field('source')." = 'progress-transaction'":"$json #> '{audit,available}' = 'true'::jsonb AND $json #> '{audit,version}' = '1'::jsonb AND ".$field('source')." = 'progress-transaction'";
        $hasRecord=isset($scope['audit_record_id']);
        $match=$this->purchaseItemLogPredicate($field,$json,$sqlite,'itemProcCompraId','originItemProcCompraId',$hasRecord?':itemAuditRecord':'');
        if ($hasRecord) { $qb->setParameter('itemAuditRecord',$sqlite?$scope['audit_record_id']:(string)$scope['audit_record_id'],$sqlite?\Doctrine\DBAL\ParameterType::INTEGER:\Doctrine\DBAL\ParameterType::STRING); }
        if (isset($scope['audit_operation'])) { $match.=' AND '.$field('resource')." = 'itens_proc_compra' AND ".$field('operation')." = 'delete' AND audit_scope.operation = 'entity.api.crud' AND t.".$this->quote('event_type')." = 'proc_compra_itens_progress.api.delete'"; }
        if (isset($scope['audit_proc_compra_id'])) {
            if (!$hasRecord) { $match.=' AND ('.$field('procCompraId').' = :itemAuditParent OR '.$field('originProcCompraId').' = :itemAuditParent)'; }
            else { $match.=' AND (('.$field('itemProcCompraId').' = :itemAuditRecord AND '.$field('procCompraId').' = :itemAuditParent) OR ('.$field('originItemProcCompraId').' = :itemAuditRecord AND '.$field('originProcCompraId').' = :itemAuditParent))'; }
            $qb->setParameter('itemAuditParent',$sqlite?$scope['audit_proc_compra_id']:(string)$scope['audit_proc_compra_id'],$sqlite?\Doctrine\DBAL\ParameterType::INTEGER:\Doctrine\DBAL\ParameterType::STRING);
        }
        $qb->setParameter('itemAuditTenant',$this->permissions->getTenantId());
        $qb->andWhere('EXISTS (SELECT 1 FROM runtime_transaction audit_scope WHERE audit_scope.id = t.'.$this->quote('transaction_id')." AND audit_scope.tenant_id = :itemAuditTenant AND audit_scope.status = 'succeeded' AND ($confirmed) AND ($match))");
    }

    /** Related records are imported only by the closed container contract; parent tenant is always required. */
    private function applyContainerAuditScope(QueryBuilder $qb, array $scope): bool
    {
        $resources = match ($scope['audit_screen_id']) {
            'ciw.containers', 'ciw.container-painel' => ['containers','container_versions','versoes_item_custo_container','itens_container','despesas_container','calcs_imp_container','cta_corren_fornec_imp','pagtos_container','container_payment_links','nfs_container','cta_corren_desconto','anexos_container','pp_container_permissao','pp_container_saldo_repres','pp_it_container','item_container_custos','item_container_custo_despesas','item_container_custo_nfs','item_container_custo_pgtos','item_container_custo_descontos','cta_corren_custo_distr','item_custos'],
            'ciw.container-pagamentos' => ['container_payment_links','containers','itens_container','despesas_container','calcs_imp_container','cta_corren_fornec_imp'],
            'ciw.container-despesas' => ['despesas_container'],
            'ciw.container-itens' => ['itens_container','item_container_custos','item_custos','cta_corren_custo_distr','cta_corren_fornec_imp'],
            'ciw.container-versoes-custo' => ['container_versions','versoes_item_custo_container','item_container_custos','item_container_custo_despesas','item_container_custo_nfs','item_container_custo_pgtos','item_container_custo_descontos','item_custos','cta_corren_custo_distr'],
            'ciw.container-custos-itens' => ['item_container_custos','item_custos','cta_corren_custo_distr'],
            'ciw.container-anexos' => ['anexos_container'],
            default => null,
        };
        if ($resources === null) { return false; }
        $column = match ($scope['audit_screen_id']) {
            'ciw.containers','ciw.container-painel' => 'containerId',
            'ciw.container-pagamentos' => 'paymentKey',
            'ciw.container-versoes-custo' => 'versionId',
            'ciw.container-itens','ciw.container-custos-itens' => 'itemContainerId',
            default => 'recordId',
        };
        $payment = $column === 'paymentKey';
        $json = 't.'.$this->quote('metadata');
        $sqlite = $this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLitePlatform;
        if ($sqlite) {
            $field = static fn ($key) => "json_extract($json, '$.audit.$key')";
            $confirmed = "json_type($json, '$.audit.available') = 'true' AND json_type($json, '$.audit.version') = 'integer' AND ".$field('version')." = 1 AND ".$field('source')." = 'progress-transaction'";
            $record = "json_type($json, '$.audit.$column') = '".($payment ? 'text' : 'integer')."' AND ".$field($column).' = :containerAuditRecord';
            $qb->setParameter('containerAuditRecord', $scope['audit_record_id'], $payment ? \Doctrine\DBAL\ParameterType::STRING : \Doctrine\DBAL\ParameterType::INTEGER);
        } else {
            $json = 'CAST('.$json.' AS jsonb)';
            $field = static fn ($key) => "($json #>> '{audit,$key}')";
            $confirmed = "$json #> '{audit,available}' = 'true'::jsonb AND $json #> '{audit,version}' = '1'::jsonb AND ".$field('source')." = 'progress-transaction'";
            $record = "jsonb_typeof($json #> '{audit,$column}') = '".($payment ? 'string' : 'number')."' AND ".$field($column).' = :containerAuditRecord';
            $qb->setParameter('containerAuditRecord', (string) $scope['audit_record_id']);
        }
        $related = "COALESCE(($confirmed AND ".$field('scope')." = 'container-panel-v1'), FALSE)";
        $known = implode(',', array_map(static fn ($resource) => "'".$resource."'", $resources));
        $match = "$related AND audit_scope.status = 'succeeded' AND $record AND ".$field('resource')." IN ($known)";
        // Attempts carry only validated request context, never inferred snapshots or confirmed audit flags.
        if ($column === 'containerId') {
            if ($sqlite) {
                $attempt = "json_extract($json, '$.entityCode') IN ('container_processos_progress','container_alocacao_progress') AND json_extract($json, '$.containerContext.version') = 1 AND json_type($json, '$.containerContext.version') = 'integer' AND json_extract($json, '$.containerContext.source') = 'runtime-validated-context' AND json_type($json, '$.containerContext.containerId') = 'integer' AND json_extract($json, '$.containerContext.containerId') = :containerAuditRecord";
            } else {
                $attempt = "$json ->> 'entityCode' IN ('container_processos_progress','container_alocacao_progress') AND $json #> '{containerContext,version}' = '1'::jsonb AND $json #>> '{containerContext,source}' = 'runtime-validated-context' AND jsonb_typeof($json #> '{containerContext,containerId}') = 'number' AND $json #>> '{containerContext,containerId}' = :containerAuditRecord";
            }
            $match = "(($match) OR COALESCE(($attempt), FALSE))";
        }
        $fallback = "NOT $related AND audit_scope.screen_id = :containerAuditScreen AND COALESCE(audit_scope.operation, '') <> 'batch' AND audit_scope.record_id = :containerAuditParentRecord";
        $qb->setParameter('containerAuditScreen', $scope['audit_screen_id'])->setParameter('containerAuditParentRecord', (string) $scope['audit_record_id'])->setParameter('containerAuditTenant', $this->permissions->getTenantId());
        $qb->andWhere('EXISTS (SELECT 1 FROM runtime_transaction audit_scope WHERE audit_scope.id = t.'.$this->quote('transaction_id').' AND audit_scope.tenant_id = :containerAuditTenant AND (('.$match.') OR ('.$fallback.')))');
        return true;
    }

    /** Enrich only authorized rows; parent lookup is bounded and scoped to the current tenant. */
    private function withStoredAuditActors(array $definition, array $rows): array
    {
        if (!in_array($definition['entityCode'], ['runtime_transaction', 'runtime_transaction_log'], true)) {
            return $rows;
        }
        $parentIds = [];
        foreach ($rows as $index => $row) {
            $column = $definition['entityCode'] === 'runtime_transaction' ? 'request_context' : 'metadata';
            $context = $this->auditJsonObject($this->auditRowColumn($definition, $row, $column));
            $actor = is_array($context['actor'] ?? null) ? $context['actor'] : [];
            $rows[$index]['_storedAuditActor'] = $this->storedAuditActor($actor);
            if ($definition['entityCode'] === 'runtime_transaction_log' && $rows[$index]['_storedAuditActor'] === []) {
                $id = $this->auditRowColumn($definition, $row, 'transaction_id');
                if ((is_int($id) && $id > 0) || (is_string($id) && preg_match('/\A[1-9][0-9]{0,17}\z/', $id))) {
                    $parentIds[(string) $id] = (int) $id;
                }
            }
        }
        if ($parentIds !== []) {
            $parents = $this->connection->fetchAllAssociative(
                'SELECT id, request_context FROM runtime_transaction WHERE tenant_id = :tenant AND id IN (:ids)',
                ['tenant' => $this->permissions->getTenantId(), 'ids' => array_slice(array_values($parentIds), 0, 500)],
                ['ids' => ArrayParameterType::INTEGER],
            );
            $actors = [];
            foreach ($parents as $parent) {
                $context = $this->auditJsonObject($parent['request_context']);
                $actors[(string) $parent['id']] = $this->storedAuditActor(is_array($context['actor'] ?? null) ? $context['actor'] : []);
            }
            foreach ($rows as $index => $row) {
                if ($row['_storedAuditActor'] === []) {
                    $id = $this->auditRowColumn($definition, $row, 'transaction_id');
                    $rows[$index]['_storedAuditActor'] = $actors[(string) $id] ?? [];
                }
            }
        }
        return $rows;
    }

    private function auditRowColumn(array $definition, array $row, string $column): mixed
    {
        foreach ($definition['fields'] as $code => $field) {
            if (($field['column'] ?? null) === $column) {
                return $row[$code] ?? null;
            }
        }
        return null;
    }

    private function auditJsonObject(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;
        return is_array($decoded) ? $decoded : [];
    }

    private function storedAuditActor(array $value): array
    {
        if (($value['source'] ?? null) !== 'authenticated-session' || ($value['version'] ?? null) !== 1) {
            return [];
        }
        $actor = [];
        foreach (['userId', 'username', 'displayName'] as $field) {
            if (is_string($value[$field] ?? null) && trim($value[$field]) !== '') {
                $actor[$field] = mb_substr($value[$field], 0, 160);
            }
        }
        return $actor;
    }
    private function get(array $definition, string|int $id): array
    {
        $row = $this->findRow($definition, $id);
        $response = $this->formatRow($definition, $this->withStoredAuditActors($definition, [$row])[0]);
        $this->transactions->log($definition['entityCode'] . '.get', 'Registro consultado pelo runtime generico.', after: $response);

        return $response;
    }

    private function create(string $screenId, array $definition, array $payload, string $actionId): array
    {
        $values = $this->extractValues($definition, $payload, true);
        if ($definition['entityCode'] === 'runtime_notification') {
            $values = $this->notifications->prepareValues($values, true);
            $this->notifications->validateValues($values);
        }
        if ($definition['entityCode'] === 'privacy_subject_request') {
            $values = $this->privacyRequests->prepareSubjectRequestValues($values, true);
        }
        $values = $this->situations->applyCreateDefaults($definition, $values);
        $context = $this->newRuleContext($definition, 'create', $actionId, $payload, $values);
        $this->rules->beforeValidate($context);
        $values = $context->getValues();
        $values = $this->hydrateVersionReferences($definition, $values, []);
        $values = $this->customCodes->applyCreateValues($definition, $values, $payload);
        $this->validateRequiredValues($definition, $values, true);
        $context->setValues($values);
        $this->configuredRules->runPhase('beforeValidate', $context);
        $values = $context->getValues();
        $context->setValues($values);
        $this->rules->beforePersist($context);
        $this->configuredRules->runPhase('beforePersist', $context);
        $values = $context->getValues();
        $situationTransition = $this->situations->validateCreate($definition, $values, $actionId);
        $dbValues = $this->toDatabaseValues($definition, $values);
        $this->injectSubscriberIsolationValues($definition, $dbValues);
        $this->applyTimestampColumns($definition, $dbValues, true);

        $columns = array_keys($dbValues);
        if (!$columns) {
            throw new RuntimeValidationException('ENTITY_VALUES_REQUIRED', 'Informe os dados para gravar.', [
                'status' => 'blocked',
                'title' => 'Inconsistencias encontradas',
                'titleKey' => 'validation.title.inconsistencies',
                'messages' => [[
                    'type' => 'error',
                    'message' => 'Nenhum campo permitido foi informado.',
                    'messageKey' => 'validation.message.no_allowed_fields',
                ]],
            ]);
        }

        $params = [];
        $placeholders = [];
        foreach ($columns as $index => $column) {
            $param = 'v' . $index;
            $params[$param] = $dbValues[$column];
            $placeholders[] = $this->sqlValuePlaceholder($definition, $column, ':' . $param);
        }
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) RETURNING %s',
            $definition['quotedTableName'],
            implode(', ', array_map(fn (string $column) => $this->quote($column), $columns)),
            implode(', ', $placeholders),
            $this->quote($definition['primaryColumn']),
        );
        $id = $this->connection->fetchOne($sql, $params);
        $after = $this->formatRow($definition, $this->findRow($definition, $id));
        $entityVersion = $this->entityVersions->snapshot($definition, $after);
        if ($entityVersion) {
            $after['_runtime']['entityVersionId'] = $entityVersion->getId();
            $after['_runtime']['entityVersionRevision'] = $entityVersion->getRevision();
        }
        $context->setAfter($after);
        if ($definition['entityCode'] === 'runtime_notification') {
            $this->notifications->syncRecipients((int) $id);
        }
        if ($definition['entityCode'] === 'privacy_subject_request') {
            $this->privacyRequests->afterSubjectRequestCreated($after);
        }
        $this->situations->logTransition($definition, $situationTransition, [], $after);
        $this->transactions->log($definition['entityCode'] . '.create', 'Registro incluido pelo runtime generico.', after: $after, metadata: [
            'entityCode' => $definition['entityCode'],
            'fields' => array_keys($values),
            'confirmationToken' => $payload['_runtime']['validationConfirmationToken'] ?? null,
        ]);
        $this->syncStructuralIntegrityAfterWrite($definition, (int) $id, 'runtimeEntityCreate');
        $this->rules->afterPersist($context);
        $this->configuredRules->runPhase('afterPersist', $context);
        $this->rules->afterCommit($context);
        $this->configuredRules->runPhase('afterCommit', $context);

        $after = $this->situations->applyTransitionEffects($after, $situationTransition);
        $this->publishEntityEvent('runtime.entity.created', $screenId, $definition, 'create', null, $after);

        return $after;
    }

    private function update(string $screenId, array $definition, string|int $id, array $payload, string $actionId): array
    {
        $beforeRow = $this->findRow($definition, $id);
        $before = $this->formatRow($definition, $beforeRow);
        $this->locks->validateWriteLock($definition['entityCode'], $id, 'update', $payload);
        $this->concurrency->assertExpectedVersion($definition['entityCode'], 'update', $before['_runtime']['version'] ?? null, $payload);
        $values = $this->extractValues($definition, $payload, false);
        if ($definition['entityCode'] === 'runtime_notification') {
            $values = $this->notifications->prepareValues($values, false);
            $this->notifications->validateValues(array_replace($before, $values));
        }
        if ($definition['entityCode'] === 'privacy_subject_request') {
            $values = $this->privacyRequests->prepareSubjectRequestValues($values, false);
        }
        $context = $this->newRuleContext($definition, 'update', $actionId, $payload, $values, $before);
        $this->rules->beforeValidate($context);
        $values = $context->getValues();
        $values = $this->hydrateVersionReferences($definition, $values, $before);
        $this->validateRequiredValues($definition, $values, false);
        $context->setValues($values);
        $this->configuredRules->runPhase('beforeValidate', $context);
        $values = $context->getValues();
        $context->setValues($values);
        $this->rules->beforePersist($context);
        $this->configuredRules->runPhase('beforePersist', $context);
        $values = $context->getValues();
        $situationTransition = $this->situations->validateUpdate($definition, $before, $values, $actionId);
        $dbValues = $this->toDatabaseValues($definition, $values);
        $this->injectSubscriberIsolationValues($definition, $dbValues);
        $this->applyTimestampColumns($definition, $dbValues, false);

        if (!$dbValues) {
            throw new RuntimeValidationException('ENTITY_VALUES_REQUIRED', 'Informe os dados para gravar.', [
                'status' => 'blocked',
                'title' => 'Inconsistencias encontradas',
                'titleKey' => 'validation.title.inconsistencies',
                'messages' => [[
                    'type' => 'error',
                    'message' => 'Nenhum campo permitido foi informado.',
                    'messageKey' => 'validation.message.no_allowed_fields',
                ]],
            ]);
        }

        $sets = [];
        $params = ['id' => $id];
        foreach ($dbValues as $column => $value) {
            $param = 'v_' . $column;
            $sets[] = $this->quote($column) . ' = ' . $this->sqlValuePlaceholder($definition, $column, ':' . $param);
            $params[$param] = $value;
        }
        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = :id',
            $definition['quotedTableName'],
            implode(', ', $sets),
            $this->quote($definition['primaryColumn']),
        );
        $this->connection->executeStatement($sql, $params);
        $after = $this->formatRow($definition, $this->findRow($definition, $id));
        $entityVersion = $this->entityVersions->snapshot($definition, $after);
        if ($entityVersion) {
            $after['_runtime']['entityVersionId'] = $entityVersion->getId();
            $after['_runtime']['entityVersionRevision'] = $entityVersion->getRevision();
        }
        $context->setAfter($after);
        if ($definition['entityCode'] === 'runtime_notification') {
            $this->notifications->syncRecipients((int) $id);
        }
        $this->situations->logTransition($definition, $situationTransition, $before, $after);
        $this->transactions->log($definition['entityCode'] . '.update', 'Registro alterado pelo runtime generico.', before: $before, after: $after, metadata: [
            'entityCode' => $definition['entityCode'],
            'fields' => array_keys($values),
            'confirmationToken' => $payload['_runtime']['validationConfirmationToken'] ?? null,
        ]);
        $this->syncStructuralIntegrityAfterWrite($definition, (int) $id, 'runtimeEntityUpdate');
        if (!empty($payload['_runtime']['lockToken']) || !empty($payload['lockToken'])) {
            $this->locks->release($payload, 'released');
        }
        $this->rules->afterPersist($context);
        $this->configuredRules->runPhase('afterPersist', $context);
        $this->rules->afterCommit($context);
        $this->configuredRules->runPhase('afterCommit', $context);

        $after = $this->situations->applyTransitionEffects($after, $situationTransition);
        $this->publishEntityEvent('runtime.entity.updated', $screenId, $definition, 'update', $before, $after);
        if ($this->statusChanged($definition, $before, $after)) {
            $this->publishEntityEvent('runtime.entity.status_changed', $screenId, $definition, 'status_changed', $before, $after);
        }

        return $after;
    }

    private function delete(string $screenId, array $definition, string|int $id, array $payload, string $actionId): array
    {
        $before = $this->formatRow($definition, $this->findRow($definition, $id));
        $this->locks->validateWriteLock($definition['entityCode'], $id, 'delete', $payload);
        $this->concurrency->assertExpectedVersion($definition['entityCode'], 'delete', $before['_runtime']['version'] ?? null, $payload);
        $context = $this->newRuleContext($definition, 'delete', $actionId, $payload, [], $before);
        $this->rules->beforeValidate($context);
        $this->configuredRules->runPhase('beforeValidate', $context);
        $this->rules->beforePersist($context);
        $this->configuredRules->runPhase('beforePersist', $context);
        $softDeleted = $this->softDeleteRecord($definition, $id, $payload);
        if (!$softDeleted) {
            $this->connection->delete($definition['tableName'], [
                $definition['primaryColumn'] => $id,
            ]);
        }
        if ($definition['entityCode'] === 'runtime_notification') {
            $this->notifications->deleteRecipients((int) $id);
        }
        $after = $softDeleted ? $this->formatRow($definition, $this->findRow($definition, $id, includeDeleted: true)) : null;
        $this->transactions->log($definition['entityCode'] . '.delete', $softDeleted ? 'Registro excluido logicamente pelo runtime generico.' : 'Registro excluido pelo runtime generico.', before: $before, after: $after ?? [], metadata: [
            'entityCode' => $definition['entityCode'],
            'confirmationToken' => $payload['_runtime']['validationConfirmationToken'] ?? null,
            'deleteMode' => $softDeleted ? 'soft' : 'hard',
        ]);
        if ($softDeleted) {
            $this->syncStructuralIntegrityAfterWrite($definition, (int) $id, 'runtimeEntitySoftDelete');
        } else {
            $this->deleteStructuralIntegrityAfterDelete($definition, (int) $id);
        }
        if (!empty($payload['_runtime']['lockToken']) || !empty($payload['lockToken'])) {
            $this->locks->release($payload, 'released');
        }
        $this->rules->afterPersist($context);
        $this->configuredRules->runPhase('afterPersist', $context);
        $this->rules->afterCommit($context);
        $this->configuredRules->runPhase('afterCommit', $context);
        $this->publishEntityEvent('runtime.entity.deleted', $screenId, $definition, 'delete', $before, $after);

        return ['ok' => true];
    }

    private function softDeleteRecord(array $definition, string|int $id, array $payload): bool
    {
        $config = is_array($definition['softDelete'] ?? null) ? $definition['softDelete'] : [];
        if (($config['enabled'] ?? false) !== true || empty($config['deletedAtColumn'])) {
            return false;
        }

        $values = [
            (string) $config['deletedAtColumn'] => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
        if (!empty($config['deletedByColumn'])) {
            $values[(string) $config['deletedByColumn']] = $this->permissions->getUserId();
        }
        if (!empty($config['reasonColumn'])) {
            $reason = trim((string) ($payload['_runtime']['deleteReason'] ?? $payload['deleteReason'] ?? ''));
            $values[(string) $config['reasonColumn']] = $reason !== '' ? mb_substr($reason, 0, 500) : null;
        }

        $this->connection->update($definition['tableName'], $values, [
            $definition['primaryColumn'] => $id,
        ]);

        return true;
    }

    private function publishEntityEvent(string $eventCode, string $screenId, array $definition, string $operation, ?array $before, ?array $after): void
    {
        $recordId = $after[$definition['primaryKey']] ?? $before[$definition['primaryKey']] ?? null;
        $this->events->publish($eventCode, [
            'tenantId' => $this->permissions->getTenantId(),
            'userId' => $this->permissions->getUserId(),
            'screenId' => $screenId,
            'programCode' => null,
            'entityCode' => $definition['entityCode'],
            'recordId' => $recordId,
            'operation' => $operation,
            'before' => $before,
            'after' => $after,
            'changes' => $this->eventChanges($before ?? [], $after ?? []),
            'transactionId' => $this->transactions->getCurrent()?->getId(),
            'occurredAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ], [
            'source' => 'runtime.crud',
            'screenId' => $screenId,
            'entityCode' => $definition['entityCode'],
            'recordId' => $recordId,
            'operation' => $operation,
        ]);
    }

    private function statusChanged(array $definition, array $before, array $after): bool
    {
        $field = (string) ($definition['situationFieldCode'] ?? 'status');
        return array_key_exists($field, $before) && array_key_exists($field, $after) && $before[$field] !== $after[$field];
    }

    private function eventChanges(array $before, array $after): array
    {
        $changes = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            if (str_starts_with((string) $key, '_')) {
                continue;
            }
            if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
                $changes[$key] = ['before' => $before[$key] ?? null, 'after' => $after[$key] ?? null];
            }
        }

        return $changes;
    }

    private function withResolvedRuntimePayload(array $definition, array $payload, string $actionId, string $operation): array
    {
        $payload['entityCode'] = $definition['entityCode'];
        $payload['actionId'] = $payload['actionId'] ?? $actionId;
        $payload['operation'] = $payload['operation'] ?? $operation;
        if (empty($payload['id'])) {
            $id = $this->payloadId($definition, $payload);
            if ($id !== '') {
                $payload['id'] = $id;
            }
        }

        return $payload;
    }

    private function extractValues(array $definition, array $payload, bool $create): array
    {
        $source = is_array($payload['values'] ?? null) ? $payload['values'] : $payload;
        $values = [];
        $secretParameterCode = $this->resolveSecretParameterCodeFromPayload($definition, $payload, $source);
        foreach ($source as $field => $value) {
            $field = (string) $field;
            if (str_starts_with($field, '_') || !isset($definition['fields'][$field])) {
                continue;
            }
            $config = $definition['fields'][$field];
            if (!$config['writable']) {
                continue;
            }
            if ($value !== null && !is_scalar($value) && $config['dataType'] !== 'json') {
                continue;
            }
            if (!$create && !array_key_exists($field, $source)) {
                continue;
            }
            if ($field === 'value' && $secretParameterCode && $this->builderAiSettings->shouldPreserveSecretValue($secretParameterCode, $value)) {
                continue;
            }
            $values[$field] = $this->normalizeValue($config, $value);
        }

        return $values;
    }

    private function validateRequiredValues(array $definition, array $values, bool $create): void
    {
        $messages = [];
        foreach ($definition['fields'] as $field => $config) {
            if (!$config['writable'] || !$config['required']) {
                continue;
            }
            if (!$create && !array_key_exists($field, $values)) {
                continue;
            }
            if (!array_key_exists($field, $values) || $values[$field] === null || $values[$field] === '') {
                $messages[] = [
                    'field' => $field,
                    'type' => 'error',
                    'message' => ($config['label'] ?: $field) . ' e obrigatorio.',
                    'messageKey' => 'validation.message.field_required',
                    'messageParams' => [
                        'field' => $field,
                        'fieldCode' => $field,
                        'fieldLabel' => $config['label'] ?: $field,
                    ],
                ];
            }
        }

        if ($messages) {
            throw new RuntimeValidationException('BUSINESS_VALIDATION_FAILED', 'Existem inconsistencias no formulario.', [
                'status' => 'blocked',
                'title' => 'Inconsistencias encontradas',
                'titleKey' => 'validation.title.inconsistencies',
                'messages' => $messages,
            ]);
        }
    }

    private function newRuleContext(
        array $definition,
        string $operation,
        string $actionId,
        array $payload,
        array $values,
        array $before = [],
        array $after = [],
    ): RuntimeBusinessRuleContext {
        $context = new RuntimeBusinessRuleContext($definition, $operation, $actionId, $payload, $values, $before, $after);
        $context->setLogger(function (string $eventType, ?string $message, array $beforeData, array $afterData, array $metadata): void {
            $this->transactions->log($eventType, $message, $beforeData, $afterData, $metadata);
        });

        return $context;
    }

    private function validateDeclarativeRules(array $definition, array $values): void
    {
        $rules = $definition['metadata']['rules'] ?? [];
        $messages = [];
        foreach (is_array($rules) ? $rules : [] as $rule) {
            if (!is_array($rule) || ($rule['type'] ?? '') !== 'requiredWhen') {
                continue;
            }
            $when = is_array($rule['when'] ?? null) ? $rule['when'] : [];
            $field = (string) ($rule['field'] ?? '');
            $whenField = (string) ($when['field'] ?? '');
            if ($field === '' || $whenField === '' || !isset($definition['fields'][$field])) {
                continue;
            }
            $expected = $when['equals'] ?? null;
            if (($values[$whenField] ?? null) === $expected && trim((string) ($values[$field] ?? '')) === '') {
                $messages[] = [
                    'field' => $field,
                    'type' => 'error',
                    'message' => (string) ($rule['message'] ?? (($definition['fields'][$field]['label'] ?: $field) . ' e obrigatorio.')),
                    'messageKey' => empty($rule['message']) ? 'validation.message.field_required' : null,
                    'messageParams' => empty($rule['message']) ? [
                        'field' => $field,
                        'fieldCode' => $field,
                        'fieldLabel' => $definition['fields'][$field]['label'] ?: $field,
                    ] : [],
                ];
            }
        }

        if ($messages) {
            throw new RuntimeValidationException('BUSINESS_VALIDATION_FAILED', 'Existem inconsistencias no formulario.', [
                'status' => 'blocked',
                'title' => 'Inconsistencias encontradas',
                'titleKey' => 'validation.title.inconsistencies',
                'messages' => $messages,
            ]);
        }
    }

    private function toDatabaseValues(array $definition, array $values): array
    {
        $dbValues = [];
        foreach ($values as $field => $value) {
            $column = $definition['fields'][$field]['column'] ?? null;
            if (!is_string($column) || $column === '') {
                continue;
            }
            $dbValues[$column] = $value;
        }

        return $dbValues;
    }

    private function applyTimestampColumns(array $definition, array &$dbValues, bool $create): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        if ($create && isset($definition['dbColumns']['created_at']) && !array_key_exists('created_at', $dbValues)) {
            $dbValues['created_at'] = $now;
        }
        if (isset($definition['dbColumns']['updated_at']) && !array_key_exists('updated_at', $dbValues)) {
            $dbValues['updated_at'] = $now;
        }
    }

    private function normalizeValue(array $field, mixed $value): mixed
    {
        if ($value === '') {
            return null;
        }
        if ($value === null) {
            return null;
        }

        return match ($field['dataType']) {
            'integer' => (int) $value,
            'decimal', 'number', 'currency' => (float) $value,
            'boolean' => (filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false) ? 'true' : 'false',
            'date' => (new \DateTimeImmutable((string) $value))->format('Y-m-d'),
            'datetime' => (new \DateTimeImmutable((string) $value))->format('Y-m-d H:i:s'),
            'json' => $this->normalizeJsonValue($field, $value),
            default => (string) $value,
        };
    }

    private function normalizeJsonValue(array $field, mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $text = trim((string) $value);
        if ($text === '') {
            return 'null';
        }

        try {
            json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new RuntimeValidationException('INVALID_JSON_VALUE', 'JSON invalido.', [
                'status' => 'blocked',
                'title' => 'Inconsistencias encontradas',
                'titleKey' => 'validation.title.inconsistencies',
                'messages' => [[
                    'field' => $field['code'] ?? null,
                    'type' => 'error',
                    'message' => ($field['label'] ?: 'Campo JSON') . ' deve conter um JSON valido.',
                    'messageKey' => 'validation.message.json_invalid',
                    'messageParams' => [
                        'field' => $field['code'] ?? null,
                        'fieldCode' => $field['code'] ?? null,
                        'fieldLabel' => $field['label'] ?: 'Campo JSON',
                    ],
                ]],
            ]);
        }

        return $text;
    }

    private function syncStructuralIntegrityAfterWrite(array $definition, int $id, string $source): void
    {
        $tableName = (string) ($definition['tableName'] ?? '');
        if ($id <= 0 || $tableName === '' || !$this->integrity->supportsTableName($tableName)) {
            return;
        }
        $this->integrity->signTarget($tableName, $id, ['source' => $source]);
        $this->integrity->flushPendingChanges();
    }

    private function deleteStructuralIntegrityAfterDelete(array $definition, int $id): void
    {
        $tableName = (string) ($definition['tableName'] ?? '');
        if ($id <= 0 || $tableName === '' || !$this->integrity->supportsTableName($tableName)) {
            return;
        }
        $this->integrity->deleteTarget($tableName, $id);
        $this->integrity->flushPendingChanges();
    }

    private function findRow(array $definition, string|int $id, bool $includeDeleted = false): array
    {
        if ($id === '' || $id === 0 || $id === '0') {
            throw new RuntimeHttpException('RECORD_ID_REQUIRED', 'Informe o registro.', 422);
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select(...$this->selectColumns($definition, 't'))
            ->from($definition['quotedTableName'], 't')
            ->where('t.' . $this->quote($definition['primaryColumn']) . ' = :id')
            ->setParameter('id', $id);
        $this->applySubscriberIsolation($qb, $definition, 't');
        if (!$includeDeleted) {
            $this->applySoftDeleteFilter($qb, $definition, 't');
        }
        $this->applyRequiredAuditAccess($qb, $definition);
        $row = $qb->executeQuery()->fetchAssociative();
        if (!$row) {
            throw new RuntimeHttpException('RECORD_NOT_FOUND', 'Registro nao encontrado.', 404, [
                'entityCode' => $definition['entityCode'],
                'id' => $id,
            ]);
        }

        return $row;
    }

    private function applyRequiredAuditAccess(QueryBuilder $query, array $definition): void
    {
        if (!in_array($definition['entityCode'] ?? '', ['runtime_transaction', 'runtime_transaction_log'], true)) { return; }
        if (!$this->auditAccess) {
            throw new RuntimeHttpException('AUDIT_ACCESS_POLICY_REQUIRED', 'A politica de acesso ao historico nao esta disponivel.', 503);
        }
        $this->auditAccess->applyForCurrentUser($query, $definition);
    }

    private function applySoftDeleteFilter(QueryBuilder $qb, array $definition, string $alias, array $payload = []): void
    {
        $config = is_array($definition['softDelete'] ?? null) ? $definition['softDelete'] : [];
        if (($config['enabled'] ?? false) !== true || empty($config['deletedAtColumn'])) {
            return;
        }
        $runtime = is_array($payload['_runtime'] ?? null) ? $payload['_runtime'] : [];
        $includeDeleted = ($payload['includeDeleted'] ?? false) === true || ($runtime['includeDeleted'] ?? false) === true;
        if ($includeDeleted) {
            return;
        }

        $qb->andWhere($alias . '.' . $this->quote((string) $config['deletedAtColumn']) . ' IS NULL');
    }

    private function selectColumns(array $definition, string $alias): array
    {
        $columns = [];
        $selectedColumns = [];
        foreach ($definition['fields'] as $field => $config) {
            $column = $config['column'] ?? null;
            if ($config['readable'] && is_string($column) && $column !== '') {
                $columns[] = $alias . '.' . $this->quote($column) . ' AS ' . $this->quote($field);
                $selectedColumns[$field] = true;
            }
        }
        foreach ($definition['fields'] as $field => $config) {
            $derived = is_array($config['derivedVersionField'] ?? null) ? $config['derivedVersionField'] : null;
            if (!$derived) {
                continue;
            }
            $versionField = (string) ($derived['versionField'] ?? '');
            if ($versionField === '' || !isset($definition['fields'][$versionField]) || isset($selectedColumns[$versionField])) {
                continue;
            }
            $versionColumn = $definition['fields'][$versionField]['column'] ?? null;
            if (is_string($versionColumn) && $versionColumn !== '') {
                $columns[] = $alias . '.' . $this->quote($versionColumn) . ' AS ' . $this->quote($versionField);
                $selectedColumns[$versionField] = true;
            }
        }
        if (isset($definition['dbColumns']['updated_at']) && !isset($definition['fields']['updated_at'])) {
            $columns[] = $alias . '.' . $this->quote('updated_at') . ' AS _runtime_updated_at';
        }

        return $columns ?: [$alias . '.' . $this->quote($definition['primaryColumn']) . ' AS ' . $this->quote($definition['primaryKey'])];
    }

    private function formatRow(array $definition, array $row): array
    {
        $result = [];
        foreach ($definition['fields'] as $field => $config) {
            if (!$config['readable']) {
                continue;
            }
            if (($config['virtual'] ?? false) === true) {
                $result[$field] = $this->resolveDerivedSnapshotValue($config, $row, $result);
                continue;
            }
            $result[$field] = $this->formatValue($config, $row[$field] ?? null);
        }

        if ($definition['entityCode'] === 'system_parameter_value') {
            $parameterCode = $this->resolveSecretParameterCodeFromRow($result);
            if ($parameterCode && array_key_exists('value', $result)) {
                $result['value'] = $this->builderAiSettings->maskSecretValue($parameterCode, $result['value']);
            }
        }

        if (array_key_exists('_storedAuditActor', $row)) {
            $actor = $row['_storedAuditActor'];
            $result['actor_user_id'] = $actor['userId'] ?? null;
            $result['actor_user_name'] = $actor['displayName'] ?? $actor['username'] ?? $actor['userId'] ?? null;
        }
        $versionSource = (string) ($row['_runtime_updated_at'] ?? json_encode($result, JSON_UNESCAPED_UNICODE));
        $result['_runtime'] = [
            'version' => hash('sha256', $definition['entityCode'] . ':' . ($result[$definition['primaryKey']] ?? '') . ':' . $versionSource),
            'lastModifiedAt' => $this->formatDateTime($row['_runtime_updated_at'] ?? null),
        ];

        return $this->situations->decorateRow($definition, $result);
    }

    private function hydrateVersionReferences(array $definition, array $values, array $before): array
    {
        foreach ($definition['fields'] as $field => $config) {
            $versionReference = is_array($config['versionReference'] ?? null) ? $config['versionReference'] : null;
            if (!$versionReference || !$config['writable']) {
                continue;
            }

            $sourceEntityCode = (string) ($versionReference['sourceEntityCode'] ?? '');
            $sourceIdField = (string) ($versionReference['sourceIdField'] ?? '');
            if ($sourceEntityCode === '' || $sourceIdField === '') {
                continue;
            }

            $sourceId = $values[$sourceIdField] ?? $before[$sourceIdField] ?? null;
            if ($sourceId === null || $sourceId === '') {
                continue;
            }

            $versionId = $this->entityVersions->resolveCurrentVersionId($sourceEntityCode, (string) $sourceId);
            if ($versionId === null) {
                throw new RuntimeValidationException('VERSIONED_REFERENCE_NOT_FOUND', 'Nao existe versao historica publicada para a referencia informada.', [
                    'status' => 'blocked',
                    'title' => 'Inconsistencias encontradas',
                    'titleKey' => 'validation.title.inconsistencies',
                    'messages' => [[
                        'field' => $field,
                        'type' => 'error',
                        'message' => ($config['label'] ?: $field) . ' nao conseguiu localizar a versao historica atual do cadastro relacionado.',
                        'messageKey' => 'validation.message.version_reference_not_found',
                        'messageParams' => [
                            'field' => $field,
                            'fieldCode' => $field,
                            'fieldLabel' => $config['label'] ?: $field,
                        ],
                    ]],
                ]);
            }

            $values[$field] = $versionId;
        }

        return $values;
    }

    private function resolveDerivedSnapshotValue(array $config, array $row, array $result): mixed
    {
        $derived = is_array($config['derivedVersionField'] ?? null) ? $config['derivedVersionField'] : null;
        if (!$derived) {
            return null;
        }

        $versionField = (string) ($derived['versionField'] ?? '');
        $path = (string) ($derived['path'] ?? '');
        if ($versionField === '' || $path === '') {
            return null;
        }

        $versionId = $row[$versionField] ?? $result[$versionField] ?? null;
        if ($versionId === null || $versionId === '') {
            return null;
        }

        return $this->entityVersions->resolveSnapshotValue((int) $versionId, $path);
    }

    private function formatValue(array $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($field['dataType']) {
            'integer' => (int) $value,
            'decimal', 'number', 'currency' => (float) $value,
            'boolean' => (bool) $value,
            'date' => substr((string) $value, 0, 10),
            'json' => $this->formatJsonValue($value),
            default => $value,
        };
    }

    private function formatJsonValue(mixed $value): string
    {
        if (is_string($value)) {
            try {
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                return (string) json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            } catch (\Throwable) {
                return $value;
            }
        }

        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    private function formatDateTime(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }

        return (new \DateTimeImmutable((string) $value))->format(DATE_ATOM);
    }

    private function payloadId(array $definition, array $payload): string|int
    {
        $primaryKey = $definition['primaryKey'];
        foreach ([$primaryKey, 'id', 'recordId'] as $field) {
            $value = $payload[$field] ?? null;
            if (is_scalar($value) && $value !== '') {
                return is_int($value) ? $value : (string) $value;
            }
        }
        foreach (['record', 'values'] as $group) {
            if (!is_array($payload[$group] ?? null)) {
                continue;
            }
            foreach ([$primaryKey, 'id', 'recordId'] as $field) {
                $value = $payload[$group][$field] ?? null;
                if (is_scalar($value) && $value !== '') {
                    return is_int($value) ? $value : (string) $value;
                }
            }
        }

        return '';
    }

    private function resolveSecretParameterCodeFromPayload(array $definition, array $payload, array $source): ?string
    {
        if ($definition['entityCode'] !== 'system_parameter_value') {
            return null;
        }

        $parameterId = $source['parameter_id'] ?? $payload['parameter_id'] ?? null;
        return $this->builderAiSettings->findParameterCodeById($parameterId);
    }

    private function resolveSecretParameterCodeFromRow(array $row): ?string
    {
        $parameterId = $row['parameter_id'] ?? null;
        return $this->builderAiSettings->findParameterCodeById($parameterId);
    }

    private function applySort(QueryBuilder $qb, array $definition, mixed $sort): void
    {
        $items = is_array($sort) ? $sort : [];
        if (!$items) {
            $qb->addOrderBy('t.' . $this->quote($definition['primaryColumn']), 'ASC');
            return;
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $field = (string) ($item['field'] ?? '');
            if (!isset($definition['fields'][$field])) {
                continue;
            }
            $dir = strtolower((string) ($item['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
            $qb->addOrderBy('t.' . $this->quote($definition['fields'][$field]['column']), $dir);
        }
    }

    private function applyCustomFilters(QueryBuilder $qb, array $definition, mixed $filters): void
    {
        if (!is_array($filters)) {
            return;
        }
        $counter = 0;
        foreach ($filters as $filter) {
            if (!is_array($filter)) {
                continue;
            }
            $value = $filter['value'] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $id = (string) ($filter['id'] ?? '');
            $field = (string) ($filter['field'] ?? '');
            $operator = (string) ($filter['operator'] ?? 'contains');
            if ($id === 'busca') {
                $parts = [];
                foreach (['nome', 'email'] as $searchField) {
                    if (isset($definition['fields'][$searchField])) {
                        $param = 'customSearch' . (++$counter);
                        $parts[] = 'LOWER(t.' . $this->quote($definition['fields'][$searchField]['column']) . ') LIKE :' . $param;
                        $qb->setParameter($param, '%' . mb_strtolower((string) $value) . '%');
                    }
                }
                if ($parts) {
                    $qb->andWhere('(' . implode(' OR ', $parts) . ')');
                }
                continue;
            }
            if (!isset($definition['fields'][$field])) {
                continue;
            }
            $param = 'customFilter' . (++$counter);
            $condition = $this->buildCondition($qb, $definition, $field, $operator, $value, $param);
            if ($condition !== null) {
                $qb->andWhere($condition);
            }
        }
    }

    private function applyKendoFilter(QueryBuilder $qb, array $definition, mixed $filter): void
    {
        if (!is_array($filter) || empty($filter['filters'])) {
            return;
        }
        $counter = 0;
        $expression = $this->buildKendoExpression($qb, $definition, $filter, $counter);
        if ($expression !== null) {
            $qb->andWhere($expression);
        }
    }

    private function buildKendoExpression(QueryBuilder $qb, array $definition, array $filter, int &$counter): ?string
    {
        if (isset($filter['filters']) && is_array($filter['filters'])) {
            $parts = [];
            foreach ($filter['filters'] as $child) {
                if (!is_array($child)) {
                    continue;
                }
                $part = $this->buildKendoExpression($qb, $definition, $child, $counter);
                if ($part !== null) {
                    $parts[] = $part;
                }
            }
            if (!$parts) {
                return null;
            }
            $logic = strtolower((string) ($filter['logic'] ?? 'and')) === 'or' ? ' OR ' : ' AND ';
            return '(' . implode($logic, $parts) . ')';
        }

        $field = (string) ($filter['field'] ?? '');
        if (!isset($definition['fields'][$field])) {
            return null;
        }
        $param = 'filter' . (++$counter);
        return $this->buildCondition($qb, $definition, $field, (string) ($filter['operator'] ?? 'eq'), $filter['value'] ?? null, $param);
    }

    private function buildCondition(QueryBuilder $qb, array $definition, string $field, string $operator, mixed $value, string $param): ?string
    {
        $column = 't.' . $this->quote($definition['fields'][$field]['column']);
        $normalized = strtolower($operator);
        if (in_array($normalized, ['isnull', 'isnullorempty'], true)) {
            return $column . ' IS NULL';
        }
        if ($normalized === 'isnotnull') {
            return $column . ' IS NOT NULL';
        }
        if ($normalized === 'isempty') {
            $qb->setParameter($param, '');
            return $column . ' = :' . $param;
        }
        if ($normalized === 'isnotempty') {
            $qb->setParameter($param, '');
            return $column . ' <> :' . $param;
        }
        if (in_array($normalized, ['contains', 'notcontains', 'startswith', 'endswith'], true)) {
            $needle = mb_strtolower((string) $value);
            $pattern = match ($normalized) {
                'startswith' => $needle . '%',
                'endswith' => '%' . $needle,
                default => '%' . $needle . '%',
            };
            $qb->setParameter($param, $pattern);
            $condition = 'LOWER(' . $column . ') LIKE :' . $param;
            return $normalized === 'notcontains' ? 'NOT (' . $condition . ')' : $condition;
        }
        if (in_array($normalized, ['neq', 'gte', 'lte', 'gt', 'lt'], true)) {
            $qb->setParameter($param, $this->normalizeValue($definition['fields'][$field], $value));
            $operatorSql = ['neq' => '<>', 'gte' => '>=', 'lte' => '<=', 'gt' => '>', 'lt' => '<'][$normalized];
            return $column . ' ' . $operatorSql . ' :' . $param;
        }

        $qb->setParameter($param, $this->normalizeValue($definition['fields'][$field], $value));
        return $column . ' = :' . $param;
    }

    private function quote(string $identifier): string
    {
        return $this->connection->quoteSingleIdentifier($identifier);
    }

    private function applySubscriberIsolation(QueryBuilder $qb, array $definition, string $alias): void
    {
        if ($definition['entityCode'] === 'runtime_transaction_log') {
            $qb->andWhere('EXISTS (SELECT 1 FROM runtime_transaction audit_parent WHERE audit_parent.id = ' . $alias . '.' . $this->quote('transaction_id') . ' AND audit_parent.tenant_id = :runtimeLogTenantId)');
            $qb->setParameter('runtimeLogTenantId', $this->permissions->getTenantId());
        }
        $config = is_array($definition['subscriberIsolation'] ?? null) ? $definition['subscriberIsolation'] : [];
        if (($config['enabled'] ?? false) !== true) {
            return;
        }

        $column = (string) ($config['column'] ?? '');
        if ($column === '') {
            return;
        }

        $qb->andWhere($alias . '.' . $this->quote($column) . ' = :runtimeTenantId');
        $qb->setParameter('runtimeTenantId', $this->permissions->getTenantId());
    }

    private function injectSubscriberIsolationValues(array $definition, array &$dbValues): void
    {
        $config = is_array($definition['subscriberIsolation'] ?? null) ? $definition['subscriberIsolation'] : [];
        if (($config['enabled'] ?? false) !== true) {
            return;
        }

        $column = (string) ($config['column'] ?? '');
        if ($column === '') {
            return;
        }

        $dbValues[$column] = $this->permissions->getTenantId();
    }

    private function sqlValuePlaceholder(array $definition, string $column, string $placeholder): string
    {
        foreach ($definition['fields'] as $field) {
            if (($field['column'] ?? null) === $column && ($field['dataType'] ?? null) === 'json') {
                return 'CAST(' . $placeholder . ' AS JSON)';
            }
        }

        return $placeholder;
    }
}

