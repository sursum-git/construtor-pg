<?php
namespace App\Tests\Ciw;
use App\Ciw\CiwSqlTunnelOwnership;
use App\Runtime\RuntimeHttpException;
use PHPUnit\Framework\TestCase;
final class CiwSqlTunnelOwnershipTest extends TestCase
{
    public function testOnlyListenerOwnedByManagedTunnelIsAccepted():void
    {
        $f=tempnam(sys_get_temp_dir(),'ciw-state-');
        try{
            file_put_contents($f,json_encode(['pid'=>1234,'port'=>19933,'host'=>'127.0.0.1','sshHost'=>'192.168.0.170','destination'=>'192.168.0.170:1433']));
            (new CiwSqlTunnelOwnership($f,fn()=>1234))->assertOwned();self::assertTrue(true);
            foreach([4321,null] as $other){try{(new CiwSqlTunnelOwnership($f,fn()=>$other))->assertOwned();self::fail('foreign listener accepted');}catch(RuntimeHttpException $e){self::assertSame(503,$e->getStatusCode());}}
            unlink($f);
            try{(new CiwSqlTunnelOwnership($f,fn()=>1234))->assertOwned();self::fail('missing state accepted');}catch(RuntimeHttpException $e){self::assertSame(503,$e->getStatusCode());}
        }finally{if(is_file($f))unlink($f);}
    }
}
