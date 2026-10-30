<?php
namespace App\Runtime;
/** Contrato exclusivo de im-param; nao amplia a identidade inteira dos demais CRUDs. */
final class RuntimeCiwParametroAudit
{
 public static function supports(array $definition):bool{return ($definition['entityCode']??null)==='im_param_progress';}
 public static function inspect(array $d,array $e,array $body,string $operation,mixed $expectedId):array
 {
  $no=static fn(string $reason):array=>['before'=>[],'after'=>[],'metadata'=>['available'=>false,'reason'=>$reason]];
  $url=parse_url((string)($e['url']??''));$a=$body['audit']??null;
  if(!self::supports($d)||($d['primaryKey']??null)!=='cod_param'||($d['apiBinding']['sourceCode']??null)!=='motor_progress_ciw_cadastros'||($d['apiSource']['responseContract']??null)!=='sursum-programs'
   ||!in_array($operation,['create','update','delete'],true)||strtoupper((string)($e['method']??''))!=='POST'
   ||!is_array($e['bodyTemplate']??null)||count($e['bodyTemplate'])!==2||($e['bodyTemplate']['operation']??null)!==$operation||($e['bodyTemplate']['resource']??null)!=='im_param'
   ||!is_array($url)||array_intersect(['user','pass','query','fragment'],array_keys($url))||!in_array($url['path']??null,['/SursumPrograms/ciw-cadastros','/med/web/SursumPrograms/ciw-cadastros'],true)
   ||!(($url['scheme']??null)==='http'&&($url['host']??null)==='127.0.0.1'&&($url['port']??null)===19910||($url['scheme']??null)==='https'&&($url['host']??null)==='192.168.0.111'&&($url['port']??null)===9911))return $no('unsupported_binding');
  if(!is_array($a)||array_diff(array_keys($a),['version','source','resource','operation','recordId','before','after'])||($a['version']??null)!==1||($a['source']??null)!=='progress-transaction'||($a['resource']??null)!=='im_param'||($a['operation']??null)!==$operation
   ||!self::validId($expectedId)||($a['recordId']??null)!==$expectedId||!is_array($a['before']??null)||!is_array($a['after']??null))return $no('invalid');
  foreach(['cod_param'=>'string','val_param'=>'string','tp_param'=>'integer','row_stamp'=>'string'] as $name=>$type){
   $f=$d['fields'][$name]??null;if(!is_array($f)||($f['dataType']??null)!==$type||($f['jsonPath']??null)!==$name)return $no('invalid_definition');
  }
  $before=$a['before'];$after=$a['after'];
  if($operation==='create'?$before!==[]:!self::validRow($before,$expectedId))return $no('invalid');
  if($operation==='delete'?$after!==[]:!self::validRow($after,$expectedId))return $no('invalid');
  // Os valores podem conter credenciais administrativas. Auditar identidade/estado,
  // sem uma segunda copia de valores potencialmente secretos no historico.
  if($before!==[])$before['val_param']='[redigido]';if($after!==[])$after['val_param']='[redigido]';
  return ['before'=>$before,'after'=>$after,'metadata'=>['available'=>true,'version'=>1,'source'=>'progress-transaction','resource'=>'im_param','operation'=>$operation,'recordId'=>$expectedId,'redactedFields'=>['val_param']]];
 }
 private static function validId(mixed $id):bool{return is_string($id)&&trim($id)!==''&&mb_strlen($id)<=50&&!preg_match('/[\x00-\x1f\x7f]/u',$id);}
 private static function validRow(array $row,string $id):bool
 {
  $keys=['cod_param','val_param','tp_param','agrup_im_param_id','row_stamp'];
  if(array_diff(array_keys($row),$keys)||array_diff($keys,array_keys($row))||($row['cod_param']??null)!==$id)return false;
  if($row['val_param']!==null&&(!is_string($row['val_param'])||mb_strlen($row['val_param'])>500))return false;
  foreach(['tp_param','agrup_im_param_id'] as $key)if($row[$key]!==null&&(!is_int($row[$key])||$row[$key]<-2147483648||$row[$key]>2147483647))return false;
  return is_string($row['row_stamp'])&&(bool)preg_match('/\A[A-F0-9]{64}\z/',$row['row_stamp']);
 }
}
