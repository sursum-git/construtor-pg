<?php
namespace App\Runtime;

/** Closed fiscal receipt; imports physical images without trusting browser values. */
final class RuntimePurchaseTaxAudit
{
 public static function binding(array $d,array $e):bool{return ($e['requestContextFields']??null)===['proc_compra_id']&&RuntimeProgressAuditSchema::ciwBinding($d,$e,'proc_compra_despesas_progress','despesa_proc_compra_id','proc_compra_despesas','calculate_taxes',['calculate_taxes']);}
 private static function no(string $reason='invalid_tax_receipt'):array{return ['before'=>[],'after'=>[],'events'=>[],'metadata'=>['available'=>false,'reason'=>$reason]];}
 private static function metadata(string $resource,string $op,int $id,int $pid,int $calc):array{return ['available'=>true,'version'=>1,'source'=>'progress-transaction','resource'=>$resource,'operation'=>$op,'recordId'=>$id,'procCompraId'=>$pid,'calculationId'=>$calc];}
 public static function inspect(array $d,array $e,array $body,array $context,?string $actor):array
 {
  if(!self::binding($d,$e))return self::no('unsupported_binding');
  if(($body['success']??null)!==true||($body['committed']??null)!==true||isset($body['error'])||!RuntimeProgressAuditSchema::row($context,['proc_compra_id'=>'id'])||!is_string($actor)||trim($actor)==='')return self::no();
  $a=$body['audit']??null;$calc=$body['calculationId']??null;$created=$body['created']??null;$updated=$body['updated']??null;
  if(!RuntimeProgressAuditSchema::id($calc)||!is_int($created)||!is_int($updated)||$created<0||$updated<0||$created+$updated>200
   ||!RuntimeProgressAuditSchema::exact($a,['version','source','resource','operation','recordId','actor','before','after','changes','warnings'])
   ||$a['version']!==1||$a['source']!=='progress-transaction'||$a['resource']!=='procs-compra'||$a['operation']!=='calculate_taxes'||$a['recordId']!==$context['proc_compra_id']
   ||!RuntimeProgressAuditSchema::exact($a['actor'],['login'])||$a['actor']['login']!==$actor||$a['warnings']!==[]
   ||!RuntimePurchaseOrderAudit::image($a['before'])||!RuntimePurchaseOrderAudit::image($a['after'])||$a['before']['proc_compra_id']!==$a['recordId']||!RuntimeProgressAuditSchema::same($a['before'],$a['after'])
   ||!is_array($a['changes'])||!array_is_list($a['changes'])||count($a['changes'])!==1+$created+$updated)return self::no();
  $events=[];$ids=[];$definitions=[];$calcSeen=false;$nCreate=0;$nUpdate=0;
  foreach($a['changes']as$c){
   if(!RuntimeProgressAuditSchema::exact($c,['resource','operation','recordId','before','after'])||!RuntimeProgressAuditSchema::id($c['recordId']))return self::no('invalid_tax_effect');
   if($c['resource']==='calcs-imp-proc-compra'){
    if($calcSeen||$c['operation']!=='create'||$c['recordId']!==$calc||$c['before']!==[]||!RuntimeProgressAuditSchema::row($c['after'],['calc_imp_proc_compra_id'=>'id','dt_hr_calculo'=>['type'=>'datetime','nullable'=>false],'usuario_id'=>['type'=>'string','maxLength'=>256]])||$c['after']['calc_imp_proc_compra_id']!==$calc||$c['after']['usuario_id']!==$actor)return self::no('invalid_tax_calculation');
    $calcSeen=true;$events[]=['before'=>[],'after'=>$c['after'],'metadata'=>self::metadata('calcs_imp_proc_compra','create',$calc,$a['recordId'],$calc)];continue;
   }
   if($c['resource']!=='despesas-proc-compra'||!in_array($c['operation'],['create','update'],true)||!RuntimePurchaseExpenseAudit::image($c['after'])
    ||$c['after']['despesa_proc_compra_id']!==$c['recordId']||$c['after']['proc_compra_id']!==$a['recordId']||$c['after']['calc_imp_proc_compra_id']!==$calc||$c['after']['vl_prev']===null
    ||round($c['after']['vl_prev'],2)!=(float)$c['after']['vl_prev']||isset($ids[$c['recordId']])||isset($definitions[$c['after']['despesa_id']]))return self::no('invalid_tax_expense');
   if($c['operation']==='create'){if($c['before']!==[])return self::no('invalid_tax_before');$nCreate++;}
   else{
    if(!RuntimePurchaseExpenseAudit::image($c['before'])||$c['before']['despesa_proc_compra_id']!==$c['recordId']||$c['before']['proc_compra_id']!==$a['recordId']||$c['before']['despesa_id']!==$c['after']['despesa_id']||$c['before']['calc_imp_proc_compra_id']===$calc)return self::no('invalid_tax_before');$nUpdate++;
   }
   $ids[$c['recordId']]=true;$definitions[$c['after']['despesa_id']]=true;$events[]=['before'=>$c['before'],'after'=>$c['after'],'metadata'=>self::metadata('despesas_proc_compra',$c['operation'],$c['recordId'],$a['recordId'],$calc)];
  }
  if(!$calcSeen||$nCreate!==$created||$nUpdate!==$updated)return self::no('invalid_tax_effect_count');
  return ['before'=>$a['before'],'after'=>$a['after'],'events'=>$events,'metadata'=>self::metadata('procs_compra','calculate_taxes',$a['recordId'],$a['recordId'],$calc)];
 }
}
