<?php
namespace App\Runtime;
/** Fixed version-owned projections. Financial values come exclusively from ABL. */
final class RuntimeCiwComparisonRead
{
 private const RESOURCES=['container_custos_comparacao_real_progress'=>'container_custos_comparacao_real','container_custos_comparacao_dolar_progress'=>'container_custos_comparacao_dolar'];
 private const IDS=['container_id','versao_id','item_container_custo_id','item_container_id'];
 private const NUMBERS=['qt_alocada','qt_confirm_emb','vl_custo_prev','vl_desp_prev','vl_tot_prev','vl_unit_prev','vl_desp_prev_sem_distr_custo','vl_tot_sem_distr_custo','vl_unit_sem_distr_custo','vl_desp_prev_perc_fixo','vl_tot_perc_fixo','vl_unit_perc_fixo'];
 public static function supports(array $definition):bool {return isset(self::RESOURCES[$definition['entityCode']??'']);}
 private static function invalid(int $status):never {throw new RuntimeHttpException($status===422?'COMPARISON_REQUEST_INVALID':'COMPARISON_RESPONSE_INVALID','Contexto ou resposta comparativa de custos invÃ¡lido.',$status);}
 public static function resolveContext(array $definition,array $endpoint,array $payload,string $operation):array {
  if(!self::supports($definition)||($definition['primaryKey']??null)!=='record_key'||($definition['apiBinding']['sourceCode']??null)!=='motor_progress_ciw_cadastros'||($definition['apiSource']['responseContract']??null)!=='sursum-programs'||($endpoint['requestContextFields']??null)!==['container_id','versao_id']||strtoupper((string)($endpoint['method']??''))!=='POST'||!in_array($operation,['read','get'],true)||($endpoint['bodyTemplate']['operation']??null)!==$operation||($endpoint['bodyTemplate']['resource']??null)!==self::RESOURCES[$definition['entityCode']]||count($endpoint['bodyTemplate']??[])!==2)self::invalid(422);
  $url=parse_url((string)($endpoint['url']??''));
  if(!is_array($url)||array_intersect(['user','pass','query','fragment'],array_keys($url))||!in_array($url['path']??null,['/med/web/SursumPrograms/ciw-cadastros','/SursumPrograms/ciw-cadastros'],true)||!(($url['scheme']??null)==='http'&&($url['host']??null)==='127.0.0.1'&&($url['port']??null)===19910||($url['scheme']??null)==='https'&&($url['host']??null)==='192.168.0.111'&&($url['port']??null)===9911))self::invalid(422);
  return ApiRequestContext::resolve($definition,$endpoint,$payload);
 }
 private static function key(mixed $raw,int $status):array {
  if(!is_string($raw)||$raw===''||strlen($raw)>2048)self::invalid($status);
  try{$key=json_decode($raw,true,4,JSON_THROW_ON_ERROR);}catch(\JsonException){self::invalid($status);}
  $fields=['container_id','versao_id','item_container_custo_id'];
  if(!is_array($key)||array_is_list($key)||count($key)!==count($fields)||array_diff(array_keys($key),$fields))self::invalid($status);
  foreach($fields as $field)if(!is_int($key[$field])||$key[$field]<1||$key[$field]>2147483647)self::invalid($status);
  return $key;
 }
 public static function submittedAliases(array $payload,mixed $id):void {
  self::key($id,422);
  foreach([$payload,is_array($payload['record']??null)?$payload['record']:[]] as $submitted)foreach(['id','record_key'] as $alias)if(array_key_exists($alias,$submitted)&&(!is_string($submitted[$alias])||$submitted[$alias]!==$id))self::invalid(422);
 }
 public static function assertRow(mixed $row,array $context,?string $requestedKey=null):void {
  $fields=array_merge(self::IDS,['codigo_erp','descricao'],self::NUMBERS,['record_key']);
  if(!is_array($row)||array_is_list($row)||count($row)!==count($fields)||array_diff(array_keys($row),$fields)||$requestedKey!==null&&($row['record_key']??null)!==$requestedKey)self::invalid(502);
  foreach(self::IDS as $field)if(!is_int($row[$field])||$row[$field]<1||$row[$field]>2147483647)self::invalid(502);
  foreach(['codigo_erp','descricao'] as $field)if($row[$field]!==null&&!is_string($row[$field]))self::invalid(502);
  foreach(self::NUMBERS as $field)if($row[$field]!==null&&(!(is_int($row[$field])||is_float($row[$field]))||!is_finite((float)$row[$field])))self::invalid(502);
  foreach(self::key($row['record_key'],502) as $field=>$value)if($row[$field]!==$value)self::invalid(502);
  foreach(['container_id','versao_id'] as $field)if(!is_int($context[$field]??null)||$context[$field]<1||$context[$field]>2147483647||$row[$field]!==$context[$field])self::invalid(502);
 }
}
