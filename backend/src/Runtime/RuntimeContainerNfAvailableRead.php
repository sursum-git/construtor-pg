<?php
namespace App\Runtime;

/** Closed boundary only. NF identity, collection and row projection remain in the existing ABL reader. */
final class RuntimeContainerNfAvailableRead
{
 private const ENTITY='container_nfs_disponiveis_med_progress';
 private const FIELDS=['container_id'=>'id','nf_key'=>'string','documento_localizado'=>'boolean','estab'=>'string','documento'=>['type'=>'string','nullable'=>true],'serie'=>['type'=>'string','nullable'=>true],'cod_emitente'=>'integer','nat_operacao'=>'string','especie'=>['type'=>'integer','nullable'=>true],'dt_emissao'=>'date','dt_trans'=>'date','tot_valor'=>['type'=>'decimal','nullable'=>true],'despesa_nota'=>['type'=>'decimal','nullable'=>true],'valor_embal'=>['type'=>'decimal','nullable'=>true],'valor_frete'=>['type'=>'decimal','nullable'=>true],'valor_mercad'=>['type'=>'decimal','nullable'=>true],'valor_outras'=>['type'=>'decimal','nullable'=>true],'valor_seguro'=>['type'=>'decimal','nullable'=>true]];
 public static function supports(array $definition):bool{return($definition['entityCode']??null)===self::ENTITY;}
 private static function fail(int $status=502):never{throw new RuntimeHttpException('CONTAINER_NF_AVAILABLE_INVALID','Consulta de NFs disponiveis invalida ou fora do contexto MED.',$status);}
 private static function key(mixed $value):bool{return is_string($value)&&preg_match('/\Anf_[A-F0-9]{64}\z/D',$value)===1;}
 public static function request(array $d,array $endpoint,array $payload,string $operation,mixed $id=null):array
 {
  $url=parse_url((string)($endpoint['url']??''));
  if(!self::supports($d)||($d['primaryKey']??null)!=='nf_key'||($d['apiBinding']['sourceCode']??null)!=='motor_progress_container_financeiro'||($d['apiSource']['responseContract']??null)!=='sursum-programs'
   ||!in_array($operation,['read','get'],true)||($endpoint['method']??null)!=='POST'||($endpoint['requestContextFields']??null)!==['container_id']||!RuntimeProgressAuditSchema::exact($endpoint['bodyTemplate']??null,['operation','resource'])
   ||$endpoint['bodyTemplate']['operation']!==$operation||$endpoint['bodyTemplate']['resource']!=='nfs-disponiveis'||!is_array($url)||array_intersect(['user','pass','query','fragment'],array_keys($url))
   ||!in_array($url['path']??null,['/SursumPrograms/containers-financeiro','/med/web/SursumPrograms/containers-financeiro'],true)
   ||!(($url['scheme']??null)==='http'&&($url['host']??null)==='127.0.0.1'&&($url['port']??null)===19910||($url['scheme']??null)==='https'&&($url['host']??null)==='192.168.0.111'&&($url['port']??null)===9911)
   ||($d['apiSource']['listResponse']['itemsPath']??null)!=='data'||($d['apiSource']['detailResponse']['itemPath']??null)!=='data')self::fail(422);
  $common=['context','entityCode','operation','actionId','programId','permissionPrefix','traceability','_runtime','_runtimeEndpoint','runtime'];
  $allowed=$operation==='get'?array_merge($common,['id','nf_key','record']):array_merge($common,['filter','filters','sort','skip','take','page','pageSize','group','aggregate']);
  if(array_diff(array_keys($payload),$allowed))self::fail(422);
  $context=ApiRequestContext::resolve($d,$endpoint,$payload);
  if($operation==='get'){
   if(!self::key($id)||array_key_exists('record',$payload)&&(!is_array($payload['record'])||array_is_list($payload['record'])&&$payload['record']!==[]))self::fail(422);
   foreach([$payload,$payload['record']??[]]as$submitted){foreach(['id','nf_key']as$alias)if(array_key_exists($alias,$submitted)&&$submitted[$alias]!==$id)self::fail(422);if(array_key_exists('container_id',$submitted)&&$submitted['container_id']!==$context['container_id'])self::fail(422);}
  }else{
   // Page/filter/sort validation is shared with RuntimeCiwErpQuery.
   foreach(['take','pageSize']as$bound)if(isset($payload[$bound])&&(!is_int($payload[$bound])||$payload[$bound]<1||$payload[$bound]>200))self::fail(422);
  }
  return $context;
 }
 private static function row(mixed $row,array $context,?string $id):void
 {
  if(!RuntimeProgressAuditSchema::row($row,self::FIELDS)||!self::key($row['nf_key'])||$row['container_id']!==($context['container_id']??null)||$row['documento_localizado']!==true
   ||!str_starts_with($row['estab'],'5')||!str_starts_with($row['nat_operacao'],'3')||$id!==null&&$row['nf_key']!==$id)self::fail();
 }
 public static function pageResponse(array $body,array $query):void
 {
  if(!RuntimeProgressAuditSchema::exact($body,['success','data','total'])||$body['success']!==true||!is_int($body['total'])||$body['total']<0||$body['total']>50000
   ||!is_array($body['data'])||!array_is_list($body['data'])||count($body['data'])!==min($query['take'],max(0,$body['total']-$query['skip'])))self::fail();
  $seen=[];foreach($body['data']as$row){self::row($row,$query,null);if(isset($seen[$row['nf_key']]))self::fail();$seen[$row['nf_key']]=true;}
 }
 public static function response(array $body,mixed $data,array $context,?string $id=null):void
 {
  if(!RuntimeProgressAuditSchema::exact($body,['success','data'])||$body['success']!==true||$body['data']!==$data)self::fail();
  if($id!==null){self::row($data,$context,$id);return;}
  if(!is_array($data)||!array_is_list($data)||count($data)>200)self::fail();
  $seen=[];foreach($data as$row){self::row($row,$context,null);if(isset($seen[$row['nf_key']]))self::fail();$seen[$row['nf_key']]=true;}
 }
}
