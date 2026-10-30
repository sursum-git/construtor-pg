<?php
namespace App\Runtime;

/** Closed contract for the registered container writers. Response identities never select a source. */
final class RuntimeContainerAudit
{
    public static function supports(array $definition): bool
    {
        return in_array($definition['entityCode'] ?? '', ['container_painel_progress','container_despesas_progress','container_processos_progress','container_despesas_selecao_progress','container_itens_progress','container_pagamentos_progress'], true);
    }

    public static function resource(array $definition, array $endpoint, string $operation): ?string
    {
        if (($definition['apiSource']['responseContract'] ?? null) !== 'sursum-programs' || ($endpoint['method'] ?? null) !== 'POST') { return null; }
        $contract = match ($definition['entityCode'] ?? '') {
            'container_painel_progress' => ['motor_progress_container_cabecalho','containers-cabecalho','container_id','containers'],
            'container_despesas_progress' => ['motor_progress_container_despesas','containers-despesas','despesa_container_id','container-despesas'],
            'container_itens_progress' => ['motor_progress_container_itens','containers-itens','item_container_id','container-itens'],
            'container_pagamentos_progress' => ['motor_progress_container_pagamentos','containers-pagamentos','pagamento_key','container-pagamentos'],
            'container_processos_progress' => ['motor_progress_container_processos','containers-processos','container_id','container_processes'],
            'container_despesas_selecao_progress' => ['motor_progress_container_processos','containers-processos','container_id','container_processes'],
            default => null,
        };
        if (!$contract || ($definition['primaryKey'] ?? '') !== $contract[2] || ($definition['apiBinding']['sourceCode'] ?? '') !== $contract[0]) { return null; }
        $path = parse_url((string) ($endpoint['url'] ?? ''), PHP_URL_PATH);
        $template = $endpoint['bodyTemplate'] ?? [];
        $resource = $contract[3] === 'container_processes' ? (['gerar'=>'versoes','fechar'=>'custos','sincronizar'=>'impostos','calcular_impostos'=>'impostos','selecionar_despesas'=>'impostos'][$operation] ?? null) : $contract[3];
        if (!$resource || $contract[3] !== 'container_processes' && !in_array($operation, $contract[3] === 'container-pagamentos' ? ['create','delete'] : ['create','update','delete'], true)
            || !in_array($path, ['/SursumPrograms/'.$contract[1],'/med/web/SursumPrograms/'.$contract[1]], true)
            || !is_array($template) || count($template) !== 2 || ($template['resource'] ?? null) !== $resource || ($template['operation'] ?? null) !== $operation) { return null; }
        return $contract[3];
    }

