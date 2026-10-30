<?php
namespace App\Tests\Runtime;
use App\Entity\RuntimeTransaction;
use App\Runtime\{RuntimeAuditResourceGuard,RuntimeAuditAccessPolicy,PermissionResolver};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\{Request,RequestStack};
use Symfony\Component\HttpKernel\{HttpKernelInterface,KernelEvents};
use Symfony\Component\HttpKernel\Event\RequestEvent;

final class RuntimeAuditResourceGuardTest extends TestCase
{
    public function testRawResourcesRequireAuthenticatedAdministratorAndAreReadOnly():void
    {
        foreach([[false,false,'GET',false],[true,false,'GET',false],[true,true,'GET',true],[true,true,'DELETE',false]]as [$signed,$admin,$verb,$expected]){
            $session=$this->createStub(\App\Auth\AuthenticatedSessionResolver::class);$session->method('resolve')->willReturn($signed?new \App\Entity\RuntimeUserSession():null);
            $p=new class(new RequestStack(),$admin)extends PermissionResolver{public function __construct($s,private bool $admin){parent::__construct($s);}public function isAdministrator():bool{return $this->admin;}};
            $policy=$this->createStub(RuntimeAuditAccessPolicy::class);$policy->method('effectivePermissions')->willReturn($p);
            $guard=new RuntimeAuditResourceGuard($session,$policy);
            $request=Request::create('/api/runtime_transactions', $verb);$request->attributes->set('_api_resource_class',RuntimeTransaction::class);
            $event=new RequestEvent($this->createStub(HttpKernelInterface::class),$request,HttpKernelInterface::MAIN_REQUEST);
            $guard->onKernelRequest($event);
            self::assertSame(!$expected,$event->hasResponse());
            if(!$expected)self::assertSame(403,$event->getResponse()->getStatusCode());
        }
    }
}
