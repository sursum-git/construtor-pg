<?php
namespace App\Tests\Runtime;
use App\Runtime\{RuntimeCiwCadastroAudit,RuntimeApiEntityActionService,RuntimeTransactionService};
use PHPUnit\Framework\TestCase;
final class RuntimeSampleFormatsAuditTest extends TestCase
{
 private function definition():array
 {
  return ['entityCode'=>'formatos_amostra_progress','primaryKey'=>'formato_amostra_id','apiBinding'=>['sourceCode'=>'motor_progress_ciw_cadastros'],'apiSource'=>['responseContract'=>'sursum-programs'],'fields'=>[
   'formato_amostra_id'=>['code'=>'formato_amostra_id','dataType'=>'integer','jsonPath'=>'formato_amostra_id','collection'=>false],
   'descricao'=>['code'=>'descricao','dataType'=>'string','jsonPath'=>'descricao','collection'=>false],
   'log_inativo'=>['code'=>'log_inativo','dataType'=>'boolean','jsonPath'=>'log_inativo','collection'=>false]]];
 }
 private function endpoint(string $op='update'):array{return ['method'=>'POST','url'=>'http://127.0.0.1:19910/med/web/SursumPrograms/ciw-cadastros','bodyTemplate'=>['resource'=>'formatos_amostra','operation'=>$op]];}
 private function row(string $text='Amostra',bool $inactive=false):array{return ['formato_amostra_id'=>14,'descricao'=>$text,'log_inativo'=>$inactive];}
 private function service():RuntimeApiEntityActionService
 {
  $r=new \ReflectionClass(RuntimeApiEntityActionService::class);$s=$r->newInstanceWithoutConstructor();
  (new \ReflectionProperty($s,'transactions'))->setValue($s,$this->createStub(RuntimeTransactionService::class));return $s;
 }
 public function testExactFixedBindingAndRoute():void
 {
  $d=$this->definition();$e=$this->endpoint();self::assertTrue(RuntimeCiwCadastroAudit::supports($d));self::assertSame('formatos_amostra',RuntimeCiwCadastroAudit::resource($d,$e,'update'));
  foreach([array_replace($d,['entityCode'=>'other']),array_replace($d,['primaryKey'=>'id']),array_replace($d,['apiBinding'=>['sourceCode'=>'other']])]as $bad)self::assertNull(RuntimeCiwCadastroAudit::resource($bad,$e,'update'));
  foreach([array_replace($e,['url'=>'https://other.invalid/SursumPrograms/ciw-cadastros']),array_replace($e,['method'=>'GET']),array_replace($e,['bodyTemplate'=>['resource'=>'formatos_amostra','operation'=>'delete']])]as $bad)self::assertNull(RuntimeCiwCadastroAudit::resource($d,$bad,'update'));
 }
 public function testCompleteTypedSnapshotsAndHistoricalText():void
 {
  $d=$this->definition();self::assertTrue(RuntimeCiwCadastroAudit::validSnapshot($d,$this->row(),'formatos_amostra'));self::assertTrue(RuntimeCiwCadastroAudit::validSnapshot($d,$this->row('Histórico'),'formatos_amostra'));self::assertTrue(RuntimeCiwCadastroAudit::validSnapshot($d,[],'formatos_amostra'));
  foreach([['formato_amostra_id'=>14],array_replace($this->row(),['formato_amostra_id'=>'14']),array_replace($this->row(),['log_inativo'=>1]),$this->row(str_repeat('A',41)),$this->row()+['secret'=>'synthetic']]as $bad)self::assertFalse(RuntimeCiwCadastroAudit::validSnapshot($d,$bad,'formatos_amostra'));
 }
 public function testExistingImporterAcceptsBeforeAndAfterAndRejectsExtraEnvelope():void
 {
  $s=$this->service();$m=new \ReflectionMethod($s,'writeAuditSnapshot');$d=$this->definition();$before=$this->row();$after=$this->row('Revisada',true);
  $audit=['version'=>1,'source'=>'progress-transaction','resource'=>'formatos_amostra','operation'=>'update','recordId'=>14,'before'=>$before,'after'=>$after];
  $proof=$m->invoke($s,$d,$this->endpoint(),['success'=>true,'audit'=>$audit],'update',14);self::assertTrue($proof['metadata']['available']);self::assertSame($before,$proof['before']);self::assertSame($after,$proof['after']);
  $audit['actor']=['login'=>'forged-user'];self::assertFalse($m->invoke($s,$d,$this->endpoint(),['success'=>true,'audit'=>$audit],'update',14)['metadata']['available']);
 }
 public function testDeleteKeepsPriorImageAndBatchUsesSameContract():void
 {
  $s=$this->service();$d=$this->definition();$before=$this->row();$m=new \ReflectionMethod($s,'writeAuditSnapshot');
  $audit=['version'=>1,'source'=>'progress-transaction','resource'=>'formatos_amostra','operation'=>'delete','recordId'=>14,'before'=>$before,'after'=>[]];$proof=$m->invoke($s,$d,$this->endpoint('delete'),['success'=>true,'audit'=>$audit],'delete',14);self::assertTrue($proof['metadata']['available']);self::assertSame($before,$proof['before']);self::assertSame([],$proof['after']);
  $changes=[['operation'=>'create','clientId'=>'new','data'=>['descricao'=>'Amostra','log_inativo'=>false]]];$body=['success'=>true,'results'=>[['operation'=>'create','clientId'=>'new','id'=>14]],'audit'=>['version'=>1,'source'=>'progress-transaction','resource'=>'formatos_amostra','operation'=>'batch','changes'=>[['operation'=>'create','clientId'=>'new','recordId'=>14,'before'=>[],'after'=>$before]]]];
  $m=new \ReflectionMethod($s,'batchAuditSnapshots');$proof=$m->invoke($s,$d,$this->endpoint('batch'),$body,$changes);self::assertTrue($proof['metadata']['available']);self::assertSame($before,$proof['rows']['new']['after']);
  unset($body['audit']['changes'][0]['after']['log_inativo']);self::assertFalse($m->invoke($s,$d,$this->endpoint('batch'),$body,$changes)['metadata']['available']);
 }
}
