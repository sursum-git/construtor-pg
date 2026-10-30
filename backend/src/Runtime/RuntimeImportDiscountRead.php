<?php
namespace App\Runtime;
/** Identity and context only. Financial aggregation remains unique in ABL. */
final class RuntimeImportDiscountRead
{
 private const RESOURCES=['descontos_importacao_geral_progress'=>'import_descontos_geral','descontos_importacao_lancamentos_progress'=>'import_descontos_lancamentos'];
 public static function supports(array $definition):bool {return isset(self::RESOURCES[$definition['entityCode']??'']);}
 private static function invalid(int $status):never {throw new RuntimeHttpException($status===422?'IMPORT_DISCOUNTS_REQUEST_INVALID':'IMPORT_DISCOUNTS_RESPONSE_INVALID','Identidade ou contexto do desconto invalido.',$status);}
 public static function resolveContext(array $definition,array $endpoint,array $payload):array {
  $entity=$definition['entityCode']??'';$expected=$entity==='descontos_importacao_lancamentos_progress'?['desconto_importacao_id']:[];
  if(!self::supports($definition)||($definition['primaryKey']??null)!=='record_key'||($definition['apiBinding']['sourceCode']??null)!=='motor_progress_ciw_cadastros'||($definition['apiSource']['responseContract']??null)!=='sursum-programs'||($endpoint['requestContextFields']??[])!==$expected||strtoupper((string)($endpoint['method']??''))!=='POST'||!in_array($endpoint['bodyTemplate']['operation']??null,['read','get'],true)||($endpoint['bodyTemplate']['resource']??null)!==self::RESOURCES[$entity]||count($endpoint['bodyTemplate']??[])!==2)self::invalid(422);
  if($expected===[]){if(($payload['context']??[])!==[])self::invalid(422);return [];}
  return ApiRequestContext::resolve($definition,$endpoint,$payload);
 }
 public static function submittedAliases(array $payload,mixed $id):void {
  if(!is_string($id)||$id===''||strlen($id)>2048)self::invalid(422);
  foreach([$payload,is_array($payload['record']??null)?$payload['record']:[]]as $submitted)foreach(['id','record_key']as $alias)if(array_key_exists($alias,$submitted)&&(!is_string($submitted[$alias])||$submitted[$alias]!==$id))self::invalid(422);
 }
 public static function assertRow(array $definition,mixed $row,array $context,?string $requestedKey=null):void {
  $keys=($definition['entityCode']??null)==='descontos_importacao_lancamentos_progress'?['desconto_importacao_id','cta_corren_desconto_id']:['desconto_importacao_id'];
  if(!is_array($row)||!is_string($row['record_key']??null)||$row['record_key']===''||strlen($row['record_key'])>2048||$requestedKey!==null&&$row['record_key']!==$requestedKey)self::invalid(502);
  try{$decoded=json_decode($row['record_key'],true,4,JSON_THROW_ON_ERROR);}catch(\JsonException){self::invalid(502);}
  if(!is_array($decoded)||array_is_list($decoded)||count($decoded)!==count($keys)||array_diff(array_keys($decoded),$keys))self::invalid(502);
  foreach($keys as $key)if(!is_int($row[$key]??null)||$row[$key]<-2147483648||$row[$key]>2147483647||!is_int($decoded[$key]??null)||$decoded[$key]!==$row[$key])self::invalid(502);
  if(count($keys)===2){if(!is_int($context['desconto_importacao_id']??null)||$context['desconto_importacao_id']<1)self::invalid(502);ApiRequestContext::assertRows([$row],['desconto_importacao_id'=>$context['desconto_importacao_id']]);}
 }
}
