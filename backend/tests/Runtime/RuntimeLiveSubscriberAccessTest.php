<?php
namespace App\Tests\Runtime;
use App\Auth\AuthService;
use App\Entity\{AuthUser,AuthUserSubscriber};
use App\Repository\{AuthUserRepository,AuthUserSubscriberRepository};
use App\Runtime\StructuralIntegrityService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class RuntimeLiveSubscriberAccessTest extends TestCase
{
    public function testRecomputesNativeOverridesAndRefusesDisabledAccount():void
    {
        $user=(new AuthUser())->setUsername('u')->setTenantId('a')->setStatus('active')->setPermissions(['base.read'=>true,'ciw.audit.read'=>true]);
        $link=(new AuthUserSubscriber())->setUsername('u')->setUserTenantId('a')->setSubscriberCode('a')->setPermissionOverrides(['ciw.audit.read'=>true]);
        $users=$this->createStub(AuthUserRepository::class);$users->method('findOneByTenantAndUsername')->willReturn($user);
        $exists=true;
        $links=$this->createStub(AuthUserSubscriberRepository::class);$links->method('findOneEnabledForUserAndSubscriber')->willReturnCallback(function()use(&$exists,$link){return $exists?$link:null;});
        $integrity=$this->createMock(StructuralIntegrityService::class);$integrity->expects(self::exactly(2))->method('assertAuthUserSubscriber')->with($link);
        $args=[];foreach((new \ReflectionClass(AuthService::class))->getConstructor()->getParameters()as $p){$t=$p->getType()->getName();$args[]=match($t){AuthUserRepository::class=>$users,AuthUserSubscriberRepository::class=>$links,StructuralIntegrityService::class=>$integrity,default=>$this->createStub($t)};}
        $auth=new AuthService(...$args);
        self::assertTrue($auth->resolveLiveSubscriberAccess('a','u','a')->getPermissions()['ciw.audit.read']);
        $link->setPermissionOverrides(['ciw.audit.read'=>false]);self::assertFalse($auth->resolveLiveSubscriberAccess('a','u','a')->getPermissions()['ciw.audit.read']);
        $exists=false;self::assertNull($auth->resolveLiveSubscriberAccess('a','u','a'));
        $user->setStatus('disabled');self::assertNull($auth->resolveLiveSubscriberAccess('a','u','a'));
    }
}
