<?php
namespace App\Runtime;
/** Fixed global source binding and raw identity checks; no selection formula. */
final class RuntimeSampleContainerItemsRead
{
 public static function supports(array $definition):bool {return ($definition['entityCode']??null)==='item_container_amostra_progress';}
 private static function invalid(int $status):never {throw new RuntimeHttpException($status===422?'SAMPLE_CONTAINER_ITEMS_REQUEST_INVALID':'SAMPLE_CONTAINER_ITEMS_RESPONSE_INVALID','Consulta ou identidade de itens de amostra inválida.',$status);}
 public static function resolveContext(array $definition,array $endpoint,array $payload):array {
  if(!self::supports($definition)||($definition['primaryKey']??null)!=='record_key'||($definition['apiBinding']['sourceCode']??null)!=='motor_progress_ciw_cadastros'||($definition['apiSource']['responseContract']??null)!=='sursum-programs'||($endpoint['requestContextFields']??[])!==[]||strtoupper((string)($endpoint['method']??''))!=='POST'||!in_array($endpoint['bodyTemplate']['operation']??null,['read','get'],true)||($endpoint['bodyTemplate']['resource']??null)!=='item_container_amostra'||count($endpoint['bodyTemplate']??[])!==2||array_key_exists('context',$payload)&&$payload['context']!==[])self::invalid(422);
  return [];
 }
 private static function keyId(mixed $key,int $status):int {
  if(!is_string($key)||$key===''||strlen($key)>2048)self::invalid($status);
  try{$value=json_decode($key,true,4,JSON_THROW_ON_ERROR);}catch(\JsonException){self::invalid($status);}
  if(!is_array($value)||array_keys($value)!==['item_container_id']||!is_int($value['item_container_id'])||$value['item_container_id']<-2147483648||$value['item_container_id']>2147483647)self::invalid($status);
  return $value['item_container_id'];
 }
 public static function submittedAliases(array $payload,mixed $id):void {
  self::keyId($id,422);
  foreach([$payload,is_array($payload['record']??null)?$payload['record']:[]] as $submitted)foreach(['id','record_key'] as $alias)if(array_key_exists($alias,$submitted)&&(!is_string($submitted[$alias])||$submitted[$alias]!==$id))self::invalid(422);
 }
 public static function assertRow(mixed $row,?string $requestedKey=null):void {
  if(!is_array($row)||!is_int($row['item_container_id']??null)||!is_int($row['container_id']??null)||$row['container_id']<-2147483648||$row['container_id']>2147483647||$requestedKey!==null&&($row['record_key']??null)!==$requestedKey)self::invalid(502);
  if(self::keyId($row['record_key']??null,502)!==$row['item_container_id'])self::invalid(502);
 }
}