    public static function inspect(array $definition, array $endpoint, array $body, string $operation, mixed $expectedId, mixed $expectedContainer = null): array
    {
        $invalid = static fn (string $reason='invalid'): array => ['before'=>[], 'after'=>[], 'events'=>[], 'metadata'=>['available'=>false,'reason'=>$reason]];
        $resource = self::resource($definition, $endpoint, $operation);
        $audit = $body['audit'] ?? null;
        if ($resource === null) { return $invalid(); }
        if (!array_key_exists('audit', $body)) { return $invalid('not_provided'); }
        if (($body['success'] ?? null) !== true || isset($body['error']) || !is_array($audit) || array_is_list($audit)
            || array_diff(array_keys($audit), ['version','source','resource','operation','recordId','container_id','before','after','master','changes','warnings','actor','request_id'])
            || ($audit['version'] ?? null) !== 1 || ($audit['source'] ?? null) !== 'progress-transaction'
            || ($audit['resource'] ?? null) !== $resource || ($audit['operation'] ?? null) !== $operation || !($resource === 'container-pagamentos' ? self::paymentId($audit['recordId'] ?? null) : self::id($audit['recordId'] ?? null))
            || $expectedId !== null && (string) $expectedId !== (string) $audit['recordId']) { return $invalid(); }
        $cid = $resource === 'containers' ? $audit['recordId'] : ($audit['container_id'] ?? null);
        if (!self::id($cid) || $expectedContainer !== null && (string) $expectedContainer !== (string) $cid
            || $resource === 'container_processes' && $audit['recordId'] !== $cid
            || !is_array($audit['changes'] ?? null) || !array_is_list($audit['changes']) || count($audit['changes']) > 50000) { return $invalid(); }
        if (isset($audit['actor']) && (!is_array($audit['actor']) || array_diff(array_keys($audit['actor']), ['login','id']))) { return $invalid(); }
        if (isset($audit['warnings']) && (!is_array($audit['warnings']) || !array_is_list($audit['warnings']) || count($audit['warnings']) > 1000)) { return $invalid(); }
        if (array_key_exists('request_id', $audit) && ($resource !== 'container_processes' || !is_string($audit['request_id']) || mb_strlen($audit['request_id']) > 128)) { return $invalid(); }
        $mainResource = match($resource) {'container-despesas'=>'despesas_container','container-itens'=>'itens_container','container-pagamentos'=>'container_payment_links',default=>'containers'};
        $mainOperation = $resource === 'container_processes' ? 'update' : $operation;
        $main = self::event($mainResource, $mainOperation, $audit['recordId'], $audit['before'] ?? null, $audit['after'] ?? null, $cid);
        if ($main === null) { return $invalid(); }
        $events = [];
        if ($resource === 'container-itens') {
            $purchaseId = null;
            foreach ([$main['before'],$main['after']] as $snapshot) {
                if ($snapshot === []) { continue; }
                if (!self::id($snapshot['item_proc_compra_id'] ?? null) || $purchaseId !== null && $purchaseId !== $snapshot['item_proc_compra_id']) { return $invalid(); }
                $purchaseId = $snapshot['item_proc_compra_id'];
            }
        }
        if (in_array($resource, ['container-despesas','container-itens','container-pagamentos'], true)) {
            $master = $audit['master'] ?? null;
            if (!is_array($master) || array_diff(array_keys($master), ['before','after'])) { return $invalid(); }
            $event = self::event('containers', 'update', $cid, $master['before'] ?? null, $master['after'] ?? null, $cid);
            if ($event === null) { return $invalid(); } $events[] = $event;
        } elseif (isset($audit['master'])) { return $invalid(); }
        $allowed = in_array($resource, ['container-itens','container-pagamentos'], true) ? ['itens_container','despesas_container','calcs_imp_container','cta_corren_fornec_imp'] : ($resource === 'container-despesas' ? ['despesas_container','calcs_imp_container'] : ($resource === 'containers'
            ? ['itens_container','despesas_container','calcs_imp_container','cta_corren_fornec_imp','pagtos_container','nfs_container','cta_corren_desconto','anexos_container','versoes_item_custo_container','pp_container_permissao','pp_container_saldo_repres','pp_it_container','item_container_custos']
            : ['containers','container_versions','item_container_custos','item_container_custo_despesas','item_container_custo_nfs','item_container_custo_pgtos','item_container_custo_descontos','despesas_container','calcs_imp_container','cta_corren_custo_distr','item_custos']));
        $versionAuxiliaries = ['item_container_custo_despesas','item_container_custo_nfs','item_container_custo_pgtos','item_container_custo_descontos'];
        if ($resource === 'containers' && $operation === 'delete') {
            $allowed = array_merge($allowed, $versionAuxiliaries);
        }
        $seen = [];
        foreach ($audit['changes'] as $change) {
            if (!is_array($change) || array_diff(array_keys($change), ['resource','operation','recordId','before','after']) || !in_array($change['resource'] ?? null, $allowed, true) || !is_string($change['operation'] ?? null)) { return $invalid(); }
            if ($resource === 'containers' && in_array($change['resource'], $versionAuxiliaries, true) && $change['operation'] !== 'delete') { return $invalid(); }
            $event = self::event($change['resource'], $change['operation'] ?? '', $change['recordId'] ?? null, $change['before'] ?? null, $change['after'] ?? null, $cid);
            if ($event === null) { return $invalid(); }
            if (in_array($resource, ['container-itens','container-pagamentos'], true) && $change['resource'] === 'cta_corren_fornec_imp') {
                $row=$event['after'];
                if ($change['operation'] !== 'create' || !in_array($row['cod_tipo_movto'] ?? null,$resource === 'container-pagamentos' ? [4,5] : [4,5,6,10],true)
                    || !self::id($row['item_proc_compra_id'] ?? null) || !self::id($row['fornecedor_id'] ?? null)
                    || ($row['cod_tipo_movto'] === 4 ? ($row['item_container_id'] ?? null) !== 0 : !self::id($row['item_container_id'] ?? null))
                    || $resource === 'container-pagamentos' && (!self::id($row['num_id_tit_ap'] ?? null) || ($row['transacao_id'] ?? null) !== 0)) { return $invalid(); }
            }
            $key = $change['resource'].':'.(string) $change['recordId'];
            if (isset($seen[$key])) { return $invalid(); } $seen[$key] = true;
            if ($change['resource'] === $mainResource && $change['recordId'] === $audit['recordId']) {
                if ($event['before'] !== $main['before'] || $event['after'] !== $main['after'] || $event['metadata']['operation'] !== $mainOperation) { return $invalid(); }
                continue;
            }
            $events[] = $event;
        }
        if ($resource === 'containers' && $operation === 'delete') {
            // An auxiliary FK is proven only by the matching version deleted in this same confirmed container envelope.
            $deletedVersions = [];
            foreach ($events as $event) {
                if ($event['metadata']['resource'] === 'versoes_item_custo_container' && $event['metadata']['operation'] === 'delete') {
                    $deletedVersions[$event['metadata']['recordId']] = true;
                }
            }
            foreach ($events as $event) {
                if (in_array($event['metadata']['resource'], $versionAuxiliaries, true)
                    && !isset($deletedVersions[$event['metadata']['versionId'] ?? 0])) { return $invalid(); }
            }
        }
        // Distribution type 3 has no direct item: resolve its physical snapshot FK only through this same confirmed audit.
        $snapshotLinks = [];
        foreach ($events as $event) {
            if ($event['metadata']['resource'] === 'item_container_custos' && isset($event['metadata']['itemContainerId'], $event['metadata']['versionId'])) {
                $snapshotLinks[$event['metadata']['recordId']] = array_intersect_key($event['metadata'], ['itemContainerId'=>true,'versionId'=>true]);
            }
        }
        foreach ($events as &$event) {
            if ($event['metadata']['resource'] !== 'cta_corren_custo_distr') { continue; }
            foreach ([$event['before'], $event['after']] as $snapshot) {
                if ($snapshot === [] || $snapshot['cod_tipo_movto'] !== 3) { continue; }
                $link = $snapshotLinks[$snapshot['item_container_custo_id']] ?? null;
                if ($link === null) { return $invalid(); }
                foreach ($link as $key=>$value) {
                    if (isset($event['metadata'][$key]) && $event['metadata'][$key] !== $value) { return $invalid(); }
                    $event['metadata'][$key] = $value;
                }
            }
        }
        unset($event);
        // Resolve internal cost rows through the immutable version-item links in this same confirmed transaction.
        $costLinks = [];
        foreach ($events as $event) {
            if ($event['metadata']['resource'] !== 'item_container_custos') { continue; }
            foreach ([$event['before'],$event['after']] as $snapshot) {
                if (!self::id($snapshot['item_custo_id'] ?? null) || !isset($event['metadata']['itemContainerId'],$event['metadata']['versionId'])) { continue; }
                $link = array_intersect_key($event['metadata'], ['itemContainerId'=>true,'versionId'=>true]);
                $costId = $snapshot['item_custo_id'];
                if (isset($costLinks[$costId]) && $costLinks[$costId] !== $link) { return $invalid(); }
                $costLinks[$costId] = $link;
            }
        }
        foreach ($events as &$event) {
            if ($event['metadata']['resource'] === 'item_custos' && isset($costLinks[$event['metadata']['recordId']])) {
                $event['metadata'] += $costLinks[$event['metadata']['recordId']];
            }
        }
        unset($event);
        if ($resource === 'container-pagamentos') {
            // Origin is derived only from the validated virtual main identity, never child response metadata.
            $main['metadata']['paymentKey'] = $audit['recordId'];
            foreach ($events as &$event) { $event['metadata']['paymentKey'] = $audit['recordId']; }
            unset($event);
        }
        return ['before'=>$main['before'],'after'=>$main['after'],'events'=>$events,'metadata'=>$main['metadata']];
    }

