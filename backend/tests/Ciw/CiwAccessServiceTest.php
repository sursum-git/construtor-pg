<?php
namespace App\Tests\Ciw;
use App\Ciw\{CiwAccessService,CiwSubjectResolver,CiwSqlGrantReaderInterface,CiwProgressIdentityClientInterface,CiwColoracoesPolicy};
use App\Auth\{AuthService,AuthenticatedSessionResolver};
use App\Entity\RuntimeUserSession;
use App\Runtime\RuntimeHttpException;
use Symfony\Component\HttpFoundation\Request;
use PHPUnit\Framework\TestCase;
final class CiwAccessServiceTest extends TestCase
{
    private function build(string $case):CiwAccessService
    {
        $s=(new RuntimeUserSession())->setTenantId('default')->setUserId('tcassimiro')->setSessionId('test-session');
        $s->setSessionProperties(['authentication'=>['provider'=>'ima-user-web']]);
        if(in_array($case,['expired','revoked']))$s->setStatus($case);
        $auth=$this->createStub(AuthService::class);$auth->method('currentSession')->willReturn(['authenticated'=>true,'tenantId'=>'default','session'=>['sessionId'=>'test-session']]);
        $sessions=$this->createStub(AuthenticatedSessionResolver::class);$sessions->method('resolve')->willReturn($case==='absent'?null:$s);
        $subjects=$this->createStub(CiwSubjectResolver::class);$subjects->method('resolve')->willReturn('thiago.cassimiro');
        $identity=$this->createMock(CiwProgressIdentityClientInterface::class);
        $reader=$this->createMock(CiwSqlGrantReaderInterface::class);
        $blocked=in_array($case,['expired','revoked','absent','forged']);
        $identity->expects($blocked?self::never():self::once())->method('assertActive');
        if($blocked)$reader->expects(self::never())->method('read');
        else $reader->expects(self::once())->method('read')->with('thiago.cassimiro','grid_pub_coloracoes')->willReturn(['active'=>$case!=='inactive','grants'=>$case==='denied'?[]:[['access'=>'Y']]]);
        return new CiwAccessService($auth,$sessions,$subjects,$identity,$reader,new CiwColoracoesPolicy());
    }
    public function testAllowsRealBindingWithoutReturningPrivateSubject():void
    {
        $r=$this->build('valid')->authorize(new Request(),['operation'=>'authorize','resource'=>'cadastros.coloracoes','action'=>'read']);
        self::assertTrue($r['data'][0]['allowed']);self::assertSame('legacy_ciw',$r['data'][0]['scope']);self::assertStringNotContainsString('thiago.cassimiro',json_encode($r));
    }
    public function testDeniedAndInvalidSessionsNeverProduceAllow():void
    {
        foreach(['expired','revoked','absent','forged','inactive','denied'] as $case){$svc=$this->build($case);$p=['operation'=>'authorize','resource'=>'cadastros.coloracoes','action'=>'read'];if($case==='forged')$p['subject']='admin';
            try{$svc->authorize(new Request(),$p);self::fail($case);}catch(RuntimeHttpException $e){self::assertSame(in_array($case,['expired','revoked','absent'])?401:($case==='forged'?400:403),$e->getStatusCode());}
        }
    }
}
