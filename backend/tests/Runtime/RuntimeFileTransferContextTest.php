<?php
namespace App\Tests\Runtime;
use App\Runtime\RuntimeFileTransferContext;
use App\Runtime\RuntimeHttpException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use PHPUnit\Framework\TestCase;
final class RuntimeFileTransferContextTest extends TestCase
{
 public function testOnlyUploadedFileObjectsReachThePrivateContext():void {$c=new RuntimeFileTransferContext();$this->expectException(RuntimeHttpException::class);$c->begin(['arquivo_upload'=>['path'=>'/etc/passwd']]);}
 public function testSignedEndpointAndTokenAreRequiredBeforeFilesAreConsumed():void {$p=tempnam(sys_get_temp_dir(),'ciw-transfer-');file_put_contents($p,'%PDF-1.4');try{$c=new RuntimeFileTransferContext();$u=new UploadedFile($p,'teste.pdf','application/pdf',UPLOAD_ERR_OK,true);$c->begin(['arquivo_upload'=>$u]);$payload=['record'=>['arquivo_upload'=>'local-file:'.str_repeat('a',32)]];$c->assertEndpoint('entity.api.crud',['entityCode'=>'proc_compra_anexos_progress','operation'=>'create','fileFields'=>['arquivo_upload']],$payload);self::assertSame($u,$c->file('arquivo_upload'));self::assertTrue($c->hasFiles());$c->clear();self::assertFalse($c->hasFiles());}finally{unlink($p);}}
 public function testUnrelatedCrudRejectsMultipartBeforeBusinessDispatch():void {$p=tempnam(sys_get_temp_dir(),'ciw-transfer-');file_put_contents($p,'%PDF-1.4');try{$c=new RuntimeFileTransferContext();$c->begin(['arquivo_upload'=>new UploadedFile($p,'t.pdf',null,UPLOAD_ERR_OK,true)]);$this->expectException(RuntimeHttpException::class);$c->assertEndpoint('entity.api.crud',['entityCode'=>'empresas_progress','operation'=>'create'],['record'=>[]]);}finally{unlink($p);}}
 public function testDownloadResponseIsPrivateAndCannotBeConsumedTwice():void {$c=new RuntimeFileTransferContext();$c->begin([]);$r=new Response('bytes');$c->setDownload($r);self::assertSame($r,$c->takeDownload());self::assertNull($c->takeDownload());$c->clear();$this->expectException(RuntimeHttpException::class);$c->setDownload($r);}
 public function testBrowserStringsCannotSubstituteForUploadedBytes():void {$c=new RuntimeFileTransferContext();$c->begin([]);$this->expectException(RuntimeHttpException::class);$c->file('arquivo_upload');}
 public function testNestedOrMultipleFileContextsAreRejected():void {$c=new RuntimeFileTransferContext();$c->begin([]);$this->expectException(RuntimeHttpException::class);$c->begin([]);}
 public function testMultipartPayloadIsParsedSeparatelyFromUploadedBytes():void
 {
  $p=tempnam(sys_get_temp_dir(),'ciw-transfer-');file_put_contents($p,'%PDF-1.4');
  try {$u=new UploadedFile($p,'t.pdf',null,UPLOAD_ERR_OK,true);$r=new Request([],['payload'=>'{"record":{"arquivo_upload":"local-file:'.str_repeat('b',32).'"}}'],[],[],['arquivo_upload'=>$u],['CONTENT_TYPE'=>'multipart/form-data; boundary=test']);$c=new RuntimeFileTransferContext();$payload=$c->beginRequest($r);self::assertSame('local-file:'.str_repeat('b',32),$payload['record']['arquivo_upload']);self::assertSame($u,$c->file('arquivo_upload'));self::assertArrayNotHasKey('path',$payload);$c->clear();}finally{unlink($p);}
 }
 public function testMultipartExtraFieldsAreRejected():void {$c=new RuntimeFileTransferContext();$r=new Request([],['payload'=>'{}','path'=>'/etc/passwd'],[],[],[],['CONTENT_TYPE'=>'multipart/form-data']);$this->expectException(RuntimeHttpException::class);$c->beginRequest($r);}
 public function testJsonStillUsesTheSameTransportAndRejectsNonObjectPayload():void {$c=new RuntimeFileTransferContext();$r=new Request([],[],[],[],[],['CONTENT_TYPE'=>'application/json'],'{"id":12}');self::assertSame(['id'=>12],$c->beginRequest($r));self::assertFalse($c->hasFiles());$c->clear();$this->expectException(RuntimeHttpException::class);$c->beginRequest(new Request([],[],[],[],[],['CONTENT_TYPE'=>'application/json'],'"path"'));}
 public function testUnconsumedDownloadIsDiscardedWhenDispatchFails():void {$c=new RuntimeFileTransferContext();$c->begin([]);$discarded=0;$c->setDownload(new Response('bytes'),function()use(&$discarded){$discarded++;});$c->clear();self::assertSame(1,$discarded);$c->clear();self::assertSame(1,$discarded);$c->begin([]);$c->setDownload(new Response('bytes'),function()use(&$discarded){$discarded++;});$c->takeDownload();$c->clear();self::assertSame(1,$discarded);}
}
