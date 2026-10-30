<?php
namespace App\Runtime;
/** Closed proof for registered container allocation; shared schemas own physical types. */
final class RuntimeContainerAllocationAudit {
 public static function supports(array $d):bool{return ($d['entityCode']??null)==='container_alocacao_progress';}
 public static function binding(array $d,array $e):bool {
  $u=parse_url((string)($e['url']??''));$url=is_array($u)&&!array_intersect(['user','pass','query','fragment'],array_keys($u))&&in_array($u['path']??null,['/SursumPrograms/containers-itens','/med/web/SursumPrograms/containers-itens'],true)&&((!isset($u['scheme'])&&!isset($u['host'])&&!isset($u['port']))||($u['scheme']??null)==='http'&&($u['host']??null)==='127.0.0.1'&&($u['port']??null)===19910||($u['scheme']??null)==='https'&&($u['host']??null)==='192.168.0.111'&&($u['port']??null)===9911);
  return self::supports($d)&&($d['primaryKey']??null)==='container_id'&&($d['apiBinding']['sourceCode']??null)==='motor_progress_container_alocacao'&&($d['apiSource']['responseContract']??null)==='sursum-programs'&&($e['method']??null)==='POST'&&($e['requestContextFields']??null)===['container_id']&&RuntimeProgressAuditSchema::exact($e['bodyTemplate']??null,['resource','operation'])&&($e['bodyTemplate']['resource']??null)==='container-alocacoes'&&($e['bodyTemplate']['operation']??null)==='allocate'&&$url;
 }
 private static function ids(mixed $v):bool {if(!is_array($v)||!array_is_list($v)||count($v)>25){return false;}$seen=[];foreach($v as$id){if(!RuntimeProgressAuditSchema::id($id)||isset($seen[$id])){return false;}$seen[$id]=true;}return true;}
 public static function inspect(array $d,array $e,array $b,array $ctx,array $selected,string $actor):array {
  $invalid=static fn()=>['before'=>[],'after'=>[],'events'=>[],'metadata'=>['available'=>false,'reason'=>'invalid_allocation_proof']];
  if(!self::binding($d,$e)||!RuntimeProgressAuditSchema::exact($ctx,['container_id'])||!RuntimeProgressAuditSchema::id($cid=$ctx['container_id'])||!self::ids($selected)||!$selected||trim($actor)===''||($b['success']??null)!==true||($b['committed']??null)!==true||isset($b['error'])){return $invalid();}
  $a=$b['audit']??null;
  if(!RuntimeProgressAuditSchema::exact($a,['version','source','resource','operation','recordId','container_id','before','after','changes','warnings','actor'])||$a['version']!==1||$a['source']!=='progress-transaction'||$a['resource']!=='container-alocacoes'||$a['operation']!=='allocate'||$a['recordId']!==$cid||$a['container_id']!==$cid||!RuntimeProgressAuditSchema::exact($a['actor'],['login'])||$a['actor']['login']!==$actor||!is_array($a['changes'])||!array_is_list($a['changes'])||count($a['changes'])>50000||!is_array($a['warnings'])||!array_is_list($a['warnings'])||count($a['warnings'])>1000){return $invalid();}
  $root=RuntimeContainerAudit::confirmedAllocationEvent(['resource'=>'containers','operation'=>'update','recordId'=>$cid,'before'=>$a['before'],'after'=>$a['after']]);
  if($root===null||$root['metadata']['containerId']!==$cid||!self::ids($skipped=$b['skipped']??null)||!is_array($allocated=$b['allocated']??null)||!array_is_list($allocated)||count($allocated)>25){return $invalid();}
  $byOwner=[];$byId=[];
  foreach($allocated as$row){if(!is_array($row)||!RuntimeProgressAuditSchema::id($id=$row['item_container_id']??null)||($row['container_id']??null)!==$cid||!RuntimeProgressAuditSchema::id($owner=$row['item_proc_compra_id']??null)||isset($byId[$id])||isset($byOwner[$owner])||!in_array($owner,$selected,true)||!is_numeric($row['qt_alocada']??null)||$row['qt_alocada']<=0||RuntimeContainerAudit::confirmedAllocationEvent(['resource'=>'itens_container','operation'=>'create','recordId'=>$id,'before'=>[],'after'=>$row])===null){return $invalid();}$byOwner[$owner]=$row;$byId[$id]=$row;}
  $partition=array_merge(array_keys($byOwner),$skipped);sort($partition);$expected=$selected;sort($expected);if($partition!==$expected){return $invalid();}
  if(!$allocated&&!RuntimeProgressAuditSchema::same($a['before'],$a['after'])){return $invalid();}
  $events=[];$seen=[];$created=[];
  foreach($a['changes']as$change){if(!RuntimeProgressAuditSchema::exact($change,['resource','operation','recordId','before','after'])||!in_array($change['resource'],['itens_container','despesas_container','calcs_imp_container','cta_corren_fornec_imp'],true))return $invalid();$ev=RuntimeContainerAudit::confirmedAllocationEvent($change);if($ev===null||$ev['metadata']['containerId']!==$cid)return $invalid();$key=$change['resource'].':'.$change['recordId'];if(isset($seen[$key]))return $invalid();$seen[$key]=true;
   if($change['resource']==='itens_container'){
    if($change['operation']==='create'){if(!isset($byId[$change['recordId']])||!RuntimeProgressAuditSchema::same($byId[$change['recordId']],$change['after']))return $invalid();$created[$change['recordId']]=true;}
    elseif($change['operation']==='update'){foreach(['item_proc_compra_id','qt_alocada','qt_confirm_emb','qt_recbto_fisico']as$f)if($change['before'][$f]!==$change['after'][$f])return $invalid();}
    else return $invalid();
   }
   if($change['resource']==='cta_corren_fornec_imp'){$row=$change['after'];if($change['operation']!=='create'||!in_array($row['cod_tipo_movto']??null,[4,5,6,10],true)||!RuntimeProgressAuditSchema::id($row['item_proc_compra_id']??null)||!RuntimeProgressAuditSchema::id($row['fornecedor_id']??null)||($row['login']??null)!==$actor||($row['cod_tipo_movto']===4?($row['item_container_id']??null)!==0:!RuntimeProgressAuditSchema::id($row['item_container_id']??null)))return $invalid();$ev['metadata']['itemProcCompraId']=$row['item_proc_compra_id'];}
   $events[]=$ev;
  }
  if(count($created)!==count($byId))return $invalid();$root['metadata']['operation']='allocate';$root['metadata']+=['allocatedCount'=>count($allocated),'skippedCount'=>count($skipped)];return ['before'=>$root['before'],'after'=>$root['after'],'events'=>$events,'metadata'=>$root['metadata']];
 }
}
