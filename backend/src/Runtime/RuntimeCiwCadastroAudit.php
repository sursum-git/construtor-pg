<?php
namespace App\Runtime;
/** Contratos fechados dos cadastros CIW; não seleciona fonte por dados do cliente. */
final class RuntimeCiwCadastroAudit
{
 private const RESOURCES=[
  'sinalizadores_importacao'=>['sinaliz_imp_id',['sinaliz_imp_id'=>'integer','nome'=>'string','titulo'=>'string','descricao'=>'string','qt_dias_alerta'=>'integer'],['nome'=>100,'titulo'=>300,'descricao'=>600]],
  'formatos_amostra'=>['formato_amostra_id',['formato_amostra_id'=>'integer','descricao'=>'string','log_inativo'=>'boolean'],40],
  'coloracoes'=>['coloracao_id',['coloracao_id'=>'integer','descricao'=>'string','log_inativo'=>'boolean'],50],
  'situacoes_container'=>['situacao_container_id',['situacao_container_id'=>'integer','descricao'=>'string','log_gerar_saldo_a_pagar'=>'boolean','log_gerar_distr_custo'=>'boolean','num_ordem'=>'integer'],100],
  'tipos_versao_custo'=>['tipo_versao_custo_id',['tipo_versao_custo_id'=>'integer','descricao'=>'string'],100],
  'motivos_desconto'=>['motivo_desconto_id',['motivo_desconto_id'=>'integer','descricao'=>'string','log_considerar_custo'=>'integer'],100],
 ];
 public static function supports(array $definition):bool
 {
  foreach(self::RESOURCES as $name=>$contract)if(($definition['entityCode']??null)===$name.'_progress')return true;
  return false;
 }
 public static function resource(array $definition,array $endpoint,string $operation):?string
 {
  foreach(self::RESOURCES as $name=>$contract){
   if(($definition['entityCode']??null)!==$name.'_progress')continue;
   if($name==='sinalizadores_importacao'&&$operation!=='update')return null;
   $body=$endpoint['bodyTemplate']??null;
   if(($definition['primaryKey']??null)!==$contract[0]||($definition['apiBinding']['sourceCode']??null)!=='motor_progress_ciw_cadastros'||strtoupper((string)($endpoint['method']??''))!=='POST'||!is_array($body)||count($body)!==2||($body['operation']??null)!==$operation||($body['resource']??null)!==$name||!in_array($operation,['create','update','delete','batch'],true))return null;
   $url=parse_url((string)($endpoint['url']??''));
   if(!is_array($url)||!in_array($url['path']??'', ['/SursumPrograms/ciw-cadastros','/med/web/SursumPrograms/ciw-cadastros'],true))return null;
   $local=($url['scheme']??null)==='http'&&($url['host']??null)==='127.0.0.1'&&($url['port']??null)===19910;
   $remote=($url['scheme']??null)==='https'&&($url['host']??null)==='192.168.0.111'&&($url['port']??null)===9911;
   return $local||$remote?$name:null;
  }
  return null;
 }
 public static function validSnapshot(array $definition,array $snapshot,string $resource):bool
 {
  if(!isset(self::RESOURCES[$resource]))return false;
  if($snapshot===[])return true;
  [$key,$types,$maxLength]=self::RESOURCES[$resource];
  if(array_diff_key($snapshot,$types)||array_diff_key($types,$snapshot))return false;
  foreach($types as $name=>$type){
   $field=$definition['fields'][$name]??null;
   if(!is_array($field)||($field['dataType']??null)!==$type||($field['jsonPath']??null)!==$name)return false;
   $value=$snapshot[$name];if($value===null)continue;
   if($type==='integer'&&(!is_int($value)||$value<-2147483648||$value>2147483647))return false;
   if($type==='boolean'&&!is_bool($value))return false;
   if($type==='string'&&(!is_string($value)||mb_strlen($value)>(is_array($maxLength)?($maxLength[$name]??0):$maxLength)))return false;
   if($name==='log_considerar_custo'&&!in_array($value,[0,1],true))return false;
  }
  return true;
 }
}
