<?php
namespace App\Runtime;

/** Closed shipment audit. Actual order ownership is resolved by Progress, never supplied as an image by the browser. */
final class RuntimePurchaseSampleAudit
{
    private const MAIN=['envio_amostra_repres_id'=>'id','item_proc_compra_id'=>'id','cod_rep'=>['type'=>'integer','nullable'=>true],'dt_envio'=>'date','apont_amostra_id'=>['type'=>'integer','nullable'=>true],'proc_compra_id'=>'id'];
    public static function supports(array $definition): bool { return ($definition['entityCode']??null)==='proc_compra_amostras_progress'; }
    public static function binding(array $definition,array $endpoint,string $operation): bool
    { return ($endpoint['requestContextFields']??[])===['proc_compra_id','item_proc_compra_id'] && RuntimeProgressAuditSchema::ciwBinding($definition,$endpoint,'proc_compra_amostras_progress','envio_amostra_repres_id','proc_compra_amostras',$operation,['read','get','create','update','delete']); }
    public static function editableField(string $field): bool { return in_array($field,['cod_rep','dt_envio'],true); }
    public static function dataRow(mixed $row): bool { return RuntimeProgressAuditSchema::row($row,self::MAIN+['row_stamp'=>'stamp','representante_nome'=>['type'=>'string','maxLength'=>4000]]); }
    public static function inspect(array $definition,array $endpoint,array $body,string $operation,mixed $id,mixed $context,?string $actor): array
    {
        $no=static fn(string $reason='invalid'): array=>['before'=>[],'after'=>[],'events'=>[],'metadata'=>['available'=>false,'reason'=>$reason]];
        if (!in_array($operation,['create','update','delete'],true) || !self::binding($definition,$endpoint,$operation)) { return $no('unsupported_binding'); }
        if (!array_key_exists('audit',$body)) { return $no('not_provided'); }
        $a=$body['audit'];
        if (($body['success']??null)!==true || ($body['committed']??null)!==true || isset($body['error'])
            || !RuntimeProgressAuditSchema::exact($a,['version','source','resource','operation','recordId','actor','before','after','changes','warnings'])
            || $a['version']!==1 || $a['source']!=='progress-transaction' || $a['resource']!=='envios-amostras-repres' || $a['operation']!==$operation
            || !RuntimeProgressAuditSchema::id($a['recordId']) || $id!==null && RuntimePurchaseOrderAudit::recordId($id)!==$a['recordId']
            || !is_string($actor) || $actor==='' || !RuntimeProgressAuditSchema::exact($a['actor'],['login']) || $a['actor']['login']!==$actor
            || !RuntimeProgressAuditSchema::row($context,['proc_compra_id'=>'id','item_proc_compra_id'=>'id']) || $a['changes']!==[] || $a['warnings']!==[]) { return $no(); }
        foreach (['before','after'] as $name) {
            if ($name==='before' && $operation==='create' || $name==='after' && $operation==='delete') { if ($a[$name]!==[]) { return $no(); } continue; }
            if (!RuntimeProgressAuditSchema::row($a[$name],self::MAIN) || $a[$name]['envio_amostra_repres_id']!==$a['recordId']
                || $a[$name]['proc_compra_id']!==$context['proc_compra_id'] || $a[$name]['item_proc_compra_id']!==$context['item_proc_compra_id']) { return $no(); }
        }
        if ($operation!=='delete') {
            if (!self::dataRow($body['data']??null)) { return $no('invalid_data'); }
            $data=$body['data'];unset($data['row_stamp'],$data['representante_nome']);
            if (!RuntimeProgressAuditSchema::same($data,$a['after'])) { return $no('invalid_data'); }
        } elseif (!RuntimeProgressAuditSchema::exact($body['data']??null,['envio_amostra_repres_id']) || $body['data']['envio_amostra_repres_id']!==$a['recordId']) { return $no('invalid_data'); }
        return ['before'=>$a['before'],'after'=>$a['after'],'events'=>[],'metadata'=>['available'=>true,'version'=>1,'source'=>'progress-transaction','resource'=>'envios_amostras_repres','operation'=>$operation,'recordId'=>$a['recordId'],'procCompraId'=>$context['proc_compra_id'],'itemProcCompraId'=>$context['item_proc_compra_id']]];
    }
}
