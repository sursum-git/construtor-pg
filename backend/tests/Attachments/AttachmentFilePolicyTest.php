<?php
namespace App\Tests\Attachments;
use App\Attachments\AttachmentFilePolicy;
use PHPUnit\Framework\TestCase;
final class AttachmentFilePolicyTest extends TestCase
{
 private string $temp;
 protected function setUp():void {$this->temp=tempnam(sys_get_temp_dir(),'ciw-policy-');}
 protected function tearDown():void {if(is_file($this->temp))unlink($this->temp);}
 private function put(string $text):void {file_put_contents($this->temp,$text);}
 private function rejected(callable $work,string $message=''):void {$error=null;try{$work();}catch(\RuntimeException $e){$error=$e;}self::assertNotNull($error,'Expected policy rejection');if($message!=='')self::assertStringContainsString($message,$error->getMessage());}
 public function testPdfContentAndServerHashWithExactByteLimit():void {$bytes="%PDF-1.4\nTest attachment\n%%EOF\n";$this->put($bytes);$r=AttachmentFilePolicy::inspect($this->temp,'Relatório 2026.pdf',strlen($bytes));self::assertSame('pdf',$r['extension']);self::assertSame(strlen($bytes),$r['size']);self::assertSame(hash('sha256',$bytes),$r['sha256']);self::assertSame('application/pdf',$r['contentType']);}
 public function testOverLimitAndEmptyAreRefused():void {foreach(['','%PDF-1.4 beyond']as$bytes){$this->put($bytes);$this->rejected(fn()=>AttachmentFilePolicy::inspect($this->temp,'test.pdf',3),'size');}}
 public function testForgedExtensionAndActiveContentAreRefused():void {foreach([['report.pdf',"<?php echo 'x';"],['payload.php','%PDF-1.4'],['image.svg','<svg/>'],['report.pdf.php','%PDF-1.4'],['script.html','<html/>']]as[$name,$bytes]){$this->put($bytes);$this->rejected(fn()=>AttachmentFilePolicy::inspect($this->temp,$name,1024));}}
 public function testTraversalControlAndWindowsNamesAreRefused():void {foreach(['../doc.pdf','folder/doc.pdf','C:\\doc.pdf',"bad\0.pdf","bad\r\n.pdf",'.htaccess','CON.pdf']as$name){$this->put('%PDF-1.4');$this->rejected(fn()=>AttachmentFilePolicy::inspect($this->temp,$name,1024),'name');}}
 public function testPngJpegAndZipSignaturesAreSupported():void {foreach([['test.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j6n0AAAAASUVORK5CYII=')],['test.jpg',hex2bin('ffd8ffe000104a46494600010100000100010000ffdb')],['test.zip',"PK\x05\x06".str_repeat("\0",18)]]as[$name,$bytes]){$this->put($bytes);$r=AttachmentFilePolicy::inspect($this->temp,$name,2048);self::assertSame(strlen($bytes),$r['size']);}}
 public function testKnownOfficeContainersAreRecognizedWithoutExtraction():void {$zip=new \ZipArchive();$zip->open($this->temp,\ZipArchive::OVERWRITE);$zip->addFromString('[Content_Types].xml','<Types/>');$zip->addFromString('word/document.xml','<document/>');$zip->close();$r=AttachmentFilePolicy::inspect($this->temp,'doc.docx',4096);self::assertSame('docx',$r['extension']);self::assertSame('application/vnd.openxmlformats-officedocument.wordprocessingml.document',$r['contentType']);}
 public function testRenamedZipIsNotAnOfficeDocument():void {$this->put("PK\x05\x06".str_repeat("\0",18));$this->expectException(\RuntimeException::class);AttachmentFilePolicy::inspect($this->temp,'doc.docx',4096);}
 public function testUnknownSizePolicyIsRefused():void {$this->put('%PDF-1.4');$this->expectException(\RuntimeException::class);AttachmentFilePolicy::inspect($this->temp,'doc.pdf',0);}
}
