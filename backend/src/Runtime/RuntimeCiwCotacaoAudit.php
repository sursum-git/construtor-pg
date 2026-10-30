<?php
namespace App\Runtime;
/** Historico manual e invalidacoes: contrato fechado de uma unica fonte registrada. */
final class RuntimeCiwCotacaoAudit
{
 private const FIELDS=['hist_cotacao_bc_id'=>'integer','data'=>'date','serie'=>'integer','valor'=>'decimal','dt_hr_busca'=>'datetime','nome_serie'=>'string','log_ok'=>'boolean','log_manual'=>'boolean','row_stamp'=>'string'];
 public static function supports(array $d):bool {return ($d['entityCode']??null)==='hist_cotacoes_bc_progress';}
 public static function inspect(array $d,array $e,array $body,string $operation,mixed $expectedId):array
 {
  $no=static fn(string $why):array=>['before'=>[],'after'=>[],'events'=>[],'metadata'=>['available'=>false,'reason'=>$why]];
  $a=$body['audit']??null;
  if(!self::binding($d,$e,$operation))return $no('unsupported_binding');
  foreach(self::FIELDS as $name=>$type)if(($d['fields'][$name]['dataType']??null)!==$type||($d['fields'][$name]['jsonPath']??null)!==$name)return $no('invalid_definition');
  if(!is_array($a)||array_diff(array_keys($a),['version','source','resource','operation','recordId','before','after','changes'])||($a['version']??null)!==1||($a['source']??null)!=='progress-transaction'||($a['resource']??null)!=='hist_cotacoes_bc'||($a['operation']??null)!==$operation
   ||!self::id($a['recordId']??null)||$expectedId!==null&&(string)$expectedId!==(string)$a['recordId']||!is_array($a['before']??null)||!is_array($a['after']??null)
   ||!is_array($a['changes']??null)||!array_is_list($a['changes'])||count($a['changes'])>50000)return $no('invalid');
  $id=$a['recordId'];$before=$a['before'];$after=$a['after'];
  if(in_array($operation,['create','import-bc'],true)?$before!==[]:!self::row($before,$id))return $no('invalid');
  if($operation==='delete'?$after!==[]:!self::row($after,$id))return $no('invalid');
  if($operation==='import-bc'){if(($after['log_manual']??null)!==false||($after['log_ok']??null)!==true||($after['serie']??null)!==1||($after['valor']??0)<=0||$a['changes']!==[])return $no('invalid_import');}
  elseif($operation!=='create'&&($before['log_manual']??null)!==true||$operation!=='delete'&&($after['log_manual']??null)!==true)return $no('not_manual');
  if($a['changes']!==[]&&($operation==='delete'||($after['serie']??null)!==9999))return $no('unexpected_effect');
  $events=[];$seen=[];
  foreach($a['changes'] as $c){
   if(!is_array($c)||array_diff(array_keys($c),['resource','operation','recordId','before','after'])||($c['resource']??null)!=='containers'||($c['operation']??null)!=='update'||!self::id($c['recordId']??null)||isset($seen[$c['recordId']])||!self::container($c['before']??null,$c['recordId'])||!self::container($c['after']??null,$c['recordId']))return $no('invalid_effect');
   $seen[$c['recordId']]=true;$b=$c['before'];$v=$c['after'];
   if($b['situacao']!==1||$b['ordem_busca_dolar_compra']!=='1'||$v['situacao']!==$b['situacao']||$v['ordem_busca_dolar_compra']!==$b['ordem_busca_dolar_compra']||$v['log_versao_custo_desatualizada']!==true||$v['dt_hr_ult_alt_versao_custo']===null)return $no('invalid_effect');
   $events[]=['before'=>$b,'after'=>$v,'metadata'=>['available'=>true,'version'=>1,'source'=>'progress-transaction','resource'=>'containers','operation'=>'update','recordId'=>$c['recordId'],'scope'=>['resource'=>'hist_cotacoes_bc','recordId'=>$id]]];
  }
  return ['before'=>$before,'after'=>$after,'events'=>$events,'metadata'=>['available'=>true,'version'=>1,'source'=>'progress-transaction','resource'=>'hist_cotacoes_bc','operation'=>$operation,'recordId'=>$id]];
 }
 public static function isImportEndpoint(array $d,array $e):bool {return self::binding($d,$e,'import-bc');}
 private static function binding(array $d,array $e,string $operation):bool {
  $url=parse_url((string)($e['url']??''));
  return !(!self::supports($d)||($d['primaryKey']??null)!=='hist_cotacao_bc_id'||($d['apiBinding']['sourceCode']??null)!=='motor_progress_ciw_cadastros'||($d['apiSource']['responseContract']??null)!=='sursum-programs'
   ||!in_array($operation,['create','update','delete','import-bc'],true)||strtoupper((string)($e['method']??''))!=='POST'
   ||!is_array($e['bodyTemplate']??null)||count($e['bodyTemplate'])!==2||($e['bodyTemplate']['operation']??null)!==$operation||($e['bodyTemplate']['resource']??null)!=='hist_cotacoes_bc'
   ||!is_array($url)||array_intersect(['user','pass','query','fragment'],array_keys($url))||!in_array($url['path']??null,['/SursumPrograms/ciw-cadastros','/med/web/SursumPrograms/ciw-cadastros'],true)
   ||!(($url['scheme']??null)==='http'&&($url['host']??null)==='127.0.0.1'&&($url['port']??null)===19910||($url['scheme']??null)==='https'&&($url['host']??null)==='192.168.0.111'&&($url['port']??null)===9911));
 }
 private static function id(mixed $v):bool {return is_int($v)&&$v>0&&$v<=2147483647;}
 private static function date(mixed $v):bool {
  return is_string($v)&&(bool)preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/',$v,$p)&&checkdate((int)$p[2],(int)$p[3],(int)$p[1]);
 }
 private static function time(mixed $v):bool {
  return is_string($v)&&(bool)preg_match('/\A([0-9]{4}-[0-9]{2}-[0-9]{2})T([0-9]{2}):([0-9]{2}):([0-9]{2})(?:\.[0-9]{3})?\z/',$v,$p)&&self::date($p[1])&&(int)$p[2]<24&&(int)$p[3]<60&&(int)$p[4]<60;
 }
 private static function row(array $row,int $id):bool {
  if(array_diff_key($row,self::FIELDS)||array_diff_key(self::FIELDS,$row)||($row['hist_cotacao_bc_id']??null)!==$id)return false;
  foreach(self::FIELDS as $name=>$type){$v=$row[$name];if($v===null){if(in_array($name,['hist_cotacao_bc_id','row_stamp'],true))return false;continue;}
   $valid=match($type){'integer'=>is_int($v)&&$v>=-2147483648&&$v<=2147483647,'date'=>self::date($v),'datetime'=>self::time($v),'decimal'=>(is_int($v)||is_float($v))&&is_finite((float)$v)&&round((float)$v,5)===(float)$v,'boolean'=>is_bool($v),'string'=>is_string($v),default=>false};
   if(!$valid)return false;
  }
  return mb_strlen((string)($row['nome_serie']??''))<=200&&(bool)preg_match('/\A[A-F0-9]{64}\z/',$row['row_stamp']);
 }
 private static function container(mixed $row,int $id):bool {
  $keys=['container_id','situacao','ordem_busca_dolar_compra','log_versao_custo_desatualizada','dt_hr_ult_alt_versao_custo'];
  return is_array($row)&&!array_diff(array_keys($row),$keys)&&!array_diff($keys,array_keys($row))&&($row['container_id']??null)===$id&&is_int($row['situacao'])&&is_string($row['ordem_busca_dolar_compra'])
   &&($row['log_versao_custo_desatualizada']===null||is_bool($row['log_versao_custo_desatualizada']))&&($row['dt_hr_ult_alt_versao_custo']===null||self::time($row['dt_hr_ult_alt_versao_custo']));
 }
}
