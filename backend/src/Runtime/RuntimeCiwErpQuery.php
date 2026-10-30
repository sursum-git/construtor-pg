<?php
namespace App\Runtime;

/** Transporte de consultas ERP limitadas; regras e origem permanecem no ABL. */
final class RuntimeCiwErpQuery
{
    private const RESOURCES=['container_nfs_disponiveis_med_progress'=>'nfs-disponiveis','despesas_impostos_geral_progress'=>'despesas_impostos_geral','container_custos_comparacao_real_progress'=>'container_custos_comparacao_real','container_custos_comparacao_dolar_progress'=>'container_custos_comparacao_dolar','item_custos_manual_progress'=>'item_custos_manual','item_container_amostra_progress'=>'item_container_amostra','descontos_importacao_geral_progress'=>'import_descontos_geral','descontos_importacao_lancamentos_progress'=>'import_descontos_lancamentos','custo_distribuicao_itens_aptos_progress'=>'custo_distribuicao_itens_aptos','custo_distribuicao_lancamentos_item_progress'=>'custo_distribuicao_lancamentos_item','container_versao_pagtos_historico_med_progress'=>'container_versao_pagtos_historico_med','container_versao_nfs_historico_med_progress'=>'container_versao_nfs_historico_med','container_versao_descontos_historico_med_progress'=>'container_versao_descontos_historico_med','container_versao_despesas_historico_med_progress'=>'container_versao_despesas_historico_med','container_calculos_impostos_med_progress'=>'container_calculos_impostos_med','container_despesa_memoria_med_progress'=>'container_despesa_memoria_med','container_versoes_historico_med_progress'=>'container_versoes_historico_med','proc_compra_sinalizadores_med_progress'=>'proc_compra_sinalizadores','proc_compra_sinalizados_med_progress'=>'proc_compra_sinalizados','proc_compra_quantidades_periodo_med_progress'=>'proc_compra_quantidades_periodo_med','proc_compra_quantidades_progress'=>'proc_compra_quantidades','proc_compra_quantidades_med_progress'=>'proc_compra_quantidades','proc_compra_despesas_progress'=>'proc_compra_despesas','proc_compra_documentos_progress'=>'proc_compra_documentos','proc_compra_anexos_progress'=>'proc_compra_anexos','proc_compra_amostras_progress'=>'proc_compra_amostras','proc_compra_itens_progress'=>'proc_compra_itens','notas_fiscais_med_progress'=>'notas_fiscais_med','titulos_pagar_med_progress'=>'titulos_pagar_med','procs_compra_financeiro_med_progress'=>'procs_compra_financeiro_med','procs_compra_med_progress'=>'procs_compra_med'];
    private static function primaryKey(array $d): string { return match($d['entityCode']??'') {'container_nfs_disponiveis_med_progress'=>'nf_key','container_calculos_impostos_med_progress'=>'calc_imp_container_id','container_despesa_memoria_med_progress'=>'despesa_container_id','proc_compra_despesas_progress'=>'despesa_proc_compra_id','proc_compra_documentos_progress','proc_compra_anexos_progress'=>'anexo_proc_compra_id','proc_compra_amostras_progress'=>'envio_amostra_repres_id','proc_compra_itens_progress'=>'item_proc_compra_id','procs_compra_financeiro_med_progress','procs_compra_med_progress'=>'proc_compra_id',default=>'record_key'}; }
    public static function supports(array $d): bool {return isset(self::RESOURCES[$d['entityCode']??'']);}
    private static function invalid(): never {throw new RuntimeHttpException('CIW_ERP_QUERY_INVALID','Consulta ERP MED inválida. Confira filtros, ordenação e limite de 200 registros por página.',422);}
    private static function integer(mixed $v,int $min,int $max): int {if(!is_int($v)||$v<$min||$v>$max)self::invalid();return $v;}
    public static function prepare(array $d,array $endpoint,array $payload): array
    {
        $context=[];
        if (RuntimeExpenseTaxRelationsRead::supports($d)) { RuntimeExpenseTaxRelationsRead::readRequest($d,$endpoint,$payload); }
        $nfAvailable=RuntimeContainerNfAvailableRead::supports($d);
        if ($nfAvailable) {
            $context=RuntimeContainerNfAvailableRead::request($d,$endpoint,$payload,'read');
            unset($payload['context']);
        } elseif (RuntimeCiwComparisonRead::supports($d)) {
            $context=RuntimeCiwComparisonRead::resolveContext($d,$endpoint,$payload,'read');
            unset($payload['context']);
        } elseif (RuntimeSampleContainerItemsRead::supports($d)) {
            $context=RuntimeSampleContainerItemsRead::resolveContext($d,$endpoint,$payload);
            unset($payload['context']);
        } elseif (RuntimeImportDiscountRead::supports($d)) {
            $context=RuntimeImportDiscountRead::resolveContext($d,$endpoint,$payload);
            unset($payload['context']);
        } elseif (RuntimeCostDistributionRead::supports($d)) {
            $context=RuntimeCostDistributionRead::resolveContext($d,$endpoint,$payload);
            unset($payload['context']);
        } elseif (RuntimeCostDistributionLedgerRead::supports($d)) {
            $context=RuntimeCostDistributionLedgerRead::resolveContext($d,$endpoint,$payload);
            unset($payload['context']);
        } elseif (in_array($d['entityCode']??null,['container_calculos_impostos_med_progress','container_despesa_memoria_med_progress'],true)) {
            $key=($d['entityCode']==='container_calculos_impostos_med_progress')?'calc_imp_container_id':'despesa_container_id';
            if (($endpoint['requestContextFields']??null)!==['container_id',$key]) { self::invalid(); }
            $context=ApiRequestContext::resolve($d,$endpoint,$payload);
            unset($payload['context']);
        } elseif (in_array($d['entityCode']??null,['container_versoes_historico_med_progress','container_versao_pagtos_historico_med_progress','container_versao_nfs_historico_med_progress','container_versao_descontos_historico_med_progress','container_versao_despesas_historico_med_progress'],true)) {
            if (($endpoint['requestContextFields']??null)!==['container_id','versao_id']) { self::invalid(); }
            $context=ApiRequestContext::resolve($d,$endpoint,$payload);
            unset($payload['context']);
        } elseif (($d['entityCode']??null)==='proc_compra_sinalizados_med_progress') {
            if (($endpoint['requestContextFields']??null)!==['sinaliz_imp_id']) { self::invalid(); }
            $context=ApiRequestContext::resolve($d,$endpoint,$payload);
            unset($payload['context']);
        } elseif (($d['entityCode']??null)==='proc_compra_quantidades_progress') {
            if (($endpoint['requestContextFields']??null)!==['proc_compra_id']) { self::invalid(); }
            $context=ApiRequestContext::resolve($d,$endpoint,$payload);
            unset($payload['context']);
        } elseif (in_array($d['entityCode']??null,['item_custos_manual_progress','proc_compra_sinalizadores_med_progress','proc_compra_quantidades_med_progress','proc_compra_quantidades_periodo_med_progress'],true)) {
            if (($endpoint['requestContextFields']??[])!==[]) { self::invalid(); }
        } elseif (RuntimePurchaseContextAudit::supports($d)) {
            if (!RuntimePurchaseContextAudit::binding($d,$endpoint,'read')) { self::invalid(); }
            $context=ApiRequestContext::resolve($d,$endpoint,$payload);
            unset($payload['context']);
        }
        $url=parse_url((string)($endpoint['url']??''));
        if(!$nfAvailable && (!self::supports($d)||($d['primaryKey']??null)!==self::primaryKey($d)||($d['apiBinding']['sourceCode']??null)!=='motor_progress_ciw_cadastros'||($d['apiSource']['responseContract']??null)!=='sursum-programs'
            ||strtoupper((string)($endpoint['method']??''))!=='POST'||!is_array($endpoint['bodyTemplate']??null)||count($endpoint['bodyTemplate'])!==2
            ||($endpoint['bodyTemplate']['operation']??null)!=='read'||($endpoint['bodyTemplate']['resource']??null)!==self::RESOURCES[$d['entityCode']]
            ||!is_array($url)||array_intersect(['user','pass','query','fragment'],array_keys($url))
            ||!in_array($url['path']??null,['/med/web/SursumPrograms/ciw-cadastros','/SursumPrograms/ciw-cadastros'],true)
            ||!(($url['scheme']??null)==='http'&&($url['host']??null)==='127.0.0.1'&&($url['port']??null)===19910||($url['scheme']??null)==='https'&&($url['host']??null)==='192.168.0.111'&&($url['port']??null)===9911)))self::invalid();
        $allowed=['skip','take','pageSize','page','filter','filters','sort','group','aggregate','aggregates','context','entityCode','operation','actionId','programId','permissionPrefix','traceability','_runtime','_runtimeEndpoint','runtime'];
        if(array_diff(array_keys($payload),$allowed))self::invalid();
        foreach(['context','group','aggregate','aggregates']as $name)if(isset($payload[$name])&&$payload[$name]!==[])self::invalid();
        $take=self::integer($payload['take']??$payload['pageSize']??50,1,200);
        if(isset($payload['pageSize'])&&self::integer($payload['pageSize'],1,200)!==$take)self::invalid();
        $page=isset($payload['page'])?self::integer($payload['page'],1,100001):null;
        $skip=self::integer($payload['skip']??($page===null?0:($page-1)*$take),0,100000);
        if($page!==null&&$skip!==($page-1)*$take)self::invalid();
        $filters=[];
        if(isset($payload['filter'])&&$payload['filter']!==[])$filters[]=$payload['filter'];
        if(isset($payload['filters'])){if(!is_array($payload['filters'])||!array_is_list($payload['filters'])||count($payload['filters'])>50)self::invalid();$filters=array_merge($filters,$payload['filters']);}
        $leaves=0;foreach($filters as &$filter)$filter=self::filter($d,$filter,0,$leaves);unset($filter);
        $query=['skip'=>$skip,'take'=>$take];
        if($filters){$query['filter']=count($filters)===1?$filters[0]:['logic'=>'and','filters'=>$filters];self::depth($query['filter'],0);}
        $sort=$payload['sort']??[];
        if(!is_array($sort)||!array_is_list($sort)||count($sort)>4)self::invalid();
        $seen=[];
        foreach($sort as $s){
            if(!is_array($s)||count($s)!==2||!is_string($s['field']??null)||!isset($d['fields'][$s['field']])||in_array($s['field'],['record_key','row_stamp'],true)||isset($seen[$s['field']])||!in_array($s['dir']??null,['asc','desc'],true))self::invalid();
            $seen[$s['field']]=true;
        }
        if($sort)$query['sort']=$sort;
        return $query+$context;
    }
    /** Converte o DTO da barra e do grid nativos para a arvore fechada do ABL. */
    private static function filter(array $d,mixed $f,int $depth,int &$leaves): array
    {
        if(!is_array($f)||$depth>6)self::invalid();
        if(array_key_exists('filters',$f)){
            if(count($f)!==2||!in_array($f['logic']??null,['and','or'],true)||!is_array($f['filters'])||!array_is_list($f['filters'])||count($f['filters'])<1||count($f['filters'])>50)self::invalid();
            $children=[];foreach($f['filters']as $child)$children[]=self::filter($d,$child,$depth+1,$leaves);
            return ['logic'=>$f['logic'],'filters'=>$children];
        }
        if(array_diff(array_keys($f),['field','operator','value','ignoreCase','id','label','fields','type','dataType','displayValue'])||!is_string($f['field']??null)||in_array($f['field'],['record_key','row_stamp'],true)||!isset($d['fields'][$f['field']]))self::invalid();
        if(isset($f['fields'])&&$f['fields']!==[]&&$f['fields']!==[$f['field']])self::invalid();
        if(array_key_exists('ignoreCase',$f)&&$f['ignoreCase']!==true)self::invalid();
        $op=$f['operator']??null;$type=$d['fields'][$f['field']]['dataType']??'';
        $op=match($op){'startsWith'=>'startswith','notContains'=>'doesnotcontain','isEmpty'=>'isempty','isNotEmpty'=>'isnotempty','isNull'=>'isnull','isNotNull'=>'isnotnull',default=>$op};
        $leaf=['field'=>$f['field'],'operator'=>$op];
        if(in_array($op,['between','relative','in','notIn'],true)){
            // Expandir antes de contar: maximo de 50 folhas tambem para listas/faixas.
            $children=[];$value=$f['value']??null;$logic=$op==='in'?'or':'and';
            if(in_array($op,['between','relative'],true)){
                $keys=$op==='relative'?['start','end','mode','amount','unit','direction','label']:['start','end'];
                if(!is_array($value)||array_diff(array_keys($value),$keys)||$op==='relative'&&$type!=='date')self::invalid();
                $start=$value['start']??null;$end=$value['end']??null;
                if($start!==null)$children[]=self::filter($d,['field'=>$f['field'],'operator'=>'gte','value'=>$start],$depth+1,$leaves);
                if($end!==null)$children[]=self::filter($d,['field'=>$f['field'],'operator'=>'lte','value'=>$end],$depth+1,$leaves);
                if($type!=='string'&&count($children)===2&&$children[0]['value']>$children[1]['value'])self::invalid();
            }else{
                if(!is_array($value)||!array_is_list($value)||count($value)>50)self::invalid();
                foreach($value as $v){
                    // O editor nativo de listas devolve strings mesmo para inteiros.
                    if($type==='integer'&&is_string($v)&&preg_match('/\A-?(?:0|[1-9][0-9]{0,9})\z/',$v))$v=(int)$v;
                    $children[]=self::filter($d,['field'=>$f['field'],'operator'=>$op==='in'?'eq':'neq','value'=>$v],$depth+1,$leaves);
                }
            }
            if(!$children)self::invalid();
            return count($children)===1?$children[0]:['logic'=>$logic,'filters'=>$children];
        }
        if(++$leaves>50)self::invalid();
        if(in_array($op,['isnull','isnotnull','isempty','isnotempty'],true)){
            // Operadores sem editor usam value=true na barra do construtor.
            if(isset($f['value'])&&!in_array($f['value'],[true,''],true)||in_array($op,['isempty','isnotempty'],true)&&$type!=='string')self::invalid();
            return $leaf;
        }
        if(!array_key_exists('value',$f)||!in_array($op,['eq','neq','gt','gte','lt','lte','contains','doesnotcontain','startswith','endswith'],true))self::invalid();
        if(in_array($op,['contains','doesnotcontain','startswith','endswith'],true)&&$type!=='string')self::invalid();
        if($type==='boolean'&&!in_array($op,['eq','neq'],true))self::invalid();
        $v=$f['value'];if($v===null){if(!in_array($op,['eq','neq'],true))self::invalid();return $leaf+['value'=>null];}
        if($type==='date')$v=self::civilDate($v);
        $valid=match($type){
            'integer'=>is_int($v)&&$v>=-2147483648&&$v<=2147483647,
            'decimal'=>(is_int($v)||is_float($v))&&is_finite((float)$v)&&abs($v)<=999999999999999999&&round((float)$v,10)===(float)$v,
            'string'=>is_string($v)&&mb_strlen($v)<=256&&!preg_match('/[\x00-\x1f\x7f]/',$v),
            'date'=>true,
            'boolean'=>is_bool($v),
            default=>false,
        };
        if(!$valid)self::invalid();
        $leaf['value']=$v;if(isset($f['ignoreCase']))$leaf['ignoreCase']=true;
        return $leaf;
    }
    private static function depth(array $filter,int $depth): void
    {
        if($depth>6)self::invalid();
        foreach($filter['filters']??[] as $child)self::depth($child,$depth+1);
    }
    private static function civilDate(mixed $value): string
    {
        if(!is_string($value)||!preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})(?:T([0-9]{2}):([0-9]{2}):([0-9]{2})(?:\.[0-9]{1,6})?(Z|[+-][0-9]{2}:[0-9]{2}))?\z/',$value,$parts)||!checkdate((int)$parts[2],(int)$parts[3],(int)$parts[1]))self::invalid();
        if(strlen($value)===10)return $value;
        if((int)$parts[4]>23||(int)$parts[5]>59||(int)$parts[6]>59)self::invalid();
        if($parts[7]!=='Z'&&(!preg_match('/\A[+-](0[0-9]|1[0-4]):([0-5][0-9])\z/',$parts[7])||substr($parts[7],1,2)==='14'&&substr($parts[7],4)!=='00'))self::invalid();
        // DatePicker/DateRange serializam Date em UTC. Recuperar a data civil do
        // ambiente MED (America/Sao_Paulo), inclusive o fim de faixa 23:59:59.
        return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone('America/Sao_Paulo'))->format('Y-m-d');
    }
}
