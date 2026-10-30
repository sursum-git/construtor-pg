<?php
namespace App\Runtime;

/** Seven physical columns plus separately verified actual ownership; no file bytes. */
final class RuntimePurchaseAttachmentAudit
{
 private const MAIN=['anexo_proc_compra_id'=>'id','proc_compra_id'=>['type'=>'integer','nullable'=>true],'item_proc_compra_id'=>'id','descricao'=>['type'=>'string','nullable'=>true],'tipos_anexo_id'=>['type'=>'integer','nullable'=>true],'caminho_arquivo'=>['type'=>'string','nullable'=>true],'dt_hr_arquivo'=>'datetime','owner_proc_compra_id'=>'id'];
 public static function orderScope(array $d):bool{return ($d['entityCode']??null)==='proc_compra_documentos_progress';}
 public static function supports(array $d):bool{return self::orderScope($d)||($d['entityCode']??null)==='proc_compra_anexos_progress';}
 public static function contextFields(array $d):array{return self::orderScope($d)?['proc_compra_id']:['proc_compra_id','item_proc_compra_id'];}
 private static function schema(array $d):array{$s=self::MAIN;if(self::orderScope($d)){$s['item_proc_compra_id']=['type'=>'integer','nullable'=>true];}return $s;}
 private static function validItem(mixed $id,array $d):bool{return self::orderScope($d)?($id===null||is_int($id)&&$id>=0&&$id<=2147483647):RuntimeProgressAuditSchema::id($id);}
 public static function binding(array $d,array $e,string $op):bool{return self::supports($d)&&($e['requestContextFields']??[])===self::contextFields($d) && RuntimeProgressAuditSchema::ciwBinding($d,$e,self::orderScope($d)?'proc_compra_documentos_progress':'proc_compra_anexos_progress','anexo_proc_compra_id',self::orderScope($d)?'proc_compra_documentos':'proc_compra_anexos',$op,['read','get','create','update','delete']);}
 public static function editableField(string $field):bool{return in_array($field,['descricao','tipos_anexo_id','arquivo_upload'],true);}
 public static function dataRow(mixed $row,array $d=[]):bool
 {
  $schema=self::schema($d);unset($schema['owner_proc_compra_id']);$schema['proc_compra_id']='id';$schema['proc_compra_id_registro']=['type'=>'integer','nullable'=>true];
  return RuntimeProgressAuditSchema::row($row,$schema+['row_stamp'=>'stamp','tipo_anexo_descricao'=>'string','arquivo_upload'=>['type'=>'string','nullable'=>true]]) && self::validItem($row['item_proc_compra_id'],$d) && (!self::orderScope($d)||$row['proc_compra_id_registro']===$row['proc_compra_id']) && $row['arquivo_upload']===null;
 }
 public static function inspect(array $d,array $e,array $body,string $op,mixed $id,mixed $context,?string $actor):array
 {
  $no=static fn(string $reason='invalid'):array=>['before'=>[],'after'=>[],'events'=>[],'metadata'=>['available'=>false,'reason'=>$reason]];
  if(!in_array($op,['create','update','delete'],true)||!self::binding($d,$e,$op)){return $no('unsupported_binding');}
  if(!array_key_exists('audit',$body)){return $no('not_provided');}$a=$body['audit'];
  if(($body['success']??null)!==true||($body['committed']??null)!==true||isset($body['error'])||!RuntimeProgressAuditSchema::exact($a,['version','source','resource','operation','recordId','actor','before','after','changes','warnings'])||$a['version']!==1||$a['source']!=='progress-transaction'||$a['resource']!=='anexos-proc-compra'||$a['operation']!==$op||!RuntimeProgressAuditSchema::id($a['recordId'])||$id!==null&&RuntimePurchaseOrderAudit::recordId($id)!==$a['recordId']||!is_string($actor)||$actor===''||!RuntimeProgressAuditSchema::exact($a['actor'],['login'])||$a['actor']['login']!==$actor||!RuntimeProgressAuditSchema::row($context,array_fill_keys(self::contextFields($d),'id'))||$a['changes']!==[]||$a['warnings']!==[]){return $no();}
  foreach(['before','after']as $name){
   if($name==='before'&&$op==='create'||$name==='after'&&$op==='delete'){if($a[$name]!==[]){return $no();}continue;}
   if(!RuntimeProgressAuditSchema::row($a[$name],self::schema($d))||!self::validItem($a[$name]['item_proc_compra_id'],$d)||$a[$name]['anexo_proc_compra_id']!==$a['recordId']||$a[$name]['owner_proc_compra_id']!==$context['proc_compra_id']||(self::orderScope($d)?$a[$name]['proc_compra_id']!==$context['proc_compra_id']:$a[$name]['item_proc_compra_id']!==$context['item_proc_compra_id'])){return $no();}
  }
  if($op!=='delete'){
   if(!self::dataRow($body['data']??null,$d)){return $no('invalid_data');}$data=$body['data'];unset($data['row_stamp'],$data['tipo_anexo_descricao'],$data['arquivo_upload']);$data['owner_proc_compra_id']=$data['proc_compra_id'];$data['proc_compra_id']=$data['proc_compra_id_registro'];unset($data['proc_compra_id_registro']);
   if(!RuntimeProgressAuditSchema::same($data,$a['after'])){return $no('invalid_data');}
  }elseif(!RuntimeProgressAuditSchema::exact($body['data']??null,['anexo_proc_compra_id'])||$body['data']['anexo_proc_compra_id']!==$a['recordId']){return $no('invalid_data');}
  if(self::orderScope($d)&&($op==='create'&&$a['after']['item_proc_compra_id']!==0||$op==='update'&&$a['before']['item_proc_compra_id']!==$a['after']['item_proc_compra_id'])){return $no();}
  $item=$op==='delete'?$a['before']['item_proc_compra_id']:$a['after']['item_proc_compra_id'];
  return ['before'=>$a['before'],'after'=>$a['after'],'events'=>[],'metadata'=>['available'=>true,'version'=>1,'source'=>'progress-transaction','resource'=>'anexos_proc_compra','operation'=>$op,'recordId'=>$a['recordId'],'procCompraId'=>$context['proc_compra_id'],'itemProcCompraId'=>$item??0]];
 }
}
