<?php
namespace App\Runtime;
/** Closed native quick-maintenance bridge; no business formulas. */
final class RuntimeContainerExpenseBatch {
 public const FIELDS=['despesa_container_id','despesa_id','vl_prev','container_id','calc_imp_container_id','vl_base_imp','vl_negociado','vl_proforma','cta_corren_custo_distr_id','log_utilizado','log_manual','perc_calc','memoria_calculo','item_container_id'];
 public const EDITABLE=['vl_prev','vl_base_imp','log_manual'];
 public static function supports(array $d):bool{return($d['entityCode']??null)==='container_despesas_lote_progress';}
 private static function fail(string $message,int $status=422):never{throw new RuntimeHttpException('CONTAINER_EXPENSE_BATCH_INVALID',$message,$status);}
 private static function binding(array $d,array $e,string $op):void{
  $u=parse_url((string)($e['url']??''));
  if(!self::supports($d)||($d['primaryKey']??null)!=='despesa_container_id'||($d['apiBinding']['sourceCode']??null)!=='motor_progress_container_despesas_lote'||($d['apiSource']['responseContract']??null)!=='sursum-programs'||($e['method']??null)!=='POST'||($e['requestContextFields']??null)!==['container_id']||!RuntimeProgressAuditSchema::exact($e['bodyTemplate']??null,['resource','operation'])||$e['bodyTemplate']!==['resource'=>'container-despesas-lote','operation'=>$op]&&$e['bodyTemplate']!==['operation'=>$op,'resource'=>'container-despesas-lote']||!is_array($u)||array_intersect(['user','pass','query','fragment'],array_keys($u))||!in_array($u['path']??null,['/SursumPrograms/containers-despesas-lote','/med/web/SursumPrograms/containers-despesas-lote'],true)||!(($u['scheme']??null)==='http'&&($u['host']??null)==='127.0.0.1'&&($u['port']??null)===19910||($u['scheme']??null)==='https'&&($u['host']??null)==='192.168.0.111'&&($u['port']??null)===9911))self::fail('Vinculo do lote invalido.');
 }
 public static function image(mixed $row):bool{
  return RuntimeProgressAuditSchema::exact($row,self::FIELDS)&&RuntimeProgressAuditSchema::id($row['despesa_container_id']??null)&&RuntimeProgressAuditSchema::id($row['despesa_id']??null)&&RuntimeContainerAudit::confirmedFiscalEvent(['resource'=>'despesas_container','operation'=>'update','recordId'=>$row['despesa_container_id'],'before'=>$row,'after'=>$row])!==null;
 }
 private static function sameOriginal(array $a,array $b):bool{
  if(!self::image($a)||!self::image($b))return false;
  foreach(self::FIELDS as$f){if(in_array($f,['vl_prev','vl_base_imp','vl_negociado','vl_proforma','perc_calc'],true)&&$a[$f]!==null&&$b[$f]!==null){if($a[$f]!=$b[$f])return false;}elseif($a[$f]!==$b[$f])return false;}return true;
 }
 public static function run(array $d,array $config,array $p,string $op,string $actor,callable $permit,callable $transport,callable $map,callable $log):array{
  $e=$d['apiSource'][match($op){'read'=>'listEndpoint','get'=>'detailEndpoint',default=>'batchEndpoint'}]??[];self::binding($d,$e,$op);
  if(isset($p['filters'])&&(!is_array($p['filters'])||!array_is_list($p['filters'])||count($p['filters'])>50)||isset($p['filter'])&&!is_array($p['filter']))self::fail('Filtro invalido.');
  $context=ApiRequestContext::resolve($d,$e,$p);$cid=$context['container_id'];
  if($op!=='batch'){
   $allowed=['context','id','despesa_container_id','record','skip','take','page','pageSize','filters','filter','sort','entityCode','operation','actionId','programId','_runtime','_runtimeEndpoint'];if(array_diff(array_keys($p),$allowed))self::fail('Consulta possui campos desconhecidos.');
   $request=$context;
   if($op==='get'){
    $id=$p['id']??$p['despesa_container_id']??null;if(!(is_int($id)||is_string($id))||!preg_match('/\A[1-9][0-9]{0,9}\z/',(string)$id)||(int)$id>2147483647)self::fail('Identidade invalida.');
    foreach([$p,is_array($p['record']??null)?$p['record']:[]]as$submitted)foreach(['id','despesa_container_id']as$alias)if(array_key_exists($alias,$submitted)&&(!(is_int($submitted[$alias])||is_string($submitted[$alias]))||!preg_match('/\A[1-9][0-9]{0,9}\z/',(string)$submitted[$alias])||(string)$submitted[$alias]!== (string)$id))self::fail('Alias divergente.');$request['id']=(int)$id;
   }else{
    $take=$p['take']??$p['pageSize']??50;$page=$p['page']??null;if(!is_int($take)||$take<1||$take>200||isset($p['pageSize'])&&$p['pageSize']!==$take||$page!==null&&(!is_int($page)||$page<1||$page>100001))self::fail('Pagina invalida.');$skip=$p['skip']??($page===null?0:($page-1)*$take);if(!is_int($skip)||$skip<0||$skip>100000||$page!==null&&$skip!==($page-1)*$take)self::fail('Pagina invalida.');$request+=['skip'=>$skip,'take'=>$take];
    foreach(array_merge(isset($p['filter'])?[$p['filter']]:[],$p['filters']??[])as$f)if(!is_array($f)||($f['field']??null)!=='container_id'||($f['operator']??'eq')!=='eq'||(string)($f['value']??'')!==(string)$cid)self::fail('Filtro fora do contexto.');
    $sort=$p['sort']??[];if(!is_array($sort)||!array_is_list($sort)||count($sort)>4)self::fail('Ordenacao invalida.');$seen=[];
    foreach($sort as$s){if(!RuntimeProgressAuditSchema::exact($s,['field','dir'])||!in_array($s['field'],self::FIELDS,true)||isset($seen[$s['field']])||!in_array($s['dir'],['asc','desc'],true))self::fail('Ordenacao invalida.');$seen[$s['field']]=true;}if($sort)$request['sort']=$sort;
   }
   $response=$transport($e,$request);$body=$response['body'];if(($body['success']??null)!==true||isset($body['error']))self::fail('Resposta indisponivel.',502);
   $rows=$op==='get'?[$body['data']??null]:($body['data']??null);if(!is_array($rows)||!array_is_list($rows))self::fail('Resposta invalida.',502);
   foreach($rows as$row){if(!self::image($row)||$row['container_id']!==$cid||$op==='get'&&$row['despesa_container_id']!==$request['id'])self::fail('Resposta fora do contexto.',502);}
   if($op==='read'&&(!is_int($body['total']??null)||$body['total']<0||count($rows)!==min($take,max(0,$body['total']-$skip))))self::fail('Total invalido.',502);
   $log('read',[],[],['origin'=>'MED','itemsCount'=>count($rows),'technical'=>true]);return$op==='get'?$map($rows[0]):['data'=>array_map($map,$rows),'total'=>$body['total']];
  }
  if($actor==='')self::fail('Usuario autenticado obrigatorio.',401);
  if(($config['batchSnapshot']??null)!==['originalFields'=>self::FIELDS,'contextFields'=>['container_id']]||($config['allowCreate']??null)!==false)self::fail('Contrato assinado de snapshot ausente.');
  if(array_diff(array_keys($p),['context','changes','entityCode','operation','actionId','programId','_runtimeEndpoint']))self::fail('Propriedade desconhecida.');
  foreach(['entityCode','operation','actionId','programId']as$name)if(array_key_exists($name,$p)&&$p[$name]!==($config[$name]??($name==='actionId'?'batch':null)))self::fail('Contexto difere do endpoint assinado.');
  $changes=$p['changes']??null;if(!is_array($changes)||!array_is_list($changes)||!$changes||count($changes)>100)self::fail('Informe1a100alteracoes.');$ids=[];$clients=[];$kind=null;
  foreach($changes as$c){
   if(!is_array($c)||array_is_list($c)||array_diff(array_keys($c),['operation','clientId','id','data','original']))self::fail('Linha invalida.');$operation=$c['operation']??null;
   if(!in_array($operation,['update','delete'],true)||$kind!==null&&$kind!==$operation)self::fail('O lote exige somente alteracoes ou somente exclusoes.');$kind=$operation;
   if(($config[$operation==='update'?'allowUpdate':'allowDelete']??false)!==true||!$permit(($config['permissionPrefix']??$d['entityCode']).'.'.($operation==='update'?'edit':'delete')))self::fail('Sem permissao para o lote.',403);
   if(!RuntimeProgressAuditSchema::id($id=$c['id']??null)||isset($ids[$id])||!is_string($client=$c['clientId']??null)||!preg_match('/\A[A-Za-z0-9_.:-]{1,160}\z/',$client)||isset($clients[$client]))self::fail('Identidade repetida ou invalida.');$ids[$id]=true;$clients[$client]=true;
   if(!self::image($before=$c['original']??null)||$before['container_id']!==$cid||$before['despesa_container_id']!==$id)self::fail('Original fisico obrigatorio/fora do contexto.');
   if($kind==='delete'){if(array_key_exists('data',$c))self::fail('Exclusao nao recebe valores.');}
   else{if(!is_array($v=$c['data']??null)||array_is_list($v)||!$v||array_diff(array_keys($v),self::EDITABLE))self::fail('Campo imutavel ou valor ausente.');foreach($v as$f=>$value)if($f==='log_manual'?!is_bool($value):!((is_int($value)||is_float($value))&&is_finite((float)$value)))self::fail('Valor deve ser tipado e nao nulo.');}
  }
  $response=$transport($e,['operation'=>'batch','changes'=>$changes]+$context);$body=$response['body'];
  if(($body['success']??null)!==true||($body['committed']??null)!==true||isset($body['error']))self::fail('O lote foi recusado; nenhuma confirmacao de gravacao.',502);
  $audit=self::audit($body,$changes,$cid,$kind,$actor);$rows=$body['data']??null;$presentation=is_array($rows)&&array_is_list($rows);
  $returned=[];if($presentation)foreach($rows as$row){if(!self::image($row)||$row['container_id']!==$cid||!isset($ids[$row['despesa_container_id']])||isset($returned[$row['despesa_container_id']])){$presentation=false;break;}$returned[$row['despesa_container_id']]=$row;}
  // Commit was confirmed before presentation validation. Resolve the native
  // request so saveBatch discards its draft; never turn this into a retryable error.
  if(!$presentation){$log('batch.presentation_unavailable',[],[],['committed'=>true,'available'=>false,'actor'=>$actor]);return['data'=>[],'total'=>0,'committed'=>true,'auditAvailable'=>false,'presentationAvailable'=>false];}
  if($audit!==null){foreach($audit as$event){if($event['metadata']['resource']!=='despesas_container'||!isset($ids[$event['metadata']['recordId']]))continue;$id=$event['metadata']['recordId'];if($event['after']===[]?isset($returned[$id]):!isset($returned[$id])||!RuntimeProgressAuditSchema::same($event['after'],$returned[$id])){$audit=null;break;}}}
  if($audit===null){$log('batch.audit_unavailable',[],[],['committed'=>true,'available'=>false,'actor'=>$actor]);return['data'=>array_map($map,$rows),'total'=>count($rows),'committed'=>true,'auditAvailable'=>false];}
  foreach($audit as$event)$log($event['metadata']['operation'],$event['before'],$event['after'],$event['metadata']+['actor'=>$actor]);
  $log('batch',[],[],['committed'=>true,'changesCount'=>count($changes),'actor'=>$actor]);return['data'=>array_map($map,$rows),'total'=>count($rows),'results'=>$body['results'],'committed'=>true,'auditAvailable'=>true];
 }
 private static function audit(array $body,array $changes,int $cid,string $kind,string $actor):?array{
  $a=$body['audit']??null;if(!RuntimeProgressAuditSchema::exact($a,['version','source','resource','operation','container_id','actor','master','changes','warnings'])||($a['version']??null)!==1||($a['source']??null)!=='progress-transaction'||($a['resource']??null)!=='container-despesas'||($a['operation']??null)!=='batch_'.$kind||($a['container_id']??null)!==$cid||!is_array($a['actor']??null)||array_diff(array_keys($a['actor']),['login','id'])||($a['actor']['login']??null)!==$actor||isset($a['actor']['id'])&&!is_string($a['actor']['id'])||!RuntimeProgressAuditSchema::exact($a['master']??null,['before','after'])||!is_array($a['warnings'])||!array_is_list($a['warnings'])||count($a['warnings'])>1000||!is_array($a['changes']??null)||!array_is_list($a['changes'])||count($a['changes'])>50000)return null;
  $master=RuntimeContainerAudit::confirmedFiscalEvent(['resource'=>'containers','operation'=>'update','recordId'=>$cid,'before'=>$a['master']['before']??null,'after'=>$a['master']['after']??null]);if($master===null)return null;$events=[$master];$seen=[];$targets=[];$requested=array_column($changes,null,'id');
  if(($body['results']??null)!==array_map(fn($c)=>['clientId'=>$c['clientId'],'id'=>$c['id'],'operation'=>$c['operation']],$changes))return null;
  foreach($a['changes']as$c){if(!is_array($c)||!RuntimeProgressAuditSchema::exact($c,['resource','operation','recordId','before','after'])||!in_array($c['resource'],['despesas_container','calcs_imp_container'],true))return null;$e=RuntimeContainerAudit::confirmedFiscalEvent($c);$key=$c['resource'].':'.$c['recordId'];if($e===null||$e['metadata']['containerId']!==$cid||isset($seen[$key]))return null;$seen[$key]=true;
   if($c['resource']==='despesas_container'&&isset($requested[$c['recordId']])){if($c['operation']!==$kind||!self::sameOriginal($c['before'],$requested[$c['recordId']]['original'])||$kind==='update'&&$c['after']['despesa_id']!==$c['before']['despesa_id'])return null;$targets[$c['recordId']]=true;}$events[]=$e;
  }
  return count($targets)===count($changes)?$events:null;
 }
}
