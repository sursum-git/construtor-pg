<?php
namespace App\Runtime;
/** Closed proof for the explicit tax-only command; shares all physical schemas. */
final class RuntimeContainerTaxAudit {
 public static function selected(array $operation):bool{return($operation['code']??null)==='calcular_impostos'||($operation['bodyTemplate']['operation']??null)==='calcular_impostos';}
 public static function binding(array $d,array $e):bool{
  $url=parse_url((string)($e['url']??''));
  return RuntimeContainerAudit::resource($d,$e,'calcular_impostos')==='container_processes'&&($e['requestContextFields']??null)===['container_id']&&is_array($url)&&!array_intersect(['user','pass','query','fragment'],array_keys($url))&&(($url['scheme']??null)==='http'&&($url['host']??null)==='127.0.0.1'&&($url['port']??null)===19910||($url['scheme']??null)==='https'&&($url['host']??null)==='192.168.0.111'&&($url['port']??null)===9911);
 }
 public static function inspect(array $d,array $e,array $body,array $context,string $actor):array{
  $bad=static fn():array=>['before'=>[],'after'=>[],'events'=>[],'metadata'=>['available'=>false,'reason'=>'invalid']];
  $cid=$context['container_id']??null;$a=$body['audit']??null;
  $fields=['version','source','resource','operation','recordId','container_id','before','after','changes','actor'];
  if(is_array($a)&&array_key_exists('request_id',$a))$fields[]='request_id';
  if(!self::binding($d,$e)||$actor===''||($body['committed']??null)!==true||!RuntimeProgressAuditSchema::exact($a,$fields)||!RuntimeProgressAuditSchema::exact($a['actor']??null,['login'])||$a['actor']['login']!==$actor)return$bad();
  $main=RuntimeContainerAudit::confirmedFiscalEvent(['resource'=>'containers','operation'=>'update','recordId'=>$cid,'before'=>$a['before']??null,'after'=>$a['after']??null]);
  if($main===null||!RuntimeProgressAuditSchema::same($main['before'],$main['after'])||!is_string($main['before']['cod_estabel'])||!str_starts_with($main['before']['cod_estabel'],'5'))return$bad();
  if(!is_array($a['changes']??null)||!array_is_list($a['changes'])||!$a['changes']||count($a['changes'])>50000)return$bad();
  $calc=0;
  foreach($a['changes']as$c){
   if(!RuntimeProgressAuditSchema::exact($c,['resource','operation','recordId','before','after'])||!in_array($c['resource'],['despesas_container','calcs_imp_container'],true))return$bad();
   $event=RuntimeContainerAudit::confirmedFiscalEvent($c);if($event===null||($event['metadata']['containerId']??null)!==$cid)return$bad();
   if($c['resource']==='calcs_imp_container'){if($c['operation']!=='create')return$bad();++$calc;}
   elseif(!in_array($c['operation'],['create','update'],true))return$bad();
  }
  if($calc!==1)return$bad();
  return RuntimeContainerAudit::inspect($d,$e,$body,'calcular_impostos',$cid,$cid);
 }
}