    private static function paymentId(mixed $id): bool { return is_string($id) && preg_match('/\Ap_[A-F0-9]{64}\z/D', $id) === 1; }

    private static function paymentSnapshot(array $row, string $id): bool
    {
        if (!self::id($row['num_id_tit_ap'] ?? null) || !is_string($row['cod_estab'] ?? null) || trim($row['cod_estab']) === '' || ($row['pagamento_key'] ?? null) !== $id) { return false; }
        $part = static fn(string $value): string => 'V'.mb_strlen($value, 'UTF-8').':'.$value;
        $key = 'p_'.strtoupper(hash('sha256', $part((string) $row['container_id']).$part($row['cod_estab']).$part((string) $row['num_id_tit_ap'])));
        return hash_equals($key, $id);
    }

    private static function id(mixed $id): bool { return is_int($id) && $id > 0 && $id <= 2147483647; }

    /** Pure, closed fiscal proof helper. Grants no binding or authorization. */
    public static function confirmedFiscalEvent(array $change): ?array
    { return self::confirmedPhysicalEvent($change,['containers','itens_container','despesas_container','calcs_imp_container']); }

    /** Pure proof helper for the atomic allocation profile; does not grant a route. */
    public static function confirmedAllocationEvent(array $change): ?array
    { return self::confirmedPhysicalEvent($change,['containers','itens_container','despesas_container','calcs_imp_container','cta_corren_fornec_imp']); }

