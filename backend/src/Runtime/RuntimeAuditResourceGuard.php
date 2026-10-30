<?php
namespace App\Runtime;

use App\Auth\AuthenticatedSessionResolver;
use App\Entity\{RuntimeTransaction,RuntimeTransactionLog};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Operational users use the scoped runtime; raw ORM resources never bypass it. */
class RuntimeAuditResourceGuard implements EventSubscriberInterface
{
    public function __construct(private readonly AuthenticatedSessionResolver $sessions,private readonly RuntimeAuditAccessPolicy $policy) {}
    public static function getSubscribedEvents():array{return [KernelEvents::REQUEST=>['onKernelRequest',16]];}
    public function onKernelRequest(RequestEvent $event):void
    {
        $request=$event->getRequest();
        if(!in_array($request->attributes->get('_api_resource_class'),[RuntimeTransaction::class,RuntimeTransactionLog::class],true))return;
        $session=$this->sessions->resolve($request);
        if($session && $session->getStatus()==='active' && in_array($request->getMethod(),['GET','HEAD'],true)
            && $this->policy->effectivePermissions()->isAdministrator())return;
        $event->setResponse(new JsonResponse(['error'=>['code'=>'AUDIT_RAW_RESOURCE_FORBIDDEN','message'=>'Consulte o historico pelos endpoints autorizados do runtime.']],403));
    }
}
