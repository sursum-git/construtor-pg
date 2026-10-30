<?php
namespace App\Tests\Runtime;
use App\Runtime\{RuntimeCiwCotacaoAudit, RuntimeApiEntityActionService, RuntimeTransactionService};
use PHPUnit\Framework\TestCase;
final class RuntimeCiwCotacaoAuditTest extends TestCase
{
 private function definition(): array {
  $types=['hist_cotacao_bc_id'=>'integer','data'=>'date','serie'=>'integer','valor'=>'decimal','dt_hr_busca'=>'datetime','nome_serie'=>'string','log_ok'=>'boolean','log_manual'=>'boolean','row_stamp'=>'string'];
  $fields=[];foreach($types as $k=>$v)$fields[$k]=['code'=>$k,'dataType'=>$v,'jsonPath'=>$k];
  return ['entityCode'=>'hist_cotacoes_bc_progress','primaryKey'=>'hist_cotacao_bc_id','apiBinding'=>['sourceCode'=>'motor_progress_ciw_cadastros'],'apiSource'=>['responseContract'=>'sursum-programs'],'fields'=>$fields];
 }
 private function endpoint(): array {return ['method'=>'POST','url'=>'http://127.0.0.1:19910/med/web/SursumPrograms/ciw-cadastros','bodyTemplate'=>['resource'=>'hist_cotacoes_bc','operation'=>'update']];}
 private function row(): array {return ['hist_cotacao_bc_id'=>10,'data'=>'2026-10-21','serie'=>9999,'valor'=>5.12345,'dt_hr_busca'=>'2026-10-21T12:00:00.000','nome_serie'=>'manual','log_ok'=>false,'log_manual'=>true,'row_stamp'=>str_repeat('A',64)];}
 private function body(): array {
  $before=['container_id'=>20,'situacao'=>1,'ordem_busca_dolar_compra'=>'1','log_versao_custo_desatualizada'=>false,'dt_hr_ult_alt_versao_custo'=>null];
  $after=$before;$after['log_versao_custo_desatualizada']=true;$after['dt_hr_ult_alt_versao_custo']='2026-10-21T12:00:00.123';
  $row=$this->row();$new=$row;$new['valor']=6;$new['row_stamp']=str_repeat('B',64);
  return ['audit'=>['version'=>1,'source'=>'progress-transaction','resource'=>'hist_cotacoes_bc','operation'=>'update','recordId'=>10,'before'=>$row,'after'=>$new,'changes'=>[['resource'=>'containers','operation'=>'update','recordId'=>20,'before'=>$before,'after'=>$after]]]];
 }
 public function testConfirmedFiveDecimalHistoryAndAllContainerEffectsArePreserved(): void {
  $a=RuntimeCiwCotacaoAudit::inspect($this->definition(),$this->endpoint(),$this->body(),'update',10);
  self::assertTrue($a['metadata']['available']);self::assertSame(5.12345,$a['before']['valor']);self::assertSame(6,$a['after']['valor']);
  self::assertCount(1,$a['events']);self::assertSame(20,$a['events'][0]['metadata']['recordId']);
  self::assertSame(false,$a['events'][0]['before']['log_versao_custo_desatualizada']);self::assertSame(true,$a['events'][0]['after']['log_versao_custo_desatualizada']);
 }
 public function testClosedBindingRejectsWrongSourceUrlResourceIdentityAndUnknownFields(): void {
  foreach(['source','url','urlUser','body','extra','id','field','decimal','date','manual','childKey','childExtra','childOperation','childPredicate','childValue','childDate','duplicate','wrongSeries'] as $bad){
   $d=$this->definition();$e=$this->endpoint();$b=$this->body();
   if($bad==='source')$d['apiBinding']['sourceCode']='other';if($bad==='url')$e['url']='http://evil/SursumPrograms/ciw-cadastros';
   if($bad==='urlUser')$e['url']='http://user@127.0.0.1:19910/med/web/SursumPrograms/ciw-cadastros';
   if($bad==='body')$e['bodyTemplate']['resource']='im_param';if($bad==='extra')$b['audit']['unknown']=true;
   if($bad==='id')$b['audit']['recordId']=11;if($bad==='field')$d['fields']['valor']['dataType']='string';
   if($bad==='decimal')$b['audit']['after']['valor']=5.123456;if($bad==='date')$b['audit']['after']['data']='2026-02-30';
   if($bad==='manual')$b['audit']['before']['log_manual']=false;
   if($bad==='childKey')$b['audit']['changes'][0]['recordId']=21;if($bad==='childExtra')$b['audit']['changes'][0]['after']['valor']=2;
   if($bad==='childOperation')$b['audit']['changes'][0]['operation']='delete';
   if($bad==='childPredicate')$b['audit']['changes'][0]['before']['ordem_busca_dolar_compra']='1,2';
   if($bad==='childValue')$b['audit']['changes'][0]['after']['situacao']=2;
   if($bad==='childDate')$b['audit']['changes'][0]['after']['dt_hr_ult_alt_versao_custo']='2026-10-21T25:00:00.000';
   if($bad==='duplicate')$b['audit']['changes'][]=$b['audit']['changes'][0];
   if($bad==='wrongSeries')$b['audit']['after']['serie']=1;
   self::assertFalse(RuntimeCiwCotacaoAudit::inspect($d,$e,$b,'update',10)['metadata']['available'],$bad);
  }
 }
 public function testDeleteRequiresEmptyCurrentAndNoCostInvalidation(): void {
  $e=$this->endpoint();$e['bodyTemplate']['operation']='delete';$b=$this->body();$b['audit']['operation']='delete';$b['audit']['after']=[];$b['audit']['changes']=[];
  self::assertTrue(RuntimeCiwCotacaoAudit::inspect($this->definition(),$e,$b,'delete',10)['metadata']['available']);
  $b['audit']['changes']=$this->body()['audit']['changes'];self::assertFalse(RuntimeCiwCotacaoAudit::inspect($this->definition(),$e,$b,'delete',10)['metadata']['available']);
 }
 public function testExistingApiHookEmitsTheChildThroughCurrentTransactionLogger(): void {
  $transactions=$this->createMock(RuntimeTransactionService::class);
  $transactions->expects(self::once())->method('log')->with('hist_cotacoes_bc_progress.api.update',self::anything(),self::anything(),self::anything(),self::callback(fn($m)=>$m['audit']['recordId']===20));
  $r=new \ReflectionClass(RuntimeApiEntityActionService::class);$service=$r->newInstanceWithoutConstructor();$r->getProperty('transactions')->setValue($service,$transactions);
  $audit=$r->getMethod('writeAuditSnapshot')->invoke($service,$this->definition(),$this->endpoint(),$this->body(),'update',10);
  self::assertTrue($audit['metadata']['available']);self::assertSame(6,$audit['after']['valor']);
 }
}
