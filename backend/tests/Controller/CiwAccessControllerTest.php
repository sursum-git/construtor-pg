<?php
namespace App\Tests\Controller;
use App\Controller\CiwAccessController;
use App\Ciw\CiwAccessService;
use App\Runtime\RuntimeHttpException;
use Symfony\Component\HttpFoundation\Request;
use PHPUnit\Framework\TestCase;
final class CiwAccessControllerTest extends TestCase
{
    public function testMalformedOversizedOrNonObjectBodiesNeverReachService():void
    {
        $s=$this->createMock(CiwAccessService::class);$s->expects(self::never())->method('authorize');$c=new CiwAccessController($s);
        foreach(['[]','null','{broken',str_repeat('x',16385)] as $raw){$r=$c->authorize(Request::create('/api/ciw/access','POST',content:$raw));self::assertSame(400,$r->getStatusCode());self::assertStringContainsString('no-store',$r->headers->get('Cache-Control'));}
    }
    public function testErrorIsSanitizedAndCannotBeCached():void
    {
        $s=$this->createStub(CiwAccessService::class);$s->method('authorize')->willThrowException(new RuntimeHttpException('CIW_SQL_UNAVAILABLE','password=synthetic_secret',503));
        $r=(new CiwAccessController($s))->authorize(Request::create('/api/ciw/access','POST',content:'{"operation":"authorize","resource":"cadastros.coloracoes","action":"read"}'));
        self::assertSame(503,$r->getStatusCode());self::assertStringNotContainsString('synthetic_secret',$r->getContent());
    }
}