    private static function confirmedPhysicalEvent(array $change,array $allowed): ?array
    {
        if (!is_string($change['operation']??null) || !self::id($change['recordId']??null)) { return null; }
        $resource=$change['resource']??null;
        if (!in_array($resource,$allowed,true)) { return null; }
        $schema=self::SCHEMAS[$resource];$types=['container_id'=>'id'];
        foreach ($schema['fields'] as $type=>$names) {
            foreach (explode(',',$names) as $name) { $types[$name]=['type'=>match($type){'character'=>'string','logical'=>'boolean',default=>$type},'nullable'=>true]; }
        }
        $cid=null;
        foreach (['before','after'] as $name) {
            $row=$change[$name]??null;if ($row===[]) { continue; }
            if (!RuntimeProgressAuditSchema::row($row,$types) || !self::id($row['container_id']) || $cid!==null && $cid!==$row['container_id']) { return null; }
            $cid=$row['container_id'];
        }
        $event=$cid===null ? null : self::event($resource,$change['operation']??'',$change['recordId']??null,$change['before']??null,$change['after']??null,$cid);
        if ($event!==null && $resource==='itens_container') {
            $owner=null;
            foreach ([$event['before'],$event['after']] as $row) {
                if ($row===[]) { continue; }
                if (!self::id($row['item_proc_compra_id']) || $owner!==null && $owner!==$row['item_proc_compra_id']) { return null; }
                $owner=$row['item_proc_compra_id'];
            }
            $event['metadata']['itemProcCompraId']=$owner;
        }
        return $event;
    }

