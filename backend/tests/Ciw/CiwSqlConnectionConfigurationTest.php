<?php
namespace App\Tests\Ciw;
use App\Ciw\CiwSqlConnectionConfiguration;
use App\Runtime\RuntimeHttpException;
use PHPUnit\Framework\TestCase;
final class CiwSqlConnectionConfigurationTest extends TestCase
{
    private string $file;
    protected function setUp():void {$this->file=tempnam(sys_get_temp_dir(),'ciw-test-');}
    protected function tearDown():void {unlink($this->file);}
    private function config(array $changes=[]):array{return array_replace(['host'=>'127.0.0.1','port'=>19933,'database'=>'ticontrole','username'=>'test_service','password'=>'synthetic_secret','timeoutSeconds'=>8],$changes);}
    public function testAcceptsOnlyFixedTunnelAndDoesNotAcceptDsn():void
    {
        file_put_contents($this->file,json_encode($this->config()));
        $c=(new CiwSqlConnectionConfiguration($this->file,$this->createStub(\App\Ciw\CiwSqlTunnelOwnership::class)))->resolve();self::assertSame(19933,$c['port']);
        foreach([['host'=>'192.168.0.170'],['port'=>1433],['database'=>'other'],['timeoutSeconds'=>11],['dsn'=>'odbc:secret'],['username'=>'a;Password=secret'],['extra'=>'x']] as $bad){
            file_put_contents($this->file,json_encode($this->config($bad)));
            try{(new CiwSqlConnectionConfiguration($this->file,$this->createStub(\App\Ciw\CiwSqlTunnelOwnership::class)))->resolve();self::fail('invalid config accepted');}
            catch(RuntimeHttpException $e){self::assertSame(503,$e->getStatusCode());self::assertStringNotContainsString('synthetic_secret',$e->getMessage());}
        }
    }
    public function testUnreadableOversizedAndMalformedConfigurationFailClosed():void
    {
        foreach(['{broken',str_repeat('x',8193),'null'] as $body){file_put_contents($this->file,$body);
            try{(new CiwSqlConnectionConfiguration($this->file,$this->createStub(\App\Ciw\CiwSqlTunnelOwnership::class)))->resolve();self::fail('accepted');}catch(RuntimeHttpException $e){self::assertSame(503,$e->getStatusCode());}
        }
        $this->expectException(RuntimeHttpException::class);(new CiwSqlConnectionConfiguration($this->file.'.missing',$this->createStub(\App\Ciw\CiwSqlTunnelOwnership::class)))->resolve();
    }
}
