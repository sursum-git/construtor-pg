<?php
namespace App\Tests\Ciw;
use App\Ciw\{CiwIdentityRegistration,CiwProgressIdentityClientInterface,CiwSqlGrantReaderInterface,CiwColoracoesPolicy};
use App\Entity\{AuthUser,AuthUserSubscriber,AuthSubscriber,AuthProviderConfig};
use App\Repository\{AuthUserRepository,AuthUserSubscriberRepository,AuthSubscriberRepository,AuthProviderConfigRepository};
use App\Runtime\{StructuralIntegrityService,RuntimeTransactionService,RuntimeHttpException};
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
final class CiwIdentityRegistrationTest extends TestCase
{
    private function service(string $case,array &$saved):CiwIdentityRegistration
    {
        $users=$this->createStub(AuthUserRepository::class);$links=$this->createStub(AuthUserSubscriberRepository::class);$subs=$this->createStub(AuthSubscriberRepository::class);$providers=$this->createStub(AuthProviderConfigRepository::class);
        $subs->method('findEnabledByCode')->willReturn((new AuthSubscriber())->setCode('default'));
        $providers->method('findOneBy')->willReturn((new AuthProviderConfig())->setCode('ima-user-web')->setType('ima-user-web')->setEnabled(true));
        $users->method('findOneByTenantAndUsername')->willReturn(in_array($case,['conflict','existing'])?(new AuthUser())->setUsername('tcassimiro')->setAuthSource($case==='conflict'?'local':'ima-user-web'):null);
        $links->method('findBy')->willReturn($case==='existing'?[(new AuthUserSubscriber())->setUsername('tcassimiro')->setSubscriberCode('default')->setDefaultSubscriber(true)->setMetadata(['ciwAuthorization'=>['version'=>1,'subject'=>'thiago.cassimiro','system'=>18]])]:[]);
        $em=$this->createMock(EntityManagerInterface::class);
        $em->expects(in_array($case,['conflict','existing'])?self::never():self::once())->method('wrapInTransaction')->willReturnCallback(fn($fn)=>$fn());
        $em->method('persist')->willReturnCallback(function($obj)use(&$saved){$saved[]=$obj;});
        $integrity=$this->createStub(StructuralIntegrityService::class);$tx=$this->createStub(RuntimeTransactionService::class);
        if($case==='auditFailure')$tx->method('success')->willThrowException(new \RuntimeException('synthetic private detail'));
        $id=$this->createStub(CiwProgressIdentityClientInterface::class);$grants=$this->createStub(CiwSqlGrantReaderInterface::class);$grants->method('read')->willReturn(['active'=>true,'grants'=>[['access'=>'Y']]]);
        return new CiwIdentityRegistration($users,$links,$subs,$providers,$em,$integrity,$tx,$id,$grants,new CiwColoracoesPolicy());
    }
    public function testCreatesOnlyApprovedAccountAndSignedLinkWithNoBusinessPermissions():void
    {
        $saved=[];$r=$this->service('new',$saved)->register();self::assertTrue($r['created']);self::assertCount(2,$saved);
        self::assertInstanceOf(AuthUser::class,$saved[0]);self::assertSame('tcassimiro',$saved[0]->getNormalizedUsername());self::assertNull($saved[0]->getPasswordHash());self::assertSame([],$saved[0]->getGroups());self::assertSame([],$saved[0]->getPermissions());self::assertSame('ima-user-web',$saved[0]->getAuthSource());
        self::assertInstanceOf(AuthUserSubscriber::class,$saved[1]);self::assertSame('thiago.cassimiro',$saved[1]->getMetadata()['ciwAuthorization']['subject']);self::assertSame(18,$saved[1]->getMetadata()['ciwAuthorization']['system']);
    }
    public function testConflictingAccountCannotBeOverwritten():void {$saved=[];$this->expectException(RuntimeHttpException::class);$this->service('conflict',$saved)->register();}
    public function testExactRegistrationIsIdempotent():void {$saved=[];$r=$this->service('existing',$saved)->register();self::assertTrue($r['unchanged']);self::assertFalse($r['created']);self::assertSame([],$saved);}
    public function testAuditFailurePropagatesOutOfTransactionAndIsSanitized():void
    {
        $saved=[];try{$this->service('auditFailure',$saved)->register();self::fail('accepted');}catch(RuntimeHttpException $e){self::assertSame(503,$e->getStatusCode());self::assertStringNotContainsString('synthetic',$e->getMessage());}
    }
}
