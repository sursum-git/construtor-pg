<?php
namespace App\Runtime;
/** Closed item contract. Real effect owners and initiating scope are derived from confirmed images. */
final class RuntimePurchaseItemAudit
{
    private const MAIN=[
        'item_proc_compra_id'=>'id',
        'descricao'=>['type'=>'string','nullable'=>true],
        'codigo_erp'=>['type'=>'string','nullable'=>true],
        'qt_dias_producao'=>['type'=>'integer','nullable'=>true],
        'vl_base_tributacao'=>['type'=>'decimal','nullable'=>true],
        'unid_medida'=>['type'=>'string','nullable'=>true],
        'proc_compra_id'=>'id',
        'qt_comprada'=>['type'=>'decimal','nullable'=>true],
        'vl_custo_dolar'=>['type'=>'decimal','nullable'=>true],
        'composicao'=>['type'=>'string','nullable'=>true],
        'peso'=>['type'=>'string','nullable'=>true],
        'largura'=>['type'=>'string','nullable'=>true],
        'rolo'=>['type'=>'string','nullable'=>true],
        'dt_prev_envio_desenhos'=>['type'=>'date','nullable'=>true],
        'dt_real_envio_desenhos'=>['type'=>'date','nullable'=>true],
        'log_aprov_so_ld'=>['type'=>'boolean','nullable'=>true],
        'dt_prev_recbto_so_ld'=>['type'=>'date','nullable'=>true],
        'dt_prev_aprov_so_ld'=>['type'=>'date','nullable'=>true],
        'dt_real_aprov_so_ld'=>['type'=>'date','nullable'=>true],
        'coloracao_id'=>['type'=>'integer','nullable'=>true],
        'dt_maxima_producao'=>['type'=>'date','nullable'=>true],
        'dt_mostruario'=>['type'=>'date','nullable'=>true],
        'log_mostruario'=>['type'=>'boolean','nullable'=>true],
        'formato_amostra_id'=>['type'=>'integer','nullable'=>true],
        'ind_producao_no_prazo'=>['type'=>'integer','nullable'=>true],
        'ind_relat_semanal_producao'=>['type'=>'integer','nullable'=>true],
        'ind_qualid_emb'=>['type'=>'integer','nullable'=>true],
        'ind_laudo_antes_embarque'=>['type'=>'integer','nullable'=>true],
        'ind_prod_conf_pi'=>['type'=>'integer','nullable'=>true],
        'ind_rec_amostra_antes_emb'=>['type'=>'integer','nullable'=>true],
        'ind_qt_conf_proforma'=>['type'=>'integer','nullable'=>true],
        'ind_tamanho_pecas_correto'=>['type'=>'integer','nullable'=>true],
        'observacao'=>['type'=>'string','nullable'=>true],
        'dt_cheg_amostras_emb'=>['type'=>'date','nullable'=>true],
        'dt_termino_producao'=>['type'=>'date','nullable'=>true],
        'vl_cubagem'=>['type'=>'decimal','nullable'=>true],
        'dt_recbto_laudo'=>['type'=>'date','nullable'=>true],
        'dt_recbto_embalagem'=>['type'=>'date','nullable'=>true],
        'dt_real_recbto_so_ld'=>['type'=>'date','nullable'=>true],
        'dt_prev_termino_producao'=>['type'=>'date','nullable'=>true],
        'qt_dias_calc_so_ld'=>['type'=>'integer','nullable'=>true],
        'shrinkage'=>['type'=>'string','nullable'=>true],
        'ind_distr_custo'=>['type'=>'integer','nullable'=>true],
        'vl_negociado'=>['type'=>'decimal','nullable'=>true],
        'item_estacao'=>['type'=>'integer','nullable'=>true],
        'dt_item_certificacao'=>['type'=>'date','nullable'=>true]
    ];
    private const LEDGER=[
        'cta_corren_fornec_imp_id'=>'id','fornecedor_id'=>'id','item_proc_compra_id'=>'id','proc_compra_id'=>'id',
        'data'=>'date','historico'=>['type'=>'string','nullable'=>true],'cod_tipo_movto'=>'integer',
        'vl_lancto'=>['type'=>'decimal','nullable'=>true],'cod_acao'=>['type'=>'integer','nullable'=>true],
        'login'=>['type'=>'string','nullable'=>true],'data_hora'=>'datetime','num_id_tit_ap'=>['type'=>'integer','nullable'=>true],
        'item_container_id'=>['type'=>'integer','nullable'=>true],'transacao_id'=>['type'=>'integer','nullable'=>true],
    ];
    public static function supports(array $d): bool { return ($d['entityCode']??null)==='proc_compra_itens_progress'; }
    public static function binding(array $d,array $e,string $op): bool
    { return ($e['requestContextFields']??[])===['proc_compra_id'] && RuntimeProgressAuditSchema::ciwBinding($d,$e,'proc_compra_itens_progress','item_proc_compra_id','proc_compra_itens',$op,['read','get','create','update','delete']); }
    public static function recordId(mixed $id): ?int { return RuntimePurchaseOrderAudit::recordId($id); }
    public static function editableField(string $field): bool
    { return isset(self::MAIN[$field]) && !in_array($field,['item_proc_compra_id','proc_compra_id'],true); }
    public static function dataRow(mixed $row): bool
    { return RuntimeProgressAuditSchema::row($row,self::MAIN+['row_stamp'=>'stamp','qt_alocada'=>'decimal','qt_saldo'=>'decimal','calc_gm'=>['type'=>'decimal','nullable'=>true]]); }
    public static function inspect(array $d,array $e,array $body,string $op,mixed $id,mixed $parent,?string $actor): array
    {
        $no=static fn(string $reason='invalid'):array=>['before'=>[],'after'=>[],'events'=>[],'metadata'=>['available'=>false,'reason'=>$reason]];
        if (!in_array($op,['create','update','delete'],true) || !self::binding($d,$e,$op)) { return $no('unsupported_binding'); }
        if (!array_key_exists('audit',$body)) { return $no('not_provided'); }
        $a=$body['audit'];
        if (($body['success']??null)!==true || ($body['committed']??null)!==true || isset($body['error'])
            || !RuntimeProgressAuditSchema::exact($a,['version','source','resource','operation','recordId','actor','before','after','changes','warnings'])
            || $a['version']!==1 || $a['source']!=='progress-transaction' || $a['resource']!=='itens-proc-compra' || $a['operation']!==$op
            || !RuntimeProgressAuditSchema::id($a['recordId']) || $id!==null && self::recordId($id)!==$a['recordId']
            || !is_string($actor) || trim($actor)==='' || !RuntimeProgressAuditSchema::exact($a['actor'],['login']) || $a['actor']['login']!==$actor
            || !is_array($a['changes']) || !array_is_list($a['changes']) || count($a['changes'])>2000
            || !is_array($a['warnings']) || !array_is_list($a['warnings']) || count($a['warnings'])>1000) { return $no(); }
        foreach ($a['warnings'] as $warning) { if (is_string($warning) && mb_strlen($warning)<=4000) { continue; } if (!RuntimeProgressAuditSchema::row($warning,['code'=>'string','item_container_id'=>'id','message'=>'string']) || $warning['code']!=='ITEMS_FINANCE_LEGACY_TYPE10_RETAINED') { return $no(); } }
        foreach (['before','after'] as $name) {
            if (($name==='before' && $op==='create') || ($name==='after' && $op==='delete')) { if ($a[$name]!==[]) { return $no(); } continue; }
            if (!RuntimeProgressAuditSchema::row($a[$name],self::MAIN) || $a[$name]['item_proc_compra_id']!==$a['recordId'] || $a[$name]['proc_compra_id']!==$parent) { return $no(); }
        }

        if ($op!=='delete') {
            if (!self::dataRow($body['data']??null)) { return $no('invalid_data'); }
            $data=$body['data'];unset($data['row_stamp'],$data['qt_alocada'],$data['qt_saldo'],$data['calc_gm']);
            if (!RuntimeProgressAuditSchema::same($data,$a['after'])) { return $no('invalid_data'); }
        }
        $events=[];$seen=[];
        foreach ($a['changes'] as $change) {
            if (!RuntimeProgressAuditSchema::exact($change,['resource','operation','recordId','before','after','metadata'])
                || !RuntimeProgressAuditSchema::exact($change['metadata'],['originProcCompraId','originItemProcCompraId'])
                || $change['metadata']['originProcCompraId']!==$parent || $change['metadata']['originItemProcCompraId']!==$a['recordId']) { return $no(); }
            $resource=$change['resource']; $opEffect=$change['operation']; $rid=$change['recordId'];
            if (!is_string($resource) || !is_string($opEffect) || !RuntimeProgressAuditSchema::id($rid)) { return $no(); }
            if ($resource==='cta_corren_fornec_imp') {
                $row=$change['after'];$schema=self::LEDGER;
                if (is_array($row) && array_key_exists('container_id',$row)) { $schema+=['container_id'=>'id']; }
                if ($opEffect!=='create' || $change['before']!==[] || !RuntimeProgressAuditSchema::row($row,$schema)
                    || $row['cta_corren_fornec_imp_id']!==$rid || in_array(null,$row,true)
                    || !in_array($row['cod_tipo_movto'],[3,4,5,10],true) || !in_array($row['cod_acao'],[1,2,3],true)
                    || $row['item_proc_compra_id']===$a['recordId'] && $row['proc_compra_id']!==$parent
                    || $row['transacao_id']!==0 || $row['login']!==$actor || $row['num_id_tit_ap']<0
                    || ($row['cod_tipo_movto']===3 ? $row['item_container_id']!==0 : ($row['cod_tipo_movto']===4 ? $row['item_container_id']<0 || $row['item_container_id']>0 && $row['cod_acao']!==3 : !RuntimeProgressAuditSchema::id($row['item_container_id'])))
                    || in_array($row['cod_tipo_movto'],[4,5],true) && !RuntimeProgressAuditSchema::id($row['num_id_tit_ap'])) { return $no(); }
                $meta=self::metadata($resource,$opEffect,$rid,$row['proc_compra_id'],$parent)+['itemProcCompraId'=>$row['item_proc_compra_id']];
                if (isset($row['container_id'])) { $meta['containerId']=$row['container_id'];$meta['scope']='container-panel-v1'; }
                $event=['before'=>[],'after'=>$row,'metadata'=>$meta];
            } else {
                $event=RuntimeContainerAudit::confirmedFiscalEvent($change);
                if ($event===null) { return $no(); }
            }
            $key=$resource.':'.$rid;
            if (isset($seen[$key]) && ($opEffect!=='update' || !RuntimeProgressAuditSchema::same($seen[$key],$change['before']))) { return $no(); }
            $seen[$key]=$change['after'];
            $event['metadata']['originProcCompraId']=$parent;$event['metadata']['originItemProcCompraId']=$a['recordId'];
            $events[]=$event;
        }
        return ['before'=>$a['before'],'after'=>$a['after'],'events'=>$events,'metadata'=>self::metadata('itens_proc_compra',$op,$a['recordId'],$parent,$parent)+['itemProcCompraId'=>$a['recordId'],'originItemProcCompraId'=>$a['recordId']]];
    }
    private static function metadata(string $resource,string $op,int $id,int $actualParent,int $origin): array
    { return ['available'=>true,'version'=>1,'source'=>'progress-transaction','resource'=>$resource,'operation'=>$op,'recordId'=>$id,'procCompraId'=>$actualParent,'originProcCompraId'=>$origin]; }
}
