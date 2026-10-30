<?php
namespace App\Runtime;
/** Aceita imagens apenas do recurso fechado de equivalencias da busca. */
final class RuntimeEstoqueTermoAudit
{
 public static function supports(array $definition):bool{return ($definition['entityCode']??null)==='estoque_termos_busca_progress';}
 public static function inspect(array $d,array $e,array $body,string $operation,mixed $expectedId):array
 {
  $no=static fn(string $reason):array=>['before'=>[],'after'=>[],'metadata'=>['available'=>false,'reason'=>$reason]];
  $url=parse_url((string)($e['url']??''));$a=$body['audit']??null;
  if(!self::supports($d)||($d['primaryKey']??null)!=='termo_digitado'||($d['apiBinding']['sourceCode']??null)!=='motor_progress_estoque_termos'||($d['apiSource']['responseContract']??null)!=='sursum-programs'
   ||!in_array($operation,['create','update','delete'],true)||strtoupper((string)($e['method']??''))!=='POST'
   ||!is_array($e['bodyTemplate']??null)||count($e['bodyTemplate'])!==2||($e['bodyTemplate']['operation']??null)!==$operation||($e['bodyTemplate']['resource']??null)!=='estoque_termos'
   ||!is_array($url)||array_intersect(['user','pass','query','fragment'],array_keys($url))||!in_array($url['path']??null,['/SursumPrograms/ciw-cadastros','/med/web/SursumPrograms/ciw-cadastros'],true)
   ||!(($url['scheme']??null)==='http'&&($url['host']??null)==='127.0.0.1'&&($url['port']??null)===19910||($url['scheme']??null)==='https'&&($url['host']??null)==='192.168.0.111'&&($url['port']??null)===9911))return $no('unsupported_binding');
  if(!is_array($a)||array_diff(array_keys($a),['version','source','resource','operation','recordId','before','after'])||($a['version']??null)!==1||($a['source']??null)!=='progress-transaction'||($a['resource']??null)!=='estoque_termos'||($a['operation']??null)!==$operation
   ||!is_string($expectedId)||$expectedId===''||mb_strlen($expectedId)>50||($a['recordId']??null)!==$expectedId||!is_array($a['before']??null)||!is_array($a['after']??null))return $no('invalid');
  foreach(['termo_digitado'=>'string','termo_pesquisa'=>'string','ativo'=>'boolean','row_stamp'=>'string'] as $name=>$type){$f=$d['fields'][$name]??null;if(!is_array($f)||($f['dataType']??null)!==$type||($f['jsonPath']??null)!==$name)return $no('invalid_definition');}
  $before=$a['before'];$after=$a['after'];
  if($operation==='create'?$before!==[]:!self::validRow($before,$expectedId))return $no('invalid');
  if($operation==='delete'?$after!==[]:!self::validRow($after,$expectedId))return $no('invalid');
  return ['before'=>$before,'after'=>$after,'metadata'=>['available'=>true,'version'=>1,'source'=>'progress-transaction','resource'=>'estoque_termos','operation'=>$operation,'recordId'=>$expectedId]];
 }
 private static function validRow(array $row,string $id):bool
 {
  $keys=['termo_digitado','termo_pesquisa','ativo','row_stamp'];
  if(array_diff(array_keys($row),$keys)||array_diff($keys,array_keys($row))||($row['termo_digitado']??null)!==$id)return false;
  return is_string($row['termo_pesquisa'])&&trim($row['termo_pesquisa'])!==''&&mb_strlen($row['termo_pesquisa'])<=100&&is_bool($row['ativo'])&&is_string($row['row_stamp'])&&(bool)preg_match('/\A[A-F0-9]{64}\z/',$row['row_stamp']);
 }
}
