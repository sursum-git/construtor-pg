<?php
namespace App\Tests\Runtime;
use App\Attachments\AttachmentStorageClient;
use App\Attachments\AttachmentStorageConfiguration;
use App\Runtime\RuntimeAttachmentWriteCoordinator;
use App\Runtime\RuntimeHttpException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class RuntimeAttachmentWriteCoordinatorTest extends TestCase
{
 private string $dir;private string $file;
 protected function setUp():void {$this->dir=sys_get_temp_dir().'/ciw-coordinator-'.bin2hex(random_bytes(8));mkdir($this->dir);$this->file=$this->dir.'/test.pdf';file_put_contents($this->file,"%PDF-1.4\n%%EOF\n");}
 protected function tearDown():void {foreach(glob($this->dir.'/*') as $p){unlink($p);}rmdir($this->dir);}
 private function coordinator($storage):RuntimeAttachmentWriteCoordinator {$c=$this->createStub(AttachmentStorageConfiguration::class);$c->method('journalDirectory')->willReturn($this->dir);return new RuntimeAttachmentWriteCoordinator($storage,$c);}
 private function upload():UploadedFile {return new UploadedFile($this->file,'test.pdf',null,UPLOAD_ERR_OK,true);}
 private function receipt():array {$f=glob($this->dir.'/*.json');self::assertCount(1,$f);return json_decode(file_get_contents($f[0]),true,512,JSON_THROW_ON_ERROR);}
 public function testKnownCommitWritesOnceAndPreservesPrivateReceipt():void {
  $s=$this->createMock(AttachmentStorageClient::class);$s->expects(self::once())->method('put')->willReturn(['reference'=>'ciwatt_new_test.pdf']);$s->expects(self::once())->method('commit');$s->expects(self::never())->method('abort');$c=$this->coordinator($s);$calls=0;
  $result=$c->write($this->upload(),'local-file:'.str_repeat('a',32),'user',['item'=>4],function($ref)use(&$calls){$calls++;self::assertSame('ciwatt_new_test.pdf',$ref);return ['data'=>['id'=>12]];});self::assertSame(['data'=>['id'=>12]],$result);self::assertSame('committed',$this->receipt()['state']);self::assertSame(1,$calls);
  try{$c->write($this->upload(),'local-file:'.str_repeat('a',32),'user',['item'=>4],function()use(&$calls){$calls++;return [];});self::fail('Replay accepted');}catch(RuntimeHttpException $e){self::assertSame('ATTACHMENT_OPERATION_REPEATED',$e->getErrorCode());}self::assertSame(1,$calls);
 }
 public function testTimeoutNeverCompensatesOrRepeatsProgress():void {
  $s=$this->createMock(AttachmentStorageClient::class);$s->method('put')->willReturn(['reference'=>'ciwatt_new_test.pdf']);$s->expects(self::never())->method('abort');$s->expects(self::never())->method('commit');$c=$this->coordinator($s);
  try{$c->write($this->upload(),'local-file:'.str_repeat('b',32),'user',['item'=>4],static function(){throw new \RuntimeException('timeout');});self::fail('Uncertainty ignored');}catch(RuntimeHttpException $e){self::assertSame('ATTACHMENT_COMMIT_UNCERTAIN',$e->getErrorCode());self::assertTrue($e->getDetails()['reconciliationRequired']);}self::assertSame('progress_uncertain',$this->receipt()['state']);
 }
 public function testOnlyAnExplicitProgressRollbackCompensatesTheNewObject():void {
  $s=$this->createMock(AttachmentStorageClient::class);$s->method('put')->willReturn(['reference'=>'ciwatt_new_test.pdf']);$s->expects(self::once())->method('abort');$c=$this->coordinator($s);
  try{$c->write($this->upload(),'local-file:'.str_repeat('c',32),'user',['item'=>4],static function(){throw new RuntimeHttpException('STALE','stale',409,['progressCommitted'=>false]);});self::fail('Rollback ignored');}catch(RuntimeHttpException $e){self::assertSame('STALE',$e->getErrorCode());}self::assertSame('aborted',$this->receipt()['state']);
 }
 public function testStorageFailureDoesNotRunTheWriter():void {
  $s=$this->createMock(AttachmentStorageClient::class);$s->method('put')->willThrowException(new \RuntimeException('timeout'));$s->expects(self::never())->method('abort');$c=$this->coordinator($s);$calls=0;
  try{$c->write($this->upload(),'local-file:'.str_repeat('d',32),'user',['item'=>4],function()use(&$calls){$calls++;return [];});self::fail('Storage uncertainty ignored');}catch(RuntimeHttpException $e){self::assertSame('ATTACHMENT_STORAGE_UNCERTAIN',$e->getErrorCode());}self::assertSame(0,$calls);self::assertSame('storage_uncertain',$this->receipt()['state']);
 }
 public function testBrokerCommitFailureDoesNotReportAProgressRollback():void {
  $s=$this->createMock(AttachmentStorageClient::class);$s->method('put')->willReturn(['reference'=>'ciwatt_new_test.pdf']);$s->method('commit')->willThrowException(new \RuntimeException('timeout'));$s->expects(self::never())->method('abort');$c=$this->coordinator($s);self::assertSame(['id'=>2],$c->write($this->upload(),'local-file:'.str_repeat('e',32),'user',['item'=>4],fn()=>['id'=>2]));self::assertSame('committed',$this->receipt()['state']);self::assertArrayNotHasKey('storageCommitted',$this->receipt());
 }
 public function testHttpErrorWithoutCommittedFlagIsUncertain():void {
  $s=$this->createMock(AttachmentStorageClient::class);$s->method('put')->willReturn(['reference'=>'ciwatt_new_test.pdf']);$s->expects(self::never())->method('abort');$c=$this->coordinator($s);
  try{$c->write($this->upload(),'local-file:'.str_repeat('f',32),'user',['item'=>4],static function(){throw new RuntimeHttpException('ERROR','failed',422);});self::fail('Guessed rollback');}catch(RuntimeHttpException $e){self::assertSame('ATTACHMENT_COMMIT_UNCERTAIN',$e->getErrorCode());}self::assertSame('progress_uncertain',$this->receipt()['state']);
 }
 public function testInvalidContentIsAValidationErrorBeforeAnyStorageOrProgressCall():void {
  file_put_contents($this->file,'not a PDF');$s=$this->createMock(AttachmentStorageClient::class);$s->expects(self::never())->method('put');$c=$this->coordinator($s);
  try{$c->write($this->upload(),'local-file:'.str_repeat('a',32),'user',['item'=>4],static function(){throw new \LogicException('Writer must not run');});self::fail('Invalid content accepted');}catch(RuntimeHttpException $e){self::assertSame(422,$e->getStatusCode());self::assertSame('ATTACHMENT_POLICY_REJECTED',$e->getErrorCode());}self::assertSame([],glob($this->dir.'/*.json'));
 }
}