    private static function event(string $resource, string $operation, mixed $id, mixed $before, mixed $after, int $cid): ?array
    {
        $schema = self::SCHEMAS[$resource] ?? null;
        if (!$schema || !in_array($operation, ['create','update','delete'], true) || !is_array($before) || !is_array($after)
            || $operation === 'create' && $before !== [] || $operation === 'delete' && $after !== []) { return null; }
        $keys = explode(',', $schema['key']);
        if ($resource === 'container_payment_links' ? !self::paymentId($id) : (count($keys) === 1 ? !self::id($id) : !is_string($id) || strlen($id) > 2000)) { return null; }
        foreach ([$before,$after] as $snapshot) {
            if ($snapshot === []) { continue; }
            if (array_is_list($snapshot) || ($snapshot['container_id'] ?? (in_array($resource, ['pp_container_permissao','pp_container_saldo_repres'], true) ? ($snapshot['nr_container'] ?? null) : null)) !== $cid) { return null; }
            $types = ['container_id'=>'integer'];
            foreach ($schema['fields'] as $type=>$names) { foreach (explode(',', $names) as $name) { $types[$name] = $type; } }
            if (array_diff_key($snapshot, $types)) { return null; }
            if ($resource === 'container_payment_links' && (array_diff_key($types, $snapshot) || !self::paymentSnapshot($snapshot, $id))) { return null; }
            foreach ($snapshot as $name=>$value) {
                if ($value === null) { continue; }
                $valid = match ($types[$name]) {
                    'integer' => is_int($value) && $value >= -2147483648 && $value <= 2147483647,
                    'int64' => is_int($value),
                    'decimal' => (is_int($value) || is_float($value)) && is_finite((float) $value),
                    'logical' => is_bool($value),
                    default => is_string($value) && mb_strlen($value) <= 32000,
                }; if (!$valid) { return null; }
            }
            if ($resource === 'cta_corren_custo_distr') {
                $movement = $snapshot['cod_tipo_movto'] ?? null;
                $directItem = $snapshot['item_container_id'] ?? null;
                $costSnapshot = $snapshot['item_container_custo_id'] ?? null;
                if ($movement === 3 ? $directItem !== 0 || !self::id($costSnapshot)
                    : $movement !== 4 || !self::id($directItem) || $costSnapshot !== 0) { return null; }
            }
            foreach ($keys as $key) { if (!array_key_exists($key, $snapshot) || $snapshot[$key] === null) { return null; } }
            if (count($keys) === 1) { if ($snapshot[$keys[0]] !== $id) { return null; } }
            else {
                $decoded = json_decode($id, true);
                if (in_array($resource, ['nfs_container','pp_container_permissao','pp_container_saldo_repres'], true)) {
                    if (!is_array($decoded) || array_is_list($decoded) || array_diff(array_keys($decoded), $keys) || count($decoded) !== count($keys)) { return null; }
                    foreach ($keys as $key) { if ($decoded[$key] !== $snapshot[$key]) { return null; } }
                } elseif (!is_array($decoded) || !array_is_list($decoded) || $decoded !== array_map(static fn ($key) => $snapshot[$key], $keys)) { return null; }
            }
        }
        if ($operation !== 'create' && $before === [] || $operation !== 'delete' && $after === []) { return null; }
        $links = [];
        foreach (['item_container_id'=>'itemContainerId','versao_id'=>'versionId'] as $key=>$target) {
            foreach ([$before,$after] as $snapshot) {
                if (!array_key_exists($key, $snapshot)) { continue; }
                // Only physical distribution type 3 uses item=0 plus a positive snapshot FK; inspect() proves that FK in the same audit.
                if ($resource === 'cta_corren_custo_distr' && $key === 'item_container_id' && $snapshot['cod_tipo_movto'] === 3
                    && $snapshot[$key] === 0 && self::id($snapshot['item_container_custo_id'])) { continue; }
                // These three physical records may belong to the container without an item FK.
                if ($key === 'item_container_id' && in_array($resource, ['despesas_container','anexos_container','cta_corren_fornec_imp'], true)
                    && in_array($snapshot[$key], [0, null], true)) { continue; }
                if (!self::id($snapshot[$key]) || isset($links[$target]) && $links[$target] !== $snapshot[$key]) { return null; }
                $links[$target] = $snapshot[$key];
            }
        }
        return ['before'=>$before,'after'=>$after,'metadata'=>['available'=>true,'version'=>1,'source'=>'progress-transaction','resource'=>$resource,'operation'=>$operation,'recordId'=>$id,'containerId'=>$cid,'scope'=>'container-panel-v1'] + $links];
    }

