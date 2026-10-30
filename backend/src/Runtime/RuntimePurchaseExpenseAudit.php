<?php
namespace App\Runtime;

/** Closed expense images shared by manual maintenance and missing-only generation. */
final class RuntimePurchaseExpenseAudit
{
 private const IMAGE=['despesa_proc_compra_id'=>'id','proc_compra_id'=>'id','despesa_id'=>'id','vl_prev'=>['type'=>'decimal','nullable'=>true],'calc_imp_proc_compra_id'=>['type'=>'integer','nullable'=>true]];
 public static function supports(array $d):bool{return ($d['entityCode']??null)==='proc_compra_despesas_progress';}
 public static function binding(array $d,array $e,string $op):bool{return ($e['requestContextFields']??[])===['proc_compra_id']&&RuntimeProgressAuditSchema::ciwBinding($d,$e,'proc_compra_despesas_progress','despesa_proc_compra_id','proc_compra_despesas',$op,['read','get','options','create','update','delete','generate']);}
 public static function editableField(string $f):bool{return in_array($f,['despesa_id','vl_prev'],true);}
 public static function image(mixed $v):bool{return RuntimeProgressAuditSchema::row($v,self::IMAGE);}
 public static function dataRow(mixed $v):bool{return RuntimeProgressAuditSchema::row($v,self::IMAGE+['row_stamp'=>'stamp','despesa_descricao'=>['type'=>'string','maxLength'=>4000]]);}
 private static function no(string $reason='invalid'):array{return ['before'=>[],'after'=>[],'events'=>[],'metadata'=>['available'=>false,'reason'=>$reason]];}
 private static function base(array $body,string $resource,string $op,array $context,?string $actor):bool
 {
  $a=$body['audit']??null;
  return ($body['success']??null)===true&&($body['committed']??null)===true&&!isset($body['error'])
   &&RuntimeProgressAuditSchema::row($context,['proc_compra_id'=>'id'])&&is_string($actor)&&trim($actor)!==''
   &&RuntimeProgressAuditSchema::exact($a,['version','source','resource','operation','recordId','actor','before','after','changes','warnings'])
   &&$a['version']===1&&$a['source']==='progress-transaction'&&$a['resource']===$resource&&$a['operation']===$op
   &&RuntimeProgressAuditSchema::id($a['recordId'])&&RuntimeProgressAuditSchema::exact($a['actor'],['login'])&&$a['actor']['login']===$actor&&$a['warnings']===[];
 }
 private static function metadata(string $resource,string $op,int $id,int $pid):array{return ['available'=>true,'version'=>1,'source'=>'progress-transaction','resource'=>$resource,'operation'=>$op,'recordId'=>$id,'procCompraId'=>$pid];}
 public static function inspect(array $d,array $e,array $body,string $op,mixed $id,mixed $context,?string $actor):array
 {
  if(!in_array($op,['create','update','delete'],true)||!self::binding($d,$e,$op))return self::no('unsupported_binding');
  if(!is_array($context)||!self::base($body,'despesas-proc-compra',$op,$context,$actor))return self::no();$a=$body['audit'];
  if($id!==null&&RuntimePurchaseOrderAudit::recordId($id)!==$a['recordId']||$a['changes']!==[])return self::no();
  foreach(['before','after']as$name){if($name==='before'&&$op==='create'||$name==='after'&&$op==='delete'){if($a[$name]!==[])return self::no();continue;}
   if(!self::image($a[$name])||$a[$name]['despesa_proc_compra_id']!==$a['recordId']||$a[$name]['proc_compra_id']!==$context['proc_compra_id'])return self::no();}
  if($op==='update'&&$a['before']['calc_imp_proc_compra_id']!==$a['after']['calc_imp_proc_compra_id'])return self::no();
  if($op==='delete'){if(!RuntimeProgressAuditSchema::exact($body['data']??null,['despesa_proc_compra_id'])||$body['data']['despesa_proc_compra_id']!==$a['recordId'])return self::no('invalid_data');}
  else{if(!self::dataRow($body['data']??null))return self::no('invalid_data');$data=$body['data'];unset($data['row_stamp'],$data['despesa_descricao']);if(!RuntimeProgressAuditSchema::same($data,$a['after']))return self::no('invalid_data');}
  return ['before'=>$a['before'],'after'=>$a['after'],'events'=>[],'metadata'=>self::metadata('despesas_proc_compra',$op,$a['recordId'],$context['proc_compra_id'])];
 }
 public static function inspectGeneration(array $d,array $e,array $body,array $context,?string $actor):array
 {
  if(!self::binding($d,$e,'generate')||!self::base($body,'procs-compra','generate',$context,$actor))return self::no('unsupported_binding');$a=$body['audit'];
  if($a['recordId']!==$context['proc_compra_id']||!RuntimePurchaseOrderAudit::image($a['before'])||!RuntimePurchaseOrderAudit::image($a['after'])
   ||$a['before']['proc_compra_id']!==$a['recordId']||!RuntimeProgressAuditSchema::same($a['before'],$a['after'])
   ||!is_array($a['changes'])||!array_is_list($a['changes'])||count($a['changes'])>200
   ||!is_int($body['created']??null)||$body['created']!==count($a['changes'])||!is_int($body['skipped']??null)||$body['skipped']<0||$body['skipped']>5000)return self::no();
  $events=[];$ids=[];$definitions=[];
  foreach($a['changes']as$c){
   if(!RuntimeProgressAuditSchema::exact($c,['resource','operation','recordId','before','after'])||$c['resource']!=='despesas-proc-compra'||$c['operation']!=='create'
    ||!RuntimeProgressAuditSchema::id($c['recordId'])||isset($ids[$c['recordId']])||$c['before']!==[]||!self::image($c['after'])
    ||$c['after']['despesa_proc_compra_id']!==$c['recordId']||$c['after']['proc_compra_id']!==$a['recordId']||$c['after']['calc_imp_proc_compra_id']!==0||$c['after']['vl_prev']===null||isset($definitions[$c['after']['despesa_id']]))return self::no('invalid_effect');
   $ids[$c['recordId']]=true;$definitions[$c['after']['despesa_id']]=true;$events[]=['before'=>[],'after'=>$c['after'],'metadata'=>self::metadata('despesas_proc_compra','create',$c['recordId'],$a['recordId'])];
  }
  return ['before'=>$a['before'],'after'=>$a['after'],'events'=>$events,'metadata'=>self::metadata('procs_compra','generate_expenses',$a['recordId'],$a['recordId'])];
 }
}
