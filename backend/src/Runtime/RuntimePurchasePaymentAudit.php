<?php
namespace App\Runtime;
/** Imagens confirmadas pelo writer de pagamentos; nenhum escopo vem do navegador. */
final class RuntimePurchasePaymentAudit
{
    private const LINK = [
        'pagamento_key'=>'key','proc_compra_id'=>'id','num_id_tit_ap'=>'id','titulos'=>'titles',
        'empresa_id'=>'id','cod_empresa'=>'string','cod_estab'=>'string','cod_espec_docto'=>'string',
        'cod_ser_docto'=>'string','cdn_fornecedor'=>'id','cod_tit_ap'=>'string','cod_parcela'=>'string',
        'dat_emis_docto'=>'date','dat_vencto_tit_ap'=>'date','cod_indic_econ'=>'string',
        'val_origin_tit_ap'=>'decimal','valor_real'=>'decimal','valor_dolar'=>'decimal',
    ];
    private const LEDGER = [
        'cta_corren_fornec_imp_id'=>'id','fornecedor_id'=>'id','data'=>'date','item_proc_compra_id'=>'id',
        'historico'=>'string','cod_tipo_movto'=>'integer','vl_lancto'=>'decimal','cod_acao'=>'integer',
        'login'=>'string','data_hora'=>'datetime','num_id_tit_ap'=>'id','item_container_id'=>'integer',
        'transacao_id'=>'integer','proc_compra_id'=>'id',
    ];

    public static function supports(array $d): bool { return ($d['entityCode']??null)==='proc_compra_pagamentos_progress'; }

    public static function inspect(array $d,array $e,array $body,string $operation,mixed $expectedId,mixed $expectedParent,?string $expectedActor): array
    {
        $no=static fn(string $reason='invalid'):array=>['before'=>[],'after'=>[],'events'=>[],'metadata'=>['available'=>false,'reason'=>$reason]];
        if (!self::binding($d,$e,$operation)) { return $no('unsupported_binding'); }
        if (!array_key_exists('audit',$body)) { return $no('not_provided'); }
        $a=$body['audit'];
        if (($body['success']??null)!==true || ($body['committed']??null)!==true || isset($body['error'])
            || !self::exact($a,['version','source','resource','operation','recordId','proc_compra_id','before','after','actor','changes','warnings'])
            || $a['version']!==1 || $a['source']!=='progress-transaction' || $a['resource']!=='proc-compra-pagamentos' || $a['operation']!==$operation
            || !self::key($a['recordId']) || $expectedId!==null && $expectedId!==$a['recordId']
            || !self::id($expectedParent) || $a['proc_compra_id']!==$expectedParent
            || !is_string($expectedActor) || trim($expectedActor)==='' || !self::exact($a['actor'],['login']) || $a['actor']['login']!==$expectedActor
            || !is_array($a['changes']) || !array_is_list($a['changes']) || count($a['changes'])>2000
            || !is_array($a['warnings']) || !array_is_list($a['warnings']) || count($a['warnings'])>1000) { return $no(); }
        foreach ($a['warnings'] as $warning) { if (!is_string($warning) || mb_strlen($warning)>4000) { return $no(); } }
        $parent=$expectedParent;
        $main=self::link($operation,$a['recordId'],$a['before'],$a['after'],$parent);
        if ($main===null) { return $no(); }
        $events=[]; $seen=[]; $links=[]; $hasMain=false;
        foreach ($a['changes'] as $c) {
            if (!self::exact($c,['resource','operation','recordId','before','after'])) { return $no(); }
            if ($c['resource']==='purchase_payment_links') {
                if ($c['operation']!==$operation || !self::key($c['recordId'])) { return $no(); }
                $event=self::link($operation,$c['recordId'],$c['before'],$c['after'],$parent);
                if ($event===null) { return $no(); }
                $row=$operation==='create'?$event['after']:$event['before'];
                if (isset($links[$row['num_id_tit_ap']])) { return $no(); }
                $links[$row['num_id_tit_ap']]=$c['recordId'];
                if ($c['recordId']===$a['recordId']) {
                    if ($event!==$main) { return $no(); }
                    $hasMain=true;
                } else { $events[]=$event; }
            } elseif ($c['resource']==='cta_corren_fornec_imp') {
                if ($c['operation']!=='create' || $c['before']!==[] || !self::id($c['recordId'])
                    || !self::row($c['after'],self::LEDGER) || $c['after']['cta_corren_fornec_imp_id']!==$c['recordId']
                    || $c['after']['proc_compra_id']!==$parent || $c['after']['cod_tipo_movto']!==4
                    || !in_array($c['after']['cod_acao'],[1,3],true) || $c['after']['item_container_id']!==0
                    || $c['after']['transacao_id']!==0 || $c['after']['login']!==$expectedActor) { return $no(); }
                $events[]=['before'=>[],'after'=>$c['after'],'metadata'=>self::metadata('cta_corren_fornec_imp','create',$c['recordId'],$parent)];
            } else { return $no(); }
            $identity=$c['resource'].':'.$c['recordId'];
            if (isset($seen[$identity])) { return $no(); } $seen[$identity]=true;
        }
        if (!$hasMain || count($links)>100 || $operation==='delete' && count($links)!==1) { return $no(); }
        foreach ($events as &$event) {
            if ($event['metadata']['resource']!=='cta_corren_fornec_imp') { continue; }
            $paymentKey=$links[$event['after']['num_id_tit_ap']]??null;
            if ($paymentKey===null) { return $no(); }
            $event['metadata']['paymentKey']=$paymentKey;
        }
        unset($event);
        return ['before'=>$main['before'],'after'=>$main['after'],'events'=>$events,'metadata'=>$main['metadata']];
    }

    private static function binding(array $d,array $e,string $op): bool
    {
        return RuntimeProgressAuditSchema::ciwBinding($d,$e,'proc_compra_pagamentos_progress','pagamento_key','proc_compra_pagamentos',$op,['create','delete']);
    }

    private static function link(string $op,string $id,mixed $before,mixed $after,int $parent): ?array
    {
        if ($op==='create' ? $before!==[] || !self::row($after,self::LINK) : $after!==[] || !self::row($before,self::LINK)) { return null; }
        $row=$op==='create'?$after:$before;
        if ($row['pagamento_key']!==$id || $row['proc_compra_id']!==$parent || trim($row['cod_estab'])==='' || $row['titulos']!==[$row['num_id_tit_ap']]) { return null; }
        $part=static fn(string $value):string=>'V'.mb_strlen($value,'UTF-8').':'.$value;
        $computed='p_'.strtoupper(hash('sha256',$part((string)$parent).$part($row['cod_estab']).$part((string)$row['num_id_tit_ap'])));
        if (!hash_equals($computed,$id)) { return null; }
        return ['before'=>$before,'after'=>$after,'metadata'=>self::metadata('purchase_payment_links',$op,$id,$parent)+['paymentKey'=>$id]];
    }

    private static function metadata(string $resource,string $op,int|string $id,int $parent): array
    { return ['available'=>true,'version'=>1,'source'=>'progress-transaction','resource'=>$resource,'operation'=>$op,'recordId'=>$id,'procCompraId'=>$parent]; }
    private static function exact(mixed $row,array $keys): bool
    { return RuntimeProgressAuditSchema::exact($row,$keys); }
    private static function id(mixed $value): bool { return RuntimeProgressAuditSchema::id($value); }
    private static function key(mixed $value): bool { return RuntimeProgressAuditSchema::key($value); }
    private static function row(mixed $row,array $schema): bool
    { return RuntimeProgressAuditSchema::row($row,$schema); }
}

