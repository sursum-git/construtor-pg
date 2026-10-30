<?php
namespace App\Tests\Runtime;
use App\Runtime\{RuntimeCiwParametroAudit,RuntimeApiEntityActionService,RuntimeTransactionService};
use PHPUnit\Framework\TestCase;
final class RuntimeCiwParametroAuditTest extends TestCase
{
 private function definition():array{return ['entityCode'=>'im_param_progress','primaryKey'=>'cod_param','apiBinding'=>['sourceCode'=>'motor_progress_ciw_cadastros'],'apiSource'=>['responseContract'=>'sursum-programs'],'fields'=>[
  'cod_param'=>['code'=>'cod_param','dataType'=>'string','jsonPath'=>'cod_param','collection'=>false],
  'codigo'=>['code'=>'codigo','dataType'=>'string','jsonPath'=>'cod_param','collection'=>false],
  'val_param'=>['code'=>'val_param','dataType'=>'string','jsonPath'=>'val_param','collection'=>false],
  'tp_param'=>['code'=>'tp_param','dataType'=>'integer','jsonPath'=>'tp_param','collection'=>false],
  'row_stamp'=>['code'=>'row_stamp','dataType'=>'string','jsonPath'=>'row_stamp','collection'=>false],
 ]];}
 private function endpoint():array{return ['method'=>'POST','url'=>'http://127.0.0.1:19910/med/web/SursumPrograms/ciw-cadastros','bodyTemplate'=>['operation'=>'update','resource'=>'im_param']];}
 private function row(string $hash='A'):array{return ['cod_param'=>"CODEx-a'ção",'val_param'=>'SYNTHETIC_ONLY','tp_param'=>1,'agrup_im_param_id'=>17,'row_stamp'=>str_repeat($hash,64)];}
 private function body():array{return ['audit'=>['version'=>1,'source'=>'progress-transaction','resource'=>'im_param','operation'=>'update','recordId'=>"CODEx-a'ção",'before'=>$this->row(),'after'=>$this->row('B')]];}
 public function testConfirmedStringIdentityKeepsSnapshotsButMasksParameterValues():void
 {
  $audit=RuntimeCiwParametroAudit::inspect($this->definition(),$this->endpoint(),$this->body(),'update',"CODEx-a'ção");
  self::assertTrue($audit['metadata']['available']);self::assertSame("CODEx-a'ção",$audit['metadata']['recordId']);
  self::assertSame('[redigido]',$audit['before']['val_param']);self::assertSame(17,$audit['after']['agrup_im_param_id']);
  self::assertSame(str_repeat('B',64),$audit['after']['row_stamp']);
 }
 public function testEntireIdentityBindingAndClosedSnapshotsAreRequired():void
 {
  foreach(['source','path','resource','key','id','extra','stamp','type','operation'] as $bad){$d=$this->definition();$e=$this->endpoint();$b=$this->body();$id="CODEx-a'ção";
   if($bad==='source')$d['apiBinding']['sourceCode']='other';if($bad==='path')$e['url']='http://127.0.0.1:19910/med/web/SursumPrograms/echo';
   if($bad==='resource')$e['bodyTemplate']['resource']='coloracoes';if($bad==='key')$d['primaryKey']='codigo';if($bad==='id')$id='outra';
   if($bad==='extra')$b['audit']['after']['unknown']='x';if($bad==='stamp')$b['audit']['after']['row_stamp']='bad';
   if($bad==='type')$b['audit']['after']['tp_param']='1';if($bad==='operation')$b['audit']['operation']='delete';
   self::assertFalse(RuntimeCiwParametroAudit::inspect($d,$e,$b,'update',$id)['metadata']['available'],$bad);
  }
 }
 public function testExistingSingleCrudAuditHookAcceptsTheStringContract():void
 {
  $class=new \ReflectionClass(RuntimeApiEntityActionService::class);$service=$class->newInstanceWithoutConstructor();
  $result=$class->getMethod('writeAuditSnapshot')->invoke($service,$this->definition(),$this->endpoint(),$this->body(),'update',"CODEx-a'ção");
  self::assertTrue($result['metadata']['available']);self::assertSame('[redigido]',$result['after']['val_param']);
 }
 public function testParameterPayloadValuesAreNeverPersistedInRequestContext():void
 {
  $class=new \ReflectionClass(RuntimeTransactionService::class);$service=$class->newInstanceWithoutConstructor();
  $result=$class->getMethod('redactSensitiveValues')->invoke($service,['record'=>['codigo'=>'smtp_password','val_param'=>'SYNTHETIC_ONLY','tp_param'=>1],'physical'=>['val-param'=>'SYNTHETIC_ONLY']]);
  self::assertArrayNotHasKey('val_param',$result['record']);self::assertArrayNotHasKey('val-param',$result['physical']);self::assertSame('smtp_password',$result['record']['codigo']);
 }
 public function testReadAndGetEventsDoNotCopyParameterValuesToTheJournal():void
 {
  $class=new \ReflectionClass(RuntimeTransactionService::class);$service=$class->newInstanceWithoutConstructor();
  $class->getProperty('current')->setValue($service,new \App\Entity\RuntimeTransaction());
  $em=$this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
  $em->expects(self::once())->method('persist')->with(self::callback(static function($log):bool{
   self::assertArrayNotHasKey('val_param',$log->getAfterData());self::assertArrayNotHasKey('val_param',$log->getBeforeData());
   self::assertSame('smtp_password',$log->getAfterData()['codigo']);return true;
  }));
  $class->getProperty('entityManager')->setValue($service,$em);
  $service->log('im_param_progress.api.get',before:['val_param'=>'SYNTHETIC_ONLY'],after:['codigo'=>'smtp_password','val_param'=>'SYNTHETIC_ONLY']);
 }
}
