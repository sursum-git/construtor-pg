<?php
namespace App\Runtime;
/** Closed purchase header contract. Financial child scope is read only from confirmed physical images. */
final class RuntimePurchaseOrderAudit
{
    private const MAIN=[
        'proc_compra_id'=>'id','empresa_id'=>'id','fornecedor_id'=>'id',
        'qt_container'=>['type'=>'integer','nullable'=>true],'cod_situacao'=>['type'=>'integer','nullable'=>true],
        'cod_proc_fornec'=>['type'=>'string','maxLength'=>100,'nullable'=>true],
        'qt_dias_translado'=>['type'=>'integer','nullable'=>true],'cotacao_prevista'=>['type'=>'decimal','nullable'=>true],
        'observacao'=>['type'=>'string','maxLength'=>16000,'nullable'=>true],
        'dt_registro'=>'date','dt_limite'=>'date','log_facilid_contato_fornec'=>['type'=>'boolean','nullable'=>true],
        'log_considerar_previsao'=>['type'=>'boolean','nullable'=>true],
        'nr_container_original'=>['type'=>'string','maxLength'=>20,'nullable'=>true],
    ];
    private const LEDGER=[
        'cta_corren_fornec_imp_id'=>'id','fornecedor_id'=>'id','item_proc_compra_id'=>'id','proc_compra_id'=>'id',
        'data'=>'date','historico'=>['type'=>'string','nullable'=>true],'cod_tipo_movto'=>'integer',
        'vl_lancto'=>['type'=>'decimal','nullable'=>true],'cod_acao'=>['type'=>'integer','nullable'=>true],
        'login'=>['type'=>'string','nullable'=>true],'data_hora'=>'datetime','num_id_tit_ap'=>['type'=>'integer','nullable'=>true],
        'item_container_id'=>['type'=>'integer','nullable'=>true],'transacao_id'=>['type'=>'integer','nullable'=>true],
    ];
    public static function supports(array $d): bool { return ($d['entityCode']??null)==='procs_compra_med_progress'; }
    public static function binding(array $d,array $e,string $op): bool
    { return ($e['requestContextFields']??[])===[] && RuntimeProgressAuditSchema::ciwBinding($d,$e,'procs_compra_med_progress','proc_compra_id','procs_compra_med',$op,['read','get','create','update','delete']); }
    public static function recordId(mixed $id): ?int
    {
        if (RuntimeProgressAuditSchema::id($id)) { return $id; }
        if (is_string($id) && preg_match('/\A[1-9][0-9]{0,9}\z/D',$id)===1 && (float)$id<=2147483647) { return (int)$id; }
        return null;
    }
    public static function image(mixed $row): bool { return RuntimeProgressAuditSchema::row($row,self::MAIN); }
    public static function dataRow(mixed $row): bool
    { return RuntimeProgressAuditSchema::row($row,self::MAIN+['row_stamp'=>'stamp']); }
    public static function inspect(array $d,array $e,array $body,string $op,mixed $id,?string $actor): array
    {
        $no=static fn(string $reason='invalid'):array=>['before'=>[],'after'=>[],'events'=>[],'metadata'=>['available'=>false,'reason'=>$reason]];
        if (!in_array($op,['create','update','delete'],true) || !self::binding($d,$e,$op)) { return $no('unsupported_binding'); }
        if (!array_key_exists('audit',$body)) { return $no('not_provided'); }
        $a=$body['audit'];
        if (($body['success']??null)!==true || ($body['committed']??null)!==true || isset($body['error'])
            || !RuntimeProgressAuditSchema::exact($a,['version','source','resource','operation','recordId','actor','before','after','changes','warnings'])
            || $a['version']!==1 || $a['source']!=='progress-transaction' || $a['resource']!=='procs-compra' || $a['operation']!==$op
            || !RuntimeProgressAuditSchema::id($a['recordId']) || $id!==null && self::recordId($id)!==$a['recordId']
            || !is_string($actor) || trim($actor)==='' || !RuntimeProgressAuditSchema::exact($a['actor'],['login']) || $a['actor']['login']!==$actor
            || !is_array($a['changes']) || !array_is_list($a['changes']) || count($a['changes'])>50000
            || !is_array($a['warnings']) || !array_is_list($a['warnings']) || count($a['warnings'])>1000) { return $no(); }
        foreach ($a['warnings'] as $warning) { if (!is_string($warning) || mb_strlen($warning)>4000) { return $no(); } }
        foreach (['before','after'] as $name) {
            if (($name==='before' && $op==='create') || ($name==='after' && $op==='delete')) { if ($a[$name]!==[]) { return $no(); } continue; }
            if (!RuntimeProgressAuditSchema::row($a[$name],self::MAIN) || $a[$name]['proc_compra_id']!==$a['recordId']) { return $no(); }
        }
        if ($op==='update') {
            foreach (['empresa_id','qt_container','cotacao_prevista'] as $field) { if ($a['before'][$field]!==$a['after'][$field]) { return $no(); } }
        }
        if ($op!=='delete') {
            if (!self::dataRow($body['data']??null)) { return $no('invalid_data'); }
            $data=$body['data'];unset($data['row_stamp']);
            if (!RuntimeProgressAuditSchema::same($data,$a['after'])) { return $no('invalid_data'); }
        }
        $events=[];$seen=[];
        foreach ($a['changes'] as $c) {
            if (!RuntimeProgressAuditSchema::exact($c,['resource','operation','recordId','before','after']) || $c['resource']!=='cta_corren_fornec_imp'
                || !in_array($c['operation'],['create','delete'],true) || !RuntimeProgressAuditSchema::id($c['recordId']) || isset($seen[$c['recordId']])) { return $no(); }
            $creating=$c['operation']==='create';$row=$c[$creating?'after':'before'];
            if ($c[$creating?'before':'after']!==[] || !RuntimeProgressAuditSchema::row($row,self::LEDGER)
                || $row['cta_corren_fornec_imp_id']!==$c['recordId'] || $row['cod_tipo_movto']<=2) { return $no(); }
            if ($creating) {
                if (in_array(null,$row,true) || !in_array($row['cod_tipo_movto'],[3,4,5,10],true) || !in_array($row['cod_acao'],[1,2,3],true)
                    || $row['transacao_id']!==0 || $row['login']!==$actor || $row['num_id_tit_ap']<0
                    || (in_array($row['cod_tipo_movto'],[3,4],true) ? $row['item_container_id']!==0 : !RuntimeProgressAuditSchema::id($row['item_container_id']))
                    || in_array($row['cod_tipo_movto'],[4,5],true) && !RuntimeProgressAuditSchema::id($row['num_id_tit_ap'])) { return $no(); }
            }
            $seen[$c['recordId']]=true;
            $events[]=['before'=>$c['before'],'after'=>$c['after'],'metadata'=>self::metadata('cta_corren_fornec_imp',$c['operation'],$c['recordId'],$row['proc_compra_id'],$a['recordId'])+['itemProcCompraId'=>$row['item_proc_compra_id']]];
        }
        return ['before'=>$a['before'],'after'=>$a['after'],'events'=>$events,'metadata'=>self::metadata('procs_compra',$op,$a['recordId'],$a['recordId'],$a['recordId'])];
    }
    private static function metadata(string $resource,string $op,int $id,int $actualParent,int $origin): array
    { return ['available'=>true,'version'=>1,'source'=>'progress-transaction','resource'=>$resource,'operation'=>$op,'recordId'=>$id,'procCompraId'=>$actualParent,'originProcCompraId'=>$origin]; }
}
