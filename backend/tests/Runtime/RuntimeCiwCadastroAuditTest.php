<?php
namespace App\Tests\Runtime;
use App\Runtime\{RuntimeCiwCadastroAudit,RuntimeApiEntityActionService};
use PHPUnit\Framework\TestCase;
final class RuntimeCiwCadastroAuditTest extends TestCase
{
 private function fixture():array
 {
  return ['entityCode'=>'coloracoes_progress','primaryKey'=>'coloracao_id','apiBinding'=>['sourceCode'=>'motor_progress_ciw_cadastros'],'fields'=>[
   'coloracao_id'=>['code'=>'coloracao_id','dataType'=>'integer','jsonPath'=>'coloracao_id','collection'=>false],
   'descricao'=>['code'=>'descricao','dataType'=>'string','jsonPath'=>'descricao','collection'=>false],
   'log_inativo'=>['code'=>'log_inativo','dataType'=>'boolean','jsonPath'=>'log_inativo','collection'=>false],
  ]];
 }
 private function endpoint():array{return ['method'=>'POST','url'=>'http://127.0.0.1:19910/med/web/SursumPrograms/ciw-cadastros','bodyTemplate'=>['operation'=>'update','resource'=>'coloracoes']];}
 public function testTrustRequiresExactEntitySourceResourceOperationAndRoute():void
 {
  $d=$this->fixture();$e=$this->endpoint();self::assertSame('coloracoes',RuntimeCiwCadastroAudit::resource($d,$e,'update'));
  foreach(['source','key','resource','operation','method','host','path'] as $bad){$x=$d;$y=$e;
   if($bad==='source')$x['apiBinding']['sourceCode']='other';if($bad==='key')$x['primaryKey']='id';
   if($bad==='resource')$y['bodyTemplate']['resource']='motivos_desconto';if($bad==='operation')$y['bodyTemplate']['operation']='create';if($bad==='method')$y['method']='GET';
   if($bad==='host')$y['url']='http://other.invalid/med/web/SursumPrograms/ciw-cadastros';if($bad==='path')$y['url']='http://127.0.0.1:19910/med/web/SursumPrograms/echo';
   self::assertNull(RuntimeCiwCadastroAudit::resource($x,$y,'update'),$bad);
  }
 }
 public function testSnapshotsAreCompleteClosedAndTyped():void
 {
  $d=$this->fixture();$good=['coloracao_id'=>4,'descricao'=>'Ação','log_inativo'=>false];
  self::assertTrue(RuntimeCiwCadastroAudit::validSnapshot($d,$good,'coloracoes'));
  self::assertTrue(RuntimeCiwCadastroAudit::validSnapshot($d,[],'coloracoes'));
  self::assertFalse(RuntimeCiwCadastroAudit::validSnapshot($d,$good+['password'=>'synthetic'],'coloracoes'));
  foreach([['coloracao_id'=>4],array_replace($good,['coloracao_id'=>'4']),array_replace($good,['log_inativo'=>1]),array_replace($good,['descricao'=>str_repeat('á',51)])] as $bad)self::assertFalse(RuntimeCiwCadastroAudit::validSnapshot($d,$bad,'coloracoes'));
 }
 public function testExistingAuditImporterRecognizesNewResourceAndRejectsIncompleteSnapshot():void
 {
  $class=new \ReflectionClass(RuntimeApiEntityActionService::class);$service=$class->newInstanceWithoutConstructor();
  self::assertSame('coloracoes',$class->getMethod('trustedAuditResource')->invoke($service,$this->fixture(),$this->endpoint(),'update'));
  self::assertNull($class->getMethod('mapConfirmedAuditSnapshot')->invoke($service,$this->fixture(),['coloracao_id'=>4],'coloracoes'));
 }
 public function testBatchImportsConfirmedRowsAndRejectsForgedBinding():void
 {
  $class=new \ReflectionClass(RuntimeApiEntityActionService::class);$service=$class->newInstanceWithoutConstructor();
  $method=$class->getMethod('batchAuditSnapshots');$d=$this->fixture();$d['apiSource']=['responseContract'=>'sursum-programs'];
  $e=$this->endpoint();$e['bodyTemplate']['operation']='batch';
  $row=['coloracao_id'=>4,'descricao'=>'Ação','log_inativo'=>false];
  $changes=[['operation'=>'create','clientId'=>'a','data'=>['descricao'=>'Ação']]];
  $body=['results'=>[['operation'=>'create','clientId'=>'a','id'=>4]],'audit'=>['version'=>1,'source'=>'progress-transaction','resource'=>'coloracoes','operation'=>'batch','changes'=>[['operation'=>'create','clientId'=>'a','recordId'=>4,'before'=>[],'after'=>$row]]]];
  $proof=$method->invoke($service,$d,$e,$body,$changes);
  self::assertTrue($proof['metadata']['available']);self::assertSame($row,$proof['rows']['a']['after']);
  $e['bodyTemplate']['resource']='motivos_desconto';
  self::assertFalse($method->invoke($service,$d,$e,$body,$changes)['metadata']['available']);
 }
}
