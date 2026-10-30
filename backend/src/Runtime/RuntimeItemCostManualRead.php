<?php
namespace App\Runtime;
/** Closed proof for shared manual cost rows. No invented container/company scope. */
final class RuntimeItemCostManualRead {
 public static function supports(array$d):bool{return($d['entityCode']??null)==='item_custos_manual_progress';}
 private static function fail(int$status=502):never{throw new RuntimeHttpException('ITEM_COST_MANUAL_READ_INVALID','Consulta de custos manuais invalida ou indisponivel.',$status);}
 private static function key(mixed$value,int$status):int{
  if(!is_string($value)||$value===''||strlen($value)>2048)self::fail($status);
  try{$key=json_decode($value,true,4,JSON_THROW_ON_ERROR);}catch(\JsonException){self::fail($status);}
  if(!RuntimeProgressAuditSchema::exact($key,['item_custo_id'])||!RuntimeProgressAuditSchema::id($key['item_custo_id']))self::fail($status);return$key['item_custo_id'];
 }
 private static function row(mixed$row):void{
  $schema=require __DIR__.'/RuntimeItemCostManualSchema.php';
  if(!RuntimeProgressAuditSchema::row($row,$schema)||$row['cod_tipo_custo']!==1||self::key($row['record_key'],502)!==$row['item_custo_id'])self::fail();
 }
 public static function readResponse(array$body):void{
  if(($body['success']??null)!==true||isset($body['audit'])||isset($body['committed'])||!is_int($body['total']??null)||$body['total']>50000)self::fail();
  $seen=[];foreach($body['data']as$row){self::row($row);if(isset($seen[$row['item_custo_id']]))self::fail();$seen[$row['item_custo_id']]=true;}
 }
 public static function getRequest(array$d,array$payload,mixed$id):void{
  $endpoint=$d['apiSource']['detailEndpoint']??[];
  if(array_diff(array_keys($payload),['id','record_key','record','context','entityCode','operation','actionId','programId','permissionPrefix','traceability','_runtime','_runtimeEndpoint','runtime']))self::fail(422);
  if(!RuntimeProgressAuditSchema::ciwBinding($d,$endpoint,'item_custos_manual_progress','record_key','item_custos_manual','get',['get'])||($endpoint['requestContextFields']??[])!==[]||!empty($payload['context']))self::fail(422);
  self::key($id,422);
  foreach([$payload,is_array($payload['record']??null)?$payload['record']:[]]as$source)foreach(['id','record_key']as$alias)if(array_key_exists($alias,$source)&&$source[$alias]!==$id)self::fail(422);
 }
 public static function getResponse(array$body,mixed$row,mixed$id):void{
  if(($body['success']??null)!==true||isset($body['audit'])||isset($body['committed']))self::fail();self::row($row);if(self::key($id,422)!==$row['item_custo_id'])self::fail();
 }
}
