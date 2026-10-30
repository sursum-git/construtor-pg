<?php
namespace App\Runtime;
/** Fixed global read binding and complete identity, without financial rules. */
final class RuntimeCostDistributionRead
{
 public static function supports(array $definition):bool {return ($definition['entityCode']??null)==='custo_distribuicao_itens_aptos_progress';}
 private static function invalid(int $status):never {throw new RuntimeHttpException($status===422?'COST_DISTRIBUTION_REQUEST_INVALID':'COST_DISTRIBUTION_RESPONSE_INVALID','Consulta ou identidade de distribuição inválida.',$status);}
 public static function resolveContext(array $definition,array $endpoint,array $payload):array {
  if(!self::supports($definition)||($definition['primaryKey']??null)!=='record_key'||($definition['apiBinding']['sourceCode']??null)!=='motor_progress_ciw_cadastros'||($definition['apiSource']['responseContract']??null)!=='sursum-programs'||($endpoint['requestContextFields']??[])!==[]||strtoupper((string)($endpoint['method']??''))!=='POST'||!in_array($endpoint['bodyTemplate']['operation']??null,['read','get'],true)||($endpoint['bodyTemplate']['resource']??null)!=='custo_distribuicao_itens_aptos'||count($endpoint['bodyTemplate']??[])!==2||($payload['context']??[])!==[])self::invalid(422);
  return [];
 }
 public static function submittedAliases(array $payload,mixed $id):void {
  if(!is_string($id)||$id===''||strlen($id)>2048)self::invalid(422);
  foreach([$payload,is_array($payload['record']??null)?$payload['record']:[]] as $submitted)foreach(['id','record_key'] as $alias)if(array_key_exists($alias,$submitted)&&(!is_string($submitted[$alias])||$submitted[$alias]!==$id))self::invalid(422);
 }
 public static function assertRow(mixed $row,?string $requestedKey=null):void {
  if(!is_array($row)||!is_string($row['record_key']??null)||strlen($row['record_key'])>2048||$requestedKey!==null&&$row['record_key']!==$requestedKey)self::invalid(502);
  try{$key=json_decode($row['record_key'],true,4,JSON_THROW_ON_ERROR);}catch(\JsonException){self::invalid(502);}
  if(!is_array($key)||array_keys($key)!==['item_container']||!is_int($key['item_container'])||!is_int($row['item_container']??null)||$key['item_container']!==$row['item_container']||$row['item_container']<-2147483648||$row['item_container']>2147483647)self::invalid(502);
 }
}
