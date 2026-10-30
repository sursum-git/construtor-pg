<?php
namespace App\Tests\Attachments;
use App\Attachments\AttachmentStorageConfiguration;
use PHPUnit\Framework\TestCase;
final class AttachmentStorageConfigurationTest extends TestCase
{
 private string $dir;
 protected function setUp():void{$this->dir=sys_get_temp_dir().'/ciw-config-'.bin2hex(random_bytes(8));mkdir($this->dir);mkdir($this->dir.'/journal');file_put_contents($this->dir.'/key',str_repeat('x',32));file_put_contents($this->dir.'/ca.pem','test CA fixture, no network');}
 protected function tearDown():void{foreach(glob($this->dir.'/*')as$p){if(is_dir($p)){rmdir($p);}else{unlink($p);}}rmdir($this->dir);}
 private function config(array $extra=[]):AttachmentStorageConfiguration{$c=['url'=>'https://example.invalid/fixed/index.php','secretFile'=>$this->dir.'/key','journalDir'=>$this->dir.'/journal'];file_put_contents($this->dir.'/config.json',json_encode(array_replace($c,$extra),JSON_THROW_ON_ERROR));return new AttachmentStorageConfiguration($this->dir.'/config.json');}
 public function testOptionalCaBundleIsServerConfigurationAndDoesNotDisableTls():void{$settings=$this->config(['caFile'=>$this->dir.'/ca.pem'])->settings();self::assertSame(realpath($this->dir.'/ca.pem'),$settings['caFile']);self::assertSame('https://example.invalid/fixed/index.php',$settings['url']);}
 public function testAnAbsentCaBundleFailsClosed():void{$this->expectException(\RuntimeException::class);$this->config(['caFile'=>$this->dir.'/missing.pem'])->settings();}
 public function testPlainHttpAndUrlCredentialsAreNotAllowed():void{foreach(['http://example.invalid/file','https://user@example.invalid/file','https://user:pass@example.invalid/file']as$url){$error=null;try{$this->config(['url'=>$url])->settings();}catch(\RuntimeException$e){$error=$e;}self::assertNotNull($error);}}
}
