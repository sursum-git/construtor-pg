<?php
namespace App\Runtime;
/** Closed item-owned raw ledger. Does not interpret amounts or movement types. */
final class RuntimeCostDistributionLedgerRead
{
 public static function supports(array $definition):bool {return ($definition['entityCode']??null)==='custo_distribuicao_lancamentos_item_progress';}
 private static function invalid(int $status):never {throw new RuntimeHttpException($status===422?'COST_DISTRIBUTION_LEDGER_REQUEST_INVALID':'COST_DISTRIBUTION_LEDGER_RESPONSE_INVALID','Contexto ou identidade da conta corrente inválido.',$status);}
 public static function resolveContext(array $definition,array $endpoint,array $payload):array {
  if(!self::supports($definition)||($definition['primaryKey']??null)!=='record_key'||($definition['apiBinding']['sourceCode']??null)!=='motor_progress_ciw_cadastros'||($definition['apiSource']['responseContract']??null)!=='sursum-programs'||($endpoint['requestContextFields']??[])!==['item_container_id']||strtoupper((string)($endpoint['method']??''))!=='POST'||!in_array($endpoint['bodyTemplate']['operation']??null,['read','get'],true)||($endpoint['bodyTemplate']['resource']??null)!=='custo_distribuicao_lancamentos_item'||count($endpoint['bodyTemplate']??[])!==2)self::invalid(422);
  return ApiRequestContext::resolve($definition,$endpoint,$payload);
 }
 public static function submittedAliases(array $payload,mixed $id):void {
  if(!is_string($id)||$id===''||strlen($id)>2048)self::invalid(422);
  foreach([$payload,is_array($payload['record']??null)?$payload['record']:[]] as $submitted)foreach(['id','record_key'] as $alias)if(array_key_exists($alias,$submitted)&&(!is_string($submitted[$alias])||$submitted[$alias]!==$id))self::invalid(422);
 }
 public static function assertRow(mixed $row,array $context,?string $requestedKey=null):void {
  if(!is_array($row)||!is_string($row['record_key']??null)||strlen($row['record_key'])>2048||$requestedKey!==null&&$row['record_key']!==$requestedKey)self::invalid(502);
  try{$key=json_decode($row['record_key'],true,4,JSON_THROW_ON_ERROR);}catch(\JsonException){self::invalid(502);}
  $keys=['item_container_id','cta_corren_custo_distr_id'];if(!is_array($key)||array_is_list($key)||count($key)!==count($keys)||array_diff(array_keys($key),$keys))self::invalid(502);
  foreach($keys as $field)if(!is_int($key[$field])||!is_int($row[$field]??null)||$key[$field]!==$row[$field]||$row[$field]<-2147483648||$row[$field]>2147483647)self::invalid(502);
  if(!is_int($context['item_container_id']??null)||$context['item_container_id']<1)self::invalid(502);
  ApiRequestContext::assertRows([$row],['item_container_id'=>$context['item_container_id']]);
 }
}