    // Fixed field/type allowlist from the physical dictionaries reviewed with the ABL writers.
    private const SCHEMAS = [
        'containers'=>['key'=>'container_id','fields'=>['character'=>'cod_estabel,nome_ab_forn,comprador,narrativa,usuario,char_1,medidas_container,ordem_busca_dolar_compra,ordem_busca_dolar_di,cod_depos', 'integer'=>'container_id,num_pedido,cod_emit_forn,situacao,int_1,ind_dt_embarque,situacao_container_id,ind_dt_prev_chegada,cod_agente_cargas,cod_armador,cod_tipo_calculo,cod_tipo_proj_dolar,estacao,num_origem', 'date'=>'dt_compra,dt_prev_chegada,dt_recebimento,data_1,dt_prev_embarque,dt_real_embarque,dt_prev_chegada_loja', 'decimal'=>'dec_1,vlr_frete,vlr_seguro,vlr_despesas,vl_dolar_venda,vl_dolar_compra,perc_custo,vl_dolar_di,vl_proj_dolar,ptax', 'logical'=>'log_1,exclusivo,log_liberar_custo_realizado,log_pagto_final,log_gerar_saldo_a_pagar,log_gerar_distr_custo,log_versao_custo_desatualizada,log_rota_indefinida', 'datetime'=>'dt_hr_ult_alt_versao_custo']],
        'container_versions'=>['key'=>'versao_id','fields'=>['integer'=>'versao_id,ind_sit_versao,tipo_versao_id,container_id,usuario_id,situacao_container_id,cod_tipo_cotacao', 'datetime-tz'=>'dt_hr_versao', 'character'=>'cod_usuario,descricao,desc_sit_container,hist_dolar', 'decimal'=>'vl_dolar_compra,vl_desconto_utiliz_dolar,vl_desconto_utiliz_real,vl_dolar_medio,vl_dolar_di,vl_cotacao,perc_fixo_container', 'logical'=>'log_gerar_saldo_a_pagar,log_gerar_distr_custo', 'date'=>'data_ref_cotacao']],
        'versoes_item_custo_container'=>['key'=>'versao_id','fields'=>['integer'=>'versao_id,ind_sit_versao,tipo_versao_id,container_id,usuario_id,situacao_container_id,cod_tipo_cotacao', 'datetime-tz'=>'dt_hr_versao', 'character'=>'cod_usuario,descricao,desc_sit_container,hist_dolar', 'decimal'=>'vl_dolar_compra,vl_desconto_utiliz_dolar,vl_desconto_utiliz_real,vl_dolar_medio,vl_dolar_di,vl_cotacao,perc_fixo_container', 'logical'=>'log_gerar_saldo_a_pagar,log_gerar_distr_custo', 'date'=>'data_ref_cotacao']],
        'itens_container'=>['key'=>'item_container_id','fields'=>['integer'=>'item_container_id,container_id,item_proc_compra_id,cod_tipo_calculo', 'decimal'=>'qt_alocada,qt_confirm_emb,qt_recbto_fisico,perc_custo,perc_icms,perc_ipi,preco_liquida_ima', 'logical'=>'log_rec_doc_prazo_emb,log_calc_icms,log_calc_ipi', 'character'=>'observacao']],
        'despesas_container'=>['key'=>'despesa_container_id','fields'=>['integer'=>'despesa_container_id,despesa_id,container_id,calc_imp_container_id,cta_corren_custo_distr_id,item_container_id', 'decimal'=>'vl_prev,vl_base_imp,vl_negociado,vl_proforma,perc_calc', 'logical'=>'log_utilizado,log_manual', 'character'=>'memoria_calculo']],
        'calcs_imp_container'=>['key'=>'calc_imp_container_id','fields'=>['integer'=>'container_id,usuario_id,memoria_calculo,calc_imp_container_id', 'datetime'=>'dt_hr_calculo', 'character'=>'desc_memoria_calc']],
        'cta_corren_fornec_imp'=>['key'=>'cta_corren_fornec_imp_id','fields'=>['integer'=>'cta_corren_fornec_imp_id,fornecedor_id,cod_tipo_movto,cod_acao,num_id_tit_ap,item_proc_compra_id,item_container_id,transacao_id,container_id', 'date'=>'data', 'character'=>'historico,login', 'decimal'=>'vl_lancto', 'datetime'=>'data_hora']],
        'container_payment_links'=>['key'=>'pagamento_key','fields'=>['character'=>'pagamento_key,cod_estab', 'integer'=>'num_id_tit_ap,container_id', 'decimal'=>'vl_pago_em_dolar,vl_pago_em_real,vl_aprop_em_dolar,vl_cotacao_dolar', 'date'=>'dt_pagto']],
        'pagtos_container'=>['key'=>'num_id_tit_ap','fields'=>['integer'=>'num_id_tit_ap,container_id', 'decimal'=>'vl_pago_em_dolar,vl_pago_em_real,vl_aprop_em_dolar,vl_cotacao_dolar', 'date'=>'dt_pagto']],
        'nfs_container'=>['key'=>'documento,serie,estab,nat_operacao,cod_emitente,container_id','fields'=>['character'=>'documento,serie,estab,nat_operacao', 'integer'=>'cod_emitente,container_id']],
        'cta_corren_desconto'=>['key'=>'cta_corren_desconto_id','fields'=>['integer'=>'cta_corren_desconto_id,desconto_importacao_id,container_id', 'decimal'=>'valor_utilizado', 'date'=>'data,cod_usuario']],
        'anexos_container'=>['key'=>'anexo_container_id','fields'=>['integer'=>'anexo_container_id,container_id,item_container_id,tipos_anexo_id', 'character'=>'descricao,caminho_arquivo', 'datetime'=>'dt_hr_arquivo']],
        'pp_container_permissao'=>['key'=>'nr_container,cod_repres,regiao_repres,tipo_repres','fields'=>['integer'=>'nr_container,cod_repres,tipo_repres,container_id', 'character'=>'regiao_repres']],
        'pp_container_saldo_repres'=>['key'=>'nr_container,cod_repres,regiao_repres,tipo_repres','fields'=>['integer'=>'nr_container,cod_repres,tipo_repres,container_id', 'character'=>'regiao_repres', 'decimal'=>'perc_saldo']],
        'pp_it_container'=>['key'=>'container_id','fields'=>['integer'=>'container_id,int_1,mo_codigo,qt_prazo_medio,item_proc_compra_id', 'character'=>'it_comprado,desc_item,ref_comprada,it_codigo,cod_refer,char_1,narrativa,class_fiscal,cod_estab_definido', 'decimal'=>'qt_pedida,qt_vendida,qt_recebida,preco_compra,dec_1,preco_venda,perc_dsp_venda,preco_vd_real,preco_vd_dolar,qt_faturada,vl_preco_medio,qt_devolvida,preco_medio_atu,preco_medio_meta', 'logical'=>'log_1', 'date'=>'data_1', 'datetime'=>'dt_hr_atu_fat', 'int64'=>'transacao_id_qt_fat']],
        'item_container_custos'=>['key'=>'item_container_custo_id','fields'=>['integer'=>'item_container_custo_id,item_custo_id,versao_id,item_container_id,container_id', 'decimal'=>'vl_custo_prev_dolar,qt_embarcada,vl_custo_prev_real,vl_desp_prev_dolar,vl_desp_prev_real,vl_pago_dolar,vl_pago_real,qt_alocada,qt_confirm_emb,qt_recebto_fisico,vl_desp_realiz_real,vl_desp_realiz_dolar,vl_desconto_utiliz_dolar,vl_unit_prev,vl_unit_realiz,vl_unit_prev_dolar,vl_unit_realiz_dolar,vl_desp_prev_perc_fixo,vl_custo_distribuido,vl_negociado,vl_saldo_a_pagar,vl_desp_prev_real_sem_distr_cust,vl_desp_prev_dolar_sem_distr_cus,vl_distr_custo', 'character'=>'it_codigo,memoria_calculo,vl_desconto_utiliz_real']],
        'item_container_custo_despesas'=>['key'=>'versao_id,despesa_id','fields'=>['integer'=>'versao_id,despesa_id,calc_imp_container_id,log_custo,container_id', 'decimal'=>'vl_prev,vl_negociado,vl_proforma,vl_base_imp']],
        'item_container_custo_nfs'=>['key'=>'versao_id,documento,serie,estab,nat_operacao,cod_emitente','fields'=>['integer'=>'item_container_custo_id,versao_id,cod_emitente,container_id', 'character'=>'documento,serie,estab,nat_operacao', 'decimal'=>'vl_mercadoria,vl_despesa']],
        'item_container_custo_pgtos'=>['key'=>'versao_id,num_id_tit_ap','fields'=>['integer'=>'versao_id,num_id_tit_ap,container_id', 'decimal'=>'vl_real,vl_dolar']],
        'item_container_custo_descontos'=>['key'=>'versao_id,desconto_importacao_id','fields'=>['integer'=>'versao_id,desconto_importacao_id,log_custo,container_id', 'decimal'=>'vl_autoriz_dolar,vl_autoriz_real']],
        'cta_corren_custo_distr'=>['key'=>'cta_corren_custo_distr_id','fields'=>['integer'=>'cta_corren_custo_distr_id,item_container_custo_id,item_container_id,cod_tipo_movto,despesa_container_id,container_id', 'date'=>'data', 'character'=>'historico,login', 'decimal'=>'vl_lancto']],
        'item_custos'=>['key'=>'item_custo_id','fields'=>['integer'=>'item_custo_id,cod_tipo_custo,cod_situacao,media_custo_id,container_id', 'datetime'=>'dt_hr_custo', 'decimal'=>'vl_unit_novo,qt_estoque,qt_nf_mae,qt_etiq_container,qt_etiq_branco,qt_etiq_outros_container,vl_unit_anterior,qt_estoque_selecionado,qt_estoque_media,vl_unit_media,vl_unit_container,qt_container', 'date'=>'dt_referencia', 'character'=>'caminho_arquivo,memoria_calculo,it_codigo,cod_usuario,it_codigo_media']],
    ];
}
