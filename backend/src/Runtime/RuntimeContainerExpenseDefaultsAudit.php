<?php
namespace App\Runtime;
/** Closed selection command and lookup proof. No expense rules or formulas. */
final class RuntimeContainerExpenseDefaultsAudit {
 public const PARAMETERS=['ordem_campo','ordem_direcao'];
 public static function supports(array$d):bool{return($d['entityCode']??null)==='container_despesas_selecao_progress';}
 public static function order(mixed$p):bool{return RuntimeProgressAuditSchema::exact($p,self::PARAMETERS)&&in_array($p['ordem_campo'],['ordem','despesa_id','descricao','log_calculada','perc_calculo'],true)&&in_array($p['ordem_direcao'],['asc','desc'],true);}
 public static function binding(array$d,array$e,string$op):bool{
  $url=parse_url((string)($e['url']??''));
  return self::supports($d)&&($d['primaryKey']??null)==='container_id'&&($d['apiBinding']['sourceCode']??null)==='motor_progress_container_processos'&&($d['apiSource']['responseContract']??null)==='sursum-programs'&&($e['method']??null)==='POST'&&($e['requestContextFields']??null)===['container_id']&&RuntimeProgressAuditSchema::exact($e['bodyTemplate']??null,['resource','operation'])&&$e['bodyTemplate']['resource']===($op==='options'?'despesas-selecao':'impostos')&&$e['bodyTemplate']['operation']===($op==='options'?'options':'selecionar_despesas')&&is_array($url)&&!array_intersect(['user','pass','query','fragment'],array_keys($url))&&in_array($url['path']??null,['/SursumPrograms/containers-processos','/med/web/SursumPrograms/containers-processos'],true)&&(($url['scheme']??null)==='http'&&($url['host']??null)==='127.0.0.1'&&($url['port']??null)===19910||($url['scheme']??null)==='https'&&($url['host']??null)==='192.168.0.111'&&($url['port']??null)===9911);
 }
 public static function options(array$body,array$context):bool{
  if(($body['success']??null)!==true||isset($body['error'])||!is_array($body['data']??null)||!array_is_list($body['data'])||!is_int($body['total']??null)||$body['total']!==count($body['data'])||$body['total']>200||isset($body['audit'])||isset($body['committed']))return false;
  $schema=['container_id'=>'id','despesa_id'=>'id','position'=>'id','descricao'=>['type'=>'string','nullable'=>true],'ordem'=>['type'=>'integer','nullable'=>true],'log_calculada'=>['type'=>'boolean','nullable'=>true],'perc_calculo'=>['type'=>'decimal','nullable'=>true],'label'=>'string'];$seen=[];$prior=0;
  foreach($body['data']as$row){if(!RuntimeProgressAuditSchema::row($row,$schema)||$row['container_id']!==$context['container_id']||isset($seen[$row['despesa_id']])||$row['position']<=$prior)return false;$seen[$row['despesa_id']]=true;$prior=$row['position'];}return true;
 }
 public static function inspect(array$d,array$e,array$body,array$context,string$actor):array{
  $bad=static fn()=>['before'=>[],'after'=>[],'events'=>[],'metadata'=>['available'=>false,'reason'=>'invalid']];$a=$body['audit']??null;$cid=$context['container_id'];
  $keys=['version','source','resource','operation','recordId','container_id','before','after','changes','actor'];if(is_array($a)&&array_key_exists('request_id',$a))$keys[]='request_id';
  if(!self::binding($d,$e,'process')||($body['committed']??null)!==true||$actor===''||!RuntimeProgressAuditSchema::exact($a,$keys)||!RuntimeProgressAuditSchema::exact($a['actor']??null,['login'])||$a['actor']['login']!==$actor)return$bad();
  $master=RuntimeContainerAudit::confirmedFiscalEvent(['resource'=>'containers','operation'=>'update','recordId'=>$cid,'before'=>$a['before'],'after'=>$a['after']]);
  if($master===null||!RuntimeProgressAuditSchema::same($master['before'],$master['after'])||!is_string($master['before']['cod_estabel'])||!str_starts_with($master['before']['cod_estabel'],'5')||!is_array($a['changes'])||!array_is_list($a['changes'])||!$a['changes']||count($a['changes'])>50000)return$bad();
  $calcs=0;
  foreach($a['changes']as$c){if(!RuntimeProgressAuditSchema::exact($c,['resource','operation','recordId','before','after'])||!in_array($c['resource'],['despesas_container','calcs_imp_container'],true))return$bad();$event=RuntimeContainerAudit::confirmedFiscalEvent($c);if($event===null||($event['metadata']['containerId']??null)!==$cid)return$bad();if($c['resource']==='calcs_imp_container'){if($c['operation']!=='create')return$bad();++$calcs;}elseif(!in_array($c['operation'],['create','update'],true))return$bad();}
  return$calcs===1?RuntimeContainerAudit::inspect($d,$e,$body,'selecionar_despesas',$cid,$cid):$bad();
 }
}
